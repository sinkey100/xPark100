<?php

namespace app\command\Spend;

use app\admin\model\cy\CYIosGame;
use app\admin\model\spend\Data as SpendData;
use app\admin\model\spend\FbCreative;
use app\admin\model\xpark\Domain;
use app\command\Base;
use think\console\Input;
use think\console\Output;
use think\facade\Env;

class Facebook extends Base
{


    protected function configure(): void
    {
        $this->setName('Facebook');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->days = 2;
        [$spend_table, $advertiser_ids] = $this->getSpendTable('facebook');
        SpendData::where('channel_name', 'facebook')->where('status', 1)->delete();

        // 拉数据
        $start_date = date("Y-m-d", strtotime("-{$this->days} days"));
        $end_date   = date("Y-m-d");
        $query      = [
            'access_token'   => Env::get('SPEND.FACEBOOK_TOKEN'),
            'fields'         => 'campaign_id,campaign_name,spend,impressions,clicks',
            'time_range'     => '{"since":"' . $start_date . '","until":"' . $end_date . '"}',
            'level'          => 'campaign',
            'time_increment' => '1',
            'breakdowns'     => 'country',
            'limit'          => '500'
        ];

        $insert_list = [];
        $results     = [];

        // 请求数据
        foreach ($advertiser_ids as $advertiser_id) {
            $url    = "https://graph.facebook.com/v21.0/act_{$advertiser_id}/insights";
            $result = $this->http('GET', $url, [
                'query' => $query,
            ]);
            if (empty($result['data'])) continue;
            $results = array_merge($results, $result['data']);
        }

        // 获取 campaign_id
        $campaign_ids    = array_unique(array_column($results, 'campaign_id'));
        $fb_creative_row = array_column(FbCreative::select()->toArray(), null, 'campaign_id');
        foreach ($campaign_ids as $campaign_id) {
            if (isset($fb_creative_row[$campaign_id])) continue;

            $url    = "https://graph.facebook.com/v21.0/$campaign_id/ads";
            $result = $this->http('GET', $url, [
                'query' => [
                    'access_token' => Env::get('SPEND.FACEBOOK_TOKEN'),
                    'fields'       => 'id,name,creative'
                ],
            ]);
            if (!isset($result['data'][0]['creative']['id'])) continue;

            $url    = "https://graph.facebook.com/v21.0/{$result['data'][0]['creative']['id']}/";
            $result = $this->http('GET', $url, [
                'query' => [
                    'access_token' => Env::get('SPEND.FACEBOOK_TOKEN'),
                    'fields'       => 'object_story_spec'
                ],
            ]);
            if (!isset($result['object_story_spec']['video_data']['call_to_action']['value']['link'])) continue;
            $appstore_id = explode('/', parse_url($result['object_story_spec']['video_data']['call_to_action']['value']['link'])['path']);
            $appstore_id = end($appstore_id);
            // 匹配包名
            $bundle_id = CYIosGame::where('appstore_url', 'like', "%$appstore_id%")->value('bundle_id');
            if (empty($bundle_id) || !isset($this->apps[$bundle_id])) continue;

            // 记录对应关系
            $row = ['campaign_id' => $campaign_id, 'app_id' => $this->apps[$bundle_id]['id']];
            FbCreative::create($row);
            $fb_creative_row[$campaign_id] = $row;
        }


        foreach ($results as $item) {
            if (!isset($fb_creative_row[$item['campaign_id']])) continue;

            $clicks      = $item['clicks'];
            $impressions = $item['impressions'];
            $spend       = $item['spend'];
            $cpc         = empty($impressions) ? 0 : $clicks / $impressions;
            $cpm         = empty($impressions) ? 0 : $spend / $impressions * 1000;

            if (empty($impressions) && (empty($country_code))) continue;

            $insert_list[] = [
                'app_id'        => $fb_creative_row[$item['campaign_id']]['app_id'],
                'channel_name'  => 'facebook',
                'domain_id'     => 0,
                'channel_id'    => 0,
                'is_app'        => 1,
                'date'          => $item['date_start'],
                'country_code'  => strtoupper($item['country']),
                'spend'         => $spend,
                'clicks'        => $clicks,
                'impressions'   => $impressions,
                'install'       => 0,
                'campaign_name' => $item['campaign_name'],
                'cpc'           => $cpc,
                'cpm'           => $cpm,
            ];
        }

        $this->saveSpendData($insert_list);

        SpendData::where('channel_name', 'facebook')->where('status', 0)->whereTime('date', '>=', date("Y-m-d", strtotime("-{$this->days} days")))->delete();
        SpendData::where('channel_name', 'facebook')->where('status', 1)->update(['status' => 0]);
    }

}
