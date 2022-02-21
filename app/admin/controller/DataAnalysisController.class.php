<?php
namespace Admin\Controller;
use Admin\Controller\AdminController;
use \Think\Page;
class DataAnalysisController extends AdminController{
	public function _initialize(){
		parent::_initialize();
		$this->currency=M('Currency');
	}
	//空操作
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		$this->display('Public:404');
	}
	public function index(){
		/* 持有积分方法 */
		$member=M('Member');
		$currencyId=I('currency_id');
		if(!empty($currencyId)){
			$idcurrency=$currencyId;
			$where['currency_id']=$currencyId;			//判断持有积分中的积分类型id
			$where_deity['a.currency_id']=$currencyId;		//判断挂单买记录中的积分类型id
			$where_deitys['a.currency_id']=$currencyId;		//判断挂单卖记录中的积分类型id
			$where_turnover['a.currency_id']=$currencyId;	//判断成交买记录中的积分类型id
			$where_turnovers['a.currency_id']=$currencyId;	//判断成交卖记录中的积分类型id
			$this->assign("id",$currencyId);	//取得传过来的id再传回页面
		}else{
			$where['currency_id']=0;
			$where_deity['a.currency_id']=0;
			$where_deitys['a.currency_id']=0;
			$where_turnover['a.currency_id']=0;
			$where_turnovers['a.currency_id']=0;
		}
		/* $count=$member->join('yang_currency_user on yang_currency_user.member_id = yang_member.member_id')
				->where($where)->count();
		$Page=new \Think\Page($count,20);
		setPageParameter($Page, array('currency_id'=>$currencyId));
		$show=$Page->show(); */
		$list_hold=$member->field('yang_member.member_id,yang_member.name,yang_member.phone,yang_member.email,yang_currency_user.num,yang_currency_user.forzen_num')->join('yang_currency_user on yang_currency_user.member_id = yang_member.member_id')
					->where($where)
					->where('num > 0 or forzen_num > 0')
					->order(" num desc ")		
					->select();
		$hold_sum=$member->field('sum(num) as holdsum')->join('yang_currency_user on yang_currency_user.member_id = yang_member.member_id')
					->where($where)
					->select();	
		$freeze_sum=$member->field('sum(forzen_num) as freezesum')->join('yang_currency_user on yang_currency_user.member_id = yang_member.member_id')
					->where($where)
					->select();
        $lock_sum=$member->field('sum(lock_num) as lock_num')->join('yang_currency_user on yang_currency_user.member_id = yang_member.member_id')
            ->where($where)
            ->select();
		$list_currency = $this->currency->select();
		$this->assign("list_currency",$list_currency);
		$this->assign("list_hold",$list_hold);
		//$this->assign("page",$show);
		$this->assign("hold_sum",$hold_sum[0]['holdsum']);
		$this->assign("freeze_sum",$freeze_sum[0]['freezesum']);
		$this->assign("lock_sum",$lock_sum[0]['lock_num']);

		
		/* 挂单买卖记录 */
		$where_deity['a.status'] = array('in',array(0,1));
		$where_deity['type'] = "buy";		//买
		$where_deitys['a.status'] = array('in',array(0,1));
		$where_deitys['type'] = "sell";		//卖
		$field = "a.*,b.currency_name as b_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
		/* $count_deity  = M('Orders')
		->alias('a')
		->field($field)
		->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
		->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
		->where($where_deity)
		->order(" a.add_time desc ")->count();// 查询满足要求的总记录数
		$Page_deity   = new \Think\Page($count_deity,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)		
		$show_deity   = $Page_deity->show();// 分页显示输出
		// 进行分页数据查询 注意limit方法的参数要使用Page类的属性 */
		
		//买
		$list_deity = M('Orders')
		->alias('a')
		->field($field)
		->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
		->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
		->where($where_deity)
		->order(" a.add_time desc ")
		->select();
		$deity_sum= M('Orders')
		->alias('a')
		->field('sum(num) as deitysum')
		->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
		->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
		->where($where_deity)
		->select();
		$this->assign('list_deity',$list_deity);
		$this->assign('deity_sum',$deity_sum[0]['deitysum']);
		//$this->assign('page_deity',$show_deity);// 赋值分页输出
		
		//卖
		$list_deitys = M('Orders')
		->alias('a')
		->field($field)
		->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
		->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
		->where($where_deitys)
		->order(" a.add_time desc ")
		->select();
		$deitys_sum= M('Orders')
		->alias('a')
		->field('sum(num) as deitysum')
		->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
		->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
		->where($where_deitys)
		->select();
		$this->assign('list_deitys',$list_deitys);
		$this->assign('deitys_sum',$deitys_sum[0]['deitysum']);
		
		
		/* 成交买卖记录*/
		
		//买
		$where_turnover['a.type'] = "buy";
		$where_turnovers['a.type'] = "sell";
		$field_turnover = "a.*,b.currency_name as b_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
		$list_turnover = M('Trade')
		->alias('a')
		->field($field_turnover)
		->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
		->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
		->where($where_turnover)
		->order(" a.add_time desc ")
		->select();
		$turnover_sum = M('Trade')
		->alias('a')
		->field('sum(num) as turnoversum')
		->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
		->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
		->where($where_turnover)
		->order(" a.add_time desc ")
		->select();
		$this->assign('list_turnover',$list_turnover);
		$this->assign('turnover_sum',$turnover_sum[0]['turnoversum']);
		
		//卖
		$list_turnovers = M('Trade')
		->alias('a')
		->field($field_turnover)
		->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
		->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
		->where($where_turnovers)
		->order(" a.add_time desc ")
		->select();
		$turnovers_sum = M('Trade')
		->alias('a')
		->field('sum(num) as turnoversum')
		->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
		->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
		->where($where_turnovers)
		->order(" a.add_time desc ")
		->select();
		$this->assign('list_turnovers',$list_turnovers);
		$this->assign('turnovers_sum',$turnovers_sum[0]['turnoversum']);


		/* 曲线图*/
		if(empty($idcurrency)){
			$idcurrency=1;
		}
		$currency=M('Currency')->where("currency_id='$idcurrency' ")->find();
		if(empty($currency)){
			$this->display('Public:404');
			return;
		}
		$this->assign('currency',$currency);			//货币主表
		
		//买卖盘   卖
		$sell=$this ->	getSellBuyByCurrencyIdType('sell',$idcurrency);
		
		//买卖盘   买
		$buy =$this ->	getSellBuyByCurrencyIdType('buy',$idcurrency);
		//print_r($buy);
		$this ->assign('count',max(count($sell),count($buy)));
		$this ->assign('sell',$sell);
		$this ->assign('buy',$buy);
		$this->display();
	}
	
	public function tradestatistics(){
	    $member_name=I('member_name');
	    $member_id=I('member_id');
	    $status=I('status');
	    $type=I('type');
	    $currency_id=I('currency_id');
	    $datePicker1=strtotime(I('datePicker1'));
	    $datePicker2=strtotime(I('datePicker2'));
	
	    // $where['status'] = 1;//只统计充值成功的
	    //$where['account'] = array('neq', '');//银行卡号不为空
	    
	    if(!empty($type)){
	        $where['a.type']=$type;
	        $this->assign('type',$type);
	    }
	    
	    
	    if(!empty($currency_id)){
	        $where['a.currency_id']=$currency_id;
	        $this->assign('currency_id',$currency_id);
	    }
	
	    if(!empty($datePicker1) && !empty($datePicker2)  ){
	        $where['a.add_time'] = array('between',array($datePicker1,$datePicker2));
	        $datePicker1=date("Y-m-d",$datePicker1);
	        $datePicker2=date("Y-m-d",$datePicker2);
	        $this->assign('datePicker1',$datePicker1);
	        $this->assign('datePicker2',$datePicker2);
	    }
	
	    $count =  M('trade')->alias('a')->where($where)->group('year(FROM_UNIXTIME(a.add_time)),month(FROM_UNIXTIME(a.add_time)),day(FROM_UNIXTIME(a.add_time))')->select();
	    $Page  = new \Think\Page(count($count),20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
	    //给分页传参数
	    $Page->parameter = array(
	        'currency_id' => $currency_id,
	        'type' => $type,
	        'datePicker1' => $datePicker1,
	        'datePicker2' => $datePicker2,
	    );
	    $show       = $Page->show();// 分页显示输出
	    $list= M('trade')->alias('a')->field(' year(FROM_UNIXTIME(a.add_time)) as year,month(FROM_UNIXTIME(a.add_time)) as month,day(FROM_UNIXTIME(a.add_time)) as day,sum(money) as money,sum(fee) as fee,sum(num) as num,a.type,a.add_time,b.currency_name as b_name,a.currency_id as currency_id')
	    ->join('LEFT JOIN yang_currency  as b ON  a.currency_id = b.currency_id')
	    ->where($where)->limit($Page->firstRow.','.$Page->listRows)
	    ->group('year(FROM_UNIXTIME(a.add_time)),month(FROM_UNIXTIME(a.add_time)),day(FROM_UNIXTIME(a.add_time))')
	    ->order('add_time desc')
	    ->select();
	     
	    $this->assign('page',$show);
	    $this->assign('list',$list);
	    $this->assign('empty','暂无数据');
	    //$this->assign('sum_money', M('Pay')->field('sum(money) as sum_money')->where($where)->find()['sum_money']);
	    $this->assign('sum_money_all', M('trade')->alias('a')->field('sum(money) as sum_money_all')->where($where)->find()['sum_money_all']);
	   $this->assign('sum_num', M('trade')->alias('a')->field('sum(num) as sum_num')->where($where)->find()['sum_num']);
	    $this->assign('withdraw_fee', M('trade')->alias('a')->field('sum(fee) as withdraw_fee')->where($where)->find()['withdraw_fee']);
	   // $this->assign('admin_id',$this->admin['admin_id']);
	    //积分类型
	    $currency = M('Currency')->field('currency_name,currency_id')->select();
	    $this->assign('currency',$currency);
	    $this->display();
	}
	
	public function financestatistics(){
	   
        $status=I('status');
        $status2=I('status2');
        $datePicker1=strtotime(I('datePicker1'));
        $datePicker2=strtotime(I('datePicker2'));
    
       // $where['status'] = 1;//只统计充值成功的
        //$where['account'] = array('neq', '');//银行卡号不为空
        
        if(!empty($status)){
            $where['status']=$status;
            $this->assign('status',$status);
        }
        if(!empty($status2)){
            $where2['status']=$status2;
            $this->assign('status2',$status2);
        }
        
        if(!empty($datePicker1) && !empty($datePicker2)  ){
            $where['add_time'] = array('between',array($datePicker1,$datePicker2));
            $where2['add_time'] = array('between',array($datePicker1,$datePicker2));
            $where3['reg_time'] = array('between',array($datePicker1,$datePicker2));
            $datePicker1=date("Y-m-d",$datePicker1);
            $datePicker2=date("Y-m-d",$datePicker2);
            $this->assign('datePicker1',$datePicker1);
            $this->assign('datePicker2',$datePicker2);
        }
    
        $count =  M('Member')->group('year(FROM_UNIXTIME(reg_time)),month(FROM_UNIXTIME(reg_time)),day(FROM_UNIXTIME(reg_time))')->select();
        $Page  = new \Think\Page(count($count),20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        $Page->parameter = array(
            
            'status' => $status,
            'status2' => $status2,
            'datePicker1' => $datePicker1,
            'datePicker2' => $datePicker2,
        );
        $show       = $Page->show();// 分页显示输出
        $list= M('withdraw')->field(' year(FROM_UNIXTIME(pay_time)) as year,month(FROM_UNIXTIME(pay_time)) as month,day(FROM_UNIXTIME(pay_time)) as day,sum(money) as money,sum(all_money) as all_money,sum(withdraw_fee) as withdraw_fee,status,count(*) count')
        ->where($where)->limit($Page->firstRow.','.$Page->listRows)
        ->group('year(FROM_UNIXTIME(pay_time)),month(FROM_UNIXTIME(pay_time)),day(FROM_UNIXTIME(pay_time))')
        ->order('add_time desc')
        ->select();
        $list2= M('pay')->field(' year(FROM_UNIXTIME(add_time)) as year,month(FROM_UNIXTIME(add_time)) as month,day(FROM_UNIXTIME(add_time)) as day,sum(count) as money,status,count(*) as count')
        ->where($where2)->limit($Page->firstRow.','.$Page->listRows)
        ->group('year(FROM_UNIXTIME(add_time)),month(FROM_UNIXTIME(add_time)),day(FROM_UNIXTIME(add_time))')
        ->order('add_time desc')
        ->select();
       $list3= M('Member')->field(' year(FROM_UNIXTIME(reg_time)) as year,month(FROM_UNIXTIME(reg_time)) as month,day(FROM_UNIXTIME(reg_time)) as day,count(*) as count_member')
        ->where($where3)->limit($Page->firstRow.','.$Page->listRows)
        ->group('year(FROM_UNIXTIME(reg_time)),month(FROM_UNIXTIME(reg_time)),day(FROM_UNIXTIME(reg_time))')
        ->order('reg_time desc')
        ->select();
       foreach ($list as $k => $v){
           $list[$v['year']][$v['month']][$v['day']]['0']=$v['money'];
           $list[$v['year']][$v['month']][$v['day']]['1']=$v['all_money'];
           $list[$v['year']][$v['month']][$v['day']]['2']=$v['withdraw_fee'];
           $list[$v['year']][$v['month']][$v['day']]['3']=$v['count'];
           $list[$v['year']][$v['month']][$v['day']]['4']=$v['status'];
       }
        foreach ($list2 as $k => $v){
            $list2[$v['year']][$v['month']][$v['day']]['0']=$v['money'];
            $list2[$v['year']][$v['month']][$v['day']]['1']=$v['count'];
        }
        foreach ($list3 as $k => $v){
            $list3[$v['year']][$v['month']][$v['day']]=$v['count_member'];
            $list3[$k]['pay_allmoney']= $list2[$v['year']][$v['month']][$v['day']]['0'];
            $list3[$k]['pay_count']= $list2[$v['year']][$v['month']][$v['day']]['1'];
            $list3[$k]['count_member']= $list3[$v['year']][$v['month']][$v['day']];
            $list3[$k]['money']= $list[$v['year']][$v['month']][$v['day']]['0'];
            $list3[$k]['all_money']= $list[$v['year']][$v['month']][$v['day']]['1'];
            $list3[$k]['withdraw_fee']= $list[$v['year']][$v['month']][$v['day']]['2'];
            $list3[$k]['count']= $list[$v['year']][$v['month']][$v['day']]['3'];
            $list3[$k]['status']= $list[$v['year']][$v['month']][$v['day']]['4'];
        }
        
  
        $this->assign('page',$show);
        $this->assign('list',$list3);
        $this->assign('empty','暂无数据');
        //$this->assign('sum_money', M('Pay')->field('sum(money) as sum_money')->where($where)->find()['sum_money']);
        $this->assign('sum_money_all', M('withdraw')->field('sum(all_money) as sum_money_all')->where($where)->find()['sum_money_all']);
        $this->assign('sum_money', M('withdraw')->field('sum(money) as sum_money')->where($where)->find()['sum_money']);
        $this->assign('withdraw_fee', M('withdraw')->field('sum(withdraw_fee) as withdraw_fee')->where($where)->find()['withdraw_fee']);
        $this->assign('withdraw_count', M('withdraw')->field('count(*) as withdraw_count')->where($where)->find()['withdraw_count']);
        $this->assign('pay_money', M('pay')->field('sum(count) as pay_money')->where($where2)->find()['pay_money']);
        $this->assign('pay_count', M('pay')->field('count(*) as pay_count')->where($where2)->find()['pay_count']);
        $this->assign('member_count', M('member')->field('count(*) as member_count')->where($where3)->find()['member_count']);
        $this->assign('admin_id',$this->admin['admin_id']);
        $this->display();
	}
	
	
	private  function getSellBuyByCurrencyIdType($type,$currency_id){
		$where['type']='buy';
		$where['currency_id']=$currency_id;
		$endTime=M('trade')->field('max(add_time) as time')->where($where)->find();
		if(empty($endTime['time'])){
		    $endTime['time']=time();
		}
		$startTime=$endTime['time']-30*24*3600;
		$type = strtoupper($type);
		if($type=='BUY'){
			$sql = "SELECT num,price,add_time*1000 as add_time  from  yang_trade  where type = '".$type."'
    			and currency_id  =".$currency_id." and (add_time between ".$startTime." and ".$endTime['time'].") order BY add_time desc ";
		}else{
			$sql = "SELECT num,price,add_time*1000 as add_time  from  yang_trade  where type = '".$type."'
    			and currency_id  =".$currency_id." and (add_time between ".$startTime." and ".$endTime['time'].") order BY add_time";
		}
		$list = M()->query($sql);
		return $list;
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
			if($list[$i]["currency_id"]>0){
				$currency_type = "充积分";
			}else{
				$currency_type = "充值";
			}
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
		
		//充值统计
		$totalcz = M('Pay')
		->field('sum(money) as totalczmoney,sum(count) as totalczcount')
		->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
		->where($where)
		->where('yang_pay.currency_id=0')
		->select();
		//充积分统计
		$totalcb = M('Pay')
		->field('sum(money) as totalcurrency')
		->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
		->where($where)
		->where('yang_pay.currency_id>0')
		->select();
		$a.='<tr><td><span style="font-size:16px;color:#2F4F4F;">充值合计：</span></td><td>'.$totalcz[0]['totalczmoney'].'</td>
			 <td><span style="font-size:16px;color:#2F4F4F;">充积分合计：</span></td><td>'.$totalcb[0]['totalcurrency'].'</td>
			 <td><span style="font-size:16px;color:#2F4F4F;">实际打款合计：</span></td><td>'.$totalcz[0]['totalczcount'].'</td></tr>';
			
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
							<td>'.$log[$i]["buy_name"].'</td>
							<td>'.$log[$i]["remarks"].'</td></tr>';
		}
		
		//统计
		$totalzc = M('Issue_log')
		->field('sum(yang_issue_log.num) as buynum,sum(yang_issue_log.deal) as freezenum,sum(yang_issue_log.num*yang_issue_log.price) as totalaggregate')
		->join('left join yang_member on yang_member.member_id=yang_issue_log.uid')
		->join('left join yang_issue on yang_issue.id=yang_issue_log.iid')
		->where($where)
		->select();
		$b.='<tr><td><span style="font-size:16px;color:#2F4F4F;">购买数量合计：</span></td><td>'.$totalzc[0]['buynum'].'</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">冻结数量合计：</span></td><td>'.$totalzc[0]['freezenum'].'</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">购买总额合计：</span></td><td>'.$totalzc[0]['totalaggregate'].'</td></tr>';
		
		$this->ajaxReturn($b);
	}
	
	
	//会员管理->会员列表,涉及到yang_member
	public function xiangDan_user(){
		$uid_user=I('uid_user');
		$where['yang_currency_user.member_id']=$uid_user;
		$filed='yang_member.*,yang_currency.currency_name,yang_currency_user.num,yang_currency_user.forzen_num,yang_currency_user.num_award,yang_currency_user.sum_award,yang_currency_user.currency_id';
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
						<th>充积分数量</th>
						<th>购买量</th>
						<th>卖出量</th>
                    </tr>';
		for( $i=0; $i<$count; $i++ ){
			//充积分数据
			$wherea['yang_tibi.currency_id'] = $list_user[$i]["currency_id"];
			$wherea['yang_tibi.user_id'] = $uid_user;
			$wherea['yang_tibi.status']=3;
			$totalcb = M('Tibi')
			->field('sum(num) as totalcharging')
			->join("yang_member on yang_tibi.user_id=yang_member.member_id")
			->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")
			->where($wherea)
			->select();
			//交易数据
			$whereb['c.member_id'] = $uid_user;
			$whereb['a.currency_id'] = $list_user[$i]["currency_id"];
			$totaljybuy = M('Trade')
			->alias('a')
			->field('sum(a.num) as jybuynum')
			->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
			->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
			->where($whereb)
			->where("a.type='buy'")
			->select();

			$totaljysell = M('Trade')
			->alias('a')
			->field('sum(a.num) as jybuynum')
			->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
			->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
			->where($whereb)
			->where("a.type='sell'")
			->select();		
			$c.='<tr><td>'.$list_user[$i]["currency_name"].'</td>
							<td>'.$list_user[$i]["num"].'</td>
							<td>'.$list_user[$i]["forzen_num"].'</td>
							<td>'.$list_user[$i]["num_award"].'</td>		
							<td>'.$list_user[$i]["sum_award"].'</td>
							<td>'.$totalcb[0]['totalcharging'].'</td>
							<td>'.$totaljybuy[0]['jybuynum'].'</td>
							<td>'.$totaljysell[0]['jybuynum'].'</td></tr>';
		}
		//实际充值钱、提现钱、用户备注
		$c.='|';
		$wherec['yang_pay.member_id']=$uid_user;
		$wherec['yang_pay.status']=1;
		$totalcz = M('Pay')
		->field('sum(count) as totalcount')
		->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
		->where($wherec)
		->where('yang_pay.currency_id=0')
		->select();
		$c.= $totalcz[0]['totalcount'];
		$c.='|';
		$whered['yang_bank.uid']=$uid_user;
		$whered['yang_withdraw.status']=2;
		$totaltx = M('bank')
		->field('sum(money) as totalmoney')
		->join ( "yang_withdraw ON yang_withdraw.bank_id = yang_bank.id" )
		->join("yang_areas as b ON b.area_id = yang_bank.address")
		->join("yang_areas as a ON a.area_id = b.parent_id ")
		->join("yang_member as c on yang_withdraw.uid=c.member_id")
		->where($whered)
		->select();
		$c.= $totaltx[0]['totalmoney'];
		$c.='|';
		$c.= $totalcz[0]['totalcount'] - $totaltx[0]['totalmoney'];
		$c.='|';
		$list_user_remark = M('Currency_user')->field('yang_member.remarks')->join('yang_currency ON yang_currency_user.currency_id = yang_currency.currency_id')
		->join('yang_member on yang_currency_user.member_id = yang_member.member_id')
		->where($where)
		->select();
		$c.= $list_user_remark[0]["remarks"];
		
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
							<td>'.number_format($list_jy[$i]["fee"],4,'.','').'</td>
							<td>'.$list_jy[$i]["type_name"].'</td>
							<td>'.date("Y-m-d H:i:s",$list_jy[$i]["add_time"]).'</td></tr>';
		}
		
		//统计买
		$totaljybuy = M('Trade')
		->alias('a')
		->field('sum(a.num) as jybuynum,sum(a.money) as jybuymoney')
		->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
		->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
		->where($where)
		->where("a.type='buy'")
		->select();
		//统计卖
		$totaljysell = M('Trade')
		->alias('a')
		->field('sum(a.num) as jybuynum,sum(a.money) as jybuymoney')
		->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
		->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
		->where($where)
		->where("a.type='sell'")
		->select();
		$d.='<tr><td><span style="font-size:16px;color:#2F4F4F;">买入量合计：</span></td><td>'.$totaljybuy[0]['jybuynum'].'</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">买入金额合计：</span></td><td>'.$totaljybuy[0]['jybuymoney'].'</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">卖出量合计：</span></td><td>'.$totaljysell[0]['jybuynum'].'</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">卖出金额合计：</span></td><td>'.$totaljysell[0]['jybuymoney'].'</td></tr>';
		
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
		
		//统计
		$totaltx = M('bank')
		->field('sum(money) as totalmoney')
		->join ( "yang_withdraw ON yang_withdraw.bank_id = yang_bank.id" )
		->join("yang_areas as b ON b.area_id = yang_bank.address")
		->join("yang_areas as a ON a.area_id = b.parent_id ")
		->join("yang_member as c on yang_withdraw.uid=c.member_id")
		->where($where)
		->select();
		$e.='<tr><td><span style="font-size:16px;color:#2F4F4F;">实际金额合计：</span></td><td>'.$totaltx[0]['totalmoney'].'</td></tr>';
		
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
		
		//统计
		$totaltb = M('Tibi')
		->field('sum(actual) as totalcurrency')
		->join("yang_member on yang_tibi.user_id=yang_member.member_id")
		->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")
		->where($where)
		->select();
		$f.='<tr><td><span style="font-size:16px;color:#2F4F4F;">实际数量合计：</span></td><td>'.$totaltb[0]['totalcurrency'].'</td></tr>';
		
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
		
		//统计
		$totalcb = M('Tibi')
		->field('sum(num) as totalcharging')
		->join("yang_member on yang_tibi.user_id=yang_member.member_id")
		->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")
		->where($where)
		->select();
		$f.='<tr><td><span style="font-size:16px;color:#2F4F4F;">实际数量合计：</span></td><td>'.$totalcb[0]['totalcharging'].'</td></tr>';
		
		$this->ajaxReturn($f);
	}
	
	/**
	 * 提积分记录
	 */
	public function tibi_data(){
	
	    /*	$cuid=I('cuid');
	     $email=I('email');
	     if(!empty($cuid)){
	     if($cuid==1){
	
	     }else{
	     $where['yang_tibi.currency_id']=$cuid;
	     }
	     $this->assign("id",$cuid);
	     }
	     if(!empty($cuid)){
	     $name=M("Member")->where("email='{$cuid}'")->find();
	     //$where['yang_tibi.user_id']=$name['member_id'];
	     }
	     $where['yang_tibi.status']=array("in",array(0,1));
	     if(!empty($email)){
	     $where['yang_tibi.name'] = array('like','%'.$email.'%');
	     }
	     $field="yang_tibi.*,yang_member.email,yang_currency.currency_name";
	     $count      = M("Tibi")->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
	     ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->count();// 查询满足要求的总记录数
	     $Page       = new \Think\Page($count,10);// 实例化分页类 传入总记录数和每页显示的记录数(25)
	     //给分页传参数
	     setPageParameter($Page, array('cuid'=>I("cuid"),'email'=>$name['member_id']));
	     $show       = $Page->show();// 分页显示输出
	     // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
	     $list = M("Tibi")->field($field)->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
	     ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->limit($Page->firstRow.','.$Page->listRows)->select();
	     $this->assign('list',$list);// 赋值数据集
	     $this->assign('page',$show);// 赋值分页输出
	     */
	 
	    $cuid=I('cuid');
	    $email=I('email');
	    $member_id=I('member_id');
	    $status=I('status');
	    $url=I('url');
	    if(!empty($cuid)){
	        if($cuid==5){
	
	        }else{
	            $where['yang_tibi.currency_id']=I("cuid");
	        }
	        $this->assign("id",I("cuid"));
	    }
//	    if(!empty($cuid)){
//	        $name=M("Member")->where("email='{$email}'")->find();
//	        //$where['yang_tibi.user_id']=$name['member_id'];
//	    }
	    $where['yang_tibi.status']=array("in",array(0,1,-1,-2));
	    $where['yang_tibi.b_type']=0;
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
	    $group='DATE_FORMAT(FROM_UNIXTIME(yang_tibi.add_time),"%Y-%m-%d"),yang_tibi.currency_id,yang_tibi.status';
	     
	    $field="yang_tibi.*,yang_member.email,yang_member.member_id,yang_member.name,yang_member.phone,yang_currency.currency_name,yang_member.remarks,DATE_FORMAT(FROM_UNIXTIME(yang_tibi.add_time),'%Y-%m-%d') AS add_time,sum(yang_tibi.num) as num,sum(yang_tibi.actual) as actual";
	    $count      = M("Tibi")->where($where)->join("yang_member on yang_tibi.from_member_id=yang_member.member_id")
	    ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->count();// 查询满足要求的总记录数
	    $Page       = new \Think\Page($count,20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
	    //给分页传参数
	    setPageParameter($Page, array('cuid'=>I("cuid"),'email'=>I('email'),'member_id'=>I('member_id'),'status'=>I('status'),'url'=>I('url')));
	    $show       = $Page->show();// 分页显示输出
	    // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
	    $list = M("Tibi")->field($field)->where($where)->join("yang_member on yang_tibi.from_member_id=yang_member.member_id")
	    ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")
	    ->group($group)
	    ->order("add_time desc")->limit($Page->firstRow.','.$Page->listRows)->select();
	    $this->assign('list',$list);// 赋值数据集
	    $this->assign('page',$show);// 赋值分页输出
	    //读取积分类型表
	
	    $curr=M("Currency")->select();
	    $this->assign("curr",$curr);
	
	    $this->display();
	}
	
	/**
	 * 充值记录
	 */
	public function chongzhi_data(){
	
	 
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
//	    if(!empty($cuid)){
//	        $name=M("Member")->where("email='{$email}'")->find();
//	        //$where['yang_tibi.user_id']=$name['member_id'];
//	    }
	    $where['yang_tibi.status']=array("in",array(2,3,4,5));
	    $where['yang_tibi.b_type']=0;
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
	    $group='DATE_FORMAT(FROM_UNIXTIME(yang_tibi.add_time),"%Y-%m-%d"),yang_tibi.currency_id,yang_tibi.status';
	    
	    $field="yang_tibi.*,yang_member.email,yang_member.member_id,yang_member.name,yang_member.phone,yang_currency.currency_name,yang_member.remarks,DATE_FORMAT(FROM_UNIXTIME(yang_tibi.add_time),'%Y-%m-%d') AS add_time,sum(yang_tibi.num) as num";
	    $count      = M("Tibi")->where($where)->join("yang_member on yang_tibi.to_member_id=yang_member.member_id")
	    ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->count();// 查询满足要求的总记录数
	    $Page       = new \Think\Page($count,20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
	    //给分页传参数
	    setPageParameter($Page, array('cuid'=>I("cuid"),'email'=>I('email'),'member_id'=>I('member_id'),'status'=>I('status'),'url'=>I('url')));
	    $show       = $Page->show();// 分页显示输出
	    // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
	    $list = M("Tibi")->field($field)->where($where)->join("yang_member on yang_tibi.to_member_id=yang_member.member_id")
	    ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")
	    ->group($group)
	    ->order("add_time desc")->limit($Page->firstRow.','.$Page->listRows)->select();
	    //var_dump($list);exit();
	    $this->assign('list',$list);// 赋值数据集
	    $this->assign('page',$show);// 赋值分页输出
	
	
	    //读取积分类型表
	
	    $curr=M("Currency")->select();
	    $this->assign("curr",$curr);
	
	    $this->display();
	}
	
	/**
	 * 挂单记录
	 */
	public function trade_data(){
	    $type=I('type');
	    $currency_id=I('currency_id');
	    $email=I('email');
	    $member_id = I('member_id');
	    $trade_no = I('trade_no');
	    $is_fu = I('is_fu');
	
	    $datePicker=strtotime(I('datePicker'));
	    $datePicker2=strtotime(I('datePicker2'));
	    if(!empty($type)){
	        $where['a.type'] = array("EQ",$type);
	    }
	    if(!empty($currency_id)){
	        $where['a.currency_id'] = array("EQ",$currency_id);
	    }
	    if(!empty($email)){
	        $where['c.email'] = array('like',"%".$email."%");
	    }
	    if(!empty($trade_no)){
	        $where['a.trade_no'] = array('like',"%".$trade_no."%");
	    }
	    if(!empty($datePicker) && !empty($datePicker2)  ){
	        $where['a.add_time'] = array('between',array($datePicker,$datePicker2));
	    }
	    if(!empty($member_id)){
	        $where['c.member_id'] = array('like',"%".$member_id."%");
	    }
	    if(!empty($is_fu)){
	        $where['a.money'] = array('lt',0);
	    }
	    
	    $group='DATE_FORMAT(FROM_UNIXTIME(a.add_time),"%Y-%m-%d"),b_name';
	    $field = "a.*,b.currency_name as b_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone,DATE_FORMAT(FROM_UNIXTIME(a.add_time),'%Y-%m-%d') AS add_time,sum(a.money) as money,sum(a.fee) as fee,sum(a.num) as num,count(a.trade_id) as time";
	    $count      = M('Trade')
	    ->alias('a')
	    ->field($field)
	    ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
	    ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
	    ->where($where)
	    ->count();// 查询满足要求的总记录数
	    $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
	    //给分页传参数
	    setPageParameter($Page, array('type'=>$type,'currency_id'=>$currency_id,'email'=>$email,'member_id'=>$member_id,'money'=>$is_fu));
	     
	    $show       = $Page->show();// 分页显示输出
	    // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
	    $list = M('Trade')
	    ->alias('a')
	    ->field($field)
	    ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
	    ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
	    ->where($where)
	    ->group($group)
	    ->order(" a.add_time desc ")
	    ->limit($Page->firstRow.','.$Page->listRows)
	    ->select();
	    if($list){
	        foreach ($list as $key=>$vo) {
	            $list[$key]['type_name'] = getOrdersType($vo['type']);
	        }
	    }
	    //积分类型
	    $currency = M('Currency')->field('currency_name,currency_id')->select();
	    $this->assign('currency',$currency);
	    $this->assign('list',$list);
	    $this->assign('page',$show);// 赋值分页输出
	    $this->display();
	}
}