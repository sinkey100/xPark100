<?php

namespace app\command\Spend;

use app\admin\model\spend\Data as SpendData;
use app\admin\model\xpark\Domain;
use app\command\Base;
use think\console\Input;
use think\console\Output;
use think\facade\Env;

class Unity extends Base
{


    protected function configure(): void
    {
        $this->setName('Unity');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->days = 2;
        [$spend_table, $advertiser_ids] = $this->getSpendTable('unity');
        SpendData::where('channel_name', 'unity')->where('status', 1)->delete();

        // 拉数据
        $organization = Env::get('SPEND.UNITY_ORGANIZATION_ID');
        $key_id       = Env::get('SPEND.UNITY_KEY_ID');
        $secret_key   = Env::get('SPEND.UNITY_SECRET_KEY');

        $url     = "https://services.api.unity.com/advertise/stats/v2/organizations/$organization/reports/acquisitions";
        $query   = [
            'start'      => '',
            'end'        => '',
            'scale'      => 'day',
            'metrics'    => 'starts,views,clicks,installs,cpi,spend',
            'breakdowns' => 'campaign,country,app'
        ];
        $headers = [
            'Authorization' => "Basic $key_id:$secret_key"
        ];

        $insert_list = [];
        for ($i = $this->days - 1; $i >= 0; $i--) {
            sleep(5);
            $date           = date("Y-m-d", strtotime("-{$i} days"));
            $query['start'] = $date . 'T00:00:00.000Z';
            $query['end']   = $date . 'T23:59:59.000Z';


            $result = $this->http('GET', $url, [
                'query'   => $query,
                'headers' => $headers
            ], true);

            [$fields, $csvData] = $this->csv2json($result);

            foreach ($csvData as $item) {
                $table_row = $this->campaignToDomain($item['campaign name'], $spend_table);
                if (!$table_row) continue;

                $clicks      = $item['clicks'];
                $impressions = $item['views'];
                $spend       = $item['spend'];
                $cpc         = empty($impressions) ? 0 : $clicks / $impressions;
                $cpm         = empty($impressions) ? 0 : $spend / $impressions * 1000;

                if (empty($impressions) && (empty($country_code))) continue;

                $insert_list[] = [
                    'app_id'        => $table_row['app_id'],
                    'channel_name'  => 'unity',
                    'domain_id'     => $table_row['domain_id'],
                    'channel_id'    => $table_row['channel_id'],
                    'is_app'        => $table_row['domain_or_app'],
                    'date'          => $item['timestamp'],
                    'country_code'  => $item['country'],
                    'spend'         => $spend,
                    'clicks'        => $clicks,
                    'starts'        => $item['starts'],
                    'impressions'   => $impressions,
                    'install'       => $item['installs'],
                    'campaign_name' => $item['campaign name'],
                    'cpc'           => $cpc,
                    'cpm'           => $cpm,
                    'cpi'           => $item['cpi']
                ];
            }
        }

        $this->saveSpendData($insert_list);

        for ($i = $this->days - 1; $i >= 0; $i--) {
            $date = date("Y-m-d", strtotime("-{$i} days"));
            SpendData::where('channel_name', 'unity')->where('status', 0)->where('date', $date)->delete();
        }
        SpendData::where('channel_name', 'unity')->where('status', 1)->update(['status' => 0]);
    }

}
