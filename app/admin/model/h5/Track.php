<?php

namespace app\admin\model\h5;

use think\Model;

/**
 * Track
 */
class Track extends Model
{
    // 表名
    protected $name = 'sls_track';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    public static string $SQL_TRACK = <<<EOT
* | SELECT 
    date_format(from_unixtime("__time__" - 28800), '%Y-%m-%d') as date,
    "attribute.page.host" as domain,
    "attribute.country_id" as country_id,
    "attribute.ae_event_type" as ae_event_type,
    SUM(CASE WHEN "attribute.ae_report_pixel" = 'True' THEN 1 ELSE 0 END) AS valid_events,
    SUM(CASE WHEN "attribute.ae_report_pixel" = 'False' THEN 1 ELSE 0 END) AS invalid_events,
    SUM(CASE WHEN "attribute.ae_ad_type" = 'anchored' THEN 1 ELSE 0 END) AS anchored_count,
    SUM(CASE WHEN "attribute.ae_ad_type" = 'banner' THEN 1 ELSE 0 END) AS banner_count,
    SUM(CASE WHEN "attribute.ae_ad_type" = 'fullscreen' THEN 1 ELSE 0 END) AS fullscreen_count
FROM 
    log
WHERE
    "attribute.log.key" = 'ad_event' and "attribute.ae_ad_click_num" is not null
GROUP BY 
    date, 
    domain,
    country_id,
    ae_event_type 
ORDER BY 
    date ASC, domain
EOT;

}