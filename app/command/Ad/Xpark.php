<?php

namespace app\command\Ad;

use app\admin\model\xpark\Data;
use app\command\Base;
use think\console\Input;
use think\console\Output;
use think\facade\Env;

class Xpark extends Base
{

    protected function configure()
    {
        $this->setName('Xpark');
    }

    protected function execute(Input $input, Output $output): void
    {
        // 上个月1号到今天的间隔日期
        $h          = ceil((time() - strtotime(date("Y-m-01", strtotime('last month'))) + 3600) / 86400);
        $this->days = date("H") == 0 ? $h : 3;
        $this->log("\n\n======== xPark 开始拉取数据 ========", false);
        $this->log("任务开始，拉取 {$this->days} 天");

        $rawData = $this->pull();

        if (empty($rawData) || count($rawData) == 0) {
            $this->log('======== xPark 拉取数据完成 ========', false);
            return;
        }

        if (count($rawData) > 0) {
            $this->log('准备保存新的数据');
            $this->saveData($rawData);
        }

        $this->log('准备删除历史数据');
        for ($i = 0; $i < $this->days; $i++) {
            Data::where('channel', 'xPark365')->where('status', 0)->where('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
            Data::where('channel', 'xPark365')->where('status', 1)->where('a_date', date("Y-m-d", strtotime("-$i days")))->update([
                'status' => 0
            ]);
        }
        $this->log('历史数据已删除');

        $this->log('======== xPark 拉取数据完成 ========', false);
    }

    protected function pull(): array
    {
        $days        = $this->days - 1;
        $date_ranges = $this->getPeriods($days, 30);
        $data        = [];
        foreach ($date_ranges as $date_range) {
            $result = $this->http('POST', 'https://manage.xpark365.com/backend/gmf-manage/report/get_report', [
                'json'    => [
                    'user_id'   => Env::get('XPARK.USER_ID'),
                    'from_date' => $date_range[0],
                    'to_date'   => $date_range[1]
                ],
                'headers' => [
                    'code' => Env::get('XPARK.CODE'),
                ]
            ]);

            if (!isset($result['data']['list'])) {
                $this->log('拉取数据完成，没有返回数据');
                $this->log(json_encode($result));
                continue;
            }
            if (count($result['data']['list']) == 0) {
                $this->log('拉取数据完成，长度0');
                $this->log(json_encode($result));
                continue;
            }

            foreach ($result['data']['list'] as $item_day) {
                $csvRaw = file_get_contents($item_day['url']);
                [$fields, $csvData] = $this->csv2json($csvRaw);
                foreach ($csvData as &$v) {
                    [$domain_id, $app_id] = $this->getDomainRow($v['sub_channel'], $v['a_date'], 'xPark365');
                    $v['channel']      = 'xPark365';
                    $v['channel_full'] = 'xPark365';
                    $v['domain_id']     = $domain_id;
                    $v['app_id']        = $app_id;
                    $v['sub_channel']   = str_replace($this->prefix, '', $v['sub_channel']);
                    $v['gross_revenue'] = $v['ad_revenue'];
                }
                $data = array_merge($data, $csvData);
            }
        }

        $this->log('拉取数据完成' . count($data));
        return $data;
    }

}
