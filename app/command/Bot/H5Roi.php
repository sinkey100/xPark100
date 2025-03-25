<?php

namespace app\command\Bot;

use app\admin\model\xpark\Utc;
use app\admin\model\spend\Data as SpendData;
use app\admin\model\xpark\Domain;
use app\command\Base;
use think\console\Input;
use think\console\Output;
use sdk\FeishuBot;

class H5Roi extends Base
{
    protected array $dates     = [];
    protected array $trendData = [];

    protected function configure(): void
    {
        $this->setName('H5Roi');
    }

    protected function execute(Input $input, Output $output): void
    {
        if (!in_array((int)date("H"), [9, 18])) return;

        // 获取最近8天的日期
        for ($i = 1; $i <= 8; $i++) {
            $this->dates[] = date('Y-m-d', strtotime("-$i days"));
        }

        // 获取域名数据
        $domains = Domain::where('is_app', 0)
            ->where('status', 1)
            ->column('id,domain', 'id');

        if (empty($domains)) {
            $this->log('没有找到符合条件的域名');
            return;
        }

        // 获取支出数据
        $spendData = SpendData::where('date', 'in', $this->dates)
            ->where('is_app', 0)
            ->select()
            ->toArray();

        // 获取收入数据
        $revenueData = Utc::where('a_date', 'in', $this->dates)
            ->whereIn('domain_id', array_keys($domains))
            ->select()
            ->toArray();

        // 处理数据
        $alertRows = $this->processData($domains, $spendData, $revenueData);

        if (!empty($alertRows)) {
            $this->sendAlert($alertRows);
        }
    }

    protected function processData(array $domains, array $spendData, array $revenueData): array
    {
        // 按日期、域名和地区分组汇总数据
        $groupedSpend = [];
        foreach ($spendData as $item) {
            $key = $item['date'] . '_' . $item['domain_id'] . '_' . $item['country_code'];
            if (!isset($groupedSpend[$key])) {
                $groupedSpend[$key] = 0;
            }
            $groupedSpend[$key] += $item['spend'];
        }

        $groupedRevenue = [];
        foreach ($revenueData as $item) {
            $key = date('Y-m-d', strtotime($item['a_date'])) . '_' . $item['domain_id'] . '_' . $item['country_code'];
            if (!isset($groupedRevenue[$key])) {
                $groupedRevenue[$key] = 0;
            }
            $groupedRevenue[$key] += $item['ad_revenue'];
        }

        // 预先计算所有日期的 ROI 数据
        foreach ($domains as $domainId => $domain) {
            foreach ($this->dates as $date) {
                foreach (array_unique(array_column($spendData, 'country_code')) as $countryCode) {
                    $key     = $date . '_' . $domainId . '_' . $countryCode;
                    $spend   = $groupedSpend[$key] ?? 0;
                    $revenue = $groupedRevenue[$key] ?? 0;

                    if ($spend > 0) {
                        $this->trendData[$key] = round(($revenue / $spend) * 100, 2);
                    }
                }
            }
        }

        // 计算ROI和趋势
        $result = [];
        foreach ($domains as $domainId => $domain) {
            foreach ($groupedSpend as $key => $spend) {
                list($date, $spendDomainId, $countryCode) = explode('_', $key);
                if ($spendDomainId != $domainId || $date != $this->dates[0]) continue;

                $revenue = $groupedRevenue[$key] ?? 0;
                if ($spend == 0) continue;

                $roi = round(($revenue / $spend) * 100, 2);

                // 计算同比
                $yesterdayKey = $this->dates[0] . '_' . $domainId . '_' . $countryCode;
                $beforeKey    = $this->dates[1] . '_' . $domainId . '_' . $countryCode;

                $yesterdayRoi = $this->trendData[$yesterdayKey] ?? 0;
                $beforeRoi    = $this->trendData[$beforeKey] ?? 0;

                if ($beforeRoi == 0) continue;

                $trend = round((($yesterdayRoi - $beforeRoi) / $beforeRoi) * 100, 2);

                // 计算趋势持续天数
                $trendDays = $this->calculateTrendDays($domainId, $countryCode, $trend >= 0);

                $trendText = $this->formatTrendText($trend, $trendDays);

                $result[] = [
                    'domain'       => $domain['domain'],
                    'country_code' => substr($countryCode, 0, 2), // 只取前两位字母
                    'spend'        => round($spend, 2),
                    'revenue'      => round($revenue, 2),
                    'roi'          => $roi . '%',
                    'trend'        => $trendText,
                    'trend_value'  => $trend, // 添加用于排序的原始趋势值
                    'is_up'        => $trend >= 0 // 添加用于分组排序的标记
                ];
            }
        }

        // 先按上升/下降分组，再按趋势值从大到小排序
        usort($result, function ($a, $b) {
            // 先按上升/下降分组
            if ($a['is_up'] !== $b['is_up']) {
                return $b['is_up'] - $a['is_up'];
            }
            // 同组内按趋势值绝对值从大到小排序
            return abs($b['trend_value']) <=> abs($a['trend_value']);
        });

        // 移除用于排序的临时字段
        foreach ($result as &$row) {
            unset($row['trend_value']);
            unset($row['is_up']);
        }

        return $result;
    }

