<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/24
 * Time: 9:22
 */

namespace app\api\controller;

use app\common\model\Transfer;

class TransferXrp extends Base
{
     //用户资产首页@标
    public function currency(){
        $model_transfer = new Transfer();
        $r=$model_transfer->currency_info($this->member_id);
        $this->output_new($r);

    }
     //划转Xrp首页@标
     public function index(){
         $model_transfer = new Transfer();
         $r=$model_transfer->money_limit($this->member_id);
         $this->output_new($r);

     }
     //划转Xrp功能@标
     public function payment(){
         $model_transfer = new Transfer();
         $num = input('post.num', '0');
         $phone_code = strval(input('phone_code',''));
         $pwd = input('post.pwd', '0');
         $r=$model_transfer->payment_xrp($this->member_id,$pwd,$num,$phone_code);
         $this->output_new($r);
     }
     //我的xrp资产明细@标
     public function detail(){
         $model_transfer = new Transfer();
         $type = input('post.type', 'all');
         $page = input('post.page', 1, 'intval');
         $page_size = input('post.page_size', 10, 'intval');
         $r=$model_transfer->detail_xrp($type,$this->member_id,$page,$page_size);
         $this->output_new($r);
     }
     //创新区明细@标
     public function new_detail(){
         $model_transfer = new Transfer();
         $page = input('post.page', 1, 'intval');
         $page_size = input('post.page_size', 10, 'intval');
         $r=$model_transfer->detail_new($this->member_id,$page,$page_size);
         $this->output_new($r);
     }
    //瑞波金明细@标
     public function xrpj_detail(){
         $model_transfer = new Transfer();
         $page = input('post.page', 1, 'intval');
         $page_size = input('post.page_size', 10, 'intval');
         $r=$model_transfer->detail_xrpj($this->member_id,$page,$page_size);
         $this->output_new($r);
     }

}