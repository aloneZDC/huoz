<?php
namespace app\index\controller;

use Think\Db;
use app\common\model\Member;
use app\common\model\AccountBook;
class Asset extends Base
{
    //我的資產
    public function index()
    {

        return $this->fetch('asset/safe');
    }
    //財務日誌
    public function log()
    {
        $model_account_book=new AccountBook();
        $currency_id = input('currency_id',0,'intval');
        $type = input('type',0,'intval');
        $page = input('page', 1, 'intval');
        $page_size = input('page_size', 10, 'intval');
        $list =$model_account_book->getLog($this->member_id,$currency_id,$type,$page,$page_size,$this->lang);
        return $this->fetch('asset/log',['log_list'=>$list]);
    }
    //持幣生息
    public function interest()
    {
        return $this->fetch('asset/interest');
    }

}
