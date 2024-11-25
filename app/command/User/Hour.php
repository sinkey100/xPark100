<?php

namespace app\command\User;


use app\admin\model\google\Account;
use app\admin\model\xpark\ActivityHour;
use app\admin\model\xpark\Data;
use app\admin\model\xpark\DataHour;
use app\admin\model\xpark\Domain;
use app\command\Base;
use Google\Service\Analytics;
use Google\Service\GoogleAnalyticsAdmin;
use sdk\Google as GoogleSDK;
use think\console\Input;
use think\console\Output;
use Exception;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\RunReportRequest;

class Hour extends Base
{

    protected array $domains;

    protected function configure()
    {
        $this->setName('DataHour');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->days    = 3;
        $this->domains = Domain::where('ga', '<>', '')->select()->toArray();

        $this->pull();
        $this->calc();

    }

    protected function pull(): void
    {
        $this->log("\n\n======== GA 开始拉取数据 ========", false);
        $this->log("任务开始，拉取 {$this->days} 天");

        $ga_account_ids = [
            ['google_account_id' => 4, 'ga_account_id' => 315444308],
            ['google_account_id' => 8, 'ga_account_id' => 322638147],
            ['google_account_id' => 8, 'ga_account_id' => 336921805],
        ];

        $this->log('开始拉取 GA 数据');

        foreach ($ga_account_ids as $ga_account_info) {
            // 初始化
            $account = Account::where('id', $ga_account_info['google_account_id'])->find();
            if (!$account) throw new Exception('Google 账号标记不存在');
            $client = (new GoogleSDK())->init($account);
            $client->setAccessToken($account->auth);
            $analytics      = new AnalyticsData($client);
            $analyticsAdmin = new GoogleAnalyticsAdmin($client);

            // 查询衡量ID和数字ID对应表
            $pageToken       = null;
            $properties_list = [];
            do {
                $response = $analyticsAdmin->properties->listProperties([
                    'pageSize'  => 50,
                    'filter'    => 'parent:accounts/' . $ga_account_info['ga_account_id'],
                    'pageToken' => $pageToken
                ]);
                foreach ($response->getProperties() as $property) {
                    $properties_list[$property['displayName']] = $property['name'];
                }
                $pageToken = $response->getNextPageToken();
            } while ($pageToken);

            // GA账户列表
            $days = $this->days - 1;
            foreach ($this->domains as $domain) {
                if (!isset($properties_list[$domain['domain']])) continue;
                $response = $analytics->properties->runReport($properties_list[$domain['domain']], new RunReportRequest([
                    'dimensions' => [
                        ['name' => 'date'],
                        ['name' => 'hour'],
                    ],
                    'metrics'    => [
                        ['name' => 'screenPageViews'],
                    ],
                    'dateRanges' => [
                        [
                            'startDate' => date("Y-m-d", strtotime("-{$days} days")),
                            'endDate'   => date("Y-m-d")
                        ],
                    ]
                ]));

                $this->output->writeln($domain['domain']);

                foreach ($response['rows'] as $row) {
                    $date       = date("Y-m-d", strtotime($row['dimensionValues'][0]['value']));
                    $hour       = $row['dimensionValues'][1]['value'];
                    $time_utc_8 = "$date $hour:00:00";
                    $time_utc_0 = convert_to_utc($time_utc_8);

                    $insert = [
                        'app_id'     => $domain['app_id'],
                        'domain_id'  => $domain['id'],
                        'g_id'       => $domain['ga'],
                        'time_utc_8' => $time_utc_8,
                        'time_utc_0' => $time_utc_0,
                        'page_views' => $row['metricValues'][0]['value'],
                        'status'     => 1
                    ];
                    ActivityHour::create($insert);
                }
            }
        }

        // 清除数据
        for ($i = 0; $i < $this->days; $i++) {
            ActivityHour::where('status', 0)->whereDay('time_utc_8', date("Y-m-d", strtotime("-$i days")))->delete();
            ActivityHour::where('status', 1)->whereDay('time_utc_8', date("Y-m-d", strtotime("-$i days")))->update(['status' => 0]);
        }

        $this->log('历史数据已删除');

        $this->log('======== GA 拉取数据完成 ========', false);
    }

    protected function calc(): void
    {
        foreach ($this->domains as $domain) {
            // if ($domain['id'] != 38) continue;
            for ($i = $this->days; $i >= 0; $i--) {
                // if(date("Y-m-d", strtotime("-$i days")) != '2024-11-19') continue;

                // 获取当前域名一天的流量分配
                $hour_detail = ActivityHour::where('domain_id', $domain['id'])
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
        for ($i = 0; $i < $this->days; $i++) {
            DataHour::where('status', 0)->whereDay('time_utc_8', date("Y-m-d", strtotime("-$i days")))->delete();
            DataHour::where('status', 1)->whereDay('time_utc_8', date("Y-m-d", strtotime("-$i days")))->update(['status' => 0]);
        }

        $this->log('历史数据已删除');

    }

}
