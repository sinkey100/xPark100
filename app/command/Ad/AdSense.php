<?php

namespace app\command\Ad;

use app\admin\model\google\Account;
use app\admin\model\xpark\Data;
use app\admin\model\xpark\Domain;
use app\command\Base;
use Google\Service\Adsense as GoogleAdSense;
use sdk\FeishuBot;
use sdk\Google as GoogleSDK;
use think\console\Input;
use think\console\Output;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use Exception;
use app\admin\model\h5\ChannelRevenue;

class AdSense extends Base
{

    protected function configure()
    {
        $this->setName('AdSense');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->log("\n\n======== AdSense 开始拉取数据 ========", false);

        Data::where('channel', 'AdSense')->where('status', 1)->delete();

        $this->log("任务开始，拉取 {$this->days} 天");
        $this->log('开始拉取 AdSense 数据');
        try {
            $this->pull();
        } catch (Exception $e) {
            $this->log("[{$e->getLine()}|{$e->getFile()}]{$e->getMessage()}");
            print_r($e->getTraceAsString());
            $this->log('======== AdSense 拉取数据失败 ========', false);
            return;
        }

        for ($i = 0; $i < $this->days; $i++) {
            Data::where('channel', 'AdSense')->where('status', 0)->where('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
            Data::where('channel', 'AdSense')->where('status', 1)->where('a_date', date("Y-m-d", strtotime("-$i days")))->update([
                'status' => 0
            ]);
        }

        $this->log('历史数据已删除');

        $this->revenue();
        $this->account();

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

        // 获取数据
        foreach ($domains_group as $flag => $domains) {
            $account = Account::where('flag', $flag)->find();
            if (!$account) {
                $this->log('AdSense 账号标记不存在' . $flag);
                continue;
            }
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
                    'DATE', 'COUNTRY_CODE', 'DOMAIN_NAME', 'AD_UNIT_NAME'
                ],
                'orderBy'         => '+DATE',
                'filters'         => implode(',', $filter)
            ];

            try {
                $result = $adsense->accounts_reports->generate($account->adsense_name, $params);
            } catch (Exception $e) {
                FeishuBot::text("AdSense[$flag]报告拉取错误:" . $e->getTraceAsString());
                continue;
            }
            if (!$result['rows'] || count($result['rows']) == 0) continue;

            $this->log("{$flag} 广告单元数据拉取完成");

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
                $channel_full = 'AdSense-' . $account->flag;

                $data[] = [
                    'channel'         => 'AdSense',
                    'channel_full'    => $channel_full,
                    'channel_id'      => $this->channelList[$channel_full]['id'] ?? 0,
                    'channel_type'    => ($this->channelList[$channel_full]['ad_type'] ?? 'H5') == 'H5' ? 0 : 1,
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
            unset($insert, $result);
            $this->saveData($data);
            unset($data);

            // 自动广告拉取
            $params['dimensions'] = ['DATE', 'AD_FORMAT_CODE', 'COUNTRY_CODE', 'DOMAIN_NAME'];
            $result               = $adsense->accounts_reports->generate($account->adsense_name, $params);
            if (!$result['rows'] || count($result['rows']) == 0) continue;

            $this->log("{$flag} 自动广告数据拉取完成");

            $headers = array_column($result['headers'], 'name');
            if (count($result['rows']) == 0) continue;
            $data = [];
            foreach ($result['rows'] as $row) {
                $insert = [];
                foreach ($headers as $k => $header) {
                    $insert[$header] = $row['cells'][$k]['value'];
                }
                if ($insert['AD_FORMAT_CODE'] == 'ON_PAGE') continue;
                $insert['FILLS'] = intval($insert['AD_REQUESTS_COVERAGE'] * $insert['AD_REQUESTS']);

                [$domain_id, $app_id] = $this->getDomainRow($insert['DOMAIN_NAME'], $insert['DATE'], 'AdSense');
                $channel_full = 'AdSense-' . $account->flag;

                $data[] = [
                    'channel'         => 'AdSense',
                    'channel_id'      => $this->channelList[$channel_full]['id'] ?? 0,
                    'channel_full'    => $channel_full,
                    'sub_channel'     => $insert['DOMAIN_NAME'],
                    'domain_id'       => $domain_id,
                    'app_id'          => $app_id,
                    'a_date'          => $insert['DATE'],
                    'country_code'    => $insert['COUNTRY_CODE'],
                    'ad_placement_id' => strtolower('ADS_' . $insert['AD_FORMAT_CODE']),
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
    }

    protected function revenue(): void
    {
        $arr = [4 => 7, 5 => 4];
        foreach ($arr as $channel_id => $account_id) {
            $account = Account::where('id', $account_id)->find();
            if (!$account) throw new Exception('AdSense 账号标记不存在');
            $client = (new GoogleSDK())->init($account);
            $client->setAccessToken($account->auth);
            $adsense = new GoogleAdSense($client);

            // 拉取数据参数
            $startTime = strtotime("-3 days");
            $params    = [
                'startDate.year'  => date("Y", $startTime),
                'startDate.month' => date("m", $startTime),
                'startDate.day'   => date("d", $startTime),
                'endDate.year'    => date("Y"),
                'endDate.month'   => date("m"),
                'endDate.day'     => date("d"),
                'metrics'         => ['ESTIMATED_EARNINGS'],
                'dimensions'      => ['DATE'],
                'orderBy'         => '+DATE',
            ];

            $result = $adsense->accounts_reports->generate($account->adsense_name, $params);
            if (!$result['rows'] || count($result['rows']) == 0) continue;
            $headers = array_column($result['headers'], 'name');
            foreach ($result['rows'] as $row) {
                $insert = [];
                foreach ($headers as $k => $header) {
                    $insert[$header] = $row['cells'][$k]['value'];
                }
                $row = ChannelRevenue::where('date', $insert['DATE'])->where('channel_id', $channel_id)->find();
                if ($row) {
                    $row->revenue = $insert['ESTIMATED_EARNINGS'];
                    $row->save();
                } else {
                    ChannelRevenue::create([
                        'date'       => $insert['DATE'],
                        'channel_id' => $channel_id,
                        'revenue'    => $insert['ESTIMATED_EARNINGS']
                    ]);
                }
            }
        }
    }

    protected function account(): void
    {
        if (date("H") != 12) return;
        $accounts = Account::where('adsense_state', 'READY')->select();
        $message  = [];
        foreach ($accounts as $account) {
            $expired_time = is_int($account['updated']) ? $account['updated'] : strtotime($account['updated']);
            $day          = round(($expired_time - time()) / 86400, 2);
            $message[]    = "{$account->flag} Google授权" . (time() > $expired_time ? '已过期' : "有效期还剩 $day 天");
        }
        FeishuBot::text(implode("\n", $message));
    }


}
