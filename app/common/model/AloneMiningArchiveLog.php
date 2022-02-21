<?php


namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;
use app\common\model\AloneMiningProduct;

//每日预封存算力记录
class AloneMiningArchiveLog extends Model
{
    /**
     * 添加记录
     * @param int $member_id 用户id
     * @param int $product_id 订单id
     * @param int $amount   封存数量
     * @return int
     * */
    static function addItem($member_id, $product_id, $amount) {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];
        if ($member_id <= 0) return $r;
        // 获取配置
        $alone_mining_config = AloneMiningConfig::get_key_value();
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $alone_mining_config['archive_currency_id']);
        $currency_name = Currency::where(['currency_id' => $alone_mining_config['archive_currency_id']])->value('currency_name');

        // 查询硬件
        $alone_mining_pay = AloneMiningPay::where(['id' => $product_id, 'member_id' => $member_id])->find();
        if (empty($alone_mining_pay)) {
            $r['message'] = lang('operation_failed_try_again');
            return $r;
        }
        if ($alone_mining_pay['start_day'] > time()) {
            $r['message'] = '暂未交付，无法封存';
            return $r;
        }

        $gas_fee = AloneMiningProduct::average_out();
        $preGas_1T = ($gas_fee['preGas_1T'] / 2) + $alone_mining_pay['gas_fee'];
        $pregas = keepPoint($amount * $preGas_1T, 6);
        $payment = keepPoint($amount * $gas_fee['payment_1T'], 6);
        $real_pay_num = keepPoint($pregas + $payment, 6);

        //把之前的数据状态更新为删除
        $flag = self::where(['member_id' => $member_id, 'mining_pay_id' => $product_id, 'status' => 0])->update(['status' => 2]);
        if ($flag === false) {
            return $r;
        }

        //插入记录
        $item_id = self::insertGetId([
            'member_id' => $currency_user['member_id'],
            'mining_pay_id' => $product_id,
            'tnum' => $amount,
            'pregas' => $pregas,
            'payment' => $payment,
            'real_pay_num' => $real_pay_num,
            'real_pay_currency_id' => $currency_user['currency_id'],
            'add_time' => time(),
        ]);
        if ($item_id === false) {
            return $r;
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'tnum' => $amount,
            'preGas_1T' => $pregas,
            'payment_1T' => $payment,
            'real_pay_num' => $real_pay_num,
            'currency_id' => $alone_mining_config['archive_currency_id'],
            'currency_name' => $currency_name,
            'currency_num' => $currency_user['num'],
        ];
        return $r;
    }
}