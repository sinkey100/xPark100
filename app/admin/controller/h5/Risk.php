<?php

namespace app\admin\controller\h5;

use app\admin\model\xpark\Activity;
use think\facade\Db;
use Throwable;
use DateTime;
use app\common\controller\Backend;
use app\admin\model\xpark\Channel;
use app\admin\model\xpark\Apps;
use app\admin\model\sls\Active as SLSActive;
use app\admin\model\xpark\Data as XparkData;
use app\admin\model\spend\Data as SpendData;

class Risk extends Backend
{
    protected object $model;

    protected array $columns;

    protected array|string $preExcludeFields = ['id'];

    protected string|array $quickSearchField = ['id'];

    protected float $cpc_cost_show = 0.04;
    protected array $apps          = [];
    protected array $channel       = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model   = new \app\admin\model\xpark\Analysis();
        $apps          = Apps::alias('apps')->field(['apps.*'])->select()->toArray();
        $this->apps    = array_column($apps, null, 'id');
        $channel       = Channel::select()->toArray();
        $this->channel = array_column($channel, null, 'id');

        $this->columns = [
            ["colKey" => "date", "title" => "日期", "align" => "center", "width" => 120, "fixed" => 'left'],
            ["colKey" => "channel_flag", "title" => "账号", "align" => "center", "width" => 180, "fixed" => "left"],
            ["colKey" => "app_name", "title" => "项目", "align" => "center", "width" => 180, "fixed" => "left"],
            ["colKey" => "hb_domain_name", "title" => "HB链接", "align" => "center", "width" => 220, "ellipsis" => true],
            ["colKey" => "h5_advertise_spend", "title" => "H5投放支出", "align" => "center", "width" => 110,],
            ["colKey" => "h5_advertise_revenue", "title" => "H5投放收入", "align" => "center", "width" => 110],
            ["colKey" => "h5_advertise_roi", "title" => "H5投放ROI", "align" => "center", "width" => 110],
            ["colKey" => "h5_advertise_active", "title" => "H5投放活跃", "align" => "center", "width" => 110],
            ["colKey" => "hb_show_active", "title" => "游戏中心活跃", "align" => "center", "width" => 120],
            ["colKey" => "hb_show_new", "title" => "游戏中心新增", "align" => "center", "width" => 120],
            ["colKey" => "hb_show_revenue", "title" => "游戏中心收入", "align" => "center", "width" => 120],
            ["colKey" => "hb_hide_revenue", "title" => "HB收入", "align" => "center", "width" => 100],
            ["colKey" => "dimensions_spend", "title" => "支出维度", "align" => "center", "width" => 'auto', "children" => [
                ["colKey" => "dimensions_spend_model", "title" => "支出维度标准模型", "align" => "center", "ellipsis" => true, "width" => 150],
                ["colKey" => "dimensions_spend_gap", "title" => "支出维度差值", "align" => "center", "ellipsis" => true, "width" => 150]
            ]],
            ["colKey" => "dimensions_revenue", "title" => "收入维度", "align" => "center", "width" => 'auto', "children" => [
                ["colKey" => "dimensions_revenue_model", "title" => "收入维度标准模型", "align" => "center", "ellipsis" => true, "width" => 150],
                ["colKey" => "dimensions_revenue_gap", "title" => "收入维度差值", "align" => "center", "ellipsis" => true, "width" => 150]
            ]],
            ["colKey" => "hb_hide_active", "title" => "HB活跃", "align" => "center", "width" => 180],
            ["colKey" => "dimensions_user", "title" => "用户维度", "align" => "center", "width" => 'auto', "children" => [
                ["colKey" => "dimensions_user_model", "title" => "用户维度标准模型", "align" => "center", "ellipsis" => true, "width" => 150],
                ["colKey" => "dimensions_user_gap", "title" => "用户维度差值", "align" => "center", "ellipsis" => true, "width" => 150]
            ]],
        ];
    }

    public function index(): void
    {
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        /*
         * 主查询语句
         */
        $fields  = [
            'xpark.*',
            'domain.is_hide as domain_is_hide',
            'apps.app_name',
            'sum(xpark.ad_revenue) as xpark_ad_revenue',
            'COALESCE(activity.active_users, 0) AS xpark_active_users'
        ];
        $groupBy = [
            'xpark.a_date',
            'xpark.domain_id',
            'xpark.channel_id',
        ];

        $active_sql = Activity::alias('activity')
            ->field([
                'activity.date',
                'activity.domain_id',
                'SUM(activity.active_users) as active_users',
            ])
            ->where('activity.status', 0)
            ->group('activity.date, activity.domain_id')
            ->buildSql();

        $result = XparkData::alias('xpark')
            ->field($fields)
            ->join('xpark_domain domain', 'domain.id = xpark.domain_id', 'left')
            ->join('xpark_apps apps', 'apps.id = xpark.app_id', 'left')
            ->leftJoin([$active_sql => 'activity'], 'xpark.domain_id = activity.domain_id AND xpark.a_date = activity.date')
            ->where('domain.is_hide', 1)
            ->where('xpark.status', 0)
            ->group(implode(',', $groupBy))
            ->order('xpark.a_date', 'desc');

        $sql    = $result->fetchSql(true)->select();
        $result = $result->paginate($limit);

        /*
         * 过滤信息
         */
        $channel_ids = array_values(array_unique(array_filter(array_column($result->items(), 'channel_id'))));
        $dates       = array_column($result->items(), 'a_date');
        $start_date  = substr(min($dates), 0, 10);
        $end_date    = substr(max($dates), 0, 10);

        /*
         * 附带信息查询
         */
        # H5投放支出、活跃
        $active_sql      = SLSActive::field(['channel_id', 'date', 'sum(active_users) as active_users'])->group('channel_id, date')->buildSql();
        $advertise_spend = SpendData::alias('spend')
            ->field([
                "CONCAT(DATE(spend.date), '|', spend.channel_id) as spend_key",
                'spend.date', 'spend.channel_id', 'sum(spend.spend) as spend',
                'COALESCE(active.active_users, 0) AS active_users'
            ])
            ->leftJoin([$active_sql => 'active'], 'spend.channel_id = active.channel_id AND spend.date = active.date')
            ->where('spend.channel_id', 'in', $channel_ids)
            ->where('spend.status', 0)
            ->whereBetweenTime('spend.date', $start_date, $end_date)
            ->group('spend.channel_id, spend.date')
            ->select()->toArray();
        $advertise_spend = array_column($advertise_spend, null, 'spend_key');
        # H5投放收入
        $advertise_revenue = XparkData::alias('xpark')
            ->field([
                "CONCAT(DATE(xpark.a_date), '|', xpark.channel_id) as revenue_key",
                'xpark.a_date', 'xpark.channel_id', 'sum(xpark.ad_revenue) as ad_revenue'
            ])
            ->where('xpark.channel_id', 'in', $channel_ids)
            ->where('xpark.status', 0)
            ->where('xpark.app_id', 29)
            ->whereBetweenTime('xpark.a_date', $start_date, $end_date)
            ->group('xpark.channel_id, xpark.a_date')
            ->select()->toArray();
        $advertise_revenue = array_column($advertise_revenue, null, 'revenue_key');
        # 游戏中心 显示层
        $active_sql = SLSActive::field([
            'channel_id', 'date', 'app_id', 'sum(new_users) as new_users', 'sum(active_users) as active_users'
        ])->group('channel_id, app_id, date')->buildSql();
        $show_data  = XparkData::alias('xpark')
            ->field([
                "CONCAT(DATE(xpark.a_date), '|', xpark.channel_id, '|', xpark.app_id) as revenue_key",
                'xpark.a_date', 'xpark.channel_id', 'xpark.app_id', 'sum(xpark.ad_revenue) as ad_revenue',
                'COALESCE(active.active_users, 0) AS active_users',
                'COALESCE(active.new_users, 0) AS new_users'
            ])
            ->join('xpark_domain domain', 'domain.id = xpark.domain_id', 'left')
            ->leftJoin([$active_sql => 'active'], 'xpark.channel_id = active.channel_id AND xpark.a_date = active.date AND xpark.app_id = active.app_id')
            ->where('domain.is_hide', 0)
            ->where('xpark.status', 0)
            ->whereBetweenTime('xpark.a_date', $start_date, $end_date)
            ->group('xpark.channel_id, xpark.a_date, xpark.app_id')
            ->order('xpark.a_date', 'desc')
            ->select()->toArray();
        $show_data  = array_column($show_data, null, 'revenue_key');


        /*
         * 数据加工
         */
        $data = [];
        foreach ($result->items() as $row) {
            $date                     = substr($row['a_date'], 0, 10);
            $channel_id               = $row['channel_id'];
            $hb_hide_revenue          = $row['xpark_ad_revenue'];
            $h5_advertise_spend       = $advertise_spend[$date . '|' . $channel_id]['spend'] ?? 0;
            $h5_advertise_revenue     = $advertise_revenue[$date . '|' . $channel_id]['ad_revenue'] ?? 0;
            $h5_advertise_roi         = $h5_advertise_spend ? $h5_advertise_revenue / $h5_advertise_spend * 100 : 0;
            $h5_advertise_active      = $advertise_spend[$date . '|' . $channel_id]['active_users'] ?? 0;
            $hb_show_revenue          = $show_data[$date . '|' . $channel_id . '|' . $row['app_id']]['ad_revenue'] ?? 0;
            $hb_show_active           = $show_data[$date . '|' . $channel_id . '|' . $row['app_id']]['active_users'] ?? 0;
            $hb_show_new              = $show_data[$date . '|' . $channel_id . '|' . $row['app_id']]['new_users'] ?? 0;
            $dimensions_spend_model   = $this->channel[$channel_id]['spend_model'] ?? 0;
            $dimensions_revenue_model = $this->channel[$channel_id]['revenue_model'] ?? 0;
            $dimensions_user_model    = $this->channel[$channel_id]['user_model'] ?? 0;
            // 支出维度 = 【HB收入】/（【H5投放支出】+【游戏中心新增】*【显示层CPC成本】）
            $DENOMINATOR      = $h5_advertise_spend + $hb_show_new * $this->cpc_cost_show;
            $dimensions_spend = $DENOMINATOR == 0 ? 0 : $hb_hide_revenue / $DENOMINATOR;
            // 支出维度差值 = （【支出维度标准模型】-【支出维度】）*（【H5投放支出】+【游戏中心新增】*【显示层CPC成本】）
            $dimensions_spend_gap = ($dimensions_spend_model - $dimensions_spend) * $DENOMINATOR;
            // 收入维度 = 【HB收入】/（【H5投放收入】+【游戏中心收入】）
            $DENOMINATOR        = $h5_advertise_revenue + $hb_show_revenue;
            $dimensions_revenue = $DENOMINATOR == 0 ? 0 : $hb_hide_revenue / $DENOMINATOR;
            // 收入维度差值 = （【收入维度标准模型】-【收入维度】）*（【H5投放收入】+【游戏中心收入】）
            $dimensions_revenue_gap = ($dimensions_revenue_model - $dimensions_revenue) * $DENOMINATOR;
            // 用户维度 = 【HB活跃】/（【H5投放活跃】+【游戏中心活跃】）
            $hb_hide_active  = $row['xpark_active_users'];
            $DENOMINATOR     = $h5_advertise_active + $hb_show_active;
            $dimensions_user = $DENOMINATOR == 0 ? 0 : $hb_hide_active / $DENOMINATOR;
            // 用户维度差值 = （【用户维度标准模型】-【用户维度】）*（【H5投放活跃】+【游戏中心活跃】）
            $dimensions_user_gap = ($dimensions_user_model * $dimensions_user) * $DENOMINATOR;


            $item = [
                "date"                     => $date,
                "channel_flag"             => $row['channel_full'],
                "app_name"                 => $row['app_name'],
                "hb_domain_name"           => $row['sub_channel'],
                "h5_advertise_spend"       => round($h5_advertise_spend, 2),
                "h5_advertise_revenue"     => round($h5_advertise_revenue, 2),
                "h5_advertise_roi"         => $h5_advertise_spend == 0 ? '-' : round($h5_advertise_roi, 2) . '%',
                "h5_advertise_active"      => $h5_advertise_active,
                "hb_show_active"           => $hb_show_active,
                "hb_show_new"              => $hb_show_new,
                "hb_show_revenue"          => round($hb_show_revenue, 2),
                "hb_hide_revenue"          => round($hb_hide_revenue, 2),
                "dimensions_spend"         => $dimensions_spend,
                "dimensions_spend_model"   => $dimensions_spend_model,
                "dimensions_spend_gap"     => round($dimensions_spend_gap, 2),
                "dimensions_revenue"       => $dimensions_revenue,
                "dimensions_revenue_model" => $dimensions_revenue_model,
                "dimensions_revenue_gap"   => round($dimensions_revenue_gap, 2),
                "hb_hide_active"           => $hb_hide_active,
                "dimensions_user"          => $dimensions_user,
                "dimensions_user_model"    => $dimensions_user_model,
                "dimensions_user_gap"      => round($dimensions_user_gap, 2),
            ];

            $data[] = $item;
        }


        $this->success('', [
            'sql'     => $sql,
            'list'    => $data,
            'total'   => $result->total(),
            'columns' => array_values($this->columns),
        ]);
    }


}