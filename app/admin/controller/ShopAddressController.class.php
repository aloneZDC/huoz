<?php
namespace Admin\Controller;

class ShopAddressController extends AdminController{
	public function _initialize(){
		parent::_initialize();
		$this->member=M('member');
		$this->address=M('shop_address');
	}
	//空操作
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		$this->display('Public:404');
	}
	
	//地址展示页
	public function index(){
		$count = $this->address->join('left join yang_member on yang_member.member_id=yang_shop_address.member_id')->count();
		$Page  = new \Think\Page($count,12);
		$show  = $Page->show();
		$list=$this->address->field('yang_shop_address.*,yang_member.nick')->join('left join yang_member on yang_member.member_id=yang_shop_address.member_id')->limit($Page->firstRow.','.$Page->listRows)->select();
		$this->assign('list',$list);		
		$this->assign('empty','暂无数据');
		$this->assign('page',$show);
		$this->display();
	}
	
}