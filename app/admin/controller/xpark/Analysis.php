<?php

namespace app\admin\controller\xpark;

use think\facade\Db;
use Throwable;
use DateTime;
use app\common\controller\Backend;
use app\admin\model\xpark\Channel;
use app\admin\model\xpark\Apps;
use app\admin\model\xpark\Analysis as DataModel;
use app\admin\model\xpark\Clear as ClearModel;
use app\admin\model\xpark\Hold as HoldModel;
use app\admin\model\xpark\Tidy as TidyModel;

/**
 * xPark数据
 */
class Analysis extends Backend
{
    protected object $model;

    protected array $columns;

    protected array|string $preExcludeFields = ['id'];

    protected string|array $quickSearchField = ['id'];

    protected array $apps = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model   = new \app\admin\model\xpark\Analysis();
        $apps          = Apps::alias('apps')->field(['apps.*'])->select()->toArray();
        $this->apps    = array_column($apps, null, 'id');
        $this->columns = [
            [
                "title"  => "日期",
                "colKey" => "month",
                "align"  => "center",
                "width"  => 120,
                "fixed"  => 'left'
            ], [
                "colKey" => "app_name",
                "title"  => "应用",
                "align"  => "center",
                "width"  => 180,
                "fixed"  => "left"

            ], [
                "colKey" => "settle",
                "title"  => "结算金额",
                "align"  => "center",
                "width"  => 110,
                "fixed"  => "left"
            ], [
                "colKey" => "revenue",
                "title"  => "收入",
                "align"  => "center",
                "width"  => 110,
                "fixed"  => "left"
            ], [
                "colKey" => "clear",
                "title"  => "核减",
                "align"  => "center",
                "width"  => 110,
                "fixed"  => "left"
            ], [
                "colKey" => "hold",
                "title"  => "PaymentHold",
                "align"  => "center",
                "width"  => 120,
                "fixed"  => "left"
            ], [
                "colKey" => "tidy",
                "title"  => "调整",
                "align"  => "center",
                "width"  => 110,
                "fixed"  => "left"
            ], [
                "title"    => "收入",
                "colKey"   => "col-revenue",
                "align"    => "center",
                "width"    => 'auto',
                "children" => []
            ], [
                "colKey"   => "col-clear",
                "title"    => "核减",
                "align"    => "center",
                "width"    => 'auto',
                "children" => []
            ], [
                "colKey"   => "col-hold",
                "title"    => "PaymentHold",
                "align"    => "center",
                "width"    => 'auto',
                "children" => []
            ], [
                "colKey"   => "col-tidy",
                "title"    => "调整",
                "align"    => "center",
                "width"    => 'auto',
                "children" => []
            ]
        ];
    }

    public function index(): void
    {
        // 筛选月份
        $filter_months = array_map(fn($i) => date("Y-m", strtotime("-$i months")), range(0, 2));
        // 过滤应用
        $map = [];
        if ($this->auth->id > 1) $map[] = ['admin_id', '=', $this->auth->id];
        $filter_apps = array_column(Apps::field('id')->where($map)->select()->toArray(), 'id');
        if (count($filter_apps) == 0) $filter_apps = [1];

        // 搜索条件覆盖
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        if ($where) {
            $where = array_column($where, null, 0);
            if (isset($where['analysis.a_date'])) {
                $filter_months = $this->getMonthsInRange($where['analysis.a_date'][2]);
            }
            if (isset($where['analysis.app_id'])) {
                $filter_apps = array_intersect($where['analysis.app_id'][2], $filter_apps);
            }
        }

        // 筛选通道
        $filter_channel_ids = array_column(DataModel::where('app_id', 'in', $filter_apps)->group('channel_id')->select()->toArray(), 'channel_id');
        $filter_channel     = Channel::where('id', 'in', $filter_channel_ids)->select()->toArray();
        $filter_channel_ids = array_column($filter_channel, 'id');

        // 筛选时间段内有核减的通道
        $month_range = [$filter_months[count($filter_months) - 1] . '-01 00:00:00', date("Y-m-t", strtotime($filter_months[0])) . ' 23:59:59'];
        $clear_list  = ClearModel::alias('clear')
            ->field(['clear.*', 'channel.channel_alias'])
            ->join('xpark_channel channel', 'channel.id = clear.channel_id', 'left')
            ->where('clear.channel_id', 'in', $filter_channel_ids)
            ->where('clear.month', 'between', $month_range)
            ->select()->toArray();
        foreach ($clear_list as &$item) {
            // 查询核减通道在当月的收入
            $item['ad_revenue'] = DataModel::where('channel_id', $item['channel_id'])
                ->whereMonth('a_date', date("Y-m", strtotime($item['month'])))->sum('ad_revenue');
            // 插入表格列
            $this->columns[8]['children'][] = [
                "colKey"   => "c_{$item['channel_id']}",
                "title"    => $item['channel_alias'],
                "align"    => "center",
                "ellipsis" => true,
                "width"    => 150
            ];
        }
        unset($item);

        // 筛选时间段内有hold的通道
        $hold_list = HoldModel::alias('hold')
            ->field(['hold.*', 'channel.channel_alias'])
            ->join('xpark_channel channel', 'channel.id = hold.channel_id', 'left')
            ->where('hold.channel_id', 'in', $filter_channel_ids)
            ->where('hold.month', 'between', $month_range)
            ->select()->toArray();
        foreach ($hold_list as &$item) {
            // 插入表格列
            $this->columns[9]['children'][] = [
                "colKey"   => "h_{$item['channel_id']}",
                "title"    => $item['channel_alias'],
                "align"    => "center",
                "ellipsis" => true,
                "width"    => 150
            ];
        }
        unset($item);

        // 筛选动态调整数据
        $tidy_list = TidyModel::alias('tidy')
            ->field(['tidy.*', 'channel.channel_alias'])
            ->join('xpark_channel channel', 'channel.id = tidy.channel_id', 'left')
            ->where('tidy.channel_id', 'in', $filter_channel_ids)
            ->where('tidy.month', 'between', $month_range)
            ->select()->toArray();
        foreach ($tidy_list as &$item) {
            // 查询核减通道在当月的收入
            $item['ad_revenue'] = DataModel::where('channel_id', $item['channel_id'])
                ->whereNotIn('app_id', $item['exclude_id'])
                ->whereMonth('a_date', date("Y-m", strtotime($item['month'])))->sum('ad_revenue');
            // 插入表格列
            $this->columns[10]['children'][] = [
                "colKey"   => "t_{$item['channel_id']}",
                "title"    => $item['channel_alias'],
                "align"    => "center",
                "ellipsis" => true,
                "width"    => 150
            ];
        }
        unset($item);

        // 插入表格列
        foreach ($filter_channel as $channel) {
            $this->columns[7]['children'][] = [
                "colKey"   => "r_{$channel['id']}",
                "title"    => $channel['channel_alias'],
                "align"    => "center",
                "ellipsis" => true,
                "width"    => 150
            ];
        }

        $items = [];

        foreach ($filter_months as $month) {
            foreach ($filter_apps as $app) {
                $item = [
                    'month'    => $month,
                    'app_id'   => $app,
                    'app_name' => $this->apps[$app]['app_name'] ?? 'Unknown',
                    'revenue'  => 0, // 收入
                    'clear'    => 0, // 核减
                    'hold'     => 0, // Hold
                    'tidy'     => 0, // 动态调整
                    'settle'   => 0, // 结算
                ];

                // 收入
                $rows = $this->revenue_detail($month, $app, $filter_channel_ids);
                $rows = array_column($rows, null, 'id');
                foreach ($filter_channel as $channel) {
                    $channel_id            = $channel['id'];
                    $sum                   = $rows[$channel['id']]['ad_revenue'] ?? 0;
                    $item["r_$channel_id"] = $sum;
                    $item['revenue']       += $sum;
                    $item['settle']        += $sum;

                    // 核减
                    foreach ($clear_list as $clear) {
                        if (
                            $clear['channel_id'] == $channel['id']
                            && $clear['month'] == $month . '-01'
                        ) {
                            $clear_money           = round($sum / $clear['ad_revenue'] * $clear['money'], 2);
                            $item["c_$channel_id"] = $clear_money;
                            $item['clear']         += $clear_money;
                            $item['settle']        -= $clear_money;
                        }
                    }
                    // Hold
                    foreach ($hold_list as $hold) {
                        if (
                            $hold['channel_id'] == $channel['id']
                            && $hold['month'] == $month . '-01'
                        ) {
                            $item["h_$channel_id"] = $sum;
                            $item['hold']          += $sum;
                            $item['settle']        -= $sum;
                        }
                    }

                    // 动态调整
                    foreach ($tidy_list as $tidy) {
                        if (
                            $tidy['channel_id'] == $channel['id']
                            && $tidy['month'] == $month . '-01'
                            && !in_array($app, $tidy['exclude_id'])
                        ) {
                            $tidy_money            = round($sum / $tidy['ad_revenue'] * $tidy['money'], 2);
                            $item["t_$channel_id"] = $tidy_money;
                            $item['tidy']          += $tidy_money;
                            $item['settle']        += $tidy_money;
                        }
                    }
                }

                // 格式化
                $item['revenue'] = round($item['revenue'], 2);
                $item['clear']   = round($item['clear'], 2);
                $item['hold']    = round($item['hold'], 2);
                $item['settle']  = round($item['settle'], 2);
                $item['tidy']    = round($item['tidy'], 2);
                // 空数据不记录
                if ($item['revenue'] == 0) continue;
                $items[] = $item;
            }
        }

        if(count($tidy_list) == 0) unset($this->columns[6]);
        if(count($hold_list) == 0) unset($this->columns[5]);
        if(count($clear_list) == 0) unset($this->columns[4]);


        $this->success('', [
            'list'    => $items,
            'columns' => array_values($this->columns),
        ]);
    }

    public function revenue_detail(string $month, int $app_id, array $filter_channel_ids): array
    {
        $dateRange = [$month . '-01 00:00:00', date("Y-m-t", strtotime($month)) . ' 23:59:59'];

        $res = Channel::alias('channel')
            ->field([
                'channel.id', 'COALESCE(SUM(data.ad_revenue), 0) as ad_revenue',
            ])
            ->where('channel.id', 'in', $filter_channel_ids)
            ->join('xpark_data data', 'data.channel_id = channel.id', 'left')
            ->where('data.app_id', $app_id)
            ->where('data.a_date', 'between', $dateRange)
            ->group('channel.id')
            ->select();

        return $res->toArray();
    }

    public function getMonthsInRange(array $dateRange): array
    {
        // 将字符串日期转换为DateTime对象
        $startDate = new DateTime($dateRange[0]);
        $endDate   = new DateTime($dateRange[1]);

        $months       = [];
        $currentMonth = clone $startDate;

        // 循环直到当前月份大于结束月份
        while ($currentMonth <= $endDate) {
            $months[] = $currentMonth->format('Y-m');
            $currentMonth->modify('first day of next month');
        }

        return array_reverse($months);
    }


}