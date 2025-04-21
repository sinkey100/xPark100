<?php

namespace app\command\Spend;

use app\admin\model\cy\CYIosGame;
use app\admin\model\spend\Bind;
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

        $bind = Bind::field(['campaign_id', 'domain_name'])->where('platform', 'facebook')->order('date', 'desc')->group('campaign_id')->select();
        $bind = array_column($bind->toArray(), null, 'campaign_id');

        // 拉数据
        $start_date = date("Y-m-d", strtotime("-{$this->days} days"));
        $end_date   = date("Y-m-d");
        $query      = [
            'access_token'   => Env::get('SPEND.FACEBOOK_TOKEN'),
            'fields'         => 'campaign_id,campaign_name,spend,impressions,inline_link_clicks,actions',
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
        $campaign_ids = array_unique(array_column($results, 'campaign_id'));
        foreach ($campaign_ids as $campaign_id) {
            if (isset($bind[$campaign_id])) continue;

            $url    = "https://graph.facebook.com/v21.0/$campaign_id/ads";
            $result = $this->http('GET', $url, [
                'query' => [
                    'access_token' => Env::get('SPEND.FACEBOOK_TOKEN'),
                    'fields'       => 'id,name,creative'
                ],
            ]);
            if (!isset($result['data'][0]['creative']['id'])) continue;
            $action_link = null;
            foreach ($result['data'] as $v) {
                $url    = "https://graph.facebook.com/v21.0/{$v['creative']['id']}/";
                $result = $this->http('GET', $url, [
                    'query' => [
                        'access_token' => Env::get('SPEND.FACEBOOK_TOKEN'),
                        'fields'       => 'object_story_spec'
                    ],
                ]);
                if (isset($result['object_story_spec']['video_data']['call_to_action']['value']['link'])) {
                    $action_link = $result['object_story_spec']['video_data']['call_to_action']['value']['link'];
                    break;
                }
            }
            if (empty($action_link)) continue;
            // 匹配包名
            if (str_contains($action_link, 'itunes.apple')) {
                $appstore_id = explode('/', parse_url($action_link)['path']);
                $appstore_id = end($appstore_id);
                $bundle_id   = CYIosGame::where('appstore_url', 'like', "%$appstore_id%")->value('bundle_id');
                if (empty($bundle_id) || !isset($this->apps[$bundle_id])) continue;
                $bind[$campaign_id] = Bind::create([
                    'platform'    => 'facebook',
                    'campaign_id' => $campaign_id,
                    'app_id'      => $this->apps[$bundle_id]['id'],
                    'domain_id'   => 0,
                    'domain_name' => $bundle_id,
                    'date'        => date("Y-m-d")
                ]);

            } else {
                $domain_name = parse_url($action_link)['host'];
                if (!isset($this->domains[$domain_name])) continue;
                $bind[$campaign_id] = Bind::create([
                    'platform'    => 'facebook',
                    'campaign_id' => $campaign_id,
                    'app_id'      => $this->domains[$domain_name]['app_id'],
                    'domain_id'   => $this->domains[$domain_name]['id'],
                    'domain_name' => $domain_name,
                    'date'        => date("Y-m-d")
                ]);
            }
        }


        foreach ($results as $item) {
            if (!isset($bind[$item['campaign_id']])) continue;

            $clicks      = $item['inline_link_clicks'] ?? 0;
            $actions     = array_column($item['actions'] ?? [], null, 'action_type');
            $impressions = $item['impressions'] ?? 0;
            $spend       = $item['spend'];
            $cpc         = empty($impressions) ? 0 : $clicks / $impressions;
            $cpm         = empty($impressions) ? 0 : $spend / $impressions * 1000;

            if (empty($impressions) && (empty($country_code))) continue;

            $domain_info = $this->domains[$bind[$item['campaign_id']]['domain_name']] ?? false;
            if (!$domain_info) continue;

            $insert_list[] = [
                'app_id'        => $domain_info['app_id'],
                'channel_name'  => 'facebook',
                'domain_id'     => $domain_info['is_app'] == 1 ? 0 : $domain_info['id'],
                'channel_id'    => $domain_info['is_app'] == 1 ? 0 : $domain_info['channel_id'],
                'is_app'        => $domain_info['is_app'],
                'date'          => $item['date_start'],
                'country_code'  => strtoupper($item['country']),
                'spend'         => $spend,
                'clicks'        => $clicks,
                'impressions'   => $impressions,
                'conversion'    => $actions['add_to_wishlist']['value'] ?? 0,
                'install'       => $actions['mobile_app_install']['value'] ?? 0,
                'campaign_name' => $item['campaign_name'],
                'campaign_id'   => $item['campaign_id'],
                'cpc'           => $cpc,
                'cpm'           => $cpm,
            ];
        }

        $this->saveSpendData($insert_list);

        SpendData::where('channel_name', 'facebook')->where('status', 0)->whereTime('date', '>=', date("Y-m-d", strtotime("-{$this->days} days")))->delete();
        SpendData::where('channel_name', 'facebook')->where('status', 1)->update(['status' => 0]);
    }

}
