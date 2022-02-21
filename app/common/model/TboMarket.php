<?php
namespace app\common\model;

use think\Model;
use think\Db;
use think\Exception;

// 矿源市场
class TboMarket extends Base
{
    public function cat() {
        return $this->belongsTo('app\\common\\model\\TboMarketCat', 'cat_id', 'id')->field('id,name');
    }
}
