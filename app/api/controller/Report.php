<?php

namespace app\api\controller;

use app\admin\model\Admin;
use app\admin\model\AdminLog;
use app\admin\model\sls\Active as SLSActive;
use app\admin\model\xpark\Apps;
use app\admin\model\xpark\Data;
use app\admin\model\xpark\Utc;
use think\facade\Db;
use Throwable;
use app\common\controller\Frontend;

class Report extends Frontend
{
    protected array $noNeedLogin = ['index', 'utc'];

    protected function authorized(): array
    {
        $username  = $this->request->header('username', '');
        $password  = $this->request->header('password', '');
        $from_date = $this->request->post('from_date/s', '');
        $to_date   = $this->request->post('to_date/s', '');
        if (empty($username) || empty($password)) $this->error('Unauthorized');
        if (empty($from_date) || empty($to_date)) $this->error('Parameter error');

        $from_date = strtotime($from_date);
        $to_date   = strtotime($to_date);
        if ($to_date - $from_date > 31 * 86400) $this->error('Wrong date range');

        $admin = Admin::where('username', $username)->find();
        // 用户不存在
        if (!$admin) $this->error('Unauthorized');
        // 密码错误
        if ($admin->password != encrypt_password($password, $admin->salt)) $this->error('Unauthorized');
        // 用户状态异常
        if ($admin->status != 1) $this->error('Abnormal status');

        AdminLog::create([
            'admin_id'  => $admin->id,
            'username'  => $admin->username,
            'url'       => '/api/report',
            'title'     => 'API拉取数据',
            'data'      => json_encode($this->request->param('', null, 'trim,strip_tags,htmlspecialchars')),
            'ip'        => $this->request->ip(),
            'useragent' => substr(request()->server('HTTP_USER_AGENT'), 0, 255)
        ]);

        $from_date = date("Y-m-d", $from_date);
        $to_date   = date("Y-m-d", $to_date);
        return [$from_date, $to_date, $admin];
    }

    public function index(): void
    {
        // 验参和鉴权
        [$from_date, $to_date, $admin] = $this->authorized();
        // 获取账号的应用
        $apps = Apps::where('admin_id', $admin->id)->select()->toArray();
        if (count($apps) == 0) $this->error('Apps not found');
        $apps    = array_column($apps, null, 'id');
        $app_ids = array_keys($apps);

        $dimension = ['a_date', 'sub_channel', 'app_id', 'country_code', 'ad_placement_id'];

        $field = array_merge($dimension, [
            'SUM(requests) AS requests',
            'SUM(fills) AS fills',
            'SUM(impressions) AS impressions',
            'SUM(clicks) AS clicks',
            'SUM(ad_revenue) AS ad_revenue',
        ]);

        $res = Data::field($field)
            ->where('app_id', 'in', $app_ids)
            ->where('status', 0)
            ->whereBetweenTime('a_date', $from_date, $to_date)
            ->order('a_date', 'desc')
            ->group(implode(',', $dimension))
            ->select()->toArray();

        foreach ($res as &$v) {
            $v['a_date']      = substr($v['a_date'], 0, 10);
            $v['requests']    = floatval($v['requests']);
            $v['fills']       = floatval($v['fills']);
            $v['impressions'] = floatval($v['impressions']);
            $v['clicks']      = floatval($v['clicks']);
            $v['ad_revenue']  = floatval($v['ad_revenue']);
            $v['app_name']    = $apps[$v['app_id']]['app_name'] ?? '';
            $v['date']        = $v['a_date'];
            $v['domain_name'] = $v['sub_channel'];

            $v['ctr']       = $v['clicks'] / (!empty($v['impressions']) ? $v['impressions'] : 1);
            $v['fill_rate'] = $v['fills'] / (!empty($v['requests']) ? $v['requests'] : 1);
            $v['cpc']       = round($v['ad_revenue'] / (!empty($v['clicks']) ? $v['clicks'] : 1), 2);
            $v['ecpm']      = round($v['ad_revenue'] / (!empty($v['impressions']) ? $v['impressions'] : 1) * 1000, 3);

            unset($v['a_date']);
            unset($v['sub_channel']);
            unset($v['unit_price']);
            unset($v['click_rate']);
            ksort($v);
        }

        $this->success('success', [
            'from_date' => $from_date,
            'to_date'   => $to_date,
            'list'      => $res
        ]);

    }

