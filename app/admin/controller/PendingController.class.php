<?php
/*
 * 后台审核提现
 */
namespace Admin\Controller;

use Admin\Controller\AdminController;

class PendingController extends AdminController
{
    // 空操作
    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    public function index()
    {
        $uid = $_SESSION['admin_userid'];
        $admin_username = M('Admin')->where('admin_id=' . $uid . '')->field('username,admin_id')->select();
        $this->username = $admin_username;
        $withdraw = M('Withdraw');
        $bank = M('bank');
        $name = I('cardname');
        $names = I('keyname');
        $keyid = I('keyid');
        $withdraw_id = I('withdraw_id');
        $keynum = I('keynum');
        $member_withdrawtype = I('member_withdrawtype');
        //ajax中的提交值
        $currencyId = I('currency_id');
        // I('status')--分页下标生成参数
        if (I('pend') != 0 || I('status') != 0) {
            $where ['yang_withdraw.status'] = I('status') ? I('status') : I('pend');
        }
        if (!empty($name) || !empty($names)) {
            // 如果传回的是post（keyname）就用post，否则用get（cardname）
            $cardname = I('keyname') ? I('keyname') : I('cardname');
            //模糊
            $where ['yang_bank.cardname'] = array('like', '%' . $cardname . '%');
        }
        if (!empty($keyid)) {
            $where ['yang_withdraw.uid'] = $keyid ;
        }
        if (!empty($withdraw_id)) {
            $where ['yang_withdraw.withdraw_id'] = $withdraw_id;
        }
        if (!empty($keynum)) {
            $where ['email'] = array('like', '%' . $keynum . '%');
        }
        $where ['member_withdrawtype'] ='0';
        if (!empty($member_withdrawtype)) {
            if($member_withdrawtype==6){
                $where ['member_withdrawtype'] ='0';
            }else {
            $where ['member_withdrawtype'] = $member_withdrawtype;
            }
        }
        // 查询满足要求的总记录数
        $count = $withdraw->join("yang_bank ON yang_withdraw.bank_id = yang_bank.id")->join("yang_member on yang_withdraw.uid=yang_member.member_id")->where($where)->count();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count, 20);
        //将分页（点击下一页）需要的条件保存住，带在分页中
        $Page->parameter = array(
            'yang_withdraw.status' => $where ['yang_withdraw.status'],
            'yang_bank.cardname' => $cardname,
            'keyid' => $keyid,
            'keynum' => $keynum,
            'member_withdrawtype'=> $member_withdrawtype
        );
        // 分页显示输出
        $show = $Page->show();
        //需要的数据
        $field = "yang_withdraw.*,yang_bank.cardname,yang_bank.cardnum,yang_bank.bankname,yang_bank.account_inname,b.area_name as barea_name,a.area_name as aarea_name,c.email,c.member_withdrawtype,c.name";
        $info = $withdraw->field($field)
            ->join("left join yang_bank ON yang_withdraw.bank_id = yang_bank.id")
            ->join("left join yang_areas as b ON b.area_id = yang_bank.address")
            ->join("left join yang_areas as a ON a.area_id = b.parent_id ")
            ->join("left join yang_member as c on yang_withdraw.uid=c.member_id")
            ->where($where)
            ->order('yang_withdraw.add_time desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        $this->assign('info', $info); // 赋值数据集
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('inquire', $cardname);
        $this->assign('keyid', $keyid);
        $this->display();
    }
    
    
    /**
     * 不通过提现请求(第三环)
     * @param unknown $id
     */
    public function falseByid(){
        $id = intval ( I ( 'post.id' ) );
        $remark =  I ( 'post.remark' );
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
    
        $managername = M('Admin')->where('admin_id='.$_SESSION['admin_userid'].'')->find();
        //不通过状态为1
        $data ['status'] = 1;
        $data ['check_time'] = time();
        $data ['admin_uid'] =$_SESSION['admin_userid'];
        $data ['thirdaudit_term'] = 1;
        $data ['thirdaudit_time'] = time();
        $data ['thirdaudit_username'] = $managername['username'];
        $data ['thirdaudit_remarks'] = $remark;
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

        //推送到APP
        $jpush = A("Api/Jpush");
        $jpush->index('withdraw', 'err', $info['member_id']);

        $this->addMessage_all($info['member_id'],-2,'CNY提现失败','很抱歉您提现失败，请重新操作或联系客服！');
        $datas['status'] = 1;
        $datas['info'] = "提现不通过，操作成功";
        $this->ajaxReturn($datas);
    }
    
    
    /**
     * 付款失败
     * @param unknown $id
     */
    public function falseByid_pay(){
        $id = intval ( I ( 'post.id' ) );
        $remark =  I ( 'post.remark' );
        //判断是否$id为空
        if (empty ( $id ) ) {
            $this->error ( "参数错误" );
            return;
        }
         
        //查询用户可用金额等信息
        $info = $this->getMoneyByid($id);
        $pay_time=time()-$info['add_time'];
         
    
        if($info['status']!=2){
            $datas['status'] = -1;
            $datas['info'] = "通过才可操作，请不要重复操作";
            $this->ajaxReturn($datas);
        }
        //超过14天不给撤销
        if($pay_time>11204800){
            $datas['status'] = -1;
            $datas['info'] = "超过付款失败，可撤销时间";
            $this->ajaxReturn($datas);
        }
        //将提现的钱加回可用金额
        $money['rmb'] = floatval($info['rmb']) + floatval($info['all_money']);
        //将冻结的钱减掉
        //$money['forzen_rmb'] = floatval($info['forzen_rmb']) - floatval($info['all_money']);
    
        $managername = M('Admin')->where('admin_id='.$_SESSION['admin_userid'].'')->find();
        //银行卡有误状态为-1
        $data ['status'] = -1;
        $data ['check_time'] = time();
        $data ['admin_uid'] =$_SESSION['admin_userid'];
        $data ['thirdaudit_term'] = 1;
        $data ['thirdaudit_time'] = time();
        $data ['thirdaudit_username'] = $managername['username'];
        $data ['thirdaudit_remarks'] = $remark;
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

        //推送到APP
        $jpush = A("Api/Jpush");
        $jpush->index('withdraw', 'err', $info['member_id']);

        $this->addMessage_all($info['member_id'],-2,'CNY提现失败','银行卡有误，请重新添加正确银行卡或联系客服！');
        $datas['status'] = 1;
        $datas['info'] = "提现不通过，操作成功";
        $this->ajaxReturn($datas);
    }
    
