<?php

namespace app\admin\model\mi\instant;

use think\Model;

/**
 * Report
 */
class Report extends Model
{
    // 表名
    protected $name = 'mi_instant_report';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;
    protected $updateTime = false;
    protected $append = ['ctr', 'coverage'];

    protected function getCtrAttr($value, $data): string
    {
        if(!isset($data['IMPRESSIONS_CTR'])) return '-';
        return round($data['IMPRESSIONS_CTR'] * 100, 2) . '%';
    }

    protected function getCoverageAttr($value, $data): string
    {
        if(!isset($data['AD_REQUESTS_COVERAGE'])) return '-';
        return round($data['AD_REQUESTS_COVERAGE'] * 100, 2) . '%';
    }

}