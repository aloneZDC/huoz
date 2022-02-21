<?php
//管理员充提币钱包地址列表 目前只支持XRP
namespace app\common\model;
use think\Model;

class WalletAdminAddress extends Model
{
    /**
     * 添加管理员地址
     * @param $type recharge充币地址  take提币地址
     * @param $currency_id 币种ID
     * @param $address 地址
     * @param $start_id 用户充币地址分配区间开始
     * @param $stop_id 用户充币地址分配区间结束
     */
    static function addAddress($type,$currency_id,$address,$start_id,$stop_id) {
        return self::insertGetId([
            'waa_type' => $type,
            'waa_currency_id' => $currency_id,
            'waa_address' => $address,
            'waa_start_id' => $start_id,
            'stop_id' => $stop_id,
        ]);
    }

    /**
     * 检测是否是管理员充币地址
     * @param $currency_id 币种ID
     * @param $address 钱包地址
     * @return bool
     */
    static function checkIsRecharge($currency_id,$address) {
        $info = self::where(['waa_currency_id'=>$currency_id,'waa_type'=>'recharge','waa_address'=>$address])->find();
        return $info ? true : false;
    }

    /**
     * 检测是否是管理员提币地址
     * @param $currency_id 币种ID
     * @param $address 钱包地址
     * @return bool
     */
    static function checkIsTake($currency_id,$address) {
        $info = self::where(['waa_currency_id'=>$currency_id,'waa_type'=>'take','waa_address'=>$address])->find();
        return $info ? true : false;
    }

    /**
     * 检测是否是管理员地址
     * @param $currency_id 币种ID
     * @param $address 钱包地址
     * @return bool
     */
    static function checkIsAddress($currency_id,$address) {
        $info = self::where(['waa_currency_id'=>$currency_id,'waa_address'=>$address])->find();
        return $info ? true : false;
    }

    /**
     * 获取用户钱包地址 目前只适用于XRP币种
     * @param $currency_id 币种ID
     * @param $member_id 用户ID
     * @return mixed|string
     */
    static function getRechageAddressByMemberId($currency_id,$member_id) {
        $info = self::where([
            'waa_currency_id'=>$currency_id,
            'waa_type' => 'recharge',
            'waa_start_id'=> ['elt',$member_id],
            'waa_stop_id'=> ['egt',$member_id],
        ])->find();
        return $info ? $info['waa_address'] : '';
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'waa_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
