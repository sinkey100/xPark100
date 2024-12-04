<?php

namespace app\command\Ad;

use app\admin\model\xpark\Data;
use app\admin\model\xpark\Domain;
use app\command\Base;
use think\console\Input;
use think\console\Output;
use think\facade\Env;
use Exception;

class Mango extends Base
{

    protected function configure()
    {
        $this->setName('Mango');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->log("\n\n======== Mango 开始拉取数据 ========", false);
        $this->log("任务开始，拉取当天邮件");

        Data::where('channel', 'Mango')->where('status', 1)->delete();

        try {
            $this->pull();
        }catch (Exception $e){
            print_r($e->getLine());
            print_r($e->getMessage());
        }

        $this->log('======== Mango 拉取数据完成 ========', false);
    }

    protected function pull()
    {
        // 获取账号和域名
        $ad_domains    = Domain::where('channel', 'Mango')->select()->toArray();
        $ad_domains    = array_column($ad_domains, 'domain');
        $country_codes = country_id2code();

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
            $header  = imap_headerinfo($inbox, imap_msgno($inbox, $email_uid));
            $subject = imap_mime_header_decode($header->subject)[0]->text;

            // 过滤发件人
            if (!str_contains($subject, 'Mango Media')) continue;

            $structure = imap_fetchstructure($inbox, $email_uid, FT_UID);

            if (!(isset($structure->parts) && count($structure->parts))) {
                throw new Exception('没有获取到附件');
            }

            for ($i = 0; $i < count($structure->parts); $i++) {
                $part = $structure->parts[$i];
                if (isset($part->disposition) && strtolower($part->disposition) == 'attachment') {
                    // 获取附件内容
                    $attachment = imap_fetchbody($inbox, $email_uid, $i + 1, FT_UID);
                    if ($part->encoding == 3) {
                        $attachment = base64_decode($attachment);
                    } elseif ($part->encoding == 4) {
                        $attachment = quoted_printable_decode($attachment);
                    }
                }
            }

            $data = [];
            [$dateRange, $fields, $csvData] = $this->adManagerReportCsv($attachment);
            foreach ($csvData as $v) {
                if (!in_array($v['Site'], $ad_domains)) continue;
                [$domain_id, $app_id] = $this->getDomainRow($v['Site'], $v['Date'], 'Mango');
                $channel_full = 'Mango';
                $fill_rate    = percent2decimal($v['Ad Exchange match rate']);

                $requests = (int)str_replace(',', '', $v['Ad Exchange ad requests']);
                $row    = [
                    'channel'         => 'Mango',
                    'channel_full'    => $channel_full,
                    'channel_id'      => $this->channelList[$channel_full]['id'] ?? 0,
                    'channel_type'    => ($this->channelList[$channel_full]['ad_type'] ?? 'H5') == 'H5' ? 0 : 1,
                    'sub_channel'     => $v['Site'],
                    'domain_id'       => $domain_id,
                    'app_id'          => $app_id,
                    'a_date'          => $v['Date'],
                    'country_code'    => $country_codes[$v['Country ID']] ?? '',
                    'ad_placement_id' => $v['Ad unit'] ?? '',
                    'requests'        => $requests,
                    'fills'           => intval($requests * $fill_rate),
                    'impressions'     => (int)str_replace(',', '', $v['Ad Exchange impressions']),
                    'clicks'          => (int)str_replace(',', '', $v['Ad Exchange clicks']),
                    'ad_revenue'      => (float)str_replace(',', '', $v['Ad Exchange revenue ($)']),
                    'gross_revenue'   => (float)str_replace(',', '', $v['Ad Exchange revenue ($)']),
                    'raw_ecpm'        => $v['Ad Exchange average eCPM ($)']
                ];
                $data[] = $row;
            }

            imap_close($inbox);

            if (count($data) > 0) {
                $this->log('准备保存新的数据');
                $this->saveData($data);
            }

            $this->log('准备删除历史数据');
            Data::where('channel', 'Mango')->where('status', 0)->whereBetweenTime('a_date', $dateRange[0], $dateRange[1])->delete();
            Data::where('channel', 'Mango')->where('status', 1)->whereBetweenTime('a_date', $dateRange[0], $dateRange[1])->update([
                'status' => 0
            ]);
            $this->log('历史数据已删除');


            $this->log('拉取数据完成: ' . count($data));
        }

    }


}
