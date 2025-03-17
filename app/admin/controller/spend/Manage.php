<?php

namespace app\admin\controller\spend;

use app\admin\model\xpark\Utc;
use app\admin\model\spend\Data as SpendData;
use app\common\controller\Backend;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use sdk\FeishuBot;
use sdk\QueryTimeStamp;
use think\facade\Env;
use Exception;
use app\admin\model\spend\Manage as ManageModel;

/**
 * 投放操作
 */
class Manage extends Backend
{
    /**
     * Manage模型对象
     * @var object
     * @phpstan-var ManageModel
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    protected Client $http;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ManageModel();
        $this->http  = new Client([
            'verify' => false,
            'proxy'  => [
                'http'  => Env::get('PROXY.HTTP_PROXY'),
                'https' => Env::get('PROXY.HTTP_PROXY'),
            ]
        ]);
    }

    public function index(): void
    {
        QueryTimeStamp::start();
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $revenue_map = $spend_map = [];
        foreach ($where as $k => $v) {
            if ($v[0] == 'manage.a_date') {
                $revenue_map[] = ['a_date', $v[1], $v[2]];
                $spend_map[]   = ['date', $v[1], $v[2]];
                unset($where[$k]);
            }
        }
        $where = array_values($where);

        $revenue_sql = Utc::field(['domain_id', 'country_code', 'SUM(ad_revenue) AS total_revenue'])->where($revenue_map)->group('domain_id,country_code')->buildSql();
        $spend_sql   = SpendData::field(['campaign_id', 'country_code', 'SUM(spend) AS total_spend'])->where($spend_map)->group('campaign_id, country_code')->buildSql();


        $res = $this->model
            ->alias($alias)
            ->field(['manage.*', 'domain.domain as domain_name', 'revenue.total_revenue', 'spend.total_spend'])
            ->join('xpark_domain domain', 'domain.id = manage.domain_id', 'left')
            ->leftJoin([$revenue_sql => 'revenue'], 'revenue.domain_id = manage.domain_id AND revenue.country_code = manage.country_code')
            ->leftJoin([$spend_sql => 'spend'], 'spend.campaign_id = manage.campaign_id AND spend.country_code = manage.country_code')
            ->order('manage.domain_id', 'desc')
            ->where($where)
            ->where('domain.channel', '<>', 'AppLovin')
            ->order($order);
        $sql = $res->fetchSql(true)->select();
        $res = $res->paginate($limit);

        $list = [];
        foreach ($res->items() as $v) {
            $v['roi']           = $v['total_spend'] > 0 ? number_format((float)$v['total_revenue'] / (float)$v['total_spend'] * 100, 2, '.', '') . '%' : '-';
            $v['total_revenue'] = round($v['total_revenue'] ?? 0, 2);
            $v['total_spend']   = round($v['total_spend'] ?? 0, 2);
            $list[]             = $v;
        }

        $this->success('', [
            'list'   => $list,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
            'sql'    => $sql,
            'ts'     => QueryTimeStamp::end()
        ]);
    }


    public function sync(): void
    {
        try {
            (new \app\command\Spend\Manage())->tiktok();
        } catch (Exception|GuzzleException $e) {
            $this->error($e->getMessage());
        }
        $this->success();
    }

    public function switch(): void
    {
        $id     = $this->request->post('id/s', '');
        $status = $this->request->post('status/d', 0);
        $item   = $this->model->where('id', $id)->find();
        if (!$item) $this->error('计划不存在');

        try {
            $res = $this->http->request('POST', 'https://business-api.tiktok.com/open_api/v1.3/campaign/status/update/', [
                'json'    => [
                    "advertiser_id"    => (string)$item['advertiser_id'],
                    "campaign_ids"     => [(string)$item['campaign_id']],
                    "operation_status" => $status == 0 ? "DISABLE" : "ENABLE"
                ],
                'headers' => [
                    'Access-Token' => Env::get('SPEND.TIKTOK_TOKEN'),
                    'Content-Type' => 'application/json'
                ]
            ]);
        } catch (Exception $e) {
            $this->error('请求失败', $e->getMessage());
        }
        $res = json_decode($res->getBody()->getContents(), true);
        if ($res['code'] != 0) {
            $this->error('请求失败 :' . $res['message']);
        }
        $item->status = $res['data']['status'] == "ENABLE" ? 1 : 0;
        $item->save();
        $this->success('', $item);
    }

    public function budget(): void
    {
        $id     = $this->request->post('id/s', '');
        $budget = $this->request->post('budget/f', 0);
        $item   = $this->model->where('id', $id)->find();
        if (!$item) $this->error('计划不存在');
        $url = $item->smart_switch == 1
            ? 'https://business-api.tiktok.com/open_api/v1.3/campaign/spc/update/'
            : 'https://business-api.tiktok.com/open_api/v1.3/campaign/update/';

        try {
            $res = $this->http->request('POST', $url, [
                'json'    => [
                    "advertiser_id" => (string)$item['advertiser_id'],
                    "campaign_id"   => (string)$item['campaign_id'],
                    "budget"        => $budget
                ],
                'headers' => [
                    'Access-Token' => Env::get('SPEND.TIKTOK_TOKEN'),
                    'Content-Type' => 'application/json'
                ]
            ]);
        } catch (Exception $e) {
            $this->error('请求失败', $e->getMessage());
        }
        $res = json_decode($res->getBody()->getContents(), true);
        if ($res['code'] != 0) {
            $this->error('请求失败 :' . $res['message']);
        }
        $item->budget = $res['data']['budget'];
        $item->save();
        $this->success('', $item);
    }

}