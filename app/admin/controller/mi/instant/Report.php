<?php

namespace app\admin\controller\mi\instant;

use app\admin\model\mi\instant\ReportUrl;
use Throwable;
use app\common\controller\Backend;

/**
 * Mi Instant
 */
class Report extends Backend
{
    /**
     * Report模型对象
     * @var object
     * @phpstan-var \app\admin\model\mi\instant\Report
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\mi\instant\Report();
    }


    protected function calcData()
    {
        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        $dimensions = $this->request->get('dimensions/a', []);
        $dimension  = [];
        foreach ($dimensions as $k => $v) {
            if ($k && $v == 'true') {
                $dimension[] = $k;
            }
        }

        if(in_array('PAGE_URL', $dimension)){
            $this->model = new ReportUrl();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $field = array_merge($dimension, [
            'SUM(FILLS) AS FILLS',
            'SUM(PAGE_VIEWS) AS PAGE_VIEWS',
            'SUM(AD_REQUESTS) AS AD_REQUESTS',
            'SUM(IMPRESSIONS) AS IMPRESSIONS',
            'SUM(ESTIMATED_EARNINGS) AS ESTIMATED_EARNINGS',
            'SUM(CLICKS) AS CLICKS',
        ]);

        $res = $this->model->field($field)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order('id', 'desc')
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
        $res = $res->paginate($limit);
        $res->visible(['domain' => ['domain']]);
//
//        $total = [
//            'id'          => 10000,
//            'ad_revenue'  => 0,
//            'requests'    => 0,
//            'fills'       => 0,
//            'impressions' => 0,
//            'clicks'      => 0,
//            'a_date'      => '',
//        ];
//        foreach ($res->items() as $v) {
//            $total['ad_revenue']  += $v['ad_revenue'];
//            $total['requests']    += $v['requests'];
//            $total['fills']       += $v['fills'];
//            $total['impressions'] += $v['impressions'];
//            $total['clicks']      += $v['clicks'];
//        }
//
//        // 总收入
//        $total['ad_revenue'] = round($total['ad_revenue'], 2);
//        // 填充率
//        $total['fill_rate'] = round($total['fills'] / ($total['requests'] ? : 1) * 100, 2) . '%';
//        // 点击率
//        $total['click_rate'] = round($total['clicks'] / ($total['impressions'] ? : 1) * 100, 2) . '%';
//        // 单价
//        $total['unit_price'] = round($total['ad_revenue'] / ($total['clicks'] ? : 1), 2);
//        // eCPM
//        $total['ecpm'] = round($total['ad_revenue'] / ($total['impressions'] ? : 1) * 1000, 2);
//
//
//        $list = array_merge($res->items(), [$total]);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }
}