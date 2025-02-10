<?php

namespace app\admin\controller\xpark;

use app\admin\model\xpark\Activity;
use app\admin\model\xpark\Apps;
use app\admin\model\xpark\Domain;
use app\common\controller\Backend;
use Throwable;

/**
 * xPark数据（UTC）
 */
class Hour extends Backend
{
    /**
     * Hour模型对象
     * @var object
     * @phpstan-var \app\admin\model\xpark\DataHour
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id'];

    protected string|array $quickSearchField = ['id'];

    protected array $domains = [];
    protected array $apps    = [];


    public function initialize(): void
    {
        parent::initialize();
        $this->model   = new \app\admin\model\xpark\DataHour();
        $domains       = Domain::alias('domain')
            ->field(['domain.*', 'admin.nickname'])
            ->join('admin admin', 'admin.id = domain.admin_id', 'left')
            ->select()->toArray();
        $this->domains = array_column($domains, null, 'domain');
        $apps          = Apps::alias('apps')->field(['apps.*'])->select()->toArray();
        $this->apps    = array_column($apps, null, 'id');
    }

    protected function calcData()
    {
        $app_filter = array_column(Apps::field('id')->where('admin_id', $this->auth->id)->select()->toArray(), 'id');
        if ($this->auth->id > 1 && count($app_filter) == 0) $app_filter = [1];


        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        $dimensions         = $this->request->get('dimensions/a', []);
        $dimension          = [];
        foreach ($dimensions as $k => $v) {
            if ($k && $v == 'true') {
                $dimension[] = 'data_hour.' . $k;
            }
        }


        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();


        foreach ($where as $k => $v) {
            if ($v[0] == 'data_hour.admin') {
                $app_filter       = Apps::field(['id'])->where('admin_id', $v[2])->select();
                $app_filter       = array_column($app_filter->toArray(), 'id');
                unset($where[$k]);
            }
        }

        $field = array_merge($dimension, [
            'data_hour.channel',
            'data_hour.channel_full',
            'data_hour.sub_channel',
            'data_hour.country_level',
            'data_hour.country_name',
            'SUM(data_hour.requests) AS requests',
            'SUM(data_hour.fills) AS fills',
            'SUM(data_hour.impressions) AS impressions',
            'SUM(data_hour.clicks) AS clicks',
            'SUM(data_hour.ad_revenue) AS ad_revenue',
            'SUM(data_hour.gross_revenue) AS gross_revenue'
        ]);


        $res = $this->model->field($field)
            ->alias($alias)
            ->where('status', 0)
            ->where($where);

        if ($app_filter) {
            $res = $res->where('data_hour.app_id', 'in', $app_filter);
        }
        unset($order['data_hour.id']);

        $res = $res->order($order)->order('time_utc_0', 'desc')
            ->group(implode(',', $dimension));

        return [$res, $limit, $dimension];
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {

        [$res, $limit, $dimension] = $this->calcData();
        $sql = $res->fetchSql(true)->select();

        $res = $res->paginate($limit);
        $res->visible(['domain' => ['domain']]);

        $total = [
            'id'                    => 10000,
            'ad_revenue'            => 0,
            'gross_revenue'         => 0,
            'requests'              => 0,
            'fills'                 => 0,
            'impressions'           => 0,
            'clicks'                => 0,
            'time_utc_0'            => '',
        ];
        foreach ($res->items() as $v) {
            $total['ad_revenue']    += $v['ad_revenue'];
            $total['requests']      += $v['requests'];
            $total['fills']         += $v['fills'];
            $total['impressions']   += $v['impressions'];
            $total['clicks']        += $v['clicks'];
            $total['gross_revenue'] += $v['gross_revenue'];

        }

        $list = array_merge($res->items(), [$total]);
        $list = $this->rate($list, $dimension);

        $this->success('', [
            '_'      => $this->auth->id == 1 ? $sql : '',
            'list'   => $list,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    protected function rate($data, $dimension)
    {
        foreach ($data as &$v) {
            // 点击率：  点击/展示
            $v['click_rate'] = $v['clicks'] / (!empty($v['impressions']) ? $v['impressions'] : 1);;
            $v['click_rate'] = number_format($v['click_rate'] * 100, 2) . '%';

            // 填充率：  填充/请求
            $v['fill_rate'] = $v['fills'] / (!empty($v['requests']) ? $v['requests'] : 1);
            $v['fill_rate'] = number_format($v['fill_rate'] * 100, 2) . '%';

            // 单价：  总收入/点击次数
            $v['unit_price'] = round($v['ad_revenue'] / (!empty($v['clicks']) ? $v['clicks'] : 1), 2);

            // 展示率：展示次数/填充次数
            $v['impressions_rate'] = $v['impressions'] / (!empty($v['fills']) ? $v['fills'] : 1);
            $v['impressions_rate'] = number_format($v['impressions_rate'] * 100, 2) . '%';


            // ECPM = 收入/网页展示次数×1000
            $v['ecpm']        = round($v['ad_revenue'] / (!empty($v['impressions']) ? $v['impressions'] : 1) * 1000, 3);
            $v['ad_revenue']  = round($v['ad_revenue'], 2);
            $v['requests']    = (int)$v['requests'];
            $v['fills']       = (int)$v['fills'];
            $v['clicks']      = (int)$v['clicks'];
            $v['impressions'] = (int)$v['impressions'];

            $v['app_name'] = isset($v['app_id']) && isset($this->apps[$v['app_id']]) ? $this->apps[$v['app_id']]['app_name'] : '-';

//            // 计算活跃数据
//            if (in_array('ad_placement_id', $dimension)) {
//                array_splice($dimension, array_search('ad_placement_id', $dimension), 1);
//            }
//
//            if (in_array('sub_channel', $dimension)) {
//                // 有域名的维度
//                $v['activity_page_views'] = $v['activity_new_users'] = $v['activity_active_users'] = '-';
//            } else {
//                $activity = Activity::where('date', $v['a_date'])->where('')
//            }


            if ($this->auth->id != 1) {
                unset($v['channel']);
                unset($v['gross_revenue']);
            } else {

                $v['admin']          = isset($v['sub_channel']) && isset($this->domains[$v['sub_channel']]) ? $this->domains[$v['sub_channel']]['nickname'] : '-';
                $v['gross_revenue']  = round($v['gross_revenue'], 2);
                $v['raw_unit_price'] = round($v['gross_revenue'] / (!empty($v['clicks']) ? $v['clicks'] : 1), 2);
                $v['raw_ecpm']       = round($v['gross_revenue'] / (!empty($v['impressions']) ? $v['impressions'] : 1) * 1000, 3);
            }
        }
        return $data;
    }

    public function country(): void
    {
        $page        = $this->request->get('page/d', 1);
        $quickSearch = $this->request->get('quickSearch/s', '');
        $all_country = get_country_data();

        if ($quickSearch) {
            foreach ($all_country as $k => $v) {
                if (!str_contains(implode('-', [$v['code'], $v['name'], $v['level']]), $quickSearch)) {
                    unset($all_country[$k]);
                }
            }
            array_values($all_country);
        }


        $country = array_slice($all_country, ($page - 1) * 10, 10);
        $data    = [];
        foreach ($country as $v) {
            $data[] = [
                'id'   => $v['code'],
                'name' => implode('-', [$v['code'], $v['name'], $v['level']])
            ];
        }

        $this->success('', [
            'list'  => $data,
            'total' => count($all_country)
        ]);
    }

}