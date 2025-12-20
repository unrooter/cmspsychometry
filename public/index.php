<?php
// [ 应用入口文件 ]

// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');

// 加载框架引导文件
// thinkphp 目录通常由 think-installer 插件创建
if (is_file(__DIR__ . '/../thinkphp/start.php')) {
    require __DIR__ . '/../thinkphp/start.php';
} else {
    require __DIR__ . '/../thinkphp/start.php';
}
