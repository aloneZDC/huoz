<?php

namespace app\common\model;

use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class TransferToBalance extends Model
{
    const KEY = 'f1e54e5db338a7367854c18fa3328db8';

    /**
     * @param int $user_id
     * @param int $currency_id
     * @param int $to_address
     * @param double $to_num
     * @param string $check_box
     * @param string $address_name
     * @param string $address_num
     * @return mixed
     * @throws Exception
     */
    public static function transfer_out($user_id, $currency_id, $to_address, $to_num, $check_box, $address_name, $address_num)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        $currency = (new Currency)->where(['currency_id' => $currency_id])->find();
        if (empty($currency) || $currency['currency_transfer_switch'] != 1) {
            $r['message'] = lang('lan_close');;
            return $r;
        }
        //提币最小不能少于最小提币量
        if ($currency['currency_min_tibi'] > 0 && bccomp($currency['currency_min_tibi'], $to_num, 6) > 0) {
            $r['message'] = lang("tibi_money_less_min");
            return $r;
        }
        //提币最大不能大于最大提币量
        if ($currency['currency_all_tibi'] > 0 && bccomp($to_num, $currency['currency_all_tibi'], 6) > 0) {
            $r['message'] = lang("tibi_money_less_max");
            return $r;
        }

        //检测交易所是否存在该地址
        $result = self::check_address_is_exist($currency['currency_mark'], $to_address);
        if ($result['code'] != SUCCESS) {
            $r['message'] = $result['message'];
            return $r;
        }

        //手续费
        $fee = $currency['tcoin_fee'];
        /*if ($currency['currency_is_fee_rate'] == 1) {
            //百分比手续费
            $fee = keepPoint($to_num * ($currency['currency_fee_rate'] / 100), 6);
        } else {
            //固定手续费
            $fee = $currency['currency_suggest_fee'];
        }*/

        $total_num = $to_num;
        if ($fee > 0) {
            $to_num = $total_num - $fee;  //实际到账数量
            if ($to_num <= 0) {
                $r['message'] = lang('insufficient_fee_assets');
                return $r;
            }
            /*//如果手续费币种 和 本币种一样
            if ($currency['currency_fee_currency_id'] == $currency_id) {
                $to_num = $total_num - $fee;  //实际到账数量
                if ($to_num <= 0) {
                    $r['message'] = lang('insufficient_fee_assets');
                    return $r;
                }
            } else {
                //手续费余额不足
                $users_currency_fee = UsersCurrency::getUsersCurrency($user_id, $currency['currency_fee_currency_id']);
                if (empty($users_currency_fee) || $users_currency_fee['uc_num'] < $fee) {
                    $r['message'] = lang('insufficient_fee_assets');
                    return $r;
                }
            }*/
        }

        //余额不足
        $users_currency = CurrencyUser::getCurrencyUser($user_id, $currency_id);
        if (empty($users_currency) || $users_currency['num'] < $total_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        Db::startTrans();
        try {
            //添加转账记录 出账
            $log_id = self::add_transfer('out', $user_id, $currency_id, $currency['currency_mark'], $to_address, $total_num, $to_num, $fee/*, $currency['currency_fee_currency_id']*/);
            if (!$log_id) throw new Exception(lang('operation_failed_try_again'));

            //扣除手续费
            /*if ($fee > 0 && $currency['currency_fee_currency_id'] != $currency_id) {
                $accountbook_log = Accountbook::add_accountbook($user_id, $currency_id, 5, 'fee', "out", $fee, $log_id);
                if (!$accountbook_log) throw new Exception(lang('operation_failed_try_again'));

                $flag = UsersCurrency::where(['uc_id' => $users_currency_fee['uc_id'], 'uc_num' => $users_currency_fee['uc_num']])->setDec('uc_num', $fee);
                if (!$flag) throw new Exception(lang('operation_failed_try_again'));
            }*/
            /*$accountBook = new AccountBook();

            $accountBook->addLog([
                'member_id' => $user_id,
                'currency_id' => $currency_id,
                'number_type' => 2,
                'number' => $total_num,
                'type' => 201,
                'content' => 'transfer_out',
                ''
            ]);*/
            //扣除资产
            $accountbook_log = AccountBook::add_accountbook($user_id, $currency_id, 201, 'transfer_out', "out", $total_num, $log_id, $fee/*, $currency['currency_fee_currency_id']*/);
            if (!$accountbook_log) throw new Exception(lang('operation_failed_try_again'));
            $flag = (new CurrencyUser)->where(['cu_id' => $users_currency['cu_id'], 'num' => $users_currency['num']])->setDec('num', $total_num);
//            $flag = UsersCurrency::where(['uc_id' => $users_currency['uc_id'], 'uc_num' => $users_currency['uc_num']])->setDec('uc_num', $total_num);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            Db::commit();
            $r['message'] = lang('success_operation');
            $r['code'] = SUCCESS;
        } catch (Exception $e) {
            Db::rollback();
            $r['message'] = $e->getMessage();
        }

        if ($r['code'] == SUCCESS && $check_box == 2) {
            //添加一条地址本
            QianbaoAddress::addAddress($user_id, $address_name, $to_address, $currency_id, $address_num);
//            WalletAddressList::add_address_list($user_id, $to_address, $address_name, $currency['currency_bt_id'], $address_num);
        }
        return $r;
    }

    //检测该地址在交易所是否存在 用于转出
    public static function check_address_is_exist($currency_mark, $to_address)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("address_not_exists");
        $r['result'] = null;

        $transfer_check_url = Config::get_value('transfer_check_url', '');
        if (empty($transfer_check_url)) return $r;
        $result = self::post_to($transfer_check_url, ['currency_mark' => $currency_mark, 'to_address' => $to_address]);

        if ($result['code'] != SUCCESS) return $r;

        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        return $r;
    }


    /**
     * 从外部交易所转入
     * @param string $currency_mark 币种名称
     * @param string $to_address 区块链地址
     * @param double $to_num 数量
     * @param int $third_id 第三方表ID
     * @return array
     * @throws Exception
     */
    public static function transfer_in($currency_mark, $to_address, $to_num, $third_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (empty($currency_mark) || empty($to_address) || !is_numeric($to_num) || $to_num <= 0 || $third_id <= 0) return $r;

        //防止重复推送
        $transfer_info = (new TransferToBalance)->where(['ucb_third_id' => $third_id])->find();
        if (!empty($transfer_info)) {
            $r['message'] = lang('success_operation');
            $r['code'] = SUCCESS;
            return $r;
        }

        //检测本平台地址是否存在
        $result = self::check_self_address_is_exist($currency_mark, $to_address, true);
        if ($result['code'] != SUCCESS) return $result;

        //用户资产
        $users_currency = CurrencyUser::getCurrencyUser($result['wallet_address']['member_id'], $result['currency']['currency_id']);
//        $users_currency = UsersCurrency::getUsersCurrency($result['wallet_address']['wa_user_id'], $result['currency']['currency_id']);
        if (empty($users_currency)) return $r;

        try {
            Db::startTrans();

            //添加转账记录 入账
            $log_id = self::add_transfer('in', $result['wallet_address']['member_id'], $result['currency']['currency_id'], $result['currency']['currency_mark'], $to_address, $to_num, $to_num, 0, 0, $third_id);
            if (!$log_id) throw new Exception("添加记录失败");

            //添加账本记录
            $accountbook_log = AccountBook::add_accountbook($result['wallet_address']['member_id'], $result['currency']['currency_id'], 200, 'transfer_in', "in", $to_num, $log_id);
            if ($accountbook_log === false) throw new Exception("账本记录失败");

            $flag = (new CurrencyUser)->where(['cu_id' => $users_currency['cu_id'], 'num' => $users_currency['num']])->setInc('num', $to_num);
//            $flag = UsersCurrency::where(['uc_id' => $users_currency['uc_id'], 'uc_num' => $users_currency['uc_num']])->setInc('uc_num', $to_num);
            if (!$flag) throw new Exception("增加资产失败, flag: " . $flag . ' --- cu_id: ' . $users_currency['cu_id'] . ' --- num: ' . $users_currency['num']);

            Db::commit();
            $r['message'] = lang('success_operation');
            $r['code'] = SUCCESS;
        } catch (Exception $e) {
            Db::rollback();
            $r['message'] = $e->getMessage();
            // $r['debug'] = $e->getFile() . '---' . $e->getLine();
            // $r['sql'] = db()->getLastSql();
            $r['code'] = ERROR12;
        }
        return $r;
    }

    /**
     * 检测该地址在本平台是否存在 用于转入
     * @param string $currency_mark
     * @param string $to_address
     * @param bool $is_data
     * @return array
     * @throws Exception
     */
    public static function check_self_address_is_exist($currency_mark, $to_address, $is_data = false)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("address_not_exists");
        $r['result'] = null;

        if (empty($currency_mark) || empty($to_address)) return $r;

        $currency = (new Currency)->where(['currency_mark' => $currency_mark])->find();
        if (empty($currency) || $currency['currency_transfer_switch'] != 1) return $r;
        // 查找地址是否存在
        $wallet_address = (new CurrencyUser)->where(['currency_id' => $currency['currency_id'], 'chongzhi_url' => $to_address])->find();

