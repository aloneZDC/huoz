<?php

namespace app\common\model;

class WechatBind extends Base
{
    /**
     * 保存下单信息
     * @param number $order_number 订单号
     * @param array $unifiedOrder 请求参数
     * @param array $responseData 请求结果
     * @return int|string
     */
    public static function SaveData($order_number, $unifiedOrder, $responseData)
    {
        $OrderRecord = [
            'gmo_code' => $order_number,
            'out_trade_no' => $unifiedOrder['out_trade_no'],
            'total_fee' => $unifiedOrder['total_fee'],
            'trade_type' => $unifiedOrder['trade_type'],
            'order_status' => $responseData['status'],
            'add_time' => time(),
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
        $is_out_trade_no = self::where('out_trade_no', $out_trade_no)->find();
        if (empty($is_out_trade_no)) {
            return $out_trade_no;
        }
        return self::GetRandOrderSn();
    }
}
