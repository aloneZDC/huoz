<?php
namespace app\cli\controller;
use think\Log;
use think\Db;
use think\Exception;

use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 *静态分红定时任务
 */
class BounsTask extends Command
{

    protected $same_day;//当天时间戳
    public $limit_time = 600; //10分钟通知一次
    public $last_time = 0;
    public $subtract = 0; //减去时间数
    public $config = [
        'base_bouns' => 0.005, //基础分红比例
        'stop_bouns' => [
            'no_invit' => 1.5, //没有邀请1.5倍停止
            'invit' => 3, //邀请过好友的3倍停止
            'all' => 6,
        ],
        'add_bouns' => [ //增加分红比例
            0 => 0,
            1 => 0.0005,
            2 => 0.001,
            3 => 0.0015,
            4 => 0.002,
            5 => 0.0025,
            6 => 0.003,
            7 => 0.0035,
            8 => 0.004,
            9 => 0.0045,
            10 => 0.005,
        ],
        'one_bouns' => 0.5, //一级分红比例
        'recommand_bouns' => 0.1, //推荐分红奖励
        'lucky_bouns' => 0.01,
        'lucky_level' => 10, //幸运赠送层数
    ];

    protected function configure()
    {
        $this->setName('BounsTask')->setDescription('This is a test');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

       // $this->doRun();
    }

    public function doRun() {
        $param = Db::name("boss_config")->where(['key' => "param"])->value('value');
        $this->config = unserialize($param);
        $this->config['v2_open_date'] = strtotime($this->config['v2_open_date']);
        
        $this->same_day();
        if($this->same_day['start_time']>=$this->config['v2_open_date']) {
            //V2.0基础分红比例
            $this->config['base_bouns'] = $this->config['base_bouns_v2'];

            //V2.0 基础分红 及 幸运赠送分红停止倍数
            $this->config['stop_bouns']['no_invit'] = $this->config['stop_bouns']['no_invit_v2'];
            $this->config['stop_bouns']['invit'] = $this->config['stop_bouns']['invit_v2'];
        }

        //2019-05-12所有静态停止，包括幸运
//        $this->base_add_bouns();
//        $this->one_bouns();
//        $this->lucky_bouns();
    }

