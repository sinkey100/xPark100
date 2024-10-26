<?php

namespace app\command\Ad;

use app\admin\model\google\Account;
use app\admin\model\xpark\Data;
use app\admin\model\xpark\Domain;
use app\admin\model\xpark\XparkAdSense;
use app\command\Base;
use Google\Service\Adsense as GoogleAdSense;
use sdk\Google as GoogleSDK;
use think\console\Input;
use think\console\Output;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use Exception;

class AdSense extends Base
{

    protected function configure()
    {
        $this->setName('AdSense');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->log("\n\n======== AdSense 开始拉取数据 ========", false);
        $this->log("任务开始，拉取 {$this->days} 天");

        for ($i = 0; $i < $this->days; $i++) {
            Data::where('channel', 'AdSense')->where('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
        }
        $this->log('历史数据已删除');
        $this->log('开始拉取 AdSense 数据');
        try {
            $this->pull();
        } catch (Exception $e) {
            $this->log("[{$e->getLine()}|{$e->getFile()}]{$e->getMessage()}");
            print_r($e->getTraceAsString());
            $this->log('======== AdSense 拉取数据失败 ========', false);
            return;
        }

        $this->log('======== AdSense 拉取数据完成 ========', false);
    }

    /**
     * @throws DataNotFoundException
     * @throws \Google\Exception
     * @throws \Google\Service\Exception
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
     */
    protected function pull(): void
    {
        // 获取账号和域名
        $adsense_domains = Domain::where('channel', 'AdSense')->where('flag', '<>', '')->select()->toArray();
        $domains_group   = [];
        foreach ($adsense_domains as $domain) {
            $flag = $domain['flag'];
            if (!isset($domains_group[$flag])) $domains_group[$flag] = [];
            $domains_group[$flag][] = $domain['domain'];
        }

        // 删除比对数据
        Db::execute("truncate table ba_xpark_adsense;");

        // 获取数据
        foreach ($domains_group as $flag => $domains) {
            $account = Account::where('flag', $flag)->find();
            if (!$account) throw new Exception('AdSense 账号标记不存在');
            $client = (new GoogleSDK())->init($account);
            $client->setAccessToken($account->auth);
            $adsense = new GoogleAdSense($client);

            // 拉取数据参数
            $filter = [];
            foreach ($domains as $domain) {
                $filter[] = 'DOMAIN_NAME==' . $domain;
            }
            $startTime = strtotime("-" . ($this->days - 1) . " days");
            $params    = [
                'startDate.year'  => date("Y", $startTime),
                'startDate.month' => date("m", $startTime),
                'startDate.day'   => date("d", $startTime),
                'endDate.year'    => date("Y"),
                'endDate.month'   => date("m"),
                'endDate.day'     => date("d"),
                'metrics'         => [
                    'AD_REQUESTS', 'AD_REQUESTS_COVERAGE', 'CLICKS', 'IMPRESSIONS', 'ESTIMATED_EARNINGS',
                    'COST_PER_CLICK', 'IMPRESSIONS_RPM', 'IMPRESSIONS_CTR'
                ],
                'dimensions'      => [
                    'DATE', 'COUNTRY_CODE', 'DOMAIN_NAME'
                ],
                'orderBy'         => '+DATE',
                'filters'         => implode(',', $filter)
            ];

            // 按域名拉取
            try {
                $result = $adsense->accounts_reports->generate($account->adsense_name, $params);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }

            if (!$result['rows'] || count($result['rows']) == 0) continue;
            $this->log("{$flag} 地区数据拉取完成");

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
            unset($data, $insert, $result);

            // 按广告单元拉取
            $params['dimensions'][] = 'AD_UNIT_NAME';
            $result                 = $adsense->accounts_reports->generate($account->adsense_name, $params);
            if (!$result['rows'] || count($result['rows']) == 0) continue;

            $this->log("{$flag} 广告位数据拉取完成");

            $headers = array_column($result['headers'], 'name');
            if (count($result['rows']) == 0) continue;
            $data = [];
            foreach ($result['rows'] as $row) {
                $insert = [];
                foreach ($headers as $k => $header) {
                    $insert[$header] = $row['cells'][$k]['value'];
                }
                $insert['FILLS'] = intval($insert['AD_REQUESTS_COVERAGE'] * $insert['AD_REQUESTS']);

                [$domain_id, $app_id] = $this->getDomainRow($insert['DOMAIN_NAME'], $insert['DATE'], 'AdSense');
                $data[] = [
                    'channel'         => 'AdSense',
                    'channel_full'    => 'AdSense-' . $account->flag,
                    'sub_channel'     => $insert['DOMAIN_NAME'],
                    'domain_id'       => $domain_id,
                    'app_id'          => $app_id,
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
            $this->saveData($data);
            unset($data);
        }
        $this->log("开始计算自动广告");

        // 自动广告计算
        for ($i = 0; $i < $this->days; $i++) {
            foreach ($adsense_domains as $domain) {
                // 总收入
                $domain_name   = $domain['domain'];
                $total_revenue = XparkAdSense::where('sub_channel', $domain_name)->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('ad_revenue');
                $unit_revenue  = Data::where('sub_channel', $domain_name)->where('channel', 'AdSense')->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('gross_revenue');

                // 总请求数
                $total_requests = XparkAdSense::where('sub_channel', $domain_name)->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('requests');
                $unit_requests  = Data::where('sub_channel', $domain_name)->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('requests');

                if (
                    !($total_revenue > $unit_revenue)
                    || !($total_requests > $unit_requests)
                ) continue;

                // 总展示
                $total_impressions = XparkAdSense::where('sub_channel', $domain_name)->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('impressions');
                $unit_impressions  = Data::where('sub_channel', $domain_name)->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('impressions');
                // 总点击
                $total_clicks = XparkAdSense::where('sub_channel', $domain_name)->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('clicks');
                $unit_clicks  = Data::where('sub_channel', $domain_name)->where('a_date', date("Y-m-d", strtotime("-$i days")))->sum('clicks');
                $cursor       = Data::field(['*', 'sum(requests) as total_requests'])
                    ->where('sub_channel', $domain_name)
                    ->where('channel', 'AdSense')
                    ->where('a_date', date("Y-m-d", strtotime("-$i days")))
                    ->group('country_code')
                    ->cursor();
                $data         = [];
                foreach ($cursor as $item) {
                    // 计算自动广告
                    $EARNINGS    = ($total_revenue - $unit_revenue) * 100 / $unit_requests * $item['total_requests'] / 100;
                    $AD_REQUESTS = ($total_requests - $unit_requests) * 100 / $unit_requests * $item['total_requests'] / 100;
                    $IMPRESSIONS = ($total_impressions - $unit_impressions) * 100 / $unit_requests * $item['total_requests'] / 100;
                    $CLICKS      = ($total_clicks - $unit_clicks) * 100 / $unit_requests * $item['total_requests'] / 100;
                    $data[]      = [
                        'channel'         => 'AdSense',
                        'channel_full'    => $item['channel_full'],
                        'sub_channel'     => $item['sub_channel'],
                        'domain_id'       => $item['domain_id'],
                        'app_id'          => $item['app_id'],
                        'a_date'          => $item['a_date'],
                        'country_code'    => $item->getData('country_code'),
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
                $this->saveData($data);
                unset($data);
            }
        }
    }

}
