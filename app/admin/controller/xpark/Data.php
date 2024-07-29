<?php

namespace app\admin\controller\xpark;

use Throwable;
use app\common\controller\Backend;

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

//    protected array $withJoinTable = ['domain'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\xpark\Data();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        $domain_filter = count($this->auth->domain_arr) > 0 ? $this->auth->domain_arr : false;

        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        $dimensions = $this->request->get('dimensions/a', []);
        $dimension = [];
        foreach ($dimensions as $k => $v){
            if($k && $v == 'true'){
                $dimension[] = $k;
            }
        }


        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $field = array_merge($dimension, [
            'SUM(requests) AS requests',
            'SUM(fills) AS fills',
            'SUM(impressions) AS impressions',
            'SUM(clicks) AS clicks',
            'SUM(ad_revenue) AS ad_revenue',
        ]);

        $res = $this->model->field($field)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where);



        if($domain_filter){
            $res = $res->where('domain_id', 'in', $domain_filter);
        }

//        $res = $res->fetchSql(true)->select();
//        $this->error($res);

        $res = $res->order('id', 'desc')
            ->group(implode(',', $dimension))
            ->paginate($limit);
        $res->visible(['domain' => ['domain']]);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}