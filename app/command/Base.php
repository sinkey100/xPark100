<?php

namespace app\command;

use app\admin\model\xpark\Apps;
use app\admin\model\xpark\Channel;
use app\admin\model\xpark\Data;
use app\admin\model\xpark\Domain;
use app\admin\model\xpark\DomainRate;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use app\admin\model\spend\Data as SpendData;
use sdk\FeishuBot;
use think\console\Command;
use think\console\Output;
use DateTime;
use ClickHouseDB\Client as ClickHouseDB;
use think\facade\Config;
use think\facade\Env;

class Base extends Command
{

    protected array        $domains     = [];
    protected array        $apps        = [];
    protected array        $dateRate    = [];
    protected int          $days        = 3;
    protected array        $prefix      = ['cy-'];
    protected array        $channelList = [];
    protected ClickHouseDB $clickhouse;

    public function __construct()
    {
        parent::__construct();
        $this->channelList = Channel::field(['id', 'channel_alias', 'ad_type'])->select()->toArray();
        $this->channelList = array_column($this->channelList, null, 'channel_alias');
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function http(string $method, string $url, array $options = [], bool $raw = false): array|string
    {
        $client = new Client([
            'verify' => false
        ]);
        try {
            $result = $client->request($method, $url, $options);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        if ($result->getStatusCode() != 200) {
            throw new Exception('请求失败: ' . $result->getBody()->getContents());
        }
        $result = $result->getBody()->getContents();
        return $raw ? $result : json_decode($result, true);
    }

    protected function log(string $text, $time = true): void
    {
        if ($time) $text = date("Y-m-d H:i:s") . '  ' . $text;
        $this->output->writeln($text);
    }

    protected function getPeriods($totalDays, $daysPerPeriod): array
    {
        $periods      = [];
        $currentStart = 0;

        while ($currentStart <= $totalDays) {
            // 计算当前周期的结束日期
            $currentEnd = min($currentStart + $daysPerPeriod - 1, $totalDays);

            // 保存当前周期的开始日期和结束日期
            $periods[] = [
                date("Y-m-d", strtotime("-{$currentEnd} days")),
                date("Y-m-d", strtotime("-{$currentStart} days"))
            ];

            // 更新下一个周期的开始日期
            $currentStart = $currentEnd + 1;
        }

        return array_reverse($periods);
    }

    protected function csv2json($csv_string): array
    {
        $csv_string = str_replace(["\xEF\xBB\xBF", '/\x{FE FF}/u'], '', $csv_string);
        // 将CSV字符串按行分割
        $lines = explode("\n", $csv_string);

        // 使用第一行作为标题
        $header = str_getcsv(array_shift($lines));

        // 读取剩余行并转换为关联数组
        $data = [];
        foreach ($lines as $line) {
            if (trim($line) == '') {
                continue;
            }
            $row    = str_getcsv($line);
            $data[] = array_combine($header, $row);
        }

        // 将数据转换为JSON对象
        // $json_data = json_encode($data, JSON_PRETTY_PRINT);

        return [$header, $data];
    }

    protected function getDomainRow($original_domain, $date, $channel = ''): array
    {
        //domain_id
        $domain     = str_replace($this->prefix, '', $original_domain);
        $domain_row = Domain::where('original_domain', $original_domain)->where('channel', $channel)->find();
        if (!$domain_row) {
            $domain_row = Domain::create([
                'domain'          => $domain,
                'original_domain' => $original_domain,
                'channel'         => $channel,
                'app_id'          => null,
            ]);
        }
        //app_id
        $app_id = DomainRate::where('domain', $domain)->where('date', $date)->value('app_id', $domain_row->app_id);
        return [$domain_row->id, $app_id];
    }

    protected function saveData($data): void
    {
        if (count($this->dateRate) == 0) {
            for ($i = 0; $i < $this->days; $i++) {
                $date                  = date("Y-m-d", strtotime("-$i days"));
                $tmp                   = DomainRate::where('date', $date)->select()->toArray();
                $this->dateRate[$date] = array_column($tmp, null, 'domain');
            }
        }

        $country_data = array_column(get_country_data(), null, 'code');

        $insertData = [];
        foreach ($data as $v) {
            // 地区
            if (in_array(strtolower($v['country_code']), ['n / a', 'none'])) $v['country_code'] = '';
            $v['country_name']  = $country_data[$v['country_code']]['name'] ?? '';
            $v['country_level'] = $country_data[$v['country_code']]['level'] ?? '';
            $v['country_code']  = substr($v['country_code'], 0, 2);

            // 需要特殊处理
            $v['a_date'] = $_date = date("Y-m-d", strtotime($v['a_date']));
            $_domain     = $v['sub_channel'];

            if (!isset($this->dateRate[$_date][$_domain])) {
                // 插入表
                $domain_info                      = Domain::where('domain', $_domain)->find();
                $rate                             = $domain_info['rate'] ?: 1;
                $this->dateRate[$_date][$_domain] = DomainRate::create([
                    'domain' => $_domain,
                    'date'   => $_date,
                    'rate'   => $rate,
                    'app_id' => $domain_info['app_id']
                ]);
            } else {
                $rate = floatval($this->dateRate[$_date][$_domain]['rate']);
            }

            // 备份数据
            $v['gross_revenue'] = $v['ad_revenue'];
            $v['ad_revenue']    = $v['ad_revenue'] * $rate;

            $v['status']  = 1;
            $insertData[] = $v;
        }
        $chunks = array_chunk($insertData, 100);
        foreach ($chunks as $chunk) {
            Data::insertAll($chunk);
        }
    }

    protected function saveSpendData($data): void
    {
        $country_data = array_column(get_country_data(), null, 'code');

        $insertData = [];
        foreach ($data as $v) {
            // 地区
            if (in_array(strtolower($v['country_code']), ['n / a', 'none'])) $v['country_code'] = '';
            $v['country_name']  = $country_data[$v['country_code']]['name'] ?? '';
            $v['country_level'] = $country_data[$v['country_code']]['level'] ?? '';
            $v['country_code']  = substr($v['country_code'], 0, 2);

            // 需要特殊处理
            $v['date']    = date("Y-m-d", strtotime($v['date']));
            $v['status']  = 1;
            $insertData[] = $v;
        }
        $chunks = array_chunk($insertData, 100);
        foreach ($chunks as $chunk) {
            SpendData::insertAll($chunk);
        }
    }

    protected function getMailContent(&$inbox, &$email_uid): string
    {
        $message   = '';
        $structure = imap_fetchstructure($inbox, $email_uid, FT_UID);
        if (isset($structure->parts) && count($structure->parts)) {
            for ($i = 0; $i < count($structure->parts); $i++) {
                $part = $structure->parts[$i];
                if ($part->subtype == 'HTML') {
                    $partNumber = $i + 1;
                    $message    = imap_fetchbody($inbox, $email_uid, $partNumber, FT_UID);
                    if ($part->encoding == 3) {
                        $message = base64_decode($message);
                    } elseif ($part->encoding == 4) {
                        $message = quoted_printable_decode($message);
                    }
                    break;
                }
            }
        } else {
            $message = imap_fetchbody($inbox, $email_uid, 1, FT_UID);
            if ($structure->encoding == 3) {
                $message = base64_decode($message);
            } elseif ($structure->encoding == 4) {
                $message = quoted_printable_decode($message);
            }
        }
        return $message;
    }


    protected function adManagerReportCsv(string $content): array
    {
        function parseDateRange(string $dateRange): array
        {
            $dates          = explode(' - ', $dateRange);
            $formattedDates = [];
            foreach ($dates as $date) {
                $dateTime         = DateTime::createFromFormat('M d, Y', $date);
                $formattedDates[] = $dateTime->format('Y - m - d');
            }
            return $formattedDates;
        }

        $flag      = false;
        $rows      = [];
        $dateRange = [];
        foreach (explode("\n", $content) as $line) {
            if (str_starts_with($line, 'Date range')) {
                $dateRange = str_replace(['"', 'Date range,'], '', $line);
                $dateRange = parseDateRange($dateRange);
            }
            if (str_starts_with($line, 'Total,')) {
                $flag = false;
                continue;
            }
            if (str_starts_with($line, 'Date,')) $flag = true;

            if ($flag) $rows[] = $line;
        }
        [$fields, $csvData] = $this->csv2json(implode("\n", $rows));
        return [$dateRange, $fields, $csvData];
    }

    protected function init_clickhouse(string $database = ''): ClickHouseDB
    {
        $config     = Config::get('database.connections.clickhouse');
        $clickhouse = new ClickHouseDB([
            'host'     => $config['hostname'],
            'port'     => $config['port'],
            'username' => $config['username'],
            'password' => $config['password'],
        ]);
        $clickhouse->database($database ?: $config['database']);
        return $clickhouse;
    }

    /*
     * 获取飞书表格投放对应表
     */
    protected function getSpendTable(string $platform = ''): array
    {
        if (empty($this->domains)) {
            $this->domains = Domain::where('channel_id', '>', 0)->select()->toArray();
            $this->domains = array_column($this->domains, null, 'domain');
        }
        if (empty($this->apps)) {
            $this->apps = Apps::where('pkg_name', '<>', '')->select()->toArray();
            $this->apps = array_column($this->apps, null, 'pkg_name');
        }

//        $access_token = FeishuBot::getTenantAccessToken(Env::get('BOT.HB_APP_ID'), Env::get('BOT.HB_APP_SECRET'));
//        $token        = Env::get('BOT.HB_SPEND_TO_APP_TABLE_TOKEN');
//        $table        = Env::get('BOT.HB_SPEND_TO_APP_TABLE_ID');
//        $result       = $this->http('GET', "https://open.feishu.cn/open-apis/bitable/v1/apps/$token/tables/$table/records", [
//            'query'   => [
//                'page_size' => 500
//            ],
//            'headers' => [
//                'Authorization' => 'Bearer ' . $access_token,
//            ],
//        ]);
//
//        $result = array_map(fn($item) => $item['fields'], $result['data']['items']);

        $result = json_decode('[{"ad_platform":"tiktok","advertiser_id":"7446607465292218385","app_package_name_or_url":"spark1.minitool.app","campaign_name":"toapp_spark1"},{"ad_platform":"tiktok","advertiser_id":"7446607465292218385","app_package_name_or_url":"spark2.minitool.app","campaign_name":"toapp_spark2"},{"ad_platform":"tiktok","advertiser_id":"7450035687132381200","app_package_name_or_url":"com.guanguannb.flowergame","campaign_name":"bdh_ios","is_app":true},{"ad_platform":"tiktok","advertiser_id":"7447376617032237057","app_package_name_or_url":"com.minigame.perfectneat","campaign_name":"tq_ios","is_app":true},{"ad_platform":"applovin","app_package_name_or_url":"com.guanguannb.flowergame","campaign_name":"bdh_ios","is_app":true},{"ad_platform":"applovin","app_package_name_or_url":"com.minigame.perfectneat","campaign_name":"tq_ios","is_app":true},{"ad_platform":"unity","app_package_name_or_url":"com.guanguannb.flowergame","campaign_name":"bdh_ios","is_app":true},{"ad_platform":"unity","app_package_name_or_url":"com.minigame.perfectneat","campaign_name":"tq_ios","is_app":true},{"ad_platform":"facebook","app_package_name_or_url":"com.guanguannb.flowergame","campaign_name":"bdh_ios","is_app":true},{"ad_platform":"facebook","advertiser_id":"903977011846146","app_package_name_or_url":"com.minigame.perfectneat","campaign_name":"tq_ios","is_app":true},{"ad_platform":"tiktok","advertiser_id":"7446607465292218385","app_package_name_or_url":"spark3.minitool.app","campaign_name":"toapp_spark3"},{"ad_platform":"tiktok","advertiser_id":"7446607465292218385","app_package_name_or_url":"spark4.minitool.app","campaign_name":"toapp_spark4","is_app":false},{"ad_platform":"tiktok","advertiser_id":"7446607465292218385","app_package_name_or_url":"spark5.minitool.app","campaign_name":"toapp_spark5"},{"ad_platform":"tiktok","advertiser_id":"7446607465292218385","app_package_name_or_url":"spark6.minitool.app","campaign_name":"toapp_spark6"},{"ad_platform":"facebook","advertiser_id":"384771248026058","app_package_name_or_url":"com.sortgame.cocktailsort","campaign_name":"mm_ios","is_app":true},{"ad_platform":"tiktok","advertiser_id":"7446607465292218385","app_package_name_or_url":"spark1.infitools.cc","campaign_name":"tocc_spark1"},{"ad_platform":"tiktok","advertiser_id":"7446607465292218385","app_package_name_or_url":"spark2.infitools.cc","campaign_name":"tocc_spark2"},{"ad_platform":"unity","app_package_name_or_url":"com.sortgame.cocktailsort","campaign_name":"mm_ios","is_app":true},{"ad_platform":"applovin","app_package_name_or_url":"com.sortgame.cocktailsort","campaign_name":"mm_ios","is_app":true},{"ad_platform":"tiktok","advertiser_id":"7447376649525788689","app_package_name_or_url":"com.sortgame.cocktailsort","campaign_name":"mm_ios","is_app":true}]', true);

        // 原始数据
        if ($platform) $result = array_values(array_filter($result, fn($item) => $item['ad_platform'] == $platform));

        // advertiser_ids
        $advertiser_ids = [];
        foreach (array_column($result, 'advertiser_id') as $v) {
            if (empty($v)) continue;
            $advertiser_ids = array_merge($advertiser_ids, explode(',', $v));
        }
        $advertiser_ids = array_filter(array_unique($advertiser_ids));

        return [$result, $advertiser_ids];
    }

    protected function campaignToDomain(string $campaign_full_name, array $table): array|bool
    {
        $item = false;
        foreach ($table as $row) {
            if (str_starts_with(strtolower($campaign_full_name), strtolower($row['campaign_name']))) {
                $row['is_app'] = isset($row['is_app']) && $row['is_app'] == true;
                if ($row['is_app']) {
                    if (!isset($this->apps[$row['app_package_name_or_url']])) continue;
                    $row['app_id']        = $this->apps[$row['app_package_name_or_url']]['id'];
                    $row['domain_id']     = 0;
                    $row['domain_or_app'] = 1;
                } else {
                    if (!isset($this->domains[$row['app_package_name_or_url']])) continue;
                    $domain               = $this->domains[$row['app_package_name_or_url']];
                    $row['app_id']        = $domain['app_id'];
                    $row['domain_id']     = $domain['id'];
                    $row['domain_or_app'] = $domain['is_app'];
                }
                $item = $row;
                break;
            }
        }
        return $item;
    }


}
