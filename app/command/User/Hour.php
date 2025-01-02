<?php

namespace app\command\User;

use app\admin\model\xpark\Data;
use app\admin\model\xpark\DataHour;
use app\admin\model\sls\Hour as SLSHour;
use app\admin\model\sls\User as SLSUser;
use app\admin\model\xpark\Domain;
use app\admin\model\xpark\Utc;
use app\command\Base;
use sdk\SLS;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class Hour extends Base
{

    protected array $domains;
    protected SLS   $sls;
    protected int   $start_time;
    protected int   $end_time;

    protected function configure(): void
    {
        $this->setName('DataHour');
    }

    protected function execute(Input $input, Output $output): void
    {
        ini_set('error_reporting', E_ALL & ~E_DEPRECATED);
        $this->sls = new SLS();

        $this->days       = 2;
        $this->start_time = strtotime(date('Y-m-d 00:00:00', strtotime("-" . ($this->days - 1) . " day")));
//        $this->end_time   = strtotime(date("Y-m-d H:10:00"));
        $this->end_time = strtotime('-1 hour', strtotime(date('Y-m-d H:00:00')));

        DataHour::where('status', 1)->delete();
        SLSHour::where('status', 1)->delete();
        Utc::where('status', 1)->delete();

        $this->pull();
        $this->calc();
        $this->push();
    }

    protected function pull(): void
    {
        // 查找所有SLS域名
        $sls_domains = Db::connect('chuanyou')->table('i_site_website')->alias('website')
            ->field(['website.*', 'domain.domain as domain_name'])
            ->join('i_build_domain domain', 'website.domain_id = domain.id')
            ->where('website.sls_switch', 1)
            ->select()
            ->toArray();
        $sls_domains = array_column($sls_domains, 'domain_name');
        foreach ($sls_domains as $sls_domain) {
            $domain_info = Domain::where('domain', $sls_domain)->find();
            if (!$domain_info) continue;
            $this->domains[] = $domain_info;
        }

        $this->sls_hour();
        $this->sls_new_user();
        $this->sls_total_time();
    }

    protected function sls_hour(): void
    {
        $this->log("\n\n======== SLS 开始拉取PV数据 ========", false);

        foreach ($this->domains as $domain) {

            $sls_sql = <<<EOT
* | SELECT 
    "attribute.country_id",
    date_format(from_unixtime(__time__), '%Y-%m-%d %H:00:00') AS hour,
    count(*) as page_views
FROM log 
WHERE 
    "attribute.t" = 'pv' and
    "attribute.page.host" = '{$domain->domain}' 
GROUP BY "attribute.country_id", 
    hour
ORDER BY 
    hour
EOT;

            $result = $this->sls->getLogsWithPowerSql($this->start_time, $this->end_time, $sls_sql);

            foreach ($result as $row) {
                $row        = $row->getContents();
                $time_utc_0 = convert_to_utc($row['hour']);

                $map  = [
                    'domain_id'    => $domain['id'],
                    'country_code' => $row['attribute.country_id'],
                    'time_utc_8'   => $row['hour'],
                ];
                $item = SLSHour::where($map)->find();
                if (!$item) {
                    $map['app_id']      = $domain['app_id'];
                    $map['domain_name'] = $domain['domain'];
                    $map['time_utc_0']  = $time_utc_0;
                    $item               = SLSHour::create($map);
                }
                $item->page_views = (int)$row['page_views'];
                $item->status     = 0;
                $item->save();

            }
        }

        $this->log("======== SLS 拉取PV数据完成 ========", false);

    }

    protected function sls_new_user(): void
    {
        $this->log("\n\n======== SLS 开始拉取活跃数据 ========", false);

        // 查找所有SLS域名

        foreach ($this->domains as $domain) {
            $sls_sql = <<<EOT
* | SELECT 
    "attribute.country_id",
    date_format(from_unixtime(__time__), '%Y-%m-%d %H:00:00') AS hour,
    ARRAY_AGG(DISTINCT "attribute.uid") AS user_list
FROM log 
WHERE 
    "attribute.t" = 'pv' AND
    "attribute.page.host" = '{$domain->domain}' 
GROUP BY 
    "attribute.country_id", 
    hour
ORDER BY
    hour
EOT;
            $result  = $this->sls->getLogsWithPowerSql($this->start_time, $this->end_time, $sls_sql);
            foreach ($result as $row) {
                $row        = $row->getContents();
                $time_utc_0 = convert_to_utc($row['hour']);
                $user_list  = json_decode($row['user_list'], true);
                // 新用户注册
                foreach ($user_list as $uid) {
                    $user = SLSUser::where('uid', $uid)->find();
                    if (!$user) {
                        SLSUser::create([
                            'app_id'       => $domain['app_id'],
                            'domain_id'    => $domain['id'],
                            'domain_name'  => $domain['domain'],
                            'uid'          => $uid,
                            'time_utc_8'   => $row['hour'],
                            'time_utc_0'   => $time_utc_0,
                            'country_code' => $row['attribute.country_id'],
                        ]);
                    }
                }
                // 记录
                $map  = [
                    'domain_id'    => $domain['id'],
                    'country_code' => $row['attribute.country_id'],
                    'time_utc_0'   => $time_utc_0,
                ];
                $item = SLSHour::where($map)->find();
                if (!$item) continue;
                $item->new_users    = SLSUser::where($map)->count();
                $item->active_users = count($user_list);
                $item->save();

            }
        }

        $this->log("\n\n======== SLS 拉取用户数据完成 ========", false);
    }

    protected function sls_total_time(): void
    {
        $this->log("\n\n======== SLS 开始拉取时长数据 ========", false);

        // 查找所有SLS域名

        foreach ($this->domains as $domain) {

            $sls_sql = <<<EOT
* | SELECT 
    "attribute.country_id", 
    hour, 
    AVG(total_time_per_user) AS total_time
FROM (
    SELECT 
        "attribute.country_id",
        "attribute.uid", 
            date_format(from_unixtime(__time__), '%Y-%m-%d %H:00:00') AS hour,
        SUM("attribute.totalTimeMs") AS total_time_per_user
    FROM log 
    WHERE 
        "attribute.t" = 'log' 
        AND "attribute.log.key" = 'page_duration' 
        AND "attribute.page.host" = '{$domain->domain}' 
    GROUP BY 
       "attribute.country_id",  "attribute.uid", hour
) 
GROUP BY 
    "attribute.country_id",hour
ORDER BY
    hour
EOT;

            $result = $this->sls->getLogsWithPowerSql($this->start_time, $this->end_time, $sls_sql);

            foreach ($result as $row) {
                $item       = $row->getContents();
                $time_utc_0 = convert_to_utc($item['hour']);

                SLSHour::where('domain_id', $domain['id'])
                    ->where('country_code', $item['attribute.country_id'])
                    ->where('time_utc_0', $time_utc_0)
                    ->update([
                        'total_time' => $item['total_time']
                    ]);
            }
        }

        $this->log("\n\n======== SLS 拉取时长数据完成 ========", false);
    }

    protected function calc(): void
    {

        foreach ($this->domains as $domain) {
            for ($i = $this->days - 1; $i >= 0; $i--) {

                // 获取当前域名一天的流量分配
                $hour_detail = SLSHour::where('domain_id', $domain['id'])
                    ->where('status', 0)
                    ->whereDay('time_utc_8', date("Y-m-d", strtotime("-$i days")))
                    ->order('time_utc_8', 'asc')
                    ->select()->toArray();
                if (count($hour_detail) == 0) continue;

                $total_page_views = array_sum(array_column($hour_detail, 'page_views'));
                $total_rate       = 0;
                foreach ($hour_detail as $index => &$item) {
                    $item['rate'] = round($item['page_views'] / $total_page_views, 6);
                    $total_rate   += $item['rate'];
                }
                unset($item);
                if ($total_rate != 1) $hour_detail[count($hour_detail) - 1]['rate'] += 1 - $total_rate;

                // 获取当前域名的收入数据
                $daily_revenue = Data::where('domain_id', $domain['id'])
                    ->where('status', 0)
                    ->whereDay('a_date', date("Y-m-d", strtotime("-$i days")))
                    ->select();
                if (count($daily_revenue) == 0) continue;
                $money = 0;

                // 均分出每小时的数据
                foreach ($daily_revenue as $daily) {
                    $insertData = [];
                    foreach ($hour_detail as $hour) {

                        // 比例
                        $rate  = $hour['rate'];
                        $money = $money + $daily['ad_revenue'] * $rate;

                        $insertData[] = [
                            'app_id'          => $daily['app_id'],
                            'domain_id'       => $daily['domain_id'],
                            'channel'         => $daily['channel'],
                            'channel_id'      => $daily['channel_id'],
                            'channel_full'    => $daily['channel_full'],
                            'country_level'   => $daily['country_level'],
                            'country_code'    => $daily->getData('country_code'),
                            'country_name'    => $daily['country_name'],
                            'sub_channel'     => $daily['sub_channel'],
                            'ad_placement_id' => $daily['ad_placement_id'],
                            'requests'        => $daily['requests'] * $rate,
                            'fills'           => $daily['fills'] * $rate,
                            'impressions'     => $daily['impressions'] * $rate,
                            'clicks'          => $daily['clicks'] * $rate,
                            'ad_revenue'      => $daily['ad_revenue'] * $rate,
                            'gross_revenue'   => $daily['gross_revenue'] * $rate,
                            'channel_type'    => $daily['channel_type'],
                            'time_utc_0'      => $hour['time_utc_0'],
                            'time_utc_8'      => $hour['time_utc_8'],
                            'status'          => 1
                        ];
                    }
                    DataHour::insertAll($insertData);
                }
            }
        }
        // 清除数据
        DataHour::where('status', 0)->whereTime('time_utc_8', '>=', date("Y-m-d", $this->start_time))->delete();
        DataHour::where('status', 1)->update(['status' => 0]);

        $this->log('历史数据已删除');

    }

    protected function push(): void
    {
        for ($i = $this->days - 1; $i >= 0; $i--) {
            $list = DataHour::whereDay('time_utc_0', date("Y-m-d", strtotime("-$i days")))
                ->field([
                    "DATE(time_utc_0) AS a_date",
                    "app_id", "domain_id", "country_code", "ad_placement_id",
                    "channel", "channel_id", "channel_full", "country_level", "country_name",
                    "sub_channel", "channel_type",
                    "SUM(requests) AS requests",
                    "SUM(fills) AS fills",
                    "SUM(impressions) AS impressions",
                    "SUM(clicks) AS clicks",
                    "SUM(ad_revenue) AS ad_revenue",
                    "SUM(gross_revenue) AS gross_revenue",
                    "1 AS status"
                ])
                ->group("a_date, domain_id, country_code, ad_placement_id")
                ->select()->toArray();

            Utc::insertAll($list);
            Utc::where('status', 0)->whereDay('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
            Utc::where('status', 1)->whereDay('a_date', date("Y-m-d", strtotime("-$i days")))->update(['status' => 0]);
            unset($list);
        }

    }

}
