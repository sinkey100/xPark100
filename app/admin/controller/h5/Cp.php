<?php

namespace app\admin\controller\h5;

use app\admin\model\CpRate;
use app\admin\model\xpark\Utc as UtcData;
use app\common\controller\Backend;
use app\admin\model\xpark\Apps;
use app\admin\model\spend\Data as SpendData;
use sdk\QueryTimeStamp;
use think\facade\Db;

class Cp extends Backend
{
    protected object $model;

    protected array|string $preExcludeFields = ['id'];

    protected string|array $quickSearchField = ['id'];

    protected array $apps = [];

    protected float $share_rate = 0.15;
    protected float $fake_rate  = 0.3;
    // 不操作数据的用户
    protected array $dis_rate_cp_ids = [1828995091155552256, 1847004509269457920];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\xpark\Data;
        $apps        = Apps::alias('apps')->field(['apps.*'])->select()->toArray();
        $this->apps  = array_column($apps, null, 'id');
    }

    public function index(): void
    {
        QueryTimeStamp::start();
        $hideTimestamp = request()->param('hideTimestamp/d', 0);

        $app_filter = array_column(Apps::field('id')->where('cp_admin_id', $this->auth->id)->select()->toArray(), 'id');
        if ($this->auth->id > 1 && count($app_filter) == 0) $app_filter = [1];

        $map = $revenue_map = $spend_map = [];
        if ($app_filter) {
            $map['app_id'] = ['app_id', 'in', $app_filter];
        }


        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $admin_id = $this->auth->id;
        foreach ($where as $v) {
            if ($v[0] == 'data.date') {
                $revenue_map[] = ['a_date', $v[1], $v[2]];
                $spend_map[]   = ['date', $v[1], $v[2]];
            }
            if ($v[0] == 'data.app_id') {
                // 防止越权
                if ($app_filter && !in_array($v[2], $app_filter)) $this->error();
                $map['app_id'] = ['app_id', '=', $v[2]];
            }
            if ($v[0] == 'data.admin_id' && $this->auth->id == 1) {
                // 管理员查看其他cp的数据
                $admin_id   = $v[2];
                $app_filter = array_column(Apps::field('id')->where('cp_admin_id', $admin_id)->select()->toArray(), 'id');
                $map['app_id'] = ['app_id', 'in', $app_filter];
            }
        }
        $map = array_values($map);

        $cp_rate = CpRate::where('admin_id', $admin_id)->order('date', 'asc')->select()->toArray();
        if (empty($cp_rate)) $this->error('内部错误');

        $revenue_sql = UtcData::field("DATE(a_date) AS date, app_id, ad_revenue, 0 AS spend")->where($map)->where($revenue_map)->fetchSql(true)->select();
        $spend_sql   = SpendData::field("date, app_id, 0 AS ad_revenue, spend")->where($map)->where($spend_map)->fetchSql(true)->select();

        $res = Db::table('(' . implode(' UNION ALL ', [$revenue_sql, $spend_sql]) . ') t')
            ->field("t.date, t.app_id, SUM(t.ad_revenue) AS revenue, SUM(t.spend) AS spend")
            ->group('t.date, t.app_id')
            ->order('t.date desc');

        $sql = $res->fetchSql(true)->select();
        $res = $res->paginate($limit);

        $list  = [];
        $total = [
            'id'       => 10000,
            'revenue'  => 0,
            'spend'    => 0,
            'profit'   => 0,
            'share'    => 0,
            'app_name' => '',
            'date'     => '',
        ];
        foreach ($res->items() as $v) {
            $v['date']     = $v['date'] . ' 00:00:00';
            $v['app_name'] = $this->apps[$v['app_id']]['app_name'] ?? '';

            $rate_row = $this->getClosestBefore($cp_rate, substr($v['date'], 0, 10));

            // 利润
//            if ($this->auth->id == 1) {


                $result      = $this->change_revenue($v['spend'], $v['revenue'], $rate_row['share_rate'], $rate_row['show_rate']);
                $revenue     = $result['revenue'];
                $fake_profit = $result['profit'];
                $share       = $result['shareAmount'];


//            } else if (in_array($this->auth->id, $this->dis_rate_cp_ids)) {
//
//                $original_profit = $fake_profit = $v['revenue'] - $v['spend'];
//                $share           = $original_profit * $this->share_rate;
//                $revenue         = $v['revenue'];
//
//
//            } else {
//
//                $original_profit = $v['revenue'] - $v['spend'];
//                $share           = $original_profit * $this->share_rate;
//                $fake_profit     = $share / $this->fake_rate;
//                $profit_gap      = $original_profit - $fake_profit;
//                $revenue         = $v['revenue'] - $profit_gap;
//            }


            $total['revenue'] += $revenue;
            $total['spend']   += $v['spend'];
            $total['profit']  += $fake_profit;
            $total['share']   += $share;

            $v['share']   = round($share, 2);
            $v['profit']  = round($fake_profit, 2);
            $v['revenue'] = round($revenue, 2);
            $v['spend']   = round($v['spend'], 2);

            $list[] = $v;
        }

        $total['share']   = round($total['share'], 2);
        $total['profit']  = round($total['profit'], 2);
        $total['revenue'] = round($total['revenue'], 2);
        $total['spend']   = round($total['spend'], 2);
        if ($hideTimestamp != 1) $list[] = $total;

        $this->success('', [
            '_'      => $this->auth->id == 1 ? $sql : '',
            'list'   => $list,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
            'ts'     => QueryTimeStamp::end()
        ]);
    }

    protected function change_revenue(
        float $expense,
        float $revenue,
        float $shareRatio = 0.15,
        float $displayRatio = 0.30
    ): array
    {
        // 原始利润
        $originalProfit = $revenue - $expense;

        // 真实分成金额
        $shareAmount = $originalProfit * $shareRatio;

        // 如果真实分成比例等于展示比例，直接返回原收入、原利润以及分成金额
        if (abs($shareRatio - $displayRatio) < 1e-6) {
            return [
                'revenue'     => round($revenue, 2),
                'profit'      => round($originalProfit, 2),
                'shareAmount' => round($shareAmount, 2),
            ];
        }

        // 按展示比例反推“看上去的”利润
        // shareAmount / displayRatio = displayProfit
        $displayProfit = $shareAmount / $displayRatio;

        // 调整后的收入 = 支出 + displayProfit
        $adjustedRevenue = $expense + $displayProfit;

        return [
            'revenue'     => round($adjustedRevenue, 2),
            'profit'      => round($displayProfit, 2),
            'shareAmount' => round($shareAmount, 2),
        ];
    }

    protected function getClosestBefore(array $data, string $targetDate): ?array
    {
        $targetTs  = strtotime($targetDate);
        $closest   = null;
        $closestTs = null;

        foreach ($data as $item) {
            // 验证并解析当前项的日期
            if (!isset($item['date'])) {
                continue;
            }
            $itemTs = strtotime($item['date']);
            // 只考虑日期不晚于目标日期的项
            if ($itemTs <= $targetTs) {
                // 如果还未选定，或比当前最近记录更接近目标，则更新
                if ($closestTs === null || $itemTs > $closestTs) {
                    $closest   = $item;
                    $closestTs = $itemTs;
                }
            }
        }

        return $closest;
    }

}