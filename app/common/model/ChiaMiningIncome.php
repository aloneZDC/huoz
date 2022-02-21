<?php

namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;
use app\common\model\CurrencyUser;
use app\common\model\ChiaMiningMember;
use app\common\model\AccountBook;

/**
 * 奇亚矿机收入列表
 * Class AloneMiningIncome
 * @package app\common\model
 */
class ChiaMiningIncome extends Model
{
    /**
     * 推荐奖励1代2代发放
     * @param int $award_level 等级
     * @param int $member_id 用户ID
     * @param int $currency_id 币种ID
     * @param float $award_num 奖励数量
     * @param array $mining_pay 记录信息
     * @param int $today_start 时间
     * @return bool
     * @throws \think\exception\PDOException
     */
    static function recommand_award($award_level, $member_id, $currency_id, $award_num, $mining_pay, $today_start)
    {
        $account_book_content = 'chia_mining_help' . $award_level;
        if ($award_level == 1) {
            // 推荐1
            $account_book_id = 6641;
        } elseif ($award_level == 2) {
            // 推荐2
            $account_book_id = 6642;
        } else {
            return false;
        }

        $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);
        if (empty($currency_user)) return false;

        $ChiaMiningMember = ChiaMiningMember::where(['member_id' => $member_id])->find();
        try {
            self::startTrans();

            //添加奖励记录
            $item_id = self::insertGetId([
                'member_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'type' => $award_level,
                'num' => $award_num,
                'add_time' => time(),
                'award_time' => $today_start,
                'third_num' => $mining_pay['real_pay_num'],
                'third_id' => $mining_pay['id'],
            ]);
            if (!$item_id) throw new Exception("添加奖励记录");

            if ($award_num > 0) {
                // 增加用户汇总记录
                if ($ChiaMiningMember) {
                    $flag = ChiaMiningMember::where(['member_id' => $member_id, 'total_child' . $award_level => $ChiaMiningMember['total_child' . $award_level]])->update([
                        'total_child' . $award_level => ['inc', $award_num],
                    ]);
                    if (!$flag) throw new Exception("更新奖励总量失败");
                } else {
                    $flag = ChiaMiningMember::insertGetId([
                        'member_id' => $member_id,
                        'total_child' . $award_level => $award_num,
                        'add_time' => time(),
                    ]);
                    if (!$flag) throw new Exception("更新奖励总量失败");
                }

                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $award_num, $item_id);
                if (!$flag) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $award_num);
                if (!$flag) throw new Exception("添加资产失败");
            }

            self::commit();
            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("Chia矿机:失败" . $e->getMessage());
        }
        return false;
    }

    /**
     * 线性释放明细
     * @param int $member_id 用户ID
     * @param array $type 类型
     * @param int $page 页
     * @param int $product_id 包ID
     * @param int $rows 条
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function get_list($member_id, $page = 1, $product_id = 0, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];
        $where = [
            'a.member_id' => $member_id,
            'a.num' => ['gt', 0],
        ];

        if (!empty($type)) {
            $where['a.type'] = ['in', $type];
        }
        if (!empty($product_id)) {
            $where['a.third_id'] = $product_id;
        }

        $list = self::alias('a')->field('a.id,a.num,a.type,a.add_time,b.currency_name')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->where($where)
            ->page($page, $rows)->order("a.id desc")->select();
        if (!$list) return $r;

        foreach ($list as &$item) {
            $item['title'] = lang('chia_mining_release' . $item['type']);
            $item['add_time'] = date('Y-m-d H:i', $item['add_time']);
            $item['num'] = '+' . $item['num'];
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
    public function chiaminingpay() {
        return $this->belongsTo('app\\common\\model\\ChiaMiningPay', 'third_id', 'id')->alias('a')->join('member b', 'a.member_id=b.member_id')->field('a.member_id,a.id,a.tnum,b.email,b.phone,nick,b.name,b.ename,a.real_pay_num');
    }
    public function thirdmember() {
        return $this->belongsTo('app\\common\\model\\Member', 'third_member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }

    /**
     * 奖励列表
     * @param int $member_id 用户ID
     * @param int $page 页码
     * @param int $rows 条数
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function income_list($member_id, $page = 1, $rows = 10)
    {
        $result = [
            'info' => ['reward' => 0, 'today_reward' => 0],
            'list' => null
        ];
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => $result, 'total' => 0];

        $list = self::field('num,award_time')
            ->where(['member_id' => $member_id])
            ->page($page, $rows)
            ->order("id desc")
            ->select();
        if (!$list) return $r;

        $today = todayBeginTimestamp();
        foreach ($list as &$item) {
            $item['add_time'] = date('Y-m-d H:i', $item['award_time']);
            $item['release'] = '+' . $item['num'];
            $item['xch_name'] = 'XCH';
            $item['title'] = '奖励收益';
        }

        $start_time = strtotime(date('Y-m-d') . '-1 day');
        $end_time = $start_time + 86399;
        $reward = \app\common\model\ChiaMiningMember::where(['member_id' => $member_id])->value('reward');//总奖励
        $today_reward = self::where(['member_id' => $member_id, 'award_time' => ['between', [$start_time, $end_time]]])->sum('num');//昨日奖励
        $result['info'] = ['reward' => $reward == 0 ? 0 : $reward, 'today_reward' => $today_reward == 0 ? 0 :sprintf('%.6f', $today_reward)];
        $result['list'] = $list;

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        $r['total'] = self::field('num,add_time')->where(['member_id' => $member_id])->order("id desc")->count();
        return $r;
    }

    /**
     * 奖励记录
     * @param int $member_id 用户ID
     * @param int $currency_id 币种
     * @param int $type 类型 1提成
     * @param int $num  奖励
     * @param int $percent 奖励比例
     * @param int $third_num 奖励基数
     * @param int $third_id 支付表ID
     * @param int $third_member_id 支付表用户ID
     * @return bool
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function addIncome($member_id, $today, $currency_id, $type, $num=0, $percent=0, $third_num=0, $third_id=0, $third_member_id=0) {
        $flag = self::insertGetId([
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'type' => $type,
            'num' => $num,
            'third_percent' => $percent,
            'third_num' => $third_num,
            'third_id' => $third_id,
            'third_member_id' => $third_member_id,
            'award_time' => strtotime($today),
            'add_time' => time(),
        ]);
        return $flag;
    }
}