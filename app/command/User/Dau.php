<?php

namespace app\command\User;

use app\admin\model\sls\Active as SLSActive;
use app\admin\model\xpark\Domain;
use app\command\Base;
use sdk\SLS;
use think\console\Input;
use think\console\Output;
use app\admin\model\sls\Dau as SLSDau;
use Exception;

class Dau extends Base
{

    protected string $start_time;
    protected SLS    $sls;
    protected array  $domains;

    protected function configure(): void
    {
        $this->setName('Dau');
    }

    protected function execute(Input $input, Output $output): void
    {
        ini_set('error_reporting', E_ALL & ~E_DEPRECATED);
        // 凌晨一点拉昨天和今天，其余时间只拉昨天
        $this->days = date("H") == 1 ? 2 : 1;

        $this->sls     = new SLS();
        $this->domains = array_column(Domain::where('channel_id', '>', 0)->select()->toArray(), null, 'domain');
        $this->log("\n\n======== SLS DAU 开始拉取数据 ========", false);
        $data = [];

        for ($i = $this->days - 1; $i >= 0; $i--) {
            $date = date("Y-m-d", strtotime("-{$i} days"));
            $this->log("开始拉取 $date");
            foreach ($this->domains as $domain_name => $domain) {
                // 计算活跃用户
                $result = $this->sls->getLogsWithPowerSql(strtotime("-3 days"), time(), SLSDau::SQL_DAU($date, $domain_name));
                $this->log('拉取到' . count($result) . '条数据，准备数据中');
                foreach ($result as $row) {
                    $row    = $row->getContents();
                    $data[] = [
                        'app_id'       => $this->domains[$domain_name]['app_id'],
                        'channel_id'   => $this->domains[$domain_name]['channel_id'],
                        'domain_id'    => $this->domains[$domain_name]['id'],
                        'date'         => $date,
                        'uid'          => $row['attribute.uid'],
                        'country_code' => $row['attribute.country_id'],
                        'domain_name'  => $domain_name
                    ];
                }
            }
            SLSDau::where('date', $date)->delete();
        }
        $this->log('准备存储数据');
        // 插入数据
        $chunks = array_chunk($data, 5000);
        foreach ($chunks as $chunk) {
            SLSDau::insertAll($chunk);
        }
        $this->log('存储完成');
    }

}
