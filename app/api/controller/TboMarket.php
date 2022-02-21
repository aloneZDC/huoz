<?php
//涡轮增加
namespace app\api\controller;

use think\Db;
use think\Exception;
use think\Request;

class TboMarket extends Base
{
    //配置
    public function index() {
        $res = \app\common\model\TboMarketCat::getList($this->lang);
        return $this->output_new($res);
    }
}
