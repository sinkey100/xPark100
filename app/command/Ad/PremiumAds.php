<?php

namespace app\command\Ad;

use app\admin\model\xpark\Data;
use app\admin\model\xpark\Domain;
use app\command\Base;
use think\console\Input;
use think\console\Output;
use think\facade\Env;
use Exception;

class PremiumAds extends Base
{

    protected function configure()
    {
        $this->setName('PremiumAds');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->log("\n\n======== PremiumAds 开始拉取数据 ========", false);
        $this->log("任务开始，拉取当天邮件");
        Data::where('channel', 'PremiumAds')->where('status', 1)->delete();

        $this->pull();


        $this->log('======== PremiumAds 拉取数据完成 ========', false);
    }

    protected function pull()
    {
        // 获取账号和域名
        $ad_domains = Domain::where('channel', 'PremiumAds')->select()->toArray();
        $ad_domains = array_column($ad_domains, 'domain');

        try {
            $inbox = imap_open(Env::get('MAIL.FS_HOSTNAME'), Env::get('MAIL.FS_USERNAME'), Env::get('MAIL.FS_PASSWORD'));
        } catch (Exception $e) {
            throw new Exception('邮箱连接失败');
        }
        $emails = imap_search($inbox, 'SINCE "' . date("d-M-Y") . '"', SE_UID);
        if (!$emails) throw new Exception('没有查询到邮件');

        rsort($emails);
        $returnRows = [];
        foreach ($emails as $email_uid) {
            $header = imap_headerinfo($inbox, imap_msgno($inbox, $email_uid));
//            $subject = imap_mime_header_decode($header->subject)[0]->text;
//            $date    = date("Y-m-d H:i:s", strtotime($header->date));
            $from = $header->from[0]->mailbox . '@' . $header->from[0]->host;

            // 过滤发件人
            if (!str_contains($from, 'mail.premiumads.net')) continue;

            $message = $this->getMailContent($inbox, $email_uid);

            $report_url = find_row_from_keyword($message, 'Download Report');
            $from_date  = find_row_from_keyword($message, '<b>From:</b>');
            $to_date    = find_row_from_keyword($message, '<b>To:</b>');
            if (!$report_url) throw new Exception('没有找到下载链接');
            if (!$from_date || !$to_date) throw new Exception('没有找到起止日期');
            preg_match('/\d{4}-\d{2}-\d{2}/', $from_date, $matches);
            $from_date = $matches[0];
            preg_match('/\d{4}-\d{2}-\d{2}/', $to_date, $matches);
            $to_date = $matches[0];
            preg_match_all('~\bhttps?://[^"]+~', $report_url, $matches);
            $report_url = $matches[0][0];

            $csvRaw = file_get_contents($report_url);
            [$fields, $csvData] = csv2json($csvRaw);
            $data = [];
            if (!isset($csvData[0]['country_code']) || !isset($csvData[0]['ad_unit_id'])) continue;
            foreach ($csvData as $v) {
                if (!in_array($v['app_key'], $ad_domains)) continue;
                [$domain_id, $app_id] = $this->getDomainRow($v['app_key'], $v['date'], 'PremiumAds');
                $channel_full = 'PremiumAds';

                $row    = [
                    'channel'         => 'PremiumAds',
                    'channel_full'    => $channel_full,
                    'channel_id'      => $this->channelList[$channel_full]['id'] ?? 0,
                    'channel_type'    => ($this->channelList[$channel_full]['ad_type'] ?? 'H5') == 'H5' ? 0 : 1,
                    'sub_channel'     => $v['app_key'],
                    'domain_id'       => $domain_id,
                    'app_id'          => $app_id,
                    'a_date'          => $v['date'],
                    'country_code'    => $v['country_code'],
                    'ad_placement_id' => $v['ad_name'],
                    'requests'        => $v['requests'],
                    'fills'           => $v['response'],
                    'impressions'     => $v['impressions'],
                    'clicks'          => $v['clicks'],
                    'ad_revenue'      => $v['revenue'],
                    'gross_revenue'   => $v['revenue'],
                    'raw_ecpm'        => $v['ecpm']
                ];
                $data[] = $row;
            }

            if (count($data) > 0) {
                $this->log('准备保存新的数据');
                $this->saveData($data);
            }

            $this->log('准备删除历史数据');
            Data::where('channel', 'PremiumAds')->where('status', 0)->whereBetweenTime('a_date', $from_date, $to_date)->delete();
            Data::where('channel', 'PremiumAds')->where('status', 1)->whereBetweenTime('a_date', $from_date, $to_date)->update([
                'status' => 0
            ]);
            $this->log('历史数据已删除');


            $this->log('拉取数据完成: ' . count($data));
        }
        imap_close($inbox);
    }

}