    //基础分红 本人入金*比例
    //增加分红 本人入金*根据推荐人数获得比例
    protected function base_add_bouns() {
        //2019.04.15改为基础赠送停止 
        if($this->same_day['start_time']>=$this->config['v2_open_date']) {
            Log::write($this->same_day['today'].':V2模式开启,基础赠送停止,一级分红停止');
            return;
        }

        $last_id = Db::name('boss_bouns_log')->where(['add_time'=>$this->same_day['start_time'],'type'=>1])->max('member_id');
        while (true) {
            try{
                $boss_plan_info = Db::name('boss_plan')->alias('a')
                                  ->join(config('database.prefix').'boss_plan_info b','a.member_id=b.member_id','LEFT')
                                  ->where(['a.member_id'=>['gt',$last_id],'a.status'=>3])->order('a.member_id asc')->find();
                
                if(!$boss_plan_info) {
                    Log::write($this->same_day['today'].':基础分红&增加分红已完成');
                    break;
                }

                //2019.4.17 后加入的用户不在享有基础赠送
                if($boss_plan_info['confirm_time']>=$this->config['v2_open_date']) {
                    $last_id = $boss_plan_info['member_id'];
                    continue;
                }

                //获取分红限制总量 和  还能获得的分红量 
                $limit_bouns = $this->getMemberBounsLimit($boss_plan_info);

                if($limit_bouns['limit']<=0) {
                    $last_id = $boss_plan_info['member_id'];
                    continue;
                }

                $insert_data = [];

                //获取基础分红限制总量
                $base_limit_bonus = $this->getMemberBaseLimit($boss_plan_info);
                $base_limit = min($limit_bouns['limit'],$base_limit_bonus['limit']);
                //基础分红
                $bouns_num = keepPoint($this->config['base_bouns'] * $boss_plan_info['num'],6);
                $bouns_num = $base_limit>$bouns_num ? $bouns_num : $base_limit;
                if($bouns_num>0) {
                    $insert_data[] = $this->getBounsLogData($boss_plan_info,1,$bouns_num,$this->config['base_bouns'],$base_limit_bonus['stop_bouns']);
                }

                if($this->same_day['start_time']>=$this->config['v2_open_date']) {
                    // Log::write($this->same_day['today'].':V2模式开启,增加分红停止1');
                } else {
                    //增加分红
                    $limit_bouns['limit'] -= $bouns_num;
                    if($limit_bouns['limit']>0) { 
                        $push_num = $boss_plan_info['push_num'];
                        if($push_num>10) $push_num = 10;

                        $add_bouns = keepPoint($this->config['add_bouns'][$push_num] * $boss_plan_info['num'],6);
                        $add_bouns = $limit_bouns['limit']>$add_bouns ? $add_bouns : $limit_bouns['limit'];
                        if($add_bouns>0){
                            $insert_data[] = $this->getBounsLogData($boss_plan_info,2,$add_bouns,$this->config['add_bouns'][$push_num],$limit_bouns['stop_bouns']);
                        }
                    }
                }

                if(!empty($insert_data)) {
                    $insert_id = Db::name('boss_bouns_log')->insertAll($insert_data);
                    if($insert_id===false) $this->boss_log_error($boss_plan_info,1);
                }
                $last_id = $boss_plan_info['member_id'];
                echo 'bonus_add:'.$last_id."\r\n";
            } catch(Exception $e) {
                Log::write('基础分红&增加分红:'.$e->getMessage());
            }
        }
    }

    //一级分红(一代会员增加分红的总和 * 比例) 需等待增加分红完成才可执行
    public function one_bouns(){
        //V2模式,增加分红停止
        if($this->same_day['start_time']>=$this->config['v2_open_date']) {
            Log::write($this->same_day['today'].':V2模式开启,一级分红停止');
            return;
        }

        $last_id = Db::name('boss_bouns_log')->where(['add_time'=>$this->same_day['start_time'],'type'=>3])->max('member_id');
        while (true) {
            try{
                $boss_plan_info = Db::name('boss_plan')->alias('a')
                                  ->join(config('database.prefix').'boss_plan_info b','a.member_id=b.member_id','LEFT')
                                  ->where(['a.member_id'=>['gt',$last_id],'a.status'=>3,'b.push_num'=>['gt',0]])->order('a.member_id asc')->find();
                
                if(!$boss_plan_info) {
                    Log::write($this->same_day['today'].':一级分红已完成');
                    break;
                }

                $add_bouns_total = Db::name('boss_plan')->alias('a')
                                  ->join(config('database.prefix').'boss_bouns_log b','a.member_id=b.member_id','LEFT')
                                  ->where(['a.pid'=>$boss_plan_info['member_id'],'b.type'=>2,'b.add_time'=>['between',[$this->same_day['start_time'],$this->same_day['end_time']]]])
                                  ->sum('b.num');
                if($add_bouns_total<0) {
                    $last_id = $boss_plan_info['member_id'];
                    continue;
                }

                $limit_bouns = $this->getMemberBounsLimit($boss_plan_info);
                if($limit_bouns['limit']<=0) {
                    $last_id = $boss_plan_info['member_id'];
                    continue;
                }

                $one_bouns = keepPoint($this->config['one_bouns'] * $add_bouns_total,6);
                $one_bouns = $limit_bouns['limit']>$one_bouns ? $one_bouns : $limit_bouns['limit'];

                if($one_bouns>0) {
                    $data = $this->getBounsLogData($boss_plan_info,3,$one_bouns,$this->config['one_bouns'],$limit_bouns['stop_bouns'],$add_bouns_total);

                    $insert_id = Db::name('boss_bouns_log')->insertGetId($data);
                    if($insert_id===false) $this->boss_log_error($boss_plan_info,3);
                }

                $last_id = $boss_plan_info['member_id'];
                echo 'bonus_one:'.$last_id."\r\n";
            } catch(Exception $e) {
                Log::write('一级分红:'.$e->getMessage());
            }
        }
    }

