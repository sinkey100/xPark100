<?php

namespace app\admin\controller\h5;

use app\admin\model\h5\ChannelRevenue;
use app\admin\model\xpark\Activity;
use app\common\controller\Backend;
use app\admin\model\xpark\Channel;
use app\admin\model\xpark\Apps;
use app\admin\model\sls\Active as SLSActive;
use app\admin\model\xpark\Utc as XparkData;
use app\admin\model\spend\Data as SpendData;
use sdk\QueryTimeStamp;

class Risk extends Backend
{
    protected object $model;

    protected array|string $preExcludeFields = ['id'];

    protected string|array $quickSearchField = ['id'];

    protected float $cpc_cost_show    = 0.04;
    protected array $apps             = [];
    protected array $channel          = [];
    protected array $dimensions_input = [];
    protected array $pageDates        = [];
    protected array $use_Adate        = ['data', 'xpark'];
    protected array $special_channel  = [4, 5];
    protected array $date_range       = [];
    protected array $oem_appid        = [23, 27];

    public function initialize(): void
    {
        parent::initialize();
        $this->model   = new \app\admin\model\xpark\Data;
        $apps          = Apps::alias('apps')->field(['apps.*'])->select()->toArray();
        $this->apps    = array_column($apps, null, 'id');
        $channel       = Channel::where('is_own', 1)->select()->toArray();
        $this->channel = array_column($channel, null, 'id');
    }

