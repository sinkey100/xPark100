<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        // 广告通道数据
        'Xpark'   => \app\command\Ad\Xpark::class,
        'BeesAds' => \app\command\Ad\BeesAds::class,
        'AdSense' => \app\command\Ad\AdSense::class,

        // 小米工具
        'MiTools' => \app\command\MiTools::class,

        'Demo' => \app\command\Demo::class,

    ],
];
