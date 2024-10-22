<?php

namespace app\admin\model\cy;

use think\Model;

/**
 * Account
 */
class CYDomainCp extends Model
{
    // 表名
    protected $connection = 'chuanyou';
    protected $name = 'build_domain_cp';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

}