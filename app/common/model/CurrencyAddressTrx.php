<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/11
 * Time: 18:25
 */

namespace app\common\model;


class CurrencyAddressTrx extends Base
{
    protected $resultSetType = 'collection';

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'cae_member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
}