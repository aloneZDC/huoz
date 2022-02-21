<?php


namespace app\common\model;


use think\Exception;
use think\Model;

class TransferToAssetConfig extends Model
{
    /**
     * 获取用户资产包数据 首页
     * @param $member_id
     * @param $is_financial
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function get_asset_currency($member_id,$is_financial,$asset_type) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if(empty($member_id) || empty($asset_type)) return $r;

        $asset_type = self::get_asset_type($asset_type);
        //获取对应资产包对应字段
        $currency_user_field = self::get_currency_field($asset_type,$is_financial);

        $field = "a.to_currency_id,c.currency_mark as to_currency_mark,cp.cpt_cny_price,a.to_is_transfer,a.to_is_financial";
        $config_list = self::field($field)->alias('a')->where( ['a.status'=>1,'a.asset_type'=>$asset_type] )
            ->join(config("database.prefix") . "currency c", "a.to_currency_id=c.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency_price_temp cp", "a.to_currency_id=cp.cpt_currency_id", "LEFT")
            ->select();
        if(empty($config_list)) return $r;

        $currency_user = CurrencyUser::where(['member_id'=>$member_id])->select();
        if($currency_user) {
            $currency_user = array_column($currency_user->toArray(),null,'currency_id');
        } else {
            $currency_user = [];
        }

        //资产数据
        $list = [];
        $total_money = 0;
        foreach ($config_list as $value){
            if(isset($list[$value['to_currency_id']] )) continue;

            $value['num'] = isset($currency_user[$value['to_currency_id']]) ? $currency_user[$value['to_currency_id']][$currency_user_field] : 0; ;
            if($is_financial) $value['to_is_transfer'] = $value['to_is_financial'] = 2;

            $value['money'] = keepPoint($value['cpt_cny_price'] * $value['num'],2);
            $total_money += $value['money'];
            $list[$value['to_currency_id']] = $value;
        }

        //支持的兑换列表
        $exchange_list = [];
        if(!$is_financial) {
            $exchange_list = self::get_list(['a.status'=>1,'a.asset_type'=>$asset_type]);
        }

        if (!empty($list)) {
            $r['code'] = SUCCESS;
            $r['message'] = lang("data_success");
            $r['result'] = [
                'total_money' => keepPoint($total_money,2),
                'list' => array_values($list),
                'exchange' => $exchange_list,
            ];
        } else {
            $r['message'] = lang("lan_No_data");
        }
        return $r;
    }

    static function get_list($where) {
        $field = "a.currency_id,a.to_currency_id,a.fee,b.currency_mark,b.currency_logo,c.currency_mark as to_currency_mark,c.currency_logo as to_currency_logo,cp.cpt_cny_price as to_cny_price";
        $list = self::field($field)->alias('a')->where($where)
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.to_currency_id=c.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency_price_temp cp", "a.to_currency_id=cp.cpt_currency_id", "LEFT")
            ->select();
        return $list ? $list : [];
    }

    /**
     * 获取兑换对 详情
     * @param $member_id
     * @param $from_currency_id
     * @param $to_currency_id
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function get_currency_info($member_id,$from_currency_id,$to_currency_id,$asset_type){
        $r['code'] = ERROR1;
        $r['message'] = lang("close");
        $r['result'] = null;

        if(empty($asset_type) || !is_numeric($member_id) || !is_numeric($from_currency_id) || $from_currency_id<=0 || !is_numeric($to_currency_id) || $to_currency_id<=0) return $r;

        $asset_type = self::get_asset_type($asset_type);
        $where = [
            'a.asset_type' => $asset_type,
            'a.currency_id' => $from_currency_id,
            'a.to_currency_id' => $to_currency_id,
            'a.status'=>1
        ];
        $field = "a.currency_id,a.to_currency_id,a.fee,b.currency_mark,c.currency_mark as to_currency_mark,cp.cpt_cny_price as to_cny_price";
        $config = self::field($field)->alias('a')->where($where)
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.to_currency_id=c.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency_price_temp cp", "a.to_currency_id=cp.cpt_currency_id", "LEFT")
            ->find();
        if(!$config) return $r;

        $users_currency = CurrencyUser::getCurrencyUser($member_id,$from_currency_id);
        $user_money = $users_currency ? $users_currency['num'] : 0;
        $currency_price = CurrencyPriceTemp::get_price_currency_id($from_currency_id,'CNY');
        $config['user_money'] = $user_money;
        $config['currency_price'] = $currency_price;
        $config['ratio'] = keepPoint($currency_price/$config['to_cny_price'],6); //兑换比例

        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = $config;
        return $r;
    }

    static function get_asset_type($asset_type) {
        if(empty($asset_type) || !in_array($asset_type,['asset','product'])) $asset_type = 'asset';
        return $asset_type;
    }

    //根据兑换币种获取配置
    static function get_config_by_to($to_currency_id,$asset_type) {
        $asset_type = TransferToAssetConfig::get_asset_type($asset_type);
        return TransferToAssetConfig::where(['to_currency_id'=>$to_currency_id,'status'=>1,'asset_type'=>$asset_type])->find();
    }

    static function get_config($from_currency_id,$to_currency_id,$asset_type){
        $asset_type = TransferToAssetConfig::get_asset_type($asset_type);
        return TransferToAssetConfig::where(['currency_id'=>$from_currency_id,'to_currency_id'=>$to_currency_id,'status'=>1,'asset_type'=>$asset_type])->find();
    }

    static function get_currency_field($asset_type,$is_financial) {
        $asset_type = self::get_asset_type($asset_type);
        //资产字段
        if($asset_type=='asset'){
            if($is_financial){
                $currency_user_field = 'uc_financial';
            } else {
                $currency_user_field = 'uc_asset';
            }
        } else {
            if($is_financial){
                $currency_user_field = 'uc_product_financial';
            } else {
                $currency_user_field = 'uc_product';
            }
        }
        return $currency_user_field;
    }
}