    protected function calculateTrendDays(int $domainId, string $countryCode, bool $isUp): int
    {
        $days = 0;

        // 从第二天开始比较（因为第一天和第二天的比较已经在trend中了）
        for ($i = 1; $i < count($this->dates) - 1; $i++) {
            $currentKey = $this->dates[$i] . '_' . $domainId . '_' . $countryCode;
            $nextKey    = $this->dates[$i + 1] . '_' . $domainId . '_' . $countryCode;

            $currentRoi = $this->trendData[$currentKey] ?? 0;
            $nextRoi    = $this->trendData[$nextKey] ?? 0;

            if ($currentRoi == 0 || $nextRoi == 0) break;

            $currentTrend = ($currentRoi - $nextRoi) / $nextRoi;

            // 检查趋势是否保持一致
            if (($isUp && $currentTrend > 0) || (!$isUp && $currentTrend < 0)) {
                $days++;
            } else {
                break;
            }
        }

        return $days;
    }

    protected function formatTrendText(float $trend, int $days): string
    {
        $color     = $trend >= 0 ? 'green' : 'red';
        $arrow     = $trend >= 0 ? '↑' : '↓';
        $trendText = abs($trend) . '%';
        if ($days > 0) {
            $trendText .= " ($arrow$days)";
        }
        return "<font color='$color'>$trendText</font>";
    }

    protected function sendAlert(array $rows): void
    {
        $struct = [
            'config'   => [
                'wide_screen_mode' => true
            ],
            'header'   => [
                'template' => 'blue',
                'title'    => [
                    'content' => '📊 H5 ROI 趋势通知',
                    'tag'     => 'plain_text'
                ]
            ],
            'elements' => [
                [
                    'tag'       => 'table',
                    'page_size' => 10, // 每页显示10条数据
                    'columns'   => [
                        [
                            'name'         => 'domain',
                            'display_name' => '链接',
                            'width'        => 'auto'
                        ],
                        [
                            'name'         => 'country_code',
                            'display_name' => '地区',
                            'width'        => '80px'
                        ],
                        [
                            'name'         => 'spend',
                            'display_name' => '消耗',
                            'width'        => '100px'
                        ],
                        [
                            'name'         => 'revenue',
                            'display_name' => '收入',
                            'width'        => '100px'
                        ],
                        [
                            'name'         => 'roi',
                            'display_name' => 'ROI',
                            'width'        => '80px'
                        ],
                        [
                            'name'         => 'trend',
                            'display_name' => '同比',
                            'width'        => '100px',
                            'data_type'    => 'lark_md'
                        ]
                    ],
                    'rows'      => $rows
                ]
            ]
        ];

        FeishuBot::appMsg($struct, 'H5');
    }
}