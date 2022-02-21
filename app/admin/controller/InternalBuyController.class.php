<?php
namespace Admin\Controller;
use Common\Controller\CommonController;
use Think\Page;
use Think\Exception;

class InternalBuyController extends AdminController {
    public function index(){
        $phone=I('phone');
        $member_id = I('member_id');
        
        if(!empty($phone)) {
            if(checkEmail($phone)) {
                $where['b.email'] = $phone;
            } else {
                $where['b.phone'] = $phone;
            }
        }
        if(!empty($member_id)) $where['a.member_id'] = $member_id;

        $count      = M('gac_internal_buy')->alias('a')->join('LEFT JOIN yang_member as b on a.member_id = b.member_id ')->where($where)->count();
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('phone'=>$phone,'member_id'=>$member_id));
         
        $show       = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M('gac_internal_buy')->alias('a')->field('a.*,b.phone as attend_phone,b.name as attend_name,b.email as attend_email,c.currency_mark as from_mark,d.currency_mark as to_mark')
            ->where($where)
            ->join('LEFT JOIN yang_member as b on a.member_id = b.member_id ')
            ->join('LEFT JOIN yang_currency as c on a.currency_id = c.currency_id ')
            ->join('LEFT JOIN yang_currency as d on a.to_currency_id = d.currency_id ')
            ->order("a.id desc")
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }
}