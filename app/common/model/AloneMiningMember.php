<?php

namespace app\common\model;

use think\Db;
use think\Exception;
use think\Log;
use app\common\model\CurrencyUser;
use app\common\model\AccountBook;


/**
 * 独享矿机用户汇总
 * Class AloneMiningMember
 * @package app\common\model
 */
class AloneMiningMember extends Base
{
    /**
     * 添加矿机用户汇总
     * @param int $member_id 用户ID
     * @param int $pay_tnum 购买T数
     * @return int
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function addItem($member_id,$pay_tnum=0) {
        try{
            $info = self::where(['member_id'=>$member_id])->find();
            if($info) {
                $flag = true;
                if ($pay_tnum > 0) {
                    $flag = self::where(['member_id'=>$member_id])->update([
                        'pay_tnum' => $pay_tnum,
                    ]);
                }
            } else {
                $flag = self::insertGetId([
                    'member_id' => $member_id,
                    'pay_tnum' => $pay_tnum,
                    'add_time' => time(),
                ]);
            }

            return $flag;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }

    public function pays() {
        return $this->belongsTo('app\\common\\model\\AloneMiningPay', 'member_id', 'member_id')->field('member_id,sum(total_release_num) as total_release_num,sum(total_lock_num) as total_lock_num,sum(total_lock_yu) as total_lock_yu,sum(total_lock_pledge) as total_lock_pledge,sum(total_thaw_pledge) as total_thaw_pledge')->group('member_id');
    }

    /**
     * 提成计算
     * @param int $member_id 用户ID
     * @param date $today 日期
     * @param array $mining_archive 数据
     * @param int $currency_id 币种
     * @return bool
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function addCommission($member_id, $yestday, $mining_archive, $mining_config) {
        $res = self::alias('a')->join(config("database.prefix") . 'member_bind b', 'a.member_id=b.member_id')->field('a.member_id')->where(['b.child_id' => $member_id])->order('b.level ASC')->select();
        if (!$res) return true;

        foreach ($res as $key => $value) {
            try {
                $rate = \app\common\model\AloneMiningCommission::where(['member_id' => $value['member_id'], 'child_id' => $member_id])->value('rate');//提成率
                //不存在提成配置就跳过
                if (empty($rate) && $mining_config['commission_rate'] == 0) {
                    continue;
                }

                $currency_user = CurrencyUser::getCurrencyUser($value['member_id'], $mining_config['release_currency_id']);
                if (empty($currency_user)) return false;

                Db::startTrans();
                $income = sprintf('%.6f', $mining_archive['num'] * ($rate / 100));

                if (!empty($income)) {
                    // 添加级差记录
                    $item_id = \app\common\model\AloneMiningIncome::addIncome($value['member_id'], $yestday, $mining_config['release_currency_id'], 3, $income, $rate, $mining_archive['num'], $mining_archive['id']);
                    //账本类型
                    $account_book_id = 6630;
                    $account_book_content = 'alone_mining_income';

                    //增加账本 增加资产
                    $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $income, $item_id);
                    if (!$flag) throw new Exception("添加账本失败");

                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $income);
                    if (!$flag) throw new Exception("添加资产失败");

                    self::where(['member_id' => $value['member_id']])->setInc('reward', $income);
                }
                Db::commit();

            } catch (Exception $e) {
                Db::rollback();
                Log::write("Chia矿机:失败" . $e->getMessage());
            }
        }

        return true;
    }
}