<?php

namespace Admin\Controller;


class KOKManageController extends AdminController
{
    /**
     * 参数配置
     */
    public function config()
    {
        if(IS_POST){
            $config = M('config');
            $config->startTrans();
            foreach ($_POST as $k=>$v){
                $res =$config->where("yang_config.key='{$k}'")->setField('value',$v);
                if($res === false){
                    $config->rollback();
                    $this->error('配置修改失败');exit;
                }
            }

            $config->commit();
            $this->success('配置修改成功');
        }else{
            $this->display();
        }
    }

    /**
     * 私募充值
     */
    public function recharge()
    {
        if(IS_POST){
            if(isset($this->config['is_kok_private']) && $this->config['is_kok_private']!=1){
                $this->error('私募期已关闭，不再允许私募充值');
            }
            $member_id = I('post.member_id','','intval');
            $phone= I('post.phone');
            $cid = I('post.currency_id','','intval');
            $lock_rate = I('post.lock_rate',0,'intval');
            $money = I('post.money',0);
            $message = I('post.message','');
            if (empty($member_id)&&empty($phone)) {
                $this->error('请输入充值用户ID或者手机号');
            }
            if (!empty($member_id)&&!empty($phone)) {
                $this->error('用户ID和手机号只填其中一个就行了');
            }
            if (empty($cid)) {
                $this->error('请输入积分类型');
            }
            if (empty($money)) {
                $this->error('请输入充值金额');
            }
            if ($money<0) {
                $this->error('充值金额不能为负数');
            }
            if (empty($message)) {
                $this->error('请输入充值备注');
            }
            if(!empty($member_id)){
                $member=M('Member')->field("member_id")->where("member_id = {$member_id}")->find();
                if (!$member) {
                    $this->error('用户不存在');
                }
            }
            if(!empty($phone)){
                $member=M('Member')->field("member_id")->where("phone = {$phone}")->find();
                if (!$member) {
                    $this->error('用户不存在');
                }else{
                    $member_id=$member['member_id'];
                }
            }


            $currencyUser=M("Currency_user")->where(['member_id'=>$member_id,'currency_id'=>$cid])->find();
            if(empty($currencyUser)){
                $cUser['member_id']=$member_id;
                $cUser['currency_id']=$cid;
               $cu_id= M("Currency_user")->add($cUser);
               if(!$cu_id){
                   $this->error('创建用户资产失败');
               }
            }
            M()->startTrans();//开启事务
            $data['message']=$message.' (私募充值)';
            $data['admin_id'] = $_SESSION['admin_userid'];
            $data['member_id'] = $member_id;
            $data['currency_id'] = $cid;
            $data['money'] = $money;
            $data['status'] = 1;
            $data['add_time'] = time();
            $data['type'] = 5;//管理员私募充值
            $lock_num = $data['money']*$lock_rate/100;
            $exchange_num = $data['money']-$lock_num;
            $update_data = [
                'lock_num' => ['exp', 'lock_num+' . $lock_num],
                'exchange_num' => ['exp', 'exchange_num+' . $exchange_num],

            ];

            $data['rate']=$lock_rate;
            $data['lock_money']=$lock_num;
            $data['exchange_money']=$exchange_num;
            $pid=M('pay')->add($data);
            //添加财务日志
            $addLockStream = D('CurrencyUserStream')->addStream($member_id, $cid,3, $lock_num, 1, 1, $pid, '后台充值');
            if($addLockStream){
                if($exchange_num>0){
                    $addExchangeStream = D('CurrencyUserStream')->addStream($member_id, $cid,4, $exchange_num, 1, 1, $pid, '后台充值');
                    if(!$addExchangeStream){
                        M()->rollback();
                        $this->error('添加失败');
                    }
                }
            }else{
                M()->rollback();
                $this->error('添加失败');
            }

            $r[] = $pid;
            $r[] = M('Currency_user')->where(array('member_id' => $data['member_id'], array('currency_id' => $data['currency_id'])))->save($update_data);
            $r[] = $this->addFinance($data['member_id'], 25, "私募充值", $data['money'], 1, $data['currency_id']);
            $r[] = $this->addMessage_all($data['member_id'], -2, "私募充值", "管理员私募充值" . getCurrencynameByCurrency($data['currency_id']) . ":" . $data['money']);
            if (!in_array(false, $r)) {
                M()->commit();
                $this->success('添加成功');

            } else {
                M()->rollback();
                $this->error('添加失败');
            }
        }else{
            $get['phone']=I("phone");
            $get['member_id']=I("member_id");
            $get['starttime']=I("starttime");
            $get['endtime']=I("endtime");
            if(!empty($get['phone'])){
                $where['m.phone']=$get['phone'];
            }
            if(!empty($get['member_id'])){
                $where['p.member_id']=$get['member_id'];
            }

            $starttime=$get['starttime'];
            $endtime=$get['endtime'];
            if (!empty($starttime)) {
                if (empty($endtime)) {
                    $endtime = date("Y-m-d", time());
                }
                $where['p.add_time'] = array('between', array(strtotime($starttime), strtotime($endtime) + 86400));
            }
            $where['type']=5;
            $join='left join yang_member m on m.member_id=p.member_id';
            $join1='left join yang_admin on yang_admin.admin_id=p.admin_id';
            $count = M('pay')->alias("p")->where($where)->join($join)->join($join1)->count();// 查询满足要求的总记录数
            if(I("daochu")==1){
                $list = M('pay')->alias("p")
                    ->join($join)
                    ->join($join1)
                    ->where($where)->order('add_time desc')->select();
                if(!empty($list)){
                    $cuList=M("currency")->select();
                    $newList = array_column($cuList, NULL, 'currency_id');
                    foreach ($list as &$value){
                        $value['currency_id']=$newList[$value['currency_id']]['currency_name'];
                        $value['add_time']=date("Y-m-d H:i:s",$value['add_time']);
                    }
                }

                $xlsCell = array(
                    array('member_id', '充值用户ID'),
                    array('name', '用户名'),
                    array('phone', '手机'),
                    array('currency_id', '充值积分类型'),
                    array('money', '充值金额'),
                    array('rate', '锁仓比率'),
                    array('lock_money', '锁仓金额'),
                    array('exchange_money', '可互转金额'),
                    array('add_time', '时间'),
                    array('message', '备注'),
                    array('username', '管理员'),
                );
                $this->exportExcel("私募充值记录", $xlsCell, $list);
                die();
            }


            $Page = new \Think\Page($count, 20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
            $show = $Page->show();// 分页显示输出
            $list = M('pay')->alias("p")
                ->join($join)
                ->join($join1)
                ->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->order('add_time desc')->select();
            $this->assign('page', $show);

            $this->assign('list', $list);
            $rateList = [100,90,80,70,60,50,40,30,20,10];
            $rateList = array_unique(array_merge([$this->config['kok_private_recharge_lock_rate']],$rateList)); //配置放第一个
            $currencyList=M("currency")->where(['currency_mark'=>"KOk"])->select();
            $this->assign('rateList',$rateList);
            $this->assign('currencyList',$currencyList);
            $this->assign('get',$get);
            $this->display();
        }
    }


    /**
     * 赠送充值
     */
    public function recharge_award()
    {
        if(IS_POST){
            $member_id = I('post.member_id','','intval');
            $phone= I('post.phone');
            $cid = I('post.currency_id','','intval');
            $money = I('post.money',0);
            $message = I('post.message','');
            if (empty($member_id)&&empty($phone)) $this->error('请输入充值用户ID或者手机号');
            if (!empty($member_id)&&!empty($phone)) $this->error('用户ID和手机号只填其中一个就行了');
            if (empty($cid)) $this->error('请输入积分类型');
            if (empty($money)) $this->error('请输入充值金额');
            if ($money<0) $this->error('充值金额不能为负数');
            if (empty($message)) $this->error('请输入充值备注');
            if(!empty($member_id)){
                $member=M('Member')->field("member_id")->where("member_id = {$member_id}")->find();
                if (!$member) {
                    $this->error('用户不存在');
                }
            }
            if(!empty($phone)){
                $member=M('Member')->field("member_id")->where("phone = {$phone}")->find();
                if (!$member) {
                    $this->error('用户不存在');
                }else{
                    $member_id=$member['member_id'];
                }
            }

            $currencyUser=M("Currency_user")->where(['member_id'=>$member_id,'currency_id'=>$cid])->find();
            if(empty($currencyUser)){
                $cUser['member_id']=$member_id;
                $cUser['currency_id']=$cid;
                $cu_id= M("Currency_user")->add($cUser);
                if(!$cu_id) $this->error('创建用户资产失败');
            }

            M()->startTrans();//开启事务
            $data['message']=$message.' (赠送充值)';
            $data['admin_id'] = $_SESSION['admin_userid'];
            $data['member_id'] = $member_id;
            $data['currency_id'] = $cid;
            $data['money'] = $money;
            $data['status'] = 1;
            $data['add_time'] = time();
            $data['type'] = 6;//管理员赠送充值

            $update_data = [
                'num_award' => ['exp', 'num_award+' . $data['money']],
                'sum_award' => ['exp', 'sum_award+' . $data['money']],
            ];
            $pid=M('pay')->add($data);
            //添加财务日志
            $addLockStream = D('CurrencyUserStream')->addStream($member_id, $cid,6, $data['money'], 1, 1, $pid, '后台赠送充值');
            if(!$addLockStream){
                M()->rollback();
                $this->error('添加失败');
            }

            $r[] = $pid;
            $r[] = M('Currency_user')->where(array('member_id' => $data['member_id'], array('currency_id' => $data['currency_id'])))->save($update_data);
            $r[] = $this->addFinance($data['member_id'], 26, "赠送充值", $data['money'], 1, $data['currency_id']);
            $r[] = $this->addMessage_all($data['member_id'], -2, "赠送充值", "管理员赠送充值" . getCurrencynameByCurrency($data['currency_id']) . ":" . $data['money']);
            if (!in_array(false, $r)) {
                M()->commit();
                $this->success('添加成功');

            } else {
                M()->rollback();
                $this->error('添加失败');
            }
        }else{
            $get['phone']=I("phone");
            $get['member_id']=I("member_id");
            $get['starttime']=I("starttime");
            $get['endtime']=I("endtime");
            if(!empty($get['phone'])){
                $where['m.phone']=$get['phone'];
            }
            if(!empty($get['member_id'])){
                $where['p.member_id']=$get['member_id'];
            }

            $starttime=$get['starttime'];
            $endtime=$get['endtime'];
            if (!empty($starttime)) {
                if (empty($endtime)) {
                    $endtime = date("Y-m-d", time());
                }
                $where['p.add_time'] = array('between', array(strtotime($starttime), strtotime($endtime) + 86400));
            }
            $where['type']=6;
            $join='left join yang_member m on m.member_id=p.member_id';
            $join1='left join yang_admin on yang_admin.admin_id=p.admin_id';
            $count = M('pay')->alias("p")->where($where)->join($join)->join($join1)->count();// 查询满足要求的总记录数
            if(I("daochu")==1){
                $list = M('pay')->alias("p")
                    ->join($join)
                    ->join($join1)
                    ->where($where)->order('add_time desc')->select();
                if(!empty($list)){
                    $cuList=M("currency")->select();
                    $newList = array_column($cuList, NULL, 'currency_id');
                    foreach ($list as &$value){
                        $value['currency_id']=$newList[$value['currency_id']]['currency_name'];
                        $value['add_time']=date("Y-m-d H:i:s",$value['add_time']);
                    }
                }

                $xlsCell = array(
                    array('member_id', '充值用户ID'),
                    array('name', '用户名'),
                    array('phone', '手机'),
                    array('currency_id', '充值积分类型'),
                    array('money', '充值金额'),
                    array('add_time', '时间'),
                    array('message', '备注'),
                    array('username', '管理员'),
                );
                $this->exportExcel("私募充值记录", $xlsCell, $list);
                die();
            }


            $Page = new \Think\Page($count, 20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
            $show = $Page->show();// 分页显示输出
            $list = M('pay')->alias("p")
                ->join($join)
                ->join($join1)
                ->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->order('add_time desc')->select();
            $this->assign('page', $show);

            $this->assign('list', $list);
            $currencyList=M("currency")->where(['currency_mark'=>"KOK"])->select();
            $this->assign('currencyList',$currencyList);
            $this->assign('get',$get);
            $this->display();
        }
    }

    //2018.12活动
    public function activity_12() {
        $model = M('reg_task_12');

        $where = [];

        $member_id = intval(I('member_id',0));
        if(!empty($member_id)) $where['a.member_id'] = $member_id;

        $name = I('name');
        if(!empty($name)) $where['c.name'] = $name;

        $phone = I('phone');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['c.email'] = $phone;
            } else {
                $where['c.phone'] = $phone;
            }
        }

        $starttime=I('starttime');
        $endtime=I('endtime');
        if (!empty($starttime)) {
            if (empty($endtime)) $endtime = date("Y-m-d", time());
            $where['a.add_time'] = array('between', array(strtotime($starttime), strtotime($endtime) + 86400));
        }

        $count = $model->alias('a')->where($where)->join('left join __MEMBER__ c on a.member_id=c.member_id')->count();
        $Page = new \Think\Page($count, 20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        setPageParameter($Page, array('member_id'=>$member_id,'name'=>$name,'phone'=>$phone,'starttime'=>$starttime,'endtime'=>$endtime));
        $show = $Page->show();// 分页显示输出
        $list = $model->alias('a')->field('a.*,c.phone,c.email,c.name')->join('left join __MEMBER__ c on a.member_id=c.member_id')->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->order('a.add_time desc,total desc')->select();
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->display();
    }

    /**
     * 全部锁仓
     */
    public function lockAll()
    {
//        if(IS_POST){
//            if(isset($this->config['is_kok_private']) && $this->config['is_kok_private']==1){
//                $this->error('请先关闭私募期');
//            }
//            if(isset($this->config['is_kok_private']) && $this->config['is_kok_private']>1){
//                $this->error('已锁仓，请勿重复操作');
//            }
//            $currency = M('currency_user');
//            $currency->startTrans();
//            try{
//                $sql = 'UPDATE yang_currency_user SET lock_num = lock_num  + exchange_num, exchange_num = 0 WHERE currency_id = 9';
//                $res = $currency->execute($sql);
//                $upConfig = M('config')->where(['key'=>'is_kok_private'])->setField('value',2);
//                if($res!==false && $upConfig!==false){
//                    $currency->commit();
//                    $this->success('锁仓成功');
//                }else{
//                    $currency->rollback();
//                    $this->error('锁仓失败');
//                }
//            }catch(Exception $e){
//                $currency->rollback();
//                $this->error($e->getMessage());
//            }
//        }
    }

    public function getNameByid(){
        $r['code']=ERROR1;
        $r['result']=[];
        $get_name=M("Member")->field("name,phone")->where("member_id = ".intval(I('post.id')))->find();
        if(!empty($get_name)){
            $r['code']=SUCCESS;
            $r['result']=$get_name;
        }
        $this->ajaxReturn($r);
    }
    public function getNameByphone(){
        $r['code']=ERROR1;
        $r['result']=[];
        $get_name=M("Member")->field("name,phone")->where("phone = ".strval(I('post.phone')))->find();
        if(!empty($get_name)){
            $r['code']=SUCCESS;
            $r['result']=$get_name;
        }
        $this->ajaxReturn($r);
    }
}