<?php

namespace app\common\model;

use think\Db;
use think\Model;

class ShopLogisticsList extends Model
{
    static function getExpressList() {

        $select = Db::name('express_list')->where('status', 1)->select();
        $expressList = [];
        foreach ($select as $value) {
            $expressList[$value['code']] = $value['name'];
        }
        return $expressList;
    }
}