<?php
namespace Admin\Controller;
use Admin\Controller\AdminController;
use Home\Controller\PublicController;
class ZhongchouController extends AdminController {
	//空操作
	public function _initialize(){
		parent::_initialize();
	}
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		$this->display('Public:404');
	}
	public function add(){
        if (IS_POST) {
            $lang = I('lang', 'zh');
            $issue = D('Issue');
            $issue_en = M('Issue_en');
            $issue_tc = M('Issue_tc');
            $issue_th = M('Issue_th');
            $id = I('id');
            $lock_rate = I('post.lock_rate',0,'intval');
            $where = empty($_POST['id']) ? "" : array('id' => $id);//保存查询条件
            foreach ($_POST as $k => $v) {
                $data[$k] = $v;
            }
            //判断类型不可换
            if($id){
                $issue_info=$issue->where($where)->find();
                if($data['is_forzen']!=$issue_info['is_forzen']){
                    $this->error('不可更换众筹获取类型');
                }
            }
            //处理图片
            if (!empty($_FILES["Filedata1"]["tmp_name"])) {
                $upload = $this->oss_upload($file = [], $path = 'tmp_name_pics');
                if (empty($upload)) {
                    $this->error('图片上传失败');
                }
                $data['url1'] = $upload['Filedata1'];  //保存路径到数据库
                $data['url2'] = $upload['Filedata2'];
                $data['url3'] = $upload['Filedata3'];
            }
            //处理时间
            $data['add_time'] = strtotime($data['add_time']) ? strtotime($data['add_time']) : time();
            $data['release_time'] = strtotime($data['release_time']) ? strtotime($data['release_time']) : time();
            $data['end_time'] = strtotime($data['end_time']) ? strtotime($data['end_time']) : time();
            $data['ctime'] = time();
            $data['admin_num'] = $data['admin_num'] + $data['num'];
            //成功比例
            if (!empty($_POST['zhongchou_success_bili'])) {
                $data['zhongchou_success_bili'] = I('post.zhongchou_success_bili') / 100;
            }
            //释放比例
            if (!empty($_POST['release_bili'])) {
                $data['release_bili'] = I('post.release_bili') / 100;
            }
            if ($lang === 'zh') {
                $data['title'] = I('title');//标题
                $data['content'] = I('content', '', 'htmlentities');//内容
            }
            if ($lang === 'tc') {
                $data1['title'] = I('post.tc_title', '');//标题
                $data1['info'] = I('post.tc_info', '', 'htmlentities');//内容
                $tc_find = $issue_tc->where($where)->find();
                if($id) {
                    $data1['id'] = $id;//ID
                    if (empty($tc_find)) {
                        $issue_tc->add($data1);
                    } else {
                        $issue_tc->save($data1);
                    }
                }
            }
            if ($lang === 'en') {
                $data2['title'] = I('post.en_title', '');//标题
                $data2['info'] = I('post.en_info', '', 'htmlentities');//内容
                $en_find = $issue_en->where($where)->find();
                $data2['id'] = $id;//ID
                if($id) {
                    if (empty($en_find)) {
                        $issue_en->add($data2);
                    } else {
                        $issue_en->save($data2);
                    }
                }
            }
            if ($lang === 'th') {
                $data3['title'] = I('post.th_title', '');//标题
                $data3['info'] = I('post.th_info', '', 'htmlentities');//内容
                $th_find = $issue_th->where($where)->find();
                if($id) {
                    $data3['id'] = $id;//ID
                    if (empty($th_find)) {
                        $issue_th->add($data3);
                    } else {
                        $issue_th->save($data3);
                    }
                }
            }
            if (!empty($id)) {
                $data['id'] = $id;
                $rs = $issue->save($data);
            } else {
                $rs = $issue->add($data);
            }
            if ($rs) {
                $this->success('操作成功');
            } else {
                $this->error('操作失败');
            }
        } else {
            if (!empty($_GET['id'])) {
                $list = M('Issue')->where('yang_issue.id=' . I('get.id'))
                    ->field("yang_issue.*,yang_issue_tc.title as tc_title,yang_issue_tc.info as tc_info,yang_issue_en.title as en_title,yang_issue_en.info as en_info,yang_issue_th.title as th_title,yang_issue_th.info as th_info")
                    ->join('left join yang_issue_tc ON yang_issue.id = yang_issue_tc.id')
                    ->join('left join yang_issue_en ON yang_issue.id = yang_issue_en.id')
                    ->join('left join yang_issue_th ON yang_issue.id = yang_issue_th.id')->find();
                $list['zhongchou_success_bili'] = $list['zhongchou_success_bili'] * 100;
                $list['release_bili'] = $list['release_bili'] * 100;
                $this->assign('info', $list['info']);
                $this->assign('en_info', $list['en_info']);
                $this->assign('tc_info', $list['tc_info']);
                $this->assign('th_info', $list['th_info']);
                $this->assign('list', $list);
            }
            $rateList = [100,90,80,70,60,50,40,30,20,10];
            $rateList = array_unique(array_merge([$this->config['kok_private_recharge_lock_rate']],$rateList)); //配置放第一个
            $this->assign('rateList',$rateList);
            $list = M('Currency')->field('currency_id,currency_name')->select();
            $this->assign('currency', $list);
            $this->display();
        }
	}
	public function index(){
		$this->checkZhongchou();
		$Issue=M('Issue');
		$count      = $Issue->count();// 查询满足要求的总记录数
		$Page       = new \Think\Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
		$show       = $Page->show();// 分页显示输出
		$list=$Issue
		->field('yang_issue.*,yang_currency.currency_name as name')
		->join('left join yang_currency on yang_currency.currency_id=yang_issue.currency_id')
		->limit($Page->firstRow.','.$Page->listRows)
		->order('ctime desc')
		->select();
		foreach ($list as $k=>$v){
			$list[$k]['zhongchou_success_bili']=$v['zhongchou_success_bili']*100;
		}
		$this->assign('page',$show);// 赋值分页输出
		$this->assign('list',$list);
		$this->assign('empty','暂无数据');
		$this->display();
    }
    //删除
    public function del(){
    	if(!empty($_GET['id'])){
    		$rs=M('Issue')->where('id='.$_GET['id'])->find();
        }else{
        	$this->display('Public:404');exit();
        }
        $list=M('Issue')->where('id='.$_GET['id'])->delete();
    	if($list){
    		$this->success('删除成功');
    	}else{
    		$this->error('删除失败');
    	}
    }
    //手动结束众筹
    public function zhongchouEnd(){
    	if(empty($_GET['id'])){
        	$this->display('Public:404');exit();
    	}
    	$list=M('Issue')->where('id='.$_GET['id'])->setField('status',3);
    	M('Issue')->where('id='.$_GET['id'])->setField('end_time',time());
    	if($list){
    		$this->success('操作成功');
    	}else{
    		$this->error('操作失败');
    	}
    }
    //手动开始众筹
    public function zhongchouStart(){
    	if(empty($_GET['id'])){
    		$this->display('Public:404');exit();
    	}
    	$list=M('Issue')->where('id='.$_GET['id'])->setField('status',0);
    	M('Issue')->where('id='.$_GET['id'])->setField('add_time',time());
    	M('Issue')->where('id='.$_GET['id'])->setField('end_time',time());
    	 
    	if($list){
    		$this->success('操作成功');
    	}else{
    		$this->error('操作失败');
    	}
    }
    //解冻众筹记录
    public function jiedongById(){
    	$id=I('get.id');
    	$log=M('Issue_log')->where('id='.$id)->find();
    	if($log['status']==1){
    		$this->error('该记录已处理');
    	}
    	$list[]=M('Issue_log')->where("id=$id")->setField('deal',0);
    	$list[]=M('Issue_log')->where("id=$id")->setField('add_time',time());
    	$list[]=M('Currency_user')->where("member_id={$log['uid']} and currency_id={$log['cid']}")->setInc('num',$log['deal']);
    	$list[]=M('Currency_user')->where("member_id={$log['uid']} and currency_id={$log['cid']}")->setDec('forzen_num',$log['deal']);
    	$list[]=M('Issue_log')->where("id=$id")->setField('status',1);
    	if($list){
    		$this->success('操作成功');
    	}else{
    		$this->error('操作失败');
    	}
    }
    //解冻众筹全部冻结
    public function jiedongByIid(){
    	/* $id=I('post.iid');
    	$log=M('Issue_log')->where('iid='.$id)->select();
    	foreach ($log as $k=>$v){
    		if($v['status']==1){
    			continue;
    		}
    		$list[]=M('Issue_log')->where("id={$v['id']}")->setField('deal',0);
    		$list[]=M('Issue_log')->where("id={$v['id']}")->setField('add_time',time());
    		$list[]=M('Currency_user')->where("member_id={$v['uid']} and currency_id={$v['cid']}")->setInc('num',$v['deal']);
    		$list[]=M('Currency_user')->where("member_id={$v['uid']} and currency_id={$v['cid']}")->setDec('forzen_num',$v['deal']);
    		$list[]=M('Issue_log')->where("id={$v['id']}")->setField('status',1);
    	}
    	if($list){
    		$this->success('操作成功');
    	}else{
    		$this->error('操作失败');
    	} */
    	$this->error('每天只能释放一次哦，请明天释释');
    }
    //众筹记录
    public function log(){
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

	/**
	 * 众筹推荐奖励管理
	 */
	public function awards(){
		//获取表单
		if(IS_POST){
			if(empty($_POST['ZC_AWARDS_CURRENCY_ID'])){
				$this->error('请填写积分类型');
				return;
			}
			if(!isset($_POST['ZC_AWARDS_ONE_RATIO'])){
				$this->error('请填写一级比例');
				return;
			}
			if(!isset($_POST['ZC_AWARDS_TWO_RATIO'])){
				$this->error('请填写二级比例');
				return;
			}
			if(!isset($_POST['ZC_AWARDS_STATUS'])){
				$this->error('请填写是否开启状态');
				return;
			}
			if($_POST['ZC_AWARDS_ONE_RATIO']>100 || $_POST['ZC_AWARDS_TWO_RATIO'] > 100){
				$this->error('比例不能大于100%');
				return;
			}
			//处理数据动态插入
			foreach ($_POST as $key=>$vo) {
				$r[] = M('Config')->where(array('key'=>$key))->setField('value',$vo);
			}
			//判断成功
			if(in_array(false, $r)){
				$this->success('保存成功');
			}else{
				$this->error('保存失败');
			}
		}
		else{
			//获取积分类型
			$currency = M('Currency')->field('currency_id,currency_name')->select();
			$this->assign('currency',$currency);
			$this->display();
		}
	}

	public function awardsList(){
		$count      = M('Finance')->where("type = 12 ")->count();// 查询满足要求的总记录数
		$Page       = new \Think\Page($count,10);// 实例化分页类 传入总记录数和每页显示的记录数(25)
		$show       = $Page->show();// 分页显示输出
		$list=M('Finance')
			->order('add_time desc')
			->limit($Page->firstRow.','.$Page->listRows)
			->where("type = 12 ")->select();
		dump($count);
		$this->assign('list',$list);
		$this->assign('page',$show);
		$this->assign('empty','暂无数据');
		$this->display();
	}
	
	public function manual(){
		$first_id = $_GET['id'];
		$this->assign('first_id',$first_id);
		$userid = I('post.userid','','intval');
		$second_id = I('post.second_id');
		if(!empty($userid)){
			$shows = 1;
			$this->assign('shows',$shows);
			$this->assign('userid',$userid);
			//前台众筹详细页面
			$this->checkZhongchouAdmin();
			$list=M('Issue')
			->field('yang_issue.*,yang_currency.currency_name as name')
			->join('left join yang_currency on yang_currency.currency_id=yang_issue.currency_id')
			->order('ctime desc')
			->where('id='.$second_id)
			->find();
			$list['buy_name'] = $list['buy_currency_id']==0?"人民币":M('Currency')->field('currency_name')->where("currency_id='{$list['buy_currency_id']}'")->find()['currency_name'];
			$list['buy_mark'] = $list['buy_currency_id']==0?"RMB":M('Currency')->field('currency_mark')->where("currency_id='{$list['buy_currency_id']}'")->find()['currency_mark'];
			$list['zhongchou_success_bili']=$list['zhongchou_success_bili']*100;
			$uid=$userid;
			if (!empty($uid)){
				$list['buy_count']  =$this->getIssuecountById($uid,$second_id);
			}
			if(!$list){
				$this->redirect('Public:404');
			}
			//查询个人记录
			$where['uid']=$userid;
			$where['iid']=$list['id'];
			$log=M('Issue_log')->field('*,num*price as count')->where($where)->select();
			$num_buy=M('Issue_log')->where($where)->sum('num');//一个人众筹的所有数量
			//查询账户余额
			if (!empty($uid)){
				if($list['buy_currency_id']!=0){
					$buy_num = M('Currency_user')->field('num')->where("member_id=$uid and currency_id={$list['buy_currency_id']}")->find();
					$buy_num = $buy_num['num'];
				}else{
					$buy_num = M('Member')->field('rmb')->where("member_id=$uid")->find();
					$buy_num = $buy_num['rmb'];
				}
			}
			$users = M('Member')->field('name,phone')->where("member_id=$userid")->select();
			$this->assign('users',$users[0]);
			$this->assign('id',$list['id']);
			$this->assign('num_buy',$num_buy);
			$this->assign('list',$list);
			$this->assign('buy_num',$buy_num);
		}
		$this->display();
	}
	//处理众筹
	public function subscribe(){
		$num=intval(I('post.num'));
        $original_price=intval(I('post.original_price'));
		$id=I('post.id');
		$uid=I('post.userid');
		$buy_currency_id = I('post.buy_currency_id');
		$remarks = I('post.remarks');
		//    	$member=$this->member;
		if($buy_currency_id!=0){
			$buy_num = M('Currency_user')->field('num')->where("member_id=$uid and currency_id=$buy_currency_id")->find();
			$buy_num = $buy_num['num'];
		}else{
			$buy_num = M('Member')->field('rmb')->where("member_id=$uid")->find();
			$buy_num = $buy_num['rmb'];
		}
		$config=$this->config;
		$issue=M('Issue')->where("id=$id")->find();
	
		$where['uid']=I('post.userid');
		$where['iid']=$id;
		$num_buy=M('Issue_log')->where($where)->sum('num');
	
		//获取会员一次众筹有几次记录
		$count=$this->getIssuecountById($uid,$id);
		if($issue['limit_count']<=$count){
			$data['status']=0;
			$data['info']='认筹次数不能超过限购次数';
			$this->ajaxReturn($data);
		}
		if($issue['limit']<$num){
			$data['status']=0;
			$data['info']='认筹数量不能超过限购数量';
			$this->ajaxReturn($data);
	
		}
		if($issue['min_limit']>$num){
			$data['status']=0;
			$data['info']='认筹数量不能小于最小认筹数量';
			$this->ajaxReturn($data);
		}
		if($issue['admin_deal']<$num){
			$data['status']=0;
			$data['info']='认筹数量不能超过剩余数量';
			$this->ajaxReturn($data);
		}
		if($issue['limit']<$num_buy+$num){
			$data['status']=0;
			$data['info']='您已超过总认筹限购';
			$this->ajaxReturn($data);
		}
		if($buy_num<$num*$issue['price']){
			$data['status']=0;
			$data['info']='您的账户余额不足';
			$this->ajaxReturn($data);
		}
		//修改会员表人民币字段
		//    	$rs=M('Member')->where("member_id=$uid")->setDec('rmb',$num*$issue['price']*$config['bili']);
		if($buy_currency_id!=0){
			$rs = M('Currency_user')->where("member_id=$uid and currency_id={$issue['currency_id']}")->setDec('num',$num*$issue['price']);
		}else{
			$rs = M('Member')->where("member_id=$uid")->setDec('rmb',$num*$issue['price']);
		}
		//添加财务日志表
		if($rs){
			//添加认购记录
			$arr['iid']=$id;
			$arr['uid']=$uid;
			$arr['cid']=$issue['currency_id'];
			$arr['num']=$num;
			$arr['release_num']=0;
			$arr['deal']=$num;
			$arr['price']=$issue['price'];
            $arr['original_price']=$original_price;
			$arr['add_time']=time();
			$arr['buy_currency_id']=$buy_currency_id;
			$arr['status']=0;
			$arr['is_admin']='admin';
			$arr['remarks']=$remarks;
			$arr['timebackup']=time();
			M('Issue_log')->add($arr);
			//修改会员积分类型数量
			if($issue['is_forzen']==0){
				M('Currency_user')->where("member_id=$uid and currency_id={$issue['currency_id']}")->setInc('forzen_num',$num);
			}else{
				M('Currency_user')->where("member_id=$uid and currency_id={$issue['currency_id']}")->setInc('num',$num);
			}
			if($buy_currency_id==0){
				$this->addFinance($uid, 8, '众筹扣款'.$num*$issue['price'], $num*$issue['price'], 2, $buy_currency_id);
				$this->addFinance($uid, 9, '众筹获取'.$num, $num, 1, $buy_currency_id);
			}
			//添加信息表
			$this->addMessage_all($uid, -2, '众筹成功', '您参与的众筹项目'.$issue['title'].'已成功,扣除交易积分'.$num*$issue['price'].',获取众筹积分'.$num);
			//添加众筹推荐人员奖励
			if($this->config['ZC_AWARDS_STATUS']==1){
				$this->setAwards($uid,$num);
			}
			$data['status']=0;
			$data['info']='认筹成功';
			$this->ajaxReturn($data);
		}else{
			//添加信息表
			$this->addMessage_all($uid, -2, '众筹失败', '您参与的众筹项目'.$issue['title'].'未成功');
			$data['status']=0;
			$data['info']='认筹失败';
			$this->ajaxReturn($data);
		}
	}
	public function setAwards($uid,$num){
		//查找此用户下推荐人 一代 二代
		$u_info = M('Member')->field('member_id,pid')->where("member_id = '{$uid}'")->find();
		if(empty($u_info['pid'])){
			return true;
		}
		$one_info = M('Member')->field('member_id,pid')->where("member_id = '{$u_info['pid']}'")->find();
		$one_where['member_id'] = $one_info['member_id'];
		$one_where['currency_id'] = $this->config['ZC_AWARDS_CURRENCY_ID'];
		$num = $this->config['ZC_AWARDS_ONE_RATIO']/100*$num;
	
		if ($u_info['status']==1){//只有用户正常情况才会有推荐奖励
			$r = M('Currency_user')->where($one_where)->setInc('num',$num);
			$this->addFinance($one_info['member_id'], 12, '众筹推荐奖励'.$num, $num, 1, $this->config['ZC_AWARDS_CURRENCY_ID']);
		}
	
		if(empty($one_info['pid'])){
			return true;
		}
		$two_info = M('Member')->field('member_id,pid')->where("member_id = '{$one_info['pid']}'")->find();
		$two_where['member_id'] = $two_info['member_id'];
		$two_where['currency_id'] = $this->config['ZC_AWARDS_CURRENCY_ID'];
		$num = $this->config['ZC_AWARDS_TWO_RATIO']/100*$num;
		if ($two_info['status']==1){//只有用户正常情况才会有推荐奖励
			$r = M('Currency_user')->where($two_where)->setInc('num',$num);
			$this->addFinance($two_info['member_id'], 12, '众筹推荐奖励'.$num, $num, 1, $this->config['ZC_AWARDS_CURRENCY_ID']);
		}
		if($r){
			return true;
		}
	}
    public function setOriginal_price(){
        $data = [];
        if(IS_POST){
            $id = I('post.id');
            $original_price = I('post.original_price');
            if(empty($id) || empty($original_price)){
                $data['message'] = '编号，原始价不能为空';
                $data['status'] = 0;
                $this->ajaxReturn($data);
            }

            $arr['id'] = $id;
            $arr['original_price'] = $original_price;
            $re = M('issue_log')->save($arr);
            if($re){
                $data['message'] = '更改成功';
                $data['status'] = 1;
                $this->ajaxReturn($data);
            }else{
                $data['message'] = '更改失败';
                $data['status'] = 0;
                $this->ajaxReturn($data);
            }
        }
        $data['message'] = '请求错误。';
        $data['status'] = 0;
        $this->ajaxReturn($data);
    }
}