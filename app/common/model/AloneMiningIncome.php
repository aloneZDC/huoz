<?php

namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

/**
 * 独享矿机收入列表
 * Class AloneMiningIncome
 * @package app\common\model
 */
class AloneMiningIncome extends Model
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
        $account_book_content = 'alone_mining_help' . $award_level;
        if ($award_level == 1) {
            // 推荐1
            $account_book_id = 6625;
        } elseif ($award_level == 2) {
            // 推荐2
            $account_book_id = 6626;
        } elseif ($award_level == 3) {
            // 推荐3
            $account_book_id = 6627;
        } else {
            return false;
        }

        $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);
        if (empty($currency_user)) return false;

        $AloneMiningMember = AloneMiningMember::where(['member_id' => $member_id])->find();
        try {
            self::startTrans();

            //添加奖励记录
            $item_id = AloneMiningIncome::insertGetId([
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
                if ($AloneMiningMember) {
                    $flag = AloneMiningMember::where(['member_id' => $member_id, 'total_child' . $award_level => $AloneMiningMember['total_child' . $award_level]])->update([
                        'total_child' . $award_level => ['inc', $award_num],
                    ]);
                    if (!$flag) throw new Exception("更新奖励总量失败");
                } else {
                    $flag = AloneMiningMember::insertGetId([
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
            Log::write("独享矿机:失败" . $e->getMessage());
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
    static function get_list($member_id, $type = [], $page = 1, $product_id = 0, $rows = 10)
    {
        $info = ['total_lock_yu' => 0, 'total_lock_num' => 0, 'total_release_num' => 0, 'today_lock_num' => 0, 'today_num' => 0];
        $result = ['info' => $info, 'list' => []];
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => $result];
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
            $item['title'] = lang('alone_mining_release' . $item['type']);
            $item['add_time'] = date('Y-m-d H:i', $item['add_time']);
            $item['num'] = '+' . $item['num'];
        }
        $res = \app\common\model\AloneMiningPay::where(['member_id' => $member_id, 'id' => $product_id])->find();
        $start_time = strtotime(date('Y-m-d', time()));
        $end_time = $start_time + 86399;
        $today_lock_num = \app\common\model\AloneMiningRelease::where(['member_id' => $member_id, 'third_id' => $product_id, 'release_time' => ['between', [$start_time, $end_time]]])->value('lock_num');
        $total_lock_num = keepPoint($res['total_lock_yu'] + $res['total_lock_num'], 6);
        $today_num = self::where(['member_id' => $member_id, 'third_id' => $product_id, 'type' => 4, 'award_time' => ['between', [$start_time, $end_time]]])->value('num');
        $info = [
            'total_lock_yu' => $res['total_lock_yu'] == 0 ? 0 : keepPoint($res['total_lock_yu'], 6),//剩余锁仓
            'total_lock_num' => $total_lock_num == 0 ? 0 : $total_lock_num,//累计锁仓
            'total_release_num' => $res['total_lock_num'] == 0 ? 0 : keepPoint($res['total_lock_num'], 6),//累计释放
            'today_lock_num' => $today_lock_num == 0 ? 0 : keepPoint($today_lock_num, 6),//今日锁仓
            'today_num' => $today_num == 0 ? 0 : keepPoint($today_num, 6),//今日释放
        ];
        $result['info'] = $info;
        $result['list'] = $list;

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    /**
     * 奖励记录
     * @param int $member_id 用户ID
     * @param int $currency_id 币种
     * @param int $type 类型 3提成
     * @param int $num  奖励
     * @param int $percent 奖励比例
     * @param int $third_num 奖励基数
     * @param int $third_id 支付表ID
     * @return bool
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function addIncome($member_id, $today, $currency_id, $type, $num=0, $percent=0, $third_num=0, $third_id=0) {
        $flag = self::insertGetId([
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'type' => $type,
            'num' => $num,
            'third_percent' => $percent,
            'third_num' => $third_num,
            'third_id' => $third_id,
            'award_time' => strtotime($today),
            'add_time' => time(),
        ]);
        return $flag;
    }

    /**
     * 奖励明细
     * @param int $member_id 用户ID
     * @param int $page 页
     * @param int $rows 条
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function reward_list($member_id, $page = 1, $rows = 10) {
        $info = ['today_reward_num' => 0, 'total_reward_num' => 0];
        $result = ['info' => $info, 'list' => []];
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => $result];
        $where = [
            'a.member_id' => $member_id,
            'a.num' => ['gt', 0],
            'a.type' => 3//提成奖励
        ];

        $list = self::alias('a')->field('a.id,a.num,a.type,a.add_time,b.currency_name')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->where($where)
            ->page($page, $rows)->order("a.id desc")->select();
        if (!$list) return $r;

        foreach ($list as &$item) {
            $item['title'] = lang('alone_mining_release' . $item['type']);
            $item['add_time'] = date('Y-m-d H:i', $item['add_time']);
            $item['num'] = '+' . $item['num'];
        }
        $reward = \app\common\model\AloneMiningMember::where(['member_id' => $member_id])->value('reward');
        $start_time = strtotime(date('Y-m-d', time()));
        $end_time = $start_time + 86399;
        $num = \app\common\model\AloneMiningIncome::where(['member_id' => $member_id, 'type' => 3, 'award_time' => ['between', [$start_time, $end_time]]])->sum('num');
        $info = [
            'today_reward_num' => $num == 0 ? 0 : keepPoint($num, 6),
            'total_reward_num' => $reward == 0 ? 0 : keepPoint($reward, 6),
        ];
        $result['info'] = $info;
        $result['list'] = $list;

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
    public function aloneminingpay() {
        return $this->belongsTo('app\\common\\model\\AloneMiningPay', 'third_id', 'id')->alias('a')->join('member b', 'a.member_id=b.member_id')->field('a.member_id,a.id,a.tnum,b.email,b.phone,nick,b.name,b.ename,a.real_pay_num');
    }
}