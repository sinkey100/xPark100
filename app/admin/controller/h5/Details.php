<?php

namespace app\admin\controller\h5;

use app\admin\model\spend\Data as SpendData;
use app\admin\model\xpark\Domain;
use sdk\QueryTimeStamp;
use think\facade\Db;
use Throwable;
use app\admin\model\sls\Active as SLSActive;
use app\admin\model\app\Active as AppActive;
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

        $field          = [
            'utc.app_id',
            'utc.a_date',
            'SUM(utc.ad_revenue) AS total_revenue',
            'SUM(CASE WHEN channel_type = 0 THEN ad_revenue ELSE 0 END) AS h5_revenue',
            'SUM(CASE WHEN channel_type = 1 THEN ad_revenue ELSE 0 END) AS native_revenue',
            'IFNULL(h5_active.new_users, 0) AS h5_new_users',
            'IFNULL(h5_active.active_users, 0) AS h5_active_users',
            'IFNULL(app_active.new_users, 0) AS app_new_users',
            'IFNULL(app_active.active_users, 0) AS app_active_users',
            'IFNULL(spend.total_spend, 0) AS total_spend'
        ];
        $h5_active_sql  = Db::table(SLSActive::field([
                'date', 'app_id', 'SUM(new_users) AS new_users', 'SUM(active_users) AS active_users'
            ])->group('date, app_id, domain_id')->buildSql() . ' t')->field(['date', 'app_id', 'MAX(new_users) AS new_users', 'MAX(active_users) AS active_users'])->group('date ,app_id')->buildSql();
        $app_active_sql = AppActive::field([
            'date', 'app_id', 'SUM(new_users) AS new_users', 'SUM(active_users) AS active_users'
        ])->group('date, app_id')->buildSql();
        $spend_sql      = SpendData::field(['date', 'app_id', 'SUM(spend) AS total_spend'])->group('date, app_id')->buildSql();

        $res = $this->model->field($field)
            ->alias($alias)
            ->where('utc.status', 0)
            ->join($h5_active_sql . ' h5_active', 'utc.a_date = h5_active.date AND utc.app_id = h5_active.app_id', 'left')
            ->join($app_active_sql . ' app_active', 'utc.a_date = app_active.date AND utc.app_id = app_active.app_id', 'left')
            ->join($spend_sql . ' spend', 'utc.a_date = spend.date AND utc.app_id = spend.app_id', 'left')
            ->where($where)
            ->order('utc.a_date', 'desc')
            ->order('a_date desc')
            ->order('total_revenue desc')
            ->group('utc.a_date, utc.app_id');

        $sql   = $res->fetchSql(true)->select();
        $res   = $res->paginate($limit);
        $total = [
            'id'               => 10000,
            'a_date'           => '',
            'app_id'           => '',
            'app_name'         => '',
            'app_new_users'    => 0,
            'app_active_users' => 0,
            'h5_new_users'     => 0,
            'h5_active_users'  => 0,
            'total_revenue'    => 0,
            'total_spend'      => 0,
            'profit'           => 0,
            'native_revenue'   => 0,
            'h5_revenue'       => 0,
        ];
        foreach ($res->items() as $v) {
            $total['app_new_users']    += $v['app_new_users'];
            $total['app_active_users'] += $v['app_active_users'];
            $total['h5_new_users']     += $v['h5_new_users'];
            $total['h5_active_users']  += $v['h5_active_users'];
            $total['total_revenue']    += $v['total_revenue'];
            $total['total_spend']      += $v['total_spend'];
            $total['profit']           += $v['profit'];
            $total['native_revenue']   += $v['native_revenue'];
            $total['h5_revenue']       += $v['h5_revenue'];
        }
        $list = $this->rate(array_merge($res->items(), [$total]));

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
            $v['profit']       = $v['total_revenue'] - $v['total_spend'];
            $v['roi']          = $v['total_spend'] > 0
                ? number_format((float)$v['total_revenue'] / (float)$v['total_spend'] * 100, 2, '.', '') . '%'
                : '-';
            $v['cpi']          = $v['total_spend'] > 0
                ? number_format((float)$v['app_new_users'] / (float)$v['total_spend'] * 100, 2, '.', '') . '%'
                : '-';
            $v['h5_arpu']      = $v['h5_active_users'] > 0
                ? number_format((float)$v['h5_revenue'] / $v['h5_active_users'], 2, '.', '')
                : '-';
            $v['app_arpu']     = $v['app_active_users'] > 0
                ? number_format((float)$v['total_revenue'] / $v['app_active_users'], 2, '.', '')
                : '-';
            $v['hb_open_rate'] = ($this->apps[$v['app_id']]['hb_switch'] ?? 0) == 0
                ? '-'
                : ($v['app_active_users'] > 0
                    ? number_format((float)$v['h5_active_users'] / $v['app_active_users'] * 100, 2, '.', '') . '%'
                    : 0);
            $v['native_rate']  = $v['total_revenue'] > 0
                ? number_format((float)$v['native_revenue'] / $v['total_revenue'] * 100, 2, '.', '') . '%'
                : '-';

            $v['profit']         = number_format((float)$v['profit'], 2, '.', '');
            $v['h5_revenue']     = number_format((float)$v['h5_revenue'], 2, '.', '');
            $v['native_revenue'] = number_format((float)$v['native_revenue'], 2, '.', '');
            $v['total_revenue']  = number_format((float)$v['total_revenue'], 2, '.', '');
            $v['total_spend']    = number_format((float)$v['total_spend'], 2, '.', '');
            $v['app_name']       = isset($v['app_id']) && isset($this->apps[$v['app_id']]) ? $this->apps[$v['app_id']]['app_name'] : '-';
        }
        return $data;
    }


}