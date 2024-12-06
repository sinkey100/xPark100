<?php

namespace app\admin\model\xpark;

use think\Model;

/**
 * Hold
 */
class Hold extends Model
{
    // 表名
    protected $name = 'xpark_hold';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public function channel(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\xpark\Channel::class, 'channel_id', 'id');
    }

    public function setMonthAttr($value): string
    {
        return strlen($value) == 7 ? $value . '-01' : $value;
    }

}