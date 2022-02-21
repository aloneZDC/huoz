<?php
/**
 * 合约交易对K线图数据生成，每分钟执行一次
 */
define('WEB_PATH', str_replace('\\', '/', dirname(__FILE__)) .'/');
define('BIND_MODULE','cli/ContractOrderTrust/index');
define('APP_PATH', WEB_PATH . 'app/');
// 加载框架引导文件
require WEB_PATH . 'thinkphp/start.php';