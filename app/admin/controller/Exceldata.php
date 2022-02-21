<?php
/*
 * 后台审核提现
 */
namespace app\admin\controller;
use think\Db;

class Exceldata extends Admin {
	// 空操作
	public function _empty() {
		header ( "HTTP/1.0 404 Not Found" );
		$this->display ( 'Public:404' );
	}
	
	//财务日志
	public function payexcel(){
	    $type_id=I('type_id');
	    $name=I('name');
	    $member_id=I('member_id');
	    $where=null;
	    if(!empty($type_id)){
	        $where['type']=$type_id;
	    }
	    if(!empty($name)){
	       // $uid=Db::name('Member')->where("name like '%{$name}%'")->find();
	        $where['m.phone|m.email']=$name;
	    }
	    if(!empty($member_id)){
	        $where['m.member_id']=$member_id;
	    }
	    
	    //筛选
	    $type=Db::name('Finance_type')->select();
	    $this->assign('type',$type);
	    //显示日志
	    $list=Db::name('Finance')->alias("f")
	    ->field('f.*,m.name as username,c.currency_name,ft.name as typename')
            ->join(config("database.prefix")."member m","m.member_id=f.member_id","LEFT")
            ->join(config("database.prefix")."finance_type ft","ft.id=f.type","LEFT")
            ->join(config("database.prefix")."currency c","c.currency_id=f.currency_id","LEFT")
	    ->where($where)
	    ->order('add_time desc')->paginate(25,null,['query'=>input()])->each(function ($item,$key){
                if($item['currency_id']==0){
                    $item['currency_name']=$this->config['xnb_name'];
                }
	        return $item;
            });
	   $show=$list->render();
	    $this->assign('empty','暂未查询到数据');
	    $this->assign('list',$list);
	    $this->assign ( 'page', $show ); // 赋值分页输出
	   return $this->fetch();
	}
	//人工充值审核页面
	public function payByMan(){
	     
	    $uid=$_SESSION['admin_userid'];
	     
	    $admin_username=M('Admin')->where('admin_id='.$uid.'')->field('username,admin_id')->select();
	     
	    $this->username=$admin_username;
	    $status=I('status');
	    $member_name=I('member_name');
	    $member_phone=I('member_phone');
	    $member_email=I('member_email');
	    $member_id=I('member_id');
	    if(I('mit')){
	        $due_bank=I('due_bank');
	        $batch=I('batch');
	        $capital=I('capital');
	        $hid=I('hid');
	        $payMit = M('Pay');
	        $condition['pay_id']=$hid;
	        $data['due_bank']=$due_bank;
	        $data['batch']=$batch;
	        $data['capital']=$capital;
	        $data['commit_name']=$admin_username[0]['username'];
	        $data['commit_time']=time();
	        $rs=$payMit->where($condition)->save($data);
	         
	        if($rs != false){
	            $this->success('提交成功');
	        }else{
	            $this->errror('提交失败');
	        }
	    }
	
	    if(!empty($status)||$status==="0"){
	        $where['yang_pay.status']=$status;
	    }
	    if(!empty($member_name)){
	        $where['yang_pay.member_name']=array('like',"%".$member_name."%");
	        	
	    }
	    if(!empty($member_phone)){
	         
	        $where['yang_pay.phone']=array('like',"%".$member_phone."%");
	        	
	    }
	
	    if(!empty($member_email)){
	
	
	        $where['yang_pay.email']=array('like',"%".$member_email."%");
	    }
	
	    if(!empty($member_id)){
	        $where['yang_pay.member_id']=array('like',"%".$member_id."%");
	    }
	
	
	     
	    $count =  M('Pay')->where($where)->join('left join yang_member on yang_member.member_id=yang_pay.member_id')->count();
	
	    $Page  = new \Think\Page($count,20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
	    //给分页传参数
	    setPageParameter($Page, array('status'=>$status,'member_name'=>$member_name,'member_phone'=>$member_phone,'member_email'=>$member_email,'member_id'=>$member_id));
	    $show       = $Page->show();// 分页显示输出
	    $list= M('Pay')
	    ->field('yang_pay.*,yang_member.email,yang_member.phone')
	    ->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
	    ->where($where)
	    ->limit($Page->firstRow.','.$Page->listRows)
	    ->order('add_time desc')
	    ->select();
	    //dump($list,true,'<pre>',false);exit;
	    foreach ($list as $k=>$v){
	        $list[$k]['status']=payStatus($v['status']);
	    }
	    $this->assign('page',$show);
	    $this->assign('list',$list);
	    $this->assign('empty','暂无数据');
	    $this->display();
	}
	
	/**
	 * 挂单记录
	 */
	public function trade(){
//	    $type=I('type');
//	    $currency_id=I('currency_id');
//	    $email=I('email');
//
//	    $datePicker=strtotime(I('datePicker'));
//	    $datePicker2=strtotime(I('datePicker2'));
//	    if(!empty($type)){
//	        $where['a.type'] = array("EQ",$type);
//	    }
//	    if(!empty($currency_id)){
//	        $where['a.currency_id'] = array("EQ",$currency_id);
//	    }
//	    if(!empty($email)){
//	        $where['c.email'] = array('like',"%".$email."%");
//	    }
//	    if(!empty($datePicker) && !empty($datePicker2)  ){
//	        $where['a.add_time'] = array('between',array($datePicker,$datePicker2));
//	    }
//
//	    $field = "a.*,b.currency_name as b_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
//	    $count      = M('Trade')
//	    ->alias('a')
//	    ->field($field)
//	    ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
//	    ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
//	    ->where($where)
//	    ->count();// 查询满足要求的总记录数
//	    $Page       = new \Think\Page ($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
//	    //给分页传参数
//	    setPageParameter($Page, array('type'=>$type,'currency_id'=>$currency_id,'email'=>$email));
//
//	    $show       = $Page->show();// 分页显示输出
//	    // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
//	    $list = M('Trade')
//	    ->alias('a')
//	    ->field($field)
//	    ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
//	    ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
//	    ->where($where)
//	    ->order(" a.add_time desc ")
//	    ->limit($Page->firstRow.','.$Page->listRows)
//	    ->select();
//	    if($list){
//	        foreach ($list as $key=>$vo) {
//	            $list[$key]['type_name'] = getOrdersType($vo['type']);
//	        }
//	    }
	    //积分类型
	    $currency = Db::name('Currency')->field('currency_name,currency_id')->select();
	    $this->assign('currency',$currency);
//	    $this->assign('list',$list);
//	    $this->assign('page',$show);// 赋值分页输出
	    return $this->fetch();
	}
	
	
	
	/**
	 * 充值记录
	 */
	public function chongzhi_index(){
	    $curr=Db::name("Currency")->select();
	    $this->assign("curr",$curr);
	
	    return $this->fetch();
	}
	
	public function tibi_index(){
	    $curr=Db::name("Currency")->select();
	    $this->assign("curr",$curr);
	
	   return $this->fetch();
	}
	
	public function address_index(){
	    $cuid=I('cuid');
	
	    $email=I('email');
	    $member_id=I('member_id');
	    $status=I('status');
	    $url=I('url');
	
	    if(!empty($cuid)){
	        if($cuid==1){
	
	        }else{
	            $where['yang_tibi.currency_id']=I("cuid");
	        }
	        $this->assign("id",I("cuid"));
	    }
	    if(!empty($cuid)){
	        $name=M("Member")->where("email='{$email}'")->find();
	        //$where['yang_tibi.user_id']=$name['member_id'];
	    }
	    $where['yang_tibi.status']=array("in",array(2,3,4,5));
	    if(!empty($email)){
	        $where['yang_member.email'] = array('like','%'.$email.'%');
	    }
	    if(!empty($member_id)){
	        $where['yang_member.member_id'] = $member_id;
	    }
	    if(!empty($status)){
	        $where['yang_tibi.status'] = $status;
	    }
	    if(!empty($url)){
	        $where['yang_tibi.url'] = array('like','%'.$url.'%');
	    }
	    $field="yang_tibi.*,yang_member.email,yang_member.member_id,yang_member.name,yang_member.phone,yang_currency.currency_name";
	    $count      = M("Tibi")->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
	    ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->count();// 查询满足要求的总记录数
	    $Page       = new \Think\Page($count,20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
	    //给分页传参数
	    setPageParameter($Page, array('cuid'=>I("cuid"),'email'=>I('email'),'member_id'=>I('member_id'),'status'=>I('status'),'url'=>I('url')));
	    $show       = $Page->show();// 分页显示输出
	    // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
	    $list = M("Tibi")->field($field)->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
	    ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->order("add_time desc")->limit($Page->firstRow.','.$Page->listRows)->select();
	    $this->assign('list',$list);// 赋值数据集
	    $this->assign('page',$show);// 赋值分页输出
	
	
	    //读取积分类型表
	
	    $curr=M("Currency")->select();
	    $this->assign("curr",$curr);
	
	    $this->display();
	}
	
	public function member_index()
	{
	    
	    $this->assign('list', $list);// 赋值数据集
	    $this->assign('page', $show);// 赋值分页输出
	    $this->display(); // 输出模板
	}
	public function member_award_index()
	{
	     
	    $this->assign('list', $list);// 赋值数据集
	    $this->assign('page', $show);// 赋值分页输出
	    $this->display(); // 输出模板
	}
	
	/**
	 * 委托记录
	 */
	public function orders(){
//	    $status_id=I('status_id');
//	    $currency_id=I('currency_id');
//	    $email=I('email');
//
//	    if(!empty($currency_id)){
//	        $where['a.currency_id'] = array("EQ",$currency_id);
//	    }
//	    if(!empty($status_id)||$status_id==="0"){
//	        $where['a.status'] = array('EQ',$status_id);
//	    }
//	    if(!empty($email)){
//	        $where['c.email'] = array('like',"%".$email."%");
//	    }
//
//
//	    $field = "a.*,b.currency_name as b_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
//	    $count      = M('Orders')
//	    ->alias('a')
//	    ->field($field)
//	    ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
//	    ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
//	    ->where($where)
//	    ->order(" a.add_time desc ")->count();// 查询满足要求的总记录数
//	    $Page       = new \Think\Page ($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
//	    //给分页传参数
//	    setPageParameter($Page, array('status_id'=>$status_id,'currency_id'=>$currency_id,'email'=>$email));
//
//	    $show       = $Page->show();// 分页显示输出
//	    // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
//	    $list = M('Orders')
//	    ->alias('a')
//	    ->field($field)
//	    ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
//	    ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
//	    ->where($where)
//	    ->order(" a.add_time desc ")
//	    ->limit($Page->firstRow.','.$Page->listRows)
//	    ->select();
//	    //积分类型
	    $currency = Db::name('Currency')->field('currency_name,currency_id')->select();
	    $this->assign('currency',$currency);
//	    $this->assign('list',$list);
//	    $this->assign('page',$show);// 赋值分页输出
	   return $this->fetch();
	}
	public function pendingindex() {
		$withdraw = M ( 'Withdraw' );
		$bank = M ( 'bank' );
		$name = I('cardname');
		$names = I('keyname');
		$keyid = I('keyid');
		$keynum = I('keynum');
		//ajax中的提交值
		$currencyId = I('currency_id');
		// I('status')--分页下标生成参数
		if(I('pend')!=0 || I ( 'status' )!=0){
			$where ['yang_withdraw.status'] = I ( 'status' ) ? I ( 'status' ) : I ( 'pend' );
		}
		if(!empty($name) || !empty($names)){
			// 如果传回的是post（keyname）就用post，否则用get（cardname）
			$cardname = I ( 'keyname' )? I ( 'keyname' ):I('cardname');
			//模糊
			$where ['yang_bank.cardname'] = array('like','%'.$cardname.'%');
		}
		if(!empty($keyid)){
			$where ['yang_Withdraw.uid'] = array('like','%'.$keyid.'%');
		}
		if(!empty($keynum)){
			$where ['email'] = array('like','%'.$keynum.'%');
		}
		// 查询满足要求的总记录数
		$count = $withdraw->join ( "yang_bank ON yang_withdraw.bank_id = yang_bank.id" )->join("yang_member on yang_withdraw.uid=yang_member.member_id")->where ( $where )->count (); 
		// 实例化分页类 传入总记录数和每页显示的记录数
		$Page = new \Think\Page ( $count, 20 ); 
		//将分页（点击下一页）需要的条件保存住，带在分页中
		$Page->parameter = array (
				'yang_withdraw.status' => $where ['yang_withdraw.status'],
				'yang_bank.cardname' =>  $cardname,
				'yang_Withdraw.uid' => $keyid,
				'yang_member.email' => $keynum
		);
		// 分页显示输出
		$show = $Page->show (); 
		//需要的数据
		$field = "yang_withdraw.*,yang_bank.cardname,yang_bank.cardnum,yang_bank.bankname,b.area_name as barea_name,a.area_name as aarea_name,c.email";
		$info = $withdraw->field ( $field )
				->join ( "left join yang_bank ON yang_withdraw.bank_id = yang_bank.id" )
				->join("left join yang_areas as b ON b.area_id = yang_bank.address")
				->join("left join yang_areas as a ON a.area_id = b.parent_id ")
				->join("left join yang_member as c on yang_withdraw.uid=c.member_id")
				->where ( $where )
				->order ( 'yang_withdraw.add_time desc' )
				->limit ( $Page->firstRow . ',' . $Page->listRows )
				->select ();
		$this->assign ( 'info', $info ); // 赋值数据集
		$this->assign ( 'page', $show ); // 赋值分页输出
		$this->assign ( 'inquire', $cardname );
		$this->display ();
	}	
	
	public function pendingindex2() {
	    $withdraw = M ( 'Withdraw' );
	    $bank = M ( 'bank' );
	    $name = I('cardname');
	    $names = I('keyname');
	    $keyid = I('keyid');
	    $keynum = I('keynum');
	    //ajax中的提交值
	    $currencyId = I('currency_id');
	    // I('status')--分页下标生成参数
	    if(I('pend')!=0 || I ( 'status' )!=0){
	        $where ['yang_withdraw.status'] = I ( 'status' ) ? I ( 'status' ) : I ( 'pend' );
	    }
	    if(!empty($name) || !empty($names)){
	        // 如果传回的是post（keyname）就用post，否则用get（cardname）
	        $cardname = I ( 'keyname' )? I ( 'keyname' ):I('cardname');
	        //模糊
	        $where ['yang_bank.cardname'] = array('like','%'.$cardname.'%');
	    }
	    if(!empty($keyid)){
	        $where ['yang_Withdraw.uid'] = array('like','%'.$keyid.'%');
	    }
	    if(!empty($keynum)){
	        $where ['email'] = array('like','%'.$keynum.'%');
	    }
	    // 查询满足要求的总记录数
	    $count = $withdraw->join ( "yang_bank ON yang_withdraw.bank_id = yang_bank.id" )->join("yang_member on yang_withdraw.uid=yang_member.member_id")->where ( $where )->count ();
	    // 实例化分页类 传入总记录数和每页显示的记录数
	    $Page = new \Think\Page ( $count, 20 );
	    //将分页（点击下一页）需要的条件保存住，带在分页中
	    $Page->parameter = array (
	        'yang_withdraw.status' => $where ['yang_withdraw.status'],
	        'yang_bank.cardname' =>  $cardname,
	        'yang_Withdraw.uid' => $keyid,
	        'yang_member.email' => $keynum
	    );
	    // 分页显示输出
	    $show = $Page->show ();
	    //需要的数据
	    $field = "yang_withdraw.*,yang_bank.cardname,yang_bank.cardnum,yang_bank.bankname,b.area_name as barea_name,a.area_name as aarea_name,c.email";
	    $info = $withdraw->field ( $field )
	    ->join ( "left join yang_bank ON yang_withdraw.bank_id = yang_bank.id" )
	    ->join("left join yang_areas as b ON b.area_id = yang_bank.address")
	    ->join("left join yang_areas as a ON a.area_id = b.parent_id ")
	    ->join("left join yang_member as c on yang_withdraw.uid=c.member_id")
	    ->where ( $where )
	    ->order ( 'yang_withdraw.add_time desc' )
	    ->limit ( $Page->firstRow . ',' . $Page->listRows )
	    ->select ();
	    $this->assign ( 'info', $info ); // 赋值数据集
	    $this->assign ( 'page', $show ); // 赋值分页输出
	    $this->assign ( 'inquire', $cardname );
	    $this->display ();
	}

	/**
	 * 通过提现请求
	 * @param unknown $id
	 */
	public function successByid(){		
		$id = intval ( I ( 'post.id' ) );
			//判断是否$id为空
			if (empty ( $id ) ) {
				$datas['status'] = 3;
			    $datas['info'] = "参数错误";
			    $this->ajaxReturn($datas);
			}
		//查询用户可用金额等信息
		$info = $this->getMoneyByid($id);
		if($info['status']!=3){
			$datas['status'] = -1;
			$datas['info'] = "请不要重复操作";
			$this->ajaxReturn($datas);
		}
		//通过状态为2
		$data ['status'] = 2;
		$data ['check_time'] = time();
		$data ['admin_uid'] =$_SESSION['admin_userid'];
		//更新数据库
		$re = M ( 'Withdraw' )->where ( "withdraw_id = '{$id}'" )->save ( $data );	
		$num= M ( 'Withdraw' )->where ( "withdraw_id = '{$id}'" )->find ();
		M('Member')->where("member_id={$num['uid']}")->setDec('forzen_rmb',$num['all_money']);	
		if($re == false){
			$datas['status'] = 0;
			$datas['info'] = "提现操作失败";
			$this->ajaxReturn($datas);
		}
		$this->addMessage_all($info['member_id'],-2,'CNY提现成功',"恭喜您提现{$info['all_money']}成功！");
		$this->addFinance($info['member_id'],23,"提现{$info['all_money']}",$info['withdraw_fee'],2,0);
		$datas['status'] = 1;
		$datas['info'] = "提现通过，操作成功";
		$this->ajaxReturn($datas);
	}

	/**
	 * 不通过提现请求
	 * @param unknown $id
	 */
	public function falseByid(){
		$id = intval ( I ( 'post.id' ) );
			//判断是否$id为空
			if (empty ( $id ) ) {
				$this->error ( "参数错误" );
				return;
			}
		//查询用户可用金额等信息
		$info = $this->getMoneyByid($id);
		if($info['status']!=3){
			$datas['status'] = -1;
			$datas['info'] = "请不要重复操作";
			$this->ajaxReturn($datas);
		}
		//将提现的钱加回可用金额
		$money['rmb'] = floatval($info['rmb']) + floatval($info['all_money']);
		//将冻结的钱减掉
		$money['forzen_rmb'] = floatval($info['forzen_rmb']) - floatval($info['all_money']);
		
		//不通过状态为1
		$data ['status'] = 1;
		$data ['check_time'] = time();
		$data ['admin_uid'] =$_SESSION['admin_userid'];
		//更新数据库,member修改金额
		$res = M( 'Member' )->where("member_id = {$info['member_id']}")->save($money);
		//withdraw修改状态
		$re = M ( 'Withdraw' )->where ( "withdraw_id = '{$id}'" )->save ( $data );
		if($res == false){
			$datas['status'] = 0;
			$datas['info'] = "提现不通过，操作失败";
			$this->ajaxReturn($datas);
		}
		if($re == false){
			$datas['status'] = 2;
			$datas['info'] = "提现不通过，操作失败";
			$this->ajaxReturn($datas);
		}
		$this->addMessage_all($info['member_id'],-2,'CNY提现失败','很抱歉您提现失败，请重新操作或联系客服！');
		$datas['status'] = 1;
		$datas['info'] = "提现不通过，操作成功";
		$this->ajaxReturn($datas);
	}
	
	/**
	 * 获取提现金额信息
	 * @param unknown $id
	 * @return boolean|unknown $rmb 会员号，可用金额，冻结金额，手续费，提现金额
	 */
	public function getMoneyByid($id){

		$field = "yang_member.member_id,yang_member.rmb,yang_member.forzen_rmb,yang_withdraw.status,yang_withdraw.all_money,yang_withdraw.withdraw_fee";
		$rmb = M('Withdraw')
				->field($field)
				->join('yang_member ON yang_withdraw.uid = yang_member.member_id')
				->where("withdraw_id = '{$id}'")
				->find();
		if(empty($rmb)){
			return false;
		}
		return $rmb;
	}
	
	//获取财务管理->人工充值管理中的数据,涉及到yang_member、yang_pay
	public function xiangDan(){
		//取会员ID、将会员ID和状态赋给字段
		$uid=I('uid');
		$where['yang_pay.member_id']=$uid;
		$where['yang_pay.status']=1;
		//取总记录数
		$count =  M('Pay')->where($where)->join('left join yang_member on yang_member.member_id=yang_pay.member_id')->count();
		//取结果
		$list= M('Pay')
		->field('yang_pay.*,yang_member.email,yang_member.phone')
		->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
		->where($where)
		->order('add_time desc')
		->select();
		//将状态字段的数值用文字来表示
		foreach ($list as $k=>$v){
			$list[$k]['status']=payStatus($v['status']);
		}
		//循环打印结果
		for( $i=0; $i<$count; $i++ ){
			$a.='<tr><td>'.$list[$i]["pay_id"].'</td>
                     		<td>'.$list[$i]["email"].'</td>
							<td>'.$list[$i]["member_name"].'</td>
							<td>'.$list[$i]["member_id"].'</td>
							<td>'.$list[$i]["account"].'</td>
							<td>'.$list[$i]["money"].'</td>
							<td>'.$list[$i]["count"].'</td>
							<td>'.$list[$i]["status"].'</td>
							<td>'.date("Y-m-d H:i:s",$list[$i]["add_time"]).'</td>
							<td>'.$list[$i]["due_bank"].'</td>
							<td>'.$list[$i]["batch"].'</td>
							<td>'.$list[$i]["capital"].'</td>
							<td>'.$list[$i]["commit_name"].'</td>
							<td>'.date("Y-m-d H:i:s",$list[$i]["commit_time"]).'</td>
							<td>'.$list[$i]["audit_name"].'</td>
							<td>'.date("Y-m-d H:i:s",$list[$i]["audit_time"]).'</td></tr>';
		}
			
		                         
		$this->ajaxReturn($a);
	}
	
	//获取众筹管理->众筹记录中的数据,涉及到yang_member、yang_issue、yang_issue_log
	public function xiangdan_zc(){
		$uid_zc=I('uid_zc');
		$option_val=I('option_val');
		$where['yang_member.member_id']=$uid_zc;
		if($option_val!==''){
			if($option_val!=-1){
				$where['yang_issue_log.cid']=$option_val;
			}
		}
		$count= M('Issue_log')->join('left join yang_member on yang_member.member_id=yang_issue_log.uid')->join('left join yang_issue on yang_issue.id=yang_issue_log.iid')->where($where)->count();
			$log=M('Issue_log')
			->field('yang_issue_log.*,yang_member.member_id,yang_member.name,yang_issue.title,yang_issue_log.num*yang_issue_log.price as count')
			->join('left join yang_member on yang_member.member_id=yang_issue_log.uid')
			->join('left join yang_issue on yang_issue.id=yang_issue_log.iid')
			->order('add_time desc')
			->where($where)->select();
		foreach ($log as $key=>$vo) {
			$log[$key]['buy_name'] = $vo['buy_currency_id']==0?"人民币":M('Currency')->where("currency_id='{$vo['buy_currency_id']}'")->find()['currency_name'];
		}
		$curName=M("Currency")->field('currency_id,currency_name')->select();
		if($option_val==""){
			$b.='<select name="currency_id" id="select_zc">
                 <option value="-1" selected>全部</option>
					<option value="0">人民币</option>';
		}else{
			if($option_val==-1){
				$b.='<select name="currency_id" id="select_zc">
                 <option value="-1" selected>全部</option>';
			}else{
				$b.='<select name="currency_id" id="select_zc">
                 <option value="-1">全部</option>';
			}
			if($option_val==0){
				$b.='<option value="0" selected>人民币</option>';
			}else{
				$b.='<option value="0">人民币</option>';
			}	
		}		
		foreach($curName as $key=>$cur ){
			if($option_val==$cur["currency_id"]){
				$b.='<option value='.$cur["currency_id"].' id="currencyId" selected>'.$cur["currency_name"].'</option>';
			}else{
				$b.='<option value='.$cur["currency_id"].' id="currencyId">'.$cur["currency_name"].'</option>';
			}
		}

        $b.='</select>|';
		for( $i=0; $i<$count; $i++ ){
			$b.='<tr><td>'.$log[$i]["iid"].'</td>
                     		<td>'.$log[$i]["title"].'</td>
							<td>'.$log[$i]["name"].'</td>
							<td>'.$log[$i]["member_id"].'</td>
							<td>'.$log[$i]["num"].'</td>
							<td>'.$log[$i]["deal"].'</td>
							<td>'.$log[$i]["price"].'</td>
							<td>'.$log[$i]["count"].'</td>
							<td>'.date("Y-m-d H:i:s",$log[$i]["add_time"]).'</td>
							<td>'.$log[$i]["buy_name"].'</td></tr>';
		}
		
		$this->ajaxReturn($b);	
	}
	
	
	//会员管理->会员列表,涉及到yang_member
	public function xiangDan_user(){
		$uid_user=I('uid_user');
		$where['yang_currency_user.member_id']=$uid_user;
		$filed='yang_member.*,yang_currency.currency_name,yang_currency_user.num,yang_currency_user.forzen_num,yang_currency_user.num_award,yang_currency_user.sum_award';
		$count = M('Currency_user')->field($filed)->join('yang_currency ON yang_currency_user.currency_id = yang_currency.currency_id')
				->join('yang_member on yang_currency_user.member_id = yang_member.member_id')
    			->where($where)->count();
		$list_user = M('Currency_user')->field($filed)->join('yang_currency ON yang_currency_user.currency_id = yang_currency.currency_id')
		->join('yang_member on yang_currency_user.member_id = yang_member.member_id')
		->where($where)
		->select();
		$c.='<tr><td>'.$list_user[0]["member_id"].'</td>
                     		<td>'.$list_user[0]["email"].'</td>
							<td>'.$list_user[0]["name"].'</td>
							<td>'.$list_user[0]["phone"].'</td>
							<td>'.$list_user[0]["rmb"].'</td>
							<td>'.$list_user[0]["forzen_rmb"].'</td>
							<td>'.date("Y-m-d H:i:s",$list_user[0]["reg_time"]).'</td></tr>';
		$c.='<tr>
                        <th>积分类型名称</th>
                        <th>持有数量</th>
						<th>冻结数量</th>
						<th>剩余奖励</th>
						<th>总奖励</th> 
                    </tr>';
		for( $i=0; $i<$count; $i++ ){
			$c.='<tr><td>'.$list_user[$i]["currency_name"].'</td>
							<td>'.$list_user[$i]["num"].'</td>
							<td>'.$list_user[$i]["forzen_num"].'</td>
							<td>'.$list_user[$i]["num_award"].'</td>		
							<td>'.$list_user[$i]["sum_award"].'</td></tr>';
		}
			
		$this->ajaxReturn($c);
	}
	
	
	//交易管理->交易记录,涉及到yang_trade、yang_currency、yang_member
	public function xiangDan_jy(){
		$uid_jy=I('uid_jy');
		$option_valjy=I('option_valjy');
		$where['c.member_id']=$uid_jy;
		if($option_valjy!==''){
			if($option_valjy!=-1){
				$where['a.currency_id']=$option_valjy;
			}
		}
		$field = "a.*,b.currency_name as b_name,c.email as email";
		$count = M('Trade')
		->alias('a')
		->field($field)
		->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
		->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
		->where($where)
		->count();
		$list_jy = M('Trade')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->order(" a.add_time desc ")
            ->select();
		foreach ($list_jy as $key=>$vo) {
			$list_jy[$key]['type_name'] = getOrdersType($vo['type']);
		}
		$curName_jy=M('Currency')->field('currency_id,currency_name')->select();
		if($option_valjy==""){
			$d.='<select name="currency_id3" id="select_jy">
                 <option value="-1" selected>全部</option>
					<option value="0">人民币</option>';
		}else{
			if($option_valjy==-1){
				$d.='<select name="currency_id3" id="select_jy">
                 <option value="-1" selected>全部</option>';
			}else{
				$d.='<select name="currency_id3" id="select_jy">
                 <option value="-1">全部</option>';
			}
			if($option_valjy==0){
				$d.='<option value="0" selected>人民币</option>';
			}else{
				$d.='<option value="0">人民币</option>';
			}
		}
		foreach($curName_jy as $key=>$cur ){
			if($option_valjy==$cur["currency_id"]){
				$d.='<option value='.$cur["currency_id"].' id="currencyId3" selected>'.$cur["currency_name"].'</option>';
			}else{
				$d.='<option value='.$cur["currency_id"].' id="currencyId3">'.$cur["currency_name"].'</option>';
			}
		}
		$d.='</select>|';
		for( $i=0; $i<$count; $i++ ){
			$d.='<tr><td>'.$list_jy[$i]["trade_id"].'</td>
                     		<td>'.$list_jy[$i]["trade_no"].'</td>
							<td>'.$list_jy[$i]["email"].'</td>							
							<td>'.$list_jy[$i]["b_name"].'</td>
							<td>'.$list_jy[$i]["num"].'</td>
							<td>'.$list_jy[$i]["price"].'</td>
							<td>'.$list_jy[$i]["money"].'</td>
							<td>'.number_format($list_jy[$i]["fee"]*$list_jy[$i]["price"]*$list_jy[$i]["num"],4,'.','').'</td>
							<td>'.$list_jy[$i]["type_name"].'</td>
							<td>'.date("Y-m-d H:i:s",$list_jy[$i]["add_time"]).'</td></tr>';
		}
		
		$this->ajaxReturn($d);
	}
	
	
	//财务管理->提现审核,涉及到yang_bank、yang_withdraw、yang_areas、yang_member
	public function xiangDan_tx(){
		$uid_tx=I('uid_tx');
		$where['yang_bank.uid']=$uid_tx;
		$where ['yang_withdraw.status']=2;
		$count = M ( 'Withdraw' )->join ( "yang_bank ON yang_withdraw.bank_id = yang_bank.id" )->join("yang_member on yang_withdraw.uid=yang_member.member_id")->where ( $where )->count ();
		$field = "yang_withdraw.*,yang_bank.cardname,yang_bank.cardnum,yang_bank.bankname,b.area_name as barea_name,a.area_name as aarea_name,c.email";
		$list_tx = M ( 'bank' )->field ( $field )
				->join ( "yang_withdraw ON yang_withdraw.bank_id = yang_bank.id" )
				->join("yang_areas as b ON b.area_id = yang_bank.address")
				->join("yang_areas as a ON a.area_id = b.parent_id ")
				->join("yang_member as c on yang_withdraw.uid=c.member_id")
				->where ( $where )
				->order ( 'yang_withdraw.status desc,yang_withdraw.add_time desc' )
				->select ();
		foreach($list_tx as $key=>$v){
			$list_tx[$key]['status'] = $v['status']==2?"通过":"其它";
		}
		for( $i=0; $i<$count; $i++ ){
			$e.='<tr><td>'.$list_tx[$i]["withdraw_id"].'</td>
                     		<td>'.$list_tx[$i]["cardname"].'</td>
							<td>'.$list_tx[$i]["uid"].'</td>
							<td>'.$list_tx[$i]["bankname"].'</td>
							<td>'.$list_tx[$i]["cardnum"].'</td>
							<td>'.$list_tx[$i]["aarea_name"].'&nbsp;&nbsp;'.$list_tx[$i]["barea_name"].'</td>
							<td>'.$list_tx[$i]["all_money"].'</td>
							<td>'.$list_tx[$i]["withdraw_fee"].'</td>
							<td>'.$list_tx[$i]["money"].'</td>
							<td>'.$list_tx[$i]["order_num"].'</td>
							<td>'.date("Y-m-d H:i:s",$list_tx[$i]["add_time"]).'</td>
							<td>'.$list_tx[$i]["status"].'</td></tr>';
		}
		//财务日志
		$e.='|';
		$wherec['yang_finance.member_id'] = $uid_tx;
		$countc = M('Finance')
		->field('yang_finance.*,yang_member.name as username,yang_currency.currency_name,yang_finance_type.name as typename')
		->join('left join yang_member on yang_member.member_id=yang_finance.member_id')
		->join('left join yang_finance_type on yang_finance_type.id=yang_finance.type')
		->join('left join yang_currency on yang_currency.currency_id=yang_finance.currency_id')
		->where ( $wherec )->count ();
		$list=M('Finance')
		->field('yang_finance.*,yang_member.name as username,yang_currency.currency_name,yang_finance_type.name as typename')
		->join('left join yang_member on yang_member.member_id=yang_finance.member_id')
		->join('left join yang_finance_type on yang_finance_type.id=yang_finance.type')
		->join('left join yang_currency on yang_currency.currency_id=yang_finance.currency_id')
		->where($wherec)
		->order('add_time desc')
		->select();
		foreach ($list as $k=>$v){
			if($v['currency_id']==0){
				$list[$k]['currency_name']=$this->config['xnb_name'];
			}
			$list[$k]['moneytype']=$v['money_type']==1 ? '收入' : '支出';
		}
		for( $i=0; $i<$countc; $i++ ){
				$e.='<tr>
	                     		<td>'.$list[$i]["finance_id"].'</td>
								<td>'.$list[$i]["username"].'</td>
								<td>'.$list[$i]["typename"].'</td>
								<td>'.$list[$i]["content"].'</td>
								<td>'.$list[$i]["money"].'</td>
								<td>'.$list[$i]["currency_name"].'</td>
								<td>'.$list[$i]["moneytype"].'</td>			
								<td>'.date("Y-m-d H:i:s",$list[$i]["add_time"]).'</td>
								<td>'.$list[$i]["ip"].'</td></tr>';
		}
		$this->ajaxReturn($e);
	}
	
	
	
	//钱包积分类型管理->提积分记录,涉及到yang_tibi、yang_currency、yang_member
	public function xiangDan_tb(){
		$option_valtb=I('option_valtb');
		$uid_tb=I('uid_tb');
		$where['yang_tibi.user_id']=$uid_tb;
		$where['yang_tibi.status']=1;
		if($option_valtb!==''){
			if($option_valtb!=-1){
				$where['yang_tibi.currency_id']=$option_valtb;
			}
		}
		$count = M("Tibi")->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
		->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->count();
		$field="yang_tibi.*,yang_member.email,yang_currency.currency_name";
		$list_tb = M("Tibi")->field($field)->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
		->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->select();
		foreach($list_tb as $key=>$v){
			$list_tb[$key]['status'] = $v['status']==1?"通过":"等待转出";
		}
		$curName_tb=M('Currency')->field('currency_id,currency_name')->select();
		if($option_valtb==""){
			$f.='<select name="currency_id2" id="select_tb">
                 <option value="-1" selected>全部</option>
					<option value="0">人民币</option>';
		}else{
			if($option_valtb==-1){
				$f.='<select name="currency_id2" id="select_tb">
                 <option value="-1" selected>全部</option>';
			}else{
				$f.='<select name="currency_id2" id="select_tb">
                 <option value="-1">全部</option>';
			}
			if($option_valtb==0){
				$f.='<option value="0" selected>人民币</option>';
			}else{
				$f.='<option value="0">人民币</option>';
			}
		}
		foreach($curName_tb as $key=>$cur ){
			if($option_valtb==$cur["currency_id"]){
				$f.='<option value='.$cur["currency_id"].' id="currencyId2" selected>'.$cur["currency_name"].'</option>';
			}else{
				$f.='<option value='.$cur["currency_id"].' id="currencyId2">'.$cur["currency_name"].'</option>';
			}
		}
		$f.='</select>|';
		for( $i=0; $i<$count; $i++ ){
			$f.='<tr><td>'.$list_tb[$i]["email"].'</td>
                     		<td>'.$list_tb[$i]["currency_name"].'</td>
							<td>'.$list_tb[$i]["url"].'</td>
							<td>'.$list_tb[$i]["num"].'</td>
							<td>'.$list_tb[$i]["actual"].'</td>
							<td>'.date("Y-m-d H:i:s",$list_tb[$i]["add_time"]).'</td>
							<td>'.$list_tb[$i]["status"].'</td></tr>';
		}
	
		$this->ajaxReturn($f);
	}
	
	
	//钱包积分类型管理->充积分记录,涉及到yang_tibi、yang_currency、yang_member
	public function xiangDan_cb(){
		$option_valcb=I('option_valcb');
		$uid_cb=I('uid_cb');
		$where['yang_tibi.user_id']=$uid_cb;
		$where['yang_tibi.status']=3;
		if($option_valcb!==''){
			if($option_valcb!=-1){
				$where['yang_tibi.currency_id']=$option_valcb;
			}
		}
		$count = M("Tibi")->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
		->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->count();
		$field="yang_tibi.*,yang_member.email,yang_currency.currency_name";
		$list_cb = M("Tibi")->field($field)->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
		->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->select();
		foreach($list_cb as $key=>$v){
			$list_cb[$key]['status'] = $v['status']==3?"通过":"等待转出";
		}
		$curName_cb=M('Currency')->field('currency_id,currency_name')->select();
		if($option_valcb==""){
			$f.='<select name="currency_id4" id="select_cb">
                 <option value="-1" selected>全部</option>
					<option value="0">人民币</option>';
		}else{
			if($option_valcb==-1){
				$f.='<select name="currency_id4" id="select_cb">
                 <option value="-1" selected>全部</option>';
			}else{
				$f.='<select name="currency_id4" id="select_cb">
                 <option value="-1">全部</option>';
			}
			if($option_valcb==0){
				$f.='<option value="0" selected>人民币</option>';
			}else{
				$f.='<option value="0">人民币</option>';
			}
		}
		foreach($curName_cb as $key=>$cur ){
			if($option_valcb==$cur["currency_id"]){
				$f.='<option value='.$cur["currency_id"].' id="currencyId4" selected>'.$cur["currency_name"].'</option>';
			}else{
				$f.='<option value='.$cur["currency_id"].' id="currencyId4">'.$cur["currency_name"].'</option>';
			}
		}
		$f.='</select>|';
		for( $i=0; $i<$count; $i++ ){
			$f.='<tr><td>'.$list_cb[$i]["email"].'</td>
                     		<td>'.$list_cb[$i]["currency_name"].'</td>
							<td>'.$list_cb[$i]["url"].'</td>
							<td>'.$list_cb[$i]["num"].'</td>
							<td>'.$list_cb[$i]["actual"].'</td>
							<td>'.date("Y-m-d H:i:s",$list_cb[$i]["add_time"]).'</td>
							<td>'.$list_cb[$i]["status"].'</td></tr>';
		}
	
		$this->ajaxReturn($f);
	}
	
	//excel导出
	public function excel_pending() {
	    $withdraw = M ( 'Withdraw' );
	    $bank = M ( 'bank' );
	    $name = I('cardname');
	    $names = I('keyname');
	    $keyid = I('keyid');
	    $keynum = I('keynum');
	    $datePicker=strtotime(I('datePicker'));
	    $datePicker2=strtotime(I('datePicker2'));
	    //ajax中的提交值
	    $currencyId = I('currency_id');
	    // I('status')--分页下标生成参数
	    if(I('pend')!=0 || I ( 'status' )!=0){
	        $where ['yang_withdraw.status'] = I ( 'status' ) ? I ( 'status' ) : I ( 'pend' );
	    }
	    if(!empty($name) || !empty($names)){
	        // 如果传回的是post（keyname）就用post，否则用get（cardname）
	        $cardname = I ( 'keyname' )? I ( 'keyname' ):I('cardname');
	        //模糊
	        $where ['yang_bank.cardname'] = array('like','%'.$cardname.'%');
	    }
	    if(!empty($keyid)){
	        $where ['yang_Withdraw.uid'] = array('like','%'.$keyid.'%');
	    }
	    if(!empty($keynum)){
	        $where ['email'] = array('like','%'.$keynum.'%');
	    }
	    if(!empty($datePicker) && !empty($datePicker2)  ){
	        $where['yang_withdraw.add_time'] = array('between',array($datePicker,$datePicker2));
	    }
	    // 查询满足要求的总记录数
	    $count = $withdraw->join ( "yang_bank ON yang_withdraw.bank_id = yang_bank.id" )->join("yang_member on yang_withdraw.uid=yang_member.member_id")->where ( $where )->count ();
	    // 实例化分页类 传入总记录数和每页显示的记录数
	    $Page = new \Think\Page ( $count, 20 );
	    //将分页（点击下一页）需要的条件保存住，带在分页中
	    $Page->parameter = array (
	        'yang_withdraw.status' => $where ['yang_withdraw.status'],
	        'yang_bank.cardname' =>  $cardname,
	        'yang_Withdraw.uid' => $keyid,
	        'yang_member.email' => $keynum
	    );
	// 分页显示输出
		$show = $Page->show (); 
		//需要的数据
		$field = "yang_withdraw.*,yang_bank.cardname,yang_bank.cardnum,yang_bank.bankname,b.area_name as barea_name,a.area_name as aarea_name,c.email";
		$info = $withdraw->field ( $field )
				->join ( "left join yang_bank ON yang_withdraw.bank_id = yang_bank.id" )
				->join("left join yang_areas as b ON b.area_id = yang_bank.address")
				->join("left join yang_areas as a ON a.area_id = b.parent_id ")
				->join("left join yang_member as c on yang_withdraw.uid=c.member_id")
				->where ( $where )
				->order ( 'yang_withdraw.add_time desc' )
				//->limit ( $Page->firstRow . ',' . $Page->listRows )
				->select ();
		
		
		foreach ($info as $k=>$v){
		    $info[$k]['cardnum'] =  '’'.$v['cardnum'];
		    $info[$k]['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
		   
		    if($v['status']==1 ){
		         
		        $info[$k]['status']='未通过';
		    }elseif ($v['status']==2){
		        $info[$k]['status']='通过';
		        
		    }elseif ($v['status']==0){
		        
		        $info[$k]['status']='已撤销';
		    }else {
		         
		        $info[$k]['status']='审核中';
		    }
		}
		$this->assign ( 'info', $info ); // 赋值数据集
		$this->assign ( 'page', $show ); // 赋值分页输出
		$this->assign ( 'inquire', $cardname );
		
		
		$xlsName  = "User";
		$xlsCell  = array(
		    array('withdraw_id','ID'),
		     
		    array('cardname','提现人'),
		    array('uid','会员ID'),
		    array('bankname','银行'),
		    array('cardnum','银行账号'),
		    array('aarea_name','银行开户地'),
		    array('all_money','提现金额'),
		    array('withdraw_fee','手续费'),
		    array('money','实际金额'),
		     
		     
		    array('order_num','订单号'),
		    array('add_time','提交时间'),
		     
		    array('status','状态')
		);
		// $xlsModel = M('Post');
		$xlsData  = $info;
		$this->exportExcel($xlsName,$xlsCell,$xlsData);
		
		 
		
		
		
		$this->display ();
	}
	
	/**
	 * 导出excel
	 */
	public function excel_trade(){
	    $type=I('type');
	    $currency_id=I('currency_id');
	    $email=I('email');
	    $datePicker=strtotime(I('datePicker'));
	    $datePicker2=strtotime(I('datePicker2'));
	    if(!empty($type)){
	        $where['a.type'] =$type;
	    }
	    if(!empty($currency_id)){
	        $where['a.currency_id'] = $currency_id;
	    }
	    if(!empty($email)){
	        $where['c.email'] = array('like',"%".$email."%");
	    }
	    if(!empty($datePicker) && !empty($datePicker2)  ){
	        $where['a.add_time'] = array('between',array($datePicker,$datePicker2));
	    }
	    $field = "a.*,b.currency_name as b_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";

	    // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
	    $list = Db::name('Trade')
	    ->alias('a')
	    ->field($field)
            ->join(config("database.prefix")."currency b","b.currency_id=a.currency_id","LEFT")
            ->join(config("database.prefix")."member c","c.member_id=a.member_id","LEFT")
	    ->where($where)
	    ->order("a.add_time desc")
	    ->select();
	    if($list){
	        foreach ($list as $key=>$vo) {
	            $list[$key]['type_name'] = getOrdersType($vo['type']);
	            $list[$key]['add_time'] = date('Y-m-d H:i:s', $vo['add_time']);
	             
	        }
	    }
	    $this->assign('list',$list);
	    $xlsName  = "交易记录";
	    $xlsCell  = array(
	        array('trade_id','成交编号'),
	        array('trade_no','订单号'),
	        array('email','买家email'),
	        array('member_id','会员ID'),
	        array('name','姓名'),
	        array('phone','手机'),
	        array('b_name','积分类型'),
	        array('num','数量'),
	        array('price','单价'),
	        array('money','总价'),
	        array('fee','手续费'),
	        array('type_name','类型'),
	        array('add_time','成交时间')
	    );
	  return export_excel($xlsName,$xlsCell,$list);
	}
	
	/**
	 * 导出excel orders
	 */
	public function excel_orders(){
	    $status_id=I('status_id');
	    $currency_id=I('currency_id');
	    $email=I('email');
	    $datePicker=strtotime(I('datePicker'));
	    $datePicker2=strtotime(I('datePicker2'));
	    if(!empty($currency_id)){
	        $where['a.currency_id'] =$currency_id;
	    }
	    if(!empty($status_id)||$status_id==="0"){
	        $where['a.status'] =$status_id;
	    }
	    if(!empty($email)){
	        $where['c.email'] = array('like',"%".$email."%");
	    }
	    if(!empty($datePicker) && !empty($datePicker2)  ){
	        $where['a.add_time'] = array('between',array($datePicker,$datePicker2));
	    }
	
	    $field = "a.*,b.currency_name as b_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
	    $list = Db::name('Orders')
	    ->alias('a')
	    ->field($field)
            ->join(config("database.prefix")."currency b","b.currency_id=a.currency_id","LEFT")
            ->join(config("database.prefix")."member c","c.member_id=a.member_id","LEFT")
	    ->where($where)
	    ->order("a.add_time desc")
	    ->select();
	    //积分类型
	     
	    if($list){
	        foreach ($list as $key=>$vo) {
	            $list[$key]['type_name'] = getOrdersType($vo['type']);
	            $list[$key]['add_time'] = date('Y-m-d H:i:s', $vo['add_time']);
	            $list[$key]['trade_time'] = date('Y-m-d H:i:s', $vo['trade_time']);
	            $list[$key]['fee']=$vo['num'] * $vo['fee'] * $vo['price'];
	            $list[$key]['status'] = getOrdersStatus($vo['status']);
	        }
	    }
	    $xlsName  = "委托记录";
	    $xlsCell  = array(
	        array('order_id','委托编号'),
	        array('email','用户邮箱'),
	        array('member_id','会员ID'),
	        array('name','姓名'),
	        array('phone','手机'),
	        array('b_name','积分类型'),
	        array('price','价格'),
	        array('num','挂单数量'),
	        array('trade_num','成交数量'),
	        array('fee','手续费'),
	        array('type_name','类型'),
	        array('add_time','挂单时间'),
	        array('trade_time','成交时间'),
	
	        array('status','状态')
	    );
	   return export_excel($xlsName,$xlsCell,$list);
	}
	
	
	public function excel_payByMan(){
	
	    $uid=$_SESSION['admin_userid'];
	
	    $admin_username=M('Admin')->where('admin_id='.$uid.'')->field('username,admin_id')->select();
	
	    $this->username=$admin_username;
	    $status=I('status');
	    $member_name=I('member_name');
	    $member_phone=I('member_phone');
	    $member_email=I('member_email');
	    $member_id=I('member_id');
	    $datePicker=strtotime(I('datePicker'));
	    $datePicker2=strtotime(I('datePicker2'));
	    if(I('mit')){
	        $due_bank=I('due_bank');
	        $batch=I('batch');
	        $capital=I('capital');
	        $hid=I('hid');
	        $payMit = M('Pay');
	        $condition['pay_id']=$hid;
	        $data['due_bank']=$due_bank;
	        $data['batch']=$batch;
	        $data['capital']=$capital;
	        $data['commit_name']=$admin_username[0]['username'];
	        $data['commit_time']=time();
	        $rs=$payMit->where($condition)->save($data);
	
	        if($rs != false){
	            $this->success('提交成功');
	        }else{
	            $this->errror('提交失败');
	        }
	    }
	     
	    if(!empty($status)||$status==="0"){
	        $where['yang_pay.status']=$status;
	    }
	    if(!empty($member_name)){
	        $where['yang_pay.member_name']=array('like',"%".$member_name."%");
	
	    }
	    if(!empty($member_phone)){
	
	        $where['yang_pay.phone']=array('like',"%".$member_phone."%");
	
	    }
	     
	    if(!empty($member_email)){
	         
	         
	        $where['yang_pay.email']=array('like',"%".$member_email."%");
	    }
	     
	    if(!empty($member_id)){
	        $where['yang_pay.member_id']=array('like',"%".$member_id."%");
	    }
	    if(!empty($datePicker) && !empty($datePicker2)  ){
	        $where['add_time'] = array('between',array($datePicker,$datePicker2));
	    }
	     
	
	    $count =  M('Pay')->where($where)->join('left join yang_member on yang_member.member_id=yang_pay.member_id')->count();
	     
	    $Page  = new \Think\Page($count,20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
	    //给分页传参数
	    setPageParameter($Page, array('status'=>$status,'member_name'=>$member_name,'member_phone'=>$member_phone,'member_email'=>$member_email,'member_id'=>$member_id));
	    $show       = $Page->show();// 分页显示输出
	    $list= M('Pay')
	    ->field('yang_pay.*,yang_member.email,yang_member.phone')
	    ->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
	    ->where($where)
	    //->limit($Page->firstRow.','.$Page->listRows)
	    ->order('add_time desc')
	    ->select();
	    //dump($list,true,'<pre>',false);exit;
	    foreach ($list as $k=>$v){
	        $list[$k]['status']=payStatus($v['status']);
	        $list[$k]['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
	        $list[$k]['trade_time'] = date('Y-m-d H:i:s', $v['trade_time']);
	        $list[$k]['commit_time'] = date('Y-m-d H:i:s', $v['commit_time']);
	        $list[$k]['account'] =  '’'.$v['account'];
	        $list[$k]['batch'] =  '’'.$v['batch'];
	        if($v['type']==13 || $v['currency_id']>0){
	             
	            $list[$k]['type']='充积分';
	        }else {
	             
	            $list[$k]['type']='充值';
	        }
	    }
	    $this->assign('page',$show);
	    $this->assign('list',$list);
	    $this->assign('empty','暂无数据');
	     
	    $xlsName  = "User";
	    $xlsCell  = array(
	        array('pay_id','订单号'),
	         
	        array('email','汇款人账号'),
	        array('member_id','汇款人ID'),
	        array('member_name','汇款人'),
	        array('phone','手机'),
	        array('account','银行卡号'),
	        array('money','充值钱数'),
	        array('count','实际打款'),
	        array('status','状态'),
	         
	         
	        array('type','充值类型'),
	        array('add_time','时间'),
	         
	        array('due_bank','收款行'),
	        array('batch','交易流水号'),
	        array('capital','实收资金'),
	         
	        array('commit_name','提交账号'),
	        array('commit_time','提交时间'),
	        array('audit_time','审核账号'),
	        array('audit_time','审核时间')
	    );
	    // $xlsModel = M('Post');
	    $xlsData  = $list;
	    $this->exportExcel($xlsName,$xlsCell,$xlsData);
	
	     
	     
	    $this->display();
	}
	 
	//excel财务日志
	public function excel_pay_index(){
	    $type_id=I('type_id');
	    $name=I('name');
	    $member_id=I('member_id');
	    $datePicker=strtotime(I('datePicker'));
	    $datePicker2=strtotime(I('datePicker2'));
	    $where=null;
	    if(!empty($type_id)){
	        $where['type']=$type_id;
	    }
	    if(!empty($name)){
	        $where['m.email|m.phone']=$name;
	    }
	    if(!empty($member_id)){
	        $where['m.member_id']=$member_id;
	    }
	    if(!empty($datePicker) && !empty($datePicker2)  ){
	        $where['f.add_time'] = array('between',array($datePicker,$datePicker2));
	    }
	
	    //筛选
        $field="f.finance_id,m.name as username,ft.name as typename,f.content,f.money,f.add_time,f.currency_id,f.money_type,c.currency_name";
	    $list=Db::name('Finance')->alias("f")
	    ->field($field)
            ->join(config("database.prefix")."member m","m.member_id=f.member_id","LEFT")
            ->join(config("database.prefix")."finance_type ft","ft.id=f.type","LEFT")
            ->join(config("database.prefix")."currency c","c.currency_id=f.currency_id","LEFT")
	    ->where($where)
	    ->order('add_time desc')
	    ->select();
	    foreach ($list as $k=>$v){
	        if($v['currency_id']==0){
                $list[$k]['currency_name']="人民币";
            }
//	            $list[$k]['currency_name']=$this->config['xnb_name'];
	            $list[$k]['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
	            if($v['money_type']==1 ){
	
	                $list[$k]['money_type']='收入';
	            }else {
	
	                $list[$k]['money_type']='支出';
	            }
//	        }
            unset($list[$k]['currency_id']);

	    }
//        $xlsCell = array(
//            '日志编号',
//            '所属',
//            '财务类型',
//            '内容',
//            '金额',
//            '时间',
//            '收入/支出',
//            '币种',
//        );
        $xlsCell  = array(
            array('finance_id','日志编号'),
            array('username','所属'),
            array('typename','财务类型'),
            array('content','内容'),
            array('money','金额'),
            array('currency_name','积分类型'),
            array('money_type','收入/支出'),
            array('add_time','时间')
        );
        return export_excel("财务日志",$xlsCell,$list);
	}
	
	//众筹记录
	public function zhongchoulog(){
	    $iid=I('iid');
	    $name=I('name');
	    if(!empty($iid)){
	        $where['yang_issue_log.iid']=$iid;
	    }
	    if(!empty($name)){
	        $uid=M('Member')->where("name like '%{$name}%'")->select();
	        foreach($uid as $k=>$v){
	            $arr[]=$uid[$k]['member_id'];
	        }
	        $where['yang_issue_log.uid']=array('in',$arr);
	    }
	    $count      = M('Issue_log')->count();// 查询满足要求的总记录数
	    $Page       = new \Think\Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
	    //给分页传参数
	    setPageParameter($Page, array('iid'=>$iid,'name'=>$name));
	     
	    $show       = $Page->show();// 分页显示输出
	    $log=M('Issue_log')
	    ->field('case when yang_issue_log.is_release <> 0 then (select min(add_time) from yang_issue_release where log_id =yang_issue_log.id) else yang_issue_log.add_time end begin,yang_issue_log.*,yang_member.name,yang_issue.title,yang_issue_log.num*yang_issue_log.price as count')
	    ->join('left join yang_member on yang_member.member_id=yang_issue_log.uid')
	    ->join('left join yang_issue on yang_issue.id=yang_issue_log.iid')
	    ->join('left join yang_issue_release on yang_issue_release.log_id=yang_issue_log.id')
	    ->group('yang_issue_log.id')
	    ->order('begin desc')
	    ->limit($Page->firstRow.','.$Page->listRows)
	    ->where($where)->select();
	    foreach ($log as $key=>$vo) {
	        $log[$key]['buy_name'] = $vo['buy_currency_id']==0?"人民币":M('Currency')->where("currency_id='{$vo['buy_currency_id']}'")->find()['currency_name'];
	    }
	
	
	    $this->assign('log',$log);
	    $this->assign('page',$show);
	    $issue=M('Issue')->field('id,title')->select();
	    $this->assign('issue',$issue);
	    $this->assign('empty','暂无数据');
	    $this->display();
	}
	
	//众筹记录
	public function excel_zhongchoulog(){
	    $iid=I('iid');
	    $name=I('name');
	    $noname=I('noname');
	    if(!empty($iid)){
	        $where['yang_issue_log.iid']=$iid;
	    }
	    if(!empty($name)){
	        $uid=M('Member')->where("name not like '%{$name}%'")->select();
	        foreach($uid as $k=>$v){
	            $arr[]=$uid[$k]['member_id'];
	        }
	        $where['yang_issue_log.uid']=array('in',$arr);
	    }
	    if(!empty($noname)){
	        $uid=M('Member')->where("name like '%{$name}%'")->select();
	        foreach($uid as $k=>$v){
	            $arr[]=$uid[$k]['member_id'];
	        }
	        $where['yang_issue_log.uid']=array('in',$arr);
	    }
	    
	    $where['yang_issue_log.is_admin']='';
	    $count      = M('Issue_log')->count();// 查询满足要求的总记录数
	    $Page       = new \Think\Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
	    //给分页传参数
	    setPageParameter($Page, array('iid'=>$iid,'name'=>$name));
	
	    $show       = $Page->show();// 分页显示输出
	    $log=M('Issue_log')
	    ->field('case when yang_issue_log.is_release <> 0 then (select min(add_time) from yang_issue_release where log_id =yang_issue_log.id) else yang_issue_log.add_time end begin,yang_issue_log.*,yang_member.name,yang_member.phone,yang_member.email,yang_issue.title,yang_issue_log.num*yang_issue_log.price as count')
	    ->join('left join yang_member on yang_member.member_id=yang_issue_log.uid')
	    ->join('left join yang_issue on yang_issue.id=yang_issue_log.iid')
	    ->join('left join yang_issue_release on yang_issue_release.log_id=yang_issue_log.id')
	    ->group('yang_issue_log.id')
	    ->order('begin desc')
	    //->limit($Page->firstRow.','.$Page->listRows)
	    ->where($where)->select();
	    foreach ($log as $key=>$vo) {
	        $log[$key]['buy_name'] = $vo['buy_currency_id']==0?"人民币":M('Currency')->where("currency_id='{$vo['buy_currency_id']}'")->find()['currency_name'];
	        $log[$key]['begin'] = date('Y-m-d H:i:s', $vo['begin']);
	    }
	    
	    
	
	
	    $this->assign('log',$log);
	    $this->assign('page',$show);
	    $issue=M('Issue')->field('id,title')->select();
	    $this->assign('issue',$issue);
	    $this->assign('empty','暂无数据');
	    
	    
	    
	    $xlsName  = "User";
	    $xlsCell  = array(
	        array('iid','众筹编号'),
	    
	        array('username','众筹名称'),
	        array('name','购买人'),
	        array('email','账号'),
	        array('phone','电话'),
	        array('num','购买数量'),
	        array('deal','冻结数量'),
	        array('release_num','释放数量'),
	        array('price','单价'),
	        array('count','购买金额'),
	        array('begin','购买时间'),
	        array('buy_name','花费类型'),
	        array('remarks','备注说明')
	    );
	    // $xlsModel = M('Post');
	    $xlsData  = $log;
	    $this->exportExcel($xlsName,$xlsCell,$xlsData);
	    
	    
	    $this->display();
	}
	
	public function excel_pending2() {
	    $withdraw = M ( 'Withdraw' );
	    $bank = M ( 'bank' );
	    $name = I('cardname');
	    $names = I('keyname');
	    $keyid = I('keyid');
	    $keynum = I('keynum');
	    $two_pass = I('two_pass');
	    $one_pass = I('one_pass');
	    $admin_pass = I('admin_pass');
	    $ID_after = I('ID_after');
	    $ID_before = I('ID_before');
	    $status=I('status');
	    $is_time=I('is_time');
	    $is_alipay=I('is_alipay');
	    $datePicker=strtotime(I('datePicker'));
	    $datePicker2=strtotime(I('datePicker2'));
	    //ajax中的提交值
	    $currencyId = I('currency_id');
	    // I('status')--分页下标生成参数
	    if(I('pend')!=0 || I ( 'status' )!=0){
	        if($status==10){
	            $where ['yang_withdraw.status']='0';
	        }else{
	        
	        $where ['yang_withdraw.status'] = I ( 'status' ) ? I ( 'status' ) : I ( 'pend' );
	        }
	    }
	    if(!empty($name) || !empty($names)){
	        // 如果传回的是post（keyname）就用post，否则用get（cardname）
	        $cardname = I ( 'keyname' )? I ( 'keyname' ):I('cardname');
	        //模糊
	        $where ['yang_bank.cardname'] = array('like','%'.$cardname.'%');
	    }
	    if(!empty($keyid)){
	        $where ['yang_Withdraw.uid'] = array('like','%'.$keyid.'%');
	    }
	    if(!empty($keynum)){
	        $where ['email'] = array('like','%'.$keynum.'%');
	    }
	    if(!empty($datePicker) && !empty($datePicker2)  ){
	        if($is_time=='1'){
	            $where['yang_withdraw.add_time'] = array('between',array($datePicker,$datePicker2));
	        }else {
	            
	            $where['yang_withdraw.pay_time'] = array('between',array($datePicker,$datePicker2));
	        }
	        
	        
	    }
	    if(!empty($ID_before) && !empty($ID_after)  ){
	        $where['yang_withdraw.withdraw_id'] = array('between',array($ID_before,$ID_after));
	    }
	    if(!empty($one_pass)){
	        $where ['yang_Withdraw.firstaudit_term'] = $one_pass;
	    }
	    if(!empty($two_pass)){
	        $where ['yang_Withdraw.secondaudit_term'] = $two_pass;
	    }
	    if(!empty($admin_pass)){
	        $where ['yang_Withdraw.firstaudit_username'] = $admin_pass;
	    }
	    if(!empty($is_alipay)){
	        if ($is_alipay==3){
	            $where ['is_alipay'] = '0';
	        }else {
	        $where ['is_alipay'] = $is_alipay;
	        }
	    }
	    
	    // 查询满足要求的总记录数
	    $count = $withdraw->join ( "yang_bank ON yang_withdraw.bank_id = yang_bank.id" )->join("yang_member on yang_withdraw.uid=yang_member.member_id")->where ( $where )->count ();
	    // 实例化分页类 传入总记录数和每页显示的记录数
	    $Page = new \Think\Page ( $count, 20 );
	    //将分页（点击下一页）需要的条件保存住，带在分页中
	    $Page->parameter = array (
	        'yang_withdraw.status' => $where ['yang_withdraw.status'],
	        'yang_bank.cardname' =>  $cardname,
	        'yang_Withdraw.uid' => $keyid,
	        'yang_member.email' => $keynum
	    );
	    // 分页显示输出
	    $show = $Page->show ();
	    //需要的数据
	    $field = "yang_withdraw.*,yang_bank.cardname,yang_bank.cardnum,yang_bank.bankname,b.area_name as barea_name,a.area_name as aarea_name,c.email,c.phone,c.name,account_inname,is_alipay,firstaudit_term,secondaudit_term,firstaudit_username,c.member_id";
	    $info = $withdraw->field ( $field )
	    ->join ( "left join yang_bank ON yang_withdraw.bank_id = yang_bank.id" )
	    ->join("left join yang_areas as b ON b.area_id = yang_bank.address")
	    ->join("left join yang_areas as a ON a.area_id = b.parent_id ")
	    ->join("left join yang_member as c on yang_withdraw.uid=c.member_id")
	    ->where ( $where )
	    ->order ( 'yang_withdraw.add_time desc' )
	    //->limit ( $Page->firstRow . ',' . $Page->listRows )
	    ->select ();
	
	
	    $xuhao=1;
	    foreach ($info as $k=>$v){
	        $info[$k]['cardnum'] =  '’'.$v['cardnum'];
	        $info[$k]['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
	        $info[$k]['withdraw_fee2'] = round($v['withdraw_fee']/$v['all_money'],3);
	        $info[$k]['xuhao'] = $xuhao++;
	        $info[$k]['beizhu'] = '';
	        $info[$k]['xuhaoid'] = $v['withdraw_id'].'-'.$v['member_id'];
	     if($v['firstaudit_term']==1 ){
	             
	            $info[$k]['firstaudit_term']='未通过';
	        }else {
	             
	            $info[$k]['firstaudit_term']='通过';
	        }
	        
	    if($v['secondaudit_term']==1 ){
	             
	            $info[$k]['secondaudit_term']='未通过';
	        }else {
	             
	            $info[$k]['secondaudit_term']='通过';
	        }
	    
	    
	    
	        if($v['status']==1 ){
	        
	            $info[$k]['status']='未通过';
	        }elseif ($v['status']==2){
	            $info[$k]['status']='通过';
	        
	        }elseif ($v['status']==0){
	        
	            $info[$k]['status']='已撤销';
	        }elseif ($v['status']==-1){
	            $info[$k]['status']='付款失败';
	        
	        }elseif ($v['status']==4){
	        
	            $info[$k]['status']='付款成功';
	        }else {
	        
	            $info[$k]['status']='审核中';
	        }
	        
	        if($v['pay_statue']==1 ){
	             
	            $info[$k]['pay_statue']='付款成功';
	        }elseif ($v['pay_statue']==2){
	            $info[$k]['pay_statue']='付款失败';
	             
	        }else {
	             
	            $info[$k]['pay_statue']='未付款';
	        }
	    
	    }
	    
	    
	    $this->assign ( 'info', $info ); // 赋值数据集
	    $this->assign ( 'page', $show ); // 赋值分页输出
	    $this->assign ( 'inquire', $cardname );
	
	
	    $xlsName  = "User";
	    $xlsCell  = array(
	        array('withdraw_id','序号'),
	          array('bankname','银行'),
	       
	        array('aarea_name','地区（省）'),
	        array('barea_name','地区（市）'),
	        //array('uid','会员ID'),
	       
	        
	        array('account_inname','支行名'),
	         array('name','开户名'),
	        array('cardnum','银行账号'),
	        //array('all_money','提现金额'),
	       // array('withdraw_fee','手续费'),
	        array('money','实际金额'),
	        array('phone','电话号码'),
	        
	        array('xuhaoid','备注'),
	       array('order_num','订单号'),
	        array('add_time','提交时间'),
	         
	        array('status','状态'),
	        array('firstaudit_term','一审'),
	        array('firstaudit_username','一审用户'),
	        array('secondaudit_term','二审'),
	        array('pay_statue','付款状态'),
	        array('withdraw_fee','手续费'),
	        array('withdraw_fee2','手续率'),
	    );
	    // $xlsModel = M('Post');
	    $xlsData  = $info;
	    $this->exportExcel($xlsName,$xlsCell,$xlsData);
	
	    	
	
	
	
	    $this->display ();
	}
	
	public function excel_chongzhi_index(){
	    $cuid=I('cuid');
	    
	    $email=I('email');
	    $member_id=I('member_id');
	    $status=I('status');
	    $url=I('url');
	    $datePicker=strtotime(I('datePicker'));
	    $datePicker2=strtotime(I('datePicker2'));
	    if(!empty($cuid)){
	      $where['t.currency_id']=I("cuid");
	    }
	    $where['t.status']=array("in",array(2,3,4,5));
	    if(!empty($email)){
	        $where['m.email'] = array('like','%'.$email.'%');
	    }
	    if(!empty($member_id)){
	        $where['m.member_id'] = $member_id;
	    }
	    if(!empty($status)){
	        $where['t.status'] = $status;
	    }
	    if(!empty($url)){
	        $where['t.url'] = array('like','%'.$url.'%');
	    }
	    if(!empty($datePicker) && !empty($datePicker2)  ){
	        $where['t.add_time'] = array('between',array($datePicker,$datePicker2));
	    }
	    $field="t.*,m.email,m.member_id,m.name,m.phone,c.currency_name";

	    // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
	    $list = Db::name("Tibi")->alias("t")->field($field)->where($where)
            ->join(config("database.prefix")."member m","m.member_id=t.to_member_id","LEFT")
            ->join(config("database.prefix")."currency c","c.currency_id=t.currency_id","LEFT")
            ->order("add_time desc")->select();
	    foreach ($list as $k=>$v){
	        $list[$k]['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
	        if($v['status']==2 ){
	            $list[$k]['status']='充值中';
	        }elseif ($v['status']==3){
	            $list[$k]['status']='充值完成';
	        }else {
	            $list[$k]['status']='奖励完成';
	        }
	    }
	    $xlsName  = "充币记录";
	    $xlsCell  = array(
	        array('member_id','会员ID'),
	        array('email','会员邮箱'),
	        array('name','姓名'),
	        array('phone','手机'),
	        array('currency_name','积分类型名称'),
	        array('from_url','转入钱包地址'),
	        array('to_url','接收钱包地址'),
	        array('ti_id','转账hash'),
	        array('num','转入数量'),
	        array('actual','实际数量'),
	        array('add_time','时间'),
	        array('status','状态')
	    );
	  return  export_excel($xlsName,$xlsCell,$list);
	}
	
	
	public function excel_tibi_index(){
	    $cuid=I('cuid');
	    $email=I('email');
	    $member_id=I('member_id');
	    $status=I('status');
	    $url=I('url');
	    $datePicker=strtotime(I('datePicker'));
	    $datePicker2=strtotime(I('datePicker2'));
	    if(!empty($cuid)){
	      $where['t.currency_id']=I("cuid");
	    }
	    $where['t.status']=array("in",array(0,1));
	    if(!empty($email)){
	        $where['m.email'] = array('like','%'.$email.'%');
	    }
	    if(!empty($member_id)){
	        $where['m.member_id'] = $member_id;
	    }
	    if(!empty($status)){
	        $where['t.status'] = $status;
	    }
	    if(!empty($url)){
	        $where['t.url'] = array('like','%'.$url.'%');
	    }
	    if(!empty($datePicker) && !empty($datePicker2)  ){
	        $where['t.add_time'] = array('between',array($datePicker,$datePicker2));
	    }
	    $field="t.*,m.email,m.member_id,m.name,m.phone,c.currency_name";

	    $list = Db::name("Tibi")->alias("t")->field($field)->where($where)
            ->join(config("database.prefix")."member m","m.member_id=t.from_member_id","LEFT")
            ->join(config("database.prefix")."currency c","c.currency_id=t.currency_id","LEFT")
            ->order("add_time desc")->select();

	    foreach ($list as $k=>$v){
	        $list[$k]['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
	        if($v['status']==1 ){
	            $list[$k]['status']='提积分成功';
	        }elseif ($v['status']==0){
	            $list[$k]['status']='提积分中';
	        }else {
	            $list[$k]['status']='奖励完成';
	        }
	    }
	
	    $xlsName  = "提币记录";
	    $xlsCell  = array(
	        array('member_id','会员ID'),
	        array('email','会员邮箱'),
	        array('name','姓名'),
	        array('phone','手机'),
	        array('currency_name','积分类型名称'),
	        array('from_url','转出钱包地址'),
	        array('to_url','接收钱包地址'),
	        array('ti_id','转账hash'),
	        array('num','转入数量'),
	        array('actual','实际数量'),
	        array('add_time','时间'),
	        array('status','状态')
	    );
	    return export_excel($xlsName,$xlsCell,$list);
	}
	
	public function excel_address_index(){
	    $cuid=I('cuid');
	
	    $email=I('email');
	    $member_id=I('member_id');
	    $status=I('status');
	    $url=I('url');
	    $datePicker=strtotime(I('datePicker'));
	    $datePicker2=strtotime(I('datePicker2'));
	    $qian_id=I('qian_id');
	    $hou_id=I('hou_id');
	    if(!empty($cuid)){
	        if($cuid==1){
	
	        }else{
	            $where['yang_currency_user.currency_id']=I("cuid");
	        }
	        $this->assign("id",I("cuid"));
	    }
	    if(!empty($cuid)){
	        $name=M("Member")->where("email='{$email}'")->find();
	        //$where['yang_tibi.user_id']=$name['member_id'];
	    }
	    //$where['yang_currency_user.chongzhi_url']=array('exp','!=''');
	    if(!empty($email)){
	        $where['yang_member.email'] = array('like','%'.$email.'%');
	    }
	    if(!empty($member_id)){
	        $where['yang_member.member_id'] = $member_id;
	    }
	    if(!empty($status)){
	        $where['yang_currency_user.status'] = $status;
	    }
	    if(!empty($url)){
	        $where['yang_currency_user.url'] = array('like','%'.$url.'%');
	    }
	    if(!empty($datePicker) && !empty($datePicker2)  ){
	        $where['yang_currency_user.add_time'] = array('between',array($datePicker,$datePicker2));
	    }
	    if(!empty($qian_id) && !empty($hou_id)){
	        $where['yang_currency_user.cu_id'] = array('between',array($qian_id,$hou_id));
	    }
	    $field="yang_currency_user.*,yang_member.email,yang_member.member_id,yang_member.name,yang_member.phone,yang_currency.currency_name";
	    //$count      = M("currency_user")->where($where)->join("yang_member on yang_currency_user.user_id=yang_member.member_id")
	    //->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->count();// 查询满足要求的总记录数
	   // $Page       = new \Think\Page($count,20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
	    //给分页传参数
	  //  setPageParameter($Page, array('cuid'=>I("cuid"),'email'=>I('email'),'member_id'=>I('member_id'),'status'=>I('status'),'url'=>I('url')));
	  //  $show       = $Page->show();// 分页显示输出
	    // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
	    $list = M("currency_user")->field($field)->where($where)->where("chongzhi_url !=''")->join("yang_member on yang_currency_user.member_id=yang_member.member_id")
	    ->join("yang_currency on yang_currency_user.currency_id=yang_currency.currency_id")->order("cu_id desc")->select();
	    $this->assign('list',$list);// 赋值数据集
	    //$this->assign('page',$show);// 赋值分页输出
	
	
	    //读取积分类型表
	
	
	
	  /*  $curr=M("Currency")->select();
	    $this->assign("curr",$curr);
	    foreach ($list as $k=>$v){
	
	        $list[$k]['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
	        if($v['status']==1 ){
	
	            $list[$k]['status']='提积分成功';
	        }elseif ($v['status']==0){
	            $list[$k]['status']='提积分中';
	
	        }else {
	
	            $list[$k]['status']='奖励完成';
	        }
	
	    }*/
	
	    $xlsName  = "User";
	    $xlsCell  = array(
	        array('member_id','会员ID'),
	        array('email','会员邮箱'),
	
	
	        array('name','姓名'),
	        array('phone','手机'),
	        array('currency_name','积分类型名称'),
	        array('chongzhi_url','钱包地址')
	    );
	    // $xlsModel = M('Post');
	    $xlsData  = $list;
	    $this->exportExcel($xlsName,$xlsCell,$xlsData);
	
	
	
	
	    $this->display();
	
	}
	
	
	public function excel_member_index()
	{
	    $email = I('email');
	    $member_id = I('member_id');
	    $name = I('name');
	    $phone = I('phone');
	    $pid = I('pid');
	    //$status = I('status');
	    $datePicker=strtotime(I('datePicker'));
	    $datePicker2=strtotime(I('datePicker2'));
	
	    if (!empty($email)) {
	        $where['member.email'] = array('like', '%' . $email . '%');
	    }
	    if (!empty($member_id)) {
	        $where['member.member_id'] = $member_id;
	    }
	    if (!empty($name)) {
	        $where['member.name'] = array('like', '%' . $name . '%');
	    }
	    if (!empty($phone)) {
	        $where['member.phone'] = array('like', '%' . $phone . '%');
	    }
	    if (!empty($pid)) {
	        $where['member.pid'] = $pid;
	    }
	    if(!empty($datePicker) && !empty($datePicker2)  ){
	        $where['yang_member.reg_time'] = array('between',array($datePicker,$datePicker2));
	    }
	    
	    $where['yang_member.member_id'] = array('gt', 0);
	    $where['yang_member.status'] = '1';

	
	    $model = M('member');
	
	    $count = $model->where($where)->count();// 查询满足要求的总记录数
	   
	   
	    $field = "yang_member.*";
	    $list = $model->field($field)->where($where)->select();
	
	    

	    $curr=M("Currency")->select();
	    $this->assign("curr",$curr);
	    foreach ($list as $k=>$v){
	        $list[$k]['idcard'] =  '’'.$v['idcard'];
	    
	        $list[$k]['reg_time'] = date('Y-m-d H:i:s', $v['reg_time']);
	        if($v['status']==1 ){
	    
	            $list[$k]['status']='提积分成功';
	        }elseif ($v['status']==0){
	            $list[$k]['status']='提积分中';
	    
	        }else {
	    
	            $list[$k]['status']='奖励完成';
	        }
	    
	    }
	    
	    $xlsName  = "User";
	    $xlsCell  = array(
	        array('member_id','会员ID'),
	        array('email','会员邮箱'),
	    
	    
	        array('name','姓名'),
	        array('phone','手机'),
	        array('idcard','身份证'),
	        array('rmb','余额'),
	      
	       
	        array('reg_time','时间'),
	        
	    );
	    // $xlsModel = M('Post');
	    $xlsData  = $list;
	    $this->exportExcel($xlsName,$xlsCell,$xlsData);
	    
	    
	    
	    $this->display(); // 输出模板
	}
	
	public function excel_member_award_index()
	{
	    $email = I('email');
	    $member_id = I('member_id');
	    $name = I('name');
	    $phone = I('phone');
	    $pid = I('pid');
	    $number = I('number');
	    //$status = I('status');
	    $datePicker=strtotime(I('datePicker'));
	    $datePicker2=strtotime(I('datePicker2'));
	
	    if (!empty($email)) {
	        $where['member.email'] = array('like', '%' . $email . '%');
	    }
	    if (!empty($member_id)) {
	        $where['member.member_id'] = $member_id;
	    }
	    if (!empty($name)) {
	        $where['member.name'] = array('like', '%' . $name . '%');
	    }
	    if (!empty($phone)) {
	        $where['member.phone'] = array('like', '%' . $phone . '%');
	    }
	    if (!empty($pid)) {
	        $where['member.pid'] = $pid;
	    }
	    if(!empty($datePicker) && !empty($datePicker2)  ){
	        $where['yang_member.reg_time'] = array('between',array($datePicker,$datePicker2));
	    }
	     

// 	    $where['yang_member.status'] = '1';

	    if (!empty($number)) {
	      // $where['count'] = array('EGT',$number);;
	        $where['tier'] = 1;
	        $group='member_id';
	        $order='count desc';
	    }
	    $model = M('currency_user_num_award');
	
// 	    $count = $model->where($where)->count();// 查询满足要求的总记录数
	
	    if (!empty($number)) {
	        $field = "sum(num_award) as sum_num_award,count(num_award) as count,
	            yang_currency_user_num_award.member_id,yang_currency_user_num_award.member_id_one,
	            yang_currency_user_num_award.tier,yang_currency_user_num_award.num_award,
	            yang_member.email,yang_member.phone,yang_member.reg_time,yang_member.name";

	    }else {
	    $field = "
	        yang_currency_user_num_award.member_id,yang_currency_user_num_award.member_id_one,yang_currency_user_num_award.tier,yang_currency_user_num_award.num_award,yang_member.email,yang_member.phone,yang_member.reg_time";
	    
	    }
	    
	    $list = $model->field($field)
	    ->join('left join yang_member on yang_currency_user_num_award.member_id=yang_member.member_id')
	    ->where($where)
	    ->group($group)
	    ->order($order)
	    ->select();
	
	   // var_dump($list);exit();
	
// 	    $curr=M("Currency")->select();
// 	    $this->assign("curr",$curr);
// 	    foreach ($list as $k=>$v){
// 	        $list[$k]['idcard'] =  '’'.$v['idcard'];
	         
// 	        $list[$k]['reg_time'] = date('Y-m-d H:i:s', $v['reg_time']);
// 	        if($v['status']==1 ){
	             
// 	            $list[$k]['status']='提积分成功';
// 	        }elseif ($v['status']==0){
// 	            $list[$k]['status']='提积分中';
	             
// 	        }else {
	             
// 	            $list[$k]['status']='奖励完成';
// 	        }
	         
// 	    }
	     
	    $xlsName  = "User";
	    if(!empty($number)){
	    $xlsCell  = array(
	        array('member_id','收益用户ID'),
	       
	        array('sum_num_award','奖励数量'),
	        array('name','姓名'),
	         
	        array('count','邀请人数'),
	        
	         
	    );
	    }else {
	        $xlsCell  = array(
	            array('member_id','收益用户ID'),
	            array('member_id_one','被邀请ID'),
	            array('num_award','奖励数量'),
	            array('name','姓名'),
	        
	            array('tier','层级'),
	        
	        );
	        
	    }
	    // $xlsModel = M('Post');
	    $xlsData  = $list;
	    $this->exportExcel($xlsName,$xlsCell,$xlsData);
	     
	     
	     
	    $this->display(); // 输出模板
	}
	
}
?>