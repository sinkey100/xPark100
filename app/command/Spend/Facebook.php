<?php

namespace app\command\Spend;

use app\admin\model\spend\Data as SpendData;
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
        $url   = 'https://graph.facebook.com/v21.0/act_903977011846146/insights';
        $query = [
            'access_token'   => Env::get('SPEND.FACEBOOK_TOKEN'),
            'fields'         => 'campaign_id,campaign_name,spend,impressions,clicks',
            'time_range'     => '',
            'level'          => 'campaign',
            'time_increment' => '1',
            'breakdowns'     => 'country',
            'limit'          => '500'
        ];

        $insert_list = [];

        for ($i = $this->days - 1; $i >= 0; $i--) {
            $date                = date("Y-m-d", strtotime("-{$i} days"));
            $query['time_range'] = '{"since":"' . $date . '","until":"' . $date . '"}';

            $result = $this->http('GET', $url, [
                'query' => $query,
            ]);
            if (empty($result['data'])) continue;

            foreach ($result['data'] as $item) {
                $table_row = $this->campaignToDomain($item['campaign_name'], $spend_table);
                if (!$table_row) continue;

                $clicks      = $item['clicks'];
                $impressions = $item['impressions'];
                $spend       = $item['spend'];
                $cpc         = empty($impressions) ? 0 : $clicks / $impressions;
                $cpm         = empty($impressions) ? 0 : $spend / $impressions * 1000;

                if (empty($impressions) && (empty($country_code))) continue;

                $insert_list[] = [
                    'app_id'        => $table_row['app_id'],
                    'channel_name'  => 'facebook',
                    'domain_id'     => $table_row['domain_id'],
                    'channel_id'    => $table_row['channel_id'],
                    'is_app'        => $table_row['domain_or_app'],
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
        }

        $this->saveSpendData($insert_list);

        for ($i = $this->days - 1; $i >= 0; $i--) {
            $date = date("Y-m-d", strtotime("-{$i} days"));
            SpendData::where('channel_name', 'facebook')->where('status', 0)->where('date', $date)->delete();
        }
        SpendData::where('channel_name', 'facebook')->where('status', 1)->update(['status' => 0]);
    }

}
