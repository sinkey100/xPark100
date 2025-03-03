<?php

namespace app\command\User;

use app\admin\model\xpark\Data;
use app\admin\model\sls\Hour as SLSHour;
use app\admin\model\xpark\DataHour;
use app\admin\model\xpark\Domain;
use app\admin\model\xpark\Utc;
use app\command\Base;
use sdk\SLS;
use Exception;
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
        // 确认参数
        ini_set('error_reporting', E_ALL & ~E_DEPRECATED);
        $this->sls        = new SLS();
        $this->days       = 2;
        $this->start_time = strtotime(date('Y-m-d 00:00:00', strtotime("-" . ($this->days - 1) . " day"))) - 8 * 3600;
        $this->end_time   = strtotime('-1 hour', strtotime(date('Y-m-d H:00:00')));

        // 查找所有SLS域名
        $sls_domains   = Domain::where('channel_id', '>', 0)->select()->toArray();
        $this->domains = array_column($sls_domains, null, 'domain');

        // 重置数据
        Db::execute('truncate table ba_xpark_data_hour;');
        Db::execute('truncate table ba_sls_hour;');
        Utc::where('status', 1)->delete();

        // 开始任务
        $this->pull();
        $this->calc();
        $this->push();
    }

    protected function pull(): void
    {
        $this->log("\n\n======== SLS 拉取PV开始 ========", false);

        $this->log('SLS查询开始...');
        $result = $this->sls->getLogsWithPowerSql($this->start_time, $this->end_time, SLSHour::$SQL_PV_BY_HOUR);
        $this->log('SLS查询完成，共查询到' . count($result) . '条，开始保存小时数据');

        $insert_list = [];
        foreach ($result as $row) {
            $row    = $row->getContents();
            $domain = $this->domains[$row['attribute.page.host']] ?? false;
            if (!$domain) continue;

            $insert_list[] = [
                'app_id'       => $domain['app_id'],
                'domain_id'    => $domain['id'],
                'domain_name'  => $domain['domain'],
                'country_code' => $row['attribute.country_id'],
                'time_utc_8'   => $row['hour'],
                'time_utc_0'   => convert_to_utc($row['hour']),
                'page_views'   => (int)$row['page_views'],
                'status'       => 0
            ];
        }
        SLSHour::insertAll($insert_list);
        $this->log('数据保存完成...');

        $this->log("======== SLS 拉取PV完成 ========", false);

    }

    protected function calc(): void
    {
        $this->log("\n\n======== SLS 分配小时收入开始 ========", false);

        $this->log('开始分配，共' . count($this->domains) . '个域名');
        foreach ($this->domains as $domain) {
            for ($i = $this->days - 1; $i >= 0; $i--) {

                $this->log('获取小时数据开始：' . $domain['domain']);
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

                $this->log('获取收入数据');

                // 获取当前域名的收入数据
                $daily_revenue = Data::where('domain_id', $domain['id'])
                    ->where('status', 0)
                    ->whereDay('a_date', date("Y-m-d", strtotime("-$i days")))
                    ->select();
                if (count($daily_revenue) == 0) continue;
                $money = 0;

                $this->log('计算UTC数据并保存');

                // 均分出每小时的数据
                $insert_list = [];
                foreach ($daily_revenue as $daily) {
                    foreach ($hour_detail as $hour) {
                        // 比例
                        $rate          = $hour['rate'];
                        $money         = $money + $daily['ad_revenue'] * $rate;
                        $insert_list[] = [
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
                            'status'          => 0
                        ];
                    }
                }
                $this->log('准备存储' . count($insert_list) . '条数据');

                DataHour::insertAll($insert_list);

                $this->log("保存完成\n");
            }
        }
        $this->log('数据分配完成');
        $this->log("======== SLS 分配小时收入完成 ========", false);
    }

    protected function push(): void
    {
        $this->log("\n\n======== 合并UTC收入开始 ========", false);
        for ($i = $this->days - 1; $i >= 0; $i--) {
            $date = date("Y-m-d", strtotime("-$i days"));

            $list = DataHour::whereDay('time_utc_0', $date)
                ->field([
                    'DATE(time_utc_0) as a_date', 'app_id', 'domain_id', 'country_code', 'ad_placement_id', 'channel',
                    'channel_id', 'channel_full', 'country_level', 'country_name', 'sub_channel', 'channel_type',
                    'SUM(requests)as requests', 'SUM(fills) as fills', 'SUM(impressions) as impressions',
                    'SUM(clicks) as clicks', 'SUM(ad_revenue) as ad_revenue', 'SUM(gross_revenue) as gross_revenue',
                    '1 as status'
                ])
                ->group('DATE(time_utc_0), app_id, domain_id, country_code, ad_placement_id, channel, channel_id, channel_full, country_level, country_name, sub_channel, channel_type')
                ->select()->toArray();

            $chunks = array_chunk($list, 100000);
            foreach ($chunks as $chunk) {
                Utc::insertAll($chunk);
            }

//            // 插入无法计算的数据
            if (date("H") > 8) {
                $field          = 'domain_id';
                $all_domains    = array_column(Data::field($field)->where('status', 0)->whereDay('a_date', $date)->group($field)->select()->toArray(), $field);
                $utc_domains    = array_column(Utc::field($field)->where('status', 1)->whereDay('a_date', $date)->group($field)->select()->toArray(), $field);
                $ext_domain_ids = array_values(array_diff($all_domains, $utc_domains));
                if (count($ext_domain_ids) > 0) {
                    $ext_domain_ids = implode(',', $ext_domain_ids);
                    $sql            = "
INSERT INTO ba_xpark_utc (
    `app_id`, `channel_id`, `domain_id`, `channel`, `channel_full`, `a_date`, `country_code`, `country_level`, `country_name`, `sub_channel`,
    `ad_placement_id`,`requests`,`fills`,`impressions`,`clicks`,`ad_revenue`,`channel_type`,`gross_revenue`, `status`
)
SELECT `app_id`, `channel_id`, `domain_id`, `channel`, `channel_full`, `a_date`, `country_code`, `country_level`, `country_name`,
    `sub_channel`, `ad_placement_id`,`requests`,`fills`,`impressions`,`clicks`,`ad_revenue`,`channel_type`,`gross_revenue`, CAST(1 AS TINYINT) as status
FROM ba_xpark_data
WHERE domain_id in ( $ext_domain_ids ) and date(a_date) = '$date' and status = 0;";
                    try {
                        Db::execute($sql);
                    } catch (Exception $e) {
                        file_put_contents('error.log', date("Y-m-d H:i:s") . "\n", 8);
                        file_put_contents('error.log', $e->getMessage() . "\n", 8);
                        file_put_contents('error.log', $e->getTraceAsString() . "\n\n\n", 8);
                    }
                }
            }

            Utc::where('status', 0)->whereDay('a_date', $date)->delete();
            Utc::where('status', 1)->whereDay('a_date', $date)->update(['status' => 0]);
            unset($list);
            $this->log('第' . ($this->days - $i) . '/' . $this->days . '天 完成');
        }
        $this->log("======== SLS 合并UTC收入完成 ========", false);
    }

}
