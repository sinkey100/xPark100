<?php

namespace app\command;

use app\admin\model\xpark\Domain;
use app\admin\model\xpark\Data;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Env;

class Xpark extends Base
{

    protected array $domains = [];
    protected array $prefix = ['cy-'];

    protected function configure()
    {
        $this->setName('Xpark');
    }


    protected function execute(Input $input, Output $output)
    {
        $output->writeln(date("Y-m-d H:i:s") . ' 任务开始');
        // 清除老数据
        for ($i = 0; $i < 3; $i++) {

            Data::where('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
            // 重置自增id
            $maxId = Data::max('id');
            $maxId++;
            Db::execute("ALTER TABLE `ba_xpark_data` AUTO_INCREMENT={$maxId};");
        }
        $this->xpark365($output);
        $this->beesAds($output);
    }

    protected function beesAds(Output $output)
    {
        $pageSize = 100;
        $params   = [
            'headers' => [
                'x-apihub-ak'  => Env::get('BEESADS.TOKEN'),
                'x-apihub-env' => 'prod',
            ],
            'json'    => [
                'date_range' => [
                    'start' => date("Y-m-d", strtotime("-2 days")),
                    'end'   => date("Y-m-d")
                ],
                'dimensions' => [
                    'Date', 'Domain', 'Country', 'Zone'
                ],
                'sorts'      => [
                    'Date' => 1
                ],
                'page_index' => 1,
                'page_size'  => $pageSize
            ],
        ];

        // 获取total
        $params['json']['page_size'] = 1;
        $result                      = $this->http('POST',
            'https://api-us-east.eclicktech.com.cn/wgt/report/gamebridge/v1/ssp/report',
            $params
        );
        if (!empty($result['error'])) {
            $output->writeln(date("Y-m-d H:i:s") . ' BeesAd拉取数据错误');
            $output->writeln(json_encode($result));
            return;
        }
        if (empty($result['data']['total'])) {
            $output->writeln(date("Y-m-d H:i:s") . ' BeesAd拉取数据完成，没有返回数据');
            $output->writeln(json_encode($result));
            return;
        }
        $output->writeln(date("Y-m-d H:i:s") . ' BeesAd准备拉取' . $result['data']['total'] . '条数据');

        $pages = ceil($result['data']['total'] / $pageSize);
        // 批量拉取数据

        for ($page = 1; $page <= $pages; $page++) {
            sleep(5);
            $params['json']['page_index'] = $page;
            $params['json']['page_size']  = $pageSize;
            $result                       = $this->http('POST',
                'https://api-us-east.eclicktech.com.cn/wgt/report/gamebridge/v1/ssp/report',
                $params
            );
            $data                         = [];
            if (empty($result['data']['rows'])) {
                $output->writeln('BeesAds没有拉取到数据');
                return;
            }

            foreach ($result['data']['rows'] as $v) {
                $row    = [
                    'channel'         => 'BeesAds',
                    'sub_channel'     => $v['Domain'],
                    'domain_id'       => $this->getDomainId($v['Domain'], 'BeesAds'),
                    'a_date'          => $v['Date'],
                    'country_code'    => $v['Country'],
                    'ad_placement_id' => $v['Zone'],
                    'requests'        => $v['TotalAdRequests'],
                    'fills'           => $v['ResponseRate'] * $v['TotalAdRequests'],
                    'impressions'     => $v['Impressions'],
                    'clicks'          => $v['Clicks'],
                    'ad_revenue'      => $v['GrossRevenue'],

                    'gross_revenue' => $v['GrossRevenue'],
                    'net_revenue'   => $v['NetRevenue'],
                    'raw_cpc'       => $v['Cpc'],
                    'raw_ctr'       => $v['Ctr'],
                    'raw_ecpm'      => $v['ECpm']
                ];
                $data[] = $row;
            }
            $output->writeln(date("Y-m-d H:i:s") . ' BeesAds拉取数据完成，长度' . count($data));
            $this->saveData($data);
        }
    }

    protected function xpark365(Output $output)
    {
        $result = $this->http('POST', 'https://manage.xpark365.com/backend/gmf-manage/report/get_report', [
            'json'    => [
                'user_id'   => Env::get('XPARK.USER_ID'),
                'from_date' => date("Y-m-d", strtotime("-2 days")),
                'to_date'   => date("Y-m-d")
            ],
            'headers' => [
                'code' => Env::get('XPARK.CODE'),
            ]
        ]);

        if (!isset($result['data']['list'])) {
            $output->writeln(date("Y-m-d H:i:s") . ' xPark拉取数据完成，没有返回数据');
            $output->writeln(json_encode($result));
            return;
        }
        if (count($result['data']['list']) == 0) {
            $output->writeln(date("Y-m-d H:i:s") . ' xPark拉取数据完成，长度0');
            $output->writeln(json_encode($result));
            return;
        }

        $data = [];
        foreach ($result['data']['list'] as $item_day) {
            $csvRaw = file_get_contents($item_day['url']);
            [$fields, $csvData] = $this->csv_to_json($csvRaw);
            foreach ($csvData as &$v) {
                $v['channel']     = 'xpark365';
                $v['domain_id']   = $this->getDomainId($v['sub_channel'], $v['channel']);
                $v['sub_channel'] = str_replace($this->prefix, '', $v['sub_channel']);
                $v['gross_revenue'] = $v['ad_revenue'];
            }

            $data = array_merge($data, $csvData);
        }
        $output->writeln(date("Y-m-d H:i:s") . ' xPark拉取数据完成，长度' . count($data));
        $this->saveData($data);
    }

    protected function getDomainId($domain, $channel = ''): int
    {
        // 系统记录的域名列表
        if (count($this->domains) == 0) {
            $domains       = Domain::field(['id', 'domain', 'original_domain', 'rate'])->select()->toArray();
            $this->domains = array_column($domains, null, 'original_domain');
        }
        if (isset($this->domains[$domain])) {
            return $this->domains[$domain]['id'];
        }
        $item                   = Domain::create([
            'domain'          => str_replace($this->prefix, '', $domain),
            'original_domain' => $domain,
            'channel'         => $channel
        ]);
        $this->domains[$domain] = [
            'domain'          => $item->domain,
            'original_domain' => $item->original_domain,
            'id'              => $item->id
        ];
        return $item->id;
    }

    protected function saveData($data): void
    {
        foreach ($data as &$row) {
            if (
                !isset($this->domains[$row['sub_channel']])
                || !isset($this->domains[$row['sub_channel']]['rate'])
                || floatval($this->domains[$row['sub_channel']]['rate']) == 1
            ) continue;

            // 需要特殊处理
            $rate = floatval($this->domains[$row['sub_channel']]['rate']);
            // 备份数据
            $row['gross_revenue'] = $row['ad_revenue'];
            $row['ad_revenue'] = $row['ad_revenue'] * $rate;
        }

        Data::insertAll($data);
    }

}
