<?php

namespace app\command;

use app\admin\model\xpark\Data;
use app\admin\model\xpark\Domain;
use app\admin\model\xpark\DomainRate;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\console\Command;
use think\console\Output;

class Base extends Command
{

    protected array $domains = [];
    protected array $dateRate = [];
    protected int $days = 3;
    protected array $prefix = ['cy-'];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function http(string $method, string $url, array $options = []): array
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
        return json_decode($result->getBody()->getContents(), true);
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
        $domain_row = Domain::where('original_domain', $original_domain)->find();
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
            if ($v['country_code'] == 'N/A') $v['country_code'] = '';
            $v['country_name']  = $country_data[$v['country_code']]['name'] ?? '';
            $v['country_level'] = $country_data[$v['country_code']]['level'] ?? '';

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

//            $this->output->writeln($rate);
            // 备份数据
            $v['gross_revenue'] = $v['ad_revenue'];
            $v['ad_revenue']    = $v['ad_revenue'] * $rate;
            $insertData[]       = $v;
        }
        Data::insertAll($insertData);
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

}
