<?php
namespace app\api\controller;
use think\Db;
use think\Exception;
use think\Log;

class Error extends \app\common\controller\Common{
    public function report() {
        $msg = input('post.');
        if(!empty($msg)) {
            Db::name('error_report')->insertGetId([
                'msg' => json_encode($msg),
                'add_time' => time(),
            ]);
        }
        $this->output(10000,lang('lan_operation_success'));
    }
}
