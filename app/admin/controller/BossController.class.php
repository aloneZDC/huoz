<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2019/1/8
 * Time: 18:11
 */

namespace Admin\Controller;


class BossController extends AdminController
{
    //空操作
    public function _empty(){
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }


    /**
     * 老板计划晋升条件列表
     * Created by Red.
     * Date: 2019/1/9 16:39
     */
    function updateConfigList(){
        $map['key']  = array('exp'," IN ('V1','V2','V3','V4','V5','V6') ");
        $config=M("boss_config")->where($map)->select();
        if(!empty($config)){
            foreach ($config as &$value){
                $value['value']=unserialize($value['value']);
            }
            $this->assign("list",$config);
            $this->display();
        }else{
            $this->error("参数没配置");
        }
    }

    /**
     * 修改晋升等级条件
     * Created by Red.
     * Date: 2019/1/11 19:07
     */
    function updateConfig(){
        $key=I("key");
        $keys=I("post.keys");
        $recommend=I("post.recommend");
        $money=I("post.money");
        $culture=I("post.culture");
        if(!empty($keys)&&$recommend>=0){
            $value['recommend']=$recommend;
            if(!empty($money))$value['money']=$money;
            if(!empty($culture))$value['culture']=$culture;
            $vv=serialize($value);
           $update= M("boss_config")->where(['key'=>$keys])->save(['value'=>$vv]);
           if($update){
              return $this->success("修改成功");
           }else{
              return $this->error("没有任何修改");
           }
        }
        if(!empty($key)){
            $config=M("boss_config")->where(['key'=>$key])->find();
            if(!empty($config)){
                $config['value']=unserialize($config['value']);
                $this->assign("keys",$config);
                $this->assign("list",['V1','V2','V3','V4','V5']);
                $this->display();
            }else{
               return $this->error("参数错误");
            }
        }else{
           return $this->error("参数错误");
        }
    }

    public function bossPlan(){
        $where=[];

        $phone=I('phone');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['m.email'] = $phone;
            } else {
                $where['m.phone'] = $phone;
            }
        }
        $member_id=I('member_id','');
        if(I('member_id')){
            $where['a.member_id']=$member_id;
        }

        $status = I('status','');
        if($status!='') {
            $status = intval($status);
            $where['b.status'] = $status;
        }
        $level = I('level','');
        if(is_numeric($level)){
            $level = intval($level);
            $where['a.level'] = $level;
        }
        $votes = I('votes','');
        if(is_numeric($votes)){
            $where['a.votes'] = $votes;
        }
        $pid = I('pid','');
        if(is_numeric($pid)){
            $where['a.pid'] = $pid;
        }
        $model_plan=M("boss_plan_info");
        $total_field='count(a.member_id) as people_num,
              sum(num) as num,
              sum(xrpz_num) as xrpz_num,
              sum(xrpj_num) as xrpj_num,
              sum(total_team_num) as total_team_num,
              sum(xrpz_new_num) as xrpz_new_num ';
        $count = $model_plan->field($total_field)->alias("a")
            ->join("left join " . C("DB_PREFIX") . "boss_plan as b on b.member_id = a.member_id")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.member_id")
            ->where($where)
            ->find();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count['people_num'], 10);
        //给分页传参数
        setPageParameter($Page, array('name' => ""));
        //分页显示输出性
        $show = $Page->show();
        $field="a.*,b.status,b.create_time,b.activate_time,m.email,m.phone,b.lock_status";
        $list = $model_plan->alias("a")
            ->join("left join " . C("DB_PREFIX") . "boss_plan as b on b.member_id = a.member_id")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.member_id")
            ->field($field)
            ->where($where)
            ->order("b.create_time desc")
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($list as $key=>$val){
          //  $list[$key]['email']=empty($val['email'])?"无":$val['email'];
            $list[$key]['phone']=empty($val['phone'])?empty($val['email'])?"无":$val['email']:$val['phone'];
            $list[$key]['num']=strval(floatval($val['num']));
            $list[$key]['xrpz_num']=$val['xrpz_num'];
            $list[$key]['lock_status']=str_replace(array(1,2),array('未锁定','已锁定'),$val['lock_status']);
            $list[$key]['lock_state']=$val['lock_status']==1?2:1;
            $list[$key]['xrpz_forzen']=$val['xrpz_forzen'];
            $list[$key]['xrpj_num']=$val['xrpj_num'];
            $list[$key]['xrpj_forzen']=$val['xrpj_forzen'];
            $list[$key]['xrpz_new_num']=$val['xrpz_new_num'];
            $list[$key]['total_team_num']=$val['total_team_num'];
            $list[$key]['create_time']=$val['create_time']>0?date('Y-m-d H:i:s',$val['create_time']):"无";
            $list[$key]['activate_time']=$val['activate_time']>0?date('Y-m-d H:i:s',$val['activate_time']):"无";
            $list[$key]['upgrade_time']=$val['upgrade_time']>0?date('Y-m-d H:i:s',$val['upgrade_time']):"无";
            $list[$key]['is_admin']=$val['is_admin']>0?"<span style='color: red'>（后台添加）</span>":"";
            $list[$key]['status']=str_replace(array("1","3"),array("待审核","审核通过"),$val['status']);//1 待审核  2 待确定 3 已确定

        }

          $total_arr['people_num']=$count['people_num']>0?$count['people_num']:0;
          $total_arr['num']=$count['num']>0?$count['num']:'0.00000';
          $total_arr['xrpz_num']=$count['xrpz_num']>0?$count['xrpz_num']:'0.00000';
          $total_arr['xrpj_num']=$count['xrpj_num']>0?$count['xrpj_num']:'0.00000';
          $total_arr['total_team_num']=$count['total_team_num']>0?$count['total_team_num']:'0.00000';
          $total_arr['xrpz_new_num']=$count['xrpz_new_num']>0?$count['xrpz_new_num']:'0.00000';
          $where['votes'] = $votes;
          $where['level']=$level;
          $where['status']=$status;
          $where['phone']=$phone;
          $where['member_id']=$member_id;
          $where['pid'] = $pid;
        $this->assign('total_arr', $total_arr);
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->assign('where', $where);
        $this->display();
    }
    //锁定用户
    public function user_lock(){

        if (IS_POST) {
            $model_plan=M("boss_plan");
            $member_id = I("post.id");
            $lock_state = I("post.type", 1, 'intval');
            $info =  $model_plan->where(['member_id' => $member_id])->save(['lock_status'=>$lock_state]);
            if($info){
                $this->ajaxReturn(['Code' =>1, 'Msg' =>"操作成功"]);
            }else{
                $this->ajaxReturn(['Code' => 0, 'Msg' => "操作失败"]);
            }

        }
    }
    //老板计划用户收益
    public function incomeCount(){
        $where=[];
        $week_where=[];
        $phone=I('phone');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['m.email'] = $phone;
                $week_where['m.email']=$phone;
            } else {
                $where['m.phone'] = $phone;
                $week_where['m.phone']=$phone;
            }
        }
        $member_id=I('member_id','');
        if(I('member_id')){
            $where['a.member_id']=$member_id;
            $week_where['a.member_id']=$member_id;
        }
        $level=I('level','');
        if(I('level')){
            $where['b.level']=$level;
        }
        $order = I('order');
        if(!empty($order) && in_array($order,['a.num1','a.num2','a.num3','a.num5','a.num6','a.num7','a.num8','a.num10','a.total_profit','move_bonus','quiet_bonus','b.num'])) {
            $order = $order;
        } else {
            $order = 'a.total_profit';
        }
      //  $order_type=I('order_type','');
