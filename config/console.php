<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        // 广告通道数据
        'Xpark'         => \app\command\Ad\Xpark::class,
        'BeesAds'       => \app\command\Ad\BeesAds::class,
        'AdSense'       => \app\command\Ad\AdSense::class,
        'PremiumAds'    => \app\command\Ad\PremiumAds::class,
        'Adx'           => \app\command\Ad\Adx::class,
        'AdSkeeper'     => \app\command\Ad\AdSkeeper::class,
        'AnyMind'       => \app\command\Ad\AnyMind::class,
        'AppLovin'      => \app\command\Ad\AppLovin::class,
        'Mango'         => \app\command\Ad\Mango::class,

        // 活跃数据
        'GA'            => \app\command\User\GA::class,
        'Hour'          => \app\command\User\Hour::class,
        'Active'        => \app\command\User\Active::class,
        'Dau'           => \app\command\User\Dau::class,
        'Adjust'        => \app\command\User\Adjust::class,
        'SLSTrack'      => \app\command\User\SLSTrack::class,
        'Bot'           => \app\command\Bot::class,

        // 投放
        'SpendTikTok'   => \app\command\Spend\Tiktok::class,
        'SpendUnity'    => \app\command\Spend\Unity::class,
        'SpendAppLovin' => \app\command\Spend\AppLovin::class,
        'SpendFacebook' => \app\command\Spend\Facebook::class,
        'SpendManage'  => \app\command\Spend\Manage::class,


        // 小米工具
        'MiTools'       => \app\command\MiTools::class,
        'Demo'          => \app\command\Demo::class,

    ],
];
