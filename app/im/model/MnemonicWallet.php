<?php
namespace app\im\model;

use app\common\model\Currency;
use think\Db;

class MnemonicWallet extends Base
{
    static function getWallet($member_id) {
        $list = self::with(['token'])->where('member_id',$member_id)
            ->field('id,parent_id,currency_name,currency_token,private_key,public_key,pwd')
            ->where('parent_id',0)->select();
        if(empty($list)) return new \ArrayObject();

        foreach ($list as &$item) {
            if(empty($item['token'])) $item['token'] = new \ArrayObject();
        }

        return $list;
    }

    public function token() {
        return $this->belongsTo('app\\im\\model\\MnemonicWallet', 'id', 'parent_id')->field('id,parent_id,currency_name,currency_token,private_key,public_key,pwd');
    }
}
