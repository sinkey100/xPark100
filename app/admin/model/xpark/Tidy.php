<?php

namespace app\admin\model\xpark;

use think\Model;

/**
 * Tidy
 */
class Tidy extends Model
{
    // 表名
    protected $name = 'xpark_tidy';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 追加属性
    protected $append = [
        'exclude',
    ];


    public function getExcludeAttr($value, $row): array
    {
        return [
            'app_name' => \app\admin\model\xpark\Apps::whereIn('id', $row['exclude_id'])->column('app_name'),
        ];
    }

    public function setMonthAttr($value): string
    {
        return strlen($value) == 7 ? $value . '-01' : $value;
    }

    public function getExcludeIdAttr($value): array
    {
        if ($value === '' || $value === null) return [];
        if (!is_array($value)) {
            return explode(',', $value);
        }
        return $value;
    }

    public function setExcludeIdAttr($value): string
    {
        return is_array($value) ? implode(',', $value) : $value;
    }

    public function channel(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\xpark\Channel::class, 'channel_id', 'id');
    }
}