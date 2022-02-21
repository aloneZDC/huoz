<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2019/1/8
 * Time: 18:11
 */

namespace Admin\Controller;


class BossPlanController extends AdminController
{   
    //幸运赠送查账
    public function reward_bouns_detail() {
        $today = I('today');
        $member_id = intval(I('member_id'));

        $today_unix = strtotime($today);
        $start_unix = $today_unix - 86400;

        $bouns_info = M('boss_bouns_log')->where(['member_id'=>$member_id,'type'=>4,'add_time'=>$today_unix])->find();

        $boss_plan = M('boss_plan_info')->where(['member_id'=>$member_id])->find();
        if($boss_plan) {
            $boss_config = [];
            $boss_config_info = M('boss_config')->where(['key'=>'param'])->find();
            if($boss_config_info) $boss_config = unserialize($boss_config_info['value']);

            $bouns_total = 0;
            if(!empty($boss_config)) {
                $boss_plan_lucky = M('boss_plan_lucky')->where(['member_id'=>$member_id])->find();
                if($boss_plan_lucky) {
                    $lucky_list = M('boss_plan_lucky')->where('add_time>'.$boss_plan_lucky['add_time'])->order('add_time asc')->limit(10)->select();
                    foreach ($lucky_list as &$lucky) {
                        //过滤掉任务跑完之后新加入公排的用户
                        if($lucky['add_time']>$bouns_info['run_time']) {
                            $lucky['lucy_bouns'] = 0;
                            continue;
                        }

                        $lucy_bouns = 0;
                        $lt_bouns = M()->query("select sum(pay_number) as num from yang_boss_plan_buy WHERE pay_number<".$boss_plan['num']." and member_id in (select child_id from yang_member_bind WHERE member_id=".$lucky['member_id']." and level<=".intval($boss_config['lucky_level']).") and status=1 and add_time BETWEEN ".$start_unix." and ".$today_unix." limit 1");
                        if($lt_bouns && isset($lt_bouns[0]) && !empty($lt_bouns[0]['num'])) $lucy_bouns += $lt_bouns[0]['num'];

                        $gt_bouns_total = M()->query("select count(*) as count from yang_boss_plan_buy WHERE pay_number>=".$boss_plan['num']." and member_id in (select child_id from yang_member_bind WHERE member_id=".$lucky['member_id']." and level<=".intval($boss_config['lucky_level']).") and status=1 and add_time BETWEEN ".$start_unix." and ".$today_unix." limit 1");
                        if($gt_bouns_total &&isset($gt_bouns_total[0]) && !empty($gt_bouns_total[0]['count'])) {
                            $lucy_bouns += $gt_bouns_total[0]['count'] * $boss_plan['num'];
                        }
                        
                        $bouns_total += $lucy_bouns;
                        $lucky['lucy_bouns'] = $lucy_bouns;
                    }
                }
            }
        }

        $this->assign('bouns_total',$bouns_total);
        $this->assign('bouns_info',$bouns_info);
        $this->assign('boss_plan',$boss_plan);
        $this->assign('boss_plan_lucky',$boss_plan_lucky);
        $this->assign('lucky_list',$lucky_list);
        $this->display();
    }

