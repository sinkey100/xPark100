<?php

namespace app\admin\model\cy;

use think\Model;

/**
 * Account
 */
class CYDomain extends Model
{
    // 表名
    protected $connection = 'chuanyou';
    protected $name = 'build_domain';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'created' => 'timestamp:Y-m-d H:i:s',
        'updated' => 'timestamp:Y-m-d H:i:s',
    ];

}