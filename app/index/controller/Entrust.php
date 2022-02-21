<?php
namespace app\index\controller;
class Entrust extends TradeFather {
	public function _initialize(){
		parent::_initialize();
	}
	//空操作
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		return $this->fetch('Public:404');
	}
    //委托管理
	public function manage(){
	    $this->User_status();//判断是否需要进行信息补全
		//获取主积分类型
		$currency=$this->currency();
		$this->assign('culist',$currency);
		$currency_trade_list = ['XRP','USDT','ETH','BTC'];
		$culist_trade = [];
		foreach($currency as $key=>$value){
			if(in_array($value['currency_mark'],$currency_trade_list)){
				$culist_trade[] = $value;
			}
		}
		$this->assign('culist_trade',$culist_trade);
		$currency_trade = intval(input('currency_trade'));
		$currencytype = intval(input('currency'));
		$status=intval(input('status'));
		$search = [
			'currency' => $currencytype,
			'currency_trade' => $currency_trade,
			'status' => $status,
			'_status' => input('status')
		];
		if(input('status')==='0'){
			$search['status'] =2;
		}
		if(!empty($currencytype)){
			$where['currency_id'] =$currencytype;
		}
		if(!empty($currency_trade)){
			$where['currency_trade_id'] =$currency_trade;
		}
        $where['status'] =array('in',"$status");
		if($status == 2){
            $where['status'] = array('in','0,1');
        }
		$where['member_id'] = session('USER_KEY_ID');


		$count      = db('Orders')->where($where)->count();// 查询满足要求的总记录数
		$Page       = new \Think\Page($count,10);// 实例化分页类 传入总记录数和每页显示的记录数
		
		//给分页传参数
		setPageParameter($Page, array('currency'=>$currencytype, 'currency_trade'=>$currency_trade, 'status'=>$status));
		
		$show       = $Page->show();// 分页显示输出
		// 进行分页数据查询 注意limit方法的参数要使用Page类的属性
		$list = db('Orders')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select();

		$this->assign('page',$show);// 赋值分页输出
		$this->assign('list',$list);
		$this->assign('search',$search);
		return $this->fetch();
     }
     
    //委托历史
    public function history(){
        $this->User_status();//判断是否需要进行信息补全
    	//获取主积分类型
		$currency=$this->currency();
		$this->assign('culist',$currency);
		$currency_trade_list = ['KOK','USDT','ETH','BTC'];
		$culist_trade = [];
		foreach($currency as $key=>$value){
			if(in_array($value['currency_mark'],$currency_trade_list)){
				$culist_trade[] = $value;
			}
		}
		$this->assign('culist_trade',$culist_trade);
		$currency_trade = intval(input('currency_trade'));
		$currencytype = intval(input('currency'));
		$status=intval(input('status'));
		$search = [
			'currency' => $currencytype,
			'currency_trade' => $currency_trade,
			'status' => $status
		];
   	   if(!empty($currencytype)){
			$where['currency_id'] =$currencytype;
		}
		if(!empty($currency_trade)){
			$where['currency_trade_id'] =$currency_trade;
		}
		
		$where['status'] = array('in','-1,2');
		$where['member_id'] = session('USER_KEY_ID');
		
    	if(!empty($status)){
			$where['status'] =array('in',"$status");
		}
		
    	$count      = db('Orders')->where($where)->count();// 查询满足要求的总记录数
		$Page       = new \Think\Page($count,10);// 实例化分页类 传入总记录数和每页显示的记录数
		
		//给分页传参数
		setPageParameter($Page, array('currency'=>$currencytype, 'currency_trade'=>$currency_trade, 'status'=>$status));
		
		$show       = $Page->show();// 分页显示输出
		// 进行分页数据查询 注意limit方法的参数要使用Page类的属性
		$list = db('Orders')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select();
		
		$this->assign('page',$show);// 赋值分页输出
    
    	$this->assign('list',$list);
		$this->assign('search',$search);
     	return $this->fetch();
     }
     
     /**
      *  撤销方法
      */
     public function cancel(){
     		$order_id = intval(input('post.order_id'));     		
     		if(empty($order_id)){
     			$info['status'] = 0;
     			$info['info'] = '撤销订单不正确';
     			mobileAjaxReturn($info);
     		}
     		//获取人的一个订单
     		$one_order=$this->getOneOrdersByMemberAndOrderId(session('USER_KEY_ID'), $order_id,array(0,1));
     		if(empty($one_order)){
     			$info['status'] = -1;
     			$info['info'] = '传入信息错误';
                mobileAjaxReturn($info);
     		}
     		$info = 	$this ->cancelOrdersByOrderId($one_order);
         mobileAjaxReturn($info);
     		
     }
     
}