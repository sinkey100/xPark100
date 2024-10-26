<?php

namespace app\command\Ad;

use app\admin\model\xpark\Data;
use app\admin\model\xpark\Domain;
use app\command\Base;
use think\console\Input;
use think\console\Output;
use think\facade\Env;
use Exception;

class BeesAds extends Base
{

    protected function configure()
    {
        $this->setName('BeesAds');
    }

    protected function execute(Input $input, Output $output): void
    {
        // 获取小蜜蜂账号数量
        $accounts = Domain::field('flag')->where('channel', 'BeesAds')->group('flag')->select();
        $accounts = array_column($accounts->toArray(), 'flag');

        $this->log("\n\n======== BeesAds 开始拉取数据 ========", false);
        $this->log("任务开始，拉取 {$this->days} 天");

        $rawData = [];
        foreach ($accounts as $account) {
            $rawData = array_merge($rawData, $this->pull($account));
        }

        if (empty($rawData) || count($rawData) == 0) {
            $this->log('======== BeesAds 拉取数据完成 ========', false);
            return;
        }

        $this->log('准备删除历史数据');
        for ($i = 0; $i < $this->days; $i++) {
            Data::where('channel', 'BeesAds')->where('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
        }
        $this->log('历史数据已删除');

        if (count($rawData) > 0) {
            $this->log('准备保存新的数据');
            $this->saveData($rawData);
        }

        $this->log('======== BeesAds 拉取数据完成 ========', false);
    }

    protected function pull(string $account): array
    {
        $this->log('准备开始拉取:' . $account);

        $returnRows = [];
        $pageSize   = 1000;
        $days       = $this->days - 1;
        $params     = [
            'headers' => [
                'x-apihub-ak'  => Env::get('BEESADS.' . $account),
                'x-apihub-env' => 'prod',
            ],
            'json'    => [
                'date_range' => [
                    'start' => date("Y-m-d", strtotime("-{$days} days")),
                    'end'   => date("Y-m-d")
                ],
                'dimensions' => [
                    'Date', 'Domain', 'Country', 'Zone'
                ],
                'sorts'      => [
                    'Date' => 1
                ],
                'page_index' => 1,
                'page_size'  => $pageSize
            ],
        ];

        // 获取total
        $params['json']['page_size'] = 1;
        try {
            $result = $this->http('POST',
                'https://api-us-east.eclicktech.com.cn/wgt/report/gamebridge/v1/ssp/report',
                $params
            );
        } catch (Exception $e) {
            $this->log('POST请求出错: ' . $e->getMessage());
            return [];
        }
        if (!empty($result['error'])) {
            $this->log('接口返回报错');
            $this->log(json_encode($result));
            return [];
        }
        if (empty($result['data']['total'])) {
            $this->log('拉取数据完成，没有返回数据');
            $this->log(json_encode($result));
            return [];
        }
        $this->log('共找到' . $result['data']['total'] . '条数据');

        $pages = ceil($result['data']['total'] / $pageSize);
        // 批量拉取数据

        for ($page = 1; $page <= $pages; $page++) {
            sleep(5);
            $params['json']['page_index'] = $page;
            $params['json']['page_size']  = $pageSize;
            $result                       = $this->http('POST',
                'https://api-us-east.eclicktech.com.cn/wgt/report/gamebridge/v1/ssp/report',
                $params
            );
            $data                         = [];
            if (empty($result['data']['rows'])) {
                $this->log('没有拉取到数据');
                return [];
            }

            foreach ($result['data']['rows'] as $v) {
                [$domain_id, $app_id] = $this->getDomainRow($v['Domain'], $v['Date'], 'BeesAds');
                $row    = [
                    'channel'         => 'BeesAds',
                    'channel_full'    => 'BeesAds-' . $account,
                    'sub_channel'     => $v['Domain'],
                    'domain_id'       => $domain_id,
                    'app_id'          => $app_id,
                    'a_date'          => $v['Date'],
                    'country_code'    => $v['Country'],
                    'ad_placement_id' => $v['Zone'],
                    'requests'        => $v['TotalAdRequests'],
                    'fills'           => (float)$v['ResponseRate'] * (float)$v['TotalAdRequests'],
                    'impressions'     => $v['Impressions'],
                    'clicks'          => $v['Clicks'],
                    'ad_revenue'      => $v['GrossRevenue'],

                    'gross_revenue' => $v['GrossRevenue'],
                    'net_revenue'   => $v['NetRevenue'],
                    'raw_cpc'       => $v['Cpc'],
                    'raw_ctr'       => $v['Ctr'],
                    'raw_ecpm'      => $v['ECpm']
                ];
                $data[] = $row;
            }
            $returnRows = array_merge($returnRows, $data);
        }
        $this->log('拉取数据完成，长度' . count($returnRows));
        return $returnRows;
    }

}
