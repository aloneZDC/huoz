<?php
//拼团挖矿
namespace app\api\controller;

use app\common\model\Currency;
use app\common\model\CurrencyUser;
use app\common\model\GroupMiningConfig;
use app\common\model\GroupMiningForzen;
use app\common\model\GroupMiningForzenLog;
use app\common\model\GroupMiningLog;
use app\common\model\GroupMiningRewardLevel;
use app\common\model\GroupMiningSourceBuy;
use app\common\model\GroupMiningSourceLevel;
use app\common\model\GroupMiningUser;
use think\Db;

class GroupMining extends Base
{
    //拼团挖矿首页
    public function index()
    {
        $userInfo = GroupMiningUser::getUserInfo($this->member_id, true);
        $openBuy = GroupMiningSourceBuy::getOpenBuy($this->member_id);
        $currencyId = GroupMiningConfig::get_value('group_mining_currency_id', 69);//拼团挖矿币种id
        $moneyCurrencyId = GroupMiningConfig::get_value('group_mining_money_currency_id', 66);//拼团挖矿支付币种id
        $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $moneyCurrencyId);
        $info = [
            'reward_level'=>$userInfo['reward_level'],
            'reward_level_name'=>$userInfo['reward_level'] > 0 ? GroupMiningRewardLevel::where('level_id', $userInfo['reward_level'])->value('level_name') : 'F0',
            'role'=>1,
            'buy_id'=>0,
            'today_num'=>0,
            'total_forzen_num'=>$userInfo['total_forzen_num'],
            'forzen_currency_name'=>Currency::where('currency_id', $currencyId)->value('currency_name'),
            'total_lose_reward'=>$userInfo['total_lose_reward'],
            'lose_reward_currency_name'=>Currency::where('currency_id', $moneyCurrencyId)->value('currency_name'),
            'mining_user_num'=>0,
            'today_lose_reward'=>GroupMiningLog::where(['user_id' => $this->member_id,'date' => date('Y-m-d'),'result_status' => 2,])->sum('reward_num') ? : 0,
            'last_day'=>0,
            'money_num'=>floattostr($currencyUser['num']),
            'level_info'=>[],
        ];
        $role = 1;//身份 1-游客 2-申购
        $now = time();
        $nowTime = strtotime(date('Y-m-d 00:00:00', $now));
        if ($openBuy) {
            //类型 1-申购 2-体验
            if ($openBuy['type'] == 1) {
                $role = 2;
                $info['last_day'] = intval(($openBuy['end_time'] - max($openBuy['start_time'], $nowTime)) / 86400);
            }
            $info['level_info'] = GroupMiningSourceLevel::where('level_id', $openBuy['level_id'])->field('level_id,level_name,daily_num,days,daily_num*days AS total_num')->find();
            $info['buy_id'] = $openBuy['id'];
            $info['today_num'] = $info['level_info']['daily_num'] - $openBuy['today_num'];
        }
        else {
            $info['level_info'] = GroupMiningSourceLevel::where('type', 2)->field('level_id,level_name,daily_num,days,daily_num*days AS total_num')->find();
        }
        $info['role'] = $role;
        $enlargeMultiple = GroupMiningConfig::get_value('group_mining_user_num_enlarge_multiple', 22);//拼团挖矿人数放大倍数
        $miningUserNum = GroupMiningLog::whereTime('add_time', '-1 hours')->count('DISTINCT user_id');
        $info['mining_user_num'] = ($miningUserNum + 1) * $enlargeMultiple + mt_rand(1, 9);
        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = $info;
        return $this->output_new($r);
    }

    //矿源列表
    public function levels()
    {
        $res = GroupMiningSourceLevel::getLevelList();
        return $this->output_new($res);
    }

    //申购矿源
    public function buy_init() {
        $level_id = intval(input('level_id'));
        $res = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        $levelInfo = GroupMiningSourceLevel::get($level_id);
        if ($levelInfo['type'] != 1) return $this->output_new($res);
        $res = GroupMiningSourceBuy::buy_init($this->member_id,$level_id);
        return $this->output_new($res);
    }

    //申购矿源
    public function buy() {
        $level_id = intval(input('level_id'));
        $money_type = intval(input('money_type', 1));
        $currency_id = intval(input('currency_id', 0));
        $num = input('num', 0);
        $res = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        $levelInfo = GroupMiningSourceLevel::get($level_id);
        if ($levelInfo['type'] != 1) return $this->output_new($res);
        $res = GroupMiningSourceBuy::buy($this->member_id,$level_id,$money_type,$currency_id,$num);
        return $this->output_new($res);
    }

    //申购矿源记录
    public function buy_log() {
        $page = intval(input('page'));
        $res = GroupMiningSourceBuy::getList($this->member_id,$page);
        return $this->output_new($res);
    }

    //获取体验次数
    public function get_experience() {
        $res = GroupMiningSourceBuy::getExperience($this->member_id);
        return $this->output_new($res);
    }

    //挖矿
    public function mining() {
        $buy_id = intval(input('buy_id', 0));
        $type = intval(input('type', 1));//挖矿类型 1-单次挖矿 2-一键挖矿
        $res = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if (!$buy_id) return $this->output_new($res);
        $res = GroupMiningUser::mining($this->member_id, $buy_id, $type);
        return $this->output_new($res);
    }

    //一键挖矿
    public function onekey_mining() {
        $buy_id = intval(input('buy_id', 0));
        $res = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if (!$buy_id) return $this->output_new($res);
        $res = GroupMiningUser::onekey_mining($this->member_id, $buy_id);
        return $this->output_new($res);
    }

    //挖矿记录
    public function mining_log() {
        $page = intval(input('page'));
        $res = GroupMiningLog::getList($this->member_id,$page);
        return $this->output_new($res);
    }

    //签到释放首页
    public function free_index()
    {
        $res = GroupMiningForzen::getList($this->member_id);
        return $this->output_new($res);
    }

    //签到释放
    public function free() {
        $level_id = intval(input('level_id', 0));
        $res = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if (!$level_id) return $this->output_new($res);
        $res = GroupMiningUser::forzen_free($this->member_id, $level_id);
        return $this->output_new($res);
    }

    //释放记录
    public function free_log() {
        $level_id = intval(input('level_id', 0));
        $res = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if (!$level_id) return $this->output_new($res);
        $page = intval(input('page'));
        $res = GroupMiningForzenLog::getList($this->member_id,$level_id,$page);
        return $this->output_new($res);
    }

    //升级首页
    public function upgrade_index()
    {
        $res = GroupMiningUser::getUpgradeInfo($this->member_id);
        return $this->output_new($res);
    }

    //升级燃烧
    public function upgrade_burn() {
        $level_id = intval(input('level_id', 0));
        $res = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if (!$level_id) return $this->output_new($res);
        $res = GroupMiningUser::upgrade_burn($this->member_id, $level_id);
        return $this->output_new($res);
    }

    //转赠矿源
    public function give() {
        $buy_id = intval(input('buy_id', 0));
        $target_account = strval(input('target_account', ''));
        $res = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if (!$buy_id || !$target_account) return $this->output_new($res);
        $res = GroupMiningSourceBuy::give($this->member_id,$buy_id,$target_account);
        return $this->output_new($res);
    }
}
