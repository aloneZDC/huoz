<?php
//线下商家
namespace app\common\model;

use think\Model;

class StoresMainProject extends Model {
   static function get_list() {
       return self::order('sort asc')->field('id,cat_name,icon')->select();
   }

   static function check($id) {
       $info = self::where(['id'=>$id])->find();
       if(!$info) return false;

       return true;
   }
}