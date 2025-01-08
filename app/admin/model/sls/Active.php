<?php

namespace app\admin\model\sls;

use app\admin\model\Admin;
use think\Model;

class Active extends Model
{

    // 表名
    protected $name = 'sls_active';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


    protected $append = [];

    public static string $SQL_DAILY_PV = <<<EOT
* | 
SELECT  "attribute.country_id", "attribute.page.host", count(*) as page_views
FROM log 
WHERE "attribute.t" = 'pv' 
GROUP BY "attribute.country_id", "attribute.page.host"
EOT;

    public static string $SQL_DAILY_ACTIVE_USER = <<<EOT
* | 
SELECT "attribute.country_id", "attribute.page.host", ARRAY_AGG(DISTINCT "attribute.uid") AS user_list 
FROM log 
WHERE "attribute.t" = 'pv' 
GROUP BY "attribute.country_id", "attribute.page.host"
EOT;

    public static string $SQL_DAILY_TOTAL_TIME = <<<EOT
* | 
SELECT "attribute.country_id", "attribute.page.host", AVG(total_time_per_user) AS total_time 
FROM (
    SELECT "attribute.country_id", "attribute.uid", "attribute.page.host", SUM("attribute.totalTimeMs") AS total_time_per_user
    FROM log 
    WHERE "attribute.t" = 'log' AND "attribute.log.key" = 'page_duration' 
    GROUP BY "attribute.country_id",  "attribute.uid", "attribute.page.host"
)
GROUP BY "attribute.country_id","attribute.page.host"
EOT;

    public static function SQL_MERGE_NEW_USERS($domain_name, $appid, $domain_id, $country_code, $date): string
    {
        return <<<EOT
INSERT INTO ba_sls_user (domain_name, app_id, domain_id, uid, country_code, date)
SELECT 
    '$domain_name' AS domain_name, 
    $appid AS app_id, 
    $domain_id AS domain_id, 
    uid, 
    '$country_code' AS country_code, 
    '$date' AS date
FROM ba_sls_user_staging
WHERE uid NOT IN (
    SELECT uid FROM ba_sls_user
);
EOT;
    }


}