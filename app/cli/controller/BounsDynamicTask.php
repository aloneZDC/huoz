<?php
namespace app\cli\controller;
use think\Log;
use think\Db;
use think\Exception;

use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 *动态分红定时任务
 */
class BounsDynamicTask extends Command
{
    private $table_name;
    public $same_day;//当天时间戳
    public $subtract = 0; //减去时间数
    public $config = [
        'level_bouns' => [ //社区奖励比例
            0 => 0,
            1 => 0.05,
            2 => 0.1,
            3 => 0.15,
            4 => 0.18,
            5 => 0.2,
            6 => 0.05,
        ],
        'ping_bouns' => [
            1 => 0.015,
            2 => 0.02,
        ],
        'manager_bouns' => 0.3,
        'stop_bouns' => 6,
        'recommand_bouns' => 0.1, //推荐分红奖励
    ];
    public $entrepreneur_config = [
        'stop_level' => 1,
        'bouns' => 0.01,
    ];

    protected function configure()
    {
        $this->setName('BounsDynamicTask')->setDescription('This is a test');
    }

    public function execute(Input $input, Output $output)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        $this->doRun();
    }

    public function doRun() {
        $param = Db::name("boss_config")->where(['key' => "dynamic"])->value('value');
        $this->config = unserialize($param);
        $this->config['v2_open_date'] = strtotime($this->config['v2_open_date']);

        $param = Db::name("boss_config")->where(['key' => "entrepreneur"])->value('value');
        if($param) $this->entrepreneur_config = unserialize($param);

        $this->same_day();

        // $boss_plan_info = Db::name('boss_plan_info')->where(['member_id'=>172956])->find();
        // $this->pid_tmp($boss_plan_info);
        // exit;

        //更新新增业绩,创建新表
        $flag = $this->create_bouns_table();
        if($flag===false){
            Log::write("创建表失败!");
            exit;
        }
        $this->new_bouns();

        $this->community_bouns_5();
        $this->community_bouns_6();
        $this->same_level_bouns();
        $this->manage_bouns();  
        $this->entrepreneur_bouns();  
        $this->recommand_bouns();
    }

    //社区奖励(1-5:用户等级 * 比例)
    protected function community_bouns_5() {
        $last_id = Db::name('boss_bouns_log')->where(['add_time'=>$this->same_day['start_time'],'type'=>6])->max('member_id');
        while (true) {
            try{
                //获取用户,ID升序
                $log_member = Db::name($this->table_name)->where(['member_id'=>['gt',$last_id],'add_time'=>$this->same_day['yestoday_start'],'type'=>6])->order('member_id asc')->find();
                if(!$log_member) {
                    Log::write($this->same_day['today'].':社区奖励1-5已完成');
                    break;
                }

                //查询用户老板计划详情
                $boss_plan_info = Db::name('boss_plan_info')->where(['member_id'=>$log_member['member_id']])->find();
                if(!$boss_plan_info) {
                    $last_id = $log_member['member_id'];
                    echo 'community_bouns_5:'.$last_id."\r\n";
                    continue;
                }

                //获取社区奖励的总和
                $total_bouns = Db::name($this->table_name)->field('sum(num) as num,sum(total) as total')->where(['member_id'=>$boss_plan_info['member_id'],'add_time'=>$this->same_day['yestoday_start'],'type'=>6])->find();
                if(!$total_bouns) {
                    $last_id = $boss_plan_info['member_id'];
                    echo 'community_bouns_5:'.$last_id."\r\n";
                    continue;
                }

                //6级社区奖励为全球，下个方法跑
                if($boss_plan_info['level']<=0 || $boss_plan_info['level']>5) {
                    $last_id = $boss_plan_info['member_id'];
                    echo 'community_bouns_5:'.$last_id."\r\n";
                    continue;
                }

                $limit_bouns = $this->getMemberTotalLimit($boss_plan_info);
                if($limit_bouns['limit']<=0) {
                    $last_id = $boss_plan_info['member_id'];
                    continue;
                }
                $total_bouns['num'] = $limit_bouns['limit']>$total_bouns['num'] ? $total_bouns['num'] : $limit_bouns['limit'];

                if($total_bouns['num']>0) {
                    $insert_data = $this->getBounsLogData($boss_plan_info,6,$total_bouns['num'],$this->config['level_bouns'][$boss_plan_info['level']],0,$total_bouns['total']);

                    $insert_id = BounsReceive::bouns_insert_receive($insert_data,$this->same_day['start_time']);
                    if($insert_id===false) $this->boss_log_error($boss_plan_info,6);
                }
                $last_id = $boss_plan_info['member_id'];

                echo 'community_bouns_5:'.$last_id."\r\n";
            } catch(Exception $e) {
                Log::write('community_bouns_5:'.$e->getMessage());
            }
        }
    }

    //社区奖励(6级 全球新业绩加权平分)
    protected function community_bouns_6() {
        $count = Db::name('boss_bouns_log')->where(['add_time'=>$this->same_day['start_time'],'type'=>9])->count();
        if($count>0) return;

        //获取昨日全球业绩
        $global_num = Db::name('boss_plan_buy')->where(['add_time'=>['between',[$this->same_day['yestoday_start'],$this->same_day['yestoday_stop']]],'status'=>1])->sum('pay_number');

        //获取6级用户
        $boss_level_6 = Db::name('boss_plan_info')->where(['level'=>6])->select();
        $avg = [];
        $level_6_total = 0;
        foreach ($boss_level_6 as $key => $level_6) {
            //获取6级用户昨日新增业绩
            $new_team_bouns_total = Db::name($this->table_name)->where(['member_id'=>$level_6['member_id'],'add_time'=>$this->same_day['yestoday_start'],'type'=>6])->sum('total');

            $level_6_total+= $new_team_bouns_total;
            $level_6['new_team_bouns_total'] = $new_team_bouns_total;
            $boss_level_6[$key] = $level_6;
        }

        if($level_6_total<=0) {
            Log::write('6级社区奖励分红已完成:没有数据');
            return;
        }

        $global_num = keepPoint($this->config['level_bouns'][6]*$global_num,6);

        $insert_data = [];
        foreach ($boss_level_6 as $key=>$level_6) {
            $percent = keepPoint($level_6['new_team_bouns_total']/$level_6_total,2);
            $total_bouns = keepPoint($global_num * $percent,6);
            
            $limit_bouns = $this->getMemberTotalLimit($level_6);
            if($limit_bouns['limit']<=0) {
                continue;
            }
            $total_bouns = $limit_bouns['limit']>$total_bouns ? $total_bouns : $limit_bouns['limit'];

            if($total_bouns>0) {
                $insert_data = $this->getBounsLogData($level_6,9,$total_bouns,$percent,0,$global_num);
                $insert_id = BounsReceive::bouns_insert_receive($insert_data,$this->same_day['start_time']);
                if($insert_id===false) $this->boss_log_error($level_6,9);
            }
        }
        Log::write('6级社区奖励分红已完成');
    }

    //平级奖励
    protected function same_level_bouns(){
        $last_id = Db::name('boss_bouns_log')->where(['add_time'=>$this->same_day['start_time'],'type'=>7])->max('member_id');
        while (true) {
            try{
                //获取用户,ID升序
                $log_member = Db::name($this->table_name)->where(['member_id'=>['gt',$last_id],'add_time'=>$this->same_day['yestoday_start'],'type'=>7])->order('member_id asc')->find();
                if(!$log_member) {
                    Log::write($this->same_day['today'].':平级奖励已完成');
                    break;
                }

                $boss_plan_info = Db::name('boss_plan_info')->where(['member_id'=>$log_member['member_id']])->find();
                if(!$boss_plan_info) {
                    $last_id = $log_member['member_id'];
                    echo 'same_level_bouns:'.$last_id."\r\n";
                    continue;
                }

                //获取昨日平级奖励的总和
                $total_bouns = Db::name($this->table_name)->field('sum(num) as num,sum(total) as total')->where(['member_id'=>$boss_plan_info['member_id'],'add_time'=>$this->same_day['yestoday_start'],'type'=>7])->find();
                if(!$total_bouns) {
                    $last_id = $boss_plan_info['member_id'];
                    echo 'same_level_bouns:'.$last_id."\r\n";
                    continue;
                }

                $limit_bouns = $this->getMemberTotalLimit($boss_plan_info);
                if($limit_bouns['limit']<=0) {
                    $last_id = $boss_plan_info['member_id'];
                    continue;
                }
                $total_bouns['num'] = $limit_bouns['limit']>$total_bouns['num'] ? $total_bouns['num'] : $limit_bouns['limit'];

                if($total_bouns['num']>0) {
                    $insert_data = $this->getBounsLogData($boss_plan_info,7,$total_bouns['num'],0,0,$total_bouns['total']);

                    $insert_id = BounsReceive::bouns_insert_receive($insert_data,$this->same_day['start_time']);
                    if($insert_id===false) $this->boss_log_error($boss_plan_info,6);
                }
                $last_id = $boss_plan_info['member_id'];

                echo 'same_level_bouns:'.$last_id."\r\n";
            } catch(Exception $e) {
                Log::write('same_level_bouns:'.$e->getMessage());
            }
        }
    }

    protected function manage_bouns(){
        $last_id = Db::name('boss_bouns_log')->where(['add_time'=>$this->same_day['start_time'],'type'=>8])->max('member_id');
        while (true) {
            try{
                //获取昨日新增业绩量
                $log_member = Db::name($this->table_name)->where(['member_id'=>['gt',$last_id],'add_time'=>$this->same_day['yestoday_start'],'type'=>8])->order('member_id asc')->find();
                if(!$log_member) {
                    Log::write($this->same_day['today'].':管理奖励已完成');
                    break;
                }

                $boss_plan_info = Db::name('boss_plan_info')->where(['member_id'=>$log_member['member_id']])->find();
                if(!$boss_plan_info) {
                    $last_id = $log_member['member_id'];
                    echo 'manage_bouns:'.$last_id."\r\n";
                    continue;
                }

                //获取平级奖励的总和
                $total_bouns = Db::name($this->table_name)->field('sum(num) as num,sum(total) as total')->where(['member_id'=>$boss_plan_info['member_id'],'add_time'=>$this->same_day['yestoday_start'],'type'=>8])->find();
                if(!$total_bouns) {
                    $last_id = $boss_plan_info['member_id'];
                    echo 'manage_bouns:'.$last_id."\r\n";
                    continue;
                }

                $limit_bouns = $this->getMemberTotalLimit($boss_plan_info);
                if($limit_bouns['limit']<=0) {
                    $last_id = $boss_plan_info['member_id'];
                    continue;
                }
                $total_bouns['num'] = $limit_bouns['limit']>$total_bouns['num'] ? $total_bouns['num'] : $limit_bouns['limit'];

                if($total_bouns['num']>0) {
                    $insert_data = $this->getBounsLogData($boss_plan_info,8,$total_bouns['num'],0,0,$total_bouns['total']);
                    $insert_id = BounsReceive::bouns_insert_receive($insert_data,$this->same_day['start_time']);
                    if($insert_id===false) $this->boss_log_error($boss_plan_info,8);
                }
                $last_id = $boss_plan_info['member_id'];

                echo 'manage_bouns:'.$last_id."\r\n";
            } catch(Exception $e) {
                Log::write('same_level_bouns:'.$e->getMessage());
            }
        }
    }

    //创业奖励
    protected function entrepreneur_bouns(){
        $last_id = Db::name('boss_bouns_log')->where(['add_time'=>$this->same_day['start_time'],'type'=>10])->max('member_id');
        while (true) {
            try{
                //获取昨日新增业绩量
                $log_member = Db::name($this->table_name)->where(['member_id'=>['gt',$last_id],'add_time'=>$this->same_day['yestoday_start'],'type'=>10])->order('member_id asc')->find();
                if(!$log_member) {
                    Log::write($this->same_day['today'].':创业奖励已完成');
                    break;
                }

                $boss_plan_info = Db::name('boss_plan_info')->where(['member_id'=>$log_member['member_id']])->find();
                if(!$boss_plan_info) {
                    $last_id = $log_member['member_id'];
                    echo 'entrepreneur_bouns:'.$last_id."\r\n";
                    continue;
                }

                //获取平级奖励的总和
                $total_bouns = Db::name($this->table_name)->field('sum(num) as num,sum(total) as total')->where(['member_id'=>$boss_plan_info['member_id'],'add_time'=>$this->same_day['yestoday_start'],'type'=>10])->find();
                if(!$total_bouns) {
                    $last_id = $boss_plan_info['member_id'];
                    echo 'entrepreneur_bouns:'.$last_id."\r\n";
                    continue;
                }

                $limit_bouns = $this->getMemberTotalLimit($boss_plan_info);
                if($limit_bouns['limit']<=0) {
                    $last_id = $boss_plan_info['member_id'];
                    continue;
                }
                $total_bouns['num'] = $limit_bouns['limit']>$total_bouns['num'] ? $total_bouns['num'] : $limit_bouns['limit'];

                if($total_bouns['num']>0) {
                    $insert_data = $this->getBounsLogData($boss_plan_info,10,$total_bouns['num'],0,0,$total_bouns['total']);
                    $insert_id = BounsReceive::bouns_insert_receive($insert_data,$this->same_day['start_time']);
                    if($insert_id===false) $this->boss_log_error($boss_plan_info,10);
                }
                $last_id = $boss_plan_info['member_id'];

                echo 'entrepreneur_bouns:'.$last_id."\r\n";
            } catch(Exception $e) {
                Log::write('entrepreneur_bouns:'.$e->getMessage());
            }
        }
    }

    //推荐奖励
    protected function recommand_bouns() {
        $last_id = Db::name('boss_bouns_log')->where(['add_time'=>$this->same_day['start_time'],'type'=>5])->max('member_id');
        while (true) {
            try{
                //获取昨日新增业绩量
                $log_member = Db::name($this->table_name)->where(['member_id'=>['gt',$last_id],'add_time'=>$this->same_day['yestoday_start'],'type'=>5])->order('member_id asc')->find();
                if(!$log_member) {
                    Log::write($this->same_day['today'].':推荐奖励已完成');
                    break;
                }

                $boss_plan_info = Db::name('boss_plan_info')->where(['member_id'=>$log_member['member_id']])->find();
                if(!$boss_plan_info) {
                    $last_id = $log_member['member_id'];
                    echo 'recommand_bouns:'.$last_id."\r\n";
                    continue;
                }

                //获取平级奖励的总和
                $total_bouns = Db::name($this->table_name)->field('sum(num) as num,sum(total) as total')->where(['member_id'=>$boss_plan_info['member_id'],'add_time'=>$this->same_day['yestoday_start'],'type'=>5])->find();
                if(!$total_bouns) {
                    $last_id = $boss_plan_info['member_id'];
                    echo 'recommand_bouns:'.$last_id."\r\n";
                    continue;
                }

                $limit_bouns = $this->getMemberTotalLimit($boss_plan_info);
                if($limit_bouns['limit']<=0) {
                    $last_id = $boss_plan_info['member_id'];
                    continue;
                }
                $total_bouns['num'] = $limit_bouns['limit']>$total_bouns['num'] ? $total_bouns['num'] : $limit_bouns['limit'];

                if($total_bouns['num']>0) {
                    $insert_data = $this->getBounsLogData($boss_plan_info,5,$total_bouns['num'],0,0,$total_bouns['total']);
                    $insert_id = BounsReceive::bouns_insert_receive($insert_data,$this->same_day['start_time']);
                    if($insert_id===false) $this->boss_log_error($boss_plan_info,5);
                }
                $last_id = $boss_plan_info['member_id'];

                echo 'recommand_bouns:'.$last_id."\r\n";
            } catch(Exception $e) {
                Log::write('recommand_bouns:'.$e->getMessage());
            }
        }
    }

    //昨日新增业绩
    private function new_bouns() {
        $last_id = Db::name($this->table_name)->where(['add_time'=>$this->same_day['yestoday_start']])->max('third_id');
        while (true) {
            try {
                $is_start = false;
                $boss_plan_buy = Db::name('boss_plan_buy')->where([
                    'id'=>['gt',$last_id],
                    'add_time'=>['between',[$this->same_day['yestoday_start'],$this->same_day['yestoday_stop']]],
                    'status'=>1])->order('id asc')->find();
                if(!$boss_plan_buy) {
                    Log::write($this->same_day['today'].':新增业绩已完成');
                    break;
                }

                //数量都是>0, 防止出错
                if($boss_plan_buy['pay_number']<=0) {
                    $last_id = $boss_plan_buy['id'];
                    Log::write($this->same_day['today'].':老板用户不存在'.$boss_plan_buy['id']);
                    continue;
                }

                $boss_plan_info = Db::name('boss_plan_info')->where(['member_id'=>$boss_plan_buy['member_id']])->find();
                if(!$boss_plan_info) {
                    $last_id = $boss_plan_buy['id'];
                    Log::write($this->same_day['today'].':老板用户不存在'.$boss_plan_buy['id']);
                    continue;
                }

                //推荐奖励
                $pid_boss_plan_info = Db::name('boss_plan_info')->where(['member_id'=>$boss_plan_info['pid']])->find();
                if(!$pid_boss_plan_info) {
                    $last_id = $boss_plan_buy['id'];
                    Log::write($this->same_day['today'].':老板用户上级不存在'.$boss_plan_buy['id']);
                    continue;
                }
                $recommand_base = min($boss_plan_buy['pay_number'],$pid_boss_plan_info['num']);
                $recommand_num = floor($recommand_base * $this->config['recommand_bouns']);
                $flag = Db::name($this->table_name)->insertGetId([
                    'member_id' => $pid_boss_plan_info['member_id'],
                    'num' => $recommand_num,
                    'total' => $boss_plan_buy['pay_number'],
                    'type' => 5,
                    'profit' => $this->config['recommand_bouns'],
                    'third_id' => $boss_plan_buy['id'],
                    'add_time' => $this->same_day['yestoday_start'],
                    'run_time' => time(),
                ]);
                if(!$flag) throw new Exception("插入创业奖励出错");

                //创业奖励 查找上级10层中 级别小于指定级别
                //2019-05-12 取消2-10代的推荐奖，上面的1代推荐奖不变
//                $entrepreneur_list = Db::name('member_bind')->field('a.level as c_level,b.member_id,b.level,b.num')->alias('a')
//                    ->join(config('database.prefix').'boss_plan_info b','a.member_id=b.member_id','LEFT')
//                    ->where(['a.child_id'=>$boss_plan_info['member_id'],'a.level'=>['elt',10],'b.level'=>['lt',$this->entrepreneur_config['stop_level']]])
//                    ->order('a.level asc')->select();
//                if($entrepreneur_list) {
//                    foreach ($entrepreneur_list as $entrepreneur) {
//                        if($entrepreneur['c_level']==1) continue;
//
//                        $num_entrepreneur_base = min($boss_plan_buy['pay_number'],$entrepreneur['num']);
//                        $num_entrepreneur = floor($num_entrepreneur_base * $this->entrepreneur_config['bouns']);
//                        $flag = Db::name($this->table_name)->insertGetId([
//                            'member_id' => $entrepreneur['member_id'],
//                            'num' => $num_entrepreneur,
//                            'total' => $boss_plan_buy['pay_number'],
//                            'type' => 10,
//                            'profit' => $this->entrepreneur_config['bouns'],
//                            'third_id' => $boss_plan_buy['id'],
//                            'add_time' => $this->same_day['yestoday_start'],
//                            'run_time' => time(),
//                        ]);
//                        if(!$flag) throw new Exception("插入创业奖励出错");
//                    }
//                }

                #上层关系网中 如果有更高级别的隔断 更上层级是否能拿到平级奖？
                // if($boss_plan_info['level']>0) {
                //     $same_level_base = $boss_plan_info;
                // } else {
                //     //如果是V0,查找离我最近的有等级的上级
                //     $same_level_base = Db::name('member_bind')->field('b.member_id,b.level')->alias('a')
                //         ->join(config('database.prefix').'boss_plan_info b','a.member_id=b.member_id','LEFT')
                //         ->where(['a.child_id'=>$boss_plan_info['member_id'],'b.level'=>['gt',0]])
                //         ->order('a.level asc')->find();
                // }

                //2019.1.28 修改为只有V5才能拿平级 查找离我最近的V5
                // $same_level_base = Db::name('member_bind')->field('b.member_id,b.level')->alias('a')
                //     ->join(config('database.prefix').'boss_plan_info b','a.member_id=b.member_id','LEFT')
                //     ->where(['a.child_id'=>$boss_plan_info['member_id'],'b.level'=>5])
                //     ->order('a.level asc')->find();
                // if(!empty($same_level_base)) {
                //     //如有平级则获取平级奖励
                //     $same_level = [];
                //     $same_level_list = Db::name('member_bind')->field('a.member_id,a.child_id,b.num')->alias('a')
                //     ->join(config('database.prefix').'boss_plan_info b','a.member_id=b.member_id','LEFT')
                //     ->where(['a.child_id'=>$same_level_base['member_id'],'b.level'=>['eq',$same_level_base['level']]])
                //     ->order('a.level asc')->limit(2)->select();
                //     if($same_level_list) {
                //         if(isset($same_level_list[0])) {
                //             $same_level[1] = $same_level_list[0];
                //         }
                //         if(isset($same_level_list[1])) {
                //             $same_level[2] = $same_level_list[1];
                //         }
                //     }

                //     //如果有平级,插入平级奖励
                //     if(!empty($same_level)) {
                //         //1级平级
                //         if(isset($same_level[1])){
                //             $num_same_base = min($boss_plan_buy['pay_number'],$same_level[1]['num']);
                //             $num_same = floor($num_same_base * $this->config['ping_bouns'][1]);
                //             $flag = Db::name($this->table_name)->insertGetId([
                //                 'member_id' => $same_level[1]['member_id'],
                //                 'num' => $num_same,
                //                 'total' => $boss_plan_buy['pay_number'],
                //                 'type' => 7,
                //                 'profit' => $this->config['ping_bouns'][1],
                //                 'third_id' => $boss_plan_buy['id'],
                //                 'add_time' => $this->same_day['yestoday_start'],
                //                 'run_time' => time(),
                //             ]);
                //             if(!$flag) throw new Exception("插入昨日新增业绩出错");
                //         }

                //         //2级平级
                //         if(isset($same_level[2])) {
                //             $num_same_base = min($boss_plan_buy['pay_number'],$same_level[2]['num']);
                //             $num_same = floor($num_same_base * $this->config['ping_bouns'][2]);
                //             $flag = Db::name($this->table_name)->insertGetId([
                //                 'member_id' => $same_level[2]['member_id'],
                //                 'num' => $num_same,
                //                 'total' => $boss_plan_buy['pay_number'],
                //                 'type' => 7,
                //                 'profit' => $this->config['ping_bouns'][2],
                //                 'third_id' => $boss_plan_buy['id'],
                //                 'add_time' => $this->same_day['yestoday_start'],
                //                 'run_time' => time(),
                //             ]);
                //             if(!$flag) throw new Exception("插入昨日新增业绩出错");
                //         }
                //     }
                // }    

                //2019.2.16 平级奖励修改为上级V1-V5 都拿1%
                //2019.5.12 平级奖励修改为上级V1-V6 都拿1%
                for ($boss_level=1; $boss_level <= 6; $boss_level++) {
                    //查找离我最近的上级
                    $same_level_base = Db::name('member_bind')->field('b.member_id,b.level')->alias('a')
                        ->join(config('database.prefix').'boss_plan_info b','a.member_id=b.member_id','LEFT')
                        ->where(['a.child_id'=>$boss_plan_info['member_id'],'b.level'=>$boss_level])
                        ->order('a.level asc')->find();

                    if($same_level_base) {
                        //查找上级的平级
                        $same_level1 = Db::name('member_bind')->field('b.member_id,b.level,b.num')->alias('a')
                            ->join(config('database.prefix').'boss_plan_info b','a.member_id=b.member_id','LEFT')
                            ->where(['a.child_id'=>$same_level_base['member_id'],'b.level'=>$boss_level])
                            ->order('a.level asc')->find();
                        if($same_level1) {
                            $num_same_base = min($boss_plan_buy['pay_number'],$same_level1['num']);
                            $num_same = floor($num_same_base * $this->config['ping_bouns'][1]);
                            $flag = Db::name($this->table_name)->insertGetId([
                                'member_id' => $same_level1['member_id'],
                                'num' => $num_same,
                                'total' => $boss_plan_buy['pay_number'],
                                'type' => 7,
                                'profit' => $this->config['ping_bouns'][1],
                                'third_id' => $boss_plan_buy['id'],
                                'add_time' => $this->same_day['yestoday_start'],
                                'run_time' => time(),
                            ]);
                            if(!$flag) throw new Exception("插入平级奖励出错");
                        }
                    }
                }

                //更新临时用户关系
                $flag = $this->pid_tmp($boss_plan_info);
                if($flag===false) {
                    $last_id = $boss_plan_buy['id'];
                    Log::write($this->same_day['today'].':更新临时用户关系出错:'.$boss_plan_buy['id']);
                    continue;
                }

                $is_start = true;
                Db::startTrans();
                //查询等级>=1的上级可以获得奖励 'b.level'=>['gt',0]
                $member_bind =  Db::name('member_bind')->alias('a')->field('b.member_id,b.level,b.pid,b.num')
                                ->join(config('database.prefix').'boss_plan_info b','a.pid_tmp=b.member_id','LEFT')
                                ->where(['child_id'=>$boss_plan_buy['member_id'],'pid_tmp'=>['gt',0]])->order('a.level asc')->select();
                if($member_bind) {
                    $last_level = 0;
                    foreach ($member_bind as $member) {
                        //6级用户,只新增业绩,没有社区奖励
                        if($member['level']==6) {
                            $profit = 0;
                            $num_6 = 0;
                        } else {
                            $profit = $this->config['level_bouns'][$member['level']] - $this->config['level_bouns'][$last_level];
                            if($profit<=0){
                                //过滤上级中有平级存在的情况
                                continue;
                            }

                            //插入社区奖励记录
                            $num_6 = min($member['num'],$boss_plan_buy['pay_number']);
                            $num_6 = floor($num_6 * $profit);
                            if($num_6<=0) {
                                continue;
                            }
                        }
                        $flag = Db::name($this->table_name)->insertGetId([
                            'member_id' => $member['member_id'],
                            'num' => $num_6,
                            'total' => $boss_plan_buy['pay_number'],
                            'type' => 6,
                            'profit' => $profit,
                            'third_id' => $boss_plan_buy['id'],
                            'add_time' => $this->same_day['yestoday_start'],
                            'run_time' => time(),
                        ]);
                        if(!$flag) throw new Exception("插入昨日新增业绩出错");
                        
                        //如有直属上级管理奖励
                        if($member['pid']!=0) {
                            if($this->same_day['start_time']>=$this->config['v2_open_date']) {
                                //V2.0 改为上级15层,每层票数考核 考核通过2%
                                $manager_level_list = Db::name('member_bind')->field('a.member_id,a.child_id,a.level,b.votes')->alias('a')
                                                ->join(config('database.prefix').'boss_plan_info b','a.member_id=b.member_id','LEFT')
                                                ->where(['a.child_id'=>$member['member_id'],'b.level'=>['elt',15]])
                                                ->order('a.level asc')->limit(15)->select();
                                foreach ($manager_level_list as $manager_level) {
                                    if(isset($this->config['manager_level_'.$manager_level['level']])) {
                                        $min_votes =  $this->config['manager_level_'.$manager_level['level']];
                                        if($manager_level['votes']>=$min_votes) {
                                            //管理奖励
                                            $manager_bouns = floor($this->config['manager_bouns_v2'] * $num_6);
                                            if($manager_bouns>0){
                                                $flag = Db::name($this->table_name)->insertGetId([
                                                    'member_id' => $manager_level['member_id'],
                                                    'num' => $manager_bouns,
                                                    'total' => $num_6,
                                                    'type' => 8,
                                                    'profit' => $this->config['manager_bouns_v2'],
                                                    'third_id' => $boss_plan_buy['id'],
                                                    'add_time' => $this->same_day['yestoday_start'],
                                                    'run_time' => time(),
                                                ]);
                                                if(!$flag) throw new Exception("插入昨日新增业绩管理奖励V2出错");
                                            }
                                        }
                                    }
                                }
                            } else {
                                //管理奖励
                                $manager_bouns = floor($this->config['manager_bouns'] * $num_6);
                                if($manager_bouns){
                                    $flag = Db::name($this->table_name)->insertGetId([
                                        'member_id' => $member['pid'],
                                        'num' => $manager_bouns,
                                        'total' => $num_6,
                                        'type' => 8,
                                        'profit' => $this->config['manager_bouns'],
                                        'third_id' => $boss_plan_buy['id'],
                                        'add_time' => $this->same_day['yestoday_start'],
                                        'run_time' => time(),
                                    ]);
                                    if(!$flag) throw new Exception("插入昨日新增业绩管理奖励出错");
                                }
                            }
                        }
                        $last_level = $member['level'];
                    }
                }
                Db::commit();

                $last_id = $boss_plan_buy['id'];
                echo $this->same_day['today'].' yestoday new bouns:'.$last_id."\r\n";
            } catch(Exception $e) {
                if($is_start) Db::rollback();
                $msg = $e->getMessage();
                Log::write("yestoday new bouns:".$msg);
            }
        }
    }

    /**
     *返回插入数据库数据
     *@param member_id 用户ID 
     *@param type 1 基础分红 2 增加分红 3 一级分红 4 互助分红 5推荐奖励 6社区奖励 7平级奖励 8管理奖励 9全球加权平分 10创业奖励
     *@param num 分红数量
     *@param rate 比例
     *@param limit_bouns 入金额容量
     */
    protected function getBounsLogData($boss_plan_info,$type,$num,$rate,$limit_bouns,$child_num=0,$param='') {
        return [
            'member_id' => $boss_plan_info['member_id'],
            'num' => $num,
            'profit' => $rate,
            'add_time' => $this->same_day['start_time'],
            'type' => $type,
            'type_status' => 2,
            'limit_num' => $limit_bouns,
            'in_num' => $boss_plan_info['num'],
            'level' => $boss_plan_info['level'],
            'child_num' => $child_num,
            'run_time' => time(),
            'param' => $param,
        ]; 
    }

     /**
     * @desc 当日时间
     * @return array
     */
    public function same_day()
    {
        $today = date('Y-m-d');
        $today_unix = strtotime($today);
        $today_unix -= $this->subtract;
        $this->same_day = ['today'=>date('Ymd',$today_unix),'start_time' => $today_unix, 'end_time' => ($today_unix+86400-1),'yestoday_start'=>($today_unix-86400),'yestoday_stop'=>($today_unix-1)];
        return $this->same_day;
    }

    private function boss_log_error($boss_plan_info,$type) {
        try{
            Db::name('yang_boss_bouns_log_error')->insertGetId([
                'member_id' => $boss_plan_info['member_id'],
                'type' => $type,
                'add_time' => $this->same_day['start_time'],
            ]);
        } catch(Exception $e) {
            return false;
        }
    }

    private function create_bouns_table() {
        try{
            $this->table_name = 'boss_bouns_detail'.$this->same_day['today'];
            $table_name = config('database.prefix').$this->table_name;
            //$table_exist = Db::query('show tables like "'.$table_name.'"');
            //if($table_exist) return true;

            $flag = Db::execute("CREATE TABLE IF NOT EXISTS `".$table_name."` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id` INT(11) NOT NULL DEFAULT '0',
    `num` DECIMAL(20,6) NOT NULL DEFAULT '0.000000' COMMENT '分红金额',
    `total` DECIMAL(20,6) NOT NULL DEFAULT '0.000000' COMMENT '新增业绩',
    `type` INT(11) NOT NULL DEFAULT '0',
    `profit` DECIMAL(5,3) NOT NULL DEFAULT '0.000' COMMENT '分红比例',
    `third_id` INT(11) NOT NULL DEFAULT '0' COMMENT '第三方ID',
    `add_time` INT(11) NOT NULL DEFAULT '0' COMMENT '时间',
    `run_time` INT(11) NOT NULL DEFAULT '0' COMMENT '插入时间',
    PRIMARY KEY (`id`),
    INDEX `member_id` (`member_id`, `add_time`, `type`)
)
COMMENT='老板计划新增业绩-昨天业绩'
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;
");
            if($flag===false) return false;

            return true;
        }catch(Exception $e){
            echo $e->getMessage();
            return false;
        }
    }

    //更新临时关系
    private function pid_tmp($boss_plan_info) {
        try{
            //恢复原有关系
            $flag = Db::execute('update '.config('database.prefix').'member_bind set pid_tmp=member_id where child_id='.$boss_plan_info['member_id']);

            //查询最靠近我的 级别比我高的上级
            $pidInfo = Db::name('member_bind')->field('a.member_id,a.child_id,a.level,b.level as pid_level')->alias('a')
            ->join(config('database.prefix').'boss_plan_info b','a.member_id=b.member_id','LEFT')
            ->where(['a.child_id'=>$boss_plan_info['member_id'],'b.level'=>['gt',$boss_plan_info['level']]])
            ->order('a.level asc')->find();
            if(!$pidInfo) {               
                //如果没有则表示我是最高级别,没有人能领取到我的奖励
                $flag = Db::name('member_bind')->where(['child_id'=>$boss_plan_info['member_id']])->setField('pid_tmp',0);
                if($flag===false) throw new Exception('我是最高级别更新失败:'.$boss_plan_info['member_id']);
            } else {
                //重新挂关系
                $flag = Db::name('member_bind')->where(['member_id'=>$pidInfo['member_id'],'child_id'=>$boss_plan_info['member_id']])->setField('pid_tmp',$pidInfo['member_id']);
                if($flag===false) throw new Exception('重新挂关系更新失败:'.$boss_plan_info['member_id']);

                //去除比我上级等级低的
                $flag = Db::name('member_bind')->alias('a')
                ->join(config('database.prefix').'boss_plan_info b','a.member_id=b.member_id','LEFT')
                ->where(['a.child_id'=>$boss_plan_info['member_id'],'b.level'=>['lt',$pidInfo['pid_level']]])->setField('pid_tmp',0);
                if($flag===false) throw new Exception('去除比我上级等级低的更新失败:'.$boss_plan_info['member_id']);

                //去除无效下级的关系 根据层级
                $flag = Db::name('member_bind')->where(['child_id'=>$boss_plan_info['member_id'],'level'=>['lt',$pidInfo['level']]])->setField('pid_tmp',0);
                if($flag===false) throw new Exception('去除无效下级的关系更新失败:'.$boss_plan_info['member_id']);

                //查询用户关系中 离我最近的最高级 去除小于最大的
                $maxPidInfo = Db::name('member_bind')->field('a.member_id,a.child_id,a.level,b.level as pid_level')->alias('a')
                ->join(config('database.prefix').'boss_plan_info b','a.member_id=b.member_id','LEFT')
                ->where(['a.child_id'=>$boss_plan_info['member_id']])
                ->order('b.level desc,a.level asc')
                ->find();

                //去除 比最高级别 层次高的上级
                $flag = Db::name('member_bind')->where(['child_id'=>$boss_plan_info['member_id'],'level'=>['gt',$maxPidInfo['level']]])->setField('pid_tmp',0);
                if($flag===false) throw new Exception('去除比最高级别等级低的更新失败:'.$boss_plan_info['member_id']);
            }

            return true;
        } catch(Exception $e) {
            Log::write($this->same_day['today'].'更新用户临时关系错误:'.$e->getMessage());
            return false;
        }
    }

    //2019.04.17 增加出局制度 300倍即出局
    protected function getMemberTotalLimit($boss_plan_info){
        $limit = $this->config['stop_all_bouns'];
        $stop_bouns = $boss_plan_info['num'] * $limit;
        $member_boss_total = $this->getMemberBounsTotal($boss_plan_info['member_id']);
        $limit = $stop_bouns - $member_boss_total;
        if($limit<=0) {
            $limit = 0;
        } else {
            $limit = keepPoint($limit,6);
        }

        return ['stop_bouns'=>$stop_bouns,'limit'=>$limit];
    }

    //用户获取的分红总和
    protected function getMemberBounsTotal($member_id) {
        $total_profit =  Db::name('boss_plan_count')->where(['member_id'=>$member_id])->value('total_profit');
        if(!$total_profit) $total_profit = 0;
        
        return $total_profit;
    }
}
