<?php
namespace Admin\Controller;
use Admin\Controller\AdminController;
class PrizemagController extends AdminController{
	public function _initialize(){
		parent::_initialize();
		$this->currency=M('Currency');
		$this->ordergd=M('yy_order_goods');
	}
	//空操作
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		$this->display('Public:404');
	}
	
	//获奖信息展示页
	public function index(){	
		$count = $this->ordergd->where('win_order=1')->count();
		$Page  = new \Think\Page($count,12);
		$show  = $Page->show();
		$list=$this->ordergd->where('win_order=1')->limit($Page->firstRow.','.$Page->listRows)->select();
		foreach ($list as $key => $v){
			$currency=$this->currency->field('currency_name')->where('currency_id='.$v['currency_id'])->find();
			$list[$key]['cname']=$currency['currency_name'];
		}
		//dump($list);exit;
		$this->assign('list',$list);
		$this->assign('empty','用户未确认');
		$this->assign('page',$show);
		$this->display();
	}
	//设置发货状态
	public function setState(){
		$id = intval(I('id'));
		if(IS_POST){
			if(!empty($_POST['bhz'])){			//备货中
				$order_id = $_POST['id'];
				$data['status']=$_POST['bhz'];
				$data['choice_time']=time();
				//dump($data);
				$rs=$this->ordergd->where('order_id='.$order_id)->save($data);
			}
			if(!empty($_POST['ckz'])){			//出库中
				$order_id = $_POST['id'];
				$data['status']=$_POST['ckz'];
				$data['delivery_time']=time();
				//dump($data);
				$rs=$this->ordergd->where('order_id='.$order_id)->save($data);
			}
			if(!empty($_POST['ykd'])){			//已快递
				$order_id = $_POST['id'];
				$data['status']=$_POST['ykd'];
				$data['expressno']=$_POST['expressno'];
				$data['express_time']=time();
				//dump($data);exit;
				$rs=$this->ordergd->where('order_id='.$order_id)->save($data);
			}
		}
		$list=$this->ordergd->field('order_id,status,choice_time,delivery_time,express_time,expressno')->where('order_id='.$id)->find(); 
		$this->assign('list',$list);		
		$this->display();
	}
}