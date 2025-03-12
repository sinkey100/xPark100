<?php

namespace app\command\User;

use app\admin\model\sls\Active as SLSActive;
use app\admin\model\sls\User as SLSUser;
use app\admin\model\UserStaging;
use app\admin\model\xpark\Domain;
use app\command\Base;
use sdk\SLS;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class Active extends Base
{

    protected array $domains;
    protected SLS   $sls;
    protected int   $start_time;
    protected int   $end_time;

    protected function configure(): void
    {
        $this->setName('Active');
    }

    protected function execute(Input $input, Output $output): void
    {
        // 确认参数
        ini_set('error_reporting', E_ALL & ~E_DEPRECATED);
        $this->sls = new SLS();
        // 凌晨一点拉昨天和今天，其余时间只拉昨天
        $this->days = date("H" == 1) ? 2 : 1;
        // 查找所有SLS域名
        $sls_domains   = Domain::where('channel_id', '>', 0)->select()->toArray();
        $this->domains = array_column($sls_domains, null, 'domain');

        // 开始任务
        $this->pull();
    }

    protected function pull(): void
    {

        $this->log("\n\n======== SLS 开始拉取活跃数据 ========", false);

        for ($i = $this->days - 1; $i >= 0; $i--) {
            $date = date("Y-m-d", strtotime("-{$i} days"));

            $utc_start_time = strtotime("-7 days");
            $utc_end_time   = time();
            $this->log('开始拉取 UTC0 ' . $date);

            // 计算PV
            $result = $this->sls->getLogsWithPowerSql($utc_start_time, $utc_end_time, SLSActive::SQL_DAILY_PV($date));
            foreach ($result as $row) {
                $row = $row->getContents();
                $this->update_active_row($row['attribute.page.host'], $row['attribute.country_id'], $date, [
                    'page_views' => (int)$row['page_views']
                ]);
            }
            $this->log('PV 计算完成');

            // 新版计算新增活跃
            $result = Db::query(SLSActive::SQL_CALC_NEW_AND_ACTIVE_USERS($date));
            foreach ($result as $item) {
                SLSActive::where('domain_id', $item['domain_id'])
                    ->where('country_code', $item['country_code'])
                    ->where("date", $date)
                    ->update([
                        'new_users'    => $item['new_user_count'],
                        'active_users' => $item['active_user_count']
                    ]);
            }
            $this->log('新增活跃 计算完成');

            // 计算时长数据
            $result = $this->sls->getLogsWithPowerSql($utc_start_time, $utc_end_time, SLSActive::SQL_DAILY_TOTAL_TIME($date));
            foreach ($result as $row) {
                $row = $row->getContents();
                $this->update_active_row($row['attribute.page.host'], $row['attribute.country_id'], $date, [
                    'total_time' => $row['total_time']
                ]);
            }
            $this->log("活跃时长计算完成\n");
        }

        $this->log("\n\n======== SLS 拉取用户数据完成 ========", false);
    }

    protected function update_active_row(string $domain_name, string $country_code, string $date, array $data = []): void
    {
        $domain = $this->domains[$domain_name] ?? false;
        if (!$domain) return;

        $map = [
            'domain_id'    => $domain['id'],
            'country_code' => $country_code,
            'date'         => $date,
        ];

        $item = SLSActive::where($map)->find();
        if (!$item) {
            $map['channel_id']  = $domain['channel_id'];
            $map['app_id']      = $domain['app_id'];
            $map['domain_name'] = $domain['domain'];
            $item               = SLSActive::create($map);
        }
        $item->save($data);
    }


}
