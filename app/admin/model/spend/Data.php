<?php

namespace app\admin\model\spend;

use think\Model;

/**
 * Data
 */
class Data extends Model
{
    // 表名
    protected $name = 'spend_data';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

}