    /**
     * 付款成功
     * @param unknown $id
     */
    public function succeedByid_pay(){
        $id = intval ( I ( 'post.id' ) );
        $remark =  I ( 'post.remark' );
        //判断是否$id为空
        if (empty ( $id ) ) {
            $this->error ( "参数错误" );
            return;
        }
    
        //查询用户可用金额等信息
        $info = $this->getMoneyByid($id);
        $pay_time=time()-$info['add_time'];
    
    
        if($info['status']!=2){
            $datas['status'] = -1;
            $datas['info'] = "通过才可操作，请不要重复操作";
            $this->ajaxReturn($datas);
        }
         
        //将提现的钱加回可用金额
        // $money['rmb'] = floatval($info['rmb']) + floatval($info['all_money']);
        //将冻结的钱减掉
        //$money['forzen_rmb'] = floatval($info['forzen_rmb']) - floatval($info['all_money']);
    
        $managername = M('Admin')->where('admin_id='.$_SESSION['admin_userid'].'')->find();
        //付款成功状态为4
        $data ['status'] = 4;
        $data ['check_time'] = time();
        $data ['admin_uid'] =$_SESSION['admin_userid'];
        $data ['thirdaudit_term'] = 1;
        $data ['thirdaudit_time'] = time();
        $data ['pay_time'] = time();
        $data ['thirdaudit_username'] = $managername['username'];
        $data ['thirdaudit_remarks'] = $remark;
        //更新数据库,member修改金额
        //$res = M( 'Member' )->where("member_id = {$info['member_id']}")->save($money);
        //withdraw修改状态
        $re = M ( 'Withdraw' )->where ( "withdraw_id = '{$id}'" )->save ( $data );
         
        if($re == false){

            //推送到APP
            $jpush = A("Api/Jpush");
            $jpush->index('withdraw', 'err', $info['member_id']);

            $datas['status'] = 2;
            $datas['info'] = "付款不成功，操作失败";
            $this->ajaxReturn($datas);
        }

        //推送到APP
        $jpush = A("Api/Jpush");
        $jpush->index('withdraw', 'suc', $info['member_id']);

        //$this->addMessage_all($info['member_id'],-2,'CNY提现失败','银行卡有误，请重新添加正确银行卡或联系客服！');
        $datas['status'] = 1;
        $datas['info'] = "付款成功，操作成功";
        $this->ajaxReturn($datas);
    }
   
    
    /**
     * 财务提现审核第一环通过
     * @param unknown $id
     */
    public function successByidfirst()
    {
        $id = intval(I('post.id'));
        $remark = I('post.remark');
        //判断是否$id为空
        if (empty ($id)) {
            $datas['status'] = 0;
            $datas['info'] = "参数错误";
            $this->ajaxReturn($datas);
        }
        //查询用户可用金额等信息
        $info = $this->getMoneyByid($id);
        if ($info['status'] != 3) {
            $datas['status'] = -1;
            $datas['info'] = "请不要重复操作";
            $this->ajaxReturn($datas);
        }
        $managername = M('Admin')->where('admin_id=' . $_SESSION['admin_userid'] . '')->find();
        //通过状态为2
        $data ['firstaudit_term'] = 2;
        $data ['firstaudit_time'] = time();
        $data ['firstaudit_username'] = $managername['username'];
        $data ['firstaudit_remarks'] = $remark;
        //更新数据库
        $re = M('Withdraw')->where("withdraw_id = '{$id}'")->save($data);
        if ($re == false) {
            $datas['status'] = 0;
            $datas['info'] = "初审操作通过失败";
            $this->ajaxReturn($datas);
        }
        $datas['status'] = 1;
        $datas['info'] = "初审操作通过成功";
        $this->ajaxReturn($datas);
    }

    /**
     * 财务提现审核第一环不通过
     * @param unknown $id
     */
    public function falseByidfirst()
    {
        $id = intval(I('post.id'));
        $remark = I('post.remark');
        //判断是否$id为空
        if (empty ($id)) {
            $this->error("参数错误");
            return;
        }
        //查询用户可用金额等信息
        $info = $this->getMoneyByid($id);
        if ($info['status'] != 3) {
            $datas['status'] = -1;
            $datas['info'] = "请不要重复操作";
            $this->ajaxReturn($datas);
        }
        $managername = M('Admin')->where('admin_id=' . $_SESSION['admin_userid'] . '')->find();
        //不通过状态为1
        $data ['firstaudit_term'] = 1;
        $data ['firstaudit_time'] = time();
        $data ['firstaudit_username'] = $managername['username'];
        $data ['firstaudit_remarks'] = $remark;

        //withdraw修改状态
        $re = M('Withdraw')->where("withdraw_id = '{$id}'")->save($data);

        if ($re == false) {
            $datas['status'] = 0;
            $datas['info'] = "初审操作不通过失败";
            $this->ajaxReturn($datas);
        }
        $datas['status'] = 1;
        $datas['info'] = "初审操作不通过成功";
        $this->ajaxReturn($datas);
    }

