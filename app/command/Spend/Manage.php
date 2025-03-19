<?php

namespace app\command\Spend;

use app\admin\model\cy\CYIosGame;
use app\admin\model\spend\Bind;
use app\admin\model\spend\Data as SpendData;
use app\admin\model\spend\Manage as ManageModel;
use app\admin\model\xpark\Domain;
use app\command\Base;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use sdk\FeishuBot;
use think\console\Input;
use think\console\Output;
use think\facade\Env;

class Manage extends Base
{

    protected array  $domains;
    protected string $date;

    protected function configure(): void
    {
        $this->setName('Manage');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->date    = date("Y-m-d");
        $this->domains = array_column(Domain::select()->toArray(), null, 'domain');


        $this->tiktokBind();

        $this->tiktok();
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function tiktok(): void
    {
        $bind = Bind::field(['campaign_id', 'domain_id'])->where('platform', 'tiktok')->order('date', 'desc')->group('campaign_id')->select();
        $bind = array_column($bind->toArray(), null, 'campaign_id');

        $accounts = $this->getTiktokAccount();
        // 获取账户的所有计划
        foreach ($accounts as $advertiser_id => $account) {
            $this->log('开始拉取 Tiktok 计划:' . $advertiser_id);
            try {
                $result = $this->http('GET', 'https://business-api.tiktok.com/open_api/v1.3/campaign/get/', [
                    'json'    => [
                        'advertiser_id' => (string)$advertiser_id,
                        'page_size'     => 1000
                    ],
                    'headers' => [
                        'Access-Token' => $account['account_key'],
                        'Content-Type' => 'application/json'
                    ],
                ]);
            } catch (Exception $e) {
                continue;
            }
            if ($result['code'] != 0) continue;

            // 遍历处理每一个计划
            foreach ($result['data']['list'] as $v) {
                $item = ManageModel::where('campaign_id', $v['campaign_id'])->find();
                if (!$item) {
                    ManageModel::create([
                        'spend_platform' => 'tiktok',
                        'campaign_id'    => $v['campaign_id'],
                        'campaign_name'  => $v['campaign_name'],
                        'domain_id'      => $bind[$v['campaign_id']]['domain_id'] ?? null,
                        'smart_switch'   => $v['is_smart_performance_campaign'] ? 1 : 0,
                        'budget'         => $v['budget'],
                        'status'         => $v['operation_status'] == 'ENABLE' ? 1 : 0,
                        'account_name'   => $account['账户名称'] ?? '',
                        'advertiser_id'  => $v['advertiser_id']
                    ]);
                } else {
                    $item->save([
                        'domain_id'    => $bind[$v['campaign_id']]['domain_id'] ?? null,
                        'smart_switch' => $v['is_smart_performance_campaign'] ? 1 : 0,
                        'budget'       => $v['budget'],
                        'status'       => $v['operation_status'] == 'ENABLE' ? 1 : 0,
                    ]);
                }
            }
        }
        $this->log('拉取完成 Tiktok 计划');
    }

    public function tiktokBind(): void
    {
        // 现有绑定关系
        $total = Bind::where('platform', 'tiktok')->where('date', $this->date)->count();
        if ($total > 0) return;

        $accounts = $this->getTiktokAccount();

        foreach ($accounts as $advertiser_id => $account) {
            $this->log('开始获取 Tiktok 域名关系：' . $advertiser_id);

            $headers = [
                'Access-Token' => $account['account_key'],
                'Content-Type' => 'application/json'
            ];

            try {
                $result = $this->http('GET', 'https://business-api.tiktok.com/open_api/v1.3/campaign/get/', [
                    'json'    => [
                        'advertiser_id' => (string)$advertiser_id,
                        'page_size'     => 1000
                    ],
                    'headers' => $headers,
                ]);
            } catch (Exception $e) {
                continue;
            }
            if ($result['code'] != 0) continue;

            $smart_campaign  = [];
            $common_campaign = [];
            $campaign_list   = [];
            // 寻找所有计划
            foreach ($result['data']['list'] as $v) {
                if ($v['is_smart_performance_campaign']) {
                    $smart_campaign[] = (string)$v['campaign_id'];
                } else {
                    $common_campaign[] = (string)$v['campaign_id'];
                }
            }

            // 查询普通计划对应关系
            $chunks  = array_chunk($common_campaign, 30);
            $ad_data = [];
            foreach ($chunks as $chunk) {
                $result = $this->http('GET', 'https://business-api.tiktok.com/open_api/v1.3/ad/get/', [
                    'json'    => [
                        'page'          => 1,
                        'page_size'     => 500,
                        'advertiser_id' => (string)$advertiser_id,
                        'filtering'     => [
                            'campaign_ids' => $chunk
                        ]
                    ],
                    'headers' => $headers
                ]);
                if ($result['code'] == 0 && $result['message'] == 'OK') {
                    $ad_data = array_merge($ad_data, $result['data']['list']);
                }
            }
            foreach ($ad_data as $row) {
                if (empty($row['landing_page_url']) || isset($campaign_list[$row['campaign_id']])) continue;
                $campaign_list[$row['campaign_id']] = parse_url($row['landing_page_url'])['host'];;
            }

            // 查询 Smart 计划对应关系
            $chunks     = array_chunk($smart_campaign, 30);
            $smart_data = [];
            foreach ($chunks as $chunk) {
                $result = $this->http('GET', 'https://business-api.tiktok.com/open_api/v1.3/campaign/spc/get/', [
                    'json'    => [
                        'advertiser_id' => (string)$advertiser_id,
                        'campaign_ids'  => $chunk
                    ],
                    'headers' => $headers
                ]);
                if ($result['code'] == 0 && $result['message'] == 'OK') {
                    $smart_data = array_merge($smart_data, $result['data']['list']);
                }
            }
            foreach ($smart_data as $row) {
                if (empty($row['landing_page_urls'][0]['landing_page_url']) || isset($campaign_list[$row['campaign_id']])) continue;
                $campaign_list[$row['campaign_id']] = parse_url($row['landing_page_urls'][0]['landing_page_url'])['host'];;
            }

            // 查询 App 对应关系
            $app_data = [];
            $page     = 1;
            do {
                $result = $this->http('GET', 'https://business-api.tiktok.com/open_api/v1.3/adgroup/get/', [
                    'json'    => [
                        'advertiser_id' => (string)$advertiser_id,
                        'page'          => $page,
                        'page_size'     => 1000,
                    ],
                    'headers' => $headers
                ]);
                if ($result['code'] == 0 && $result['message'] == 'OK') {
                    $app_data    = array_merge($app_data, $result['data']['list']);
                    $total_pages = $result['data']['page_info']['total_page'];
                    $page++;
                } else {
                    break;
                }
            } while ($page <= $total_pages);

            foreach ($app_data as $row) {
                if (empty($row['app_download_url']) || isset($campaign_list[$row['campaign_id']])) continue;
                if (!preg_match('/id(\d+)/', $row['app_download_url'], $matches)) continue;
                $appstore_id = $matches[0];
                $ios_app     = CYIosGame::where('appstore_url', 'like', "%$appstore_id%")->find();
                if (!$ios_app) continue;
                $campaign_list[$row['campaign_id']] = $ios_app->bundle_id;
            }
            print_r($campaign_list);

            // 存储到数据库
            foreach ($campaign_list as $campaign_id => $domain_name) {
                $domain_info = $this->domains[$domain_name] ?? false;
                if (!$domain_info) continue;
                // 存储
                Bind::create([
                    'platform'    => 'tiktok',
                    'campaign_id' => $campaign_id,
                    'app_id'      => $domain_info['app_id'],
                    'domain_id'   => $domain_info['id'],
                    'domain_name' => $domain_info['domain'],
                    'date'        => $this->date
                ]);
            }
        }
        $this->log('获取成功 Tiktok 域名关系');
    }

    protected function getTiktokAccount(): array
    {
        // 飞书表格 - 获取广告账户列表
        $fs_token       = FeishuBot::getTenantAccessToken(Env::get('BOT.HB_APP_ID'), Env::get('BOT.HB_APP_SECRET'));
        $fs_table_token = Env::get('BOT.HB_SPEND_TO_APP_TABLE_TOKEN');
        $fs_table_id    = Env::get('BOT.HB_SPEND_TO_APP_TABLE_ID');

        $result = $this->http('GET', "https://open.feishu.cn/open-apis/bitable/v1/apps/$fs_table_token/tables/$fs_table_id/records", [
            'query'   => ['page_size' => 500],
            'headers' => ['Authorization' => 'Bearer ' . $fs_token],
        ]);
        $result = array_map(fn($item) => $item['fields'], $result['data']['items']);
        $result = array_filter($result, fn($item) => $item['ad_platform'] == 'tiktok');
        return array_column(array_values((array)$result), null, 'advertiser_id');
    }

}
