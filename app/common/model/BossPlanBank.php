<?php
// +------------------------------------------------------
// | Author: 黄树标 <18899716854@qq.com>
// +------------------------------------------------------
namespace app\common\model;
use think\Lang;
use think\Model;
use think\Exception;
use think\Db;

class BossPlanBank extends Base {

    public static $ratio_9=0.97;//比率值
    public static $ratio_1=0.03;//比率值
    public static $gac_currency='GAC';//币种

    /**
     *时间区间
     * @param int $type
     */
    public static function time_interval($type=1){
        if($type==1){
            //$time=date('Y-m-d',strtotime("-1 day"));//昨天时间
            $time=date('Y-m-d');
        }elseif($type==2){
            //$time=date('Y-m-d');//今天时间
            $time=date('Y-m-d',strtotime("+1 day"));//昨天时间
        }elseif($type==3){//上周开始时间
            $time=date('Y-m-d H:i:s',mktime(0,0,0,date('m'),date('d')-date('w')+1,date('Y')));
        }elseif($type==4){//上周结束时间
            $time=date('Y-m-d H:i:s',mktime(0,0,0,date('m'),date('d')-date('w')+8,date('Y')));
        }
        return $time;
    }
    //XRP 社區管理計劃
    static function plan_info($member_id){
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result']=[];
        if($member_id<=0&&!is_numeric($member_id)){
            return $r;
        }
        $info=Db::name('boss_plan_info')->field('votes,level,num,xrpj_num,xrpz_num')->where(['member_id'=>$member_id])->find();
        if(empty($info)){
            $info['votes']=0;
            $info['level']=0;
            $info['num']=0;
        }

        //1 基础分红 2 增加分红 3 一级分红
             $bouns1=self::plan_list($member_id,0,[1,2,3,4],'member_id,type,sum(num) as number','type',self::time_interval(1),self::time_interval(2));
             $bouns1=empty($bouns1)?[]:$bouns1;


        // 4 互助分红
//         $bouns2=self::plan_list($member_id,0,[4],'member_id,type,sum(num) as number','type',self::time_interval(3),self::time_interval(4));
//         $bouns2=empty($bouns2)?[]:$bouns2;
           $today_type="";
         if(!empty($bouns1)||!empty($bouns2)){
             $today_type=1;//判断是否可领取
         }

        // 5推荐奖励 6社区奖励 7平级奖励 8管理奖励
            $reward1=self::plan_list($member_id,1,[5,6,7,8,9,10],'member_id,type,sum(num) as number','type');

            $reward1=empty($reward1)?[]:$reward1;
            //$count_arr = array_merge($bouns1,$bouns2,$reward1);
            $count_arr = array_merge($bouns1,$reward1);

        if(!empty($count_arr)){
            $count_arr=array_column($count_arr,'number','type');
            if(!empty($count_arr[9])){
                if(empty($count_arr[6])){
                    $count_arr[6]=0;
                }
                $count_arr[6]=$count_arr[6]+$count_arr[9];
                unset($count_arr[9]);
            }
            if(!empty($count_arr[10])){
                if(empty($count_arr[5])){
                    $count_arr[5]=0;
                }
                $count_arr[5]=$count_arr[5]+$count_arr[10];
                unset($count_arr[10]);
            }
            $people_number=Db::name('boss_plan')->where(['pid'=>$member_id,'status'=>3])->count();
            if($people_number>0){
                $count_arr[9]=strval(floatval($people_number));
            }
            $total_arr=[];
            foreach ($count_arr as $key=>$val){
                $total_arr[$key]['type']=$key;
                $total_arr[$key]['number']=$val;
            }
            $count_arr=array_values($total_arr);
        }

        //我的社員总人数

        $plan_count=Db::name('boss_plan_count')->where(['member_id'=>$member_id])->select();
        if(empty($plan_count)){
            Db::name('boss_plan_count')->insert(array('member_id'=>$member_id));
        }
        $switch_where=[
            'base_boss_bonus_switch',
            'add_boss_bonus_switch',
            'first_boss_bonus_switch',
            'mutual_boss_bonus_switch',

        ];

        $bonus_switch=2;//用户的领取开关初始值
        $old_user_switch=2;//开关初始值
        $switch_list=Db::name('boss_config')->where(['key'=>['in',$switch_where],'value'=>1])->select();
        $boss_old_user_remain_money_switch=Db::name('boss_config')->where(['key'=>'boss_old_user_remain_money_switch'])->find();
        $boss_plan="";
        $icon='https://ruibooss.oss-cn-hongkong.aliyuncs.com/article_pics/2019-04-15/ca5fdc784ad34267.png';
        $confirm_time=Db::name('boss_config')->field('value')->where(['key'=>'boss_old_user_confirm_time'])->find();

            $where_boss_plan['member_id']=$member_id;
            $where_boss_plan['confirm_time']=array('<',$confirm_time['value']);
            $boss_plan=Db::name('boss_plan')->where($where_boss_plan)->find();
            if(!empty($boss_plan)){
                $icon='https://ruibooss.oss-cn-hongkong.aliyuncs.com/article_pics/2019-04-15/1f0a8b418bf74821.png';
            }


        if(!empty($switch_list)){


            $switch_arr=array_column($switch_list,'value','key');
            //判断旧用户的剩余可领取开关是否显示
            if(!empty($boss_old_user_remain_money_switch)&&!empty($boss_plan)){
                $old_user_switch=1;

            }
            //判断用户的领取开关是否显示
            if(!empty($switch_arr)){
                $bonus_switch=1;
            }
        }

        //用户总已领取分红
        $currency = Db::name('currency')->field('currency_id,currency_name')->where(['currency_mark'=>self::$gac_currency])->find();
        $user_total=Db::name('currency_user')->field('remaining_principal')->where(['member_id'=>$member_id,'currency_id'=>$currency['currency_id']])->find();
        $r['code'] = SUCCESS;
        $r['message'] = lang("lan_data_success");
        $info['xrpj_num']=empty($info['xrpj_num'])?'0':strval($info['xrpj_num']);
        $info['xrpz_num']=empty($info['xrpz_num'])?'0':strval($info['xrpz_num']);
        $info['num']=empty($info['num'])?'0':strval(floatval($info['num']));
        $r['result']['info']=$info;
        $r['result']['bonus_switch']=$bonus_switch==2?2:1;//用户的领取开关初始值
        $r['result']['icon']=$icon;//新旧用户图标标识
        $r['result']['remain_money_name']=lang('lan_ser_remaining_principal');
        $r['result']['lan_ser_remaining_principal_name']=lang('lan_gac_welfare_transfer_name');
        $r['result']['remain_money']=empty($user_total['remaining_principal'])?'0.00':strval($user_total['remaining_principal']);//用户GAC福利
        $r['result']['user_remain_money_switch']=$old_user_switch==2?2:1;//旧用户的剩余可领取开关
        $r['result']['today_type']=empty($today_type)?2:1;
        $r['result']['bouns']=self::plan_info_config($count_arr);

        return $r;
    }
    //分红详情信息
    static function bouns_detail($type=1,$member_id){
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result']=[];
        $button= lang("receive_button");
        $color_type=1;
         if(!in_array($type,[1,2,3,4,5,6,7,8])){
             return $r;
         }

         $info=Db::name('boss_plan_info')->field('push_num,num')->where(['member_id'=>$member_id])->find();
         if(empty($info)){
             $info['push_num']='0';
             $info['num']='0';
         }

         $count=Db::name('boss_plan_count')->where(['member_id'=>$member_id])->find();
         if(empty($count)){
             $count['num1']=0;
             $count['num2']=0;
             $count['num3']=0;
             $count['num4']=0;
             $count['num5']=0;
             $count['num6']=0;
             $count['num7']=0;
             $count['num8']=0;
             $count['total_profit']=0;
         }
        if(in_array($type,[1,2,3,4,5])){
            $log=self::plan_list($member_id,'',array($type),"receive_status,level,child_num,profit,sum(num) as num",$group='type',self::time_interval(1),self::time_interval(2),'find');
        }elseif(in_array($type,[6,7,8])){
            if($type==6){
                $type_s=array(6,9);
            }else{
                $type_s[]=$type;
            }

            $log=self::plan_list($member_id,'',$type_s,"receive_status,level,child_num,profit,sum(num) as num",$group='type',self::time_interval(3),self::time_interval(4),'find');
            $level=Db::name('boss_plan_info')->field('level')->where(['member_id'=>$member_id])->find();
        }

        if(empty($log)){

            $color_type=2;
            $log['num']='0';
            if(in_array($type,[1,2,4])){
                $log['profit']=0;//当前收益率
            }elseif(in_array($type,[3,5])){
                $log['child_num']='0';
            }
            if($type=4){
                $log['child_num']='0';
            }
            $log['receive_status']=0;
        }


        $move_bar1=$count['total_profit']>0?$count['total_profit']:0;
        $people_number=Db::name('boss_plan')->where(['pid'=>$member_id,'status'=>3])->count();

        $config=self::boss_config();
        $info['bar_type']=2;
        $info['text']="";
        $move_bar2 = 0;
        $base_bar2=$info['num']*$config['stop_bouns']['no_invit'];//静态进度条结束
        switch ($type){
            case 1:
                if($log['receive_status']==1){
                    $color_type=2;
                    $button= lang("have_receive");
                }

                if($people_number<=0&&$type==1){//静态进度与动态进度
                    $info['bar_type']=1;
                }

                $count_profit=$count['num1'];//基础总分红收益

                $base_bar2=$info['num']*$config['stop_bouns']['no_invit'];//静态进度条结束

                $move_bar2=$info['num']*$config['stop_bouns']['invit'];//动态进度条结束
                if($count['total_profit']>=$base_bar2){

                    $button= lang("end_base_button");
                    $color_type=2;
                    $info['text']=self::plan_content_config($type);
                }

                break;
            case 2:
                if($log['receive_status']==1){
                    $color_type=2;
                    $button= lang("have_receive");
                }
                if($people_number>0){//动态进度
                    $info['bar_type']=2;
                }
                $count_profit=$count['num2'];//增加总分红收益
                $move_bar2=$info['num']*$config['stop_bouns']['invit'];//动态进度条结束
                if($count['total_profit']>=$move_bar2){
                    $color_type=2;
                    $button= lang("end_add_button");
                    $info['text']=self::plan_content_config($type);
                }
                break;
            case 3:
                if($log['receive_status']==1){
                    $color_type=2;
                    $button= lang("have_receive");
                }
                if($people_number>0){//动态进度
                    $info['bar_type']=2;
                }
                $count_profit=$count['num3'];//一级总分红收益
                $move_bar2=$info['num']*$config['stop_bouns']['invit'];//动态进度条结束
                if($count['total_profit']>=$move_bar2){
                    $color_type=2;
                    $button= lang("end_one_button");
                    $info['text']=self::plan_content_config($type);
                }

                break;
            case 4:
                if($log['receive_status']==1){
                    $color_type=2;
                    $button= lang("have_receive");
                }
                if($people_number>0){//动态进度
                    $info['bar_type']=2;
                }
                $count_profit=$count['num4'];//互助总分红收益
                $move_bar2=$info['num']*$config['stop_bouns']['invit']>0?$info['num']*$config['stop_bouns']['invit']:0;//动态进度条结束
                if($count['total_profit']>=$move_bar2){
                    $color_type=2;
                    $info['text']=self::plan_content_config($type);
                    $button= lang("end_mutual_button");
                }
                break;
            case 5:

                if($log['receive_status']==1){
                    $color_type=2;
                    $button= lang("have_receive");
                }elseif(empty($log)){
                    $color_type=2;
                    $button= lang("reward_button1");
                }
                $count_profit=$count['num5'];//推薦獎勵总收益
                break;
            case 6:
                $button= lang("reward_button3");
                if($log['receive_status']==1){
                    $color_type=2;
                    $button= lang("have_receive");
                }elseif(empty($log)){
                    $color_type=2;
                    $button= lang("reward_button2");
                }
                $count_profit=$count['num6'];//社區獎勵总收益

                break;
            case 7:
                $button= lang("reward_button3");
                if($log['receive_status']==1){
                    $color_type=2;
                    $button= lang("have_receive");
                }elseif(empty($log)){
                    $color_type=2;
                    $button= lang("reward_button2");
                }
                $count_profit=$count['num7'];//平級獎勵总收益
                break;
            case 8:
                $button= lang("reward_button3");
                if($log['receive_status']==1){
                    $color_type=2;
                    $button= lang("have_receive");
                }elseif(empty($log)){
                    $color_type=2;
                    $button= lang("reward_button2");
                }
                $count_profit=$count['num8'];//管理獎勵总收益
                break;

        }

        if($people_number<=0){
            //$info['bar_type']=1;
            $move_bar2=$base_bar2;
        }
        $info['num']=strval(floatval($info['num']));
        $info['profit_rate']=empty($log['profit'])?'0':strval(floatval($log['profit']*1000));//当前收益率
        $info['today_profit']=strval(floatval($log['num']));//今天收益或者本周收益
        $info['count_profit']=$count_profit?strval(floatval($count_profit)):'0';//总分红收益
        $info['base_bar1']=empty($move_bar1)?'0':strval($move_bar1);//静态进度条开始
//        $info['base_bar1']=empty($count['num1'])?'0':strval($count['num1']);//静态进度条开始
        $info['base_bar2']=empty($base_bar2)?'0':strval(floatval($base_bar2));//静态进度条结束
        $info['move_bar1']=empty($move_bar1)?'0':strval($move_bar1);//动态进度条开始
        $info['move_bar2']=empty($move_bar2)?'0':strval(floatval($move_bar2));//动态进度条结束
        $info['level']=empty($level['level'])?"LV0":"LV".$level['level'];//等级
        $info['child_num']=empty($log['child_num'])?'0':strval(floatval($log['child_num']));//社员昨日有效收益

        $r['code'] = SUCCESS;
        $r['message'] = lang("lan_data_success");
        $info['button'] = $button;
        $info['color_type'] = $color_type;
        $r['result']=$info;
        return $r;
    }
    //老板计划组合数据
    static function plan_list($member_id,$receive_status="",$arr_type=array(),$field="*",$group='',$stat_time='',$end_time='',$state='select'){
        $where['member_id']=$member_id;

        if(is_numeric($receive_status)){
            $where['receive_status']=$receive_status;
        }
        $where['type']=array('in',$arr_type);
            $model_bouns=Db::name('boss_bouns_log');
            $model_bouns->field($field);
            $model_bouns->where($where);
            if(!empty($stat_time)){
                $model_bouns->whereTime('add_time', '>=',$stat_time);
            }
           if(!empty($end_time)){
               $model_bouns->whereTime('add_time', '<',$end_time);
           }
            $model_bouns->group($group);
            $model_bouns->order('add_time desc');
            if($state=='select'){
                $bouns=$model_bouns->select();
            }else{
                $bouns=$model_bouns->find();

            }

   // echo Db::name('boss_bouns_log')->getLastSql();die;
      return empty($bouns)?[]:$bouns;

    }
    //分红日志
    static function bouns_log($member_id,$type,$page=1,$page_size=10){
       $r['message'] = lang("lan_not_data");
        $types=0;
       if($type==6){
           $types=6;
           $type=array('in',[6,9]);
       }
        $log=Db::name('boss_bouns_log')
            ->where(['receive_status'=>1,'type'=>$type,'member_id'=>$member_id])
            ->order('receive_time desc')
            ->limit(($page - 1) * $page_size, $page_size)
            ->select();
        //echo db('boss_bouns_log')->getlastsql();die;
        $log_arr=array();
        if($log){

            foreach ($log as $key =>$val){
                $log_arr[$key]['icon']=lang("lan_boss_icon");
                if($type==1){
                    $log_arr[$key]['title']=lang("lan_boss_title_log1");
                    $log_arr[$key]['profit']=lang("lan_boss_title_log1").lang("lan_boss_rate_log")."：".floatval($val['profit']*1000)."‰";

                }elseif ($type==2){
                    $log_arr[$key]['title']=lang("lan_boss_title_log2");
                    $log_arr[$key]['profit']=lang("lan_boss_title_log2").lang("lan_boss_rate_log")."：".floatval($val['profit']*1000)."‰";
                }elseif ($type==3){
                    $log_arr[$key]['title']=lang("lan_boss_title_log3");
                    $log_arr[$key]['profit']=lang("lan_boss_profit_log5")."：".floatval($val['child_num'])."XRP";
                    $log_arr[$key]['num']="+".floatval($val['num'])."XRP";
                }elseif ($type==4){
                    $log_arr[$key]['title']=lang("lan_boss_title_log4");
                    $log_arr[$key]['profit']="";
                    $log_arr[$key]['num']="+".floatval($val['num'])."XRP";
                }elseif ($type==5){
                    $log_arr[$key]['title']=lang("lan_boss_title_log5");
                    $log_arr[$key]['profit']=lang("lan_boss_achievement_log")."：".floatval($val['child_num']);
                }elseif ($types==6){
                    $log_arr[$key]['title']=lang("lan_boss_title_log6");
                    $log_arr[$key]['profit']=lang("lan_boss_title_log6")."LV".$val['level'].lang("lan_boss_block_log7");
                }elseif ($type==7){
                    $log_arr[$key]['title']=lang("lan_boss_title_log7");
                    $log_arr[$key]['profit']=lang("lan_boss_title_log7")."：LV".$val['level'].lang("lan_boss_block_log7");
                }elseif ($type==8){
                    $log_arr[$key]['title']=lang("lan_boss_title_log8");
                    $log_arr[$key]['profit']=lang("lan_boss_title_log8")."：LV".$val['level'].lang("lan_boss_block_log8");
                }

                $log_arr[$key]['num']="+".floatval($val['num'])."XRP";

                $log_arr[$key]['in_num']=lang("lan_boss_ticket_log")."：".$val['in_num']."XRP";

                $log_arr[$key]['receive_time']=date('Y-m-d H:i:s',$val['receive_time']);
            }
            $r['message'] = lang("lan_data_success");
        }

        $r['result']=empty($log)?[]:$log_arr;
        $r['code'] = SUCCESS;
        return $r;
    }
    //领取分红
    static function receive_bouns($member_id,$type){
        $r['result']=[];
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $ratio=self::boss_ratio($member_id);
        $boss_plan = Db::name('boss_plan')->field('lock_status')->where(['member_id'=>$member_id])->find();
        if($boss_plan['lock_status']==2){
            $r['message'] = lang("lan_lock_user");
            return $r;
        }
       if(!in_array($type,[1,2,3,4,5,6,7,8])){
           return $r;
       }
        if(in_array($type,[1,2,3,4,5])){
            $log=self::plan_list($member_id,0,$arr_type=array($type),"id,member_id,num,type,receive_status",'',self::time_interval(1),self::time_interval(2));
        }
        if(in_array($type,[6,7,8])){
            if($type==6){
                $type_s=[6,9];
            }else{
                $type_s[]=$type;
            }
            $log=self::plan_list($member_id,0,$arr_type=$type_s,"id,member_id,num,type,receive_status",'',self::time_interval(3),self::time_interval(4));
        }

        if(empty($log)){
            $r['code'] = ERROR2;
            $r['message'] = lang("lan_not_data");
            return $r;
        }

        $id_arr=array_column($log,'id');
        $receive_status=1;
        $num=0;
        foreach($log as $key=>$val){
            if($val['receive_status']==0){
                $receive_status=0;
            }
            $num=$num+$val['num'];
        }
        $config=self::boss_config();
        $count=Db::name('boss_plan_count')->field('total_profit')->where(['member_id'=>$member_id])->find();
        $info=Db::name('boss_plan_info')->field('num')->where(['member_id'=>$member_id])->find();
        $no_invit=$info['num']*$config['stop_bouns']['no_invit'];//静态限制收益
        $invit=$info['num']*$config['stop_bouns']['invit'];//动态限制收益
        if(is_numeric($count['total_profit'])){
            $total_profit=$count['total_profit']+$num;
        }else{
            $total_profit=0;
        }
        if(in_array($type,[1])){//校验静态限制收益
            if($total_profit>$no_invit){
                $r['message'] = lang("lan_exceeding_revenue");
                $r['code'] = ERROR10;
                return $r;
            }
        }elseif(in_array($type,[2,3,4])){//校验静态限制收益
            if($total_profit>$invit){
                $r['message'] = lang("lan_exceeding_revenue");
                $r['code'] = ERROR10;
                return $r;
            }
        }
        if($receive_status==1){
            $r['code'] = ERROR3;
            $r['message'] = lang("have_receive");
            return $r;
        }
        Db::startTrans();
        try{
            $update_log=Db::name('boss_bouns_log')->where(['id'=>['in',$id_arr]])->update(['receive_status'=>1,'receive_time'=>time()]);
            if(!$update_log){
                $r['code'] = ERROR4;
                throw new Exception(lang('receive_error'));
            }
            //添加瑞波钻百分之九十
            if($ratio['ratio_9']>0){
                $update_plan=Db::name('boss_plan_info')->where(['member_id'=>$member_id])->setInc('xrpz_num',$num*$ratio['ratio_9']);
                if(!$update_plan){
                    $r['code'] = ERROR5;
                    throw new Exception(lang('receive_error'));
                }
            }
            //添加创新区瑞波钻百分之十
           if($ratio['ratio_1']>0){
               $update_plan=Db::name('boss_plan_info')->where(['member_id'=>$member_id])->setInc('xrpz_new_num',$num*$ratio['ratio_1']);
               if(!$update_plan){
                   $r['code'] = ERROR5;
                   throw new Exception(lang('receive_error'));
               }
           }
            //分别：基礎分红总收益、增加分红总收益、一級分红总收益、推薦獎勵总收益、社區獎勵总收益、平級獎勵总收益、管理獎勵总收益
            $res=Db::name('boss_plan_count')->where(['member_id'=>$member_id])->setInc('num'.$type,$num);
            if(!$res){
                $r['code'] = ERROR6;
                throw new Exception(lang('receive_error'));
            }
            //总统计：分红总收益
            $res=Db::name('boss_plan_count')->where(['member_id'=>$member_id])->setInc('total_profit',$num);
            if(!$res){
                $r['code'] = ERROR7;
                throw new Exception(lang('receive_error'));
            }
            $date=time();
            //添加日志
            if($ratio['ratio_9']>0){
                $data1 =  ['l_member_id'=>$member_id,'l_value'=>$num*$ratio['ratio_9'],'l_time'=>$date,'l_title'=>self::bouns_log_config($type)['title'],'l_type'=>$type,'l_type_explain'=>self::bouns_log_config($type)['type_explain']];
                //收入日志
                $insert_xrp_log = Db::name('xrp_log')->insert($data1);
                if(!$insert_xrp_log){
                    $r['code'] = ERROR8;
                    throw new Exception(lang('lan_transfer_error'));
                }
            }

            //添加创新区日志
            if($ratio['ratio_1']>0){
                $data2 =  ['l_member_id'=>$member_id,'l_value'=>$num*$ratio['ratio_1'],'l_time'=>$date,'l_title'=>self::bouns_log_config($type)['title'],'l_type'=>$type,'l_type_explain'=>self::bouns_log_config($type)['type_explain']];
                //创新区收入日志
                $insert_xrp_log = Db::name('innovate_log')->insert($data2);
                if(!$insert_xrp_log){
                    $r['code'] = ERROR9;
                    throw new Exception(lang('lan_transfer_error'));
                }
            }

            $r['code'] = SUCCESS;
            $r['message']= lang("receive_succ");
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $r['message']=$e->getMessage();
        }
        return $r;
    }

