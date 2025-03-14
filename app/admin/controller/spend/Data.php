<?php

namespace app\admin\controller\spend;

use app\admin\model\xpark\Domain;
use sdk\QueryTimeStamp;
use Throwable;
use app\admin\model\xpark\Activity;
use app\admin\model\xpark\Apps;
use app\common\controller\Backend;

/**
 * 投放管理
 */
class Data extends Backend
{
    /**
     * Data模型对象
     * @var object
     * @phpstan-var \app\admin\model\spend\Data
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id'];

    protected string|array $quickSearchField = ['id'];

    protected array $domains = [];
    protected array $apps    = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\spend\Data();

        $domains       = Domain::alias('domain')
            ->field(['domain.*', 'admin.nickname'])
            ->join('admin admin', 'admin.id = domain.admin_id', 'left')
            ->select()->toArray();
        $this->domains = array_column($domains, null, 'id');
        $apps          = Apps::alias('apps')->field(['apps.*'])->select()->toArray();
        $this->apps    = array_column($apps, null, 'id');
    }

    protected function calcData()
    {
        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        $dimensions = $this->request->get('dimensions/a', []);
        $dimension  = [];
        foreach ($dimensions as $k => $v) {
            if ($k && $v == 'true') {
                $dimension[] = 'data.' . $k;
            }
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $field = array_merge($dimension, [
            'data.channel_name',
            'data.domain_id',
            'data.country_level',
            'data.country_name',
            'SUM(data.spend) AS spend',
            'SUM(data.clicks) AS clicks',
            'SUM(data.impressions) AS impressions',
            'SUM(data.conversion) AS conversion',
            'SUM(data.install) AS install',
            'SUM(data.starts) AS starts',
        ]);


        $res = $this->model->field($field)
            ->alias($alias)
            ->where('status', 0)
            ->where($where);

        unset($order['data.id']);

        $res = $res->order($order)->order('date', 'desc')
            ->group(implode(',', $dimension));

        return [$res, $limit, $dimension];
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        QueryTimeStamp::start();
        [$res, $limit, $dimension] = $this->calcData();
        $sql = $res->fetchSql(true)->select();
        $res = $res->paginate($limit);
        $res->visible(['domain' => ['domain']]);

        $list = $this->rate($res->items(), $dimension);

        $this->success('', [
            '_'      => $this->auth->id == 1 ? $sql : '',
            'list'   => $list,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
            'ts'     => QueryTimeStamp::end()
        ]);
    }

    protected function rate($data, $dimension)
    {
        foreach ($data as $k => &$v) {
            $v['date']   .= ' 00:00:00';
            $clicks      = $v['clicks'];
            $impressions = $v['impressions'];
            $spend       = $v['spend'];

            $v['cpc']      = round(empty($impressions) ? 0 : $clicks / $impressions, 3);
            $v['cpm']      = round(empty($impressions) ? 0 : $spend / $impressions * 1000, 3);
            $v['spend']    = round($v['spend'], 3);
            $v['app_name'] = isset($v['app_id']) && isset($this->apps[$v['app_id']]) ? $this->apps[$v['app_id']]['app_name'] : '-';

            if (in_array('data.domain_id', $dimension)) {
                if (!isset($this->domains[$v['domain_id']]['domain'])) {
                    unset($data[$k]);
                    continue;
                }
                $v['domain_name'] = $this->domains[$v['domain_id']]['domain'];
            }
        }
        return array_values($data);
    }


}