    /**
     * 财务提现审核第二环通过
     * @param unknown $id
     */
    public function successByidsecond()
    {
        $id = intval(I('post.id'));
        $remark = I('post.remark');
        //判断是否$id为空
        if (empty ($id)) {
            $datas['status'] = 0;
            $datas['info'] = "参数错误";
            $this->ajaxReturn($datas);
        }
        //查询用户可用金额等信息
        $info = $this->getMoneyByid($id);
        if ($info['status'] != 3) {
            $datas['status'] = -1;
            $datas['info'] = "请不要重复操作";
            $this->ajaxReturn($datas);
        }
        $managername = M('Admin')->where('admin_id=' . $_SESSION['admin_userid'] . '')->find();
        //通过状态为2
        $data ['secondaudit_term'] = 2;
        $data ['secondaudit_time'] = time();
        $data ['secondaudit_username'] = $managername['username'];
        $data ['secondaudit_remarks'] = $remark;
        //更新数据库
        $re = M('Withdraw')->where("withdraw_id = '{$id}'")->save($data);
        if ($re == false) {
            $datas['status'] = 0;
            $datas['info'] = "二审操作通过失败";
            $this->ajaxReturn($datas);
        }
        $datas['status'] = 1;
        $datas['info'] = "二审操作通过成功";
        $this->ajaxReturn($datas);
    }

    /**
     * 财务提现审核第二环不通过
     * @param unknown $id
     */
    public function falseByidsecond()
    {
        $id = intval(I('post.id'));
        $remark = I('post.remark');
        //判断是否$id为空
        if (empty ($id)) {
            $this->error("参数错误");
            return;
        }
        //查询用户可用金额等信息
        $info = $this->getMoneyByid($id);
        if ($info['status'] != 3) {
            $datas['status'] = -1;
            $datas['info'] = "请不要重复操作";
            $this->ajaxReturn($datas);
        }
        $managername = M('Admin')->where('admin_id=' . $_SESSION['admin_userid'] . '')->find();
        //不通过状态为1
        $data ['secondaudit_term'] = 1;
        $data ['secondaudit_time'] = time();
        $data ['secondaudit_username'] = $managername['username'];
        $data ['secondaudit_remarks'] = $remark;

        //withdraw修改状态
        $re = M('Withdraw')->where("withdraw_id = '{$id}'")->save($data);

        if ($re == false) {
            $datas['status'] = 0;
            $datas['info'] = "二审操作不通过失败";
            $this->ajaxReturn($datas);
        }
        $datas['status'] = 1;
        $datas['info'] = "二审操作不通过成功";
        $this->ajaxReturn($datas);
    }

    /**
     * 通过提现请求(第三环)
     * @param unknown $id
     */
    public function successByid()
    {
        $id = intval(I('post.id'));
        $remark = I('post.remark');
        //判断是否$id为空
        if (empty ($id)) {
            $datas['status'] = 3;
            $datas['info'] = "参数错误";
            $this->ajaxReturn($datas);
        }
        //查询用户可用金额等信息
        $info = $this->getMoneyByid($id);
        if ($info['status'] != 3) {
            $datas['status'] = -1;
            $datas['info'] = "请不要重复操作";
            $this->ajaxReturn($datas);
        }
        $managername = M('Admin')->where('admin_id=' . $_SESSION['admin_userid'] . '')->find();
        //通过状态为2
        $data ['status'] = 2;
        $data ['check_time'] = time();
        $data ['admin_uid'] = $_SESSION['admin_userid'];
        $data ['thirdaudit_term'] = 2;
        $data ['thirdaudit_time'] = time();
        $data ['thirdaudit_username'] = $managername['username'];
        $data ['thirdaudit_remarks'] = $remark;
        //更新数据库
        $re = M('Withdraw')->where("withdraw_id = '{$id}'")->save($data);
        $num = M('Withdraw')->where("withdraw_id = '{$id}'")->find();
        M('Member')->where("member_id={$num['uid']}")->setDec('forzen_rmb', $num['all_money']);
        if ($re == false) {
            $datas['status'] = 0;
            $datas['info'] = "提现操作失败";
            $this->ajaxReturn($datas);
        }
        $this->addMessage_all($info['member_id'], -2, 'CNY提现成功', "恭喜您提现{$info['all_money']}成功！");
        $this->addFinance($info['member_id'], 23, "提现{$info['all_money']}", $info['withdraw_fee'], 2, 0);
        $datas['status'] = 1;
        $datas['info'] = "提现通过，操作成功";
        $this->ajaxReturn($datas);
    }

  

    /**
     * 获取提现金额信息
     * @param unknown $id
     * @return boolean|unknown $rmb 会员号，可用金额，冻结金额，手续费，提现金额
     */
    public function getMoneyByid($id)
    {

        $field = "yang_member.member_id,yang_member.rmb,yang_member.forzen_rmb,yang_withdraw.status,yang_withdraw.all_money,yang_withdraw.withdraw_fee,yang_withdraw.add_time";
        $rmb = M('Withdraw')
            ->field($field)
            ->join('yang_member ON yang_withdraw.uid = yang_member.member_id')
            ->where("withdraw_id = '{$id}'")
            ->find();
        if (empty($rmb)) {
            return false;
        }
        return $rmb;
    }

