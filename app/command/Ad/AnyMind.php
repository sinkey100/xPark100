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

class AnyMind extends Base
{

    protected function configure()
    {
        $this->setName('AnyMind');
    }

    protected function execute(Input $input, Output $output): void
    {

        $this->log("\n\n======== AnyMind 开始拉取数据 ========", false);
        $this->log("任务开始，拉取 {$this->days} 天");

        for ($i = 0; $i < $this->days; $i++) {
            Data::where('channel', 'AnyMind')->where('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
        }
        $this->log('历史数据已删除');
        $this->log('开始拉取 AnyMind 数据');
        try {
            $this->pull();
        } catch (Exception $e) {
            $this->log("[{$e->getLine()}|{$e->getFile()}]{$e->getMessage()}");
            print_r($e->getTraceAsString());
            $this->log('======== AnyMind 拉取数据失败 ========', false);
            return;
        }

        $this->log('======== AnyMind 拉取数据完成 ========', false);
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
        $ad_domains = Domain::where('channel', 'AnyMind')->select()->toArray();
        $ad_domains = array_column($ad_domains, 'domain');


        $token = $this->http('POST', 'https://api.app.anymanager.io/api/v1/obtain_token/', [
            'json' => [
                'email'    => Env::get('ANYMIND.EMAIL'),
                'password' => Env::get('ANYMIND.PASSWORD'),
            ],
        ]);
        if (!isset($token['token'])) throw new Exception('Token获取失败');


        $days = $this->days - 1;

        $result = $this->http('POST', 'https://api.app.anymanager.io/api/v1/report/report/', [
            'json'    => [
                "limit"       => 10000,
                "start_date"  => date("Y-m-d", strtotime("-$days days")),
                "end_date"    => date("Y-m-d"),
                "report_type" => "web_report",
                "dimension"   => "date,site_app,ad_unit_name",
                "metric"      => "imp,fill_rate,publisher_revenue,click,publisher_cpc,ctr,publisher_cpm,total_ad_request"
            ],
            'headers' => [
                'Authorization' => "JWT {$token['token']}",
            ]
        ]);

        if (!isset($result['results'])) {
            $this->log('拉取数据错误');
            $this->log(json_encode($result));
            return;
        }
        if (count($result['results']) == 0) {
            $this->log('拉取数据完成，长度0');
            $this->log(json_encode($result));
            return;
        }
        $saveData = [];
        foreach ($result['results'] as $v) {
            if (!in_array($v['site_app'], $ad_domains)) continue;

            [$domain_id, $app_id] = $this->getDomainRow($v['site_app'], $v['date'], 'AnyMind');

            $saveData[] = [
                'channel'         => 'AnyMind',
                'channel_full'    => 'AnyMind',
                'sub_channel'     => $v['site_app'],
                'domain_id'       => $domain_id,
                'app_id'          => $app_id,
                'a_date'          => $v['date'],
                'country_code'    => '',
                'ad_placement_id' => $v['ad_unit_name'],
                'requests'        => $v['total_ad_request'],
                'fills'           => $v['total_ad_request'] * $v['fill_rate'],
                'impressions'     => $v['imp'],
                'clicks'          => $v['click'],
                'ad_revenue'      => $v['publisher_revenue'],
                'gross_revenue'   => $v['publisher_revenue'],
                'net_revenue'     => $v['publisher_revenue'],
                'raw_cpc'         => $v['publisher_cpc'],
                'raw_ctr'         => $v['ctr'],
                'raw_ecpm'        => $v['publisher_cpm']
            ];
        }

        $this->saveData($saveData);

    }

}
