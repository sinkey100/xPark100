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
        $this->sls  = new SLS();
        $this->days = 2;
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
            Db::execute('truncate table ba_sls_user_staging;');
            $date           = date("Y-m-d", strtotime("-{$i} days"));
            $utc_start_time = strtotime($date . ' 00:00:00') - 8 * 3600;
            $utc_end_time   = strtotime($date . ' 23:59:59') - 8 * 3600;
            $this->log('开始拉取 UTC0 ' . $date);

            // 计算PV
            $result = $this->sls->getLogsWithPowerSql($utc_start_time, $utc_end_time, SLSActive::$SQL_DAILY_PV);
            foreach ($result as $row) {
                $row = $row->getContents();
                $this->update_active_row($row['attribute.page.host'], $row['attribute.country_id'], $date, [
                    'page_views' => (int)$row['page_views']
                ]);
            }
            $this->log('PV 计算完成');

            // 计算新增活跃
            $result = $this->sls->getLogsWithPowerSql($utc_start_time, $utc_end_time, SLSActive::$SQL_DAILY_ACTIVE_USER);
            $this->log('准备遍历数据量：' . count($result));
            foreach ($result as $row) {
                $row = $row->getContents();
                if (!isset($this->domains[$row['attribute.page.host']])) continue;
                $this->log('开始处理：' . $row['attribute.page.host']);
                $domain = $this->domains[$row['attribute.page.host']];

                $user_list = json_decode($row['user_list'], true);
                $user_list = array_map(fn($item) => ['uid' => $item], $user_list);

                UserStaging::insertAll($user_list);

                // 批量去重插入
                Db::execute(SLSActive::SQL_MERGE_NEW_USERS_MYSQL(
                    $domain['domain'], $domain['app_id'], $domain['id'], $row['attribute.country_id'], $date
                ));

                // 记录
                $new_users = SLSUser::where('domain_id', $domain['id'])
                    ->where('country_code', $row['attribute.country_id'])
                    ->where('date', $date)
                    ->count();

                $this->update_active_row($row['attribute.page.host'], $row['attribute.country_id'], $date, [
                    'new_users'    => $new_users,
                    'active_users' => count($user_list)
                ]);
            }
            $this->log('新增活跃计算完成');


            // 计算时长数据
            $result = $this->sls->getLogsWithPowerSql($utc_start_time, $utc_end_time, SLSActive::$SQL_DAILY_TOTAL_TIME);
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
