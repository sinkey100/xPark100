<?php

namespace app\admin\controller\xpark;

use app\common\controller\Backend;

/**
 * 域名
 */
class Domain extends Backend
{
    /**
     * Domain模型对象
     * @var object
     * @phpstan-var \app\admin\model\xpark\Domain
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\xpark\Domain();
    }

    public function index(): void
    {

        $domain_filter = count($this->auth->domain_arr) > 0 ? $this->auth->domain_arr : false;

        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where);

        if($domain_filter){
            $res = $res->where('id', 'in', $domain_filter);
        }

        $res = $res->order($order)
            ->paginate($limit);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }
}