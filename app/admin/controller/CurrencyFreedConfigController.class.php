<?php
namespace Admin\Controller;
use Common\Controller\CommonController;
use Think\Page;
use Think\Exception;

class CurrencyFreedConfigController extends AdminController {
    //空操作
    public function _empty(){
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    //配置列表
    public function setting() {
        $where = [];

        $member_id=I('member_id');
        $currency_id=I('currency_id');
        $phone=I('phone');
        if(!empty($member_id)) $where['a.member_id'] = $member_id;
        if(!empty($currency_id)) $where['a.currency_id'] = $currency_id;
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['c.email'] = $phone;
            } else {
                $where['c.phone'] = $phone;
            }
        }

        $count      = M('currency_lock_freed_config')->alias('a')->where($where)->count();// 查询满足要求的总记录数
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        setPageParameter($Page, array('currency_id'=>$currency_id,'member_id'=>$member_id,'phone'=>$phone));

        $list = M('currency_lock_freed_config')->alias('a')->field('a.*,b.currency_name,c.phone,c.email,c.name')->where($where)->join('left join __CURRENCY__ b on a.currency_id=b.currency_id')->join('left join __MEMBER__ c on a.member_id=c.member_id')->order('a.id desc')->limit($Page->firstRow.','.$Page->listRows)->select();

        $currency = M('Currency')->field('currency_name,currency_id')->select();
        $this->assign('currency',$currency);
        $this->assign('list',$list);
        $show       = $Page->show();
        $this->assign('page',$show);
        $this->display();
    }

    //添加配置
    public function add_setting() {
        $id = I('id',0,'intval');        
        if(IS_POST){
            $model = M('currency_lock_freed_config');
            $data = [
                'currency_id' => I('currency_id',0,'intval'),
                'member_id' => I('member_id','','intval'),
                'rate'=> I('rate','','floatval'),
            ];
            $member = M('member')->field('member_id')->where(['member_id'=>$data['member_id']])->find();
            if(!$member) $this->error('用户不存在');

            if($data['rate']<=0) $this->error('释放比例不能为空');

            if(empty($id)) {
                $log = $model->where(['member_id'=>$data['member_id'],'currency_id'=>$data['currency_id']])->find();
                if($log) $this->error('该币种用户配置已存在');
                
                $result = $model->add($data);
            } else {
                $result = $model->where(['id'=>$id])->save($data); 
            }
            
            if($result!==false){
                $this->success(L('lan_operation_success'));
            } else {
                $this->error(L('lan_network_busy_try_again'));
            }
        } else {
            $info = [];
            if(!empty($id)) $info = M('currency_lock_freed_config')->where(['id'=>$id])->find();
            $this->assign('list',$info);
            $this->assign('currency',M('currency')->select());
            $this->display();
        }
    }

    public function del_setting() {
        $id = I('id',0,'intval');
        if(empty($id)) self::output(0, L('lan_Illegal_operation'));

        $model = M('currency_lock_freed_config');
        $info = $model->where(['id'=>$id])->find();
        if(empty($info)) self::output(0, L('lan_Illegal_operation'));

        $result = $model->where(['id'=>$id])->delete();
        if(!$result) {
            self::output(0, L('lan_network_busy_try_again'));
        } else {
            self::output(1, L('lan_operation_success'));
        }
    }

    public function member_info() {
        $member_id = I('member_id',0,'intval');
        if(empty($member_id)) self::output(0, '请填写用户ID');

        $member = M('member')->field('member_id,phone,email,name')->where(['member_id'=>$member_id])->find();
        if(!$member) self::output(0, '该用户不存在');

        $name = '';
        if(!empty($member['name'])){
            $name = $member['name'];
        } elseif(!empty($member['phone'])) {
            $name = $member['phone'];
        } elseif (!empty($member['email'])) {
            $name = $member['email'];
        }
        self::output(1, '成功',['name'=>$name]);
    }
}