//        $wallet_address = WalletAddress::where(['wa_bt_id' => $currency['currency_bt_id'], 'wa_address' => $to_address])->find();
        if (empty($wallet_address)) return $r;

        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        if ($is_data) {
            $r['currency'] = $currency;
            $r['wallet_address'] = $wallet_address;
        }
        return $r;
    }

    /**
     * @param string $type in 从交易所转入  out转入到交易所
     * @param int $user_id
     * @param int $currency_id
     * @param string $currency_mark
     * @param string $to_address 转入地址
     * @param double $to_num 转入数量
     * @param double $to_acutal 实际到账数量
     * @param int|double $fee 手续费
     * @param int $fee_currency_id 手续费币种
     * @param int $third_id 交易所唯一ID
     * @return int|string
     */
    public static function add_transfer($type, $user_id, $currency_id, $currency_mark, $to_address, $to_num, $to_acutal, $fee = 0, $fee_currency_id = 0, $third_id = 0)
    {
        $status = 2;
        if ($type == 'in') $status = 1;
        //外部推送过来的必须有第三方ID
        if ($type == 'in' && $third_id <= 0) return false;

        $time = time();
        return (new TransferToBalance)->insertGetId([
            'ucb_type' => $type,
            'ucb_user_id' => $user_id,
            'ucb_currency_id' => $currency_id,
            'ucb_currency_mark' => $currency_mark,
            'ucb_to_address' => $to_address,
            'ucb_num' => $to_num,
            'ucb_fee' => $fee,
            'bcb_fee_currency_id' => $fee_currency_id,
            'ucb_actual' => $to_acutal,
            'ucb_status' => $status, //状态 1推送成功 2推送中
            'ucb_count' => 0,
            'ucb_add_time' => $time,
            'ucb_last_time' => $time,
            'ucb_third_id' => $type == 'in' ? $third_id : null,
        ]);
    }

    public static function post_to($url, $data)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        try {
            $sign = createSign($data, self::KEY);
            $data['sign'] = $sign;

            $query = http_build_query($data);
            $options['http'] = array(
                'timeout' => 5,
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => $query
            );
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);

            if (!empty($result)) {
                $result = json_decode($result, true);
                if (!empty($result) && isset($result['code']) && $result['code'] == SUCCESS) {
                    $r['code'] = SUCCESS;
                    $r['message'] = lang('successful_operation');
                } else {
                    $r = $result;
                }
            }
        } catch (\Exception $e) {
            $r['message'] = $e->getMessage();
            Log::write("transfer post to error");
        }
        return $r;
    }

    public function feecurrency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'bcb_fee_currency_id', 'currency_id')->field('currency_id,currency_mark');
    }

    public function user()
    {
        return $this->belongsTo('app\\common\\model\\Member', 'ucb_user_id', 'member_id')->field('member_id,nick,email');
    }
}