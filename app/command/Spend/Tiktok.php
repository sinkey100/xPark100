<?php

namespace app\command\Spend;

use app\admin\model\cy\CYIosGame;
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
        // 普通广告列表参数
        $ad_url   = 'https://business-api.tiktok.com/open_api/v1.3/ad/get/';
        $ad_query = [
            'page'          => 1,
            'page_size'     => 1000,
            'advertiser_id' => ''
        ];
        // Smart 广告列表参数
        $smart_url   = 'https://business-api.tiktok.com/open_api/v1.3/campaign/spc/get/';
        $smart_query = [
            'advertiser_id' => ''
        ];
        // App 广告列表参数
        $app_url       = 'https://business-api.tiktok.com/open_api/v1.3/adgroup/get/';
        $app_query     = [
            'advertiser_id' => '',
            'page'          => 1,
            'page_size'     => 1000,
        ];
        $headers       = [
            'Access-Token' => Env::get('SPEND.TIKTOK_TOKEN'),
            'Content-Type' => 'application/json'
        ];
        $campaign_list = [];

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

        // 分页获取普通广告数据
        $ad_data = [];
        foreach ($advertiser_ids as $advertiser_id) {
            $ad_query['advertiser_id'] = $advertiser_id;
            $ad_query['page']          = 1;
            do {
                $result = $this->http('GET', $ad_url, [
                    'json'    => $ad_query,
                    'headers' => $headers
                ]);
                if ($result['code'] == 0 && $result['message'] == 'OK') {
                    $ad_data    = array_merge($ad_data, $result['data']['list']);
                    $totalPages = $result['data']['page_info']['total_page'];
                    $ad_query['page']++;
                } else {
                    break;
                }
            } while ($ad_query['page'] <= $totalPages);
        }
        foreach ($ad_data as $row) {
            if (empty($row['landing_page_url']) || isset($campaign_list[$row['campaign_id']])) continue;
            $campaign_list[$row['campaign_id']] = parse_url($row['landing_page_url'])['host'];
        }

        $campaign_ids = array_map(fn($item) => $item['metrics']['campaign_id'], $spend_data);
        $campaign_ids = array_values(array_unique($campaign_ids));

        // 获取smart广告数据
        $smart_data = [];
        foreach ($advertiser_ids as $advertiser_id) {
            $chunks = array_chunk($campaign_ids, 30);
            foreach ($chunks as $chunk) {
                $smart_query['advertiser_id'] = $advertiser_id;
                $smart_query['campaign_ids']  = $chunk;
                $result                       = $this->http('GET', $smart_url, [
                    'json'    => $smart_query,
                    'headers' => $headers
                ]);
                if ($result['code'] == 0 && $result['message'] == 'OK') {
                    $smart_data = array_merge($smart_data, $result['data']['list']);
                }
            }
        }
        foreach ($smart_data as $row) {
            if (empty($row['landing_page_urls'][0]['landing_page_url']) || isset($campaign_list[$row['campaign_id']])) continue;
            $campaign_list[$row['campaign_id']] = parse_url($row['landing_page_urls'][0]['landing_page_url'])['host'];
        }

        // 分页获取app投放数据
        $app_data = [];
        foreach ($advertiser_ids as $advertiser_id) {
            $app_query['advertiser_id'] = $advertiser_id;
            $app_query['page']          = 1;
            do {
                $result = $this->http('GET', $app_url, [
                    'json'    => $app_query,
                    'headers' => $headers
                ]);
                if ($result['code'] == 0 && $result['message'] == 'OK') {
                    $app_data   = array_merge($app_data, $result['data']['list']);
                    $totalPages = $result['data']['page_info']['total_page'];
                    $app_query['page']++;
                } else {
                    break;
                }
            } while ($app_query['page'] <= $totalPages);
        }

        foreach ($app_data as $row) {
            if (empty($row['app_download_url']) || isset($campaign_list[$row['campaign_id']])) continue;
            if (!preg_match('/id(\d+)/', $row['app_download_url'], $matches)) continue;
            $appstore_id = $matches[0];
            $ios_app     = CYIosGame::where('appstore_url', 'like', "%$appstore_id%")->find();
            if (!$ios_app) continue;
            $campaign_list[$row['campaign_id']] = $ios_app->bundle_id;
        }

        // 存储数据
        $insert_list = [];

        foreach ($spend_data as $item) {

            $domain_name = $campaign_list[$item['metrics']['campaign_id']] ?? null;
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
