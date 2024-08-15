<?php

namespace sdk;

use app\admin\model\google\Account;
use Google\Client as GoogleClient;
use Google\Exception;
use Google\Service\Adsense;
use Google\Service\Oauth2;
use Google\Service\PeopleService;
use GuzzleHttp\Client as HttpClient;
use think\facade\Env;

class Google
{

    /**
     * @throws Exception
     */
    public function init(Account $account): GoogleClient
    {
        if(empty($account->json_file)) throw new Exception('账户错误');

        $client = new GoogleClient();
        $client->setHttpClient(new HttpClient([
            'verify' => false,
            'proxy'  => [
                'http'  => Env::get('PROXY.HTTP_PROXY'),
                'https' => Env::get('PROXY.HTTP_PROXY'),
            ]
        ]));

        $client->setAuthConfig(json_decode($account->json_text, true));
        $client->setRedirectUri(Env::get('APP.APP_URL') . 'callback.php?s=google');
        $client->addScope(AdSense::ADSENSE_READONLY); // AdSense 数据权限
        $client->addScope(Oauth2::USERINFO_EMAIL); // 获取 Gmail
        $client->addScope(Oauth2::USERINFO_PROFILE);
        $client->addScope(PeopleService::CONTACTS_READONLY);
        $client->setState($account->id);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        return $client;
    }


}