    //V2幸运赠送算法
    public function lucky_bouns_v2() {
        if(empty($this->config['lucky_bouns_v2'])) return;

        //昨日新增业绩总数
        $lucky_total = Db::name('boss_plan_buy')->where(['add_time'=>['between',[$this->same_day['yestoday_start'],$this->same_day['yestoday_stop']]],'status'=>1])->sum('pay_number');
        if(!$lucky_total || $lucky_total<=0) {
            Log::write($this->same_day['today'].date('Y-m-d H:i:s').':幸运分红已完成');
            return;
        }

        //全球公排人数
        $lucky_member_count = Db::name('boss_plan_lucky')->where(['is_stop'=>0])->count();
        if(!$lucky_member_count || $lucky_member_count<=0) {
            Log::write($this->same_day['today'].date('Y-m-d H:i:s').':幸运分红已完成');
            return;
        }
        
        //每人的平均数量
        $lucky_bouns_base = keepPoint(($lucky_total*$this->config['lucky_bouns_v2'])/$lucky_member_count,6); //lucky_bouns_v2 0.2

        Log::write(date('Y-m-d H:i:s').':幸运分红V2开始');
        $last_id = Db::name('boss_bouns_log')->where(['add_time'=>$this->same_day['start_time'],'type'=>4])->max('member_id');
        while (true) {
            try{
                $boss_plan_lucky = Db::name('boss_plan_lucky')->alias('a')
                                  ->field('a.member_id,a.add_time as join_time,b.num,b.level,b.push_num')
                                  ->join(config('database.prefix').'boss_plan_info b','a.member_id=b.member_id','LEFT')
                                  ->where(['a.member_id'=>['gt',$last_id],'a.is_stop'=>0])->order('a.member_id asc')->find();
                if(!$boss_plan_lucky) {
                    Log::write($this->same_day['today'].date('Y-m-d H:i:s').':幸运分红V2已完成');
                    break;
                }

                $min_luck_base = keepPoint($lucky_bouns_base*0.9,6);
                $lucky_bouns = randomFloat($min_luck_base,$lucky_bouns_base);
                if($lucky_bouns>1) $lucky_bouns = intval($lucky_bouns);

                $limit_bouns = $this->getMemberBounsLimit($boss_plan_lucky);
                //减去今天得到的静123
                $today_total_123 = Db::name('boss_bouns_log')->where(['member_id'=>$boss_plan_lucky['member_id'],'add_time'=>$this->same_day['start_time'],'type'=>['lt',4]])->sum('num');
                if($today_total_123) $limit_bouns['limit'] -= $today_total_123;

                if($limit_bouns['limit']<=0) {
                    Db::name('boss_plan_lucky')->where(['member_id'=>$boss_plan_lucky['member_id']])->setField('is_stop',1);
                    $last_id = $boss_plan_lucky['member_id'];
                    continue;
                }

                if($limit_bouns['limit']<$lucky_bouns) {
                    Db::name('boss_plan_lucky')->where(['member_id'=>$boss_plan_lucky['member_id']])->setField('is_stop',1);
                    
                    $lucky_bouns = $limit_bouns['limit'];
                }

                if($lucky_bouns>0) {
                    $data = $this->getBounsLogData($boss_plan_lucky,4,$lucky_bouns,$this->config['lucky_bouns_v2'],$limit_bouns['stop_bouns'],$lucky_bouns_base);
                    $insert_id = Db::name('boss_bouns_log')->insertGetId($data);
                    if($insert_id===false) $this->boss_log_error($boss_plan_lucky,4);
                }

                $last_id = $boss_plan_lucky['member_id'];
                echo date('Y-m-d',$this->same_day['start_time']).'lucky_bouns:'.$last_id."\r\n";
            } catch(Exception $e) {
                Log::write('幸运分红V2:'.$e->getMessage());
            }
        }
    }

