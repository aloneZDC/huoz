<?php
//俱乐部投票 推荐奖励发放
namespace app\cli\controller;

use app\common\model\Configs;
use app\common\model\Currency;
use app\common\model\GameLockLog;
use app\common\model\MemberBind;
use app\common\model\UsersLine;
use app\common\model\UsersLineLevelSetting;
use app\common\model\UsersVotes;
use app\common\model\UsersVotesAward;
use app\common\model\UsersVotesConfig;
use app\common\model\UsersVotesPay;
use app\common\model\UsersVotesUpgrade;
use think\Db;
use think\Exception;
use think\Log;
use think\console\Input;
use think\console\Output;
use think\console\Command;

class UsersVotesAwardTask extends Command
{
    protected function configure()
    {
        $this->setName('UsersVotesAwardTask')->setDescription('This is a UsersVotesAwardTask');
    }

    protected function execute(Input $input, Output $output)
    {
        \think\Request::instance()->module('cli');
        $this->doRun('');
    }

    protected function doRun($today='')
    {
        set_time_limit(0);
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        if(empty($today)) $today = date('Y-m-d');

        $today_start = strtotime($today);
        $today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'yestday_start' => $today_start - 86400,
            'yestday_stop' => $today_start - 1,
        ];

        Log::write("投票俱乐部奖励开始");
        $votes_config = UsersVotesConfig::get_config();
        if(empty($votes_config) || $votes_config['votes_is_open']['uvs_value']!=1) {
            Log::write("投票俱乐部奖励失败,配置为空");
            return;
        }

        $votes_currency = Currency::where(['currency_mark'=>$votes_config['votes_currency_mark']['uvs_value']])->field('currency_id,currency_mark')->find();
        if(empty($votes_currency)) {
            Log::write("投票俱乐部奖励失败,IO豆币种不存在");
            return;
        }

        $votes_score_currency = Currency::where(['currency_mark'=>$votes_config['votes_score_currency_mark']['uvs_value']])->field('currency_id,currency_mark')->find();
        if(empty($votes_score_currency)) {
            Log::write("投票俱乐部奖励失败,IO积分币种不存在");
            return;
        }

        //投票升级
        $this->votes_upgrade($today_config);

        $last_id = UsersVotesAward::where(['today'=>$today_config['today']])->max('third_id');
        while (true){
            echo "Votes award:".$last_id."\r\n";
            $pay_info = UsersVotesPay::where([ 'id'=>['gt',$last_id],'add_time'=>['between',[ $today_config['yestday_start'],$today_config['yestday_stop']]] ])->order('id asc')->find();
            if(empty($pay_info)){
                Log::write("投票俱乐部奖励已结束");
                break;
            }

            //上级推荐奖励
            $flag = UsersVotesAward::award($pay_info,$votes_config,$today_config,$votes_currency,$votes_score_currency);
            //释放游戏记录
            $flag = GameLockLog::game_release($pay_info,$votes_score_currency,$votes_config);
            $last_id = $pay_info['id'];
        }

        //数据汇总
        $this->votes_summary($today_config);
    }

    private function votes_upgrade($today_config) {
        UsersVotes::where(['user_id'=>['gt',0]])->setField('yestoday_team_num',0);

        $last_id = 0;
        while (true) {
            echo "user team:".$last_id."\r\n";
            $pay_info = UsersVotesPay::where(['id' => ['gt', $last_id], 'add_time' => ['between', [$today_config['yestday_start'], $today_config['yestday_stop']]]])->order('id asc')->find();
            if (empty($pay_info)) {
                Log::write("投票俱乐部更新团队业绩已结束");
                break;
            }

            if($pay_info['is_award']==1) {
                $last_id = $pay_info['id'];
                echo "skip\r\n";
                continue;
            }

            $flag = UsersVotesPay::where(['id'=>$pay_info['id']])->setField('is_award',1);
            if($flag) {
                //增加上级总业绩
                Db::execute('update '.config('database.prefix').'users_votes set team_num=team_num+'.$pay_info['pay_number'].' where user_id in(select member_id from '.config('database.prefix').'member_bind where child_id='.$pay_info['user_id'].')');
                //增加上级昨日业绩
                Db::execute('update '.config('database.prefix').'users_votes set yestoday=\''.$today_config['today'].'\',yestoday_team_num=yestoday_team_num+'.$pay_info['pay_number'].' where user_id in(select member_id from '.config('database.prefix').'member_bind where child_id='.$pay_info['user_id'].')');
                $last_id = $pay_info['id'];
            }
        }

        $max_level = UsersVotesConfig::getMaxLevel();
        $last_id = 0;
        while (true) {
            $votes_info = UsersVotes::where(['user_id'=>['gt',$last_id] ])->order('user_id asc')->find();
            if(empty($votes_info)) {
                Log::write('投票俱乐部升级结束'.$today_config['today']);
                break;
            }
            $last_id = $votes_info['user_id'];

            //根据团队业绩获取用户等级
            $team_num = $votes_info['num'] + $votes_info['team_num'];
            $total_num = max($team_num,$votes_info['game_money']); //团队业绩 和 晋级豆 的最大值
            $cur_level = UsersVotesConfig::getLevelByNum($total_num);
            echo "user upgrade:".$last_id." ". $team_num." ".$cur_level." ".$votes_info['level']."\r\n";
            if($cur_level>$votes_info['level']) {
                $flag = UsersVotesUpgrade::upgrade('team',$votes_info['user_id'],$cur_level,$team_num,$votes_info['game_money']);
                if($flag['code']!=SUCCESS) {
                    echo $flag['code']."\r\n";
                }
            }
        }
    }

    //汇总
    private function votes_summary($today_config) {
        $data = [
            'today' => $today_config['today'],
            'pay_num' => 0,
            'pay_score_num' => 0,
            'pay_io_num' => 0,
            'award_num' => 0,
            'award_score_num' => 0,
            'award_io_num' => 0,
            'game_lock_release' => 0,
            'add_time' => time(),
            'pay_num' => 0,
        ];
        $pay_num = UsersVotesPay::where(['add_time' => ['between', [$today_config['yestday_start'], $today_config['yestday_stop']]]])->field('sum(pay_number) as pay_number,sum(score_num) as score_num,sum(io_num) as io_num')->find();
        if($pay_num && !empty($pay_num['pay_number'])){
            $data['pay_num'] = $pay_num['pay_number'];
            $data['pay_score_num'] = $pay_num['score_num'];
            $data['pay_io_num'] = $pay_num['io_num'];
        }

        $min_id = UsersVotesPay::where(['add_time' => ['between', [$today_config['yestday_start'], $today_config['yestday_stop']]]])->min('id');
        $max_id = UsersVotesPay::where(['add_time' => ['between', [$today_config['yestday_start'], $today_config['yestday_stop']]]])->max('id');
        if($min_id && $max_id) {
            $game_lock_release = GameLockLog::where(['type'=>'release','third_id'=>['between',[$min_id,$max_id] ] ])->sum('num');
            if($game_lock_release) $data['game_lock_release'] = $game_lock_release;
        }

        $award_num = UsersVotesAward::where(['today'=>$today_config['today']])->field('sum(num) as num,sum(score_num) as score_num,sum(io_num) as io_num')->find();
        if($award_num && !empty($award_num['num']) ) {
            $data['award_num'] = $award_num['num'];
            $data['award_score_num'] = $award_num['score_num'];
            $data['award_io_num'] = $award_num['io_num'];
        }
        Db::name('users_votes_summary')->insertGetId($data);
    }
}