    //一键领取分红
    static function one_receive($member_id){
        $r['result']=[];
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $ratio=self::boss_ratio($member_id);
        $boss_plan = Db::name('boss_plan')->field('lock_status')->where(['member_id'=>$member_id])->find();
        if($boss_plan['lock_status']==2){
            $r['message'] = lang("lan_lock_user");
            return $r;
        }
        $config=self::boss_config();
        $count=Db::name('boss_plan_count')->field('total_profit')->where(['member_id'=>$member_id])->find();
        if(empty($count['total_profit'])){
            $count['total_profit']=0;
        }
        $info=Db::name('boss_plan_info')->field('num')->where(['member_id'=>$member_id])->find();
        $no_invit=$info['num']*$config['stop_bouns']['no_invit'];//静态限制收益
        $invit=$info['num']*$config['stop_bouns']['invit'];//动态限制收益

        $check_log1=self::plan_list($member_id,0,array(1),"sum(num) as num",$group='type',self::time_interval(1),self::time_interval(2),'find');
        if(empty($check_log1)){
            $check_log1['num'] =0;
        }
        $check_log2=self::plan_list($member_id,0,array(2,3),"sum(num) as num",$group='type',self::time_interval(3),self::time_interval(4),'find');
        if(empty($check_log2)){
            $check_log2['num'] =0;
        }
        $log1=self::plan_list($member_id,0,$arr_type=array(1),"id,member_id,num,type",'',self::time_interval(1),self::time_interval(2));
        $log1=empty($log1)?[]:$log1;
        $log2=self::plan_list($member_id,0,$arr_type=array(2,3,4),"id,member_id,num,type",'',self::time_interval(1),self::time_interval(2));
        $log2=empty($log2)?[]:$log2;
        $log3=self::plan_list($member_id,0,$arr_type=array(5),"id,member_id,num,type",'',self::time_interval(1),self::time_interval(2));
        $log3=empty($log3)?[]:$log3;
        $log4=self::plan_list($member_id,0,$arr_type=array(6,7,8,9),"id,member_id,num,type",'',self::time_interval(3),self::time_interval(4));
        $log4=empty($log4)?[]:$log4;
        if($check_log1['num']>0){//校验静态限制收益
            $total_profit=$count['total_profit']+$check_log1['num'];
            if($total_profit>$no_invit){
                if(empty($log2)&&empty($log3)&&empty($log4)){
                    $r['code'] = ERROR10;
                    $r['message'] = lang("lan_exceeding_revenue");
                    return $r;
                }else{
                    $log1=[];
                }

            }
        }

        if($check_log2['num']>0){//动态限制收益
            $total_profit=$count['total_profit']+$check_log2['num'];
            if($total_profit>$invit){
                if(empty($log3)&&empty($log4)){
                    $r['code'] = ERROR10;
                    $r['message'] = lang("lan_exceeding_revenue");
                    return $r;
                }else{
                    $log2=[];
                }

            }
        }
        //合拼数组数据
        $log=array_merge($log1,$log2,$log3,$log4);
        //处理数组数据
        foreach($log as $key=>$val){
            if(empty($val)){
                unset($log[$key]);
            }
        }
        if(empty($log)){
            $r['code'] = ERROR2;
            $r['message'] = lang("have_receive");
            return $r;
        }
        //获取所有未领取数据数组ID
        $id_arr=array_column($log,'id');

        Db::startTrans();
        try{
             //修改成已领取
            $update_log=Db::name('boss_bouns_log')->where(['id'=>['in',$id_arr]])->update(['receive_status'=>1,'receive_time'=>time()]);
            if(!$update_log){
                $r['code'] = ERROR2;
                throw new Exception(lang('receive_error'));
            }
            //处理数组的瑞波钻
            $count_num=0;
            foreach ($log as $key =>$val){
                if(empty($arr[$val['type']])){
                    $arr[$val['type']]=$val['num'];
                }else{
                    $arr[$val['type']]=$arr[$val['type']]+$val['num'];
                }
                $count_num=$count_num+$val['num'];
            }

            //添加瑞波钻百分之九十
            if($ratio['ratio_9']>0){
                $update_plan=Db::name('boss_plan_info')->where(['member_id'=>$member_id])->setInc('xrpz_num',$count_num*$ratio['ratio_9']);
                if(!$update_plan){
                    $r['code'] = ERROR5;
                    throw new Exception(lang('receive_error'));
                }
            }

            //添加创新区瑞波钻百分之十
            if($ratio['ratio_1']>0){
                $update_plan=Db::name('boss_plan_info')->where(['member_id'=>$member_id])->setInc('xrpz_new_num',$count_num*$ratio['ratio_1']);
                if(!$update_plan){
                    $r['code'] = ERROR5;
                    throw new Exception(lang('receive_error'));
                }
            }

            $model_plan_count=db('boss_plan_count');
            $date=time();
            foreach ($arr as $k=>$v){
                //分别：基礎分红总收益、增加分红总收益、一級分红总收益、推薦獎勵总收益、社區獎勵总收益、平級獎勵总收益、管理獎勵总收益
                $res=$model_plan_count->where(['member_id'=>$member_id])->setInc('num'.$k,$v);
                if(!$res){
                    $r['code'] = ERROR4;
                    throw new Exception(lang('receive_error'));
                }
                if($ratio['ratio_9']>0){
                    $data1[] =['l_member_id'=>$member_id,'l_value'=>$v*$ratio['ratio_9'],'l_time'=>$date,'l_title'=>self::bouns_log_config($k)['title'],'l_type'=>$k,'l_type_explain'=>self::bouns_log_config($k)['type_explain']];
                }
               if($ratio['ratio_1']>0){
                   $data2[]= ['l_member_id'=>$member_id,'l_value'=>$v*$ratio['ratio_1'],'l_time'=>$date,'l_title'=>self::bouns_log_config($k)['title'],'l_type'=>$k,'l_type_explain'=>self::bouns_log_config($k)['type_explain']];
               }

            }
            //统计添加总收益
            $res_total_profit=$model_plan_count->where(['member_id'=>$member_id])->setInc('total_profit',$count_num);
            if(!$res_total_profit){
                $r['code'] = ERROR5;
                throw new Exception(lang('receive_error'));
            }
            //添加日志
            if(!empty($data1)){
                $insert_xrp_log = Db::name('xrp_log')->insertAll($data1);
                if(!$insert_xrp_log){
                    $r['code'] = ERROR6;
                    throw new Exception(lang('lan_transfer_error'));
                }
            }

            //添加创新区日志
            if(!empty($data2)){
                $insert_innovate_log = Db::name('innovate_log')->insertAll($data2);
                if(!$insert_innovate_log){
                    $r['code'] = ERROR7;
                    throw new Exception(lang('lan_transfer_error'));
                }
            }

            $r['code'] = SUCCESS;
            $r['message']= lang("receive_succ");
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $r['message']=$e->getMessage();
        }
         return $r;
    }


