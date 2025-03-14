<?php

namespace app\command\Spend;

use app\admin\model\cy\CYIosGame;
use app\admin\model\spend\Bind;
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

        $bind = Bind::field(['campaign_id', 'domain_name'])->where('platform', 'tiktok')->order('date', 'desc')->group('campaign_id')->select();
        $bind = array_column($bind->toArray(), null, 'campaign_id');

        SpendData::where('channel_name', 'tiktok')->where('status', 1)->delete();

        // 投放数据参数
        $spend_url   = 'https://business-api.tiktok.com/open_api/v1.3/report/integrated/get/';
        $spend_query = [
            'service_type'  => 'AUCTION',
            'report_type'   => 'BASIC',
            'data_level'    => 'AUCTION_ADGROUP',
            'dimensions'    => json_encode([
                'adgroup_id', 'stat_time_day', 'country_code'
            ]),
            'metrics'       => json_encode([
                'campaign_name', 'campaign_id', 'spend', 'impressions', 'clicks', 'conversion',
            ]),
            'start_date'    => date("Y-m-d", strtotime("-{$this->days} days")),
            'end_date'      => date("Y-m-d"),
            'page'          => 1,
            'page_size'     => 1000,
            'advertiser_id' => ''
        ];
        $headers       = [
            'Access-Token' => Env::get('SPEND.TIKTOK_TOKEN'),
            'Content-Type' => 'application/json'
        ];

        // 分页获取所有投放数据
        $spend_data = [];
        foreach ($advertiser_ids as $advertiser_id) {
            $spend_query['advertiser_id'] = $advertiser_id;
            $spend_query['page']          = 1;
            do {
                $result = $this->http('GET', $spend_url, [
                    'json'    => $spend_query,
                    'headers' => $headers
                ]);
                if ($result['code'] == 0 && $result['message'] == 'OK') {
                    $spend_data = array_merge($spend_data, $result['data']['list']);
                    $totalPages = $result['data']['page_info']['total_page'];
                    $spend_query['page']++;
                } else {
                    break;
                }
            } while ($spend_query['page'] <= $totalPages);
        }

        // 存储数据
        $insert_list = [];

        foreach ($spend_data as $item) {
            $domain_name = $bind[$item['metrics']['campaign_id']]['domain_name'] ?? null;
            if (!$domain_name) continue;
            $is_app = isset($this->apps[$domain_name]) ? 1 : 0;
            if ($is_app == 0 && !isset($this->domains[$domain_name])) continue;

            $app_id     = $is_app ? $this->apps[$domain_name]['id'] : $this->domains[$domain_name]['app_id'];
            $domain_id  = $is_app ? 0 : $this->domains[$domain_name]['id'];
            $channel_id = $is_app ? 0 : $this->domains[$domain_name]['channel_id'];

            $clicks       = $item['metrics']['clicks'];
            $impressions  = $item['metrics']['impressions'];
            $conversion   = $item['metrics']['conversion'];
            $spend        = $item['metrics']['spend'];
            $country_code = $item['dimensions']['country_code'];
            $cpc          = empty($impressions) ? 0 : $clicks / $impressions;
            $cpm          = empty($impressions) ? 0 : $spend / $impressions * 1000;

            if (empty($impressions) && (empty($country_code) || 'None' == $country_code)) continue;

            $insert_list[] = [
                'app_id'        => $app_id,
                'channel_name'  => 'tiktok',
                'domain_id'     => $domain_id,
                'channel_id'    => $channel_id,
                'is_app'        => $is_app,
                'date'          => $item['dimensions']['stat_time_day'],
                'country_code'  => $country_code,
                'spend'         => $spend,
                'clicks'        => $clicks,
                'impressions'   => $impressions,
                'conversion'    => $conversion,
                'install'       => 0,
                'campaign_name' => $item['metrics']['campaign_name'],
                'cpc'           => $cpc,
                'cpm'           => $cpm,
            ];
        }

        if (count($insert_list) > 0) {
            $this->saveSpendData($insert_list);
            SpendData::where('channel_name', 'tiktok')->where('status', 0)->whereTime('date', '>=', date("Y-m-d", strtotime("-{$this->days} days")))->delete();
            SpendData::where('channel_name', 'tiktok')->where('status', 1)->update(['status' => 0]);
        }
    }

}
