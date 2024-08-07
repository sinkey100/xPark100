<?php

namespace app\command;

use app\admin\model\xpark\Domain;
use app\admin\model\xpark\Data;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Env;

class Xpark extends Base
{

    protected array $domains = [];
    protected array $prefix = ['cy-'];

    protected function configure()
    {
        $this->setName('Xpark');
    }


    protected function execute(Input $input, Output $output)
    {
        $output->writeln(date("Y-m-d H:i:s") . ' 任务开始');
        // 清除老数据
        for ($i = 0; $i < 3; $i++) {

            Data::where('a_date', date("Y-m-d", strtotime("-$i days")))->delete();
            // 重置自增id
            $maxId = Data::max('id');
            $maxId++;
            Db::execute("ALTER TABLE `ba_xpark_data` AUTO_INCREMENT={$maxId};");
        }


        $result = $this->http('POST', 'https://manage.xpark365.com/backend/gmf-manage/report/get_report', [
            'json'    => [
                'user_id'   => Env::get('XPARK.USER_ID'),
                'from_date' => date("Y-m-d", strtotime("-2 days")),
                'to_date'   => date("Y-m-d")
            ],
            'headers' => [
                'code' => Env::get('XPARK.CODE'),
            ]
        ]);

        if(!isset($result['data']['list'])){
            $output->writeln(date("Y-m-d H:i:s") . ' 拉取数据完成，没有返回数据');
            $output->writeln(json_encode($result));
            return;
        }
        if(count($result['data']['list']) == 0){
            $output->writeln(date("Y-m-d H:i:s") . ' 拉取数据完成，长度0');
            $output->writeln(json_encode($result));
            return;
        }

        $data = [];
        foreach ($result['data']['list'] as $item_day) {
            $csvRaw = file_get_contents($item_day['url']);
            [$fields, $csvData] = $this->csv_to_json($csvRaw);
            foreach ($csvData as &$v) {
                $v['domain_id']   = $this->getDomainId($v['sub_channel']);
                $v['sub_channel'] = str_replace($this->prefix, '', $v['sub_channel']);
            }

            $data = array_merge($data, $csvData);
        }
        $output->writeln(date("Y-m-d H:i:s") . ' 拉取数据完成，长度' . count($data));
        Data::insertAll($data);
    }

    protected function getDomainId($domain): int
    {
        // 系统记录的域名列表
        if (count($this->domains) == 0) {
            $domains       = Domain::field(['id', 'domain', 'original_domain'])->select()->toArray();
            $this->domains = array_column($domains, null, 'original_domain');
        }
        if (isset($this->domains[$domain])) {
            return $this->domains[$domain]['id'];
        }
        $item                   = Domain::create([
            'domain'          => str_replace($this->prefix, '', $domain),
            'original_domain' => $domain,
        ]);
        $this->domains[$domain] = [
            'domain'          => $item->domain,
            'original_domain' => $item->original_domain,
            'id'              => $item->id
        ];
        return $item->id;
    }

}
