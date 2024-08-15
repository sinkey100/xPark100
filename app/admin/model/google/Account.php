<?php

namespace app\admin\model\google;

use think\Model;

/**
 * Account
 */
class Account extends Model
{
    // 表名
    protected $connection = 'chuanyou';
    protected $name = 'report_google_account';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'created' => 'timestamp:Y-m-d H:i:s',
        'updated' => 'timestamp:Y-m-d H:i:s',
    ];

    public function getAuthAttr(): array
    {
        return json_decode($this->getAttr('raw'),true);
    }

}