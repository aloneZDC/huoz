<?php

namespace app\admin\controller;

use app\common\model\GroupMiningBurnLog;
use app\common\model\GroupMiningConfig;
use app\common\model\GroupMiningForzen;
use app\common\model\GroupMiningForzenDetail;
use app\common\model\GroupMiningForzenLog;
use app\common\model\GroupMiningGoldLog;
use app\common\model\GroupMiningIncomeAirdropDetail;
use app\common\model\GroupMiningIncomeDetail;
use app\common\model\GroupMiningIncomeDividendDetail;
use app\common\model\GroupMiningIncomeFeeDetail;
use app\common\model\GroupMiningIncomeGoldDetail;
use app\common\model\GroupMiningIncomeLog;
use app\common\model\GroupMiningIncomePioneerDetail;
use app\common\model\GroupMiningLog;
use app\common\model\GroupMiningRewardLevel;
use app\common\model\GroupMiningRewardLevelLog;
use app\common\model\GroupMiningSourceLevel;
use app\common\model\GroupMiningSourceBuy;
use app\common\model\GroupMiningUser;
use app\common\model\Currency;
use think\Request;

class GroupMining extends Admin
{
    public $type = [1 => '申购', 2 => '体验', 3 => '他人赠送'];

    // 拼团挖矿参数设置
    public function config()
    {
        $configSelect = GroupMiningConfig::select();
        $configList = [];
        foreach ($configSelect as $key => $value) {
            $configList[] = $value;
        }
        $this->assign('configList', $configList);
        return $this->fetch();
    }

    // 修改拼团挖矿参数设置
    public function update_config(Request $request)
    {
        $config = $request->post('config/a');
        foreach ($config as $key => $value) {
            $data[] = [
                'gmc_key' => $key,
                'gmc_value' => $value,
            ];
        }
        $config = new GroupMiningConfig();
        $save = $config->saveAll($data);
        if ($save === false) {
            return $this->error('修改失败!请重试');
        }
        return $this->success('修改成功!');
    }

