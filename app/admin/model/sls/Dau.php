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

    public static function SQL_DAU(string $date, string $domain): string
    {
        return <<<EOT
* | SELECT
    "attribute.uid",
    "attribute.country_id"
FROM log
WHERE "attribute.t" = 'pv' AND date_format(from_unixtime("__time__" - 28800), '%Y-%m-%d') = '$date' AND "attribute.page.host" = '$domain'
GROUP BY "attribute.uid","attribute.country_id"
EOT;
    }


}