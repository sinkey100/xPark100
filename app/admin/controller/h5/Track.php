<?php

namespace app\admin\controller\h5;

use app\common\controller\Backend;

/**
 * 投放事件管理
 */
class Track extends Backend
{
    /**
     * Track模型对象
     * @var object
     * @phpstan-var \app\admin\model\h5\Track
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\h5\Track();
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}