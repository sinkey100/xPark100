<?php

namespace app\command;

use app\admin\model\xpark\Channel;
use app\admin\model\xpark\Domain;
use app\admin\model\xpark\Tmp as TmpModel;

//use app\admin\model\xpark\Utc as UtcModel;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\console\Command;
use app\admin\model\xpark\TmpUtc as UtcModel;


class UtcBase extends Command
{

    protected int   $days          = 2;
    protected array $channelDomain = [];
    protected array $domainList    = [];
    protected array $channelList   = [];
    protected array $countryData   = [];

    public function __construct()
    {
        parent::__construct();
        // 初始化数据
        $this->countryData   = array_column(get_country_data(), null, 'code');
        $this->channelList   = array_column(Channel::select()->toArray(), null, 'id');
        $this->domainList    = array_column(Domain::select()->toArray(), null, 'domain');
        $this->channelDomain = array_reduce($this->domainList, function ($carry, $item) {
            $carry[$item['channel']][$item['domain']] = $item;
            return $carry;
        }, []);
    }

    protected function log(string $text, $time = true): void
    {
        if ($time) $text = date("Y-m-d H:i:s") . '  ' . $text;
        $this->output->writeln($text);
    }

    protected function getCountryInfo(string $code): array
    {
        $code = strtoupper($code);
        if (in_array($code, ['N/A', 'N / A', 'NONE', 'NULL'])) $code = '';
        $level = $this->countryData[$code]['level'] ?? '';
        $name  = $this->countryData[$code]['name'] ?? '';
        return [$code, $name, $level];
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

    protected function saveData(string $channel_name, array $rows): void
    {
        $insertTmpData = [];
        foreach ($rows as $row) {
            $domain_name = $row['sub_channel'];
            $domain_info = $this->channelDomain[$channel_name][$domain_name] ?? false;
            if (!$domain_info) continue;

            list($row['country_code'], $row['country_name'], $row['country_level']) = $this->getCountryInfo($row['country_code']);
            $row['a_date']        = date("Y-m-d", strtotime($row['a_date']));
            $row['gross_revenue'] = $row['ad_revenue'];
            $row['ad_revenue']    = $row['ad_revenue'] * $domain_info['rate'];
            $row['app_id']        = $domain_info['app_id'];
            $row['channel_id']    = $domain_info['channel_id'];
            $row['domain_id']     = $domain_info['id'];
            $row['channel']       = $this->channelList[$domain_info['channel_id']]['channel_type'];
            $row['channel_full']  = $this->channelList[$domain_info['channel_id']]['channel_alias'];
            $row['channel_type']  = $this->channelList[$domain_info['channel_id']]['ad_type'] == 'Native' ? 1 : 0;
            $row['status']        = 1;
            // 判断数据时区类型
            $row['data_mode'] = match (true) {
                $channel_name === 'AppLovin' => 0,
                $domain_info['sls_switch'] === 1 => 1,
                default => 2,
            };
            $insertTmpData[]  = $row;
        }
        $chunks = array_chunk($insertTmpData, 3000);
        foreach ($chunks as $chunk) {
            TmpModel::insertAll($chunk);
        }
        $this->log('开始归档数据');
        TmpModel::where('channel', $channel_name)->where('status', 0)->whereTime('a_date', '>=', date("Y-m-d", strtotime("-$this->days days")))->delete();
        TmpModel::where('channel', $channel_name)->where('status', 1)->update(['status' => 0]);
    }


}