    // 拼团挖矿用户
    public function user(Request $request)
    {
        $where = [];
        if ($user_id = $request->param('user_id', 0)) $where['user_id'] = $user_id;

        $list = GroupMiningUser::where($where)->with(['user', 'sourceLevel'])->order('id', 'desc')->paginate(null, false, ['query' => $request->get()]);

        // 拼团挖矿拼团券奖励币种id
        $tickets_reward_currency_id = GroupMiningConfig::get_value('group_mining_tickets_reward_currency_id');
        // 拼团挖矿矿工费奖励币种id
        $fee_reward_currency_id = GroupMiningConfig::get_value('group_mining_fee_reward_currency_id');
        // 拼团挖矿拼团金奖励币种id
        $gold_reward_currency_id = GroupMiningConfig::get_value('group_mining_gold_reward_currency_id');
        // 拼团挖矿联创红利奖励币种id
        $dividend_reward_currency_id = GroupMiningConfig::get_value('group_mining_dividend_reward_currency_id');
        // 拼团挖矿联创红利奖励币种id
        $airdrop_reward_currency_id = GroupMiningConfig::get_value('group_mining_airdrop_reward_currency_id');

        // 拼团挖矿奖励等级升级燃烧支付币种id
        $burn_money_currency_id = GroupMiningConfig::get_value('group_mining_burn_money_currency_id');
        // 拼团挖矿奖励等级升级燃烧币种id
        $burn_currency_id = GroupMiningConfig::get_value('group_mining_burn_currency_id');

        // 币种列表
        $currencyList = array_column($this->currency, null, 'currency_id');

        // 联创红利奖励资格状态
        $dividend_auth_status = [0 => '无资格', 1 => '有资格', 2 => '已过期'];
        foreach ($list as &$value) {

            // 总奖励
            $value['total_reward'] = $value['total_tickets_reward'] + $value['total_fee_reward'] + $value['total_gold_reward']
                + $value['total_dividend_reward'] + $value['total_pioneer_reward1'];

            $value['total_tickets_reward'] .= "&nbsp;".$currencyList[$tickets_reward_currency_id]['currency_name'];
            $value['total_fee_reward'] .= "&nbsp;".$currencyList[$fee_reward_currency_id]['currency_name'];
            $value['total_gold_reward'] .= "&nbsp;".$currencyList[$gold_reward_currency_id]['currency_name'];
            $value['total_dividend_reward'] .= "&nbsp;".$currencyList[$dividend_reward_currency_id]['currency_name'];
            $value['total_airdrop_reward'] .= "&nbsp;".$currencyList[$airdrop_reward_currency_id]['currency_name'];

            $value['dividend_auth_status'] = $dividend_auth_status[$value['dividend_auth_status']];

            $value['total_pioneer_reward1'] .= "&nbsp;".$currencyList[$value['pioneer_reward_currency_id1']]['currency_name'];
            $value['pioneer_reward_currency_id1'] = $currencyList[$value['pioneer_reward_currency_id1']]['currency_name'].'('.$value['pioneer_reward_currency_id1'].')';

            $value['total_pioneer_reward2'] .= "&nbsp;".$currencyList[$value['pioneer_reward_currency_id2']]['currency_name'];
            $value['pioneer_reward_currency_id2'] = $currencyList[$value['pioneer_reward_currency_id2']]['currency_name'].'('.$value['pioneer_reward_currency_id2'].')';

            // 燃烧币种数量
            $value['burn_num'] .= "&nbsp;".$currencyList[$burn_currency_id]['currency_name'];
            // 燃烧支付币种数量
            $value['burn_money_num'] .= "&nbsp;".$currencyList[$burn_money_currency_id]['currency_name'];
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 修改开拓奖比例
    public function pioneer_reward_rate(Request $request)
    {
        $id = $request->post('id');
        $rewardRate = $request->post('reward_rate');
        $UserModel = new GroupMiningUser();
        $minerUser = $UserModel->where('id',$id)->find();
        if(empty($minerUser)) return successJson(ERROR1, '找不到数据');

        // 拼团挖矿开拓奖需申购的矿源等级
        $rewardLevel = GroupMiningConfig::get_value('group_mining_pioneer_reward_level', 4);
        if($minerUser->level_id != $rewardLevel) {
            return successJson(ERROR1, '没有订购相应矿源');
        }

        // 不能大于上级设置的
        $Superior = $this->memberLevel($minerUser->user_id);
        if($Superior > 0 && $rewardRate > $Superior) {
            return successJson(ERROR1, '开拓奖比例不能大于上级的：'.$Superior);
        }

        $flag = $UserModel->where('id',$id)->setField('pioneer_reward_rate',$rewardRate);
        if (!$flag) {
            return successJson(ERROR1, '系统错误，修改失败');
        }
        return successJson(SUCCESS, "修改成功");
    }

    // 根据用户ID查询
    public function memberLevel($member_id = 0) {
        $Superior = 0;
        $MemberLevel = \app\common\model\MemberBind::alias('t1')
            ->join([config("database.prefix") . 'group_mining_user' => 't2'], "t1.member_id = t2.user_id", "LEFT")
            ->field(['t1.member_id', 't1.child_id', 't2.user_id', 't2.pioneer_reward_rate'])
            ->where(['t1.child_id' => $member_id])
            ->where('t2.pioneer_reward_rate','>',0)
            ->order('t1.level', 'asc')->find();

        if(!empty($MemberLevel) && $MemberLevel->pioneer_reward_rate > 0) {
            $Superior = $MemberLevel->pioneer_reward_rate;
        }

        return $Superior;
    }

    // 拼团挖矿矿源等级
    public function source_level(Request $request)
    {
        $list = GroupMiningSourceLevel::with(['currency'])
            ->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();

        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 修改拼团挖矿矿源等级
    public function source_level_update(Request $request)
    {
        $mineraLevel = new GroupMiningSourceLevel();
        if ($request->isPost()) {
            $flag = $mineraLevel->update($request->post());
            if (!$flag) {
                return successJson(ERROR1, '系统错误，修改失败');
            }
            return successJson(SUCCESS, "修改成功");
        }

        $id = $request->param('id');
        $data = $mineraLevel->getLevelInfo($id);
        $currency = Currency::where('account_type', 'group')->field(['currency_id', 'currency_name'])->select();
        return $this->fetch(null, ['data' => $data, 'currency' => $currency, 'type' => $this->type]);
    }

    // 拼团挖矿矿源申购
    public function buy_log(Request $request)
    {
        $where = null;
        if ($id = $request->param('id', 0)) $where['id'] = $id;
        if ($user_id = $request->param('user_id', 0)) $where['user_id'] = $user_id;
        if ($type = $request->param('type', 0)) $where['type'] = $type;
        if ($status = $request->param('status', 0)) $where['status'] = $status;

        $list = GroupMiningSourceBuy::with(['member', 'currency'])->where($where)
            ->order("id desc")
            ->paginate(null, false, ['query' => $request->get()]);

        $status = [1 => '待开启', 2 => '已开启', 3 => '已结束', 4 => '已转赠'];
        foreach ($list as &$value) {
            $value['type'] = $this->type[$value['type']];
            $value['status'] = $status[$value['status']];
            $value['add_time'] = date('Y-m-d H:i', $value['add_time']);
            $value['start_time'] = date('Y-m-d H:i', $value['start_time']);
            $value['end_time'] = date('Y-m-d H:i', $value['end_time']);
        }

        $this->assign('type', $this->type);
        $this->assign('status', $status);

        $page = $list->render();
        return $this->fetch(null, ['list' => $list, 'page' => $page]);
    }

    // 拼团挖矿奖励等级
    public function reward_level(Request $request) {
        $where = null;
        $list = GroupMiningRewardLevel::where($where)
            ->paginate(null, false, ['query' => $request->get()]);

        foreach($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 修改拼团挖矿奖励等级
    public function reward_level_update(Request $request) {
        $RewardLevel = new GroupMiningRewardLevel();
        if ($request->isPost()) {
            $flag = $RewardLevel->update($request->post());
            if (!$flag) {
                return successJson(ERROR1, '系统错误，修改失败');
            }
            return successJson(SUCCESS, "修改成功");
        }

        $level_id = $request->param('level_id');
        $data = $RewardLevel->getLevelInfo($level_id);
        return $this->fetch(null, ['data' => $data]);
    }

    // 拼团挖矿用户奖励等级记录
    public function reward_level_log(Request $request) {
        $where = null;
        if ($user_id = $request->param('user_id', 0)) $where['user_id'] = $user_id;

        $list = GroupMiningRewardLevelLog::with(['member','rewlevel'])->where($where)
            ->order("id desc")
            ->paginate(null, false, ['query' => $request->get()]);

        foreach($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 拼团挖矿冻结
    public function mining_forzen(Request $request) {
        $where = null;
        if ($member_id = $request->param('user_id', 0)) $where['user_id'] = $member_id;
        if ($level_id = $request->param('level_id', 0)) $where['level_id'] = $level_id;

        $list = GroupMiningForzen::with(['member','sourceLevel','currency'])->where($where)
            ->order("id desc")
            ->paginate(null, false, ['query' => $request->get()]);

        foreach($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
            $value['last_free_time'] = date('Y-m-d H:i:s',$value['last_free_time']);
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 拼团挖矿冻结记录
    public function mining_forzen_log(Request $request) {
        $where = null;
        if ($user_id = $request->param('user_id', 0)) $where['user_id'] = $user_id;
        if ($level_id = $request->param('level_id', 0)) $where['level_id'] = $level_id;
        if ($type = $request->param('type', 0)) $where['type'] = $type;
        if ($status = $request->param('status', 0)) $where['status'] = $status;

        $list = GroupMiningForzenLog::with(['member','sourceLevel','currency','RewardCurrency'])->where($where)
            ->order('id','desc')
            ->paginate(null, false, ['query' => $request->get()]);

        $type = [1 => '冻结', 2 => '释放'];
        foreach($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
            $value['type'] = $type[$value['type']];
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count', 'type'));
    }

    // 拼团挖矿冻结记录详情
    public function mining_forzen_detail(Request $request) {
        $where = null;
        if ($member_id = $request->param('user_id', 0)) $where['user_id'] = $member_id;
        if ($type = $request->param('type', 0)) $where['type'] = $type;
        if ($level_id = $request->param('level_id', 0)) $where['level_id'] = $level_id;

        $list = GroupMiningForzenDetail::with(['member','sourceLevel','currency','RewardCurrency'])->where($where)
            ->order('id','desc')
            ->paginate(null, false, ['query' => $request->get()]);

        $type = [1 => '冻结', 2 => '释放'];
        foreach($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
            $value['type'] = $type[$value['type']];
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count', 'type'));
    }

    // 拼团挖矿记录
    public function mining_log(Request $request) {
        $where = null;
        if ($id = $request->param('id', 0)) $where['id'] = $id;
        if ($member_id = $request->param('user_id', 0)) $where['user_id'] = $member_id;
        if ($level_id = $request->param('level_id', 0)) $where['level_id'] = $level_id;

        $list = GroupMiningLog::with(['member','sourceLevel','MoneyCurrency','ForzenCurrency'])->where($where)
            ->order('id','desc')
            ->paginate(null, false, ['query' => $request->get()]);

        $mining_type = [1 => '单次挖矿', 2 => '一键挖矿'];
        $result = [1 => '赢', 2 => '输'];
        foreach($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
            $value['type'] = $this->type[$value['type']];
            $value['mining_type'] = $mining_type[$value['mining_type']];
            $value['result'] = $value['result']>0?$result[$value['result']]:'';
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 拼团挖矿奖励记录
    public function mining_income_log(Request $request) {
        $where = null;
        if ($id = $request->param('id', 0)) $where['id'] = $id;
        if ($member_id = $request->param('user_id', 0)) $where['user_id'] = $member_id;
        if ($type = $request->param('type', 0)) $where['type'] = $type;

        $list = GroupMiningIncomeLog::with(['member','currency'])->where($where)
            ->order('id','desc')
            ->paginate(null, false, ['query' => $request->get()]);

        $type = [1 => '拼团券奖励', 2 => '矿工费奖励', 3 => '拼团金奖励', 4 => '联创红利奖励', 5 => 'ABF空投奖励', 6 => '开拓奖'];
        foreach($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
            $value['type_name'] = $type[$value['type']];
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count', 'type'));
    }

    // 拼团挖矿奖励详情
    public function mining_income_detail(Request $request) {
        $where = null;
        if ($income_id = $request->param('income_id', 0)) $where['income_id'] = $income_id;
        if ($log_id = $request->param('log_id', 0)) $where['log_id'] = $log_id;
        if ($member_id = $request->param('user_id', 0)) $where['user_id'] = $member_id;
        if ($type = $request->param('type', 0)) $where['type'] = $type;

        $list = GroupMiningIncomeDetail::with(['member','sourceLevel','currency'])->where($where)
            ->order('id','desc')
            ->paginate(null, false, ['query' => $request->get()]);

        $type = [1 => '拼团券奖励', 2 => '矿工费奖励', 3 => '拼团金奖励'];
        foreach($list as &$value) {
            $value['type_name'] = $type[$value['type']];
            $value['reward_time'] = date('Y-m-d H:i:s',$value['reward_time']);
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count', 'type'));
    }

    // 拼团挖矿燃烧记录
    public function mining_burn_log(Request $request) {
        $where = null;
        if ($member_id = $request->param('user_id', 0)) $where['user_id'] = $member_id;
        if ($to_currency_id = $request->param('level_id', 0)) $where['level_id'] = $to_currency_id;

        $list = GroupMiningBurnLog::with(['member','sourceLevel','currency','MoneyCurrency'])->where($where)
            ->order('id','desc')
            ->paginate(null, false, ['query' => $request->get()]);

        foreach($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 拼团挖矿拼团金记录
    public function mining_gold_log(Request $request) {
        $where = null;
        if ($member_id = $request->param('user_id', 0)) $where['user_id'] = $member_id;
        if ($to_currency_id = $request->param('level_id', 0)) $where['level_id'] = $to_currency_id;

        $list = GroupMiningGoldLog::with(['member','sourceLevel','currency'])->where($where)
            ->order('id','desc')
            ->paginate(null, false, ['query' => $request->get()]);

        foreach($list as &$value) {
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
            $value['type'] = $this->type[$value['type']];
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 拼团挖矿ABF空投奖励详情
    public function mining_income_airdrop_detail(Request $request) {
        $where = null;
        if ($member_id = $request->param('user_id', 0)) $where['user_id'] = $member_id;
        if ($buy_id = $request->param('buy_id', 0)) $where['buy_id'] = $buy_id;
        if ($income_id = $request->param('income_id', 0)) $where['income_id'] = $income_id;

        $list = GroupMiningIncomeAirdropDetail::with(['member','sourceLevel','currency'])->where($where)
            ->order('id','desc')
            ->paginate(null, false, ['query' => $request->get()]);

        foreach($list as &$value) {
            $value['reward_time'] = date('Y-m-d H:i:s',$value['reward_time']);
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 拼团挖矿联创红利奖励详情
    public function mining_income_dividend_detail(Request $request) {
        $where = null;
        if ($member_id = $request->param('user_id', 0)) $where['user_id'] = $member_id;
        if ($income_id = $request->param('income_id', 0)) $where['income_id'] = $income_id;

        $list = GroupMiningIncomeDividendDetail::with(['member','currency'])->where($where)
            ->order('id','desc')
            ->paginate(null, false, ['query' => $request->get()]);

        foreach($list as &$value) {
            $value['reward_time'] = date('Y-m-d H:i:s',$value['reward_time']);
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 拼团挖矿矿工费(奖励等级)奖励详情
    public function mining_income_fee_detail(Request $request) {
        $where = null;
        if ($member_id = $request->param('user_id', 0)) $where['user_id'] = $member_id;
        if ($income_id = $request->param('income_id', 0)) $where['income_id'] = $income_id;
        if ($log_id = $request->param('log_id', 0)) $where['log_id'] = $log_id;

        $list = GroupMiningIncomeFeeDetail::with(['member','currency','rewlevel'])->where($where)
            ->order('id','desc')
            ->paginate(null, false, ['query' => $request->get()]);

        foreach($list as &$value) {
            $value['reward_time'] = date('Y-m-d H:i:s',$value['reward_time']);
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 拼团挖矿拼团金(奖励等级)奖励详情
    public function mining_income_gold_detail(Request $request) {
        $where = null;
        if ($member_id = $request->param('user_id', 0)) $where['user_id'] = $member_id;
        if ($income_id = $request->param('income_id', 0)) $where['income_id'] = $income_id;

        $list = GroupMiningIncomeGoldDetail::with(['member','currency'])->where($where)
            ->order('id','desc')
            ->paginate(null, false, ['query' => $request->get()]);

        foreach($list as &$value) {
            $value['reward_time'] = date('Y-m-d H:i:s',$value['reward_time']);
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    // 拼团挖矿开拓奖详情
    public function mining_income_pioneer_detail(Request $request) {
        $where = null;
        if ($member_id = $request->param('user_id', 0)) $where['user_id'] = $member_id;
        if ($buy_id = $request->param('buy_id', 0)) $where['buy_id'] = $buy_id;
        if ($income_id = $request->param('income_id', 0)) $where['income_id'] = $income_id;

        $list = GroupMiningIncomePioneerDetail::with(['member','sourceLevel','currency'])->where($where)
            ->order('id','desc')
            ->paginate(null, false, ['query' => $request->get()]);

        foreach($list as &$value) {
            $value['reward_time'] = date('Y-m-d H:i:s',$value['reward_time']);
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

}