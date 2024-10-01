<?php

namespace app\command;

use app\admin\model\google\Account;
use app\admin\model\xpark\Data;
use app\admin\model\xpark\Domain;
use app\admin\model\xpark\XparkAdSense;
use think\console\Input;
use think\console\Output;
use Exception;
use Google\Service\AdSense as GoogleAdSense;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use sdk\Google as GoogleSDK;


class AdSense extends Base
{

    protected array $domains = [];

    protected function configure()
    {
        // 指令配置
        $this->setName('AdSense');
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
        $this->domains = Domain::where('channel', 'AdSense')->select()->toArray();
        $this->domains = array_column($this->domains, null, 'domain');

        $days = 3;

        // 清除老数据
        for ($i = 0; $i < $days; $i++) {
            Data::where('channel', 'AdSense')->where('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
            $maxId = Data::max('id');
            $maxId++;
            Db::execute("ALTER TABLE `ba_xpark_data` AUTO_INCREMENT={$maxId};");
        }
        Db::execute("truncate table ba_xpark_adsense;");


        $accounts = Account::where('adsense_state', 'READY')->select();
        foreach ($accounts as $account) {
            // 初始化
            $client = (new GoogleSDK())->init($account);
            $client->setAccessToken($account->auth);
            $adsense = new GoogleAdSense($client);

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
                    'AD_REQUESTS', 'AD_REQUESTS_COVERAGE', 'CLICKS', 'IMPRESSIONS', 'ESTIMATED_EARNINGS',
                    'COST_PER_CLICK', 'IMPRESSIONS_RPM', 'IMPRESSIONS_CTR'
                ],
                'dimensions'      => [
                    'DATE', 'COUNTRY_CODE', 'DOMAIN_NAME'
                ],
                'orderBy'         => '+DATE',
            ];
            // 按域名拉取
            $filter = [];
            foreach ($this->domains as $filter_domain_name=>$v) {
                $filter[] = 'DOMAIN_NAME==' . $filter_domain_name;
            }
            $params['filters'] = implode(',', $filter);
            $result            = $adsense->accounts_reports->generate($account->adsense_name, $params);

            if (!$result['rows'] || count($result['rows']) == 0) continue;

            $headers = array_column($result['headers'], 'name');
            $data    = [];
            foreach ($result['rows'] as $row) {
                $insert = [];
                foreach ($headers as $k => $header) {
                    $insert[$header] = $row['cells'][$k]['value'];
                }
                $insert['FILLS'] = intval($insert['AD_REQUESTS_COVERAGE'] * $insert['AD_REQUESTS']);
                $data[]          = [
                    'sub_channel'  => $insert['DOMAIN_NAME'],
                    'a_date'       => $insert['DATE'],
                    'country_code' => $insert['COUNTRY_CODE'],
                    'requests'     => $insert['AD_REQUESTS'],
                    'fills'        => $insert['FILLS'],
                    'impressions'  => $insert['IMPRESSIONS'],
                    'clicks'       => $insert['CLICKS'],
                    'ad_revenue'   => $insert['ESTIMATED_EARNINGS'],
                    'raw_cpc'      => $insert['COST_PER_CLICK'],
                    'raw_ctr'      => $insert['IMPRESSIONS_CTR'],
                    'raw_ecpm'     => $insert['IMPRESSIONS_RPM']
                ];
            }
            XparkAdSense::insertAll($data);
            unset($data, $insert);

            // 按广告单元拉取
            $params['dimensions'][] = 'AD_UNIT_NAME';
            $result                 = $adsense->accounts_reports->generate($account->adsense_name, $params);
            if (!$result['rows'] || count($result['rows']) == 0) continue;
            $headers = array_column($result['headers'], 'name');
            if (count($result['rows']) == 0) continue;
            $data = [];
            foreach ($result['rows'] as $row) {
                $insert = [];
                foreach ($headers as $k => $header) {
                    $insert[$header] = $row['cells'][$k]['value'];
                }
                $insert['FILLS'] = intval($insert['AD_REQUESTS_COVERAGE'] * $insert['AD_REQUESTS']);
                $data[]          = [
                    'channel'         => 'AdSense',
                    'sub_channel'     => $insert['DOMAIN_NAME'],
                    'domain_id'       => $this->domains[$insert['DOMAIN_NAME']]['id'],
                    'app_id'          => $this->domains[$insert['DOMAIN_NAME']]['app_id'],
                    'a_date'          => $insert['DATE'],
                    'country_code'    => $insert['COUNTRY_CODE'],
                    'ad_placement_id' => $insert['AD_UNIT_NAME'],
                    'requests'        => $insert['AD_REQUESTS'],
                    'fills'           => $insert['FILLS'],
                    'impressions'     => $insert['IMPRESSIONS'],
                    'clicks'          => $insert['CLICKS'],
                    'ad_revenue'      => $insert['ESTIMATED_EARNINGS'],
                    'gross_revenue'   => $insert['ESTIMATED_EARNINGS'],
                    'net_revenue'     => $insert['ESTIMATED_EARNINGS'],
                    'raw_cpc'         => $insert['COST_PER_CLICK'],
                    'raw_ctr'         => $insert['IMPRESSIONS_CTR'],
                    'raw_ecpm'        => $insert['IMPRESSIONS_RPM']
                ];
            }
            unset($insert, $result, $adsense);
            $class = new Xpark();
            $class->saveData($data);
            unset($class, $data);
        }
        // 自动广告计算
        for ($i = 0; $i < $days; $i++) {
            foreach ($this->domains as $domain_name => $v){

                $output->writeln("\n $domain_name " . date("Y-m-d", strtotime("-$i days")));

                // 总收入
                $total_revenue = XparkAdSense::where('sub_channel', $domain_name)->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('ad_revenue');
                $unit_revenue  = Data::where('sub_channel', $domain_name)->where('channel', 'AdSense')->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('gross_revenue');

                // 总请求数
                $total_requests = XparkAdSense::where('sub_channel', $domain_name)->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('requests');
                $unit_requests  = Data::where('sub_channel', $domain_name)->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('requests');

                $output->writeln($total_requests);
                $output->writeln($unit_requests);


                if (
                    !($total_revenue > $unit_revenue)
                    ||  !($total_requests > $unit_requests)
                ) continue;

                // 总展示
                $total_impressions = XparkAdSense::where('sub_channel', $domain_name)->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('impressions');
                $unit_impressions  = Data::where('sub_channel', $domain_name)->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('impressions');
                // 总点击
                $total_clicks = XparkAdSense::where('sub_channel', $domain_name)->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('clicks');
                $unit_clicks  = Data::where('sub_channel', $domain_name)->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('clicks');
                $cursor = Data::field(['*', 'sum(requests) as total_requests'])
                    ->where('sub_channel', $domain_name)
                    ->where('channel', 'AdSense')
                    ->where('a_date', date("Y-m-d", strtotime("-$i days")))
                    ->group('country_code')
                    ->cursor();
                $data = [];
                foreach ($cursor as $item) {
                    // 计算自动广告
                    $EARNINGS    = ($total_revenue - $unit_revenue) * 100 / $unit_requests * $item['total_requests'] / 100;
                    $AD_REQUESTS = ($total_requests - $unit_requests) * 100 / $unit_requests * $item['total_requests'] / 100;
                    $IMPRESSIONS = ($total_impressions - $unit_impressions) * 100 / $unit_requests * $item['total_requests'] / 100;
                    $CLICKS      = ($total_clicks - $unit_clicks) * 100 / $unit_requests * $item['total_requests'] / 100;
                    $data[]          = [
                        'channel'         => 'AdSense',
                        'sub_channel'     => $item['sub_channel'],
                        'domain_id'       => $item['domain_id'],
                        'app_id'          => $item['app_id'],
                        'a_date'          => $item['a_date'],
                        'country_code'    => $item['country_code'],
                        'ad_placement_id' => 'auto_ad',
                        'requests'        => ceil($AD_REQUESTS),
                        'fills'           => ceil($AD_REQUESTS),
                        'impressions'     => ceil($IMPRESSIONS),
                        'clicks'          => $CLICKS,
                        'ad_revenue'      => round($EARNINGS, 2),
                        'gross_revenue'   => round($EARNINGS, 2),
                        'net_revenue'     => $item['net_revenue'],
                        'raw_cpc'         => round($EARNINGS / (!empty($CLICKS) ? $CLICKS : 1), 2),
                        'raw_ecpm'        => round($EARNINGS / (!empty($IMPRESSIONS) ? $IMPRESSIONS : 1) * 1000, 3)
                    ];

                }
                $class = new Xpark();
                $class->saveData($data);
                unset($class, $data);
            }
        }
    }
}
