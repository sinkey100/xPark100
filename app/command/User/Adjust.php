<?php

namespace app\command\User;

use app\admin\model\app\Active as AppActive;
use app\admin\model\xpark\Apps;
use app\command\Base;
use think\console\Input;
use think\console\Output;
use think\facade\Env;

class Adjust extends Base
{

    protected array $apps = [];

    protected function configure(): void
    {
        // https://dev.adjust.com/zh/api/rs-api/csv
        $this->setName('Adjust');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->days = 2;
        AppActive::where('status', 1)->delete();
        $this->apps = Apps::alias('apps')->field(['apps.*'])->select()->toArray();

        $days = $this->days - 1;
        $this->log("\n\n======== 开始拉取Adjust数据 ========", false);
        $start_date = date("Y-m-d", strtotime("-{$days} days"));
        $end_date   = date("Y-m-d");

        $result = $this->http('GET', 'https://automate.adjust.com/reports-service/report', [
            'query'   => [
                'ad_spend_mode' => 'network',
                'date_period'   => "$start_date:$end_date",
                'dimensions'    => 'day,store_id,app,country_code',
                'metrics'       => 'installs,daus',
                "sort"          => "day"
            ],
            'headers' => [
                'Authorization' => 'Bearer '.  Env::get('SPEND.ADJUST_TOKEN')
            ]
        ]);

        $result = $result['rows'];
        if (empty($result)) {
            $this->log('没有拉取到数据');
            return;
        }
        $this->log('拉取到数据：' . count($result));
        $insert_data = [];
        foreach ($result as $row) {
            // 查找应用
            $appstore_id = $row['store_id'];
            $app         = array_filter($this->apps, fn($item) => str_contains($item['appstore_url'], $appstore_id));
            if (!$app) continue;
            $app_id = reset($app)['id'];

            $insert_data[] = [
                'date'         => $row['day'],
                'app_id'       => $app_id,
                'country_code' => strtoupper($row['country_code']),
                'new_users'    => $row['installs'],
                'active_users' => $row['daus'],
                'status'       => 1,
            ];
        }
        if (empty($insert_data)) {
            $this->log('没有数据需要保存');
            return;
        }
        $this->log('开始保存数据');
        AppActive::insertAll($insert_data);
        AppActive::where('status', 0)->whereTime('date', '>=', $start_date)->delete();
        AppActive::where('status', 1)->update(['status' => 0]);
        $this->log('保存成功');
    }


}