    public function index(): void
    {
        QueryTimeStamp::start();
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $this->dimensions_input = $this->request->get('dimensions/a', []);
        $this->dimensions_input = array_keys(array_filter($this->dimensions_input, fn($value) => $value === "true"));

        // CY LL
        $channel_where_raw = array_column($where, null, 0);
        $channel_where     = [];
        if (isset($channel_where_raw['data.a_date'])) {
            $this->date_range = $channel_where_raw['data.a_date'][2];
            $channel_where[]  = ['date', 'between', $channel_where_raw['data.a_date'][2]];
        }
        $channel_revenue = ChannelRevenue::field(['channel_id', 'sum(revenue) as revenue'])->where($channel_where)->group('channel_id')->select();
        $channel_revenue = array_column($channel_revenue->toArray(), null, 'channel_id');
        /*
         * 主查询语句
         */
        $_main_dimensions   = $this->getDimensionsFields('data');
        $_main_dimensions[] = 'data.channel_id';

        $_active_dimensions   = $this->getDimensionsFields('activity');
        $_active_dimensions[] = 'activity.channel_id';
        $_active_join_on      = $this->getJoinOn('data', 'activity', $_active_dimensions);

        $order_by = ['xpark_ad_revenue desc'];
        if (in_array('data.a_date', $_main_dimensions)) array_unshift($order_by, 'data.a_date desc');

        $fields = array_merge($_main_dimensions, [
            'data.channel_full', 'sum(data.ad_revenue) as xpark_ad_revenue',
            'apps.app_name',
            'domain.domain', 'domain.is_hide as domain_is_hide',
            'COALESCE(activity.active_users, 0) AS xpark_active_users'
        ]);

        $active_sql = Activity::alias('activity')
            ->field(array_merge($_active_dimensions, ['SUM(activity.active_users) as active_users']))
            ->where('activity.status', 0)
            ->where('activity.date', 'between', $this->date_range)
            ->where('activity.app_id', 'not in', $this->oem_appid)
            ->group(implode(',', $_active_dimensions))
            ->buildSql();

        $result = XparkData::alias('data')
            ->field($fields)
            ->join('xpark_domain domain', 'domain.id = data.domain_id', 'left')
            ->join('xpark_apps apps', 'apps.id = data.app_id', 'left')
            ->leftJoin([$active_sql => 'activity'], $_active_join_on)
            ->where('domain.is_hide', 1)
            ->where('data.status', 0)
            ->where('data.app_id', 'not in', $this->oem_appid)
            ->where('data.channel_id', 'in', array_keys($this->channel))
            ->where($where)
            ->group(implode(',', $_main_dimensions))
            ->order($order_by);

        $sql    = $result->fetchSql(true)->select();
        $result = $result->paginate($limit);

        /*
         * 过滤信息
         */
        $channel_ids     = array_values(array_unique(array_filter(array_column($result->items(), 'channel_id'))));
        $this->pageDates = array_column($result->items(), 'a_date');

        /*
         * 附带信息查询
         */
        # H5投放支出、活跃
        $_spend_dimensions    = $this->getDimensionsFields('spend', ['app_id', 'domain_id']);
        $_spend_dimensions[]  = 'spend.channel_id';
        $_active_dimensions   = $this->getDimensionsFields('active', ['app_id', 'domain_id']);
        $_active_dimensions[] = 'active.channel_id';
        $_active_join_on      = $this->getJoinOn('spend', 'active', $_active_dimensions);

        $active_sql      = SLSActive::alias('active')
            ->field(array_merge($_active_dimensions, ['sum(active_users) as active_users']))
            ->where($this->getBetweenTime('active.date'))
            ->group(implode(',', $_active_dimensions))->buildSql();
        $advertise_spend = SpendData::alias('spend')
            ->field(array_merge($_spend_dimensions, [
                "CONCAT(" . (in_array('spend.date', $_spend_dimensions) ? 'DATE(spend.date),' : '') . " '|', spend.channel_id) as spend_key",
                'sum(spend.spend) as spend',
                'COALESCE(active.active_users, 0) AS active_users'
            ]))
            ->leftJoin([$active_sql => 'active'], $_active_join_on)
            ->where('spend.channel_id', 'in', $channel_ids)
            ->where('spend.status', 0)
            ->where($this->getBetweenTime('spend.date'))
            ->group(implode(',', $_spend_dimensions))
            ->select()->toArray();
        $advertise_spend = array_column($advertise_spend, null, 'spend_key');

        # H5投放收入
        $_revenue_dimensions   = $this->getDimensionsFields('xpark', ['app_id', 'domain_id']);
        $_revenue_dimensions[] = 'xpark.channel_id';

        $advertise_revenue = XparkData::alias('xpark')
            ->field(array_merge($_revenue_dimensions, [
                "CONCAT(" . (in_array('a_date', $this->dimensions_input) ? 'DATE(xpark.a_date),' : '') . " '|', xpark.channel_id) as revenue_key",
                'sum(xpark.ad_revenue) as ad_revenue'
            ]))
            ->where('xpark.channel_id', 'in', $channel_ids)
            ->where('xpark.status', 0)
            ->where('xpark.app_id', 29)
            ->where($this->getBetweenTime('xpark.a_date'))
            ->group(implode(',', $_revenue_dimensions))
            ->select()->toArray();
        $advertise_revenue = array_column($advertise_revenue, null, 'revenue_key');

        # 游戏中心 显示层
        $_show_dimensions     = $this->getDimensionsFields('xpark', ['domain_id']);
        $_show_dimensions[]   = 'xpark.channel_id';
        $_active_dimensions   = $this->getDimensionsFields('active', ['domain_id']);
        $_active_dimensions[] = 'active.channel_id';
        $_active_join_on      = $this->getJoinOn('xpark', 'active', $_active_dimensions);

        $active_sql = SLSActive::alias('active')
            ->field(array_merge($_active_dimensions, ['sum(active.new_users) as new_users', 'sum(active.active_users) as active_users']))
            ->join('xpark_domain domain', 'active.domain_id = domain.id and domain.is_hide = 0', 'inner')
            ->join('xpark_apps apps', 'apps.id = active.app_id', 'left')
            ->where($this->getBetweenTime('active.date'))
            ->where('apps.app_type', 'in', [0, 1])
            ->group(implode(',', $_active_dimensions))->buildSql();
        $show_data  = XparkData::alias('xpark')
            ->field(array_merge($_show_dimensions, [
                "CONCAT(" . (in_array('xpark.a_date', $_show_dimensions) ? 'DATE(xpark.a_date),' : '') . " '|', xpark.channel_id, '|' " . (in_array('xpark.app_id', $_show_dimensions) ? ',DATE(xpark.app_id)' : '') . ") as revenue_key",
                'sum(xpark.ad_revenue) as ad_revenue',
                'COALESCE(active.active_users, 0) AS active_users',
                'COALESCE(active.new_users, 0) AS new_users'
            ]))
            ->join('xpark_domain domain', 'domain.id = xpark.domain_id', 'left')
            ->join('xpark_apps apps', 'apps.id = xpark.app_id', 'left')
            ->leftJoin([$active_sql => 'active'], $_active_join_on)
            ->where('domain.is_hide', 0)
            ->where('apps.app_type', 'in', [0, 1])
            ->where('xpark.status', 0)
            ->where($this->getBetweenTime('xpark.a_date'))
            ->group(implode(',', $_show_dimensions))
            ->order('xpark.a_date', 'desc')
            ->select()->toArray();
        $show_data  = array_column($show_data, null, 'revenue_key');

        # OEM 数量
        $_show_dimensions     = $this->getDimensionsFields('xpark', ['domain_id']);
        $_show_dimensions[]   = 'xpark.channel_id';
        $_active_dimensions   = $this->getDimensionsFields('activity', ['domain_id']);
        $_active_dimensions[] = 'activity.channel_id';
        $_active_join_on      = $this->getJoinOn('xpark', 'activity', $_active_dimensions);

        $active_sql = Activity::alias('activity')
            ->field(array_merge($_active_dimensions, ['SUM(activity.active_users) as active_users']))
            ->where('activity.status', 0)
            ->where($this->getBetweenTime('activity.date'))
            ->where('activity.app_id', 'in', $this->oem_appid)
            ->group(implode(',', $_active_dimensions))
            ->buildSql();

        $oem_data = XparkData::alias('xpark')
            ->field(array_merge($_show_dimensions, [
                "CONCAT(" . (in_array('xpark.a_date', $_show_dimensions) ? 'DATE(xpark.a_date),' : '') . " '|', xpark.channel_id, '|' " . (in_array('xpark.app_id', $_show_dimensions) ? ',DATE(xpark.app_id)' : '') . ") as revenue_key",
                'sum(xpark.ad_revenue) as ad_revenue',
                'COALESCE(activity.active_users, 0) AS active_users',
            ]))
            ->join('xpark_domain domain', 'domain.id = xpark.domain_id', 'left')
            ->leftJoin([$active_sql => 'activity'], $_active_join_on)
            ->where('xpark.status', 0)
            ->where('xpark.app_id', 'in', $this->oem_appid)
            ->where($this->getBetweenTime('xpark.a_date'))
            ->group(implode(',', $_show_dimensions))
            ->order('xpark.a_date', 'desc')
            ->select()->toArray();
        $oem_data = array_column($oem_data, null, 'revenue_key');


        /*
         * 数据加工
         */
        $data = [];
        foreach ($result->items() as $row) {
            $date                     = empty($row['a_date']) ? '' : substr($row['a_date'], 0, 10);
            $channel_id               = $row['channel_id'];
            $hb_hide_revenue          = $row['xpark_ad_revenue'];
            $h5_advertise_spend       = $advertise_spend[$date . '|' . $channel_id]['spend'] ?? 0;
            $h5_advertise_revenue     = $advertise_revenue[$date . '|' . $channel_id]['ad_revenue'] ?? 0;
            $h5_advertise_roi         = $h5_advertise_spend ? $h5_advertise_revenue / $h5_advertise_spend * 100 : 0;
            $h5_advertise_active      = $advertise_spend[$date . '|' . $channel_id]['active_users'] ?? 0;
            $hb_show_revenue          = $show_data[$date . '|' . $channel_id . '|' . ($row['app_id'] ?? '')]['ad_revenue'] ?? 0;
            $hb_show_active           = $show_data[$date . '|' . $channel_id . '|' . ($row['app_id'] ?? '')]['active_users'] ?? 0;
            $hb_show_new              = $show_data[$date . '|' . $channel_id . '|' . ($row['app_id'] ?? '')]['new_users'] ?? 0;
            $oem_revenue              = $oem_data[$date . '|' . $channel_id . '|' . ($row['app_id'] ?? '')]['ad_revenue'] ?? 0;
            $oem_active               = $oem_data[$date . '|' . $channel_id . '|' . ($row['app_id'] ?? '')]['active_users'] ?? 0;
            $dimensions_spend_model   = $this->channel[$channel_id]['spend_model'] ?? 0;
            $dimensions_revenue_model = $this->channel[$channel_id]['revenue_model'] ?? 0;
            $dimensions_user_model    = $this->channel[$channel_id]['user_model'] ?? 0;
            // 支出维度 = 【HB收入】/（【HB收入】+（【H5投放支出】+【游戏中心新增】*【显示层CPC成本】+ 【OEM收入】）
            $DENOMINATOR      = $hb_hide_revenue + ($h5_advertise_spend + $hb_show_new * $this->cpc_cost_show + $oem_revenue);
            $dimensions_spend = $DENOMINATOR == 0 ? 0 : $hb_hide_revenue / $DENOMINATOR;
            // 支出维度差值 = （【支出维度标准模型】-【支出维度】）*（【HB收入】+【H5投放支出】+【游戏中心新增】*【显示层CPC成本】+【OEM收入】）
            $dimensions_spend_gap = ($dimensions_spend_model - $dimensions_spend) * $DENOMINATOR;
            // 收入维度 = 【HB收入】/（【H5投放收入】+【游戏中心收入】+【HB收入】+【OEM收入】）
            $DENOMINATOR        = $h5_advertise_revenue + $hb_show_revenue + $hb_hide_revenue + $oem_revenue;
            $dimensions_revenue = $DENOMINATOR == 0 ? 0 : $hb_hide_revenue / $DENOMINATOR;
            if (in_array($channel_id, $this->special_channel)) {
                // CY LL 收入维度 = （【HB收入】/ 【账号流水】）
                $flow               = $channel_revenue[$channel_id]['revenue'];
                $dimensions_revenue = $flow == 0 ? 0 : $hb_hide_revenue / $flow;
            }
            // 收入维度差值 = （【收入维度标准模型】-【收入维度】）*（【H5投放收入】+【游戏中心收入】+【HB收入】）
            $dimensions_revenue_gap = ($dimensions_revenue_model - $dimensions_revenue) * $DENOMINATOR;
            // 用户维度 = 【HB活跃】/（【HB活跃】+【H5投放活跃】+【游戏中心活跃】）
            $hb_hide_active  = $row['xpark_active_users'];
            $DENOMINATOR     = $h5_advertise_active + $hb_show_active + $hb_hide_active + $oem_active;
            $dimensions_user = $DENOMINATOR == 0 ? 0 : $hb_hide_active / $DENOMINATOR;
            // 用户维度差值 =（【用户维度标准模型】-【用户维度】）*（【HB活跃】+【H5投放活跃】+【游戏中心活跃】）
            $dimensions_user_gap = ($dimensions_user_model * $dimensions_user) * $DENOMINATOR;

            // CY LL
            if (in_array($channel_id, $this->special_channel)) {
                $dimensions_user_model    = $dimensions_spend_model = $dimensions_user_gap = $dimensions_spend_gap = '-';
                $dimensions_revenue_model = 0.1;
            }

            $item = [
                // 日期
                "date"                     => $date,
                // 账号
                "channel_flag"             => $row['channel_full'],
                // 应用
                "app_name"                 => $row['app_name'],
                // HB链接
                "hb_domain_name"           => $row['domain'],
                // H5投放支出
                "h5_advertise_spend"       => round($h5_advertise_spend, 2),
                // H5投放收入
                "h5_advertise_revenue"     => round($h5_advertise_revenue, 2),
                // h5_advertise_roi
                "h5_advertise_roi"         => $h5_advertise_spend == 0 ? '-' : round($h5_advertise_roi, 2) . '%',
                // H5投放活跃
                "h5_advertise_active"      => $h5_advertise_active,
                // 游戏中心活跃
                "hb_show_active"           => $hb_show_active,
                // 游戏中心新增
                "hb_show_new"              => $hb_show_new,
                // 游戏中心收入
                "hb_show_revenue"          => round($hb_show_revenue, 2),
                // OEM活跃
                "oem_active"               => $oem_active,
                // OEM收入
                "oem_revenue"              => round($oem_revenue, 2),
                // HB收入
                "hb_hide_revenue"          => round($hb_hide_revenue, 2),
                // 支出维度
                "dimensions_spend"         => $dimensions_spend,
                // 支出维度标准模型
                "dimensions_spend_model"   => $dimensions_spend_model,
                // 支出维度差值
                "dimensions_spend_gap"     => $dimensions_spend_gap == '-' ? '-' : round($dimensions_spend_gap, 2),
                // 收入维度
                "dimensions_revenue"       => $dimensions_revenue,
                // 收入维度标准模型
                "dimensions_revenue_model" => $dimensions_revenue_model,
                // 收入维度差值
                "dimensions_revenue_gap"   => $dimensions_revenue_gap == '-' ? '-' : round($dimensions_revenue_gap, 2),
                // 用户维度
                "dimensions_user"          => $dimensions_user,
                // HB活跃
                "hb_hide_active"           => $hb_hide_active,
                // 用户维度标准模型
                "dimensions_user_model"    => $dimensions_user_model,
                // 用户维度差值
                "dimensions_user_gap"      => $dimensions_user_gap == '-' ? '-' : round($dimensions_user_gap, 2),
            ];

            $data[] = $item;
        }


        $this->success('', [
            'sql'   => $sql,
            'list'  => $data,
            'total' => $result->total(),
            'ts'    => QueryTimeStamp::end()
        ]);
    }

