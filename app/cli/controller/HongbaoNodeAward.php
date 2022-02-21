<?php
namespace app\cli\controller;
use app\common\model\ContractOrder;
use app\common\model\Currency;
use app\common\model\HongbaoConfig;
use app\common\model\HongbaoNodeAwardDetail;
use app\common\model\HongbaoNodeCurrencyUser;
use app\common\model\Member;
use app\common\model\MemberBind;
use app\common\model\Sender;
use think\Log;
use think\Db;
use think\Exception;

use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 *K红包项目 -- 节点奖励
 * 考核 1.有10个下级直推，且每个下级都最少有100KOI
 *      2.本人最少500KOI
 *      3.3个部门 一个大于5W 一个大于3W 一个大于2W
 * 奖励：团队中有N个下级资产大于100KOI 奖励=N*个数 <200 个数0.5 <500个数1  <1000个数1.5 <5000个数2
 *
 * 限制：每人奖励2000时停止， 昨日合约达到6次后才能继续拿节点奖
 */
class HongbaoNodeAward extends Command
{
    public $name = '节点奖励';
    protected $award_config = [];
    protected $today_config = [];

    protected function configure()
    {
        $this->setName('HongbaoNodeAward')->setDescription('This is a HongbaoNodeAward');
    }

    protected function execute(Input $input, Output $output){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);
        \think\Request::instance()->module('cli');

