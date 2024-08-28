<?php

namespace app\command;

use app\admin\model\Admin;
use app\admin\model\google\Account;
use app\admin\model\mi\instant\Report;
use Google\Service\Gmail;
use Google\Service\Oauth2;
use think\console\Input;
use think\console\Output;
use Exception;

use GuzzleHttp\Client as HttpClient;
use Google\Client as GoogleClient;
use Google\Service\AdSense;
use Google_Service_Adsense;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\facade\Env;
use sdk\Google as GoogleSDK;
use app\admin\model\mi\instant\Report as InstantReport;
use app\admin\model\mi\instant\ReportUrl as InstantReportUrl;


class Demo extends Base
{


    protected function configure()
    {
        // 指令配置
        $this->setName('Demo');
    }

    protected function execute(Input $input, Output $output): void
    {
        $res = file_get_contents('https://www.globalpetrolprices.com/Singapore/');
        echo $res;
        exit;


       $json = file_get_contents('https://raw.onmicrosoft.cn/Bing-Wallpaper-Action/main/data/zh-CN_all.json');
       $json = json_decode($json, true);
       $json = $json['data'];
       $json = array_slice($json, 0 , 10);

       foreach ($json as $v){

           $date = date("Y-m-d", strtotime($v['startdate']));
           $url = 'https://cn.bing.com'. $v['url'];




           $output->writeln("INSERT INTO wallpaper (date, lang, title, image_url) VALUES ('$date', 'zh-CN', '{$v['title']}', '$url');");


       }

    }
}
