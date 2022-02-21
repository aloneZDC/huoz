<?php
//资产互转
namespace app\common\model;


use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Log;
use think\Model;

class CurrencyUserTransfer extends Model
{

    const TYPES = ['num', 'uc_card', 'uc_card_lock', 'dnc_lock', 'keep_num'];

    public static function get_config($user_id, $currency_id, $type = 'num')
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if (!in_array($type, self::TYPES)) return $r;

        $config = [
            'min_num' => 0,
            'max_num' => 0,
            'fee_type' => 0,
            'fee' => 0,
        ];
        $config_auto = CurrencyUserTransferConfig::where(['currency_id' => $currency_id, 'type' => $type])->find();
        if ($config_auto) {
            $config['min_num'] = $config_auto['min_num'];
            $config['max_num'] = $config_auto['max_num'];
            $config['fee_type'] = $config_auto['fee_type'];
            $config['fee'] = $config_auto['fee'];
        }

        $currency = Currency::where(['currency_id' => $currency_id])->field('currency_id,currency_name,exchange_switch')->find();
        if (empty($currency)) return $r;
        $config['currency'] = $currency;

        if ($currency['exchange_switch'] != 1 and 'num' == $type) {
            $r['code'] = ERROR5;
            $r['message'] = lang('lan_close');;
            return $r;
        }

