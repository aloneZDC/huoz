<?php

namespace app\h5\controller;

use app\common\model\Currency;
use app\common\model\GroupMiningConfig;
use app\common\model\GroupMiningIncomeLog;
use app\common\model\GroupMiningSourceBuy;
use app\common\model\GroupMiningSourceLevel;
use app\common\model\GroupMiningUser;
use app\common\model\MemberBind;
use think\Db;
use think\Exception;

class GroupMining extends Base
{

    // 升级首页
    public function upgrade_index()
    {
        $res = GroupMiningUser::getUpgradeInfo($this->member_id);
        return $this->output_new($res);
    }

    // 升级燃烧
    public function upgrade_burn()
    {
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

    // 我的团队
    public function my_team()
    {
        $mineraInfo = GroupMiningUser::getUserInfo($this->member_id, true);

        $mining_source = GroupMiningSourceLevel::with('currency')->where('level_id',$mineraInfo['level_id'])->find(); // 矿源
        $result = [
            'mining_source_price' => $mining_source['price'],
            'mining_source_unit' => $mining_source['currency']['currency_name'],
            'pioneer_reward_rate' => $mineraInfo['pioneer_reward_rate'],
            'total_pioneer_reward1' => $mineraInfo['total_pioneer_reward1'],
            'pioneer_reward_currency_id1' => Currency::where('currency_id', $mineraInfo['pioneer_reward_currency_id1'])->value('currency_name'),
            'total_pioneer_reward2' => $mineraInfo['total_pioneer_reward2'],
            'pioneer_reward_currency_id2' => Currency::where('currency_id', $mineraInfo['pioneer_reward_currency_id2'])->value('currency_name'),
        ];

        $r = [
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => $result
        ];
        return $this->output_new($r);
    }

    // 团队列表
    public function team_list()
    {
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('row', 10, 'intval,filter_page');
        $child_id = input('child_id', 0, 'intval');
        $res = GroupMiningUser::getMyTeam($this->member_id, $child_id, $page, $page_size);
        return $this->output_new($res);
    }

    // 开拓奖设置
    public function set_pioneer()
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('parameter_error'),
            'result' => null
        ];
        $child_id = input('child_id', 0, 'intval');
        $rate = input('rate', 0, 'floatval');
        if (empty($child_id) || empty($rate)) return $this->output_new($r);

        $mineraInfo = GroupMiningUser::getUserInfo($this->member_id, true);
        $childMineraInfo = GroupMiningUser::getUserInfo($child_id);
        if (!$mineraInfo || !$childMineraInfo) return $this->output_new($r);

        $where = [
            'member_id' => $this->member_id,
            'child_id' => $child_id,
            'level' => 1,
        ];
        $bindFind = MemberBind::where($where)->find();
        if (!$bindFind) {
            $r['message'] = lang('lan_minera_starmap_auth_error');
            return $this->output_new($r);
        }
        if ($mineraInfo['pioneer_reward_rate'] <= 0) {
            $r['message'] = lang('lan_minera_starmap_auth_error');
            return $this->output_new($r);
        }
        if ($mineraInfo['pioneer_reward_rate'] <= $rate) {
            $r['message'] = lang('lan_minera_starmap_more');
            return $this->output_new($r);
        }
        if ($childMineraInfo['pioneer_reward_rate'] > 0) {
            $r['message'] = lang('lan_minera_starmap_already_set');
            return $this->output_new($r);
        }

        // 0.5的倍数
        $reward_rate = ($mineraInfo['pioneer_reward_rate'] - $rate) * 10;
        if (!isInteger($reward_rate) || $reward_rate <= 0) {
            $r['message'] = lang('lan_minera_illegal_proportion');
            return $this->output_new($r);
        }
        $rateMultiple = $reward_rate % 5;
        if ($rateMultiple != 0) {
            $r['message'] = lang('lan_minera_illegal_proportion');
            return $this->output_new($r);
        }

        // 判断是否满足等级
        $rewardLevel = GroupMiningConfig::get_value('group_mining_pioneer_reward_level', 4);//拼团挖矿开拓奖需申购的矿源等级
//        $SourceBuy = GroupMiningSourceBuy::where(['user_id' => $child_id, 'level_id' => $rewardLevel])->find();
        if ($childMineraInfo['level_id'] != $rewardLevel) {
            $r['message'] = lang('lan_minera_mineral_resources');
            return $this->output_new($r);
        }

        try {
            Db::startTrans();

            $flag = GroupMiningUser::where('id', $childMineraInfo['id'])->setField('pioneer_reward_rate', $rate);
            if ($flag === false) throw new Exception('更新用户信息失败-in line:' . __LINE__);

            Db::commit();

            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (\Exception $exception) {
            Db::rollback();
            $r['message'] = lang('system_error_please_try_again_later') . $exception->getMessage();
        }
        return $this->output_new($r);
    }

    // 奖励记录
    public function income_log()
    {
        $type = intval(input('type', 0));
        $res = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if (!$type) return $this->output_new($res);
        $page = intval(input('page'));
        $res = GroupMiningIncomeLog::getList($this->member_id,$type,$page);
        return $this->output_new($res);
    }

    // 奖励详情
    public function income_detail()
    {
        $income_id = intval(input('income_id', 0));
        $res = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if (!$income_id) return $this->output_new($res);
        $res = GroupMiningIncomeLog::getDetail($this->member_id,$income_id);
        return $this->output_new($res);
    }
}