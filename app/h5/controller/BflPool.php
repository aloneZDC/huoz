<?php
namespace app\h5\controller;

class BflPool extends Base
{
    protected $public_action = ['index'];
    protected $is_decrypt = false; //不验证签名

    public function index() {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST, GET");
        header('Access-Control-Allow-Headers:x-requested-with,content-type');
        $res = \app\common\model\BflPool::getList(\app\common\model\BflPool::DEFAULT_CURRENCY);
        $this->output_new($res);
    }
}
