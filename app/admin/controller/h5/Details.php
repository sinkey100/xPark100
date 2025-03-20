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
        // 维度信息获取
        $input_dimensions  = $this->request->get('dimensions/a', []);
        $main_dimension    = $h5_active_dimensions = $app_active_dimensions = $spend_active_dimensions = [];
        $h5_active_join_on = $app_active_join_on = $spend_active_join_on = [];
        $h5_active_where   = $app_active_where = $spend_where = [];

        foreach ($input_dimensions as $k => $v) {
            if ($k && $v == 'true') $main_dimension[] = 'utc.' . $k;
            if (in_array($k, ['a_date', 'app_id', 'channel_id']) && $v == 'true') {
                $new_field                 = $k == 'a_date' ? 'date' : $k;
                $h5_active_dimensions[]    = $new_field;
                $spend_active_dimensions[] = $new_field;

                $h5_active_join_on[]    = "utc.$k = h5_active.$new_field";
                $spend_active_join_on[] = "utc.$k = spend.$new_field";

                if ($new_field != 'channel_id') {
                    $app_active_dimensions[] = $new_field;
                    $app_active_join_on[]    = "utc.$k = app_active.$new_field";
                }
            }
        }
        if(count($main_dimension) == 0) $this->error('维度为必选项');
        if(count($main_dimension) == 1 && $main_dimension[0] == 'utc.channel_id') $this->error('通道维度不能单独选择');


        list($where, $alias, $limit, $order) = $this->queryBuilder();
        foreach ($where as $key => $item) {
            if (str_ends_with($item[0], 'date')) {
                $h5_active_where[]  = ['date', $item[1], $item[2]];
                $app_active_where[] = ['date', $item[1], $item[2]];
                $spend_where[]      = ['date', $item[1], $item[2]];
            }
            if (str_ends_with($item[0], 'app_id')) {
                $h5_active_where[]  = ['app_id', $item[1], $item[2]];
                $app_active_where[] = ['app_id', $item[1], $item[2]];
                $spend_where[]      = ['app_id', $item[1], $item[2]];
            }
            if (str_ends_with($item[0], 'channel_id')) {
                $h5_active_where[] = ['channel_id', $item[1], $item[2]];
                $spend_where[]     = ['channel_id', $item[1], $item[2]];
            }
        }
        $where = array_values($where);

        $field = array_merge($main_dimension, [
            'utc.channel_full',
            'apps.app_type',
            'SUM(utc.ad_revenue) AS total_revenue',
            'SUM(CASE WHEN channel_type = 0 THEN ad_revenue ELSE 0 END) AS h5_revenue',
            'SUM(CASE WHEN channel_type = 1 THEN ad_revenue ELSE 0 END) AS native_revenue',
            'IFNULL(h5_active.new_users, 0) AS h5_new_users',
            'IFNULL(h5_active.active_users, 0) AS h5_active_users',
            'IFNULL(app_active.new_users, 0) AS app_new_users',
            'IFNULL(app_active.active_users, 0) AS app_active_users',
            'IFNULL(spend.total_spend, 0) AS total_spend'
        ]);


        $h5_active_sql = Db::table(
            SLSActive::field(array_merge($h5_active_dimensions, [
                'SUM(new_users) AS new_users', 'SUM(active_users) AS active_users'
            ]))->where($h5_active_where)->group(implode(', ', array_merge($h5_active_dimensions, ['domain_id'])))->buildSql() . ' t'
        )
            ->field(array_merge($h5_active_dimensions, ['MAX(new_users) AS new_users', 'MAX(active_users) AS active_users']))
            ->group(implode(', ', $h5_active_dimensions))->buildSql();


        $app_active_sql = AppActive::field(array_merge($app_active_dimensions, [
            'SUM(new_users) AS new_users', 'SUM(active_users) AS active_users'
        ]))->where($app_active_where)->group(implode(', ', $app_active_dimensions))->buildSql();

        $spend_sql = SpendData::field(array_merge($spend_active_dimensions, [
            'SUM(spend) AS total_spend'
        ]))->where($spend_where)->group(implode(', ', $spend_active_dimensions))->buildSql();

        $res = $this->model->field($field)
            ->alias($alias)
            ->where('utc.status', 0)
            ->join($h5_active_sql . ' h5_active', implode(' AND ', $h5_active_join_on), 'left')
            ->join($app_active_sql . ' app_active', implode(' AND ', $app_active_join_on), 'left')
            ->join($spend_sql . ' spend', implode(' AND ', $spend_active_join_on), 'left')
            ->join('xpark_apps apps', 'apps.id = utc.app_id', 'left')
            ->where($where)
            ->order('utc.a_date', 'desc')
            ->order('a_date desc')
            ->order('total_revenue desc')
            ->group(implode(', ', $main_dimension));

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
            $v['cpi']          = $v['app_new_users'] > 0
                ? number_format((float)$v['total_spend'] / (float)$v['app_new_users'], 2, '.', '')
                : '-';
            $v['h5_arpu']      = $v['h5_active_users'] > 0
                ? number_format((float)$v['h5_revenue'] / $v['h5_active_users'], 2, '.', '')
                : '-';
            $v['app_arpu']     = $v['app_active_users'] > 0
                ? number_format((float)$v['total_revenue'] / $v['app_active_users'], 2, '.', '')
                : '-';
            $v['hb_open_rate'] = !in_array(($this->apps[$v['app_id']]['app_type'] ?? 2), [0, 1])
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