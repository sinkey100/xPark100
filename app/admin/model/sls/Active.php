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

    public static function SQL_DAILY_PV(string $date): string
    {
        return <<<EOT
* | SELECT  
"attribute.country_id", "attribute.page.host", count(*) as page_views
FROM log 
WHERE "attribute.t" = 'pv' AND date_format(from_unixtime("__time__" - 28800), '%Y-%m-%d') = '$date'
GROUP BY "attribute.country_id", "attribute.page.host"
EOT;
    }

    public static function SQL_DAILY_TOTAL_TIME(string $date): string
    {
       return <<<EOT
* | SELECT "attribute.country_id", "attribute.page.host", AVG(total_time_per_user) AS total_time 
FROM (
    SELECT "attribute.country_id", "attribute.uid", "attribute.page.host", SUM("attribute.totalTimeMs") AS total_time_per_user
    FROM log 
    WHERE "attribute.t" = 'log' AND "attribute.log.key" = 'page_duration' AND  date_format(from_unixtime("__time__" - 28800), '%Y-%m-%d') = '$date'
    GROUP BY "attribute.country_id",  "attribute.uid", "attribute.page.host"
)
GROUP BY "attribute.country_id","attribute.page.host"
EOT;
    }

    public static function SQL_CALC_NEW_AND_ACTIVE_USERS($date): string
    {
        return "SELECT 
    date, domain_name, country_code,
    COUNT(DISTINCT uid) AS active_user_count,
    COUNT(DISTINCT CASE WHEN first_date = date THEN uid END) AS new_user_count
FROM (
    SELECT
        t.*,
        MIN(date) OVER (PARTITION BY uid) AS first_date
    FROM ba_sls_dau t
) t
where date = '$date'
GROUP BY date, domain_name, country_code;";
    }


}