//        if($order_type==1){
//            $orderby="quiet_bonus desc";
//        }elseif($order_type==2){
//            $orderby=" move_bonus desc";
//        }elseif($order_type==3){
//            $orderby=" total_profit desc";
//        }
        $order_by = I('order_by');
        if(empty($order_by) || $order_by=='desc') {
            $order_by = 'desc';
        } else {
            $order_by = 'asc';
        }

        $model_plan=M("boss_plan_count");
        //根据分类或模糊查找数据数量
        $count = $model_plan->field('count(a.member_id) as num')->alias("a")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.member_id")
            ->join("left join " . C("DB_PREFIX") . "boss_plan_info as b on b.member_id = a.member_id")
            ->where($where)
            ->find();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count['num'], 10);
        //给分页传参数
        setPageParameter($Page, array('phone' => $phone,'level'=>$level,'member_id'=>$member_id,'order'=>$order,'order_by'=>$order_by));
        //分页显示输出性
        $show = $Page->show();
        $field="a.*,(a.num5+a.num6+a.num7+a.num8+a.num10) as move_bonus,(a.num1+a.num2+a.num3) as quiet_bonus,m.email,m.phone,b.level,b.upgrade_time,b.push_num,b.num";
        $list = $model_plan->alias("a")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.member_id")
            ->join("left join " . C("DB_PREFIX") . "boss_plan_info as b on b.member_id = a.member_id")
            ->field($field)
            ->where($where)
            ->limit($Page->firstRow . ',' . $Page->listRows)->order($order.' '.$order_by)->select();
        $model_bouns_week=M('boss_bouns_week');
        foreach ($list as $key =>$val){
            $week1=$model_bouns_week->field('sum(num) as week_num')->where(['member_id'=>$val['member_id']])->find();
            $week2=$model_bouns_week->field('num as week_num')->where(['member_id'=>$val['member_id']])->order('add_time desc')->find();
            if(empty($val['phone'])){
                $list[$key]['phone']=$val['email'];
            }
            $list[$key]['total_week']=$week1['week_num'];
            $list[$key]['upgrade_time']=empty($val['upgrade_time'])?'无':date("Y-m-d H:i:s",$val['upgrade_time']);
            $list[$key]['week']=$week2['week_num'];
            $list[$key]['move_bonus']=$val['num6']+$val['num7']+$val['num8'];
            $list[$key]['quiet_bonus']=$val['num1']+$val['num2']+$val['num3']+$val['num5'];

        }

        $profit_field='sum(a.num1) as num1,
        sum(a.num2) as num2,
        sum(a.num3) as num3,
        sum(a.num4) as num4,
        sum(a.num5) as num5,
        sum(a.num6) as num6,
        sum(a.num7) as num7,
        sum(a.num8) as num8,
        sum(a.num10) as num10,
        sum(a.total_profit) as total_profit';
        $profit=$model_plan->alias("a")
            ->field($profit_field)
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.member_id")
            ->join("left join " . C("DB_PREFIX") . "boss_plan_info as b on b.member_id = a.member_id")
            ->where($where)
            ->find();
        $total_num=$model_bouns_week->alias("a")
            ->field('sum(a.num) as num')
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.member_id")
            ->where($week_where)
            ->find();
        $total_arr['people_num']=$count['num'];
        $total_arr['week_num']=$total_num['num']>0?$total_num['num']:'0.000000';
        $total_arr['profit_num1']=$profit['total_profit']>0?$profit['total_profit']:'0.000000';
        $total_arr['profit_num2']=($profit['num1']+$profit['num2']+$profit['num']+$profit['num5'])>0?$profit['num1']+$profit['num2']+$profit['num']:'0.000000';
        $total_arr['profit_num3']=($profit['num5']+$profit['num6']+$profit['num7']+$profit['num8']+$profit['num10'])>0?$profit['num5']+$profit['num6']+$profit['num7']+$profit['num8']+$profit['num10']:'0.000000';
        $where['phone']=$phone;
        $where['level']=$level;
        $where['member_id']=$member_id;
        $where['order'] = $order;
        $where['order_by'] = $order_by;
        $this->assign('total_arr', $total_arr);
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->assign('where', $where);

        $this->display();
    }
     //收益日志
    public function incomeLog(){
        $where=[];
        $phone=I('phone');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['m.email'] = $phone;
            } else {
                $where['m.phone'] = $phone;
            }
        }
        $member_id=I('member_id','');
        if(I('member_id')){
            $where['a.member_id']=$member_id;
        }

        $status = I('status','');
        if($status!='') {
            $status = intval($status);
            if($status==1){
                $where['a.receive_status'] = 0;
            }elseif($status==2){
                $where['a.receive_status'] = 1;
            }

        }
        $type = I('type','');
        if($type!='') {
            $type = intval($type);
            if($type==6){
                $where['a.type'] = array('in',[6,9]);
            }else{
                $where['a.type'] = $type;
            }

        }
        $start_time = I('start_time','');
        $end_time = I('end_time','');
        if(!empty($start_time)&&!empty($end_time)){
            if(strtotime($start_time) <=strtotime($end_time)){
                $where['a.add_time']=array(array('egt',strtotime($start_time)),array('lt',strtotime($end_time)+86400));
            }elseif(strtotime($start_time) >strtotime($end_time)){
                $where['a.add_time']=array(array('egt',strtotime($start_time)),array('lt',strtotime($start_time)+86400));
            }
        }else{
            if(!empty($start_time)){
                $where['a.add_time']=array('egt',strtotime($start_time));
            }elseif (!empty($end_time)){
                $where['a.add_time']=array('egt',strtotime($end_time));
            }
        }
        $order = I('order');
        if(!empty($order) && in_array($order,['a.child_num','a.in_num','a.num','a.limit_num'])) {
            $order = $order;
        } else {
            $order = 'a.add_time';
        }

        $order_by = I('order_by');
        if(empty($order_by) || $order_by=='desc') {
            $order_by = 'desc';
        } else {
            $order_by = 'asc';
        }

        $model_plan=M("boss_bouns_log");
        //根据分类或模糊查找数据数量
        $count = $model_plan->field('count(a.member_id) as num,sum(a.num) as bonus_num')->alias("a")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.member_id")
            ->where($where)
            ->find();

       // echo $model_plan->getLastSql();die;
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count['num'], 10);
        //给分页传参数

        setPageParameter($Page, array('order'=>$order,'order_by'=>$order_by,'start_time'=>$start_time,'end_time'=>$end_time,'type'=>$type,'member_id'=>$member_id,'phone'=>$phone,'status'=>$status));
        //分页显示输出性
        $show = $Page->show();
        $field="a.*,m.email,m.phone";
        $list = $model_plan->alias("a")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.member_id")
            ->field($field)
            ->where($where)
            ->order($order.' '.$order_by)
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $where['a.add_time']=array('egt',strtotime(date('Y-m-d')));
        $child_num=$model_plan->alias("a")->field('sum(num) as num')->where($where)->find();
        foreach ($list as $key=>$val){
            if(empty($val['phone'])){
                $list[$key]['phone']= $val['email'];
            }
            $list[$key]['num']=strval(floatval($val['num']));
            $list[$key]['profit']=$val['profit'];
            $list[$key]['add_time_txt']=$val['add_time']>0?date('Y-m-d H:i:s',$val['add_time']):"无";
           // '1 基础分红 2 增加分红 3 一级分红 4 幸运赠送 5推荐奖励 6社区奖励 7平级奖励 8管理奖励 9全球加权平分'
            $list[$key]['type_txt']=str_replace(array('1','2','3','4','5','6','7','8','9'),array('基础分红','增加分红','一级分红','幸运赠送','推荐奖励','社区奖励','平级奖励','管理奖励','全球加权平分'),$val['type']);
            $list[$key]['type_status']=str_replace(array('0','1','2'),array('无','静态','动态'),$val['type_status']);
            $list[$key]['receive_status']=str_replace(array('0','1'),array('未领取','已领取'),$val['receive_status']);
            $list[$key]['receive_time']=$val['receive_time']>0?date('Y-m-d H:i:s',$val['receive_time']):"无";

        }

        unset($where);
        $where['status']=$status;
        $where['phone']=$phone;
        $where['type']=$type;
        $where['member_id']=$member_id;
        $where['start_time']=$start_time;
        $where['end_time']=$end_time;
        $where['order'] = $order;
        $where['order_by'] = $order_by;
        $total_arr['num']=$count['num']>0?$count['num']:0;
        $total_arr['bonus_num']=$count['bonus_num']>0?$count['bonus_num']:'0.000000';
        $this->assign('total_arr', $total_arr);
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->assign('where', $where);
        $this->display();
    }
    //瑞波钻日志
    public function xrpLog(){
        $where=[];
        $phone=I('phone');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['m.email'] = $phone;
            } else {
                $where['m.phone'] = $phone;
            }
        }
        $member_id=I('member_id','');
        if(I('member_id')){
            $where['a.l_member_id']=$member_id;
            $gold_where['member_id']=$member_id;
            $bonus_where['member_id']=$member_id;
            $mutual_bonus_where['l_member_id']=$member_id;
            $book_where['a.member_id']=$member_id;

        }

        $status = I('status','1');
        if($status!='') {
            $status = intval($status);
            $book_where['a.number_type']=$status;
            if($status ==1){
                $where['a.l_value'] = array('gt',0);
            }elseif($status ==2){
                $where['a.l_value'] = array('lt',0);
            }
        }
        $type = I('type','');
        if($type!='') {
            $type = intval($type);
            $where['a.l_type'] = $type;


        }
        $info=M('currency')->field('currency_id')->where(['currency_name'=>'XRP'])->find();
        $book_where['a.currency_id']=$info['currency_id'];
        $book_where['a.type']=18;

        $start_time = I('start_time','');
        $end_time = I('end_time','');
        if(!empty($start_time)&&!empty($end_time)){
            if(strtotime($start_time) <=strtotime($end_time)){
                $book_where['a.add_time']=array(array('egt',strtotime($start_time)),array('lt',strtotime($end_time)+86400));
                $where['a.l_time']=array(array('egt',strtotime($start_time)),array('lt',strtotime($end_time)+86400));
            }elseif(strtotime($start_time) >strtotime($end_time)){
                $book_where['a.add_time']=array(array('egt',strtotime($start_time)),array('lt',strtotime($start_time)+86400));
                $where['a.l_time']=array(array('egt',strtotime($start_time)),array('lt',strtotime($end_time)+86400));
            }
        }else{
            if(!empty($start_time)){
                $book_where['a.l_time']=array('egt',strtotime($start_time));
                $where['a.l_time']=array('egt',strtotime($start_time));
            }elseif (!empty($end_time)){
                $where['a.l_time']=array('egt',strtotime($end_time));
                $book_where['a.l_time']=array('egt',strtotime($end_time));
            }
        }

        $state=I('state','');
        if($state!=''){
            $state = intval($state);
            if($state==1){
                $model_plan=M("xrp_log");
            }elseif($state==2){
                $model_plan=M("innovate_log");
            }elseif($state==3){
                $model_plan=M("xrpj_log");
            }elseif($state==4){
                $model_plan=M("accountbook");
            }
        }else{
            $model_plan=M("xrp_log");
        }
        if($state==4){
            //根据分类或模糊查找数据数量
            $count = $model_plan->field('count(a.member_id) as num,sum(number) as total_num')->alias("a")
                ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.member_id")
                ->where($book_where)
                ->find();
            // 实例化分页类 传入总记录数和每页显示的记录数
            $Page = new \Think\Page ($count['num'], 10);
            //给分页传参数
            setPageParameter($Page, array('name' => ""));
            //分页显示输出性
            $show = $Page->show();
            $field="a.*,m.email,m.phone";
            $log = $model_plan->alias("a")
                ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.member_id")
                ->field($field)
                ->where($book_where)
                ->order("a.add_time desc")
                ->limit($Page->firstRow . ',' . $Page->listRows)->select();
           $people_count = count($model_plan->field('a.member_id')->alias("a")
                ->group('a.member_id')
                ->where($book_where)
                ->select());
            if($log){
                foreach ($log as $key=>$val){
                    $nick_info=$this->member_info($val['to_member_id']);
                    if(empty($val['phone'])){
                        $logs[$key]['phone']=$val['email'];
                    }
                    $logs[$key]['l_member_id']=$val['member_id']>0?$val['member_id']:'无';
                    $logs[$key]['l_member_id']=$val['member_id']>0?$val['member_id']:'无';
                    $logs[$key]['to_member_id']=$val['to_member_id']>0?$val['to_member_id']:'无';
                    $nick=empty($nick_info)?"******":$nick_info['nick'];
                    if($val['number_type']==1){
                        $logs[$key]['l_title']=L('lan_mutual_transfer2').$nick.L('lan_mutual_transfer3');
                        $logs[$key]['l_state']="收入";
                        $logs[$key]['l_value']=$val['number']>0?'+'.$val['number'].'xrp':$val['number'].'xrp';
                    }elseif ($val['number_type']==2){
                        $logs[$key]['l_title']=L('lan_mutual_transfer1').$nick;
                        $logs[$key]['l_state']="支出";
                        $logs[$key]['l_value']=$val['number']>0?'-'.$val['number'].'xrp':$val['number'].'xrp';
                    }
                    $logs[$key]['l_time']=date('Y-m-d H:i:s',$val['add_time']);
                    $logs[$key]['l_type']="平台内转账";
                    $logs[$key]['l_type_explain']="平台内转账";


                }
                unset($log);
                $log=$logs;
            }
        }else{
            //根据分类或模糊查找数据数量
            $count = $model_plan->field('count(a.l_member_id) as num ,sum(a.l_value) as total_num')->alias("a")
                ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.l_member_id")
                ->where($where)
                ->find();
            // 实例化分页类 传入总记录数和每页显示的记录数
            $Page = new \Think\Page ($count['num'], 10);
            //给分页传参数
            setPageParameter($Page, array('name' => ""));
            //分页显示输出性
            $show = $Page->show();
            $field="a.*,m.email,m.phone";
            $log = $model_plan->alias("a")
                ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.l_member_id")
                ->field($field)
                ->where($where)
                ->order("a.l_time desc")
                ->limit($Page->firstRow . ',' . $Page->listRows)->select();
            $where['a.l_value']=array('lt',0);
            if($type==9||$type==10){

                $people_count = count($model_plan->field('a.l_member_id')->alias("a")
                    ->group('a.l_member_id')
                    ->where($where)
                    ->select());
//            echo  M('boss_plan_info')->getLastSql();die;
            }
            if($log){
                foreach ($log as $key=>$val){
                    if(empty($val['phone'])){
                        $log[$key]['phone']=$val['email'];
                    }
                    $log[$key]['l_title']=L($val['l_title']);
                    $log[$key]['to_member_id']="无";
                    if($val['l_type']==10){
                        $nick_info=$this->member_info($val['l_title']);
                        $log[$key]['to_member_id']=$val['l_title'];
                        $nick=empty($nick_info)?"******":$nick_info['nick'];
                        if($val['l_value']>0){
                            $log[$key]['l_title']=L('lan_mutual_transfer2').$nick.L('lan_mutual_transfer3');
                        }elseif($val['l_value']<0){
                            $log[$key]['l_title']=L('lan_mutual_transfer1').$nick;
                        }
                    }
                    if($val['l_type']==11){
                        $log[$key]['l_title']=$val['l_votes'].L('lan_accountbook_boss_plan_ticket');
                    }
                    if($val['l_type']==12){
                        $log[$key]['to_member_id']=$val['l_title'];
                        $nick_info=$this->member_info($val['l_title']);
                        $nick=empty($nick_info)?"******":$nick_info['nick'];
                        $log[$key]['l_title']=L('lan_accountbook_boss_plan_wei').$nick.L('lan_accountbook_boss_plan_active').$val['l_votes'].L('lan_accountbook_boss_plan_ticket');
                    }
                    $log[$key]['l_type_explain']=L($val['l_type_explain']);
                    $log[$key]['l_time']=date('Y-m-d H:i:s',$val['l_time']);

                    if($state==3){
                        $log[$key]['l_value']=$val['l_value']>0?'+'.$val['l_value'].'xrpj':$val['l_value'].'xrpj';
                    }else{
                        $log[$key]['l_value']=$val['l_value']>0?'+'.$val['l_value'].'xrp':$val['l_value'].'xrp';
                    }

                    $log[$key]['l_state']=$val['l_value']>0?"收入":"支出";
                    $log[$key]['l_type']=$this->type_arr(1)[$val['l_type']];
                }
            }
        }


        if($status==1){
            $lang['state1']='收入';
            $lang['state2']='转入';
        }elseif($status==2){
            $lang['state1']='支出';
            $lang['state2']='转出';
        }
        $profit_field='sum(a.num1) as num1,
        sum(a.num2) as num2,
        sum(a.num3) as num3,
        sum(a.num4) as num4,
        sum(a.num5) as num5,
        sum(a.num6) as num6,
        sum(a.num7) as num7,
        sum(a.num8) as num8,
        sum(a.total_profit) as total_profit';
        $profit=M('boss_plan_count')->alias("a")
            ->field($profit_field)
            ->where($bonus_where)
            ->find();
        $total['total_profit']=$profit['total_profit']>0?$profit['total_profit']:'0.000000';
        $total['people_num']=$people_count>0?$people_count:0;
        $total['total_num']=str_replace('-','',$count['total_num'])>0?str_replace('-','',$count['total_num']):'0.000000';
        $total['num']=$count['num']>0?$count['num']:0;

        $where['type']=$type;
        $where['status']=$status;
        $where['state']=$state;
        $where['phone']=$phone;
        $where['member_id']=$member_id;
        $where['start_time']=$start_time;
        $where['end_time']=$end_time;
        $this->assign('lang', $lang);
        $this->assign('page', $show);
        $this->assign('list', $log);
        $this->assign('where', $where);
        $this->assign('total', $total);

        $this->assign('type_arr', $this->type_arr(1));
        $this->display();
    }
    private function member_info($member_id,$ield="member_id,phone,nick,reg_time,email"){
        $member=M('member')->field($ield)->where(['member_id'=>$member_id])->find();
        if(!empty($member)){
            if(empty($member['nick'])){
                $member['nick']=$member['phone'];
                if(empty($member['phone'])){
                    $member['nick']=$member['email'];
                }
            }
        }

        return empty($member)?[]:$member;
    }

    //每日拨比
    public function bouns_total(){

        $where=[];
        $start_time = I('start_time','');
        $end_time = I('end_time','');
        if(!empty($start_time)&&!empty($end_time)){
            if(strtotime($start_time) <=strtotime($end_time)){
                $where['add_time']=array(array('egt',strtotime($start_time)),array('lt',strtotime($end_time)+86400));

            }elseif(strtotime($start_time) >strtotime($end_time)){
                $where['add_time']=array(array('egt',strtotime($start_time)),array('lt',strtotime($start_time)+86400));

            }
        }else{
            if(!empty($start_time)){
                $where['add_time']=array('egt',strtotime($start_time));
            }elseif (!empty($end_time)){
                $where['add_time']=array('egt',strtotime($end_time));
            }
        }
        $order = I('order');
        if(!empty($order) && in_array($order,['pay_number','xrp_num','xrpz','xrpj','bouns_num','base_num','num1','num2','num3','num4','num5','num6','num7','num8','num9','num10','all_bonus'])) {
            $order = $order;
        } else {
            $order = 'add_time';
        }

        $order_by = I('order_by');
        if(empty($order_by) || $order_by=='desc') {
            $order_by = 'desc';
        } else {
            $order_by = 'asc';
        }
        //总拨比统计
        $yesterday=strtotime(date("Y-m-d",strtotime("+1 day")));
        $pay_number=M('boss_plan_buy')->field('sum(pay_number) as num')->where(['status'=>1,'add_time'=>array('lt',$yesterday)])->find();
        $pay_number['num']=$pay_number['num']>0?$pay_number['num']:0;
        $bouns=M('boss_bouns_log')->field('sum(num) as num')->where(['receive_status'=>1])->find();
        $bouns['num']=$bouns['num']>0?$bouns['num']:0;
        //静态拨比统计
        $quiet_bouns=M('boss_bouns_log')->field('sum(num) as num')->where(['receive_status'=>1,'type'=>array('in',[1,2,3,4])])->find();
        $quiet_bouns['num']=$quiet_bouns['num']>0?$quiet_bouns['num']:0;
        //动态拨比统计
        $move_bouns=M('boss_bouns_log')->field('sum(num) as num')->where(['receive_status'=>1,'type'=>array('in',[5,6,7,8,9])])->find();
        $move_bouns['num']=$move_bouns['num']>0?$move_bouns['num']:0;

        $model_plan=M("boss_bouns_total");
        //根据分类或模糊查找数据数量
        $field='count(id) as num,
        sum(pay_number) as pay_number,
        sum(xrp_num) as xrp_num,
        sum(xrpz) as xrpz,
        sum(xrpj) as xrpj,
        sum(bouns_num) as bouns_num,
        sum(base_num) as base_num,
        sum(num1) as num1,
        sum(num2) as num2,
        sum(num3) as num3,
        sum(num4) as num4,
        sum(num5) as num5,
        sum(num6) as num6,
        sum(num7) as num7,
        sum(num8) as num8,
        sum(num9) as num9,
        sum(num10) as num10';
        $count = $model_plan->field($field)
            ->where($where)
            ->find();

        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count['num'], 10);
        //给分页传参数
        setPageParameter($Page, array('order'=>$order,'order_by'=>$order_by,'start_time'=>$start_time,'end_time'=>$end_time));
        //分页显示输出性
        $show = $Page->show();
        $log = $model_plan
            ->field('*,(base_num+bouns_num) as all_bonus')
            ->where($where)
            ->order($order.' '.$order_by)
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($log as $key=>$val){
            $log[$key]['add_time']=date('Y-m-d H:i:s',$val['add_time']);
            $log[$key]['all_bonus']=$val['base_num']+$val['bouns_num'];
            $log[$key]['base_num']=$val['base_num']-$val['num5']-$val['num4'];
            $log[$key]['bouns_num']=$val['bouns_num']+$val['num5'];
            //静态奖金拨比
            $log[$key]['quiet_bobbi']=(round(($val['base_num']-$val['num5']-$val['num4'])/$pay_number['num'], 4)*100).'%';
//            $log[$key]['quiet_bobbi']=(round(($val['base_num']-$val['num5'])/$val['pay_number'], 4)*100).'%';
//            $log[$key]['quiet_bobbi']=(round(($val['num1']+$val['num2']+$val['num3']+$val['num5'])/$val['pay_number'], 4)*100).'%';
           // 动态奖金拨比
            $log[$key]['move_bobbi']=(round(($val['bouns_num']+$val['num5'])/$val['pay_number'], 4)*100).'%';
         //  $log[$key]['move_bobbi']=(round(($val['num6']+$val['num7']+$val['num8']+$val['num9'])/$val['pay_number'], 4)*100).'%';
            //总奖金拨比
            $log[$key]['num4_bobbi'] = (round($val['num4']/$val['pay_number'], 4)*100).'%';
            $log[$key]['total_bobbi']=((round(($val['base_num']-$val['num5']-$val['num4'])/$pay_number['num'], 4)*100)+(round(($val['bouns_num']+$val['num5']+$val['num4'])/$val['pay_number'], 4)*100)).'%';
         //   $log[$key]['total_bobbi']=(round(($val['num1']+$val['num2']+$val['num3']+$val['num5']+$val['num6']+$val['num7']+$val['num8']+$val['num9'])/$val['pay_number'], 4)*100).'%';
        }

        $total_arr['num']=$count['num']>0?$count['num']:0;
        // 全部静态拨比
        $total_arr['quiet_bobi']=(round(($quiet_bouns['num']/$pay_number['num']),4)*100).'%';
        //全部动态拨比
        $total_arr['move_bobi']=(round(($move_bouns['num']/$pay_number['num']),4)*100).'%';
        //全部总拨比
        $total_arr['all_bobi']=(round(($bouns['num']/$pay_number['num']),4)*100).'%';
        $total_arr['pay_number']=$count['pay_number']>0?$count['pay_number']:'0.000000';
        $total_arr['xrp_num']=$count['xrp_num']>0?$count['xrp_num']:'0.000000';
        $total_arr['xrpz']=$count['xrpz']>0?$count['xrpz']:'0.000000';
        $total_arr['xrpj']=$count['xrpj']>0?$count['xrpj']:'0.000000';
        $total_arr['bouns_num']=$count['bouns_num']>0?$count['bouns_num']+$count['num5']:'0.000000';
        $total_arr['base_num']=$count['base_num']>0?$count['base_num']-$count['num5']:'0.000000';
        $total_arr['num1']=$count['num1']>0?$count['num1']:'0.000000';
        $total_arr['num2']=$count['num2']>0?$count['num2']:'0.000000';
        $total_arr['num3']=$count['num3']>0?$count['num3']:'0.000000';
        $total_arr['num4']=$count['num4']>0?$count['num4']:'0.000000';
        $total_arr['num5']=$count['num5']>0?$count['num5']:'0.000000';
        $total_arr['num6']=$count['num6']>0?$count['num6']:'0.000000';
        $total_arr['num7']=$count['num7']>0?$count['num7']:'0.000000';
        $total_arr['num8']=$count['num8']>0?$count['num8']:'0.000000';
        $total_arr['num9']=$count['num9']>0?$count['num9']:'0.000000';
        $total_arr['num10']=$count['num10']>0?$count['num10']:'0.000000';
        $where['start_time']=$start_time;
        $where['end_time']=$end_time;
        $where['order'] = $order;
        $where['order_by'] = $order_by;
        $this->assign('page', $show);
        $this->assign('total_arr', $total_arr);
        $this->assign('where', $where);
        $this->assign('list', $log);
        $this->display();
    }
    //老板计划阶段
    public function planStep(){
        $model_plan=M("boss_plan_step");
        $count = $model_plan->count();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count, 10);
        //给分页传参数
        setPageParameter($Page, array('name' => ""));
        //分页显示输出性
        $show = $Page->show();
        $list=$model_plan->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($list as $key =>$val){
            $list[$key]['start_time']=date('Y-m-d H:i:s',$val['start_time']);
            $list[$key]['add_time']=date('Y-m-d H:i:s',$val['add_time']);
            $list[$key]['is_open']=str_replace(array(0,1),array('关闭','开启'),$val['is_open']);
        }
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->display();
    }

    public function swicth_step() {
        $step_id = intval(I('step_id'));
        $info = M('boss_plan_step')->where(['step_id'=>$step_id])->find();
        if(IS_POST) {
            if($info) {
                $start_time = I('start_time');
                if(!empty($start_time)) {
                    $start_time = strtotime($start_time);
                } else {
                    $start_time = $info['start_time'];
                }
                $data = [
                    'min_votes' => intval(I('min_votes')),
                    'max_votes' => intval(I('max_votes')),
                    'start_time' => $start_time,
                ];
                $flag = M('boss_plan_step')->where(['step_id'=>$step_id])->save($data);
                if($flag===false){
                    $this->error('修改失败');
                } else {
                    $this->error('修改成功');
                }
            }
        } else {
            $this->assign('list',$info);
            $this->display();
        }
    }

    //老板计划激活日志
    public function activationLog(){
        $where=[];
        $phone=I('phone');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['m.email'] = $phone;
            } else {
                $where['m.phone'] = $phone;
            }
        }
        $member_id=I('member_id','');
        if(I('member_id')){
            $where['a.member_id']=$member_id;
        }
        $pid=I('pid','');
        if(I('pid')){
            $where['a.pid']=$pid;
        }
        $pay_id=I('pay_id','');
        if(I('pay_id')){
            $where['a.pay_id']=$pay_id;
        }

        $status = I('status','');
        if($status!='') {
            $status = intval($status);
            if($status==1){
                $where['a.status'] = 0;
            }elseif($status==2){
                $where['a.status'] = 1;
            }elseif($status==3){
                $where['a.status'] = 2;
            }
        }
        $type = I('type','');
        if($type!='') {
            $type = intval($type);
            if($type==1){
                $where['a.type'] = 0;
            }elseif($type==2){
                $where['a.type'] = 1;
            }
        }
        $votes= I('votes','');
        if(is_numeric($votes)){
            $where['a.votes'] = $votes;
        }
        $start_time = I('start_time','');
        $end_time = I('end_time','');
        if(!empty($start_time)&&!empty($end_time)){
            if(strtotime($start_time) <=strtotime($end_time)){
                $where['add_time']=array(array('egt',strtotime($start_time)),array('lt',strtotime($end_time)+86400));

            }elseif(strtotime($start_time) >strtotime($end_time)){
                $where['add_time']=array(array('egt',strtotime($start_time)),array('lt',strtotime($start_time)+86400));

            }
        }else{
            if(!empty($start_time)){
                $where['add_time']=array('egt',strtotime($start_time));
            }elseif (!empty($end_time)){
                $where['add_time']=array('egt',strtotime($end_time));
            }
        }
        $model_plan=M("boss_plan_buy");
        //根据分类或模糊查找数据数量
        $field='
        count(a.member_id) as num,
        sum(a.votes) as votes,
        sum(a.total) as total,
        sum(a.pay_number) as pay_number,
        sum(a.xrp_num) as xrp_num,
        sum(a.xrpz) as xrpz,
        sum(a.xrpj) as xrpj
        ';
        $count = $model_plan->field($field)->alias("a")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.member_id")
            ->where($where)
            ->find();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count['num'], 10);
        //给分页传参数
        setPageParameter($Page, array('name' => ""));
        //分页显示输出性
        $show = $Page->show();
        $field="a.*,m.email,m.phone";
        $list = $model_plan->alias("a")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.member_id")
            ->field($field)
            ->where($where)
            ->order("a.add_time desc")
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();

        foreach ($list as $key=>$val){
            if(empty($val['phone'])){
                $list[$key]['phone']=$val['email'];
            }

            $list[$key]['type']=str_replace(array(0,1),array('激活','认购投票'),$val['type']);
            $list[$key]['status']=str_replace(array(0,1,2),array('冻结中','激活','已撤销'),$val['status']);
            $list[$key]['add_time']=$val['add_time']>0?date('Y-m-d H:i:s',$val['add_time']):"无";
        }

        $where['status']=$status;
        $where['phone']=$phone;
        $where['member_id']=$member_id;
        $where['pid']=$pid;
        $where['pay_id']=$pay_id;
        $where['type']=$type;
        $where['start_time']=$start_time;
        $where['end_time']=$end_time;
        $where['votes']=$votes;

        $total_arr['num']=$count['num']>0?$count['num']:0;
        $total_arr['votes']=$count['votes']>0?$count['votes']:'0';
        $total_arr['total']=$count['total']>0?$count['total']:'0.000000';
        $total_arr['pay_number']=$count['pay_number']>0?$count['pay_number']:'0.000000';
        $total_arr['xrp_num']=$count['xrp_num']>0?$count['xrp_num']:'0.000000';
        $total_arr['xrpz']=$count['xrpz']>0?$count['xrpz']:'0.000000';
        $total_arr['xrpj']=$count['xrpj']>0?$count['xrpj']:'0.000000';
        $this->assign('total_arr', $total_arr);
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->assign('where', $where);
        $this->display();
    }

    //@标交易开关（划转与互转）
    public function transaction_switch(){
        $model_confing=M('boss_config');
        $where=[
            'ransfer_switch','mutual_turn_switch','transfer_fee','xrp_fee','xrpj_fee','wallet_fee','gac_lock_switch','gac_lock_fee','gac_internal_buy_fee','gac_internal_buy_switch',
            'base_boss_bonus_switch','add_boss_bonus_switch','first_boss_bonus_switch','mutual_boss_bonus_switch','recommend_boss_bonus_switch','community_boss_bonus_switch',
            'level_boss_bonus_switch','manage_boss_bonus_switch','boss_old_user_remain_money_switch','boss_old_user_confirm_time','xrp_exchange_gac','remain_gac_price_cny',
            'gac_xrp_exchange_gac_fee','gac_transfer_switch','xrp_exchange_release_gac_switch','gac_transfer_switch,xrp_exchange_release_gac_switch','manage_boss_bonus_switch','boss_old_user_remain_money_switch'
        ];
        //开关数组
        $switch_arr=[
            'gac_internal_buy_switch','base_boss_bonus_switch','add_boss_bonus_switch','first_boss_bonus_switch','mutual_boss_bonus_switch','recommend_boss_bonus_switch','community_boss_bonus_switch','boss_old_user_remain_money_switch',
            'level_boss_bonus_switch','manage_boss_bonus_switch','gac_transfer_switch','xrp_exchange_release_gac_switch'];
        //手续费数组
        $fee_arr=['gac_xrp_exchange_gac_fee','xrp_exchange_gac'];
        $list=$model_confing->where(['key'=>array('in',$where)])->select();
        foreach ($list as $key =>$val){
            if($val['key']=='ransfer_switch'){
                $list[$key]['key_name']="划转瑞波钻";
                if($val['value']==1){
                    $list[$key]['key_state']="开启";
                }elseif($val['value']==2){
                    $list[$key]['key_state']="关闭";
                }
            }elseif($val['key']=='mutual_turn_switch'){
                $list[$key]['key_name']="互转瑞波钻与瑞波金、钱包";
                if($val['value']==1){
                    $list[$key]['key_state']="开启";
                }elseif($val['value']==2){
                    $list[$key]['key_state']="关闭";
                }
            }elseif($val['key']=='gac_lock_switch'){
                $list[$key]['key_name']="GAC兑换";
                if($val['value']==1){
                    $list[$key]['key_state']="开启";
                }elseif($val['value']==2){
                    $list[$key]['key_state']="关闭";
                }
            }elseif($val['key']=='gac_internal_buy_switch'){
                $list[$key]['key_name']="GAC内购";
                if($val['value']==1){
                    $list[$key]['key_state']="开启";
                }elseif($val['value']==2){
                    $list[$key]['key_state']="关闭";
                }
            }elseif($val['key']=='transfer_fee'){
                $list[$key]['key_name']="划转手续费";
                $list[$key]['key_state']=($val['value']*100)."%";
            }elseif($val['key']=='xrp_fee'){
                $list[$key]['key_name']="瑞波钻互转手续费";
                $list[$key]['key_state']=($val['value']*100)."%";
            }elseif($val['key']=='xrpj_fee'){
                $list[$key]['key_name']="瑞波金互转手续费";
                $list[$key]['key_state']=($val['value']*100)."%";
            }elseif($val['key']=='wallet_fee'){
                $list[$key]['key_name']="钱包互转手续费";
                $list[$key]['key_state']=($val['value']*100)."%";
            }elseif($val['key']=='gac_lock_fee'){
                $list[$key]['key_name']="GAC兑换互转手续费";
                $list[$key]['key_state']=($val['value']*100)."%";
            }elseif($val['key']=='gac_internal_buy_fee'){
                $list[$key]['key_name']="GAC内购互转手续费";
                $list[$key]['key_state']=($val['value']*100)."%";
            }elseif($val['key']=='remain_gac_price_cny'){
                $list[$key]['key_name']=$val['desc'];
                $list[$key]['key_state']=$val['value'];
            }elseif($val['key']=='boss_old_user_confirm_time'){
                $list[$key]['key_name']=$val['desc'];
                $list[$key]['key_state']=date('Y-m-d H:i:s',$val['value']);
                $list[$key]['value']=date('Y-m-d H:i:s',$val['value']);
            }else{
                $list[$key]['key_name']=str_replace('(1为开启，2为关闭)','',$val['desc']);

            }
            if(in_array($val['key'],$switch_arr)){
                $list[$key]['key_name']=str_replace('(1为开启，2为关闭)','',$val['desc']);
                if($val['value']==1){
                    $list[$key]['key_state']="开启";
                }elseif($val['value']==2){
                    $list[$key]['key_state']="关闭";
                }
            }
            if(in_array($val['key'],$fee_arr)){
                $list[$key]['key_name']=str_replace('(1为开启，2为关闭)','',$val['desc']);
                $list[$key]['key_state']=($val['value']*100)."%";
            }


        }
        $this->assign('list', $list);
        $this->display();
    }
    //@标交易开关（划转与互转）操作
    public function transaction(){
        $model_confing=M('boss_config');
        $key = I("post.key");
        $state = I("post.type");
        $where=[
            'ransfer_switch','mutual_turn_switch','transfer_fee','xrp_fee','xrpj_fee','wallet_fee','gac_lock_switch','gac_lock_fee','gac_internal_buy_fee','gac_internal_buy_switch',
            'base_boss_bonus_switch','add_boss_bonus_switch','first_boss_bonus_switch','mutual_boss_bonus_switch','recommend_boss_bonus_switch','community_boss_bonus_switch',
            'level_boss_bonus_switch','manage_boss_bonus_switch','boss_old_user_remain_money_switch','boss_old_user_confirm_time','xrp_exchange_gac','remain_gac_price_cny',
            'gac_xrp_exchange_gac_fee','gac_transfer_switch','xrp_exchange_release_gac_switch','gac_transfer_switch,xrp_exchange_release_gac_switch','manage_boss_bonus_switch','boss_old_user_remain_money_switch'
        ];
        if(!in_array($key,$where)||!in_array($state,[1,2])){
            $this->ajaxReturn(['Code' => 0, 'Msg' => "参数错误！"]);
        }
        if($state==1){
            $state=2;
        }elseif($state==2){
            $state=1;
        }
        $confing=$model_confing->where(['key'=>$key])->find();
        if(empty($confing)){
            $this->ajaxReturn(['Code' => 0, 'Msg' => "数据异常！"]);
        }
        $update=$model_confing->where(['key'=>$key])->save(['value'=>$state]);
        if($update){
            $this->ajaxReturn(['Code' =>1, 'Msg' =>"操作成功"]);
        }else{
            $this->ajaxReturn(['Code' => 0, 'Msg' => "操作失败"]);
        }

    }
    //@标手续费设置
    public function fee_set(){
        $model_confing=M('boss_config');
        $key = I("post.key");
        $state = I("post.type");
        if($state<0 ||$state>=1){
            $this->ajaxReturn(['Code' => 0, 'Msg' => "请输入大于0，小于1数值！"]);
        }
        //判断输入金额不能超出3位小数
        if($this->getFloatLength($state)>3){
            $this->ajaxReturn(['Code' => 0, 'Msg' => "輸入數量最多保留小數點後3位"]);
        }
        if(!in_array($key,['transfer_fee','xrp_fee','xrpj_fee','wallet_fee','gac_lock_fee','gac_internal_buy_fee','gac_xrp_exchange_gac_fee','xrp_exchange_gac'])){
            $this->ajaxReturn(['Code' => 0, 'Msg' => "参数错误！"]);
        }
        $confing=$model_confing->where(['key'=>$key])->find();
        if(empty($confing)){
            $this->ajaxReturn(['Code' => 0, 'Msg' => "数据异常！"]);
        }
        $update=$model_confing->where(['key'=>$key])->save(['value'=>$state]);
        if($update){
            $this->ajaxReturn(['Code' =>1, 'Msg' =>"操作成功"]);
        }else{
            $this->ajaxReturn(['Code' => 0, 'Msg' => "操作失败"]);
        }
    }
    public function set(){
        $model_confing=M('boss_config');
        $key = I("post.key");
        $state = I("post.type");

        if(!in_array($key,['boss_old_user_confirm_time','remain_gac_price_cny'])){
            $this->ajaxReturn(['Code' => 0, 'Msg' => "参数错误！"]);
        }
        if($key=='boss_old_user_confirm_time'){
            $state=strtotime($state);
        }
        $confing=$model_confing->where(['key'=>$key])->find();
        if(empty($confing)){
            $this->ajaxReturn(['Code' => 0, 'Msg' => "数据异常！"]);
        }
        $update=$model_confing->where(['key'=>$key])->save(['value'=>$state]);
        if($update){
            $this->ajaxReturn(['Code' =>1, 'Msg' =>"操作成功"]);
        }else{
            $this->ajaxReturn(['Code' => 0, 'Msg' => "操作失败"]);
        }
    }
    //划转报表日志@标
    public function transfer(){
        $where=[];
        $phone=I('phone');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['m.email'] = $phone;
            } else {
                $where['m.phone'] = $phone;
            }
        }
        $member_id=I('member_id','');
        if(I('member_id')){
            $where['a.l_member_id']=$member_id;
        }
        $where['a.l_type'] = 9;
        $start_time = I('start_time','');
        $end_time = I('end_time','');
        if(!empty($start_time)&&!empty($end_time)){
            if(strtotime($start_time) <=strtotime($end_time)){
                $where['a.l_time']=array(array('egt',strtotime($start_time)),array('lt',strtotime($end_time)+86400));
            }elseif(strtotime($start_time) >strtotime($end_time)){
                $where['a.l_time']=array(array('egt',strtotime($start_time)),array('lt',strtotime($start_time)+86400));
            }
        }else{
            if(!empty($start_time)){
                $where['a.l_time']=array('egt',strtotime($start_time));
            }elseif (!empty($end_time)){
                $where['a.l_time']=array('egt',strtotime($end_time));
            }
        }

        $model_plan=M("xrp_log");
        //根据分类或模糊查找数据数量
        $count = $model_plan->field('count(a.l_member_id) as num, sum(l_value) as transfer_num' )->alias("a")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.l_member_id")
            ->where($where)
            ->find();
        //划转人数
        $transfer_number=$model_plan->field('count(a.l_member_id) as number' )->alias("a")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.l_member_id")
            ->where($where)
            ->group('a.l_member_id')
            ->find();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count['num'], 10);
        //给分页传参数
        setPageParameter($Page, array('name' => ""));
        //分页显示输出性
        $show = $Page->show();
        $field="a.*,m.email,m.phone";
        $log = $model_plan->alias("a")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.l_member_id")
            ->field($field)
            ->where($where)
            ->order("a.l_time desc")
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();

        if($log){
            foreach ($log as $key=>$val){
                $log[$key]['l_time']=date('Y-m-d H:i:s',$val['l_time']);
                $log[$key]['l_value']=$val['l_value']>0?'+'.$val['l_value'].'xrp':$val['l_value'].'xrp';
                $log[$key]['l_state']=$val['l_value']>0?"收入":"支出";
                $log[$key]['l_type']=$this->type_arr(1)[$val['l_type']];
            }
        }

        $where['phone']=$phone;
        $where['member_id']=$member_id;
        $where['start_time']=$start_time;
        $where['end_time']=$end_time;

        $this->assign('page', $show);
        $this->assign('list', $log);
        $this->assign('where', $where);

        $this->assign('type_arr', $this->type_arr(1));
        $this->display();
    }

    //互转报表日志@标
    public function mutual_turn(){
        $where=[];
        $phone=I('phone');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['m.email'] = $phone;
            } else {
                $where['m.phone'] = $phone;
            }
        }
        $member_id=I('member_id','');
        if(I('member_id')){
            $where['a.l_member_id']=$member_id;
        }

        $status = I('status','');
        if($status!='') {
            $status = intval($status);
            if($status ==1){
                $where['a.l_value'] = array('gt',0);
            }elseif($status ==2){
                $where['a.l_value'] = array('lt',0);
            }

        }
        $type = I('type','');
        if($type!='') {
            $type = intval($type);
            $where['a.l_type'] = $type;
        }
        $start_time = I('start_time','');
        $end_time = I('end_time','');
        if(!empty($start_time)&&!empty($end_time)){
            if(strtotime($start_time) <=strtotime($end_time)){
                $where['a.l_time']=array(array('egt',strtotime($start_time)),array('lt',strtotime($end_time)+86400));
            }elseif(strtotime($start_time) >strtotime($end_time)){
                $where['a.l_time']=array(array('egt',strtotime($start_time)),array('lt',strtotime($start_time)+86400));
            }
        }else{
            if(!empty($start_time)){
                $where['a.l_time']=array('egt',strtotime($start_time));
            }elseif (!empty($end_time)){
                $where['a.l_time']=array('egt',strtotime($end_time));
            }
        }

        $state=I('state','');
        if($state!=''){
            $state = intval($state);
            if($state==1){
                $model_plan=M("accountbook");
                $info=Db::name('currency')->field('currency_id')->where(['currency_name'=>'XRP'])->find();
                    $where['a.currency_id']=$info['currency_id'];
                    $where['a.type']=18;
            }elseif($state==2){
                $model_plan=M("xrp_log");
                $where['a.l_type']=10;
            }elseif($state==3){
                $model_plan=M("xrpj_log");
                $where['a.l_type']=10;
            }
        }else{
            $model_plan=M("xrp_log");
        }
        //根据分类或模糊查找数据数量
        $count = $model_plan->field('count(a.l_member_id) as num')->alias("a")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.l_member_id")
            ->where($where)
            ->find();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count['num'], 10);
        //给分页传参数
        setPageParameter($Page, array('name' => ""));
        //分页显示输出性
        $show = $Page->show();
        $field="a.*,m.email,m.phone";
        $log = $model_plan->alias("a")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.l_member_id")
            ->field($field)
            ->where($where)
            ->order("a.l_time desc")
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();

        if($log){
            foreach ($log as $key=>$val){
                $log[$key]['l_title']=L($val['l_title']);
                $log[$key]['to_member_id']="无";
                if($val['l_type']==10){
                    $nick_info=$this->member_info($val['l_title']);
                    $log[$key]['to_member_id']=$val['l_title'];
                    $nick=empty($nick_info)?"******":$nick_info['nick'];
                    if($val['l_value']>0){
                        $log[$key]['l_title']=L('lan_mutual_transfer2').$nick.L('lan_mutual_transfer3');
                    }elseif($val['l_value']<0){
                        $log[$key]['l_title']=L('lan_mutual_transfer1').$nick;
                    }
                }
                if($val['l_type']==11){
                    $log[$key]['l_title']=$val['l_votes'].L('lan_accountbook_boss_plan_ticket');
                }
                if($val['l_type']==12){
                    $log[$key]['to_member_id']=$val['l_title'];
                    $nick_info=$this->member_info($val['l_title']);
                    $nick=empty($nick_info)?"******":$nick_info['nick'];
                    $log[$key]['l_title']=L('lan_accountbook_boss_plan_wei').$nick.L('lan_accountbook_boss_plan_active').$val['l_votes'].L('lan_accountbook_boss_plan_ticket');
                }
                $log[$key]['l_type_explain']=L($val['l_type_explain']);
                $log[$key]['l_time']=date('Y-m-d H:i:s',$val['l_time']);
                $log[$key]['l_value']=$val['l_value']>0?'+'.$val['l_value'].'xrp':$val['l_value'].'xrp';
                $log[$key]['l_state']=$val['l_value']>0?"收入":"支出";
                $log[$key]['l_type']=$this->type_arr(1)[$val['l_type']];
            }
        }


        $where['status']=$status;
        $where['state']=$state;
        $where['phone']=$phone;
        $where['member_id']=$member_id;
        $where['start_time']=$start_time;
        $where['end_time']=$end_time;

        $this->assign('page', $show);
        $this->assign('list', $log);
        $this->assign('where', $where);
        $this->assign('type_arr', $this->type_arr(5));
        $this->display();
    }
    //判断小数的位数@标
    public function getFloatLength($num) {
        $count = 0;

        $temp = explode ( '.', $num );
        if (sizeof ( $temp ) > 1) {
            $decimal = end ( $temp );
            $count=strlen($decimal);
        }
        return $count;
    }

    //树形结构
    public function ztree(){
        $where=[];
        $phone=I('phone');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['m.email'] = $phone;
            } else {
                $where['m.phone'] = $phone;
            }
        }
        $member_id=I('member_id','');
        if(I('member_id')){
            $where['a.member_id']=$member_id;
        }

        $model_plan=M("boss_plan_info");
        //根据分类或模糊查找数据数量
        if(!empty($member_id)||!empty($phone)){
            $field="a.*,m.email,m.phone";
            $info = $model_plan->alias("a")
                ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.member_id")
                ->field($field)
                ->where($where)
                ->find();
            if($info){
                $users=["id"=>$info['member_id'],"pId"=>0,"name"=>$info['member_id'].'_V'.$info['level'],"open"=>false,"isParent"=>true];

                $total_num = 0;
                $total_team_num = M('boss_bouns_week')->field('sum(num+child_num) as num')->where(['member_id'=>$info['member_id']])->find();
                if(!empty($total_team_num) && !empty($total_team_num['num'])) $total_num =  $total_team_num['num'];
                $info['total_team_num'] = $total_num;

                $total_child = M('member_bind')->where(['member_id'=>$info['member_id']])->count();
                $info['next_leve_num'] = $total_child;
            }
        }else{
            $info=[];
            $users=[];
        }

        $where['phone']=$phone;
        $where['member_id']=$member_id;
        $this->assign('where', $where);
        $this->assign('userinfo', $info);
        $this->assign('users', json_encode($users));
        $this->display();
    }
    /**
     * @标
     * 树形结构获取子节点数据
     */
    public function getChildNode()
    {
        $member_id = $_POST['id'];
        $model_member=M('member');
        $model_bind=M("member_bind");
        $user=$model_member->where(['member_id' => $member_id])->find();
        $list=$model_bind->where(['member_id'=>$user['member_id'],'level'=>1])->select();
        $userList = [];
        if($user){
            foreach ($list as $key=> $val){
                $userInfo=$model_bind->where(['member_id'=>$val['child_id']])->find();
                if (!empty($userInfo)) {
                    $isParent = true;
                } else {
                    $isParent = false;
                }
                $userList[] = [
                    'name' => $val['child_id'].'_V'.$val['child_level'],
                    'id' =>  $val['child_id'],
                    'pid' => $user['member_id'],
                    'isParent' => $isParent,
                ];
            }
        }
        $this->ajaxReturn($userList);
    }
    /**
     * @标
     * 树形结构用户信息
     */
    public function getUserInfo(){
        $user_id = I('post.user_id');
        $model_plan=M("boss_plan_info");
        //根据分类或模糊查找数据数量
        if(!empty($user_id)){
            $field="a.*,m.email,m.phone";
            $info = $model_plan->alias("a")
                ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.member_id")
                ->field($field)
                ->where(['a.member_id' => $user_id])
                ->find();
            if($info) {
                $total_num = 0;
                $total_team_num = M('boss_bouns_week')->field('sum(num+child_num) as num')->where(['member_id'=>$info['member_id']])->find();
                if(!empty($total_team_num) && !empty($total_team_num['num'])) $total_num =  $total_team_num['num'];
                $info['total_team_num'] = $total_num;

                $total_child = M('member_bind')->where(['member_id'=>$info['member_id']])->count();
                $info['next_leve_num'] = $total_child;
            }
        }
        $this->ajaxReturn($info);
    }



    private function type_arr($type){
        if($type==1){
           return array('1'=>'基础分红','2'=>'增加分红','3'=>'一级分红','4'=>'幸运赠送','5'=>'推荐奖励','6'=>'社区奖励','7'=>'平级奖励','8'=>'管理奖励','9'=>'划转','10'=>'平台内账','11'=>'激活 ','12'=>'认购','13'=>'管理员充值');
        }elseif($type==2){
            return array('1'=>'基础分红','2'=>'增加分红','3'=>'一级分红','4'=>'幸运赠送','5'=>'推荐奖励','6'=>'社区奖励','7'=>'平级奖励','8'=>'管理奖励','9'=>'划转','10'=>'平台内账');
        }elseif($type==3){
            return array('11'=>'激活 ','12'=>'认购','13'=>'管理员充值');
        }elseif($type==4){
            return array('1'=>'基础分红','2'=>'增加分红','3'=>'一级分红','4'=>'幸运赠送','5'=>'推荐奖励','6'=>'社区奖励','7'=>'平级奖励','8'=>'管理奖励');
        }elseif($type==5){
            return array('1'=>'钱包互转','2'=>'瑞波钻互转','3'=>'瑞波金互转');
        }



    }

}