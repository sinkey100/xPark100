<?php

namespace app\command;

use app\admin\model\xpark\Data;
use sdk\FeishuBot;
use think\console\Input;
use think\console\Output;

class Bot extends Base
{

    // https://open.feishu.cn/document/common-capabilities/message-card/message-cards-content/using-markdown-tags
    protected array $struct;
    protected bool  $test = false;

    protected function configure(): void
    {
        $this->setName('Bot');
        $this->struct = [
            "config"   => [
                "wide_screen_mode" => true
            ],
            "elements" => [],
            "header"   => [
                "template" => "blue",
                "title"    => [
                    "content" => "",
                    "tag"     => "plain_text"
                ]
            ]
        ];
    }

    protected function execute(Input $input, Output $output): void
    {
        if (in_array(date("H"), [10, 14, 19])) {
            $this->ctr();
            $this->fill();
        }

    }

    protected function setTableCard(): void
    {
        $this->struct['elements'] = [
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
                "columns"             => [],
                "rows"                => []
            ]
        ];
    }

    protected function ctr(): void
    {
        $this->setTableCard();
        // 设置表头
        $this->struct['elements'][0]['columns'] = [
            [
                "name"             => "app_name",
                "display_name"     => "应用",
                "data_type"        => "text",
                "horizontal_align" => "left",
                "vertical_align"   => "top",
                "width"            => "auto"
            ],
            [
                "name"             => "sub_channel",
                "display_name"     => "域名",
                "data_type"        => "text",
                "horizontal_align" => "left",
                "vertical_align"   => "top",
                "width"            => "auto"
            ],
            [
                "name"             => "ctr",
                "display_name"     => "点击率",
                "data_type"        => "text",
                "horizontal_align" => "left",
                "vertical_align"   => "top",
                "width"            => "85px"
            ],
            [
                "name"             => "clicks",
                "display_name"     => "点击数",
                "data_type"        => "text",
                "horizontal_align" => "left",
                "vertical_align"   => "top",
                "width"            => "85px"
            ],
            [
                "name"             => "ad_revenue",
                "display_name"     => "收入",
                "data_type"        => "number",
                "horizontal_align" => "left",
                "format"           => [
                    "symbol"    => "$",
                    "precision" => 2,
                    "seperator" => true
                ],
                "width"            => "auto"
            ],
        ];

        $rows = Data::alias('data')
            ->field([
                'data.domain_id', 'data.sub_channel', 'data.app_id', 'apps.app_name', 'data.channel_type',
                'SUM(clicks) AS total_clicks',
                'SUM(ad_revenue) AS total_ad_revenue',
                'SUM(impressions) AS total_impressions',
                '(SUM(clicks) / SUM(impressions)) AS ctr'
            ])
            ->join('xpark_apps apps', 'apps.id = data.app_id', 'left')
            ->whereDay('data.a_date', date("Y-m-d"))
            ->group('data.domain_id')
            ->having('ctr > 0.1')
            ->order('data.app_id desc')
            ->select();

        $arr = [];

        foreach ($rows as $row) {
            $app_name = empty($row->app_name) ? '[未分配]' : $row->app_name;
            if (in_array($app_name, ['th5apk', 'th5apk5b'])) continue;
            if ($row->channel_type == 1) continue;
            if (!isset($arr[$app_name])) $arr[$app_name] = [];
            $arr[$app_name][] = $row;
        }
        foreach ($arr as $app_name => $list) {
            foreach ($list as $row) {
                $this->struct['elements'][0]['rows'][] = [
                    "app_name"    => $app_name,
                    "sub_channel" => $row['sub_channel'],
                    "ctr"         => round($row['ctr'] * 100, 2) . '%',
                    "clicks"      => $row['total_clicks'],
                    "ad_revenue"  => $row['total_ad_revenue'],
                ];
            }
        }

        $this->struct['header']['title']['content'] = '🔥 HB点击率预警';
        FeishuBot::appMsg($this->struct, $this->test);
    }

    protected function fill(): void
    {
        $this->setTableCard();
        // 设置表头
        $this->struct['elements'][0]['columns'] = [
            [
                "name"             => "app_name",
                "display_name"     => "应用",
                "data_type"        => "text",
                "horizontal_align" => "left",
                "vertical_align"   => "top",
                "width"            => "auto"
            ],
            [
                "name"             => "sub_channel",
                "display_name"     => "域名",
                "data_type"        => "text",
                "horizontal_align" => "left",
                "vertical_align"   => "top",
                "width"            => "auto"
            ],
            [
                "name"             => "rate",
                "display_name"     => "填充率",
                "data_type"        => "text",
                "horizontal_align" => "left",
                "vertical_align"   => "top",
                "width"            => "85px"
            ],
            [
                "name"             => "fills",
                "display_name"     => "填充数",
                "data_type"        => "text",
                "horizontal_align" => "left",
                "vertical_align"   => "top",
                "width"            => "85px"
            ],
            [
                "name"             => "ad_revenue",
                "display_name"     => "收入",
                "data_type"        => "number",
                "horizontal_align" => "left",
                "format"           => [
                    "symbol"    => "$",
                    "precision" => 2,
                    "seperator" => true
                ],
                "width"            => "auto"
            ],
        ];

        $rows = Data::alias('data')
            ->field([
                'data.domain_id', 'data.sub_channel', 'data.app_id', 'apps.app_name', 'data.channel_type',
                'SUM(requests) AS total_requests',
                'SUM(ad_revenue) AS total_ad_revenue',
                'SUM(fills) AS total_fills',
                '(SUM(fills) / SUM(requests)) AS rate'
            ])
            ->join('xpark_apps apps', 'apps.id = data.app_id', 'left')
            ->whereDay('data.a_date', date("Y-m-d"))
            ->group('data.domain_id')
            ->having('rate < 0.8')
            ->order('data.app_id desc')
            ->select();


        $arr = [];

        foreach ($rows as $row) {
            $app_name = empty($row->app_name) ? '[未分配]' : $row->app_name;
            if (in_array($app_name, ['th5apk', 'th5apk5b'])) continue;
            if ($row->channel_type == 1) continue;
            if (!isset($arr[$app_name])) $arr[$app_name] = [];
            $arr[$app_name][] = $row;
        }
        foreach ($arr as $app_name => $list) {
            foreach ($list as $row) {
                $this->struct['elements'][0]['rows'][] = [
                    "app_name"    => $app_name,
                    "sub_channel" => $row['sub_channel'],
                    "rate"        => round($row['rate'] * 100, 2) . '%',
                    "fills"       => $row['total_fills'],
                    "ad_revenue"  => $row['total_ad_revenue'],
                ];
            }
        }

        $this->struct['header']['title']['content'] = '🔥 HB填充率预警';
        $this->struct['header']['template']         = 'yellow';
        FeishuBot::appMsg($this->struct, $this->test);
    }


}
