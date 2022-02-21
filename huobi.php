<?php
/**
 * D:\phpstudy8\Extensions\php\php7.3.4nts\php.exe huobi.php start
 * php start.php start
 */

ini_set('display_errors', 'on');
ini_set('serialize_precision',14); //防止php7.1以上浮点数json_encode精度会出问题
use Workerman\Worker;

//if(strpos(strtolower(PHP_OS), 'win') === 0)
//{
//    exit("start.php not support windows, please use start_for_win.bat\n");
//}


// 标记是全局启动
define('GLOBAL_START', 1);
// define('geturl', "192.168.1.129");  //设置url ip

require_once __DIR__ . '/vendor/autoload.php';

// 加载所有Applications/*/start.php，以便启动所有服务
foreach(glob(__DIR__.'/app/Huobiapi/start_huobi.php') as $start_file)
{
    require_once $start_file;
}

// 检查扩展
if(!extension_loaded('pcntl'))
{
    //exit("Please install pcntl extension. See http://doc3.workerman.net/appendices/install-extension.html\n");
}

if(!extension_loaded('posix'))
{
    //exit("Please install posix extension. See http://doc3.workerman.net/appendices/install-extension.html\n");
}


// 将屏幕打印输出到Worker::$stdoutFile指定的文件中
Worker::$stdoutFile = 'F:\svn\hongbao\hb.log';

// 运行所有服务
Worker::runAll();
