<?php

namespace sdk;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\facade\Cache;
use Exception;
use think\facade\Env;

class FeishuBot
{

    protected static array $config;

    public static function appMsg(array $content, bool $test = false, string $msg_type = 'interactive'): bool
    {
        $config = [
            'app_id'        => Env::get('BOT.HB_APP_ID'),
            'app_secret'    => Env::get('BOT.HB_APP_SECRET'),
            'group_id'      => Env::get('BOT.HB_GROUP_ID'),
            'test_group_id' => Env::get('BOT.HB_TEST_GROUP_ID'),
        ];

        $http  = new Client(['verify' => false]);
        $token = self::getTenantAccessToken($config);

        $body = [
            "msg_type"   => $msg_type,
            'receive_id' => $config[$test ? 'test_group_id' : 'group_id'],
            "content"    => json_encode($content)
        ];

        try {
            $response = $http->request('POST', 'https://open.feishu.cn/open-apis/im/v1/messages?receive_id_type=chat_id', [
                'json'    => $body,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json'
                ],
            ]);
            $response = json_decode($response->getBody()->getContents(), true);

            return isset($response['msg']) && $response['msg'] == 'success';
        } catch (GuzzleException $e) {
            return false;
        }
    }


    /**
     * @throws Exception
     */
    protected static function getTenantAccessToken(array $config): string
    {
        $token_key = 'fs_app_token_' . $config['app_id'];
        $token     = Cache::get($token_key);
        if ($token) return $token;

        // 获取新 Token
        $http = new Client(['verify' => false]);

        try {
            $response = $http->request('POST', 'https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal', [
                'json' => [
                    'app_id'     => $config['app_id'],
                    'app_secret' => $config['app_secret'],
                ]
            ]);
            $response = json_decode($response->getBody()->getContents(), true);
            if (isset($response['code']) && $response['code'] === 0) {
                $token = $response['tenant_access_token'];
                Cache::set($token_key, $token, $response['expire'] - 30);
                return $token;
            } else {
                throw new Exception('Failed to fetch token: ' . $response['msg']);
            }
        } catch (GuzzleException $e) {
            throw new Exception('Failed to fetch token: ' . $e->getMessage());
        }

    }

}