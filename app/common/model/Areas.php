<?php
//线下商家
namespace app\common\model;

use think\Model;

class Areas extends Model {
    static function check($area_id) {
        return self::where(['area_id'=>$area_id])->find();
    }

    static function checkParent($area_id,$parent_id) {
        $result = self::check($area_id);
        if(empty($result) || $result['parent_id']!=$parent_id) return false;

        return $result;
    }

    static function check_pca($province_id,$city_id,$area_id) {
        if(!self::checkParent($area_id,$city_id) || !self::checkParent($city_id,$province_id)) return false;
        return true;
    }

    static function check_pca_address($province_id,$city_id,$area_id) {
        if ($area_id > 0) {
            $area_info = self::checkParent($area_id,$city_id);
            if(!$area_info) return false;
        }

        $city_info = self::checkParent($city_id,$province_id);
        if(!$city_info) return false;

        $province_info = self::check($province_id);
        if(!$province_info) return false;
        $flag = $area_id > 0 ? $area_info['area_name'] : '';
        return $province_info['area_name'].$city_info['area_name']. $flag;
    }


    //地区类型 0:country 1:province 2:city  3:district
    static function get_list($type,$parent_id) {
        if($type==1 && $parent_id==0) $parent_id = 1;//默认中国的省市
        $field = "area_id as value,area_name as text";
        if (in_array($type, [2, 3])) {
            $parent_name = self::where('area_id', $parent_id)->value('area_name');
            $data = self::where(['area_type'=>$type,'parent_id'=>$parent_id])->field($field)->order('area_id asc')->select();
            foreach ($data as &$item) {
                if ($type == 3) {
                    $item['city'] = $parent_name;
                } else {
                    $item['province'] = $parent_name;
                }
            }
            return $data;
        }
        return self::where(['area_type'=>$type,'parent_id'=>$parent_id])->field($field)->order('area_id asc')->select();
    }
}