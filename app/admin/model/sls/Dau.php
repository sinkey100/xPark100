<?php

namespace app\admin\model\sls;

use app\admin\model\Admin;
use think\Model;

class Dau extends Model
{

    // 表名
    protected $name = 'sls_dau';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    public static string $SQL_DAU = <<<EOT
* | 
SELECT
    date_format(from_unixtime(__time__), '%Y-%m-%d') as date,
    "attribute.page.host",
    "attribute.uid",
    "attribute.country_id"
FROM log
WHERE "attribute.t" = 'pv' 
GROUP BY date, "attribute.page.host", "attribute.uid","attribute.country_id"
EOT;



}