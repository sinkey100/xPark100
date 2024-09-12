<?php

namespace app\command;

use app\admin\model\google\Account;
use app\admin\model\mi\instant\ReportUnit;
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
use app\admin\model\mi\instant\ReportUnit as InstantReportUnit;


class MiTools extends Base
{

    protected array $oldAdName = [
        'cooltool.vip_0723_tools_1',
        'cooltool.vip_0723_tools_2',
        'cooltool.vip_0723_tools_3',
        'cooltool.vip_0723_tools_4',
        'cooltool.vip_0723_tools_5',
    ];

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
        $days       = 3;
        $domainList = [
            4 => [
                'cooltool.vip',
            ],
            8 => [
                'minitool.app'
            ]
        ];

        // 清除老数据
        for ($i = 0; $i < $days; $i++) {
            InstantReport::where('DATE', date("Y-m-d", strtotime("-$i days")))->delete();
            InstantReportUnit::where('DATE', date("Y-m-d", strtotime("-$i days")))->delete();
            // 重置自增id
            $maxId = InstantReport::max('id');
            $maxId++;
            Db::execute("ALTER TABLE `ba_mi_instant_report` AUTO_INCREMENT={$maxId};");
            $maxId = InstantReportUnit::max('id');
            $maxId++;
            Db::execute("ALTER TABLE `ba_mi_instant_report_unit` AUTO_INCREMENT={$maxId};");
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

            // 拉取数据参数
            $startTime = strtotime("-" . ($days - 1) . " days");
            $params    = [
                'startDate.year'  => date("Y", $startTime),
                'startDate.month' => date("m", $startTime),
                'startDate.day'   => date("d", $startTime),
                'endDate.year'    => date("Y"),
                'endDate.month'   => date("m"),
                'endDate.day'     => date("d"),
//                'startDate.year'  => '2024',
//                'startDate.month' => '07',
//                'startDate.day'   => '01',
//                'endDate.year'    => '2024',
//                'endDate.month'   => '09',
//                'endDate.day'     => '13',
                'metrics'         => [
                    'PAGE_VIEWS', 'AD_REQUESTS', 'AD_REQUESTS_COVERAGE', 'CLICKS', 'AD_REQUESTS_CTR', 'IMPRESSIONS',
                    'AD_REQUESTS_RPM', 'IMPRESSIONS_CTR', 'COST_PER_CLICK', 'IMPRESSIONS_RPM', 'ESTIMATED_EARNINGS',
                    'PAGE_VIEWS_CTR', 'PAGE_VIEWS_RPM', 'ACTIVE_VIEW_VIEWABILITY', 'TOTAL_EARNINGS'
                ],
                'dimensions'      => [
                    'DATE', 'COUNTRY_CODE', 'DOMAIN_NAME'
                ],
                'orderBy'         => '+DATE',
            ];

            // =================== 按域名维度拉取数据
            $params['filters'] = array_map(function ($domain) {
                return "DOMAIN_NAME=@$domain";
            }, $domainList[$account->id]);
            $result            = $adsense->accounts_reports->generate($account->adsense_name, $params);
            if (!$result['rows'] || count($result['rows']) == 0) continue;

            $headers = array_column($result['headers'], 'name');
            foreach ($result['rows'] as $row) {
                $insert = [];
                foreach ($headers as $k => $header) {
                    $insert[$header] = $row['cells'][$k]['value'];
                }
                $insert['FILLS']             = intval($insert['AD_REQUESTS_COVERAGE'] * $insert['AD_REQUESTS']);
                $insert['google_account_id'] = $account->id;
                InstantReport::create($insert);
            }
            // =================== 按广告单元维度拉取数据
            $params['dimensions'][] = 'AD_UNIT_NAME';
            $result                 = $adsense->accounts_reports->generate($account->adsense_name, $params);
            if (!$result['rows'] || count($result['rows']) == 0) continue;

            $headers = array_column($result['headers'], 'name');
            foreach ($result['rows'] as $row) {
                $insert = [];
                foreach ($headers as $k => $header) {
                    $insert[$header] = $row['cells'][$k]['value'];
                }
                $insert['FILLS']             = intval($insert['AD_REQUESTS_COVERAGE'] * $insert['AD_REQUESTS']);
                $insert['google_account_id'] = $account->id;
                $insert['AD_UNIT_URL']       = $this->ad2url($insert['AD_UNIT_NAME']);
                InstantReportUnit::create($insert);
            }

            // 均分自动广告收入
            for ($i = 0; $i < $days; $i++) {
                // 总收入
                $total_revenue = InstantReport::where('DATE', date("Y-m-d", strtotime("-$i days")))->sum('ESTIMATED_EARNINGS');
                $unit_revenue  = InstantReportUnit::where('DATE', date("Y-m-d", strtotime("-$i days")))->sum('ESTIMATED_EARNINGS');
                if (!($total_revenue > $unit_revenue)) continue;
                // 总请求数
                $total_requests = InstantReport::where('DATE', date("Y-m-d", strtotime("-$i days")))->sum('AD_REQUESTS');
                $unit_requests  = InstantReportUnit::where('DATE', date("Y-m-d", strtotime("-$i days")))->sum('AD_REQUESTS');
                // 总展示
                $total_impressions = InstantReport::where('DATE', date("Y-m-d", strtotime("-$i days")))->sum('IMPRESSIONS');
                $unit_impressions  = InstantReportUnit::where('DATE', date("Y-m-d", strtotime("-$i days")))->sum('IMPRESSIONS');
                // 总点击
                $total_clicks = InstantReport::where('DATE', date("Y-m-d", strtotime("-$i days")))->sum('CLICKS');
                $unit_clicks  = InstantReportUnit::where('DATE', date("Y-m-d", strtotime("-$i days")))->sum('CLICKS');

                $cursor = InstantReportUnit::field(['*', 'sum(AD_REQUESTS) as TOTAL_AD_REQUESTS'])
                    ->where('DATE', date("Y-m-d", strtotime("-$i days")))
                    ->group('AD_UNIT_URL')
                    ->cursor();
                foreach ($cursor as $item) {
                    // 计算自动广告
                    $EARNINGS    = ($total_revenue - $unit_revenue) * 100 / $unit_requests * $item['TOTAL_AD_REQUESTS'] / 100;
                    $AD_REQUESTS = ($total_requests - $unit_requests) * 100 / $unit_requests * $item['TOTAL_AD_REQUESTS'] / 100;
                    $IMPRESSIONS = ($total_impressions - $unit_impressions) * 100 / $unit_requests * $item['TOTAL_AD_REQUESTS'] / 100;
                    $CLICKS      = ($total_clicks - $unit_clicks) * 100 / $unit_requests * $item['TOTAL_AD_REQUESTS'] / 100;

                    $insert = [
                        'google_account_id'    => $item['google_account_id'],
                        'DATE'                 => $item['DATE'],
                        'AD_UNIT_NAME'         => $this->url2autoAdName($item['AD_UNIT_URL']),
                        'AD_UNIT_URL'          => $item['AD_UNIT_URL'],
                        'DOMAIN_NAME'          => $item['DOMAIN_NAME'],
                        'COUNTRY_CODE'         => $item['COUNTRY_CODE'],
                        'PAGE_VIEWS'           => 0,
                        // 请求数
                        'AD_REQUESTS'          => ceil($AD_REQUESTS),
                        // 展示数
                        'IMPRESSIONS'          => ceil($IMPRESSIONS),
                        // 填充率
                        'AD_REQUESTS_COVERAGE' => 1,
                        // 点击数
                        'CLICKS'               => $CLICKS,
                        // 点击率
                        'IMPRESSIONS_CTR'      => 0,
                        // 单价
                        'COST_PER_CLICK'       => 0,
                        // eCPM
                        'IMPRESSIONS_RPM'      => 0,
                        'ESTIMATED_EARNINGS'   => round($EARNINGS, 2)
                    ];
                    ReportUnit::create($insert);
                }
            }

        }
    }

    protected function ad2url(string $name): string
    {
        if ($name == 'minitool.app_0828_Online-Alarm-Clock_01') $name = 'minitool.app_0828_01';
        if (in_array($name, $this->oldAdName)) $name = 'cooltool.vip_0909_01';
        $name = explode("_", $name);
        array_splice($name, -2);
        return implode('/', $name) . '/';
    }

    protected function url2autoAdName(string $url): string
    {
        $name = explode("/", $url);
        array_pop($name);
        $name[] = 'autoad';
        return implode('_', $name);
    }
}
