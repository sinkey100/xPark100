<?php

namespace app\admin\controller\xpark;

use app\admin\model\xpark\Activity;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use sdk\QueryTimeStamp;
use Throwable;
use app\common\controller\Backend;
use app\admin\model\xpark\Domain;
use app\admin\model\xpark\Apps;

/**
 * xPark数据
 */
class Data extends Backend
{
    /**
     * Data模型对象
     * @var object
     * @phpstan-var \app\admin\model\xpark\Data
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id'];

    protected array $withJoinTable = ['activity'];
//    protected array $withJoinTable = ['domain'];

    protected string|array $quickSearchField = ['id'];

    protected array $domains = [];
    protected array $apps    = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model   = new \app\admin\model\xpark\Data();
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
        $activity_dimension = [];
        $activity_join_on   = [];
        foreach ($dimensions as $k => $v) {
            if ($k && $v == 'true') {
                $dimension[] = 'data.' . $k;

                if ($k != 'ad_placement_id') {
                    $key                  = $k == 'a_date' ? 'date' : $k;
                    $activity_dimension[] = 'activity.' . $key;
                    $activity_join_on[]   = "data.$k = activity.$key";
                }
            }
        }


        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $activity_where = [];
        if ($this->auth->id == 1) {
            $activity_where[] = ['activity.status', '=', 0];
        }

        foreach ($where as $k => $v) {
            if ($v[0] == 'data.admin') {
                $app_filter       = Apps::field(['id'])->where('admin_id', $v[2])->select();
                $app_filter       = array_column($app_filter->toArray(), 'id');
                if(empty($app_filter)) $app_filter = [1];
                $activity_where[] = ['activity.app_id', 'in', $app_filter];
                unset($where[$k]);
            }
            if (in_array($v[0], ['data.a_date', 'data.domain_id', 'data.app_id', 'data.country_code'])) {
                $filter           = [...$v];
                $filter[0]        = str_replace(['a_date', 'data.'], ['date', 'activity.'], $filter[0]);
                $activity_where[] = $filter;
            }
        }

        $field = array_merge($dimension, [
            'data.channel',
            'data.channel_full',
            'data.sub_channel',
            'data.country_level',
            'data.country_name',
            'SUM(data.requests) AS requests',
            'SUM(data.fills) AS fills',
            'SUM(data.impressions) AS impressions',
            'SUM(data.clicks) AS clicks',
            'SUM(data.ad_revenue) AS ad_revenue',
            'SUM(data.gross_revenue) AS gross_revenue'
        ]);
        if (!in_array('ad_placement_id', $dimension)) {
            $field = array_merge($field, [
                'activity.new_users as activity_new_users',
                'activity.active_users as activity_active_users',
                'activity.page_views as activity_page_views'
            ]);
        }

        $activity_field   = array_merge($activity_dimension, [
            'SUM(activity.new_users) as new_users',
            'SUM(activity.active_users) as active_users',
            'SUM(activity.page_views) as page_views'
        ]);
        $activity_summary = Activity::alias('activity')
            ->field($activity_field)
            ->where($activity_where)
            ->group(implode(',', $activity_dimension))
            ->buildSql();

        $res = $this->model->field($field)
            ->leftJoin([$activity_summary => 'activity'], implode(' AND ', $activity_join_on))
            ->alias($alias)
            ->where('status', 0)
            ->where($where);

        if ($app_filter) {
            $res = $res->where('data.app_id', 'in', $app_filter);
        }
        unset($order['data.id']);

        $res = $res->order($order)->order('a_date', 'desc')
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

        $total = [
            'id'                    => 10000,
            'ad_revenue'            => 0,
            'gross_revenue'         => 0,
            'requests'              => 0,
            'fills'                 => 0,
            'impressions'           => 0,
            'clicks'                => 0,
            'activity_page_views'   => 0,
            'activity_new_users'    => 0,
            'activity_active_users' => 0,
            'activity_per_display'  => 0,
            'a_date'                => '',
        ];
        foreach ($res->items() as $v) {
            $total['ad_revenue']    += $v['ad_revenue'];
            $total['requests']      += $v['requests'];
            $total['fills']         += $v['fills'];
            $total['impressions']   += $v['impressions'];
            $total['clicks']        += $v['clicks'];
            $total['gross_revenue'] += $v['gross_revenue'];
            if ($this->auth->id == 23) {
                $total['activity_page_views']   += $v['activity_page_views'];
                $total['activity_new_users']    += $v['activity_new_users'];
                $total['activity_active_users'] += $v['activity_active_users'];
            } else {
                $total['activity_page_views']   = max($total['activity_page_views'], $v['activity_page_views']);
                $total['activity_new_users']    = max($total['activity_new_users'], $v['activity_new_users']);
                $total['activity_active_users'] = max($total['activity_active_users'], $v['activity_active_users']);
            }

        }

        $list = array_merge($res->items(), [$total]);
        $list = $this->rate($list, $dimension);

        $this->success('', [
            '_'      => $this->auth->id == 1 ? $sql : '',
            'list'   => $list,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
            'ts'     => QueryTimeStamp::end()
        ]);
    }

    public function export(): void
    {
        [$list, $limit, $dimension] = $this->calcData();
        $list = $list->select();
        $list = $this->rate($list, $dimension);

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $cell = [
            '#'               => '#',
            'a_date'          => '日期',
            'sub_channel'     => '子渠道',
            'country_code'    => '地区',
            'ad_placement_id' => '广告单元',
            'ad_revenue'      => '预计收入',
            'requests'        => '请求次数',
            'fills'           => '填充次数',
            'fill_rate'       => '填充率',
            'impressions'     => '展示次数',
            'clicks'          => '点击次数',
            'click_rate'      => '点击率(ctr)',
            'unit_price'      => '单价(cpc)',
            'ecpm'            => 'eCPM',
        ];
        if ($this->auth->id == 1) {
            $cell['channel_full'] = '广告通道';
        }

        foreach (['data.a_date', 'data.domain_id', 'data.country_code', 'data.ad_placement_id'] as $v) {
            $field = explode('.', $v);
            $field = $field[count($field) - 1];
            if (!in_array($v, $dimension)) unset($cell[$field]);
        }

        if (in_array('data.domain_id', $dimension) && !in_array('data.ad_placement_id', $dimension)) {
            $cell = array_merge($cell, [
                'activity_page_views'   => 'PV',
                'activity_new_users'    => '新增',
                'activity_active_users' => '活跃'
            ]);
        }

        $i = 0;
        foreach ($cell as $k => $v) {
            $i++;
            $sheet->setCellValue([$i, 1], $v);
        }

        $h = 2;
        foreach ($list as $v) {
            $i = 0;
            foreach ($cell as $key => $value) {
                $i++;
                $value = str_replace(' 00:00:00', '', $v[$key] ?? $h - 1);
                $sheet->setCellValue([$i, $h], $value);
            }
            $h++;
        }

        $writer = new Xlsx($spreadsheet);
        $file   = time() . '.xlsx';
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: think-lang, server, ba_user_token, ba-user-token, ba_token, ba-token, batoken, Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-CSRF-TOKEN, X-Requested-With');
        header('Access-Control-Max-Age: 1800');
        header('Access-Control-Expose-Headers:Content-Disposition');
        header('Content-Disposition: attachment;filename=' . $file);
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
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

            // 人均展示：展示次数/UV
            $v['activity_per_display'] = empty($v['activity_active_users'])
                ? ''
                : round($v['impressions'] / $v['activity_active_users'], 2);

            // ECPM = 收入/网页展示次数×1000
            $v['ecpm']        = round($v['ad_revenue'] / (!empty($v['impressions']) ? $v['impressions'] : 1) * 1000, 3);
            $v['ad_revenue']  = round($v['ad_revenue'], 2);
            $v['requests']    = (int)$v['requests'];
            $v['fills']       = (int)$v['fills'];
            $v['clicks']      = (int)$v['clicks'];
            $v['impressions'] = (int)$v['impressions'];
            $v['rpm']         = empty($v['activity_page_views']) ? '' : round($v['ad_revenue'] / $v['activity_page_views'] * 1000, 3);

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
                $v['raw_rpm']        = empty($v['activity_page_views']) ? '' : round($v['gross_revenue'] / $v['activity_page_views'] * 1000, 3);
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