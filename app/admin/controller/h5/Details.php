<?php

namespace app\admin\controller\h5;

use app\admin\model\spend\Data as SpendData;
use app\admin\model\xpark\Domain;
use sdk\QueryTimeStamp;
use Throwable;
use app\admin\model\sls\Active as SLSActive;
use app\admin\model\xpark\Apps;
use app\common\controller\Backend;

/**
 * 投放管理
 */
class Details extends Backend
{

    protected object $model;

    protected array|string $preExcludeFields = ['id'];

    protected string|array $quickSearchField = ['id'];

    protected array $apps = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\xpark\Utc();
        $apps        = Apps::alias('apps')->field(['apps.*'])->select()->toArray();
        $this->apps  = array_column($apps, null, 'id');
    }

    public function index(): void
    {
        QueryTimeStamp::start();
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $field      = [
            'utc.app_id',
            'utc.a_date',
            'SUM(utc.ad_revenue) AS total_revenue',
            'SUM(CASE WHEN channel_type = 0 THEN ad_revenue ELSE 0 END) AS h5_revenue',
            'SUM(CASE WHEN channel_type = 1 THEN ad_revenue ELSE 0 END) AS native_revenue',
            'IFNULL(active.new_users, 0) AS h5_new_users',
            'IFNULL(active.active_users, 0) AS h5_active_users',
            'IFNULL(spend.total_spend, 0) AS total_spend'
        ];
        $active_sql = SLSActive::field([
            'date', 'app_id', 'SUM(new_users) AS new_users', 'SUM(active_users) AS active_users'
        ])->group('date, app_id')->buildSql();
        $spend_sql  = SpendData::field(['date', 'app_id', 'SUM(spend) AS total_spend'])->group('date, app_id')->buildSql();

        $res = $this->model->field($field)
            ->alias($alias)
            ->where('utc.status', 0)
            ->join($active_sql . ' active', 'utc.a_date = active.date AND utc.app_id = active.app_id', 'left')
            ->join($spend_sql . ' spend', 'utc.a_date = spend.date AND utc.app_id = spend.app_id', 'left')
            ->where($where)
            ->order('utc.a_date', 'desc')
            ->order($order)
            ->group('utc.a_date, utc.app_id');

        $sql  = $res->fetchSql(true)->select();
        $res  = $res->paginate($limit);
        $list = $this->rate($res->items());


        $this->success('', [
            '_'      => $this->auth->id == 1 ? $sql : '',
            'list'   => $list,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
            'ts'     => QueryTimeStamp::end()
        ]);
    }

    protected function rate($data)
    {
        foreach ($data as &$v) {
            $v['roi']            = $v['total_spend'] > 0
                ? number_format((float)$v['total_revenue'] / (float)$v['total_spend'] * 100, 2, '.', '') . '%'
                : '-';
            $v['h5_arpu']        = $v['h5_active_users'] > 0
                ? number_format((float)$v['h5_revenue'] / $v['h5_active_users'], 2, '.', '')
                : '-';
            $v['h5_revenue']     = number_format((float)$v['h5_revenue'], 2, '.', '');
            $v['native_revenue'] = number_format((float)$v['native_revenue'], 2, '.', '');
            $v['total_revenue']  = number_format((float)$v['total_revenue'], 2, '.', '');
            $v['total_spend']    = number_format((float)$v['total_spend'], 2, '.', '');
            $v['app_name']       = isset($v['app_id']) && isset($this->apps[$v['app_id']]) ? $this->apps[$v['app_id']]['app_name'] : '-';
        }
        return $data;
    }


}