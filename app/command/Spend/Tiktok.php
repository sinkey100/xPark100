<?php

namespace app\command\Spend;

use app\admin\model\spend\Data as SpendData;
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
        $this->days = 2;
        [$spend_table, $advertiser_ids] = $this->getSpendTable('tiktok');

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
                'campaign_name', 'spend', 'impressions', 'clicks', 'timezone',
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


                $result = $this->http('GET', $url, [
                    'query'   => $query,
                    'headers' => $headers
                ]);
                if ($result['code'] != 0) continue;

                foreach ($result['data']['list'] as $item) {
                    $table_row = $this->campaignToDomain($item['metrics']['campaign_name'], $spend_table);
                    if (!$table_row) continue;

                    $clicks       = $item['metrics']['clicks'];
                    $impressions  = $item['metrics']['impressions'];
                    $spend        = $item['metrics']['spend'];
                    $country_code = $item['dimensions']['country_code'];
                    $cpc          = empty($impressions) ? 0 : $clicks / $impressions;
                    $cpm          = empty($impressions) ? 0 : $spend / $impressions * 1000;

                    if (empty($impressions) && (empty($country_code) || 'None' == $country_code)) continue;

                    $insert_list[] = [
                        'app_id'        => $table_row['app_id'],
                        'channel_name'  => 'tiktok',
                        'domain_id'     => $table_row['domain_id'],
                        'channel_id'    => $table_row['channel_id'],
                        'is_app'        => $table_row['domain_or_app'],
                        'date'          => $item['dimensions']['stat_time_day'],
                        'country_code'  => $country_code,
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
