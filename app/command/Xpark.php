<?php

namespace app\command;

use app\admin\model\xpark\DomainRate;
use Exception;
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
    protected array $insertData = [];
    protected array $dateRate = [];

    protected function configure()
    {
        $this->setName('Xpark');
    }


    protected function execute(Input $input, Output $output)
    {
        $output->writeln(date("Y-m-d H:i:s") . ' 任务开始');

        for ($i = 0; $i < 3; $i++) {
            $date                  = date("Y-m-d", strtotime("-$i days"));
            $tmp                   = DomainRate::where('date', $date)->select()->toArray();
            $this->dateRate[$date] = array_column($tmp, null, 'domain');
        }

        $xpark365 = $this->xpark365($output);
        $BeesAds  = $this->beesAds($output);

        $output->writeln(date("Y-m-d H:i:s") . ' 准备删除历史数据');
        if (count($xpark365) > 0) {
            for ($i = 0; $i < 3; $i++) {
                Data::where('channel', 'xPark365')->where('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
            }
            $output->writeln(date("Y-m-d H:i:s") . ' xPark历史数据已删除');

        }
        if (count($BeesAds) > 0) {
            for ($i = 0; $i < 3; $i++) {
                Data::where('channel', 'BeesAds')->where('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
            }
            $output->writeln(date("Y-m-d H:i:s") . ' BeesAds历史数据已删除');
        }

        $maxId = Data::max('id');
        $maxId++;
        Db::execute("ALTER TABLE `ba_xpark_data` AUTO_INCREMENT={$maxId};");
        $output->writeln(date("Y-m-d H:i:s") . ' 索引ID已重建');

        if (count($xpark365) > 0) {
            $output->writeln(date("Y-m-d H:i:s") . ' 准备保存xPark数据');
            $this->saveData($xpark365);

        }
        if (count($BeesAds) > 0) {
            $output->writeln(date("Y-m-d H:i:s") . ' 准备保存BeesAds数据');
            $this->saveData($BeesAds);
        }
        $output->writeln(date("Y-m-d H:i:s") . " 保存成功\n\n");
    }

    protected function beesAds(Output $output)
    {
        $returnRows = [];
        $pageSize   = 1000;
        $params     = [
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
        try {
            $result = $this->http('POST',
                'https://api-us-east.eclicktech.com.cn/wgt/report/gamebridge/v1/ssp/report',
                $params
            );
        } catch (Exception $e) {
            $output->writeln(date("Y-m-d H:i:s") . ' BeesAd请求出错: ' . $e->getMessage());
            return [];
        }
        if (!empty($result['error'])) {
            $output->writeln(date("Y-m-d H:i:s") . ' BeesAd拉取数据错误');
            $output->writeln(json_encode($result));
            return [];
        }
        if (empty($result['data']['total'])) {
            $output->writeln(date("Y-m-d H:i:s") . ' BeesAd拉取数据完成，没有返回数据');
            $output->writeln(json_encode($result));
            return [];
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
                return [];
            }

            foreach ($result['data']['rows'] as $v) {
                [$domain_id, $app_id] = $this->getDomainId($v['Domain'], 'BeesAds');
                $row    = [
                    'channel'         => 'BeesAds',
                    'sub_channel'     => $v['Domain'],
                    'domain_id'       => $domain_id,
                    'app_id'          => $app_id,
                    'a_date'          => $v['Date'],
                    'country_code'    => $v['Country'],
                    'ad_placement_id' => $v['Zone'],
                    'requests'        => $v['TotalAdRequests'],
                    'fills'           => (float)$v['ResponseRate'] * (float)$v['TotalAdRequests'],
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
            $returnRows = array_merge($returnRows, $data);
        }
        return $returnRows;
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
            return [];
        }
        if (count($result['data']['list']) == 0) {
            $output->writeln(date("Y-m-d H:i:s") . ' xPark拉取数据完成，长度0');
            $output->writeln(json_encode($result));
            return [];
        }

        $data = [];
        foreach ($result['data']['list'] as $item_day) {
            $csvRaw = file_get_contents($item_day['url']);
            [$fields, $csvData] = $this->csv_to_json($csvRaw);
            foreach ($csvData as &$v) {
                [$domain_id, $app_id] = $this->getDomainId($v['Domain'], 'xPark365');
                $v['channel']       = 'xPark365';
                $v['domain_id']     = $domain_id;
                $v['app_id']        = $app_id;
                $v['sub_channel']   = str_replace($this->prefix, '', $v['sub_channel']);
                $v['gross_revenue'] = $v['ad_revenue'];
            }

            $data = array_merge($data, $csvData);
        }
        $output->writeln(date("Y-m-d H:i:s") . ' xPark拉取数据完成，长度' . count($data));
        return $data;
    }

    protected function getDomainId($domain, $channel = ''): int
    {
        // 系统记录的域名列表
        if (count($this->domains) == 0) {
            $domains       = Domain::field(['id', 'domain', 'original_domain', 'rate', 'app_id'])->select()->toArray();
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
        return [$item->id, $item->app_id];
    }

    protected function saveData($data): void
    {
        $fields     = [
            'domain_id', 'channel', 'a_date', 'country_code', 'sub_channel', 'ad_placement_id', 'requests', 'fills',
            'impressions', 'clicks', 'ad_revenue', 'user_id', 'raw_cpc', 'raw_ctr', 'raw_ecpm', 'net_revenue', 'gross_revenue'
        ];
        $insertData = [];
        foreach ($data as $row) {
            $v = [];
            foreach ($fields as $field) {
                $v[$field] = $row[$field] ?? null;
                // 需要特殊处理
                $date = date("Y-m-d", strtotime($row['a_date']));

                if (!isset($this->dateRate[$date][$row['sub_channel']])) {
                    // 插入表
                    $rate                                       = Domain::where('domain', $row['sub_channel'])->value('rate', 1);
                    $this->dateRate[$date][$row['sub_channel']] = DomainRate::create([
                        'domain' => $row['sub_channel'],
                        'date'   => $date,
                        'rate'   => $rate
                    ]);

                } else {
                    $rate = floatval($this->dateRate[$date][$row['sub_channel']]['rate']);
                }

                // 备份数据
                $v['gross_revenue'] = $row['ad_revenue'];
                $v['ad_revenue']    = $row['ad_revenue'] * $rate;
            }
            ksort($v);
            $insertData[] = $v;
        }
        Data::insertAll($insertData);
    }

}
