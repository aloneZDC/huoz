<?php
/**
 * 挂单机器人2
 */
define('WEB_PATH', str_replace('\\', '/', dirname(__FILE__)) .'/');
define('BIND_MODULE','cli/OrdersRebot2/index');
define('APP_PATH', WEB_PATH . 'app/');
// 加载框架引导文件
require WEB_PATH . 'thinkphp/start.php';