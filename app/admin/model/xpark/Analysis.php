<?php

namespace app\admin\model\xpark;

use app\admin\model\Admin;
use think\Model;
use think\model\relation\BelongsTo;

class Analysis extends Model
{

    // 表名
    protected $name = 'xpark_data';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


    protected $append = [];

}