<?php

namespace app\admin\controller\xpark;

use app\admin\model\xpark\DomainRate;
use Throwable;
use app\common\controller\Backend;
use app\admin\model\xpark\Domain;
use app\admin\model\xpark\Data;

/**
 * 应用管理
 */
class Apps extends Backend
{
    /**
     * Apps模型对象
     * @var object
     * @phpstan-var \app\admin\model\xpark\Apps
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'createtime', 'updatetime'];

    protected array $withJoinTable = [];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\xpark\Apps();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->alias($alias)
            ->field(['apps.*', 'admin.nickname as admin_nickname', 'cp_admin.nickname as cp_admin_nickname'])
            ->join('admin admin', 'admin.id = apps.admin_id', 'left')
            ->join('admin cp_admin', 'cp_admin.id = apps.cp_admin_id', 'left')
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */

    public function edit(): void
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }

        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds && !in_array($row[$this->dataLimitField], $dataLimitAdminIds)) {
            $this->error(__('You have no permission'));
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('edit');
                        $data[$pk] = $row[$pk];
                        $validate->check($data);
                    }
                }

                Domain::where('app_id', $row['id'])->update([
                    'app_id'   => null,
                    'admin_id' => null
                ]);
                DomainRate::where('app_id',$row['id'])->where('date', date("Y-m-d"))->delete();

                $domains = Domain::where('id', 'in', $data['domain_arr'])->select();
                foreach($domains as $domain){
                    $domain->app_id = $row['id'];
                    $domain->admin_id = $row['admin_id'];
                    $domain->save();
                    DomainRate::where('domain', $domain->domain)->where('date', date("Y-m-d"))->delete();
                }

                $result = $row->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }

        $row->domain_arr = Domain::where('app_id', $id)->select()->toArray();
        $row->domain_arr = array_column($row->domain_arr, 'id');

        $this->success('', [
            'row' => $row
        ]);
    }

    public function select(): void
    {
        $map = [];
        if ($this->auth->id > 1) {
            $map['admin_id|cp_admin_id'] = $this->auth->id;
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->where($map)
            ->order($order)
            ->paginate($limit);
        $res->visible(['admin' => ['nickname']]);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function monthDomains(): void
    {
        $month   = $this->request->post('month/s', '');
        $app_ids = $this->request->post('app_ids/a', []);
        if (!$month || empty($app_ids)) $this->error('参数错误');

        $domains = Data::whereMonth('a_date', $month)
            ->where('app_id', 'in', $app_ids)
            ->group('sub_channel')
            ->select()->toArray();

        if(count($domains) == 0) $this->error('当月没有数据');

        $domains = array_column($domains, 'sub_channel');

        $this->success('', [
            'domains' => $domains
        ]);

    }
}