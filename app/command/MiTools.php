<?php

namespace app\command;

use app\admin\model\google\Account;
use Google\Service\Gmail;
use Google\Service\Oauth2;
use think\console\Input;
use think\console\Output;
use Exception;

use GuzzleHttp\Client as HttpClient;
use Google\Client as GoogleClient;
use Google\Service\AdSense;
use Google_Service_Adsense;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\facade\Env;
use sdk\Google as GoogleSDK;
use app\admin\model\mi\instant\Report as InstantReport;


class MiTools extends Base
{


    protected function configure()
    {
        // 指令配置
        $this->setName('MiTools');
    }

    /**
     * @throws DataNotFoundException
     * @throws \Google\Service\Exception
     * @throws \Google\Exception
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
     */
    protected function execute(Input $input, Output $output): void
    {
        $domainList = [
            4 => [
                'cooltool.vip'
            ]
        ];

        // 清除老数据
        for ($i = 0; $i < 3; $i++) {
            InstantReport::where('DATE', date("Y-m-d", strtotime("-$i days")))->delete();
            // 重置自增id
            $maxId = InstantReport::max('id');
            $maxId++;
            Db::execute("ALTER TABLE `ba_mi_instant_report` AUTO_INCREMENT={$maxId};");
        }

        $accounts = Account::where('id', 'in', array_keys($domainList))->select();
        foreach ($accounts as $account) {
            if (count($domainList[$account->id]) == 0) continue;
            // 初始化
            $client = (new GoogleSDK())->init($account);
            $client->setAccessToken($account->auth);

            // 获取AFC
            $adsense       = new AdSense($client);
            $adsenseClient = $adsense->accounts_adclients->listAccountsAdclients($account->adsense_name);
            $adsenseClient = array_filter($adsenseClient['adClients'], function ($item) {
                return $item['productCode'] == 'AFC';
            });
            $adsenseClient = array_values($adsenseClient);
            if (empty($adsenseClient)) throw new Exception('没有找到 AFC Client');
            $adsenseClient = $adsenseClient[0];
            $afcId         = substr($adsenseClient['name'], strrpos($adsenseClient['name'], '/') + 1);
            if (empty($afcId)) throw new Exception('AFC Client 错误');

            // 拉取数据
            $filters   = array_map(function ($domain) {
                return "PAGE_URL=@$domain";
            }, $domainList[$account->id]);
            $filters[] = 'AD_CLIENT_ID==' . $afcId;

            $startTime = strtotime("-2 days");
            $result    = $adsense->accounts_reports->generate($account->adsense_name, [
                'startDate.year'  => date("Y", $startTime),
                'startDate.month' => date("m", $startTime),
                'startDate.day'   => date("d", $startTime),
                'endDate.year'    => date("Y"),
                'endDate.month'   => date("m"),
                'endDate.day'     => date("d"),
                'metrics'         => [
                    'PAGE_VIEWS', 'AD_REQUESTS', 'AD_REQUESTS_COVERAGE', 'CLICKS', 'AD_REQUESTS_CTR', 'IMPRESSIONS',
                    'AD_REQUESTS_RPM', 'IMPRESSIONS_CTR', 'COST_PER_CLICK', 'IMPRESSIONS_RPM', 'ESTIMATED_EARNINGS',
                    'PAGE_VIEWS_CTR', 'PAGE_VIEWS_RPM', 'ACTIVE_VIEW_VIEWABILITY'
                ],
                'dimensions'      => [
                    'DATE', 'PAGE_URL', 'COUNTRY_CODE'
                ],
                'orderBy'         => '+DATE',
                'filters'         => $filters
            ]);

            if (!$result['rows'] || count($result['rows']) == 0) continue;

            $headers = array_column($result['headers'], 'name');
            foreach ($result['rows'] as $row) {
                $insert = [
                    'report_google_id' => $account->id
                ];

                foreach ($headers as $k => $header) {
                    $insert[$header] = $row['cells'][$k]['value'];
                }
                $insert['DOMAIN_NAME'] = parse_url($insert['PAGE_URL'])['host'] ?? '';
                $insert['google_account_id'] = $account->id;

                InstantReport::create($insert);
            }
        }
    }
}
