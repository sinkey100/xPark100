<?php

namespace app\admin\model\xpark;

use app\admin\model\Admin;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * Domain
 */
class Domain extends Model
{
    // 表名
    protected $name = 'xpark_domain';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'created' => 'timestamp:Y-m-d H:i:s',
        'updated' => 'timestamp:Y-m-d H:i:s',
    ];

//    public function admin(): BelongsTo
//    {
//        return $this->belongsTo(Admin::class, 'admin_id');
//    }
//
//    public function app(): BelongsTo
//    {
//        return $this->belongsTo(Apps::class, 'app_id');
//    }

}