    //老板计划分红配置
    function updateConfigList(){
        $map['key']  = array('exp'," IN ('dynamic','param','entrepreneur')");
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

    //修改分红配置
    function updateConfig(){
        $key=I("key");
        if(!empty($key)){
            $config=M("boss_config")->where(['key'=>$key])->find();
            if(!empty($config)){
                $config_values = unserialize($config['value']);
                if(IS_POST) {
                    //基础分红配置
                    if($key=='param') {
                        $base_bouns = I('base_bouns');
                        if(!empty($base_bouns)) $config_values['base_bouns'] = $base_bouns;

                        foreach (['no_invit','invit','all','no_invit_v2','invit_v2'] as $value) {
                            $stop_value = I('stop_bouns_'.$value);
                            if(!empty($stop_value)) $config_values['stop_bouns'][$value] = $stop_value;
                        }

                        for ($num=0; $num < 11; $num++) { 
                            $c_value = I('add_bouns_'.$num);
                            if(!empty($c_value)) $config_values['add_bouns'][$num] = $c_value;
                        }

                        $one_bouns = I('one_bouns');
                        if(!empty($one_bouns)) $config_values['one_bouns'] = $one_bouns;

                        $recommand_bouns = I('recommand_bouns');
                        if(!empty($recommand_bouns)) $config_values['recommand_bouns'] = $recommand_bouns;

                        $lucky_bouns = I('lucky_bouns');
                        if(!empty($lucky_bouns)) $config_values['lucky_bouns'] = $lucky_bouns;

                        $lucky_level = I('lucky_level');
                        if(!empty($lucky_level)) $config_values['lucky_level'] = $lucky_level;

                        $base_bouns_v2 = I('base_bouns_v2');
                        if(!empty($base_bouns_v2)) $config_values['base_bouns_v2'] = $base_bouns_v2;

                        $v2_open_date = I('v2_open_date');
                        if(!empty($lucky_level)) $config_values['v2_open_date'] = $v2_open_date;

                        $lucky_bouns_v2 = I('lucky_bouns_v2');
                        if(!empty($lucky_bouns_v2)) $config_values['lucky_bouns_v2'] = $lucky_bouns_v2;
                        
                    } elseif($key=='dynamic'){
                        //动态分红配置之社区奖励
                        for ($num=0; $num < 7; $num++) { 
                            $c_value = I('level_bouns_'.$num);
                            if(!empty($c_value)) $config_values['level_bouns'][$num] = $c_value;
                        }

                        //平级奖励
                        for ($num=1; $num < 3; $num++) { 
                            $c_value = I('ping_bouns_'.$num);
                            if(!empty($c_value)) $config_values['ping_bouns'][$num] = $c_value;
                        }

                        //管理奖励
                        $manager_bouns = I('manager_bouns');
                        if(!empty($manager_bouns)) $config_values['manager_bouns'] = $manager_bouns;

                        //推荐奖励
                        $recommand_bouns = I('recommand_bouns');
                        if(!empty($recommand_bouns)) $config_values['recommand_bouns'] = $recommand_bouns;

                        //V2.0
                        $v2_open_date = I('v2_open_date');
                        if(!empty($v2_open_date)) $config_values['v2_open_date'] = $v2_open_date;

                        $manager_bouns_v2 = I('manager_bouns_v2');
                        if(!empty($manager_bouns_v2)) $config_values['manager_bouns_v2'] = $manager_bouns_v2;

                        //出局倍数
                        $stop_all_bouns = I('stop_all_bouns');
                        if(!empty($stop_all_bouns)) $config_values['stop_all_bouns'] = $stop_all_bouns;

                        for ($level=1; $level <=15 ; $level++) { 
                            $manager_bouns_v = I('manager_level_'.$level);
                            if(!empty($manager_bouns_v)) $config_values['manager_level_'.$level] = $manager_bouns_v;
                        }
                    } elseif ($key=='entrepreneur') {
                        //创业分红配置

                        //停止级别不包含
                        $stop_level = I('stop_level');
                        if(!empty($stop_level)) $config_values['stop_level'] = $stop_level;

                        //奖励比例
                        $bouns = I('bouns');
                        if(!empty($bouns)) $config_values['bouns'] = $bouns;
                    }
                    $vv=serialize($value);
                    $update= M("boss_config")->where(['key'=>$key])->save(['value'=>serialize($config_values)]);
                    if($update===false) {
                        $this->error("修改失败");
                    } else{
                        $this->success("修改成功");
                    }
                } else {
                    $this->assign("key",$key);
                    $this->assign("config_values",$config_values);
                    $this->display($key);
                }
            }else{
                $this->error("参数错误");
            }
        }else{
            $this->error("参数错误");
        }
    }

    //收银台配置
    public function pay_config()
    {
        $this->display();
    }

    //查询下级
    public function pid_level(){
        $member_id = intval(I('member_id'));
        $list = M()->query("select a.member_id,b.level as pid_level,a.level from yang_member_bind a left join yang_boss_plan_info b on a.member_id=b.member_id WHERE a.child_id=".$member_id." ORDER BY a.level desc");

        if($list) {
            foreach ($list as $key => $value) {
                echo $value['member_id']." 上级等级:".$value['pid_level']."  层级:".$value['level']."<br />";
            }
        }
    }

    public function child_level() {
        $member_id = intval(I('member_id'));
        $is_filter = intval(I('filter'));

        $join = '';
        if($is_filter) $join = ' and child_level>0 '; 
        $list = M()->query("select child_id,child_level from yang_member_bind WHERE member_id=".$member_id.$join." ORDER BY level desc");

        if($list) {
            foreach ($list as $key => $value) {
                echo $value['child_id']." ".$value['child_level']."<br />";
            }
        }
    }

    //分红详情
    public function bouns_detail() {
        $member_id = intval(I('member_id'));
        $today = I('today');
        if(empty($today)) $today = date('Y-m-d');

        $today = $this->same_day($today);
        //当天总分红和波比
        $bouns_total = M('boss_bouns_total')->where(['add_time'=>$today['start_time']])->find();
        $bouns_total['total_pay_number'] = M('boss_plan_buy')->where(['status'=>1])->sum('pay_number');
        $bouns_total['base_num'] -= $bouns_total['num5'];
        $bouns_total['bouns_num'] += $bouns_total['num5'];     

        if(!empty($member_id)) {
            //下级用户总数量
            $child_sum = M('member_bind')->where(['member_id'=>$member_id])->count();

            // //团队总充币
            // $chongbi_sum = M()->query('SELECT sum(num) as num from yang_tibi WHERE (to_member_id in (select child_id as member_id from yang_member_bind WHERE member_id='.$member_id.') or to_member_id in(select member_id from yang_member_bind where child_id='.$member_id.') or to_member_id='.$member_id.') and transfer_type="1"');

            // //团队总提币
            // $tibi_sum = M()->query('SELECT sum(num) as num from yang_tibi WHERE (from_member_id in (select child_id as member_id from yang_member_bind WHERE member_id='.$member_id.') or from_member_id in(select member_id from yang_member_bind where child_id='.$member_id.') or from_member_id='.$member_id.') and transfer_type="1" and status=1');

            // //团队总收益
            // $bouns_team_num = M()->query("SELECT sum(num) as num from yang_boss_bouns_log WHERE receive_status=1 and  (member_id in (select child_id as member_id from yang_member_bind WHERE member_id=".$member_id.") or member_id in(select member_id from yang_member_bind WHERE child_id=".$member_id.") or member_id=".$member_id.') limit 1');

            // //团队总入金
            // $pay_number = M()->query("SELECT sum(pay_number) as num from yang_boss_plan_buy WHERE status=1 and  (member_id in (select child_id as member_id from yang_member_bind WHERE member_id=".$member_id.") or member_id in(select member_id from yang_member_bind WHERE child_id=".$member_id.") or member_id=".$member_id.') limit 1');

            // //瑞波金管理员充值
            // $xrpj_chongzhi = M()->query("SELECT sum(money) as num from yang_pay WHERE type=7 and  (member_id in (select child_id as member_id from yang_member_bind WHERE member_id=".$member_id.") or member_id in(select member_id from yang_member_bind WHERE child_id=".$member_id.") or member_id=".$member_id.') limit 1');


            //团队总充币
            // $chongbi_sum = M()->query('SELECT sum(num) as num from yang_tibi WHERE (to_member_id in (select child_id as member_id from yang_member_bind WHERE member_id='.$member_id.') or to_member_id in(select member_id from yang_member_bind where child_id='.$member_id.') or to_member_id='.$member_id.') and transfer_type="1"');

            // //团队总提币
            // $tibi_sum = M()->query('SELECT sum(num) as num from yang_tibi WHERE (from_member_id in (select child_id as member_id from yang_member_bind WHERE member_id='.$member_id.') or from_member_id in(select member_id from yang_member_bind where child_id='.$member_id.') or from_member_id='.$member_id.') and transfer_type="1" and status=1');

            // //团队总收益
            // $bouns_team_num = M()->query("SELECT sum(num) as num from yang_boss_bouns_log WHERE receive_status=1 and  (member_id in (select child_id as member_id from yang_member_bind WHERE member_id=".$member_id.") or member_id in(select member_id from yang_member_bind WHERE child_id=".$member_id.") or member_id=".$member_id.') limit 1');

            // //团队总入金
            // $pay_number = M()->query("SELECT sum(pay_number) as num from yang_boss_plan_buy WHERE status=1 and  (member_id in (select child_id as member_id from yang_member_bind WHERE member_id=".$member_id.") or member_id in(select member_id from yang_member_bind WHERE child_id=".$member_id.") or member_id=".$member_id.') limit 1');

            // //瑞波金管理员充值
            // $xrpj_chongzhi = M()->query("SELECT sum(money) as num from yang_pay WHERE type=7 and  (member_id in (select child_id as member_id from yang_member_bind WHERE member_id=".$member_id.") or member_id in(select member_id from yang_member_bind WHERE child_id=".$member_id.") or member_id=".$member_id.') limit 1');



            //充币
            $chongbi_sum = M()->query('SELECT sum(num) as num from yang_tibi WHERE to_member_id='.$member_id.' and transfer_type="1"');
            //总提币
            $tibi_sum = M()->query('SELECT sum(num) as num from yang_tibi WHERE from_member_id='.$member_id.' and transfer_type="1" and status=1');
            //总收益
            $bouns_team_num = M()->query("SELECT sum(num) as num from yang_boss_bouns_log WHERE receive_status=1 and  member_id=".$member_id.' limit 1');
            //总入金
            $pay_number = M()->query("SELECT sum(pay_number) as num from yang_boss_plan_buy WHERE status=1 and  member_id=".$member_id.' limit 1');
            //管理员充值
            $xrpj_chongzhi = M()->query("SELECT sum(money) as num from yang_pay WHERE type=7 and member_id=".$member_id.' limit 1');
            $tibi_total = [
                'chongbi_sum' => !empty($chongbi_sum)&&!empty($chongbi_sum[0]['num']) ? $chongbi_sum[0]['num'] : 0,
                'tibi_sum' => !empty($tibi_sum)&&!empty($tibi_sum[0]['num']) ? $tibi_sum[0]['num'] : 0,
                'pay_number' => !empty($pay_number)&&!empty($pay_number[0]['num']) ? $pay_number[0]['num'] : 0,
                'bouns_team_num' => !empty($bouns_team_num)&&!empty($bouns_team_num[0]['num']) ? $bouns_team_num[0]['num'] : 0,
                'xrpj_chongzhi' => !empty($xrpj_chongzhi)&&!empty($xrpj_chongzhi[0]['num']) ? $xrpj_chongzhi[0]['num'] : 0,
                'child_sum' => $child_sum,
            ];
            $this->assign('tibi_total',$tibi_total);

            //昨日已领取的收益
            $receive_log = M()->query('SELECT type,sum(num) as num from yang_boss_bouns_log WHERE member_id='.$member_id.' and add_time='.$today['start_time'].' and receive_status=1 GROUP BY type;');
            $this->assign('receive_log',$receive_log);

            //用户下级昨日入金记录
            $buy_log = M()->query("SELECT a.*,b.name,b.phone,b.email,c.name as pid_name,c.phone as pid_phone,c.email as pid_email,d.name as pay_name,d.phone as pay_phone,d.email as pay_email from yang_boss_plan_buy a left join yang_member b on a.member_id=b.member_id left join yang_member c on a.pid=c.member_id left join yang_member d on a.pay_id=d.member_id  WHERE (a.member_id in (select child_id as member_id from yang_member_bind WHERE member_id=".$member_id.") or a.member_id=".$member_id.") and a.add_time BETWEEN ".$today['yestoday_start']." and ".$today['yestoday_stop']." and a.status=1 order by a.id asc");
            $third_ids = array_column($buy_log, 'id');
            
            //昨日获取到的收益详情
            $table_name = 'yang_boss_bouns_detail'.$today['today'];
            $table_exist = M()->query('show tables like "'.$table_name.'"');
            $bouns_log_total = [];
            if($table_exist && !empty($third_ids)) {
                $third_ids = implode(',',$third_ids);
                $bouns_log = M()->query('select * from '.$table_name.' where third_id in('.$third_ids.')');
                $bouns_log_total = [];
                foreach ($bouns_log as $key => $value) {
                    if(!isset($bouns_log_total[$value['third_id']])) $bouns_log_total[$value['third_id']] = [];
                    $bouns_log_total[$value['third_id']][] = $value;
                }
            }

            $detail_sum = [];
            foreach ($buy_log as $key => &$log) {
                $log['detail'] = '';
                $log['self'] = '';
                if(empty($log['phone'])) $log['phone'] = $log['email'];
                if(empty($log['pid_phone'])) $log['pid_phone'] = $log['pid_email'];
                if(empty($log['pay_phone'])) $log['pay_phone'] = $log['pay_email'];
                if($log['pid']==$member_id) {
                    if(!isset($detail_sum[5])) {
                        $detail_sum[5] = $log['pay_number'] * 0.1;
                    } else {
                        $detail_sum[5] += $log['pay_number'] * 0.1;
                    }
                }

                $bouns_num = 0;
                if(isset($bouns_log_total[$log['id']])){
                    $detail = '';
                    foreach ($bouns_log_total[$log['id']] as $value1) {
                        $bouns_num += $value1['num'];
                        $detail_info = '';
                        if($value1['type']==5) {
                            $detail_info .= '用户:'.$value1['member_id'].' 分红:'.$value1['num'];
                            $detail_info .= ' 百分比:'.$value1['profit'];
                            $detail_info .= " 推荐奖励<br>";
                        } if($value1['type']==6) {
                            $detail_info .= '用户:'.$value1['member_id'].' 分红:'.$value1['num'];
                            $detail_info .= ' 百分比:'.$value1['profit'];
                            $detail_info .= " 社区奖励<br>";
                        } elseif($value1['type']==7){
                            $detail_info .= '用户:'.$value1['member_id'].' 分红:'.$value1['num'];
                            $detail_info .= ' 百分比:'.$value1['profit'];
                            $detail_info .= " 平级奖励<br>";
                        } elseif ($value1['type']==8) {
                            $detail_info .= '用户:'.$value1['member_id'].' 分红:'.$value1['num'];
                            $detail_info .= ' 百分比:'.$value1['profit'];
                            $detail_info .= " 管理奖励<br>";
                        }elseif ($value1['type']==10) {
                            $detail_info .= '用户:'.$value1['member_id'].' 分红:'.$value1['num'];
                            $detail_info .= ' 百分比:'.$value1['profit'];
                            $detail_info .= " 创业奖励<br>";
                        }
                        $detail .= $detail_info;
                        if($value1['member_id']==$member_id) {
                            $log['self'] .= $detail_info;
                            if(!isset($detail_sum[$value1['type']])) {
                                $detail_sum[$value1['type']] = $value1['num'];
                            } else {
                                $detail_sum[$value1['type']] += $value1['num'];
                            }
                        }
                    }
                    $log['detail'] = $detail;
                }
                $log['percent'] = keepPoint($bouns_num/$log['pay_number']*100,2);
            }
            $this->assign('buy_log',$buy_log);
            $this->assign('detail_sum',$detail_sum);
        }
        $this->assign('bouns_total',$bouns_total);
        $this->display();
    }

    private function same_day($today)
    {
        $today_unix = strtotime($today);
        $same_day = ['today'=>date('Ymd',$today_unix),'start_time' => $today_unix, 'end_time' => ($today_unix+86400-1),'yestoday_start'=>($today_unix-86400),'yestoday_stop'=>($today_unix-1)];
        return $same_day;
    }

    //社区长审核
    public function leader_apply() {
        $model = M('boss_plan_leader');
        if (IS_POST) {
            $id = I("post.id");
            $result = I("post.result", 1, 'intval');
            $info = $model->where(['id' => $id])->select();
            if (empty($info)) $this->ajaxReturn(['Code' => 0, 'Msg' => "操作失败"]);

            $r = $this->verify($result, $info);
            $this->ajaxReturn(['Code' => $r['code'], 'Msg' => $r['message']]);
        } else {
            $status = I("get.status", 0, 'intval');
            $where['a.status'] = $status;
            $type = $where['a.status'];

            $member_id = I("get.member_id", 0, 'intval');
            if (!empty($member_id)) $where['a.member_id'] = $member_id;

            $email = I('email');
            $name = I('name');
            $phone = I('phone');

            if (!empty($email)) $where['member.email'] = $email;
            if (!empty($name)) $where['member.name'] = $name;
            if (!empty($phone)) $where['member.phone'] = $phone;

            $count = $model->alias("a")->join("left join " . C("DB_PREFIX") . "member as member on member.member_id = a.member_id")->where($where)->count();// 查询满足要求的总记录数
            $Page = new Page($count, 25);// 实例化分页类 传入总记录数和每页显示的记录数(25)

            //给分页传参数
            setPageParameter($Page, ['status' => $type]);

            $show = $Page->show();// 分页显示输出
            // 进行分页数据查询 注意limit方法的参数要使用Page类的属性

            //$field = "member.member_id,member.email,member.phone,member.name,member.idcard,verify_file.pic1,verify_file.pic2,verify_file.pic3,verify_file.addtime";
            $field = "member.member_id,member.email,member.phone,a.addtime";
            $list = $model->alias("a")
                ->join("left join " . C("DB_PREFIX") . "member as member on member.member_id = a.member_id")
                ->field($field)
                ->where($where)
                ->order("id desc")
                ->limit($Page->firstRow . ',' . $Page->listRows)->select();
            $this->assign('type', $type);
            $this->assign('list', $list);
            $this->assign('page', $show);// 赋值分页输出
            $this->display(); // 输出模板
        }
    }

    private function verify($status, $info = array()){ 
        $r['code'] = 0;
        $r['message'] = "审核失败";
        if (!in_array($status, [1, 3])) {
            $r['code'] = 0;
            $r['message'] = "参数错误";
            return $r;
        }
        try {
            M()->startTrans();
            foreach ($info as $key => $val) {
                $update_data1 = M('boss_plan_leader')->where(['id' => $val['id']])->setField('status',$status);
                if (!$update_data1) {
                    throw new Exception('审核失败');
                }
                if ($status == 1) { 
                    $update_data2 = M('boss_plan_info')->where(['member_id' => $val['member_id']])->setField('is_leader',1);
                    if (!$update_data2) {
                        throw new Exception('审核失败');
                    }
                }
            }
            M()->commit();
            $r['message'] = "审核成功";
            $r['code'] = 1;
        } catch (Exception $e) {
            M()->rollback();
            $r['message'] = $e->getMessage();
        }

        return $r;

    }
}