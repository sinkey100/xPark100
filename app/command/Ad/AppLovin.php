<?php

namespace app\command\Ad;

use app\admin\model\xpark\Channel;
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

class AppLovin extends Base
{

    protected array $country_data = [];

    protected function configure(): void
    {
        $this->setName('AppLovin');
    }

    protected function execute(Input $input, Output $output): void
    {

        $this->log("\n\n======== AppLovin 开始拉取数据 ========", false);
        $this->country_data = array_column(get_country_data(), null, 'code');


        Data::where('channel', 'AppLovin')->where('status', 1)->delete();

        $this->log("任务开始，拉取 {$this->days} 天");
        $this->log('开始拉取 AppLovin 数据');
        try {
            $this->pull();
        } catch (Exception $e) {
            $this->log("[{$e->getLine()}|{$e->getFile()}]{$e->getMessage()}");
            print_r($e->getTraceAsString());
            $this->log('======== AppLovin 拉取数据失败 ========', false);
            return;
        }

        for ($i = 0; $i < $this->days; $i++) {
            Data::where('channel', 'AppLovin')->where('status', 0)->where('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
            Data::where('channel', 'AppLovin')->where('status', 1)->where('a_date', date("Y-m-d", strtotime("-$i days")))->update([
                'status' => 0
            ]);
        }
        $this->log('历史数据已删除');

        $this->log('======== AppLovin 拉取数据完成 ========', false);
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
        $ad_domains = Domain::where('channel', 'AppLovin')->select()->toArray();
        $ad_domains = array_column($ad_domains, 'domain');
        $days       = $this->days - 1;
        // 获取通道数量
        $applovin_channels = Channel::where('channel_type', 'AppLovin')->select()->toArray();

        foreach ($applovin_channels as $applovin_channel) {
            $result = $this->http('GET', 'https://r.applovin.com/maxReport', [
                'query' => [
                    'api_key' => Env::get('APPLOVIN.REPORT_KEY_' . $applovin_channel['id']),
                    'columns' => implode(",", [
                        'attempts', 'package_name', 'day', 'country', 'max_ad_unit_id',
                        'responses', 'impressions', 'estimated_revenue', 'ecpm', 'network'
                    ]),
                    "start"   => date("Y-m-d", strtotime("-$days days")),
                    "end"     => date("Y-m-d"),
                    'format'  => 'json'
                ]
            ]);

            if (!(isset($result['code']) && $result['code'] == 200)) {
                $this->log('拉取数据错误');
                $this->log(json_encode($result));
                return;
            }

            if (count($result['results']) == 0) {
                $this->log('拉取数据完成，长度0');
                $this->log(json_encode($result));
                return;
            }
            $this->log('拉取数据完成，长度' . count($result['results']));
            $saveData    = [];
            foreach ($result['results'] as $v) {
                if (!in_array($v['package_name'], $ad_domains)) continue;

                [$domain_id, $app_id] = $this->getDomainRow($v['package_name'], $v['day'], 'AppLovin');

                $saveData[] = [
                    'channel'         => 'AppLovin',
                    'channel_full'    => $applovin_channel['channel_alias'],
                    'channel_id'      => $applovin_channel['id'],
                    'channel_type'    => 1,
                    'sub_channel'     => $v['package_name'],
                    'domain_id'       => $domain_id,
                    'app_id'          => $app_id,
                    'a_date'          => $v['day'],
                    'country_code'    => strtoupper($v['country']),
                    'ad_placement_id' => $v['max_ad_unit_id'],
                    'requests'        => $v['attempts'],
                    'fills'           => $v['responses'],
                    'impressions'     => $v['impressions'],
                    'clicks'          => 0,
                    'ad_revenue'      => $v['estimated_revenue'],
                    'gross_revenue'   => $v['estimated_revenue'],
                    'net_revenue'     => $v['estimated_revenue'],
                    'raw_cpc'         => 0,
                    'raw_ctr'         => 0,
                    'raw_ecpm'        => $v['ecpm']
                ];

            }

            $this->log('准备存储数据，长度' . count($saveData));
            $this->saveData($saveData);
        }

    }

}