    protected function getDimensionsFields(string $prefix, array $exclude = []): array
    {
        $arr = [];
        foreach ($this->dimensions_input as $field) {
            if (in_array($field, $exclude)) continue;
            if (!in_array($prefix, $this->use_Adate) && $field == 'a_date') $field = 'date';
            $arr[] = $prefix . '.' . $field;
        }
        return $arr;
    }

    protected function getJoinOn(string $prefix_1, string $prefix_2, array $dimensions): string
    {
        $arr = [];
        foreach ($dimensions as $field) {
            $field   = str_replace($prefix_2 . '.', '', $field);
            $field_1 = (in_array($prefix_1, $this->use_Adate) && $field == 'date') ? 'a_date' : $field;
            $field_2 = (in_array($prefix_2, $this->use_Adate) && $field == 'date') ? 'a_date' : $field;
            $arr[]   = $prefix_1 . '.' . $field_1 . ' = ' . $prefix_2 . '.' . $field_2;
        }
        return implode(' AND ', $arr);
    }

    protected function getBetweenTime(string $field): array
    {
        if (!empty($this->date_range)) return [[$field, 'between', $this->date_range]];
        if (empty($this->pageDates)) return [];
        $start_date = substr(min($this->pageDates), 0, 10);
        $end_date   = substr(max($this->pageDates), 0, 10);
        return [[$field, 'between', [$start_date, $end_date]]];
    }

}