        $currency_user = CurrencyUser::getCurrencyUser($user_id, $currency_id);
        $config['user_num'] = 0;
        if ($type == 'num') {
            $config['user_num'] = $currency_user ? $currency_user[StoresConvertConfig::NUM_FIELD] : 0;
        } elseif ($type == 'uc_card') {
            $config['user_num'] = $currency_user ? $currency_user[StoresConvertConfig::CARD_FIELD] : 0;
        } elseif ($type == 'uc_card_lock') {
            $config['user_num'] = $currency_user ? $currency_user[StoresConvertConfig::FINANCIAL_FIELD] : 0;
        } elseif ($type == 'dnc_lock') {
            $config['user_num'] = $currency_user ? $currency_user[StoresConvertConfig::DNC_LOCK] : 0;
        } elseif ($type == 'keep_num') {
            $config['user_num'] = $currency_user ? $currency_user[StoresConvertConfig::KEEP_NUM] : 0;
        }
        $r['result'] = $config;
        $r['message'] = lang('data_success');
        $r['code'] = SUCCESS;
        return $r;
    }

    /**
     * @param int $user_id 用户ID
     * @param int $currency_id 币种ID
     * @param int $target_user_id 目标用户ID
     * @param int $target_account 目标用户u账户
     * @param double $num 数量
     * @param string $type num可用互转  uc_card i券互转 dnc_lock DNC锁仓互转
     * @param string memo 备注
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws PDOException
     */
    public static function transfer($user_id, $currency_id, $target_user_id, $target_account, $num, $type = 'num', $memo = '')
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (!is_numeric($num) || $num <= 0 || !in_array($type, self::TYPES)) return $r;
        $num = keepPoint($num, 6);

        $config = [
            'min_num' => 0,
            'max_num' => 0,
            'fee_type' => 0, // 手续费类型 0比例 1固定值
            'fee' => 0,
            'is_open' => 2,
            'check_type' => 'num',
            'check_number' => 0,
            'check_currency_id' => 0
        ];
        $config_auto = CurrencyUserTransferConfig::where(['currency_id' => $currency_id, 'type' => $type])->find();
        if ($config_auto) $config = $config_auto;

        if ($config['min_num'] > 0 && $num < $config['min_num']) {
            $r['message'] = lang('lan_num_not_less_than') . $config['min_num'];
            return $r;
        }
        if ($config['max_num'] > 0 && $num > $config['max_num']) {
            $r['message'] = lang('lan_num_not_greater_than') . $config['max_num'];
            return $r;
        }

        $currency = Currency::where(['currency_id' => $currency_id])->field('currency_id,currency_name,exchange_switch')->find();
        if (empty($currency)) return $r;

        if (($currency['exchange_switch'] != 1 and $type == 'num') || $config['is_open'] != 1) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        if (empty($target_user_id)) {
            $r['message'] = lang('lan_account_member_not_exist');
            return $r;
        }

        if ($user_id == $target_user_id) {
            $r['message'] = lang('lan_can_not_transfer_yourself');
            return $r;
        }

        $member = Member::where(['member_id' => $user_id])->field('member_id,phone,email')->find();
        if (!$member) {
            $r['message'] = lang('lan_account_member_not_exist');
            return $r;
        }

        //接收用户信息
        $to_member = Member::where(['member_id' => $target_user_id])->field('member_id,phone,email,ename')->find();
        if (!$to_member) {
            $r['message'] = lang('lan_account_member_not_exist');
            return $r;
        }

        if ($config['check_currency_id']) {
            $checkCurrencyUser = CurrencyUser::getCurrencyUser($user_id, $config['check_currency_id']);
            if ($checkCurrencyUser[$config['check_type']] < $config['check_number']) {
                $checkCurrency = Currency::where(['currency_id' => $config['check_currency_id']])->field('currency_id,currency_name')->find();
                $typeEnum = [
                    'num' => '可用',
                    'dnc_lock' => '鎖倉'
                ];
                $r['message'] = lang('assess_balance_must_surplus', [
                    'num' => (double)$config['check_number'],
                    'name' => $checkCurrency['currency_name'],
                    'type' => $typeEnum[$config['check_type']]
                ]);
                return $r;
            }
        }

        $currency_user = CurrencyUser::getCurrencyUser($user_id, $currency_id);

        if (!$currency_user) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        // $targetCurrencyUsr = CurrencyUser::getCurrencyUser($target_currency_user, $currency_id); // 初始化不存在的币种资产数据
        $fee = 0;
        $actual = $num; //实际到账

        // 计算手续费
        if ($config['fee'] > 0) {
            if ($config['fee_type'] == 1) { // 固定值
                $fee = (double)$config['fee'];
            } else { // 百分比
                $fee = keepPoint($num * $config['fee'] / 100, 6);
            }
//            $fee = keepPoint($num * $config['fee']/100,6);
            $num += $fee;
            // $actual = keepPoint($num-$fee,6);
        }

        if ($type == 'num' && $currency_user[StoresConvertConfig::NUM_FIELD] < $num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        } elseif ($type == 'uc_card' && $currency_user[StoresConvertConfig::CARD_FIELD] < $num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        } elseif ($type == 'uc_card_lock' && $currency_user[StoresConvertConfig::FINANCIAL_FIELD] < $num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        } elseif ($type == 'dnc_lock' && $currency_user[StoresConvertConfig::DNC_LOCK] < $num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        } elseif ($type == 'keep_num' && $currency_user[StoresConvertConfig::KEEP_NUM] < $num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $target_currency_user = CurrencyUser::getCurrencyUser($target_user_id, $currency_id);
        if (!$target_currency_user) return $r;

        try {
            self::startTrans();

            //添加互转记录
            $cut_add_time = time();
            $log_id = self::insertGetId([
                'cut_user_id' => $user_id,
                'cut_currency_id' => $currency_id,
                'cut_target_user_id' => $target_user_id,
                'cut_num' => $num,
                'cut_type' => $type,
                'cut_add_time' => $cut_add_time,
                'cut_fee' => $fee,
                'cut_memo' => $memo,
                'cut_hash' => getNonceStr(),
            ]);
            if (!$log_id) throw new Exception(lang('operation_failed_try_again'));

            if ($type == 'num') {
                //添加转出账本
                $flag = AccountBook::add_accountbook($user_id, $currency_id, 600, 'asset_transfer_out', 'out', $num, $log_id, $fee);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                //扣除资产
                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], StoresConvertConfig::NUM_FIELD => $currency_user[StoresConvertConfig::NUM_FIELD]])->setDec(StoresConvertConfig::NUM_FIELD, $num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                //添加转入账本
                $flag = AccountBook::add_accountbook($target_user_id, $currency_id, 600, 'asset_transfer_in', 'in', $actual, $log_id, $fee);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                //增加资产
                $flag = CurrencyUser::where(['cu_id' => $target_currency_user['cu_id'], StoresConvertConfig::NUM_FIELD => $target_currency_user[StoresConvertConfig::NUM_FIELD]])->setInc(StoresConvertConfig::NUM_FIELD, $actual);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            } elseif ($type == 'uc_card') {
                //扣除资产
                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], StoresConvertConfig::CARD_FIELD => $currency_user[StoresConvertConfig::CARD_FIELD]])->setDec(StoresConvertConfig::CARD_FIELD, $num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = StoresCardLog::add_log('transfer_out', $user_id, $currency_id, $num, $log_id, 0, 0);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                //增加资产
                $flag = CurrencyUser::where(['cu_id' => $target_currency_user['cu_id'], StoresConvertConfig::CARD_FIELD => $target_currency_user[StoresConvertConfig::CARD_FIELD]])->setInc(StoresConvertConfig::CARD_FIELD, $actual);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = StoresCardLog::add_log('transfer_in', $target_user_id, $currency_id, $actual, $log_id, 0, 0);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            } elseif ($type == 'uc_card_lock') {
                //扣除资产
                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], StoresConvertConfig::FINANCIAL_FIELD => $currency_user[StoresConvertConfig::FINANCIAL_FIELD]])->setDec(StoresConvertConfig::FINANCIAL_FIELD, $num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = StoresFinancialLog::add_log('transfer_out', $user_id, $currency_id, $num, $log_id, 0, 0);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                //增加资产
                $flag = CurrencyUser::where(['cu_id' => $target_currency_user['cu_id'], StoresConvertConfig::FINANCIAL_FIELD => $target_currency_user[StoresConvertConfig::FINANCIAL_FIELD]])->setInc(StoresConvertConfig::FINANCIAL_FIELD, $actual);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = StoresFinancialLog::add_log('transfer_in', $target_user_id, $currency_id, $actual, $log_id, 0, 0);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            } elseif ($type == 'dnc_lock') {
                // 扣除资产
                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], StoresConvertConfig::DNC_LOCK => $currency_user[StoresConvertConfig::DNC_LOCK]])->setDec(StoresConvertConfig::DNC_LOCK, $num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = DcLockLog::add_log(DcLockLog::TYPE_TRANSFER_OUT, $user_id, $currency_id, $num, $log_id);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                // 增加资产
                $flag = CurrencyUser::where(['cu_id' => $target_currency_user['cu_id'], StoresConvertConfig::DNC_LOCK => $target_currency_user[StoresConvertConfig::DNC_LOCK]])->setInc(StoresConvertConfig::DNC_LOCK, $actual);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = DcLockLog::add_log(DcLockLog::TYPE_TRANSFER_IN, $target_user_id, $currency_id, $actual, $log_id);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            } elseif ($type == 'keep_num') {
                // 扣除资产
                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], StoresConvertConfig::KEEP_NUM => $currency_user[StoresConvertConfig::KEEP_NUM]])->setDec(StoresConvertConfig::KEEP_NUM, $num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = HongbaoKeepLog::add_log('transfer_out', $user_id, $currency_id, $num, $log_id);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                // 增加资产
                $flag = CurrencyUser::where(['cu_id' => $target_currency_user['cu_id'], StoresConvertConfig::KEEP_NUM => $target_currency_user[StoresConvertConfig::KEEP_NUM]])->setInc(StoresConvertConfig::KEEP_NUM, $actual);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = HongbaoKeepLog::add_log('transfer_in', $target_user_id, $currency_id, $actual, $log_id);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            } else {
                throw new Exception(lang('operation_failed_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_operation_success');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }

        return $r;
    }

    public function users()
    {
        return $this->belongsTo('app\\common\\model\\Member', 'cut_user_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }

    public function currency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'cut_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function targetusers()
    {
        return $this->belongsTo('app\\common\\model\\Member', 'cut_target_user_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }

    /**
     * @param int $user_id 用户ID
     * @param int $currency_id 币种ID
     * @param int $target_user_id 目标用户ID
     * @param int $target_account 目标用户u账户
     * @param double $num 数量
     * @param string $type num可用互转  uc_card i券互转 dnc_lock DNC锁仓互转
     * @param string memo 备注
     */
    public static function subscribe_transfer($user_id, $currency_id, $target_user_id, $target_account, $num, $type = 'num', $memo = '')
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (!is_numeric($num) || $num <= 0 || !in_array($type, self::TYPES)) return $r;
        $num = keepPoint($num, 6);

        $config = [
            'min_num' => 0,
            'max_num' => 0,
            'fee_type' => 0, // 手续费类型 0比例 1固定值
            'fee' => 0,
            'is_open' => 2,
            'check_type' => 'num',
            'check_number' => 0,
            'check_currency_id' => 0
        ];
        $config_auto = CurrencyUserTransferConfig::where(['currency_id' => $currency_id, 'type' => $type])->find();
        if ($config_auto) $config = $config_auto;
        if ($config['min_num'] > 0 && $num < $config['min_num']) {
            $r['message'] = lang('lan_num_not_less_than') . $config['min_num'];
            return $r;
        }
        if ($config['max_num'] > 0 && $num > $config['max_num']) {
            $r['message'] = lang('lan_num_not_greater_than') . $config['max_num'];
            return $r;
        }

        $currency = Currency::where(['currency_id' => $currency_id])->field('currency_id,currency_name,exchange_switch')->find();
        if (empty($currency)) return $r;

        if (($currency['exchange_switch'] != 1 and $type == 'num') || $config['is_open'] != 1) {
            $r['message'] = lang('lan_close');;
            return $r;
        }

        if (empty($target_user_id)) {
            $r['message'] = lang('lan_account_member_not_exist');
            return $r;
        }

        if ($user_id == $target_user_id) {
            $r['message'] = lang('lan_can_not_transfer_yourself');
            return $r;
        }

        $member = Member::where(['member_id' => $user_id])->field('member_id,phone,email')->find();
        if (!$member) {
            $r['message'] = lang('lan_account_member_not_exist');
            return $r;
        }

        //接收用户信息
        $to_member = Member::where(['member_id' => $target_user_id])->field('member_id,phone,email,ename')->find();
        if (!$to_member) {
            $r['message'] = lang('lan_account_member_not_exist');
            return $r;
        }
        //判断手机号或者邮箱是否相符
        if ($to_member['phone'] != $target_account && $to_member['email'] != $target_account) {
            $r['message'] = lang('lan_account_member_not_exist');
            return $r;
        }

        if ($config['check_currency_id']) {
            $checkCurrencyUser = CurrencyUser::getCurrencyUser($user_id, $config['check_currency_id']);
            if ($checkCurrencyUser[$config['check_type']] < $config['check_number']) {
                $checkCurrency = Currency::where(['currency_id' => $config['check_currency_id']])->field('currency_id,currency_name')->find();
                $typeEnum = [
                    'num' => '可用',
                    'dnc_lock' => '鎖倉'
                ];
                $r['message'] = lang('assess_balance_must_surplus', [
                    'num' => (double)$config['check_number'],
                    'name' => $checkCurrency['currency_name'],
                    'type' => $typeEnum[$config['check_type']]
                ]);
                return $r;
            }
        }

        $currency_user = CurrencyUser::getCurrencyUser($user_id, $currency_id);
        if (!$currency_user) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $fee = 0;
        $actual = $num; //实际到账
        // 计算手续费
        if ($config['fee'] > 0) {
            if ($config['fee_type'] == 1) { // 固定值
                $fee = (double)$config['fee'];
            } else { // 百分比
                $fee = keepPoint($num * $config['fee'] / 100, 6);
            }
            $num += $fee;
        }

        if ($type == 'num' && $currency_user[StoresConvertConfig::NUM_FIELD] < $num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        $target_currency_user = CurrencyUser::getCurrencyUser($target_user_id, $currency_id);
        if (!$target_currency_user) return $r;

        try {
            self::startTrans();

            //添加互转记录
            $cut_add_time = time();
            $log_id = self::insertGetId([
                'cut_user_id' => $user_id,
                'cut_currency_id' => $currency_id,
                'cut_target_user_id' => $target_user_id,
                'cut_num' => $num,
                'cut_type' => $type,
                'cut_add_time' => $cut_add_time,
                'cut_fee' => $fee,
                'cut_memo' => $memo,
                'cut_hash' => getNonceStr(),
            ]);
            if (!$log_id) throw new Exception(lang('operation_failed_try_again'));

            if ($type == 'num') {
                //添加转出账本
                $flag = AccountBook::add_accountbook($user_id, $currency_id, 600, 'asset_transfer_out', 'out', $num, $log_id, $fee);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                //扣除资产
                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], StoresConvertConfig::NUM_FIELD => $currency_user[StoresConvertConfig::NUM_FIELD]])->setDec(StoresConvertConfig::NUM_FIELD, $num);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                //添加转入账本
                $flag = AccountBook::add_accountbook($target_user_id, $currency_id, 600, 'asset_transfer_in', 'in', $actual, $log_id, $fee);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                //增加资产
                $flag = CurrencyUser::where(['cu_id' => $target_currency_user['cu_id'], StoresConvertConfig::NUM_FIELD => $target_currency_user[StoresConvertConfig::NUM_FIELD]])->setInc(StoresConvertConfig::NUM_FIELD, $actual);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            } else {
                throw new Exception(lang('operation_failed_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_operation_success');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }

        return $r;
    }
}
