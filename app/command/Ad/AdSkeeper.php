<?php

namespace app\command\Ad;

use app\admin\model\xpark\Data;
use app\admin\model\xpark\Domain;
use app\command\Base;
use GuzzleHttp\Exception\GuzzleException;
use think\console\Input;
use think\console\Output;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use Exception;
use think\facade\Env;

class AdSkeeper extends Base
{

    protected function configure()
    {
        $this->setName('AdSkeeper');
    }

    protected function execute(Input $input, Output $output): void
    {

        $this->log("\n\n======== AdSkeeper 开始拉取数据 ========", false);
        $this->log("任务开始，拉取 {$this->days} 天");

        $this->log('开始拉取 AdSkeeper 数据');
        try {
            $this->pull();
        } catch (Exception $e) {
            $this->log("[{$e->getLine()}|{$e->getFile()}]{$e->getMessage()}");
            print_r($e->getTraceAsString());
            $this->log('======== AdSkeeper 拉取数据失败 ========', false);
            return;
        }

        for ($i = 0; $i < $this->days; $i++) {
            Data::where('channel', 'AdSkeeper')->where('status', 0)->where('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
            Data::where('channel', 'AdSkeeper')->where('status', 1)->where('a_date', date("Y-m-d", strtotime("-$i days")))->update([
                'status' => 0
            ]);
        }
        $this->log('历史数据已删除');

        $this->log('======== AdSkeeper 拉取数据完成 ========', false);
    }

    /**
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception|GuzzleException
     */
    protected function pull(): void
    {
        // 获取账号和域名
        $ad_domains = Domain::where('channel', 'AdSkeeper')->select()->toArray();
        $ad_domains = array_column($ad_domains, 'domain');

        $client_id = Env::get('ADSKEEPER.CLIENT_ID');
        $token     = Env::get('ADSKEEPER.TOKEN');
        $days      = $this->days - 1;

        $result = $this->http('GET', "https://api.adskeeper.co.uk/v1/publishers/$client_id/widget-custom-report", [
            'json'    => [
                "dateInterval" => "interval",
                "startDate"    => date("Y-m-d", strtotime("-$days days")),
                "endDate"      => date("Y-m-d", strtotime('-1 days')),
                "dimensions"   => "date,domain,countryIso,widgetName",
                "metrics"      => "adRequests,impressions,visibilityRate,wages,clicks,eCpm,cpc,ctr"
            ],
            'headers' => [
                'Authorization' => "Bearer $token",
            ]
        ]);

        if (isset($result['errors'])) {
            $this->log('拉取数据错误');
            $this->log(json_encode($result));
            return;
        }
        if (count($result) == 0) {
            $this->log('拉取数据完成，长度0');
            $this->log(json_encode($result));
            return;
        }
        $saveData = [];
        foreach ($result as $v) {
            if (!isset($v['widgetName'])) continue;
            $domain_name = explode('_', $v['widgetName'])[0];
            if (!in_array($domain_name, $ad_domains)) continue;

            [$domain_id, $app_id] = $this->getDomainRow($domain_name, $v['date'], 'AdSkeeper');
            $channel_full = 'AdSkeeper';

            $saveData[] = [
                'channel'         => 'AdSkeeper',
                'channel_full'    => $channel_full,
                'channel_id'      => $this->channelList[$channel_full]['id'] ?? 0,
                'channel_type'    => ($this->channelList[$channel_full]['ad_type'] ?? 'H5') == 'H5' ? 0 : 1,
                'sub_channel'     => $domain_name,
                'domain_id'       => $domain_id,
                'app_id'          => $app_id,
                'a_date'          => $v['date'],
                'country_code'    => $v['countryIso'],
                'ad_placement_id' => $v['widgetName'],
                'requests'        => $v['adRequests'],
                'fills'           => $v['adRequests'] * $v['visibilityRate'] / 100,
                'impressions'     => $v['impressions'],
                'clicks'          => $v['clicks'],
                'ad_revenue'      => $v['wages'],
                'gross_revenue'   => $v['wages'],
                'net_revenue'     => $v['wages'],
                'raw_cpc'         => $v['cpc'],
                'raw_ctr'         => $v['ctr'] / 100,
                'raw_ecpm'        => $v['eCpm']
            ];
        }
        $this->saveData($saveData);

    }

}
