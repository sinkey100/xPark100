<?php

namespace app\command\Spend;

use app\admin\model\spend\Data as SpendData;
use app\admin\model\xpark\Domain;
use app\command\Base;
use think\console\Input;
use think\console\Output;
use think\facade\Env;

class AppLovin extends Base
{


    protected function configure(): void
    {
        $this->setName('AppLovin');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->days = 2;
        [$spend_table, $advertiser_ids] = $this->getSpendTable('applovin');
        SpendData::where('channel_name', 'applovin')->where('status', 1)->delete();

        // 拉数据
        $url   = 'https://r.applovin.com/report';
        $query = [
            'api_key'     => Env::get('SPEND.APPLOVIN_API_KEY'),
            'start'       => date("Y-m-d", strtotime("-{$this->days} days")),
            'end'         => date("Y-m-d"),
            'format'      => 'json',
            'columns'     => 'ad_id,day,campaign,country,impressions,clicks,campaign_package_name,cost',
            'report_type' => 'advertiser'
        ];

        $insert_list = [];


        $result = $this->http('GET', $url, [
            'query' => $query,
        ]);
        if ($result['code'] != 200) {
            $this->log('请求失败');
            $this->log(json_encode($result));
        };

        foreach ($result['results'] as $item) {
            if (!isset($this->apps[$item['campaign_package_name']])) continue;

            $clicks      = $item['clicks'];
            $impressions = $item['impressions'];
            $spend       = $item['cost'];
            $cpc         = empty($impressions) ? 0 : $clicks / $impressions;
            $cpm         = empty($impressions) ? 0 : $spend / $impressions * 1000;

            if (empty($impressions) && (empty($country_code))) continue;

            $insert_list[] = [
                'app_id'        => $this->apps[$item['campaign_package_name']]['id'],
                'channel_name'  => 'applovin',
                'domain_id'     => 0,
                'channel_id'    => 0,
                'is_app'        => 1,
                'date'          => $item['day'],
                'country_code'  => strtoupper($item['country']),
                'spend'         => $spend,
                'clicks'        => $clicks,
                'impressions'   => $impressions,
                'install'       => 0,
                'campaign_name' => $item['campaign'],
                'cpc'           => $cpc,
                'cpm'           => $cpm,
            ];
        }


        $this->saveSpendData($insert_list);

        SpendData::where('channel_name', 'applovin')->where('status', 0)->whereTime('date', '>=', date("Y-m-d", strtotime("-{$this->days} days")))->delete();
        SpendData::where('channel_name', 'applovin')->where('status', 1)->update(['status' => 0]);
    }

}
