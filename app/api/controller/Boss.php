<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/24
 * Time: 9:22
 */

namespace app\api\controller;

use app\common\model\BossPlanBank;

class Boss extends Base
{
     //社區管理計劃@标
    public function plan(){
        $r=BossPlanBank::plan_info($this->member_id);
        $this->output_new($r);
    }
     //分红详情@标
    public function bouns_detail(){
        $type = input('post.type', 1, 'intval');
        $r=BossPlanBank::bouns_detail($type,$this->member_id);
        $this->output_new($r);
    }
    //领取分红@标
    public function bouns_receive(){
        return $this->output_new(ERROR1,lang("lan_modifymember_parameter_error"),[]);//此接口不执行
        $type = input('post.type', 1, 'intval');
        $r=BossPlanBank::receive_bouns($this->member_id,$type);
        $this->output_new($r);
    }
    //领取日志@标
    public function bouns_log(){
        $type = input('post.type', 1, 'intval');
        $page = intval(input('post.page', 1, 'intval,filter_page'));
        if(!is_numeric($page)||$page<=0){
            $page=1;
        }
        $page_size = input('post.page_size', 10, 'intval,filter_page');
        $r=BossPlanBank::bouns_log($this->member_id,$type,$page,$page_size);
        $this->output_new($r);
    }
    //一键领取@标
    public function user_one_receive(){
        return $this->output_new(ERROR1,lang("lan_modifymember_parameter_error"),[]);//此接口不执行
        $r=BossPlanBank::one_receive($this->member_id);
        $this->output_new($r);
    }
   //XRP 社區管理等级@标
   public function plan_level(){
        $model_boss_plan_bank=new BossPlanBank();
       $r=$model_boss_plan_bank->level($this->member_id);
       $this->output_new($r);
   }
}