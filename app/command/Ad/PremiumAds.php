<?php

namespace app\command\Ad;

use app\admin\model\xpark\Data;
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

        $rawData = $this->pull();

        if (empty($rawData) || count($rawData) == 0) {
            $this->log('======== PremiumAds 拉取数据完成 ========', false);
            return;
        }

        $this->log('准备删除历史数据');

        for ($i = 0; $i < $this->days; $i++) {
            Data::where('channel', 'PremiumAds')->where('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
        }
        $this->log('历史数据已删除');

        if (count($rawData) > 0) {
            $this->log('准备保存新的数据');
            $this->saveData($rawData);
        }

        $this->log('======== PremiumAds 拉取数据完成 ========', false);
    }

    protected function pull(): array
    {
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

            $message = find_row_from_keyword($message, 'Download Report');
            if (!$message) throw new Exception('没有找到下载链接');

            preg_match_all('~\bhttps?://[^"]+~', $message, $matches);
            $csvRaw = file_get_contents($matches[0][0]);
            [$fields, $csvData] = $this->csv2json($csvRaw);
            $data = [];
            if (!isset($csvData[0]['country_code']) || !isset($csvData[0]['ad_unit_id'])) continue;
            foreach ($csvData as $v) {
                [$domain_id, $app_id] = $this->getDomainRow($v['app_key'], $v['date'], 'PremiumAds');
                $row    = [
                    'channel'         => 'PremiumAds',
                    'channel_full'    => 'PremiumAds',
                    'sub_channel'     => $v['app_key'],
                    'domain_id'       => $domain_id,
                    'app_id'          => $app_id,
                    'a_date'          => $v['date'],
                    'country_code'    => $v['country_code'],
                    'ad_placement_id' => $v['ad_unit_id'],
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
            $returnRows = array_merge($returnRows, $data);
        }
        imap_close($inbox);
        $this->log('拉取数据完成: ' . count($returnRows));
        return $returnRows;
    }

}
