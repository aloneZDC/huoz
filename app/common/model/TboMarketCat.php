<?php
namespace app\common\model;

use think\Model;
use think\Db;
use think\Exception;

// 矿源市场
class TboMarketCat extends Base
{
    static function getList($lang) {
        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');

        if($lang=='en') {
            $field = 'id,en_name as name';
        } else {
            $field = 'id,name';
        }
        $r['result'] = self::with('market')->field($field)->where('status',1)->order('sort asc')->select();
        return $r;
    }

    public function market() {
        return $this->hasMany('app\\common\\model\\TboMarket', 'cat_id', 'id')->order('sort asc')->field('id,cat_id,name,image,url');
    }
}