    //XRP 社區管理等级详情
    public function level($member_id){
        $r['code'] = SUCCESS;
        $r['message'] = lang("lan_data_success");
        $boss_plan_step=DB::name('boss_plan_step')->field('max(number) as number')->find();
        if(empty($boss_plan_step)){
            $boss_plan_step['number']=0;
        }
        $boss_bouns_week=DB::name('boss_bouns_week')->field('sum(num) as num,sum(child_num) as child_num')->where(['member_id'=>$member_id])->find();
        $boss_bouns_sum=empty($boss_bouns_week)?0:$boss_bouns_week['num']+$boss_bouns_week['child_num'];
        $boss_plan_info=DB::name('boss_plan_info')->field('next_leve_num,level')->where(['member_id'=>$member_id])->find();
        $next_leve_num=empty($boss_plan_info['next_leve_num'])?0:$boss_plan_info['next_leve_num'];
        $people_number=Db::name('boss_plan')->where(['pid'=>$member_id,'status'=>3])->count();
        $member=Db::name('member')->field('member_id,phone,nick,head,email')->where(['member_id'=>$member_id])->find();
        if(!empty($member)){
            if(empty($member['head'])){
                $member['head']=$this->default_head;
            }
            if(empty($member['nick'])){
                $member['nick']=substr($member['phone'],0,3).'****'.substr($member['phone'],-4);
                if(empty($member['phone'])){
                    $member['nick']=substr($member['email'],0,3).'****'.substr($member['email'],-7);;
                }
            }
            unset($member['email']);
            unset($member['phone']);
        }else{
            $member['head']="";
            $member['nick']="";
            $member['member_id']=0;
        }
        $member['level1']=$boss_plan_info['level']>0?"Lv".$boss_plan_info['level'].lang("lan_level_name".$boss_plan_info['level']):"Lv".$boss_plan_info['level'].lang("lan_no_level_name");
        $member['level2']="Lv".($boss_plan_info['level']+1).lang("lan_level_name".($boss_plan_info['level']+1));
        for($i=0;$i<6;$i++){
            $j=$i;
            $config=self::boss_config('V'.($j+1));
            $config['stat_level']="LV".$i;
            $config['end_level']="LV".($j+1);
            if($i==0){
                $config['end_recommend']=$config['recommend'];
                $config['stat_recommend']=$people_number>$config['end_recommend']?strval($config['end_recommend']):strval($people_number);
                $stat_money=$boss_bouns_sum>$config['money']?$config['money']:$boss_bouns_sum;
                $config['stat_money']=strval(floatval(($stat_money/$boss_plan_step['number'])));
                $config['end_money']=strval(floatval(($config['money']/$boss_plan_step['number'])));
                $config['title_level']=lang("lan_level_name".$i);
                $config['title_ticket_level']=lang("lan_ticket_level");

            }elseif($i>0){
                $config['end_recommend']=strval($config['recommend']);
                if($boss_plan_info['level']>$i){
                    $config['stat_recommend']=strval($config['end_recommend']);
                }elseif($boss_plan_info['level']==$i){
                    $config['stat_recommend']=strval($next_leve_num);
                }else{
                    $config['stat_recommend']='0';
                }
                $config['title_level']=lang("lan_level_name".$i).":(".lang("lan_need_to").$config['stat_level'].")";
            }
            unset($config['recommend']);
            unset($config['culture']);
            unset($config['money']);
            $arr[$i]=$config;

        }
        $r['result']['member']=$member;
        $r['result']['level_list']=$arr;
        return $r;

    }

