<?php

namespace app\admin\model\sls;

use app\admin\model\Admin;
use think\Model;

class Hour extends Model
{

    // 表名
    protected $name = 'sls_hour';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    protected $append = [];

    public static string $SQL_PV_BY_HOUR = <<<EOT
* | 
SELECT "attribute.country_id", "attribute.page.host", date_format(from_unixtime(__time__), '%Y-%m-%d %H:00:00') AS hour, count(*) as page_views
FROM log 
WHERE "attribute.t" = 'pv' 
GROUP BY "attribute.country_id", "attribute.page.host" , hour
ORDER BY hour
EOT;

    public static function SQL_HOUR_TO_UTC(string $date): string
    {
        return <<<EOT
SELECT 
    toDate(time_utc_0) AS a_date, app_id, domain_id, country_code,  ad_placement_id,  channel,  channel_id,  channel_full,
    country_level,  country_name,  sub_channel,channel_type, SUM(requests) AS requests, SUM(fills) AS fills, SUM(impressions) AS impressions,
    SUM(clicks) AS clicks, SUM(ad_revenue) AS ad_revenue, SUM(gross_revenue) AS gross_revenue, 1 AS status
FROM ba_xpark_data_hour
WHERE time_utc_0 BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY a_date, domain_id, country_code, ad_placement_id,  app_id,  channel,  channel_id,  channel_full,  country_level, country_name,  sub_channel,  channel_type;
EOT;
    }

}