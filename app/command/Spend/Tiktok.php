<?php

namespace app\command\Spend;

use app\admin\model\spend\Data as SpendData;
use app\admin\model\xpark\Domain;
use app\command\Base;
use think\console\Input;
use think\console\Output;
use think\facade\Env;

class Tiktok extends Base
{


    protected function configure(): void
    {
        $this->setName('TikTok');
    }

    protected function execute(Input $input, Output $output): void
    {
        [$advertiser_ids, $campaigns, $spend_table] = $this->getSpendTable('tiktok');
        $this->days    = 2;
        $this->domains = Domain::where('channel_id', '>', 0)->select()->toArray();
        $this->domains = array_column($this->domains, null, 'domain');
        SpendData::where('channel_name', 'tiktok')->where('status', 1)->delete();

        // 拉数据
        $url     = 'https://business-api.tiktok.com/open_api/v1.3/report/integrated/get/';
        $query   = [
            'service_type'  => 'AUCTION',
            'report_type'   => 'BASIC',
            'data_level'    => 'AUCTION_ADGROUP',
            'dimensions'    => json_encode([
                'adgroup_id', 'stat_time_day', 'country_code'
            ]),
            'metrics'       => json_encode([
                'campaign_name', 'spend', 'impressions', 'clicks', 'timezone'
            ]),
            'start_date'    => '',
            'end_date'      => '',
            'page'          => 1,
            'page_size'     => 1000,
            'advertiser_id' => ''
        ];
        $headers = [
            'Access-Token' => Env::get('SPEND.TIKTOK_TOKEN')
        ];

        $insert_list = [];
        foreach ($advertiser_ids as $advertiser_id) {
            for ($i = $this->days - 1; $i >= 0; $i--) {
                $date                   = date("Y-m-d", strtotime("-{$i} days"));
                $query['advertiser_id'] = $advertiser_id;
                $query['start_date']    = $date;
                $query['end_date']      = $date;


                $result                 = $this->http('GET', $url, [
                    'query'   => $query,
                    'headers' => $headers
                ]);
                if ($result['code'] != 0) continue;

                foreach ($result['data']['list'] as $item) {
                    $domain_name = $this->campaignToDomain($item['metrics']['campaign_name'], $campaigns);
                    if (!isset($this->domains[$domain_name])) continue;
                    $domain = $this->domains[$domain_name];

                    $clicks      = $item['metrics']['clicks'];
                    $impressions = $item['metrics']['impressions'];
                    $spend       = $item['metrics']['spend'];
                    $cpc         = empty($impressions) ? 0 : $clicks / $impressions;
                    $cpm         = empty($impressions) ? 0 : $spend / $impressions * 1000;

                    $insert_list[] = [
                        'app_id'        => $domain['app_id'],
                        'channel_name'  => 'tiktok',
                        'domain_id'     => $domain['id'],
                        'is_app'        => $domain['is_app'],
                        'date'          => $item['dimensions']['stat_time_day'],
                        'country_code'  => $item['dimensions']['country_code'],
                        'spend'         => $spend,
                        'clicks'        => $clicks,
                        'impressions'   => $impressions,
                        'install'       => 0,
                        'campaign_name' => $item['metrics']['campaign_name'],
                        'cpc'           => $cpc,
                        'cpm'           => $cpm,
                    ];
                }
            }
        }
        $this->saveSpendData($insert_list);

        for ($i = $this->days - 1; $i >= 0; $i--) {
            $date = date("Y-m-d", strtotime("-{$i} days"));
            SpendData::where('channel_name', 'tiktok')->where('status', 0)->where('date', $date)->delete();
        }
        SpendData::where('channel_name', 'tiktok')->where('status', 1)->update(['status' => 0]);
    }

}
