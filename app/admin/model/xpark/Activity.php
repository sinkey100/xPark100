<?php

namespace app\admin\model\xpark;

use think\Model;

/**
 * Apps
 */
class Activity extends Model
{
    // 表名
    protected $name = 'xpark_activity';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

}