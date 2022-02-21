<?php

namespace app\common\model;

use think\Db;
use think\Exception;

class WechatTransfer extends Base
{
    public static $pay_status = [];

    /**
     * 关联 微信绑定表
     * @return \think\model\relation\BelongsTo
     */
    public function wechatbind()
    {
        return $this->belongsTo(WechatBind::class, 'wxid')->field(['id', 'openid', 'actual_name', 'wechat_account']);
    }

    public function memberbank()
    {
        return $this->belongsTo(MemberBank::class, 'wxid', 'id')->field(['id', 'bank_card', 'actual_name', 'open_bank']);
    }

    /**
     * 提现申请提交
     * @param int $member_id 用户ID
     * @param int $wxid 微信ID
     * @param number $number 提现数量
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public static function WithdrawSubmit($member_id, $wxid, $number, $type)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        if ($type == 2) {
            // 检查是否绑定微信
            $WechatBind = WechatBind::where(['id' => $wxid, 'member_id' => $member_id, 'status' => 1])->find();
            if (empty($WechatBind)) {
                $r['message'] = lang('wechat_not_bind');
                return $r;
            }

            // 计算手续费
            $withdraw_fee = 0;
            $wechat_withdraw_fee = Config::get_value('wechat_withdraw_fee', 0);
            if ($wechat_withdraw_fee > 0) {
                $withdraw_fee = bcdiv(bcmul($wechat_withdraw_fee, $number, 2), 100, 4); // 百分比
            }
        } else {
            // 检查是否绑定银行卡
            $bankBind = \app\common\model\MemberBank::where(['id' => $wxid, 'member_id' => $member_id, 'status' => 1])->find();
            if (empty($bankBind)) {
                $r['message'] = '未绑定银行卡';
                return $r;
            }

            // 计算手续费
            $withdraw_fee = 0;

//            $bank_withdraw_fee = Config::get_value('bank_withdraw_fee', 0);
//            if ($bank_withdraw_fee > 0) {
//                $withdraw_fee = bcdiv(bcmul($bank_withdraw_fee, $number, 2), 100, 4); // 百分比
//            }

            $currency = Currency::where(['currency_id' => Currency::TRC20_ID])->find();
            if ($currency['fee_greater'] > 0) {
                $withdraw_fee = bcdiv(bcmul($currency['fee_greater'], $number, 2), 100, 4); // 百分比
            }

            if ($currency['currency_min_tibi'] > 0
                && $currency['currency_min_tibi'] > $number) {
                $r['message'] = '最低提现数量' . $currency['currency_min_tibi'] . '火米起';
                return $r;
            }
        }

        // 账户中要保留10元
//        $wechat_withdraw_basis = Db::name('yn_config')->where(['key' => 'month_reduce_num'])->value('value', 0);

        // 计算金额是否充足
        //$totalMoney = bcadd($number, $withdraw_fee, 6);
        $totalMoney = bcsub($number, $withdraw_fee, 6);
        $currency_id = ShopConfig::get_value('reward_currency_id');
        $CurrencyUser = CurrencyUser::getCurrencyUser($member_id, $currency_id);
        if (bccomp($number, $CurrencyUser->num, 6) > 0) {
            $r['message'] = lang("lan_insufficient_balance");
            return $r;
        }
        try {
            self::startTrans();
            $data = [
                'type' => $type == 1 ? 2 : 1,
                'member_id' => $member_id,
                'currency_id' => $currency_id,
                'partner_trade_no' => self::GetRandOrderSn(),
                'fee' => $withdraw_fee,
                'amount' => $totalMoney,
                'add_time' => time()
            ];
            if ($type == 2) {
                $data['wxid'] = $wxid;
            } else {
                $data['bank_id'] = $wxid;
            }
            $log_id = self::insertGetId($data);
            if (!$log_id) throw new Exception(lang('operation_failed_try_again'));

            // 钱包账户减少
            $flag = AccountBook::add_accountbook($data['member_id'], $data['currency_id'], 122, 'wechat_reduce_withdraw', 'out', $number, $log_id);
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            // 扣除数量及手续费
            $flag = CurrencyUser::where(['member_id' => $data['member_id'], 'currency_id' => $data['currency_id'], 'num' => $CurrencyUser->num])
                ->dec('num', $number)
                ->inc('forzen_num', $number)
                ->update();
            if (!$flag) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    // 贡献值提现记录
    static function WithdrawLog($member_id, $currency_id, $page, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        $result = self::where([
            'member_id' => $member_id,
            'currency_id' => $currency_id,
        ])
            ->field('id,bank_id,currency_id,fee,amount,add_time,check_status')
            ->page($page, $rows)->order('id desc')->select();
        if (!$result) return $r;
        foreach ($result as &$item) {
            $item['title'] = '出仓';
            $item['add_time'] = date('Y-m-d H:i:s', $item['add_time']);
            $Currency = Currency::where(['currency_id' => $item['currency_id']])->find();
            $item['currency_name'] = $Currency['currency_name'];
            $MemberBank = MemberBank::where(['id' => $item['bank_id'], 'member_id' => $member_id])->find();
            $item['actual_name'] = $MemberBank['truename'];
            $item['bank_card'] = $MemberBank['bankcard'];
            $item['open_bank'] = $MemberBank['bankadd'];
            $item['amount'] = keepPoint($item['amount'] + $item['fee'],2);
            unset($item['bank_id'], $item['currency_id'],$item['fee']);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    // 取消退款
    static function WithdrawCancel($member_id, $id)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];
        if (empty($member_id) || empty($id)) {
            return $r;
        }
        $info = self::where(['id' => $id, 'member_id' => $member_id, 'check_status' => 0])->find();
        if (!$info) return $r;
        $totalMoney = keepPoint($info['fee'] + $info['amount']);
        $CurrencyUser = \app\common\model\CurrencyUser::getCurrencyUser($info['member_id'], $info['currency_id']);
        try {
            self::startTrans();

            $isUpdate = WechatTransfer::where('id', $info['id'])->update([
                'update_time' => time(),
                'desc' => '用户取消订单',
                'check_status' => 2,
            ]);
            if (!$isUpdate) {
                throw new Exception('更新订单失败');
            }

            // 钱包账户增加
            $flag = \app\common\model\AccountBook::add_accountbook($info['member_id'], $info['currency_id'], 126, 'wechat_reduce_withdraw_refuse', 'in', $totalMoney, $info['id']);
            if (!$flag) {
                throw new Exception('账本写入失败');
            }

            // 增加数量及手续费
            $flag = \app\common\model\CurrencyUser::where([
                'member_id' => $info['member_id'],
                'currency_id' => $info['currency_id'],
                'forzen_num' => $CurrencyUser->forzen_num
            ])
                ->inc('num', $totalMoney)
                ->dec('forzen_num', $totalMoney)
                ->update();
            if (!$flag) {
                throw new Exception('资产更新失败');
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    /**
     * 生成随机订单号
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function GetRandOrderSn()
    {
        $string = 'abcdefghkmnprstuvwxyzABCDEFGHKMNPRSTUVWXYZ23456789';
        $cdkey = "";
        for ($i = 0; $i < 22; $i++) {
            $cdkey .= $string[rand(0, strlen($string) - 1)];
        }

        $out_trade_no = $cdkey . time();
        $is_out_trade_no = self::where('partner_trade_no', $out_trade_no)->find();
        if (empty($is_out_trade_no)) {
            return $out_trade_no;
        }
        return self::GetRandOrderSn();
    }

    /**
     * 获取商品订单信息
     * @param $order_number
     * @return array|bool|\PDOStatement|string|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getGoodOrder($order_number)
    {
        return self::with(['wechatbind'])->where('partner_trade_no', $order_number)->field(['id', 'openid', 'partner_trade_no', 'amount', 'check_name', 're_user_name', 'desc'])->find();
    }

    /**
     * 保存请求信息
     * @param $responseData
     * @return WechatTransfer
     */
    public static function UpdateOrderRecord($responseData)
    {
        $SaveData = [
            'pay_status' => $responseData['pay_status'],
            'payment_no' => $responseData['payment_no'],
            'error_info' => json_encode($responseData),
            'payment_time' => time(),
        ];
        return self::where(['partner_trade_no' => $responseData['partner_trade_no']])->update($SaveData);
    }
}
