<?php

namespace app\admin\model\sls;

use app\admin\model\Admin;
use think\Model;

class User extends Model
{

    // 表名
    protected $name = 'sls_user';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


    protected $append = [];

}