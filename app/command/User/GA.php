<?php

namespace app\command\User;

use app\admin\model\cy\CYDomain;
use app\admin\model\cy\CYDomainCp;
use app\admin\model\google\Account;
use app\admin\model\xpark\Activity;
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

class GA extends Base
{

    protected function configure()
    {
        $this->setName('GA');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->days = 7;
        $this->log("\n\n======== GA 开始拉取数据 ========", false);
        $this->log("任务开始，拉取 {$this->days} 天");

        $ga_account_ids = [
            4 => '315444308',
            8 => '322638147'
        ];
        // 更新 G-ID
        $domains = Domain::where('ga', '')->select();
        foreach ($domains as $domain) {
            $cy_domain_cp = CYDomainCp::where('domain', $domain->domain)->where('cp_flag', 'chuanyou')->find();
            if (!$cy_domain_cp) continue;
            $domain->ga = $cy_domain_cp->gtag_id;
            $domain->save();
        }

        // 清除数据
        for ($i = 0; $i < $this->days; $i++) {
            Activity::where('channel', 'GA')->where('date', date("Y-m-d", strtotime("-$i days")))->delete();
        }

        $this->log('历史数据已删除');
        $this->log('开始拉取 GA 数据');

        foreach ($ga_account_ids as $account_id => $ga_account_id) {
            // 初始化
            $account = Account::where('id', $account_id)->find();
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
                    'filter'    => 'parent:accounts/' . $ga_account_id,
                    'pageToken' => $pageToken
                ]);
                foreach ($response->getProperties() as $property) {
                    $properties_list[$property['displayName']] = $property['name'];
                }
                $pageToken = $response->getNextPageToken();
            } while ($pageToken);

            // GA账户列表
            $domains = Domain::where('ga', '<>', '')->select();
            $days    = $this->days - 1;
            foreach ($domains as $domain) {
                if (!isset($properties_list[$domain->domain])) continue;
                $response = $analytics->properties->runReport($properties_list[$domain->domain], new RunReportRequest([
                    'dimensions' => [
                        ['name' => 'date'],
                        ['name' => 'countryId'],
                    ],
                    'metrics'    => [
                        ['name' => 'newUsers'],
                        ['name' => 'activeUsers'],
                        ['name' => 'screenPageViews'],
                    ],
                    'dateRanges' => [
                        [
                            'startDate' => date("Y-m-d", strtotime("-{$days} days")),
                            'endDate'   => date("Y-m-d")
                        ],
                    ]
                ]));

                $output->writeln($domain['domain']);
                foreach ($response['rows'] as $row) {
                    $insert = [
                        'app_id'       => $domain->app_id,
                        'domain_id'    => $domain->id,
                        'channel'      => 'GA',
                        'date'         => date("Y-m-d", strtotime($row['dimensionValues'][0]['value'])),
                        'country_code' => strlen($row['dimensionValues'][1]['value']) > 3 ? '' : $row['dimensionValues'][1]['value'],
                        'new_users'    => $row['metricValues'][0]['value'],
                        'active_users' => $row['metricValues'][1]['value'],
                        'page_views'   => $row['metricValues'][2]['value'],
                    ];
                    Activity::create($insert);
                }
            }
        }

        $this->log('======== GA 拉取数据完成 ========', false);

    }

}
