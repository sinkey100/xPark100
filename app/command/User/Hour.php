<?php

namespace app\command\User;

use app\admin\model\xpark\Data;
use app\admin\model\sls\Hour as SLSHour;
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
        // 确认参数
        ini_set('error_reporting', E_ALL & ~E_DEPRECATED);
        $this->sls        = new SLS();
        $this->clickhouse = $this->init_clickhouse();
        $this->days       = 2;
        $this->start_time = strtotime(date('Y-m-d 00:00:00', strtotime("-" . ($this->days - 1) . " day"))) - 8 * 3600;
        $this->end_time   = strtotime('-1 hour', strtotime(date('Y-m-d H:00:00')));

        // 查找所有SLS域名
        $sls_domains   = Domain::where('channel_id', '>', 0)->select()->toArray();
        $this->domains = array_column($sls_domains, null, 'domain');

        // 重置数据
        $this->clickhouse->write('truncate table ba_xpark_data_hour;');
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
                foreach ($daily_revenue as $daily) {
                    $insert_list = [];
                    foreach ($hour_detail as $hour) {
                        // 比例
                        $rate          = $hour['rate'];
                        $money         = $money + $daily['ad_revenue'] * $rate;
                        $insert_list[] = [
                            $daily['app_id'],
                            $daily['domain_id'],
                            $daily['channel'],
                            $daily['channel_id'],
                            $daily['channel_full'],
                            $daily['country_level'],
                            $daily->getData('country_code'),
                            $daily['country_name'],
                            $daily['sub_channel'],
                            $daily['ad_placement_id'],
                            $daily['requests'] * $rate,
                            $daily['fills'] * $rate,
                            $daily['impressions'] * $rate,
                            $daily['clicks'] * $rate,
                            $daily['ad_revenue'] * $rate,
                            $daily['gross_revenue'] * $rate,
                            $daily['channel_type'],
                            $hour['time_utc_0'],
                            $hour['time_utc_8'],
                            0
                        ];
                    }
                    $this->clickhouse->insert('ba_xpark_data_hour', $insert_list,
                        [
                            'app_id', 'domain_id', 'channel', 'channel_id', 'channel_full', 'country_level', 'country_code',
                            'country_name', 'sub_channel', 'ad_placement_id', 'requests', 'fills', 'impressions',
                            'clicks', 'ad_revenue', 'gross_revenue', 'channel_type', 'time_utc_0', 'time_utc_8', 'status'
                        ]
                    );
                }

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
            $date   = date("Y-m-d", strtotime("-$i days"));
            $list   = $this->clickhouse->select(SLSHour::SQL_HOUR_TO_UTC($date))->rows();
            $chunks = array_chunk($list, 1000);
            foreach ($chunks as $chunk) {
                Utc::insertAll($chunk);
            }
            Utc::where('status', 0)->whereDay('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
            Utc::where('status', 1)->whereDay('a_date', date("Y-m-d", strtotime("-$i days")))->update(['status' => 0]);
            unset($list);
            $this->log('第' . ($this->days - $i) . '/' . $this->days . '天 完成');
        }
        $this->log("======== SLS 合并UTC收入完成 ========", false);
    }

}
