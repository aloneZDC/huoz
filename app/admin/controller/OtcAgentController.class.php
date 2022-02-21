<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2019/1/8
 * Time: 18:11
 */
namespace Admin\Controller;
use Think\Page;

class OtcAgentController extends AdminController {
    public function index(){
        $phone=I('phone');
        $member_id = I('member_id');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['c.email'] = $phone;
            } else {
                $where['c.phone'] = $phone;
            }
        }
        if(!empty($member_id)){
            $where['c.member_id'] = $member_id;
        }

        $status = I('status');
        if($status) $where['a.status'] = intval($status);

        $field = "a.*,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone,d.pic1,d.pic2,d.pic3";
        $count      = M('otc_agent')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->join('LEFT JOIN yang_verify_file as d on a.member_id = d.member_id ')
            ->where($where)
            ->count();// 查询满足要求的总记录数
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('email'=>$phone,'member_id'=>$member_id));
         
        $show       = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M('otc_agent')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->join('LEFT JOIN yang_verify_file as d on a.member_id = d.member_id ')
            ->where($where)
            ->order(" a.addtime desc ")
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }

    //删除免手续费人员列表
    public function audit() {
        $id = I('id', 0, 'intval');
        $status = I('status',0,'intval');
        $msg = I('msg');
        if($status==2 && empty($msg)) self::output(0, '请填写拒绝理由');
        if($status==1) $msg = '';

        $model = M('otc_agent');
        if (IS_POST) {
            if(empty($id)) $this->error('请选择');

            $info = $model->where(['id'=>$id])->find();
            if(!$info) $this->error('不存在');
            
            $r = $model->where(['id'=>$id])->save(['status'=>$status,'refuse_msg'=>$msg,'admin_id'=>$_SESSION['admin_userid']]);
            if ($r !== false) {
                self::output(1, '操作成功');
            } else {
                self::output(0, '操作失败');
                return;
            }
        } else {
            $this->display();
        }
    }

}