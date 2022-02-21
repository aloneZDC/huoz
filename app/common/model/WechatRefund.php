<?php

namespace app\common\model;

class WechatRefund extends Base
{
    /**
     * 保存订单信息
     * @param array $refundOrder 请求参数
     * @param array $wechatOrder 支付订单
     * @param array $responseData 请求结果
     * @return int|string
     */
    public static function SaveOrderRecord($refundOrder, $wechatOrder, $responseData)
    {
        $OrderRecord = [
            'member_id' => $wechatOrder['member_id'],
            'gmo_id' => $wechatOrder['gmo_id'],
            'out_refund_no' => $refundOrder['out_refund_no'],
            'transaction_id' => $refundOrder['transaction_id'],
            'total_fee' => $refundOrder['total_fee'],
            'refund_fee' => $refundOrder['refund_fee'],
            'status' => $responseData['status'],
            'add_time' => time(),
            'error_info' => json_encode($responseData),
        ];
        return self::insert($OrderRecord);
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
        $is_out_trade_no = self::where('out_refund_no', $out_trade_no)->find();
        if (empty($is_out_trade_no)) {
            return $out_trade_no;
        }
        return self::GetRandOrderSn();
    }
}
