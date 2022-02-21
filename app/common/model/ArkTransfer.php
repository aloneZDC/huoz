<?php


namespace app\common\model;

use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Model;

class ArkTransfer extends Model
{
    /**
     * 获取划转记录
     * @param int $member_id    用户ID
     * @param int $page        页
     * @param int $rows        页数
     */
    static function get_log($member_id, $page, $rows = 15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null, 'currency_num' => 0];

        $transfer_currency_id = ArkConfig::getValue('transfer_currency_id');
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $transfer_currency_id);
        if (empty($currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }
        $result = self::where(['member_id' => $member_id])
            ->page($page, $rows)
            ->select();
        if (!$result) return $r;
        foreach ($result as &$value) {
            if ($value['type'] == 1) {
                $value['title'] = '转入';
                $value['num'] = '+'.$value['num'];
            } else {
                $value['title'] = '转出';
                $value['num'] = '-'.$value['num'];
            }
            $value['add_time'] = date('Y-m-d H:i', $value['add_time']);
            $value['currency_name'] = 'USDT';
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        $r['result'] = $result;
        $r['currency_num'] = $currency_user['num'];
        return $r;
    }

    /**
     * 获取划转账户
     * @param int $member_id    用户ID
     */
    static function get_transfer_info($member_id) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];

        $transfer_currency_id = ArkConfig::getValue('transfer_currency_id');
        $transfer_out_id = ArkConfig::getValue('transfer_out_id');
        $where = [];
        $where['a.currency_id'] = ['in', [$transfer_currency_id, $transfer_out_id]];
        $where['a.member_id'] = $member_id;
        $result = CurrencyUser::alias('a')
            ->join('currency b', 'a.currency_id=b.currency_id')
            ->where($where)->field('a.currency_id,a.num,b.currency_name')->select();
        if (!$result) return $r;
        foreach ($result as &$value) {
            $value['currency_name'] = $value['currency_name'] . '(冻结中)';
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        $r['result'] = $result;
        return $r;
    }

    /**
     * 提交划转
     * @param int $member_id          用户ID
     * @param int $currency_id        转入币种
     * @param int $out_currency_id    转出币种
     * @param number $num             数量
     */
    static function add_transfer($member_id, $currency_id, $out_currency_id, $num) {
        $r = ['code' => ERROR1, 'message' => lang('lan_operation_failure'), 'result' => null];
        //转出账户
        $out_currency_user = CurrencyUser::getCurrencyUser($member_id, $out_currency_id);
        if (empty($out_currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }
        // 判断账户是否足够
        if ($out_currency_user['num'] < $num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        //转入账户
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $currency_id);
        if (empty($currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $transfer_currency_id = ArkConfig::getValue('transfer_currency_id');
        if ($currency_id == $transfer_currency_id) {
            $type = 1;//转入
        } else {
            $type = 2;//转出

            $start = strtotime(date('Y-m-d'));
            $end = $start + 86399;
            $check = self::where(['member_id' => $member_id, 'type' => 1, 'add_time' => ['BETWEEN', [$start, $end]]])->find();
            if ($check) {
                $r['message'] = '次日才能转出';
                return $r;
            }
        }

        Db::startTrans();
        try {
            $data = [
                'member_id' => $member_id,
                'type' => $type,
                'num' => $num,
                'add_time' => time()
            ];
            $item_id = self::insertGetId($data);
            if ($item_id === false) throw new Exception('添加划转记录失败');

            //增加账本 扣除资产
            $flag = AccountBook::add_accountbook($out_currency_user['member_id'], $out_currency_user['currency_id'], 5201, 'Wallet transfer', 'out', $num, $item_id, 0);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id' => $out_currency_user['cu_id'], 'num' => $out_currency_user['num']])->setDec('num', $num);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            //增加账本 增加资产
            $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 5201, 'Wallet transfer', 'in', $num, $item_id, 0);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $num);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_operation_success');
            $r['result'] = $flag;
        } catch (\Exception $e) {
            Db::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }
}