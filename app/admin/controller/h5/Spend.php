<?php

namespace app\admin\controller\h5;

use app\admin\model\spend\Data as SpendData;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use sdk\QueryTimeStamp;
use app\admin\model\h5\Track as SLSTrack;
use app\admin\model\sls\Active as SLSActive;
use app\common\controller\Backend;
use think\facade\Db;

/**
 * 投放分析
 */
class Spend extends Backend
{

    protected object $model;

    protected array|string $preExcludeFields = ['id'];

    protected string|array $quickSearchField = ['id'];

    protected array $apps = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\xpark\Utc();
    }

    public function buildQuery(): array
    {
        // 维度信息获取
        $only_main_domain = $ony_account_name = false;
        $input_dimensions = $this->request->get('dimensions/a', []);
        $main_dimension   = $join_dimensions = $join_where = [];
        $active_join_on   = $track_join_on = $spend_join_on = [];
        $spend_dimensions = [];

        if (($input_dimensions['domain_id'] ?? 'false') == 'false' && ($input_dimensions['main_domain'] ?? 'false') == 'true') {
            $only_main_domain              = true;
            $input_dimensions['domain_id'] = 'true';
        }
        if (($input_dimensions['domain_id'] ?? 'false') == 'false' && ($input_dimensions['account_name'] ?? 'false') == 'true') {
            $ony_account_name              = true;
            $input_dimensions['domain_id'] = 'true';
        }

        foreach ($input_dimensions as $k => $v) {
            if ($k && $v == 'true') {
                if ($k == 'event_type') {
                    $main_dimension[] = 'track.event_type';
                } else if ($k == 'main_domain') {
                    $main_dimension[] = 'domain.main_domain';
                } else if ($k == 'account_name') {
                    $main_dimension[]   = 'spend.account_name';
                    $spend_dimensions[] = 'account_name';
                } else {
                    $main_dimension[] = 'utc.' . $k;
                }
            }
            if (in_array($k, ['a_date', 'domain_id', 'country_code', 'channel_id']) && $v == 'true') {
                $new_field         = $k == 'a_date' ? 'date' : $k;
                $join_dimensions[] = $new_field;
                $active_join_on[]  = "utc.$k = active.$new_field";
                $track_join_on[]   = "utc.$k = track.$new_field";
                $spend_join_on[]   = "utc.$k = spend.$new_field";
            }
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $check_roi = false;
        foreach ($where as $key => $item) {
            if (str_starts_with($item[0], 'ext.')) {
                $check_roi = $item[2];
                unset($where[$key]);
            }
            if (str_ends_with($item[0], 'date')) $join_where[] = ['date', $item[1], $item[2]];
            if (str_ends_with($item[0], 'country_code')) $join_where[] = ['country_code', $item[1], $item[2]];
            if (str_ends_with($item[0], 'domain_id')) $join_where[] = ['domain_id', $item[1], $item[2]];
            if (str_ends_with($item[0], 'channel_id')) $join_where[] = ['channel_id', $item[1], $item[2]];
        }
        $where = array_values($where);

        $field = array_merge($main_dimension, [
            'utc.sub_channel',
            'SUM(utc.ad_revenue) AS ad_revenue',
            'SUM(utc.requests) AS ad_requests',
            'SUM(utc.fills) AS ad_fills',
            'SUM(utc.clicks) AS ad_clicks',
            'SUM(utc.impressions) AS ad_impressions',
            'IFNULL(active.new_users, 0) AS new_users',
            'IFNULL(active.active_users, 0) AS active_users',
            'IFNULL(active.total_time, 0) AS total_time',

            'IFNULL(spend.spend_total, 0) AS spend_total',
            'IFNULL(spend.impressions, 0) AS spend_impressions',
            'IFNULL(spend.clicks, 0) AS spend_clicks',
            'IFNULL(spend.conversion, 0) AS spend_conversion',

            'IFNULL(track.valid_events, 0) AS valid_events',
            'IFNULL(track.invalid_events, 0) AS invalid_events',
            'IFNULL(track.anchored_count, 0) AS anchored_count',
            'IFNULL(track.banner_count, 0) AS banner_count',
            'IFNULL(track.fullscreen_count, 0) AS fullscreen_count',
        ]);
        if (in_array('utc.domain_id', $main_dimension)) $field = array_merge($field, ['domain.tag', 'domain.create_time as domain_date']);
        if (in_array('utc.channel_id', $main_dimension)) $field[] = 'channel.channel_alias';

        $active_sql = SLSActive::field(array_merge($join_dimensions, [
            'SUM(new_users) AS new_users', 'SUM(active_users) AS active_users', 'SUM(page_views) AS page_views', 'AVG(total_time) AS total_time'
        ]))->where($join_where)->where('app_id', 29)->group(implode(',', $join_dimensions))->buildSql();

        $track_sql = SLSTrack::field(array_merge($join_dimensions, [
            'event_type', 'SUM(valid_events) AS valid_events', 'SUM(invalid_events) AS invalid_events', 'SUM(anchored_count) AS anchored_count', 'SUM(banner_count) AS banner_count', 'SUM(fullscreen_count) AS fullscreen_count'
        ]))->where($join_where)->where('app_id', 29)->group(implode(',', $join_dimensions))->buildSql();

        $spend_sql = SpendData::field(array_merge($join_dimensions, [
            'account_name', 'SUM(impressions) AS impressions', 'SUM(clicks) AS clicks', 'SUM(conversion) AS conversion', 'SUM(spend) AS spend_total'
        ]))->where($join_where)->where('app_id', 29)->group(implode(',', array_merge($join_dimensions, $spend_dimensions)))->buildSql();

        $res = $this->model->field($field)
            ->alias($alias)
            ->join($active_sql . ' active', implode(' AND ', $active_join_on), 'left')
            ->join($track_sql . ' track', implode(' AND ', $track_join_on), 'left')
            ->join($spend_sql . ' spend', implode(' AND ', $spend_join_on), 'left')
            ->when(in_array('utc.domain_id', $main_dimension), function ($query) {
                return $query->join('xpark_domain domain', 'domain.id = utc.domain_id', 'left');
            })
            ->when(in_array('utc.channel_id', $main_dimension), function ($query) {
                return $query->join('xpark_channel channel', 'channel.id = utc.channel_id', 'left');
            })
            ->where($where)
            ->where('utc.status', 0)
            ->where('utc.app_id', 29)
            ->where('spend.spend_total', '>', 0)
            ->group(implode(',', $main_dimension))
            ->when($check_roi !== false, function ($query) use ($check_roi) {
                $operator = $check_roi == 1 ? '>=' : '<';
                return $query->having("SUM(utc.ad_revenue) $operator MAX(IFNULL(spend.spend_total, 0))");
            })
            ->order('utc.a_date', 'desc')
            ->order('utc.domain_id desc')
            ->order('ad_revenue desc');

        if ($only_main_domain || $ony_account_name) {
            $group = [];
            foreach ($main_dimension as $dimension) {
                if (!str_ends_with($dimension, 'domain_id')) {
                    $v       = explode('.', $dimension);
                    $group[] = $v[count($v) - 1];
                }
            }
            $res = Db::table($res->buildSql() . ' main')
                ->field(array_merge($group, [
                    'SUM(ad_revenue) AS ad_revenue',
                    'SUM(ad_requests) AS ad_requests',
                    'SUM(ad_fills) AS ad_fills',
                    'SUM(ad_clicks) AS ad_clicks',
                    'SUM(ad_impressions) AS ad_impressions',
                    'SUM(IFNULL(new_users, 0)) AS new_users',
                    'SUM(IFNULL(active_users, 0)) AS active_users',
                    'SUM(IFNULL(total_time, 0)) AS total_time',

                    'SUM(IFNULL(spend_total, 0)) AS spend_total',
                    'SUM(IFNULL(spend_impressions, 0)) AS spend_impressions',
                    'SUM(IFNULL(spend_clicks, 0)) AS spend_clicks',
                    'SUM(IFNULL(spend_conversion, 0)) AS spend_conversion',

                    'SUM(IFNULL(valid_events, 0)) AS valid_events',
                    'SUM(IFNULL(invalid_events, 0)) AS invalid_events',
                    'SUM(IFNULL(anchored_count, 0)) AS anchored_count',
                    'SUM(IFNULL(banner_count, 0)) AS banner_count',
                    'SUM(IFNULL(fullscreen_count, 0)) AS fullscreen_count',
                ]))
                ->group($group)
                ->when(in_array('a_date', $group), function ($query) {
                    return $query->order('a_date', 'desc');
                })
                ->order('ad_revenue desc');
        }

        return [$res, $limit];
    }

    public function index(): void
    {
        QueryTimeStamp::start();
        [$res, $limit] = $this->buildQuery();
        $sql = $res->fetchSql(true)->select();
        $res = $res->paginate($limit);

        $total = [
            'a_date'            => '本页汇总',
            'new_users'         => 0,
            'active_users'      => 0,
            'ad_revenue'        => 0,
            'ad_requests'       => 0,
            'ad_fills'          => 0,
            'ad_clicks'         => 0,
            'ad_impressions'    => 0,
            'spend_total'       => 0,
            'spend_impressions' => 0,
            'spend_clicks'      => 0,
            'spend_conversion'  => 0,
            'total_time'        => 0,
        ];
        foreach ($res->items() as $v) {
            $total['new_users']         += $v['new_users'];
            $total['active_users']      += $v['active_users'];
            $total['ad_revenue']        += $v['ad_revenue'];
            $total['ad_requests']       += $v['ad_requests'];
            $total['ad_fills']          += $v['ad_fills'];
            $total['ad_clicks']         += $v['ad_clicks'];
            $total['ad_impressions']    += $v['ad_impressions'];
            $total['spend_total']       += $v['spend_total'];
            $total['spend_impressions'] += $v['spend_impressions'];
            $total['spend_clicks']      += $v['spend_clicks'];
            $total['spend_conversion']  += $v['spend_conversion'];
        }

        $this->success('', [
            '_'      => $this->auth->id == 1 ? $sql : '',
            'list'   => $this->rate($res->items()),
            'foot'   => $this->rate([$total]),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
            'ts'     => QueryTimeStamp::end()
        ]);
    }

    protected function rate($data): array
    {
        foreach ($data as &$v) {
            $v['a_date']          = empty($v['a_date']) ? '' : (strlen($v['a_date']) == 19 ? substr($v['a_date'], 0, 10) : $v['a_date']);
            $v['domain_days']     = empty($v['domain_date']) ? '' : intval((time() - strtotime($v['domain_date'])) / 86400);
            $v['roi']             = $v['spend_total'] > 0 ? number_format((float)$v['ad_revenue'] / (float)$v['spend_total'] * 100, 2, '.', '') . '%' : '-';
            $v['per_display']     = empty($v['active_users']) ? '' : round($v['ad_impressions'] / $v['active_users'], 2);
            $v['ad_ecpm']         = empty($v['ad_impressions']) ? '-' : round($v['ad_revenue'] / $v['ad_impressions'] * 1000, 3);
            $v['ad_ctr']          = empty($v['ad_impressions']) ? '-' : round($v['ad_clicks'] / $v['ad_impressions'] * 100, 2) . '%';
            $v['ad_cpc']          = empty($v['ad_clicks']) ? '-' : round($v['ad_revenue'] / $v['ad_clicks'], 2);
            $v['ad_revenue']      = number_format((float)$v['ad_revenue'], 2, '.', '');
            $v['ad_fill_rate']    = empty($v['ad_requests']) ? '-' : round($v['ad_fills'] / $v['ad_requests'] * 100, 2) . '%';
            $v['spend_ctr']       = empty($v['spend_impressions']) ? '-' : round($v['spend_clicks'] / $v['spend_impressions'] * 100, 2) . '%';
            $v['spend_conv_rate'] = empty($v['spend_impressions']) ? '-' : round($v['spend_conversion'] / $v['spend_impressions'] * 100, 2) . '%';
            $v['spend_total']     = number_format((float)$v['spend_total'], 2, '.', '');
            $v['total_time']      = format_milliseconds((int)$v['total_time']);
            $v['gap']             = empty($v['spend_conversion']) ? '-' : round((1 - $v['ad_clicks'] / $v['spend_conversion']) * 100, 2) . '%';
        }
        return $data;
    }

}