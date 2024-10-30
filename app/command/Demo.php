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
use think\facade\Env;


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

    }


}
