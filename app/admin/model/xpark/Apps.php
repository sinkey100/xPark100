<?php

namespace app\admin\model\xpark;

use think\Model;

/**
 * Apps
 */
class Apps extends Model
{
    // 表名
    protected $name = 'xpark_apps';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


    public function admin(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id', 'id');
    }
}