    //幸运分红 原名 互助分红 有条件的公排
    public function lucky_bouns(){
        if($this->same_day['start_time']>=$this->config['v2_open_date']) {
            Log::write($this->same_day['today'].':V2模式开启,开启幸运赠送V2');
            $this->lucky_bouns_v2();
            return;
        }

        if(empty($this->config['lucky_bouns'])) return;

        $this->config['lucky_level'] = intval($this->config['lucky_level']);
        if(empty($this->config['lucky_level'])) return;

        Log::write(date('Y-m-d H:i:s').':幸运分红开始');
        $last_id = Db::name('boss_bouns_log')->where(['add_time'=>$this->same_day['start_time'],'type'=>4])->max('member_id');
        while (true) {
            try{
                $boss_plan_lucky = Db::name('boss_plan_lucky')->alias('a')
                                  ->field('a.member_id,a.add_time as join_time,b.num,b.level,b.push_num')
                                  ->join(config('database.prefix').'boss_plan_info b','a.member_id=b.member_id','LEFT')
                                  ->where(['a.member_id'=>['gt',$last_id]])->order('a.member_id asc')->find();
                if(!$boss_plan_lucky) {
                    Log::write($this->same_day['today'].date('Y-m-d H:i:s').':幸运分红已完成');
                    break;
                }

                //获取后面的10个用户
                $boss_plan_lucky_list = Db::name('boss_plan_lucky')->where(['add_time'=>['gt',$boss_plan_lucky['join_time']]])->order('add_time asc')->limit(10)->select();
                if(!$boss_plan_lucky_list) {
                    $last_id = $boss_plan_lucky['member_id'];
                    continue;
                }

                $lucky_bouns_base = 0;
                foreach ($boss_plan_lucky_list as $lucky_one) {
                    //查询昨日新增收益,有烧伤
                    $lucky_one_total_lt = Db::name('member_bind')->alias('a')
                            ->join(config('database.prefix').'boss_plan_buy b','a.child_id=b.member_id','LEFT')
                            ->where(['a.member_id'=>$lucky_one['member_id'],'a.level'=>['elt',$this->config['lucky_level']],'b.pay_number'=>['lt',$boss_plan_lucky['num']],'b.add_time'=>['between',[$this->same_day['yestoday_start'],$this->same_day['yestoday_stop']]],'b.status'=>1])->sum('b.pay_number');

                    $lucky_one_total_egt = Db::name('member_bind')->alias('a')
                            ->join(config('database.prefix').'boss_plan_buy b','a.child_id=b.member_id','LEFT')
                            ->where(['a.member_id'=>$lucky_one['member_id'],'a.level'=>['elt',$this->config['lucky_level']],'b.pay_number'=>['egt',$boss_plan_lucky['num']],'b.add_time'=>['between',[$this->same_day['yestoday_start'],$this->same_day['yestoday_stop']]],'b.status'=>1])->count();
                    $lucky_one_total = $lucky_one_total_lt + $lucky_one_total_egt * $boss_plan_lucky['num'];

                    $lucky_bouns_base += $lucky_one_total;
                }
                if($lucky_bouns_base<=0) {
                    $last_id = $boss_plan_lucky['member_id'];
                    continue;
                }

                $lucky_bouns = keepPoint($this->config['lucky_bouns'] * $lucky_bouns_base,6);

                //3倍停止
                $limit_bouns = $this->getMemberBounsLimit($boss_plan_lucky);
                //减去今天得到的静123
                $today_total_123 = Db::name('boss_bouns_log')->where(['member_id'=>$boss_plan_lucky['member_id'],'add_time'=>$this->same_day['start_time'],'type'=>['lt',4]])->sum('num');
                if($today_total_123) $limit_bouns['limit'] -= $today_total_123;

                if($limit_bouns['limit']<=0) {
                    $last_id = $boss_plan_lucky['member_id'];
                    continue;
                }

                if($limit_bouns['limit']<$lucky_bouns) $lucky_bouns = $limit_bouns['limit'];

                if($lucky_bouns>0) {
                    $data = $this->getBounsLogData($boss_plan_lucky,4,$lucky_bouns,$this->config['lucky_bouns'],$limit_bouns['stop_bouns'],$lucky_bouns_base);
                    $insert_id = Db::name('boss_bouns_log')->insertGetId($data);
                    if($insert_id===false) $this->boss_log_error($boss_plan_lucky,4);
                }

                $last_id = $boss_plan_lucky['member_id'];
                echo date('Y-m-d',$this->same_day['start_time']).'lucky_bouns:'.$last_id."\r\n";
            } catch(Exception $e) {
                Log::write('幸运分红:'.$e->getMessage());
            }
        }
    }

