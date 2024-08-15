<?php

namespace app\admin\model\mi\instant;

use think\Model;

/**
 * Report
 */
class ReportUrl extends Model
{
    // 表名
    protected $name = 'mi_instant_report_url';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $updateTime = false;
    protected $append = ['ctr', 'coverage', 'ecpm', 'cpc', 'revenue'];

    protected function getCtrAttr($value, $data): string
    {
//        if(!isset($data['IMPRESSIONS_CTR'])) return '-';
//        return round($data['IMPRESSIONS_CTR'] * 100, 2) . '%';
        $rate = $data['CLICKS'] / (!empty($data['IMPRESSIONS']) ? $data['IMPRESSIONS'] : 1);
        return number_format($rate * 100, 2) . '%';
    }

    protected function getCoverageAttr($value, $data): string
    {
//        if(!isset($data['AD_REQUESTS_COVERAGE'])) return '-';
//        return round($data['AD_REQUESTS_COVERAGE'] * 100, 2) . '%';
        // 填充率：  展示/请求
        $rate = $data['FILLS'] / (!empty($data['AD_REQUESTS']) ? $data['AD_REQUESTS'] : 1);
        return number_format($rate * 100, 2) . '%';
    }

    protected function getEcpmAttr($value, $data): string
    {
        return round($data['ESTIMATED_EARNINGS'] / (!empty($data['IMPRESSIONS']) ? $data['IMPRESSIONS'] : 1) * 1000, 3);
//        return round($data['IMPRESSIONS_RPM'] ?? '0' , 2);
    }

    protected function getCpcAttr($value, $data): string
    {
//        return round($data['COST_PER_CLICK'] ?? '0' , 2);
        return round($data['ESTIMATED_EARNINGS'] / (!empty($data['CLICKS']) ? $data['CLICKS'] : 1), 2);
    }

    protected function getRevenueAttr($value, $data): string
    {
        return round($data['ESTIMATED_EARNINGS'] ?? '0' , 2);
//        return round($data['ESTIMATED_EARNINGS'] / (!empty($data['CLICKS']) ? $data['CLICKS'] : 1), 2);
    }

}