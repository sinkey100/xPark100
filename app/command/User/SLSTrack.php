<?php

namespace app\command\User;

use app\admin\model\xpark\Domain;
use app\command\Base;
use sdk\SLS;
use think\console\Input;
use think\console\Output;
use app\admin\model\h5\Track as TrackModel;

class SLSTrack extends Base
{

    protected string $start_time;
    protected SLS    $sls;
    protected array  $domains;

    protected function configure(): void
    {
        $this->setName('SLSTrack');
    }

    protected function execute(Input $input, Output $output): void
    {
        ini_set('error_reporting', E_ALL & ~E_DEPRECATED);
        $this->sls        = new SLS();
        $this->domains    = array_column(Domain::where('channel_id', '>', 0)->select()->toArray(), null, 'domain');
        $this->start_time = strtotime(date('Y-m-d')) - 86400 - 28800;
        $this->log("\n\n======== SLS Track 开始拉取数据 ========", false);

        // 计算活跃用户
        $data   = [];
        $result = $this->sls->getLogsWithPowerSql($this->start_time, time(), TrackModel::$SQL_TRACK);
        $this->log('拉取到' . count($result) . '条数据，准备数据中');

        foreach ($result as $row) {
            $row         = $row->getContents();
            $domain_name = $row['domain'];
            if (!isset($this->domains[$domain_name])) continue;
            $data[] = [
                'app_id'           => $this->domains[$domain_name]['app_id'],
                'channel_id'       => $this->domains[$domain_name]['channel_id'],
                'domain_id'        => $this->domains[$domain_name]['id'],
                'date'             => $row['date'],
                'event_type'       => $row['ae_event_type'],
                'country_code'     => $row['country_id'],
                'valid_events'     => $row['valid_events'],
                'invalid_events'   => $row['invalid_events'],
                'anchored_count'   => $row['anchored_count'],
                'banner_count'     => $row['banner_count'],
                'fullscreen_count' => $row['fullscreen_count'],
            ];
        }
        $this->log('准备存储数据');
        // 删除数据
        TrackModel::whereTime('date', '>=', date("Y-m-d", $this->start_time))->delete();
        // 插入数据
        $chunks = array_chunk($data, 2000);
        foreach ($chunks as $chunk) {
            TrackModel::insertAll($chunk);
        }
        $this->log('存储完成');
    }

}
