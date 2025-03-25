<?php

namespace app\command\Bot;

use app\admin\model\xpark\Utc;
use app\command\Base;
use sdk\FeishuBot;
use think\console\Input;
use think\console\Output;

class H5Risk extends Base
{
    protected string $today;

    protected function configure(): void
    {
        $this->setName('H5Risk');
    }

    protected function execute(Input $input, Output $output): void
    {
        if (!in_array((int)date("H"), [9, 17, 22])) return;

        $this->today = date('Y-m-d');
        $this->log("\n\n======== H5广告收入风控报警开始 ========", false);

        // 获取当天数据
        $data = Utc::where('a_date', $this->today)
            ->where('app_id', 29)
            ->whereIn('ad_unit_type', ['ads_interstitial', 'ads_anchor', 'banner'])
            ->where('ad_revenue', '>', 1)
            ->field([
                'sub_channel',
                'country_code',
                'ad_unit_type',
                'SUM(requests) as total_requests',
                'SUM(fills) as total_fills',
                'SUM(impressions) as total_impressions',
                'SUM(clicks) as total_clicks',
                'SUM(ad_revenue) as total_revenue'
            ])
            ->group('sub_channel, country_code, ad_unit_type')
            ->select()
            ->toArray();

        // 检查填充率趋势
        foreach ($data as &$item) {
            $currentFillRate = $item['total_requests'] > 0 ? ($item['total_fills'] / $item['total_requests']) : 0;

            // 如果当天填充率高于70%，不需要检查趋势
            if ($currentFillRate >= 0.7) {
                $item['trend'] = 0;
                continue;
            }

            // 获取最近7天的填充率数据
            $fillRates = [];
            for ($i = 1; $i <= 7; $i++) {
                $date    = date('Y-m-d', strtotime("-$i days"));
                $history = Utc::where('sub_channel', $item['sub_channel'])
                    ->where('ad_unit_type', $item['ad_unit_type'])
                    ->where('a_date', $date)
                    ->field([
                        'SUM(requests) as total_requests',
                        'SUM(fills) as total_fills'
                    ])
                    ->find();

                if (!$history) continue;

                $fillRate    = $history['total_requests'] > 0 ? ($history['total_fills'] / $history['total_requests']) : 0;
                $fillRates[] = $fillRate;
            }

            // 检查趋势
            $item['trend'] = $this->checkFillRateTrend($fillRates);
        }
        unset($item);

        // 构建报警表格
        $alertRows = [];
        foreach ($data as $item) {
            $ctr      = $item['total_impressions'] > 0 ? ($item['total_clicks'] / $item['total_impressions']) : 0;
            $fillRate = $item['total_requests'] > 0 ? ($item['total_fills'] / $item['total_requests']) : 0;
            $ecpm     = $item['total_impressions'] > 0 ? ($item['total_revenue'] / $item['total_impressions'] * 1000) : 0;

            $ctrText      = $this->formatRate($ctr, $item['ad_unit_type']);
            $fillRateText = $this->formatFillRate($fillRate, $item['trend']);

            if ($this->checkAlertConditions($item['ad_unit_type'], $ctr, $fillRate)) {
                $alertRows[] = [
                    "sub_channel"  => $item['sub_channel'],
                    "country_code" => $item['country_code'],
                    "ad_unit_type" => $item['ad_unit_type'],
                    "fill_rate"    => $fillRateText,
                    "ctr"          => $ctrText,
                    "ecpm"         => round($ecpm, 2)
                ];
            }
        }

        if (!empty($alertRows)) {
            $this->sendAlert($alertRows);
        }

        $this->log('======== H5广告收入风控报警完成 ========', false);
    }

    protected function checkFillRateTrend(array $fillRates): int
    {
        if (count($fillRates) < 2) return 0;

        // 检查上升趋势
        $upCount = 0;
        for ($i = 1; $i < count($fillRates); $i++) {
            if ($fillRates[$i] > $fillRates[$i - 1]) {
                $upCount++;
            } else {
                break;
            }
        }
        if ($upCount >= 2) return $upCount;

        // 检查下降趋势
        $downCount = 0;
        for ($i = 1; $i < count($fillRates); $i++) {
            if ($fillRates[$i] < $fillRates[$i - 1]) {
                $downCount++;
            } else {
                break;
            }
        }
        if ($downCount >= 2) return -$downCount;

        return 0;
    }

    protected function checkAlertConditions(string $adType, float $ctr, float $fillRate): bool
    {
        // 检查填充率
        if ($fillRate < 0.7) {
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
        $text       = $percentage . '%';

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
        $text       = $percentage . '%';

        if ($rate >= 0.7) {
            return $text;
        }

        // 当日低于阈值，根据趋势显示颜色
        if ($trend > 0) {
            return "<font color='green'>$text (↑$trend)</font>";
        } elseif ($trend < 0) {
            return "<font color='red'>$text (↓" . abs($trend) . ")</font>";
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
                            "name"             => "sub_channel",
                            "display_name"     => "域名",
                            "data_type"        => "text",
                            "horizontal_align" => "left",
                            "vertical_align"   => "top",
                            "width"            => "140px"
                        ],
                        [
                            "name"             => "country_code",
                            "display_name"     => "地区",
                            "data_type"        => "text",
                            "horizontal_align" => "left",
                            "vertical_align"   => "top",
                            "width"            => "80px"
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
                            "name"             => "fill_rate",
                            "display_name"     => "填充率",
                            "data_type"        => "lark_md",
                            "horizontal_align" => "left",
                            "vertical_align"   => "top",
                            "width"            => "110px"
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
                            "name"             => "ecpm",
                            "display_name"     => "eCPM",
                            "data_type"        => "text",
                            "horizontal_align" => "left",
                            "vertical_align"   => "top",
                            "width"            => "80px"
                        ]
                    ],
                    "rows"                => $rows
                ]
            ],
            "header"   => [
                "template" => "red",
                "title"    => [
                    "content" => "🔥 H5广告收入风控报警",
                    "tag"     => "plain_text"
                ]
            ]
        ];

        FeishuBot::appMsg($struct);
    }
}