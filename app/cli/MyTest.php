<?php
//定时任务
namespace app\cli;

use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\Db;

class MyTest extends Command
{
    protected function configure(){
        $this->setName('MyTest')->setArgument('aaa')->setDescription('This is a test');
    }

    protected function execute(Input $input, Output $output){
        \think\Request::instance()->module('cli');

        echo $input->getArgument('aaa');
        echo "test";
    }
}
