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
        $this->sls        = new SLS();
        $this->domains    = array_column(Domain::where('channel_id', '>', 0)->select()->toArray(), null, 'domain');
        $this->start_time = date('Y-m-d 00:00:00', strtotime('-' . ((int)date("H") == 0 ? 1 : 0) . ' days'));
        $this->log("\n\n======== SLS DAU 开始拉取数据 ========", false);

        // 计算活跃用户
        $data   = [];
        $result = $this->sls->getLogsWithPowerSql(strtotime($this->start_time), time(), SLSDau::$SQL_DAU);
        $this->log('拉取到' . count($result) . '条数据，准备数据中');

        foreach ($result as $row) {
            $row         = $row->getContents();
            $domain_name = $row['attribute.page.host'];
            if (!isset($this->domains[$domain_name])) continue;
            $data[] = [
                'app_id'       => $this->domains[$domain_name]['app_id'],
                'channel_id'   => $this->domains[$domain_name]['channel_id'],
                'domain_id'    => $this->domains[$domain_name]['id'],
                'date'         => $row['date'],
                'uid'          => $row['attribute.uid'],
                'country_code' => $row['attribute.country_id'],
                'domain_name'  => $domain_name
            ];
        }
        $this->log('准备存储数据');
        // 删除数据
        SLSDau::whereTime('date', '>=', $this->start_time)->delete();
        // 插入数据
        $chunks = array_chunk($data, 2000);
        foreach ($chunks as $chunk) {
            SLSDau::insertAll($chunk);
        }
        $this->log('存储完成');
    }

}
