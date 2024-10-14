<?php

namespace app\admin\model\xpark;

use app\admin\model\Admin;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * Data
 */
class Data extends Model
{

    // 表名
    protected $name = 'xpark_data';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


    protected $append = [
        'click_rate',
        'fill_rate',
        'unit_price',
        'ecpm',
    ];


    public function domain(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(\app\admin\model\xpark\Domain::class, 'domain_id', 'id');
    }

//    protected function getClickRateAttr($value, $data): string
//    {
//        // 点击率：  点击/展示
//        $rate = $data['clicks'] / (!empty($data['impressions']) ? $data['impressions'] : 1);
//        return number_format($rate * 100, 2) . '%';
//    }
//
//    protected function getFillRateAttr($value, $data): string
//    {
//        // 填充率：  展示/请求
//        $rate = $data['fills'] / (!empty($data['requests']) ? $data['requests'] : 1);
//        return number_format($rate * 100, 2) . '%';
//    }
//
//    protected function getUnitPriceAttr($value, $data): string
//    {
//        // 单价：  总收入/点击次数
//        return round($data['ad_revenue'] / (!empty($data['clicks']) ? $data['clicks'] : 1), 2);
//    }
//
//    protected function getEcpmAttr($value, $data): string
//    {
//        // ECPM = 收入/网页展示次数×1000
//        return round($data['ad_revenue'] / (!empty($data['impressions']) ? $data['impressions'] : 1) * 1000, 3);
//    }

    protected function getCountryCodeAttr($value, $data): string
    {
        return isset($data['country_name'])
            ? $value . '-' . $data['country_name']
            : $value;
    }
}