    //社區管理計劃配置
    static function plan_info_config($arr=array()){
        $config=[
            //基礎分红
            '1'=>[ 'type'=>'1','number'=>'0','switch'=>1],
            //增加分红
            '2'=>['type'=>'2', 'number'=>'0','switch'=>1 ],
            //一級分红
            '3'=>['type'=>'3','number'=>'0','switch'=>1],
            //互助分红
            '4'=> ['type'=>'4','number'=>'0','switch'=>1],
            //推薦獎勵
            '5'=> ['type'=>'5', 'number'=>'0','switch'=>1],
            //社區獎勵
            '6'=>['type'=>'6', 'number'=>'0','switch'=>1],
            //平級獎勵
            '7'=> ['type'=>'7','number'=>'0','switch'=>1],
            //管理獎勵
            '8'=> ['type'=>'8','number'=>'0','switch'=>1],
            //我的社員： 30
            '9'=> ['type'=>'9','number'=>'0','switch'=>1],
        ];
        if(!empty($arr)){
            foreach ($arr as $key=>$val){
                $config[$val['type']]['number']=$val['number'];
            }
        }
        $switch_where=[
            'base_boss_bonus_switch',
            'add_boss_bonus_switch',
            'first_boss_bonus_switch',
            'mutual_boss_bonus_switch',
            'recommend_boss_bonus_switch',
            'community_boss_bonus_switch',
            'level_boss_bonus_switch',
            'manage_boss_bonus_switch'
        ];

        $switch_arr=Db::name('boss_config')->where(['key'=>['in',$switch_where]])->select();
        if(!empty($switch_arr)){
            foreach ($switch_arr as $key=>$val){
                if($val['key']=='base_boss_bonus_switch'){ //基礎分红
                    $config[1]['switch']=$val['value'];
                }elseif($val['key']=='add_boss_bonus_switch'){ //增加分红
                    $config[2]['switch']=$val['value'];
                }elseif($val['key']=='first_boss_bonus_switch'){//一級分红
                    $config[3]['switch']=$val['value'];
                }elseif($val['key']=='mutual_boss_bonus_switch'){//互助分红
                    $config[4]['switch']=$val['value'];
                }elseif($val['key']=='recommend_boss_bonus_switch'){//推薦獎勵
                    $config[5]['switch']=$val['value'];
                }elseif($val['key']=='community_boss_bonus_switch'){//社區獎勵
                    $config[6]['switch']=$val['value'];
                }elseif($val['key']=='level_boss_bonus_switch'){//平級獎勵
                    $config[7]['switch']=$val['value'];
                }elseif($val['key']=='manage_boss_bonus_switch'){//管理獎勵
                    $config[8]['switch']=$val['value'];
                }
            }
        }

        return array_values($config);
    }
    //社區管理計劃配置
    static function plan_content_config($type=1){
        $text=lang("bouns_all_content1");

        if($type==1){
           // $text=lang("bouns_base_content1")."\n".lang("bouns_base_content2")."\n".lang("bouns_base_content3")."\n".lang("bouns_base_content4")."\n".lang("bouns_all_content1");
        }elseif($type==2){
            //$text=lang("bouns_add_content1")."\n".lang("bouns_add_content2")."\n".lang("bouns_all_content1");
        }elseif($type==3){
           // $text=lang("bouns_one_content1")."\n".lang("bouns_one_content2")."\n".lang("bouns_all_content1");
        }
        return $text;
    }
    //分红与奖励日志配置
    static function bouns_log_config($num){
        $config=[
            //基礎分红
            '1'=>[ 'title'=>'lan_boss_title_log1','type_explain'=>'lan_bonus'],
            //增加分红
            '2'=>['title'=>'lan_boss_title_log2','type_explain'=>'lan_bonus' ],
            //一級分红
            '3'=>['title'=>'lan_boss_title_log3','type_explain'=>'lan_bonus'],
            //互助分红
            '4'=> ['title'=>'lan_boss_title_log4','type_explain'=>'lan_reward' ],
            //推薦獎勵
            '5'=> ['title'=>'lan_boss_title_log5','type_explain'=>'lan_reward'],
            //社區獎勵
            '6'=>['title'=>'lan_boss_title_log6','type_explain'=>'lan_reward' ],
            //平級獎勵
            '7'=> ['title'=>'lan_boss_title_log7','type_explain'=>'lan_reward'],
            //管理獎勵
            '8'=> ['title'=>'lan_boss_title_log8','type_explain'=>'lan_reward'],
            //社區獎勵(全球加权平分)
            '9'=>['title'=>'lan_boss_title_log6','type_explain'=>'lan_reward' ],
        ];
        return $config[$num];
    }
     //老板计划配置
    static function boss_config($param='param'){
        $where=[];
        if(!empty($param)){
            $where['key']=$param;
        }
        $config=Db::name('boss_config')->field('value')->where($where)->find();
        if(!empty($config)){
            $config=unserialize($config['value']);
        }

        return $config;
    }
    static function boss_ratio($member_id){
        $arr=[];
        $is_percent_open = Db::name('config')->where(['key'=>'is_percent_open'])->find();
        if(!$is_percent_open || $is_percent_open['value']==1){
            $time = time();
            if($time<strtotime('2019-04-11')) {
                $xrpz_percent = 0.9;
            } else {
                $xrpz_percent =  Boss_pan_receive_xrpz;
            }
            $member_percent = Db::name('boss_plan_percent')->where(['member_id'=>$member_id])->find();
            if($member_percent) {
                if($member_percent['percent']>=0 && $member_percent['percent']<=1) $xrpz_percent = (1-$member_percent['percent']);
            }
        } else {
            $xrpz_percent = 1;
        }
        
        $arr['ratio_9']= $xrpz_percent;
        $arr['ratio_1'] = 1 - $arr['ratio_9'];
        return $arr;
    }
}