    //用户获取的分红总和
    protected function getMemberBounsTotal($member_id) {
        $total_profit =  Db::name('boss_plan_count')->where(['member_id'=>$member_id])->value('total_profit');
        if(!$total_profit) $total_profit = 0;
        
        return $total_profit;
    }

    //获取还能获得的分红量
    protected function getMemberBounsLimit($boss_plan_info){
        //无邀请人1.5倍 有邀请人3倍
        $limit = $this->config['stop_bouns']['no_invit'];
        if($boss_plan_info['push_num']>0) $limit = $this->config['stop_bouns']['invit'];

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

    //获取用户基础分红限制
    protected function getMemberBaseLimit($boss_plan_info) {
        //基础分红1.5倍即停止
        // $member_base_total = Db::name('boss_bouns_log')->where(['member_id'=>$boss_plan_info['member_id'],'type'=>1,'receive_status'=>1])->sum('num');

        //改为动静结合1.5倍停止基础分红
        $member_base_total = $this->getMemberBounsTotal($boss_plan_info['member_id']);

        $stop_bouns = $boss_plan_info['num'] * $this->config['stop_bouns']['no_invit'];
        $limit = $stop_bouns - $member_base_total;
        if($limit<=0) {
            $limit = 0;
        } else {
            $limit = keepPoint($limit,6);
        }
        return ['stop_bouns'=>$stop_bouns,'limit'=>$limit];
    }

    /**
     *返回插入数据库数据
     *@param member_id 用户ID 
     *@param type 1 基础分红 2 增加分红 3 一级分红 4 互助分红
     *@param num 分红数量
     *@param rate 比例
     *@param limit_bouns 入金额容量
     */
    protected function getBounsLogData($boss_plan_info,$type,$num,$rate,$limit_bouns,$child_num=0) {
        $type_status = 1;
        if($type==5) $type_status = 2;

        return [
            'member_id' => $boss_plan_info['member_id'],
            'num' => $num,
            'profit' => $rate,
            'add_time' => $this->same_day['start_time'],
            'type' => $type,
            'type_status' => $type_status,
            'limit_num' => $limit_bouns,
            'in_num' => $boss_plan_info['num'],
            'level' => $boss_plan_info['level'],
            'child_num' => $child_num,
            'run_time' => time(),
        ];
    }

     /**
     * @desc 当日时间
     * @return array
     */
    public function same_day()
    {
        if (empty($this->same_day)) {
            $today = date('Y-m-d');
            $today_unix = strtotime($today);
            $today_unix -= $this->subtract;
            $this->same_day = ['today'=>$today,'start_time' => $today_unix, 'end_time' => ($today_unix+86400-1),'yestoday_start'=>($today_unix-86400),'yestoday_stop'=>($today_unix-1)];
        }
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

    private function randomFloat($min = 0, $max = 1) {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }
}