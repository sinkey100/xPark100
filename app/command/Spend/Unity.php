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
        [$spend_table, $advertiser_ids, $account_keys] = $this->getSpendTable('unity');

        SpendData::where('channel_name', 'unity')->where('status', 1)->delete();

        // 拉数据
        $resultData = [];
        foreach ($advertiser_ids as $k => $advertiser_id) {
            sleep(5);
            $organization = $advertiser_id;
            $account_key  = $account_keys[$k];

            $url     = "https://services.api.unity.com/advertise/stats/v2/organizations/$organization/reports/acquisitions";
            $query   = [
                'start'      => date("Y-m-d", strtotime("-{$this->days} days")) . 'T00:00:00.000Z',
                'end'        => date("Y-m-d") . 'T23:59:59.000Z',
                'scale'      => 'day',
                'metrics'    => 'starts,views,clicks,installs,cpi,spend',
                'breakdowns' => 'campaign,country,app'
            ];
            $headers = [
                'Authorization' => "Basic $account_key"
            ];

            $result = $this->http('GET', $url, [
                'query'   => $query,
                'headers' => $headers
            ], true);

            [$fields, $csvData] = $this->csv2json($result);
            $resultData = array_merge($resultData, $csvData);
        }

        $insert_list = [];

        foreach ($resultData as $item) {
            $app = $this->appName2App($item['app name']);
            if (!$app) continue;

            $clicks      = $item['clicks'];
            $impressions = $item['views'];
            $spend       = $item['spend'];
            $cpc         = empty($impressions) ? 0 : $clicks / $impressions;
            $cpm         = empty($impressions) ? 0 : $spend / $impressions * 1000;

            if (empty($impressions) && (empty($country_code))) continue;

            $insert_list[] = [
                'app_id'        => $app['id'],
                'channel_name'  => 'unity',
                'domain_id'     => 0,
                'channel_id'    => 0,
                'is_app'        => 1,
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


        $this->saveSpendData($insert_list);
        SpendData::where('channel_name', 'unity')->where('status', 0)->whereTime('date', '>=', date("Y-m-d", strtotime("-{$this->days} days")))->delete();
        SpendData::where('channel_name', 'unity')->where('status', 1)->update(['status' => 0]);
    }

}
