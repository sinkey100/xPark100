<?php

namespace app\admin\controller\h5;

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

    protected array $dis_rate_cp_ids = [1828995091155552256];

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
        }
        $map = array_values($map);

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

            // 利润
            if (in_array($this->auth->id, $this->dis_rate_cp_ids)) {
                $profit_real = $profit_fake = $v['revenue'] - $v['spend'];
                $share       = $profit_real * $this->share_rate;
                $revenue     = $v['revenue'];
            } else {
                $profit_real = $v['revenue'] - $v['spend'];
                $share       = $profit_real * $this->share_rate;
                $profit_fake = $share / $this->fake_rate;
                $profit_gap  = $profit_real - $profit_fake;
                $revenue     = $v['revenue'] - $profit_gap;
            }

            $total['revenue'] += $revenue;
            $total['spend']   += $v['spend'];
            $total['profit']  += $profit_fake;
            $total['share']   += $share;

            $v['share']   = round($share, 2);
            $v['profit']  = round($profit_fake, 2);
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

}