<?php

namespace app\admin\controller\xpark;

use app\common\controller\Backend;

/**
 * 通道管理
 */
class Channel extends Backend
{
    /**
     * Channel模型对象
     * @var object
     * @phpstan-var \app\admin\model\xpark\Channel
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\xpark\Channel();
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}