<?php
//传统矿机 收入
namespace app\common\model;

use think\Log;
use think\Model;
use think\Db;
use think\Exception;

class CommonMiningIncome extends Base
{
    /**
     * @param $common_mining_member //矿机用户
     * @param $member_id //奖励用户
     * @param $currency_id //奖励币种
     * @param $award_level //奖励类型
     * @param $award_num //奖励可用数量
     * @param $base_id //来源第三方ID
     * @param $base_num //来源第三方数量
     * @param $release_id //释放ID
     * @param $base_percent //奖励可用比例
     * @param $today_start
     * @param int $third_member_id //来源第三方用户
     * @param int $award_lock_num //奖励锁仓数量 $base_num * $award_lock_percent
     * @param int $award_lock_percent //奖励锁仓比例
     * @param int $global_lock_num 20210225新增全球锁仓
     * @param int $global_lock_percent 20210225新增全球锁仓比例
     * @return bool
     */
    static function award($member_id, $currency_id, $award_level, $award_num, $base_id, $base_num, $release_id, $base_percent, $today_start, $third_member_id = 0, $award_lock_num = 0, $award_lock_percent = 0, $global_lock_num = 0, $global_lock_percent = 0)
    {
        $account_book_content = 'common_mining_award' . $award_level;
        if ($award_level == 1) {
            // 推荐1
            $account_book_id = 6500;
        } elseif ($award_level == 2) {
            // 推荐2
            $account_book_id = 6501;
        } elseif ($award_level == 3) {
            // 推荐3
            $account_book_id = 6502;
        } elseif ($award_level == 4) {
            // 级差
            $account_book_id = 6503;
        } elseif ($award_level == 7) {
            // 全球加权分红
            $account_book_id = 6509;
        } elseif ($award_level == 9) {
            // 合伙股东奖励
            $account_book_id = 6628;
        } elseif ($award_level == 10) {
            // 合伙股东技术服务费分红
            $account_book_id = 6629;
        } else {
            return false;
        }

        $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);
        if (empty($currency_user)) return false;

        try {
            self::startTrans();

            if ($award_num > 0) {
                $flag = CommonMiningMember::where(['member_id' => $member_id])->update([
                    'total_child' . $award_level => ['inc', $award_num],
                ]);
                if (!$flag) throw new Exception("更新奖励总量失败");
            }

            //添加奖励记录
            $item_id = self::insertGetId([
                'member_id' => $member_id,
                'currency_id' => $currency_id,
                'type' => $award_level,
                'num' => $award_num,
                'lock_num' => $award_lock_num ?: $global_lock_num,
                'add_time' => time(),
                'award_time' => $today_start,
                'third_percent' => $base_percent,
                'third_lock_percent' => $award_lock_percent ?: $global_lock_percent,
                'third_num' => $base_num,
                'third_id' => $base_id,
                'third_member_id' => $third_member_id,
                'release_id' => $release_id,
            ]);
            if (!$item_id) throw new Exception("添加奖励记录");

            if ($award_num > 0) {
                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $award_num, $item_id, 0);
                if (!$flag) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $award_num);
                if (!$flag) throw new Exception("添加资产失败");
            }

            if ($award_lock_num > 0) {
                $flag = CurrencyLockBook::add_log('common_lock_num', $account_book_content, $currency_user['member_id'], $currency_user['currency_id'], $award_lock_num, $base_id, $base_num, $award_lock_percent, $third_member_id);
                if (!$flag) throw new Exception("添加锁仓资产记录失败");

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'common_lock_num' => $currency_user['common_lock_num']])->setInc('common_lock_num', $award_lock_num);
                if (!$flag) throw new Exception("添加锁仓资产失败");
            }

            if ($global_lock_num > 0) {
                $flag = CurrencyLockBook::add_log('global_lock', $account_book_content, $currency_user['member_id'], $currency_user['currency_id'], $global_lock_num, $base_id, $base_num, $global_lock_percent, $third_member_id);
                if (!$flag) throw new Exception("添加锁仓资产记录失败");

                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'global_lock' => $currency_user['global_lock']])->setInc('global_lock', $global_lock_num);
                if (!$flag) throw new Exception("添加锁仓资产失败");
            }

            self::commit();

            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("传统矿机:失败" . $e->getMessage());
        }
        return false;
    }

    /**
     * 获取奖励记录
     * @param int $member_id 用户id
     * @param array $type 类型
     * @param int $page 页
     * @param int $rows 条
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getList($member_id, $type = [], $page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        $third_type = 0;
        if (!is_array($type)
            && $type == 7) {
            $type = [6, 7];
            $third_type = 7;
        }

        $where = [
            'a.member_id' => $member_id,
            'a.num' => ['gt', 0],
        ];
        if (!empty($type)) {
            $where['a.type'] = ['in', $type];
        }

        $list = self::alias('a')->field('a.id,a.currency_id,a.third_num,a.lock_num,a.num,a.type,a.add_time,b.currency_name,m.ename')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "member m", "a.third_member_id=m.member_id", "LEFT")
            ->where($where)
            ->page($page, $rows)->order("a.id desc")->select();
        if (!$list) return $r;

        foreach ($list as &$item) {
            if ($third_type == 7
                && $item['type'] == 6) {
                $item['num'] = $item['lock_num'];
            }

            $item['title'] = lang('common_mining_award' . $item['type']);
            $item['add_time'] = date('Y-m-d H:i', $item['add_time']);
            if (!$item['ename']) $item['ename'] = '';
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    static function getLockNum($member_id)
    {
        $config = CommonMiningConfig::get_key_value();
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $config['default_lock_currency_id']);

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'lock_num' => $currency_user ? $currency_user['common_lock_num'] : 0,
            'percent' => $config['lock_release_percent'],
        ];
        return $r;
    }

    public function users()
    {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }

    public function currency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function thirdmember()
    {
        return $this->belongsTo('app\\common\\model\\Member', 'third_member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
}
