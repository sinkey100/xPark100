<?php

namespace app\command;

use app\admin\model\google\Account;
use app\admin\model\xpark\Data;
use app\admin\model\xpark\Domain;
use app\admin\model\xpark\DomainRate;
use app\admin\model\xpark\XparkAdSense;
use think\console\Input;
use think\console\Output;
use Exception;
use Google\Service\AdSense as GoogleAdSense;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use sdk\Google as GoogleSDK;


class Demo extends Base
{

    protected array $domains = [];

    protected function configure()
    {
        // 指令配置
        $this->setName('AdSense');
    }


    protected function execute(Input $input, Output $output): void
    {
//        $country_data = get_country_data();
//        foreach ($country_data as $v) {
//
//            Data::where('country_code', $v['code'])->update([
//                'country_name'  => $v['name'],
//                'country_level' => $v['level'],
//            ]);
//
//        }
//        exit;

        $data = Data::where('country_name', '')->group('country_code')->select();
        $data = $data->toArray();
        echo json_encode(array_column($data, 'country_code'));

    }
}
