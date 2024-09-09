<?php

namespace app\admin\controller\mi\instant;

use app\admin\model\mi\instant\ReportUnit;
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
     * @phpstan-var ReportUnit
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ReportUnit();
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
            ->order('DATE', 'desc')
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

        $total = [
            'ESTIMATED_EARNINGS'  => 0,
            'PAGE_VIEWS'    => 0,
            'AD_REQUESTS'       => 0,
            'IMPRESSIONS' => 0,
            'CLICKS'      => 0,
            'FILLS'      => 0,
            'date'      => ''
        ];
        foreach ($res->items() as $v) {
            $total['ESTIMATED_EARNINGS']  += $v['ESTIMATED_EARNINGS'];
            $total['PAGE_VIEWS']    += $v['PAGE_VIEWS'];
            $total['AD_REQUESTS']       += $v['AD_REQUESTS'];
            $total['IMPRESSIONS'] += $v['IMPRESSIONS'];
            $total['CLICKS']      += $v['CLICKS'];
            $total['FILLS']      += $v['FILLS'];
        }

        // 总收入
        $total['revenue'] = round($total['ESTIMATED_EARNINGS'], 2);
        // 填充率
        $total['coverage'] = round($total['FILLS'] / ($total['AD_REQUESTS'] ? : 1) * 100, 2) . '%';
        // 点击率
        $total['ctr'] = round($total['CLICKS'] / ($total['IMPRESSIONS'] ? : 1) * 100, 2) . '%';
        // 单价
        $total['cpc'] = round($total['ESTIMATED_EARNINGS'] / ($total['CLICKS'] ? : 1), 2);
        // eCPM
        $total['ecpm'] = round($total['ESTIMATED_EARNINGS'] / ($total['IMPRESSIONS'] ? : 1) * 1000, 3);


        $list = array_merge($res->items(), [$total]);

        $this->success('', [
            'list'   => $list,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }
}