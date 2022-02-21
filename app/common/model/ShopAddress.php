<?php


namespace app\common\model;


use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Model;

class ShopAddress extends Model
{
    /**
     * 添加一条收货地址
     * @param int $user_id 用户id
     * @param string $name 收货人姓名
     * @param string $mobile 手机号码
     * @param string $province 省
     * @param string $city 市
     * @param string $area 区/县
     * @param string $address 详细地址
     * @param int $default 是否为默认地址：1是，2否
     * @throws Exception
     * @return mixed
     */
    static function add_address($user_id, $name, $mobile, $province, $city, $area, $address, $default = 2)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if (isInteger($user_id) && !empty($name) && !empty($mobile) && isInteger($province) && isInteger($city) && !empty($address) && is_numeric($area)) {
            if(!checkMobile($mobile)) {
                $r['message'] = lang('lan_please_enter_the_correct_mobile_number');
                return $r;
            }
            if (false === Areas::check_pca_address($province, $city, $area) and $area != 0) {
                $r['message'] = lang('address_error');
                return $r;
            }
            $count = self::where(['sa_user_id' => $user_id])->count("sa_id");
            if ($count >= 20) {
                $r['message'] = lang("only_20shipping_addresses");
                return $r;
            }
            $default = 1;
            $data['sa_user_id'] = $user_id;
            $data['sa_name'] = $name;
            $data['sa_mobile'] = $mobile;
            $data['sa_address'] = $address;
            $data['sa_default'] = 1;
            $data['sa_add_time'] = time();
            $data['sa_province'] = $province;
            $data['sa_city'] = $city;
            $data['sa_area'] = $area;
            $sa_id = Db::name("shop_address")->insertGetId($data);
            if ($sa_id) {
                $r['message'] = lang("added_successfully");
                $r['code'] = SUCCESS;
                if ($default == 1) {
                    Db::name("shop_address")->where(['sa_default' => 1, 'sa_user_id' => $user_id])->where("sa_id", "<>", $sa_id)->update(['sa_default' => 2]);
                }
            } else {
                $r['message'] = lang("add_failed");
            }
        }
        return $r;
    }

    /**
     * 删除一条收货地址
     * @param int $user_id 用户id
     * @param int $sa_id 收货地址表id
     * @return mixed
     * @throws Exception
     * @throws PDOException
     */
    static function delete_address($user_id, $sa_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && isInteger($sa_id)) {
            $delete = Db::name("shop_address")->where(['sa_user_id' => $user_id, 'sa_id' => $sa_id])->delete();
            if ($delete) {
                $r['code'] = SUCCESS;
                $r['message'] = lang("comments_delete_success");
            } else {
                $r['message'] = lang("operation_failed");
            }
        }
        return $r;
    }

    /**
     * 获取用户的收货地址列表
     * @param int $user_id 用户id
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function get_address_list($user_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id)) {
            $list = Db::name("shop_address")->where(['sa_user_id' => $user_id])->order("sa_default asc,sa_add_time desc")->select();

            if ($list) {
                foreach ($list as &$address) {
                    $address['pca'] = Areas::check_pca_address($address['sa_province'], $address['sa_city'], $address['sa_area']);
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("no_data");
            }
        }
        return $r;
    }

    /**
     * 修改收货地址
     * @param int $sa_id 表id
     * @param int $user_id 用户id
     * @param string $name 收货人姓名
     * @param string $mobile 手机号码
     * @param string $province 省
     * @param string $city 市
     * @param string $area 区
     * @param string $address 详细地址
     * @param int $default 是否默认地址:1是，2不是
     * @return mixed
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws PDOException
     */
    static function update_address($sa_id, $user_id, $name, $mobile, $province, $city, $area, $address, $default = 2)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($sa_id) && isInteger($user_id) && !empty($name) && !empty($mobile) && isInteger($province) && isInteger($city) && !empty($address) && isInteger($area)) {
            if (false === Areas::check_pca_address($province, $city, $area)) {
                $r['message'] = lang('address_error');
                return $r;
            }
            $find = Db::name("shop_address")->where(['sa_id' => $sa_id, 'sa_user_id' => $user_id])->find();
            if (!empty($find)) {
                $find['sa_name'] = $name;
                $find['sa_mobile'] = $mobile;
                $find['sa_address'] = $address;
                $find['sa_default'] = $default == 1 ? 1 : 2;
                $find['sa_add_time'] = time();
                $find['sa_province'] = $province;
                $find['sa_city'] = $city;
                $find['sa_area'] = $area;
                $update = Db::name("shop_address")->where(['sa_id' => $sa_id, 'sa_user_id' => $user_id])->update($find);
                if ($update) {
                    $r['code'] = SUCCESS;
                    $r['message'] = lang("successfully_modified");
                    if ($default == 1) {
                        Db::name("shop_address")->where(['sa_default' => 1, 'sa_user_id' => $user_id])->where("sa_id", "<>", $sa_id)->update(['sa_default' => 2]);
                    }
                } else {
                    $r['message'] = lang("no_change");
                }
            }
        }
        return $r;
    }

    /**
     * 获取地址详情
     * @param int $user_id 用户id
     * @param int $sa_id 地址表id
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function get_address_details($user_id, $sa_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && isInteger($sa_id)) {
            $find = Db::name("shop_address")->where(['sa_user_id' => $user_id, 'sa_id' => $sa_id])->find();
            if (!empty($find)) {
                $find['pca'] = Areas::check_pca_address($find['sa_province'], $find['sa_city'], $find['sa_area']);
                $find['province'] = Areas::where('area_id', $find['sa_province'])->value('area_name');
                $find['city'] = Areas::where('area_id', $find['sa_city'])->value('area_name');
                $find['area'] = Areas::where('area_id', $find['sa_area'])->value('area_name');
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $find;
            }
        }
        return $r;
    }

    /**
     * 设置默认地址
     * @param int $user_id 用户id
     * @param int $sa_id 地址表id
     * @return mixed
     * @throws Exception
     * @throws PDOException
     */
    static function set_default($user_id,$sa_id){
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if(isInteger($user_id)&&isInteger($sa_id)){
            $update = Db::name("shop_address")->where(['sa_user_id' => $user_id, 'sa_id' => $sa_id])->update(['sa_default' => 1]);
            if ($update) {
                Db::name("shop_address")->where(['sa_default' => 1])->where('sa_user_id', $user_id)->where("sa_id", "<>", $sa_id)->update(['sa_default' => 2]);
                $r['code'] = SUCCESS;
                $r['message'] = lang("successful_operation");
            } else {
                $r['message'] = lang("operation_failed");
            }
        }
        return $r;
    }

    /**
     * 获取用户的默认地址
     * @param int $user_id 用户id
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function get_default($user_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id)) {
            $find = Db::name("shop_address")->where(['sa_user_id' => $user_id, 'sa_default' => 1])->find();
            if (!empty($find)) {
                $find['pca'] = Areas::check_pca_address($find['sa_province'], $find['sa_city'], $find['sa_area']);
                $find['province'] = Areas::where('area_id', $find['sa_province'])->value('area_name');
                $find['city'] = Areas::where('area_id', $find['sa_city'])->value('area_name');
                $find['area'] = Areas::where('area_id', $find['sa_area'])->value('area_name');
                $r['message'] = lang("data_success");
                $r['code'] = SUCCESS;
                $r['result'] = $find;
            } else {
                $r['message'] = lang("no_data");
            }
        }
        return $r;
    }


}