    public function utc(): void
    {
        // 验参和鉴权
        [$from_date, $to_date, $admin] = $this->authorized();

        // 获取账号的应用
        $apps = Apps::where('admin_id', $admin->id)->select()->toArray();
        if (count($apps) == 0) $this->error('Apps not found');
        $apps    = array_column($apps, null, 'id');
        $app_ids = array_keys($apps);

        $revenue_sql = Utc::field([
            'DATE(a_date) as a_date', 'app_id', 'country_code', 'domain_id', 'sub_channel',
            'SUM(requests) AS requests',
            'SUM(fills) AS fills',
            'SUM(impressions) AS impressions',
            'SUM(clicks) AS clicks',
            'SUM(ad_revenue) AS ad_revenue'
        ])
            ->whereIn('app_id', $app_ids)
            ->where('status', 0)
            ->whereBetweenTime('a_date', $from_date, $to_date)
            ->group('a_date, app_id, country_code, domain_id')
            ->buildSql();

        $active_sql = SLSActive::field([
            'date', 'app_id', 'country_code', 'domain_id',
            'SUM(page_views) AS page_views',
            'SUM(new_users) AS new_users',
            'SUM(active_users) AS active_users'
        ])
            ->whereIn('app_id', $app_ids)
            ->whereBetweenTime('date', $from_date, $to_date)
            ->group('date, app_id, country_code, domain_id')
            ->buildSql();

        $res = Db::table($revenue_sql . ' a')
            ->field('a.a_date, a.app_id, a.country_code, a.domain_id, a.sub_channel,
             a.requests, a.fills, a.impressions, a.clicks, a.ad_revenue, 
             b.page_views, b.new_users, b.active_users')
            ->join($active_sql . ' b', "DATE(a.a_date) = b.date AND a.app_id = b.app_id AND a.country_code = b.country_code AND a.domain_id = b.domain_id", 'LEFT')
            ->order('a.a_date DESC')
            ->select()->toArray();

        foreach ($res as &$v) {
            $v['a_date']      = substr($v['a_date'], 0, 10);
            $v['requests']    = floatval($v['requests']);
            $v['fills']       = floatval($v['fills']);
            $v['impressions'] = floatval($v['impressions']);
            $v['clicks']      = floatval($v['clicks']);
            $v['ad_revenue']  = floatval($v['ad_revenue']);
            $v['app_name']    = $apps[$v['app_id']]['app_name'] ?? '';
            $v['date']        = $v['a_date'];
            $v['domain_name'] = $v['sub_channel'];

            $v['ctr']        = $v['clicks'] / (!empty($v['impressions']) ? $v['impressions'] : 1);
            $v['fill_rate']  = $v['fills'] / (!empty($v['requests']) ? $v['requests'] : 1);
            $v['cpc']        = round($v['ad_revenue'] / (!empty($v['clicks']) ? $v['clicks'] : 1), 2);
            $v['ecpm']       = round($v['ad_revenue'] / (!empty($v['impressions']) ? $v['impressions'] : 1) * 1000, 3);
            $v['ad_revenue'] = floatval(number_format($v['ad_revenue'], 5, '.', ''));
            // 人均展示：展示次数/UV
            $v['page_views']         = intval($v['page_views']);
            $v['new_users']          = intval($v['new_users']);
            $v['active_users']       = intval($v['active_users']);
            $v['per_capita_display'] = empty($v['active_users'])
                ? ''
                : round($v['impressions'] / $v['active_users'], 2);

            unset($v['a_date']);
            unset($v['sub_channel']);
            unset($v['unit_price']);
            unset($v['domain_id']);
            unset($v['click_rate']);
            ksort($v);
        }

        $this->success('success', [
            'from_date' => $from_date,
            'to_date'   => $to_date,
            'list'      => $res
        ]);

    }

}