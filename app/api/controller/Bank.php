<?php
/**
 *发送邮件
 */
namespace app\api\controller;
use think\Db;
use think\Exception;

class Bank extends Base
{
    protected $public_action = ['banklist'];
    protected $is_method_filter = true;
    //支持的銀行卡類型
    public function banklist() {
        $list = model('Bank')->banklist($this->lang);
        $this->output(10000,lang('lan_operation_success'),$list);
    }

    //用户收款方式列表
    public function index() {
        $list = model('Bank')->getList($this->member_id,$this->lang);
        $this->output(10000,lang('lan_operation_success'),$list);
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
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
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
        if(is_string($info)) $this->output(10001,$info);

        $this->output(10000,lang('lan_operation_success'),$info);
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
            $upload = $this->base64Upload($img);
            if(is_string($upload)) $this->output(10001,$upload);
            $img = $upload['path'];
        }

        $result = model('Bank')->addLog($this->member_id,$type,$id,$pwd,$name,$cardnum,$img,$bname,$inname,$this->config);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'),$result);
    }
}