<?php


namespace app\common\model;


use think\Model;

class ShopPayType extends Model
{
    const TYPE_ENUM = [
        'game_lock' => 'io券',
        'uc_card' => 'i券',
        'uc_card_lock' => 'o券',
        'none' => null
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->field('currency_id, currency_name');
    }

    /**
     * 支付列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function getPayTypeList()
    {
        $select = self::order('id asc')->select();
        $payTypeList = [];
        foreach ($select as $value) {
            $payTypeList[$value['id']] = $value['name'];
        }
        return $payTypeList;
    }
}