    //获取财务管理->人工充值管理中的数据,涉及到yang_member、yang_pay
    public function xiangDan()
    {
        //取会员ID、将会员ID和状态赋给字段
        $uid = I('uid');
        $where['yang_pay.member_id'] = $uid;
        $where['yang_pay.status'] = 1;
        //取总记录数
        $count = M('Pay')->where($where)->join('left join yang_member on yang_member.member_id=yang_pay.member_id')->count();
        //取结果
        $list = M('Pay')
            ->field('yang_pay.*,yang_member.email,yang_member.phone')
            ->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
            ->where($where)
            ->order('add_time desc')
            ->select();
        //将状态字段的数值用文字来表示
        foreach ($list as $k => $v) {
            $list[$k]['status'] = payStatus($v['status']);
        }
        //循环打印结果
        for ($i = 0; $i < $count; $i++) {
            if ($list[$i]["currency_id"] > 0) {
                $currency_type = "充积分";
            } else {
                $currency_type = "充值";
            }
            $a .= '<tr><td>' . $list[$i]["pay_id"] . '</td>
                     		<td>' . $list[$i]["email"] . '</td>
							<td>' . $list[$i]["member_name"] . '</td>
							<td>' . $list[$i]["member_id"] . '</td>
							<td>' . $list[$i]["account"] . '</td>
							<td>' . $list[$i]["money"] . '</td>
							<td>' . $list[$i]["count"] . '</td>
							<td>' . $list[$i]["status"] . '</td>
							<td>' . $currency_type . '</td>
							<td>' . date("Y-m-d H:i:s", $list[$i]["add_time"]) . '</td>
							<td>' . $list[$i]["due_bank"] . '</td>
							<td>' . $list[$i]["batch"] . '</td>
							<td>' . $list[$i]["capital"] . '</td>
							<td>' . $list[$i]["commit_name"] . '</td>
							<td>' . date("Y-m-d H:i:s", $list[$i]["commit_time"]) . '</td>
							<td>' . $list[$i]["audit_name"] . '</td>
							<td>' . date("Y-m-d H:i:s", $list[$i]["audit_time"]) . '</td></tr>';
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
        $a .= '<tr><td><span style="font-size:16px;color:#2F4F4F;">充值合计：</span></td><td>' . $totalcz[0]['totalczmoney'] . '</td>
			 <td><span style="font-size:16px;color:#2F4F4F;">充积分合计：</span></td><td>' . $totalcb[0]['totalcurrency'] . '</td>
			 <td><span style="font-size:16px;color:#2F4F4F;">实际打款合计：</span></td><td>' . $totalcz[0]['totalczcount'] . '</td></tr>';

        $this->ajaxReturn($a);
    }

    //获取众筹管理->众筹记录中的数据,涉及到yang_member、yang_issue、yang_issue_log
    public function xiangdan_zc()
    {
        $uid_zc = I('uid_zc');
        $option_val = I('option_val');
        $where['yang_member.member_id'] = $uid_zc;
        if ($option_val !== '') {
            if ($option_val != -1) {
                $where['yang_issue_log.cid'] = $option_val;
            }
        }
        $count = M('Issue_log')->join('left join yang_member on yang_member.member_id=yang_issue_log.uid')->join('left join yang_issue on yang_issue.id=yang_issue_log.iid')->where($where)->count();
        $log = M('Issue_log')
            ->field('yang_issue_log.*,yang_member.member_id,yang_member.name,yang_issue.title,yang_issue_log.num*yang_issue_log.price as count')
            ->join('left join yang_member on yang_member.member_id=yang_issue_log.uid')
            ->join('left join yang_issue on yang_issue.id=yang_issue_log.iid')
            ->order('add_time desc')
            ->where($where)->select();
        foreach ($log as $key => $vo) {
            $log[$key]['buy_name'] = $vo['buy_currency_id'] == 0 ? "人民币" : M('Currency')->where("currency_id='{$vo['buy_currency_id']}'")->find()['currency_name'];
        }
        $curName = M("Currency")->field('currency_id,currency_name')->select();
        if ($option_val == "") {
            $b .= '<select name="currency_id" id="select_zc">
                 <option value="-1" selected>全部</option>
					<option value="0">人民币</option>';
        } else {
            if ($option_val == -1) {
                $b .= '<select name="currency_id" id="select_zc">
                 <option value="-1" selected>全部</option>';
            } else {
                $b .= '<select name="currency_id" id="select_zc">
                 <option value="-1">全部</option>';
            }
            if ($option_val == 0) {
                $b .= '<option value="0" selected>人民币</option>';
            } else {
                $b .= '<option value="0">人民币</option>';
            }
        }
        foreach ($curName as $key => $cur) {
            if ($option_val == $cur["currency_id"]) {
                $b .= '<option value=' . $cur["currency_id"] . ' id="currencyId" selected>' . $cur["currency_name"] . '</option>';
            } else {
                $b .= '<option value=' . $cur["currency_id"] . ' id="currencyId">' . $cur["currency_name"] . '</option>';
            }
        }

        $b .= '</select>|';
        for ($i = 0; $i < $count; $i++) {
            $b .= '<tr><td>' . $log[$i]["iid"] . '</td>
                     		<td>' . $log[$i]["title"] . '</td>
							<td>' . $log[$i]["name"] . '</td>
							<td>' . $log[$i]["member_id"] . '</td>
							<td>' . $log[$i]["num"] . '</td>
							<td>' . $log[$i]["deal"] . '</td>
							<td>' . $log[$i]["price"] . '</td>
							<td>' . $log[$i]["count"] . '</td>
							<td>' . date("Y-m-d H:i:s", $log[$i]["add_time"]) . '</td>
							<td>' . $log[$i]["buy_name"] . '</td>
							<td>' . $log[$i]["remarks"] . '</td></tr>';
        }

        //统计
        $totalzc = M('Issue_log')
            ->field('sum(yang_issue_log.num) as buynum,sum(yang_issue_log.deal) as freezenum,sum(yang_issue_log.num*yang_issue_log.price) as totalaggregate')
            ->join('left join yang_member on yang_member.member_id=yang_issue_log.uid')
            ->join('left join yang_issue on yang_issue.id=yang_issue_log.iid')
            ->where($where)
            ->select();
        $b .= '<tr><td><span style="font-size:16px;color:#2F4F4F;">购买数量合计：</span></td><td>' . $totalzc[0]['buynum'] . '</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">冻结数量合计：</span></td><td>' . $totalzc[0]['freezenum'] . '</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">购买总额合计：</span></td><td>' . $totalzc[0]['totalaggregate'] . '</td></tr>';

        $this->ajaxReturn($b);
    }


    //会员管理->会员列表,涉及到yang_member
    public function xiangDan_user()
    {
        $uid_user = I('uid_user');
        $where['yang_currency_user.member_id'] = $uid_user;
        $filed = 'yang_member.*,yang_currency.currency_name,yang_currency_user.num,yang_currency_user.forzen_num,yang_currency_user.num_award,yang_currency_user.sum_award,yang_currency_user.currency_id';
        $count = M('Currency_user')->field($filed)->join('yang_currency ON yang_currency_user.currency_id = yang_currency.currency_id')
            ->join('yang_member on yang_currency_user.member_id = yang_member.member_id')
            ->where($where)->count();
        $list_user = M('Currency_user')->field($filed)->join('yang_currency ON yang_currency_user.currency_id = yang_currency.currency_id')
            ->join('yang_member on yang_currency_user.member_id = yang_member.member_id')
            ->where($where)
            ->select();

        $c .= '<tr><td>' . $list_user[0]["member_id"] . '</td>
                     		<td>' . $list_user[0]["email"] . '</td>
							<td>' . $list_user[0]["name"] . '</td>
							<td>' . $list_user[0]["phone"] . '</td>
							<td>' . $list_user[0]["rmb"] . '</td>
							<td>' . $list_user[0]["forzen_rmb"] . '</td>
							<td>' . date("Y-m-d H:i:s", $list_user[0]["reg_time"]) . '</td></tr>';
        /* <td>'.$list_user[0]["remarks"].'</td> */
        $c .= '<tr>
                        <th>积分类型名称</th>
                        <th>持有数量</th>
						<th>冻结数量</th>
						<th>剩余奖励</th>
						<th>总奖励</th> 
						<th>充积分数量</th>
						<th>购买量</th>
						<th>卖出量</th>
                    </tr>';
        for ($i = 0; $i < $count; $i++) {
            //充积分数据
            $wherea['yang_tibi.currency_id'] = $list_user[$i]["currency_id"];
            $wherea['yang_tibi.user_id'] = $uid_user;
            $wherea['yang_tibi.status'] = 3;
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
            if ($list_user[$i]["num"] == 0 && $list_user[$i]["forzen_num"] == 0 && $list_user[$i]["num_award"] == 0 
            	&& $list_user[$i]["sum_award"] == 0 && $totalcb[0]['totalcharging'] == 0 && $totaljybuy[0]['jybuynum'] == 0 
            	&& $totaljysell[0]['jybuynum'] == 0 ) {
            		$c .= '';
            } else {
            	if ($list_user[$i]["forzen_num"] < 0) {
            		$c .= '<tr><td>' . $list_user[$i]["currency_name"] . '</td>
							<td>' . $list_user[$i]["num"] . '</td>
							<td><span style="color:#921AFF;">' . $list_user[$i]["forzen_num"] . '</span></td>
							<td>' . $list_user[$i]["num_award"] . '</td>
							<td>' . $list_user[$i]["sum_award"] . '</td>
							<td>' . $totalcb[0]['totalcharging'] . '</td>
							<td>' . $totaljybuy[0]['jybuynum'] . '</td>
							<td>' . $totaljysell[0]['jybuynum'] . '</td></tr>';
            	}else{
            		$c .= '<tr><td>' . $list_user[$i]["currency_name"] . '</td>
							<td>' . $list_user[$i]["num"] . '</td>
							<td>' . $list_user[$i]["forzen_num"] . '</td>
							<td>' . $list_user[$i]["num_award"] . '</td>
							<td>' . $list_user[$i]["sum_award"] . '</td>
							<td>' . $totalcb[0]['totalcharging'] . '</td>
							<td>' . $totaljybuy[0]['jybuynum'] . '</td>
							<td>' . $totaljysell[0]['jybuynum'] . '</td></tr>';
            	}
            }
               
        }
        //实际充值钱、提现钱、用户备注
        $c .= '|';
        $wherec['yang_pay.member_id'] = $uid_user;
        $wherec['yang_pay.status'] = 1;
        $totalcz = M('Pay')
            ->field('sum(count) as totalcount')
            ->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
            ->where($wherec)
            ->where('yang_pay.currency_id=0')
            ->select();
        $c .= $totalcz[0]['totalcount'];
        $c .= '|';
        $whered['yang_bank.uid'] = $uid_user;
        $whered['yang_withdraw.status'] = array('in','2,4');
        $totaltx = M('bank')
            ->field('sum(money) as totalmoney')
            ->join("yang_withdraw ON yang_withdraw.bank_id = yang_bank.id")
            ->join("yang_areas as b ON b.area_id = yang_bank.address")
            ->join("yang_areas as a ON a.area_id = b.parent_id ")
            ->join("yang_member as c on yang_withdraw.uid=c.member_id")
            ->where($whered)
            ->select();
        $c .= $totaltx[0]['totalmoney'];
        $c .= '|';
        $c .= $totalcz[0]['totalcount'] - $totaltx[0]['totalmoney'];
        $c .= '|';
        $list_user_remark = M('Currency_user')->field('yang_member.remarks')->join('yang_currency ON yang_currency_user.currency_id = yang_currency.currency_id')
            ->join('yang_member on yang_currency_user.member_id = yang_member.member_id')
            ->where($where)
            ->select();
        $c .= $list_user_remark[0]["remarks"];

        $this->ajaxReturn($c);
    }


    //交易管理->交易记录,涉及到yang_trade、yang_currency、yang_member
    public function xiangDan_jy()
    {
        $uid_jy = I('uid_jy');
        $option_valjy = I('option_valjy');
        $where['c.member_id'] = $uid_jy;
        if ($option_valjy !== '') {
            if ($option_valjy != -1) {
                $where['a.currency_id'] = $option_valjy;
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
        foreach ($list_jy as $key => $vo) {
            $list_jy[$key]['type_name'] = getOrdersType($vo['type']);
        }
        $curName_jy = M('Currency')->field('currency_id,currency_name')->select();
        if ($option_valjy == "") {
            $d .= '<select name="currency_id3" id="select_jy">
                 <option value="-1" selected>全部</option>
					<option value="0">人民币</option>';
        } else {
            if ($option_valjy == -1) {
                $d .= '<select name="currency_id3" id="select_jy">
                 <option value="-1" selected>全部</option>';
            } else {
                $d .= '<select name="currency_id3" id="select_jy">
                 <option value="-1">全部</option>';
            }
            if ($option_valjy == 0) {
                $d .= '<option value="0" selected>人民币</option>';
            } else {
                $d .= '<option value="0">人民币</option>';
            }
        }
        foreach ($curName_jy as $key => $cur) {
            if ($option_valjy == $cur["currency_id"]) {
                $d .= '<option value=' . $cur["currency_id"] . ' id="currencyId3" selected>' . $cur["currency_name"] . '</option>';
            } else {
                $d .= '<option value=' . $cur["currency_id"] . ' id="currencyId3">' . $cur["currency_name"] . '</option>';
            }
        }
        $d .= '</select>|';
        for ($i = 0; $i < $count; $i++) {
            $d .= '<tr><td>' . $list_jy[$i]["trade_id"] . '</td>
                     		<td>' . $list_jy[$i]["trade_no"] . '</td>
							<td>' . $list_jy[$i]["email"] . '</td>							
							<td>' . $list_jy[$i]["b_name"] . '</td>
							<td>' . $list_jy[$i]["num"] . '</td>
							<td>' . $list_jy[$i]["price"] . '</td>
							<td>' . $list_jy[$i]["money"] . '</td>
							<td>' . number_format($list_jy[$i]["fee"], 4, '.', '') . '</td>
							<td>' . $list_jy[$i]["type_name"] . '</td>
							<td>' . date("Y-m-d H:i:s", $list_jy[$i]["add_time"]) . '</td></tr>';
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
        $d .= '<tr><td><span style="font-size:16px;color:#2F4F4F;">买入量合计：</span></td><td>' . $totaljybuy[0]['jybuynum'] . '</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">买入金额合计：</span></td><td>' . $totaljybuy[0]['jybuymoney'] . '</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">卖出量合计：</span></td><td>' . $totaljysell[0]['jybuynum'] . '</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">卖出金额合计：</span></td><td>' . $totaljysell[0]['jybuymoney'] . '</td></tr>';

        $this->ajaxReturn($d);
    }


    //财务管理->提现审核,涉及到yang_bank、yang_withdraw、yang_areas、yang_member
    public function xiangDan_tx()
    {
        $uid_tx = I('uid_tx');
        $where['yang_bank.uid'] = $uid_tx;
        $where ['yang_withdraw.status'] =array('in','2,4');
        $count = M('Withdraw')->join("yang_bank ON yang_withdraw.bank_id = yang_bank.id")->join("yang_member on yang_withdraw.uid=yang_member.member_id")->where($where)->count();
        $field = "yang_withdraw.*,yang_bank.cardname,yang_bank.cardnum,yang_bank.bankname,b.area_name as barea_name,a.area_name as aarea_name,c.email";
        $list_tx = M('bank')->field($field)
            ->join("yang_withdraw ON yang_withdraw.bank_id = yang_bank.id")
            ->join("yang_areas as b ON b.area_id = yang_bank.address")
            ->join("yang_areas as a ON a.area_id = b.parent_id ")
            ->join("yang_member as c on yang_withdraw.uid=c.member_id")
            ->where($where)
            ->order('yang_withdraw.status desc,yang_withdraw.add_time desc')
            ->select();
        foreach ($list_tx as $key => $v) {
            if($v['status'] == 2 ){
                $list_tx[$key]['status'] ='通过';
            }else {
                $list_tx[$key]['status'] ='付款成功';
            }
            
        }
        for ($i = 0; $i < $count; $i++) {
            $e .= '<tr><td>' . $list_tx[$i]["withdraw_id"] . '</td>
                     		<td>' . $list_tx[$i]["cardname"] . '</td>
							<td>' . $list_tx[$i]["uid"] . '</td>
							<td>' . $list_tx[$i]["bankname"] . '</td>
							<td>' . $list_tx[$i]["cardnum"] . '</td>
							<td>' . $list_tx[$i]["aarea_name"] . '&nbsp;&nbsp;' . $list_tx[$i]["barea_name"] . '</td>
							<td>' . $list_tx[$i]["all_money"] . '</td>
							<td>' . $list_tx[$i]["withdraw_fee"] . '</td>
							<td>' . $list_tx[$i]["money"] . '</td>
							<td>' . $list_tx[$i]["order_num"] . '</td>
							<td>' . date("Y-m-d H:i:s", $list_tx[$i]["add_time"]) . '</td>
							<td>' . $list_tx[$i]["status"] . '</td></tr>';
        }

        //统计
        $totaltx = M('bank')
            ->field('sum(money) as totalmoney')
            ->join("yang_withdraw ON yang_withdraw.bank_id = yang_bank.id")
            ->join("yang_areas as b ON b.area_id = yang_bank.address")
            ->join("yang_areas as a ON a.area_id = b.parent_id ")
            ->join("yang_member as c on yang_withdraw.uid=c.member_id")
            ->where($where)
            ->select();
        $e .= '<tr><td><span style="font-size:16px;color:#2F4F4F;">实际金额合计：</span></td><td>' . $totaltx[0]['totalmoney'] . '</td></tr>';

        //财务日志
        $e .= '|';
        $wherec['yang_finance.member_id'] = $uid_tx;
        $countc = M('Finance')
            ->field('yang_finance.*,yang_member.name as username,yang_currency.currency_name,yang_finance_type.name as typename')
            ->join('left join yang_member on yang_member.member_id=yang_finance.member_id')
            ->join('left join yang_finance_type on yang_finance_type.id=yang_finance.type')
            ->join('left join yang_currency on yang_currency.currency_id=yang_finance.currency_id')
            ->where($wherec)->count();
        $list = M('Finance')
            ->field('yang_finance.*,yang_member.name as username,yang_currency.currency_name,yang_finance_type.name as typename')
            ->join('left join yang_member on yang_member.member_id=yang_finance.member_id')
            ->join('left join yang_finance_type on yang_finance_type.id=yang_finance.type')
            ->join('left join yang_currency on yang_currency.currency_id=yang_finance.currency_id')
            ->where($wherec)
            ->order('add_time desc')
            ->select();
        foreach ($list as $k => $v) {
            if ($v['currency_id'] == 0) {
                $list[$k]['currency_name'] = $this->config['xnb_name'];
            }
            $list[$k]['moneytype'] = $v['money_type'] == 1 ? '收入' : '支出';
        }
        for ($i = 0; $i < $countc; $i++) {
            $e .= '<tr>
	                     		<td>' . $list[$i]["finance_id"] . '</td>
								<td>' . $list[$i]["username"] . '</td>
								<td>' . $list[$i]["typename"] . '</td>
								<td>' . $list[$i]["content"] . '</td>
								<td>' . $list[$i]["money"] . '</td>
								<td>' . $list[$i]["currency_name"] . '</td>
								<td>' . $list[$i]["moneytype"] . '</td>			
								<td>' . date("Y-m-d H:i:s", $list[$i]["add_time"]) . '</td>
								<td>' . $list[$i]["ip"] . '</td></tr>';
        }
        $this->ajaxReturn($e);
    }


    //钱包积分类型管理->提积分记录,涉及到yang_tibi、yang_currency、yang_member
    public function xiangDan_tb()
    {
        $option_valtb = I('option_valtb');
        $uid_tb = I('uid_tb');
        $where['yang_tibi.user_id'] = $uid_tb;
        $where['yang_tibi.status'] = 1;
        if ($option_valtb !== '') {
            if ($option_valtb != -1) {
                $where['yang_tibi.currency_id'] = $option_valtb;
            }
        }
        $count = M("Tibi")->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->count();
        $field = "yang_tibi.*,yang_member.email,yang_currency.currency_name";
        $list_tb = M("Tibi")->field($field)->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->select();
        foreach ($list_tb as $key => $v) {
            $list_tb[$key]['status'] = $v['status'] == 1 ? "通过" : "等待转出";
        }
        $curName_tb = M('Currency')->field('currency_id,currency_name')->select();
        if ($option_valtb == "") {
            $f .= '<select name="currency_id2" id="select_tb">
                 <option value="-1" selected>全部</option>
					<option value="0">人民币</option>';
        } else {
            if ($option_valtb == -1) {
                $f .= '<select name="currency_id2" id="select_tb">
                 <option value="-1" selected>全部</option>';
            } else {
                $f .= '<select name="currency_id2" id="select_tb">
                 <option value="-1">全部</option>';
            }
            if ($option_valtb == 0) {
                $f .= '<option value="0" selected>人民币</option>';
            } else {
                $f .= '<option value="0">人民币</option>';
            }
        }
        foreach ($curName_tb as $key => $cur) {
            if ($option_valtb == $cur["currency_id"]) {
                $f .= '<option value=' . $cur["currency_id"] . ' id="currencyId2" selected>' . $cur["currency_name"] . '</option>';
            } else {
                $f .= '<option value=' . $cur["currency_id"] . ' id="currencyId2">' . $cur["currency_name"] . '</option>';
            }
        }
        $f .= '</select>|';
        for ($i = 0; $i < $count; $i++) {
            $f .= '<tr><td>' . $list_tb[$i]["email"] . '</td>
                     		<td>' . $list_tb[$i]["currency_name"] . '</td>
							<td>' . $list_tb[$i]["url"] . '</td>
							<td>' . $list_tb[$i]["num"] . '</td>
							<td>' . $list_tb[$i]["actual"] . '</td>
							<td>' . date("Y-m-d H:i:s", $list_tb[$i]["add_time"]) . '</td>
							<td>' . $list_tb[$i]["status"] . '</td></tr>';
        }

        //统计
        $totaltb = M('Tibi')
            ->field('sum(actual) as totalcurrency')
            ->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")
            ->where($where)
            ->select();
        $f .= '<tr><td><span style="font-size:16px;color:#2F4F4F;">实际数量合计：</span></td><td>' . $totaltb[0]['totalcurrency'] . '</td></tr>';

        $this->ajaxReturn($f);
    }


    //钱包积分类型管理->充积分记录,涉及到yang_tibi、yang_currency、yang_member
    public function xiangDan_cb()
    {
        $option_valcb = I('option_valcb');
        $uid_cb = I('uid_cb');
        $where['yang_tibi.user_id'] = $uid_cb;
        $where['yang_tibi.status'] = 3;
        if ($option_valcb !== '') {
            if ($option_valcb != -1) {
                $where['yang_tibi.currency_id'] = $option_valcb;
            }
        }
        $count = M("Tibi")->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->count();
        $field = "yang_tibi.*,yang_member.email,yang_currency.currency_name";
        $list_cb = M("Tibi")->field($field)->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->select();
        foreach ($list_cb as $key => $v) {
            $list_cb[$key]['status'] = $v['status'] == 3 ? "通过" : "等待转出";
        }
        $curName_cb = M('Currency')->field('currency_id,currency_name')->select();
        if ($option_valcb == "") {
            $f .= '<select name="currency_id4" id="select_cb">
                 <option value="-1" selected>全部</option>
					<option value="0">人民币</option>';
        } else {
            if ($option_valcb == -1) {
                $f .= '<select name="currency_id4" id="select_cb">
                 <option value="-1" selected>全部</option>';
            } else {
                $f .= '<select name="currency_id4" id="select_cb">
                 <option value="-1">全部</option>';
            }
            if ($option_valcb == 0) {
                $f .= '<option value="0" selected>人民币</option>';
            } else {
                $f .= '<option value="0">人民币</option>';
            }
        }
        foreach ($curName_cb as $key => $cur) {
            if ($option_valcb == $cur["currency_id"]) {
                $f .= '<option value=' . $cur["currency_id"] . ' id="currencyId4" selected>' . $cur["currency_name"] . '</option>';
            } else {
                $f .= '<option value=' . $cur["currency_id"] . ' id="currencyId4">' . $cur["currency_name"] . '</option>';
            }
        }
        $f .= '</select>|';
        for ($i = 0; $i < $count; $i++) {
            $f .= '<tr><td>' . $list_cb[$i]["email"] . '</td>
                     		<td>' . $list_cb[$i]["currency_name"] . '</td>
							<td>' . $list_cb[$i]["url"] . '</td>
							<td>' . $list_cb[$i]["num"] . '</td>
							<td>' . $list_cb[$i]["actual"] . '</td>
							<td>' . date("Y-m-d H:i:s", $list_cb[$i]["add_time"]) . '</td>
							<td>' . $list_cb[$i]["status"] . '</td></tr>';
        }

        //统计
        $totalcb = M('Tibi')
            ->field('sum(num) as totalcharging')
            ->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")
            ->where($where)
            ->select();
        $f .= '<tr><td><span style="font-size:16px;color:#2F4F4F;">实际数量合计：</span></td><td>' . $totalcb[0]['totalcharging'] . '</td></tr>';

        $this->ajaxReturn($f);
    }

    //excel导出
    public function excel_index()
    {
        $withdraw = M('Withdraw');
        $bank = M('bank');
        $name = I('cardname');
        $names = I('keyname');
        $keyid = I('keyid');
        $keynum = I('keynum');
        $datePicker = strtotime(I('datePicker'));
        $datePicker2 = strtotime(I('datePicker2'));
        //ajax中的提交值
        $currencyId = I('currency_id');
        // I('status')--分页下标生成参数
        if (I('pend') != 0 || I('status') != 0) {
            $where ['yang_withdraw.status'] = I('status') ? I('status') : I('pend');
        }
        if (!empty($name) || !empty($names)) {
            // 如果传回的是post（keyname）就用post，否则用get（cardname）
            $cardname = I('keyname') ? I('keyname') : I('cardname');
            //模糊
            $where ['yang_bank.cardname'] = array('like', '%' . $cardname . '%');
        }
        if (!empty($keyid)) {
            $where ['yang_Withdraw.uid'] = array('like', '%' . $keyid . '%');
        }
        if (!empty($keynum)) {
            $where ['email'] = array('like', '%' . $keynum . '%');
        }
        if (!empty($datePicker) && !empty($datePicker2)) {
            $where['yang_withdraw.add_time'] = array('between', array($datePicker, $datePicker2));
        }
        // 查询满足要求的总记录数
        $count = $withdraw->join("yang_bank ON yang_withdraw.bank_id = yang_bank.id")->join("yang_member on yang_withdraw.uid=yang_member.member_id")->where($where)->count();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count, 20);
        //将分页（点击下一页）需要的条件保存住，带在分页中
        $Page->parameter = array(
            'yang_withdraw.status' => $where ['yang_withdraw.status'],
            'yang_bank.cardname' => $cardname,
            'yang_Withdraw.uid' => $keyid,
            'yang_member.email' => $keynum
        );
        // 分页显示输出
        $show = $Page->show();
        //需要的数据
        $field = "yang_withdraw.*,yang_bank.cardname,yang_bank.cardnum,yang_bank.bankname,b.area_name as barea_name,a.area_name as aarea_name,c.email";
        $info = $withdraw->field($field)
            ->join("left join yang_bank ON yang_withdraw.bank_id = yang_bank.id")
            ->join("left join yang_areas as b ON b.area_id = yang_bank.address")
            ->join("left join yang_areas as a ON a.area_id = b.parent_id ")
            ->join("left join yang_member as c on yang_withdraw.uid=c.member_id")
            ->where($where)
            ->order('yang_withdraw.add_time desc')
            //->limit ( $Page->firstRow . ',' . $Page->listRows )
            ->select();


        foreach ($info as $k => $v) {

            $info[$k]['add_time'] = date('Y-m-d H:i:s', $v['add_time']);

            if ($v['status'] == 1) {

                $info[$k]['status'] = '未通过';
            } elseif ($v['status'] == 2) {
                $info[$k]['status'] = '通过';

            } elseif ($v['status'] == 0) {

                $info[$k]['status'] = '已撤销';
            } else {

                $info[$k]['status'] = '审核中';
            }
        }
        $this->assign('info', $info); // 赋值数据集
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('inquire', $cardname);


        $xlsName = "User";
        $xlsCell = array(
            array('withdraw_id', 'ID'),

            array('cardname', '提现人'),
            array('uid', '会员ID'),
            array('bankname', '银行'),
            array('cardnum', '银行账号'),
            array('aarea_name', '银行开户地'),
            array('all_money', '提现金额'),
            array('withdraw_fee', '手续费'),
            array('money', '实际金额'),


            array('order_num', '订单号'),
            array('add_time', '提交时间'),

            array('status', '状态')
        );
        // $xlsModel = M('Post');
        $xlsData = $info;
        $this->exportExcel($xlsName, $xlsCell, $xlsData);


        $this->display();
    }
}

?>