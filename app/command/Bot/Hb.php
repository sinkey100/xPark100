<?php

namespace app\command\Bot;

use app\admin\model\xpark\Utc;
use app\command\Base;
use sdk\FeishuBot;
use think\console\Input;
use think\console\Output;

class Hb extends Base
{

    protected string $today;
    protected int    $hour;

    protected function configure(): void
    {
        $this->setName('Hb');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->hour = (int)date("H");
        if (!in_array($this->hour, [10, 19])) return;

        $this->today = $this->hour > 12 ? date('Y-m-d') : date('Y-m-d', strtotime('-1 days'));
        $this->log("\n\n======== HB广告收入报警开始 ========", false);

        // 获取当天数据
        $data = Utc::alias('utc')
            ->field([
                'utc.*',
                'apps.app_name',
                'domain.domain',
                'domain.is_show',
                'domain.channel',
                'domain.flag'
            ])
            ->join('xpark_apps apps', 'apps.id = utc.app_id', 'left')
            ->join('xpark_domain domain', 'domain.id = utc.domain_id', 'left')
            ->where('utc.a_date', $this->today)
            ->where('domain.is_show', 0) // 隐藏层
            ->where('domain.channel', 'AdSense')
            ->where('apps.app_name', '<>', 'th5apk')
            ->whereIn('utc.ad_unit_type', ['ads_interstitial', 'ads_anchor', 'banner'])
            ->select()
            ->toArray();

        // 按域名和广告类型分组
        $groupedData = [];
        foreach ($data as $item) {
            $key = $item['domain_id'] . '_' . $item['ad_unit_type'];
            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'app_name'     => $item['app_name'],
                    'domain'       => $item['domain'],
                    'channel'      => $item['channel'] . '-' . $item['flag'],
                    'ad_unit_type' => $item['ad_unit_type'],
                    'requests'     => 0,
                    'fills'        => 0,
                    'impressions'  => 0,
                    'clicks'       => 0,
                    'ad_revenue'   => 0,
                    'trend'        => 0
                ];
            }
            $groupedData[$key]['requests']    += $item['requests'];
            $groupedData[$key]['fills']       += $item['fills'];
            $groupedData[$key]['impressions'] += $item['impressions'];
            $groupedData[$key]['clicks']      += $item['clicks'];
            $groupedData[$key]['ad_revenue']  += $item['ad_revenue'];
        }

        // 过滤收入大于1的数据
        $groupedData = array_filter($groupedData, function ($item) {
            return $item['ad_revenue'] > 1;
        });

        // 检查填充率趋势
        foreach ($groupedData as $key => &$item) {
            $currentFillRate = $item['requests'] > 0 ? ($item['fills'] / $item['requests']) : 0;
            
            // 如果当天填充率高于80%，不需要检查趋势
            if ($currentFillRate >= 0.8) {
                $item['trend'] = 0;
                continue;
            }

            // 获取最近10天的填充率数据
            $fillRates = [];
            for ($i = 1; $i <= 10; $i++) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $history = Utc::where('domain_id', explode('_', $key)[0])
                    ->where('ad_unit_type', $item['ad_unit_type'])
                    ->where('a_date', $date)
                    ->field([
                        'SUM(requests) as total_requests',
                        'SUM(fills) as total_fills'
                    ])
                    ->find();

                if (!$history) continue;

                $fillRate = $history['total_requests'] > 0 ? ($history['total_fills'] / $history['total_requests']) : 0;
                $fillRates[] = $fillRate;
            }

            // 检查趋势
            $item['trend'] = $this->checkFillRateTrend($fillRates);
        }
        unset($item);

        // 构建报警表格
        $alertRows = [];
        foreach ($groupedData as $item) {
            $ctr = $item['impressions'] > 0 ? ($item['clicks'] / $item['impressions']) : 0;
            $fillRate = $item['requests'] > 0 ? ($item['fills'] / $item['requests']) : 0;

            $ctrText = $this->formatRate($ctr, $item['ad_unit_type']);
            $fillRateText = $this->formatFillRate($fillRate, $item['trend']);

            if ($this->checkAlertConditions($item['ad_unit_type'], $ctr, $fillRate)) {
                $alertRows[] = [
                    "app_name"     => $item['app_name'],
                    "domain"       => $item['domain'],
                    "channel"      => $item['channel'],
                    "ad_unit_type" => $item['ad_unit_type'],
                    "ctr"          => $ctrText,
                    "fill_rate"    => $fillRateText
                ];
            }
        }

        // 按应用名排序
        usort($alertRows, function($a, $b) {
            return strcmp($a['app_name'], $b['app_name']);
        });

        if (!empty($alertRows)) {
            $this->sendAlert($alertRows);
        }

        $this->log('======== HB广告收入报警完成 ========', false);
    }

    protected function checkFillRateTrend(array $fillRates): int
    {
        if (count($fillRates) < 4) return 0;

        // 检查上升趋势
        $upCount = 0;
        for ($i = 1; $i < count($fillRates); $i++) {
            if ($fillRates[$i] < $fillRates[$i-1]) {
                $upCount++;
            } else {
                break;
            }
        }
        if ($upCount >= 3) {
            return $upCount;
        }

        // 检查下降趋势
        $downCount = 0;
        for ($i = 1; $i < count($fillRates); $i++) {
            if ($fillRates[$i] > $fillRates[$i-1]) {
                $downCount++;
            } else {
                break;
            }
        }
        if ($downCount >= 4) {
            return -$downCount;
        }

        return 0;
    }

    protected function checkAlertConditions(string $adType, float $ctr, float $fillRate): bool
    {
        // 检查填充率
        if ($fillRate < 0.8) {
            return true;
        }

        // 检查点击率
        return match ($adType) {
            'banner' => $ctr < 0.04 || $ctr > 0.08,
            'ads_anchor' => $ctr < 0.2 || $ctr > 0.3,
            'ads_interstitial' => $ctr < 0.2 || $ctr > 0.5,
            default => false,
        };
    }

    protected function formatRate(float $rate, string $adType): string
    {
        $percentage = round($rate * 100, 2);
        $text = $percentage . '%';

        switch ($adType) {
            case 'banner':
                if ($rate < 0.04) return "<font color='blue'>$text</font>";
                if ($rate > 0.08) return "<font color='red'>$text</font>";
                return $text;
            case 'ads_anchor':
                if ($rate < 0.2) return "<font color='blue'>$text</font>";
                if ($rate > 0.3) return "<font color='red'>$text</font>";
                return $text;
            case 'ads_interstitial':
                if ($rate < 0.2) return "<font color='blue'>$text</font>";
                if ($rate > 0.5) return "<font color='red'>$text</font>";
                return $text;
            default:
                return $text;
        }
    }

    protected function formatFillRate(float $rate, int $trend): string
    {
        $percentage = round($rate * 100, 2);
        $text = $percentage . '%';

        if ($rate >= 0.8) {
            return $text;
        }

        // 当日低于阈值，根据趋势显示颜色
        if ($trend > 0) {
            return "<font color='green'>$text ($trend)</font>";
        } elseif ($trend < 0) {
            return "<font color='red'>$text (" . abs($trend) . ")</font>";
        } else {
            // 当日低于阈值但没有趋势，显示绿色并标注(0)
            return "<font color='green'>$text (0)</font>";
        }
    }

    protected function sendAlert(array $rows): void
    {
        $struct = [
            "config"   => [
                "wide_screen_mode" => true
            ],
            "elements" => [
                [
                    "tag"                 => "table",
                    "page_size"           => 10,
                    "row_height"          => "low",
                    "freeze_first_column" => true,
                    "header_style"        => [
                        "text_align"       => "left",
                        "text_size"        => "normal",
                        "background_style" => "grey",
                        "text_color"       => "default",
                        "bold"             => true,
                        "lines"            => 1
                    ],
                    "columns"             => [
                        [
                            "name"             => "app_name",
                            "display_name"     => "应用",
                            "data_type"        => "text",
                            "horizontal_align" => "left",
                            "vertical_align"   => "top",
                            "width"            => "auto"
                        ],
                        [
                            "name"             => "domain",
                            "display_name"     => "域名",
                            "data_type"        => "text",
                            "horizontal_align" => "left",
                            "vertical_align"   => "top",
                            "width"            => "140px"
                        ],
                        [
                            "name"             => "ad_unit_type",
                            "display_name"     => "广告类型",
                            "data_type"        => "text",
                            "horizontal_align" => "left",
                            "vertical_align"   => "top",
                            "width"            => "100px"
                        ],
                        [
                            "name"             => "ctr",
                            "display_name"     => "点击率",
                            "data_type"        => "lark_md",
                            "horizontal_align" => "left",
                            "vertical_align"   => "top",
                            "width"            => "100px"
                        ],
                        [
                            "name"             => "fill_rate",
                            "display_name"     => "填充率",
                            "data_type"        => "lark_md",
                            "horizontal_align" => "left",
                            "vertical_align"   => "top",
                            "width"            => "110px"
                        ],
                        [
                            "name"             => "channel",
                            "display_name"     => "通道",
                            "data_type"        => "text",
                            "horizontal_align" => "left",
                            "vertical_align"   => "top",
                            "width"            => "120px"
                        ]
                    ],
                    "rows"                => $rows
                ]
            ],
            "header"   => [
                "template" => "red",
                "title"    => [
                    "content" => "🔥 HB风控链接报警",
                    "tag"     => "plain_text"
                ]
            ]
        ];

        FeishuBot::appMsg($struct);
    }
}
