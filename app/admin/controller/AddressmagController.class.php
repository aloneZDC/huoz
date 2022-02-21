<?php
namespace Admin\Controller;
use Admin\Controller\AdminController;
class AddressmagController extends AdminController{
	public function _initialize(){
		parent::_initialize();
		$this->member=M('member');
		$this->address=M('yy_addressmag');
	}
	//空操作
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		$this->display('Public:404');
	}
	
	//地址展示页
	public function index(){
		$count = $this->address->join('left join yang_member on yang_member.member_id=yang_yy_addressmag.member_id')->count();
		$Page  = new \Think\Page($count,12);
		$show  = $Page->show();
		$list=$this->address->field('yang_yy_addressmag.*,yang_member.nick')->join('left join yang_member on yang_member.member_id=yang_yy_addressmag.member_id')->limit($Page->firstRow.','.$Page->listRows)->select();
		foreach ($list as $k => $v){
			$areaNamec = M('areas')->field('area_name')->where('area_id='.$v['country'])->find();
			$list[$k]['coun'] = $areaNamec['area_name'];
			$areaNamep = M('areas')->field('area_name')->where('area_id='.$v['province'])->find();
			$list[$k]['pro'] = $areaNamep['area_name'];
			$areaNameci = M('areas')->field('area_name')->where('area_id='.$v['city'])->find();
			$list[$k]['cit'] = $areaNameci['area_name'];
			$areaNamed = M('areas')->field('area_name')->where('area_id='.$v['district'])->find();
			$list[$k]['dist'] = $areaNamed['area_name'];
		}
		$this->assign('list',$list);		
		$this->assign('empty','暂无数据');
		$this->assign('page',$show);
		$this->display();
	}
	
}