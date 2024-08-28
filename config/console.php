<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'Demo'   => \app\command\Demo::class,
        'Xpark'   => \app\command\Xpark::class,
        'MiTools' => \app\command\MiTools::class
    ],
];
