<?php
/**
 *发送邮件
 */
namespace app\index\controller;
use think\Db;
use think\Exception;

class Bank extends Base
{
//    protected $public_action = ['banklist'];
//    protected $is_method_filter = true;
    //支持的銀行卡類型
    public function banklist() {
        $list = model('Bank')->banklist($this->lang);
        $this->output(10000,lang('lan_operation_success'),$list);
    }

    //用户收款方式列表
    public function index() {
        $member_info = model('member')->where(['member_id'=>$this->member_id])->find();
        if(empty($member_info['name'])||empty($member_info['idcard'])){
            $this->error(lang("lan_user_authentication_first"),url('User/senior_verify'));
        }
        $list = model('Bank')->getList($this->member_id,$this->lang);
        $banklist = model('Bank')->banklist($this->lang);
        return $this->fetch('bank/index',['banklist'=>$banklist,'list'=>$list,'name'=>$member_info['name']]);

    }

    //用户设置的可用交易方式
    public function active($value=''){
        $list = model('Bank')->getList($this->member_id,$this->lang,true);
        if(empty($list)) $this->output(10001,lang('lan_please_select_payment_method'));

        $this->output(10000,lang('lan_operation_success'),$list);
    }

    //切换状态
    public function change() {
        $id = input('id',0,'intval');
        $type = input('type','');
        $result = model('Bank')->changeActive($this->member_id,$type,$id,$this->config);
        if(is_string($result))  mobileAjaxReturn(['status' => 0, 'info' =>$result]);
        mobileAjaxReturn(['status' => 1, 'info' =>lang('lan_operation_success')]);
    }

    //删除
    public function delete() {
        $id = input('id',0,'intval');
        $pwd = input('pwd','');
        $type = input('type','');

        $result = model('Bank')->deleteLog($this->member_id,$pwd,$type,$id);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    //支付方式详情
    public function info() {
        $id = input('id',0,'intval');
        $type = input('type','');
        $info = model('Bank')->getInfoByType($id,$type,$this->lang);
        if(is_string($info)) mobileAjaxReturn(['status' => 0, 'info' =>$info]);
        mobileAjaxReturn(['status' => 1, 'info' =>lang('lan_operation_success')]);

    }

    //新增及編輯支付方式
    public function add() {
        $type = input('type','');
        $id = input('id',0,'intval');
        $pwd = input('pwd','');
        $name = input('name','');
        $cardnum = input('cardnum','');
        $img = input('img','');
        $bname = input('bname',0,'intval');
        $inname = input('inname','');

        if(!empty($img)) {
            $upload = $this->oss_base64_upload($img, 'bank');
            if(!$upload['Code']) mobileAjaxReturn(['status' => 0, 'info' => lang("lan_upload_qr_code_error")]);
            $img = $upload['Msg'][0];
        }
        if(!empty($pwd)){
            $pwd=md5($pwd);
        }

        $result = model('Bank')->addLog($this->member_id,$type,$id,$pwd,$name,$cardnum,$img,$bname,$inname,$this->config);

        if(is_string($result))mobileAjaxReturn(['status' => 0, 'info' =>$result]);
        mobileAjaxReturn(['status' => 1, 'info' =>lang('lan_operation_success')]);
    }
}