        $this->doRun();
    }

    public function doRun() {
        $today = date('Y-m-d');
        $today_start = strtotime($today);
        $this->today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'today_end' => $today_start + 86399,
            'yestday_start' => $today_start - 86400,
            'yestday_stop' => $today_start - 1,
        ];

        Log::write($this->name." 开始 ");
        $config = HongbaoConfig::get_key_value();
        if(empty($config) || $config['node_is_open']!=1) {
            Log::write($this->name.'已关闭');
            return;
        }

        $currency = Currency::where(['currency_mark'=>$config['node_currency_mark']])->find();
        if(empty($currency)) {
            Log::write($this->name.'配置币种不存在');
            $sender = model('Sender')->send_email($config['node_error_email'],'node_award');
            return;
        }

        $count = \app\common\model\HongbaoNodeAward::where(['create_time'=>['egt',todayBeginTimestamp()]])->count();
        if($count>0) {
            echo "今日奖励已发放，不能重复发放";
            Log::write($this->name.'今日奖励已发放，不能重复发放');
            return;
        }

        //重新加载新的资产数据备份
        $flag = HongbaoNodeCurrencyUser::reload_today($currency);
        if(!$flag) {
            Log::write($this->name.'生成临时资产错误');
            $sender = model('Sender')->send_email($config['node_error_email'],'node_award');
            return;
        }

        //清空奖励详情表
        $flag = HongbaoNodeAwardDetail::truncate();
        if(!$flag) {
            Log::write($this->name.'清空奖励详情表出错');
            $sender = model('Sender')->send_email($config['node_error_email'],'node_award');
            return;
        }

        //增加上级业绩
        $this->child_currency_num($config,$currency);
        //检测是否通过考核
        $this->check_pass($config);
        //添加奖励详情
        $this->award_detail($config,$currency);
        //发放奖励
        $this->award($config,$currency);
        //汇总
        $this->summary($currency,$config);
    }

    //上级业绩
    public function child_currency_num($config,$currency) {
        $last_id = 0;
        while (true) {
            $currency_user = HongbaoNodeCurrencyUser::where([
                'member_id'=>['gt',$last_id],
            ])->order('member_id asc')->find();
            if(empty($currency_user)) {
                Log::write($this->name." 上级业绩已完成");
                break;
            }
            $last_id = $currency_user['member_id'];
            echo 'child_currency_num:'.$last_id."\r\n";

            //批量增加上级业绩
            $flag = HongbaoNodeCurrencyUser::parent_inc($currency_user['member_id'],$currency_user['num'],$config);
            if(!$flag) {
                Log::write($this->name.':'.$currency_user['member_id'].'批量增加上级业绩失败');
            }
        }
    }

    //检测是否通过考核
    public function check_pass($config) {
        $last_id = 0;
        while (true) {
            //本人大于500 且 不小于200个
            $currency_user = HongbaoNodeCurrencyUser::where([
                'member_id'=>['gt',$last_id],
                'num'=>['egt',$config['node_user_currency_min']],
                'all_child_count' => ['egt',$config['node_award_child_min'] ],
            ])->order('member_id asc')->find();
            if(empty($currency_user)) {
                Log::write($this->name.'考核任务层已完成');
                break;
            }
            $last_id = $currency_user['member_id'];
            echo "check_pass：{$last_id}\r\n";

            //检测是否通过考核
            HongbaoNodeCurrencyUser::check_pass($currency_user,$config);
        }
    }

    //奖励详情
    public function award_detail($config,$currency) {
        $last_id = 0;
        while (true) {
            $currency_user = HongbaoNodeCurrencyUser::where([
                'member_id' => ['gt',$last_id],
                'num' => ['egt',$config['node_child_currency_min']]
            ])->order('member_id asc')->find();
            if(empty($currency_user)) {
                Log::write($this->name.'奖励详情已完成');
                break;
            }

            $last_id = $currency_user['member_id'];
            echo "award_detail {$last_id}\r\n";

            $has_award = 0;
            $child_num = $config['node_award_child_min']; //最低数量限制
            $child_member_id = $currency_user['member_id'];
            //最多奖励4个人
            for ($level=1;$level<=$config['node_max_award_count'];$level++) {
                //查找离我最近的通过考核的上级
                $parent_last = MemberBind::alias('a')->field('a.member_id,b.num,b.all_child_count')
                    ->join(config("database.prefix") . "hongbao_node_currency_user b", "a.member_id=b.member_id", "LEFT")
                    ->where(['a.child_id'=>$child_member_id,'b.is_pass'=>1,'b.all_child_count'=>['egt',$child_num]])
                    ->order('a.level asc')->find();
                if(empty($parent_last)) {
                    Log::write($this->name.'奖励详情'.$level.'层已完成');
                    break;
                }
                $child_member_id = $parent_last['member_id'];

                $award_config = $this->getAwardConfig($parent_last['all_child_count'],$config);
                if($award_config['award_num']<=0) break;

                $real_award = $award_config['award_num'] - $has_award;
                if($real_award<=0) break;

                HongbaoNodeAwardDetail::insertGetId([
                    'user_id' => $parent_last['member_id'],
                    'currency_id' => $currency['currency_id'],
                    'num' => $real_award,
                    'base_num' => $parent_last['all_child_count'],
                    'create_time' => time(),
                    'third_user_id' => $currency_user['member_id'],
                ]);

                //得到奖励用户的当前级别
                $next_award_config = $this->getNextAwardConfig($award_config['index'],$config);
                if(!$next_award_config) break;

                $child_num = $next_award_config['child_min'];
                $has_award = $award_config['award_num'];
            }
        }
    }

    //发放奖励
    public function award($config,$currency) {
        $last_id = 0;
        while (true) {
            $detail = HongbaoNodeAwardDetail::where([
                'user_id' => ['gt',$last_id],
                'currency_id' => $currency['currency_id'],
                'create_time' => ['between',[$this->today_config['today_start'],$this->today_config['today_end'] ]],
            ])->order('user_id asc')->find();
            if(empty($detail)) {
                Log::write($this->name."奖励发放完毕");
                break;
            }
            $last_id = $detail['user_id'];
            echo "award:{$last_id}\r\n";

            //达到限额后 如果合约没有达到数量则停止
            $award_yu = 0;
            $count = ContractOrder::get_yestoday_order_num($detail['user_id']);
            if($count<$config['node_yestoday_contract_min']) {
                //如果没有满足合约条件 则有限额
                $total_award = \app\common\model\HongbaoNodeAward::getSumNum($detail['user_id']);
                $award_yu = keepPoint($config['node_limit']-$total_award,6);
                if($award_yu<=0) continue;
            }

            $sum = HongbaoNodeAwardDetail::where([
                'user_id' => $detail['user_id'],
                'currency_id' => $currency['currency_id'],
                'create_time' => ['between',[$this->today_config['today_start'],$this->today_config['today_end'] ]],
            ])->sum('num');
            if($award_yu>0) $sum = min($sum,$award_yu);
            if($sum<0.000001) continue;

            $child_num = $child_count = $all_child_count = 0;
            $currency_user = HongbaoNodeCurrencyUser::where(['member_id'=>$detail['user_id']])->find();
            if($currency_user) {
                $child_num = $currency_user['child_num'];
                $child_count = $currency_user['child_count'];
                $all_child_count = $currency_user['all_child_count'];
            }

            \app\common\model\HongbaoNodeAward::award($detail['user_id'],$currency['currency_id'],$sum,$child_num,$child_count,$all_child_count);
        }
    }

    private function summary($currency,$config) {
        $table_name = 'hongbao_node_award_detail'.str_replace('-','',$this->today_config['today']);
        echo "summary:".$table_name;
        HongbaoNodeAwardDetail::create_table($table_name);

        $insert_data = [
            'currency_id' => $currency['currency_id'],
            'num' => 0,
            'total_user' => 0,
            'total_currency_num' => 0,
            'total_currency_user' => 0,
            'total_system_user' => 0,
            'today' => $this->today_config['today'],
            'add_tme' => time(),
        ];
        //奖励总量
        $num = \app\common\model\HongbaoNodeAward::where([
            'create_time' => ['between',[$this->today_config['today_start'],$this->today_config['today_end'] ]],
        ])->sum('num');
        if($num) $insert_data['num'] = $num;

        //奖励总人数
        $total_user = \app\common\model\HongbaoNodeAward::where([
            'create_time' => ['between',[$this->today_config['today_start'],$this->today_config['today_end'] ]],
        ])->count();
        if($total_user) $insert_data['total_user'] = $total_user;
        //统计时 系统用户资产总数量
        $total_currency_num = HongbaoNodeCurrencyUser::sum('num');
        if($total_currency_num) $insert_data['total_currency_num'] = $total_currency_num;
        $total_currency_user = $currency_user = HongbaoNodeCurrencyUser::where(['num' => ['egt',$config['node_child_currency_min']]])->count();
        if($total_currency_user) $insert_data['total_currency_user'] = $total_currency_user;
        $total_user = Member::where(['reg_time'=>['lt',$this->today_config['today_start']]])->count();
        if($total_user) $insert_data['total_system_user'] = $total_user;

        Db::name('hongbao_node_award_summary')->insertGetId($insert_data);

    }


    private function getAwardConfig($child_num,$config) {
        $award_num = [
            'award_num' => 0,
            'child_min' => 0,
            'index' => 0,
        ];
        $cur_award = $config['node_max_award_count'];
        while (true) {
            if(!isset($config['node_level'.$cur_award]) || !isset($config['node_award'.$cur_award])) break;
            if($child_num>=$config['node_level'.$cur_award]) {
                $award_num = [
                    'award_num' => $config['node_award'.$cur_award],
                    'child_min' => $config['node_level'.$cur_award],
                    'index' => $cur_award,
                ];
                break;
            }
            $cur_award--;
        }
        return $award_num;
    }

    private function getNextAwardConfig($cur_award,$config) {
        $cur_award = $cur_award + 1;
        if(!isset($config['node_level'.$cur_award]) || !isset($config['node_award'.$cur_award])) return false;
        return [
            'award_num' => $config['node_award'.$cur_award],
            'child_min' => $config['node_level'.$cur_award],
            'index' => $cur_award,
        ];
    }
}
