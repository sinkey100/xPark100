<?php

namespace app\admin\model\xpark;

use app\admin\model\Admin;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * Domain
 */
class DomainRate extends Model
{
    // 表名
    protected $name = 'xpark_domain_rate';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


}