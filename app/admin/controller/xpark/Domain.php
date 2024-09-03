<?php

namespace app\admin\controller\xpark;

use app\common\controller\Backend;
use app\admin\model\xpark\Domain as DomainModel;

/**
 * 域名
 */
class Domain extends Backend
{
    /**
     * Domain模型对象
     * @var object
     * @phpstan-var DomainModel
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id'];

    protected string|array $quickSearchField = ['id', 'domain'];

    protected array $withJoinTable = ['admin'];


    public function initialize(): void
    {
        parent::initialize();
        $this->model = new DomainModel();
    }

    public function index(): void
    {

        $domain_filter = array_column(
            DomainModel::field('id')->where('admin_id', $this->auth->id)->select()->toArray(),
            'id'
        );

        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where);

        if ($domain_filter) {
            $res = $res->where('domain.id', 'in', $domain_filter);
        }

        $res = $res->order($order)
            ->paginate($limit);

        $res->visible(['admin' => ['nickname']]);

        $list = $res->items();
        foreach ($list as &$v) {
            $v['full_name'] = $v['domain'] . ' - ' . ($v['admin']['nickname'] ?? '/');
        }

        $this->success('', [
            'list'   => $list,
            'total'  => $res->total(),
            'remark' => '',
        ]);
    }
}