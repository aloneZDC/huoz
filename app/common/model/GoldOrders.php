<?php


namespace app\common\model;


use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;

class GoldOrders extends Model
{

    public function goods()
    {
        return $this->belongsTo(Goods::class, 'go_goods_id')/*->where('goods_status', Goods::STATUS_UP)*/->with(['currency', 'other_currency'])->field('goods_id,goods_price, goods_currency_id, goods_currency_num, goods_currency_type, goods_currency_other_id, goods_currency_other_num, goods_currency_other_type');
    }

    public function goodsCurrency()
    {
        return $this->belongsTo(Goods::class, 'go_goods_id')->with(['currency', 'other_currency'])->field('goods_id, goods_currency_id, goods_currency_other_id');
    }
    /**
     * 创建一个不存在数据库中的订单编号
     * @return string
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function create_code(){
        $code=date("YmdHi").randNum();
        $find=self::where(['go_code'=>$code])->field("go_id")->find();
        if(empty($find)){
            return $code;
        }else{
           return self::create_code();
        }
    }
}