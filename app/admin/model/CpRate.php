<?php

namespace app\admin\model;

use think\Model;

/**
 * Log
 */
class CpRate extends Model
{
    // 表名
    protected $name = 'xpark_cp_rate';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;
    protected $updateTime         = false;

    protected $type = [
        'table'  => 'array',
        'fields' => 'array',
    ];

}