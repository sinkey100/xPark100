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
use DateTime;
use think\facade\Config;
use think\facade\Env;

class Base extends Command
{

    protected array $domains     = [];
    protected array $domain_row  = [];
    protected array $apps        = [];
    protected array $dateRate    = [];
    protected int   $days        = 3;
    protected array $prefix      = ['cy-'];
    protected array $channelList = [];

    public function __construct()
    {
        parent::__construct();
        $this->channelList = Channel::field(['id', 'channel_alias', 'ad_type'])->select()->toArray();
        $this->channelList = array_column($this->channelList, null, 'channel_alias');
        $this->apps        = Apps::where('pkg_name', '<>', '')->select()->toArray();
        $this->apps        = array_column($this->apps, null, 'pkg_name');
        $this->domains     = Domain::where('channel_id', '>', 0)->select()->toArray();
        $this->domains     = array_column($this->domains, null, 'domain');

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
        if (PHP_SAPI == 'cli') {
            if ($time) $text = date("Y-m-d H:i:s") . '  ' . $text;
            $this->output->writeln($text);
        }
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

    protected function getDomainRow($original_domain, $date, $channel = ''): array
    {
        //domain_id
        $domain_name = str_replace($this->prefix, '', $original_domain);
        $domain_key  = $original_domain . '_' . $channel;

        if (empty($this->domain_row)) {
            $domains = Domain::where('id', '>', 0)->select()->toArray();
            foreach ($domains as $domain) {
                $this->domain_row[$domain['original_domain'] . '_' . $domain['channel']] = $domain;
            }
        }

        if (!isset($this->domain_row[$domain_key])) {
            $domain_row                    = Domain::create([
                'domain'          => $domain_name,
                'original_domain' => $original_domain,
                'channel'         => $channel,
                'app_id'          => null,
            ]);
            $this->domain_row[$domain_key] = $domain_row;
        }

        $domain_row = $this->domain_row[$domain_key];

//        $app_id = DomainRate::where('domain', $domain_name)->where('date', $date)->value('app_id', $domain_row->app_id);
        return [$domain_row['id'], $domain_row['app_id']];
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
            $v['ad_unit_type']  = ad_name_to_type($v['ad_placement_id']);

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
            if (strtotime($_date) >= 1740758400 && $v['app_id'] == 23) {
                // 传音3月起 数据不处理
                $rate = 1;
            }

            // 备份数据
            $v['gross_revenue'] = $v['ad_revenue'];
            $v['ad_revenue']    = $v['ad_revenue'] * $rate;

            $v['status']  = 1;
            $insertData[] = $v;
        }
        $chunks = array_chunk($insertData, 1000);
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
        [$fields, $csvData] = csv2json(implode("\n", $rows));
        return [$dateRange, $fields, $csvData];
    }

    /*
     * 获取飞书表格投放对应表
     */
    protected function getSpendTable(string $platform = ''): array
    {
        $access_token = FeishuBot::getTenantAccessToken(Env::get('BOT.HB_APP_ID'), Env::get('BOT.HB_APP_SECRET'));
        $token        = Env::get('BOT.HB_SPEND_TO_APP_TABLE_TOKEN');
        $table        = Env::get('BOT.HB_SPEND_TO_APP_TABLE_ID');
        $result       = $this->http('GET', "https://open.feishu.cn/open-apis/bitable/v1/apps/$token/tables/$table/records", [
            'query'   => [
                'page_size' => 500
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);

        $result = array_map(fn($item) => $item['fields'], $result['data']['items']);

        // 原始数据
        if ($platform) $result = array_values(array_filter($result, fn($item) => $item['ad_platform'] == $platform));

        // advertiser_ids
        $advertiser_ids = [];
        foreach (array_column($result, 'advertiser_id') as $v) {
            if (empty($v)) continue;
            $advertiser_ids = array_merge($advertiser_ids, explode(',', $v));
        }
        $advertiser_ids = array_filter(array_unique($advertiser_ids));
        // account_key
        $account_keys = [];
        foreach (array_column($result, 'account_key') as $v) {
            if (empty($v)) continue;
            $account_keys = array_merge($account_keys, explode(',', $v));
        }
        $account_keys = array_filter(array_unique($account_keys));


        return [$result, $advertiser_ids, $account_keys];
    }

    protected function appName2App(string $appstore_name): array|bool
    {
        $result = array_filter($this->apps, fn($item) => $item['appstore_name'] == $appstore_name);
        if (!$result) return false;
        return reset($result);
    }


}
