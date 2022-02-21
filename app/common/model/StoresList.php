<?php
//线下商家
namespace app\common\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;

class StoresList extends Model {
    /**
     * @param int $user_id
     * @param string $logo
     * @param string $apply_image 营业执照图片
     * @param string $banner_image 店铺banner图
     * @param string $name
     * @param string $phone
     * @param string $stores_name
     * @param int $province_id
     * @param int $city_id
     * @param int $area_id
     * @param string $address
     * @param string $longitude 经度
     * @param string $latitude 纬度
     * @param int $week_start
     * @param int $week_stop
     * @param int $hour_start
     * @param int $hour_stop
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function apply($user_id, $logo, $apply_image, $banner_image/*,$legal_person_image*/, $name, $phone, $stores_name, /*$main_project_id, */$province_id, $city_id, $area_id, $address, $longitude, $latitude, $week_start, $week_stop, $hour_start, $hour_stop)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if (abs($latitude) > 90 || abs($longitude) > 180) {
            return $r;
        }

        if ($week_start < 0 || $week_start > 6 || $week_stop < 0 || $week_stop > 6) return $r;
        if ($hour_start < 0 || $hour_start > 23 || $hour_stop < 0 || $hour_stop > 23) return $r;

        if (empty($apply_image)) {
            $r['message'] = lang('business_license_cannot_be_empty');
            return $r;
        }

        if (empty($banner_image)) {
            $r['message'] = lang('lan_upload_banner_image');
            return $r;
        }

        /*if (empty($legal_person_image)) {
            $r['message'] = lang('lan_upload_legal_person_image');
            return $r;
        }*/

        if (empty($logo)) {
            $r['message'] = lang('lan_upload_logo_image');
            return $r;
        }

        if (empty($name)) {
            $r['message'] = lang('lan_user_namenot_empty');
            return $r;
        }

        if (empty($phone) || !checkMobile($phone)) {
            $r['message'] = lang('The_phone_number_is_not_correct');
            return $r;
        }

        if (empty($stores_name)) $stores_name = '';

        /*if (!StoresMainProject::check($main_project_id)) {
            $r['message'] = lang('stores_main_project_not_exist');
            return $r;
        }*/

        $full_address = Areas::check_pca_address($province_id, $city_id, $area_id);
        if (!$full_address || empty($address)) {
            $r['message'] = lang('address_error');
            return $r;
        }

        $stores = self::where(['user_id' => $user_id])->find();
        if ($stores) {
            $r['message'] = lang('can_not_apply_repeat');
            return $r;
        }

        $flag = self::insertGetId([
            'user_id' => $user_id,
            'apply_image' => $apply_image,
            'banner_image' => $banner_image,
            // 'legal_person_image' => $legal_person_image,
            'logo' => $logo,
            'name' => $name,
            'phone' => $phone,
            'stores_name' => $stores_name,
            // 'main_project_id' => $main_project_id,
            'province_id' => $province_id,
            'city_id' => $city_id,
            'area_id' => $area_id,
            'address' => $address,
            'full_address' => $full_address . $address,
            'status' => 2,
            'add_time' => time(),
            'longitude' => $longitude,
            'latitude' => $latitude,
            'week_start' => $week_start,
            'week_stop' => $week_stop,
            'hour_start' => $hour_start,
            'hour_stop' => $hour_stop,
        ]);
        if ($flag === false) {
            $r['message'] = lang('operation_failed_try_again');
            return $r;
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        return $r;
    }

    //0未申请 1通过审核  2审核中  3审核失败
    static function apply_status($user_id) {
        $status = 0;
        $info = self::where(['user_id'=>$user_id])->find();
        if($info) $status = $info['status'];
        return $status;
    }

    static function apply_info($user_id,$is_safe=false) {
        if($is_safe) {
            $info = self::where(['user_id'=>$user_id])->field('name,apply_image,legal_person_image,card_reverse,card_positive',true)->find();
        } else {
            $info = self::where(['user_id'=>$user_id])->field('stores_id, user_id, status, add_time, apply_image, banner_image, logo, name, phone, stores_name, address, full_address, longitude, latitude, week_start, week_stop, hour_start, hour_stop, fail_msg, card_reverse, card_positive')->find();
            $info['agent_address'] = UserAgentAddress::getFullAddress($user_id);
        }
        if(!$info) return $info;
        $info['add_time'] = date('Y-m-d H:i:s',$info['add_time']);
//        $info['project_name'] = isset($info['project']) ? $info['project']['cat_name']: '';
//        $info['project_icon'] = isset($info['project']) ? $info['project']['icon']: '';
        unset($info['project']);
        return $info;
    }

    static function stores_home($user_id,$stores_id) {
        $r['code']=ERROR1;
        $r['message']=lang("shop_not_exists");
        $r['result']=null;

        $stores = self::where(['stores_id'=>$stores_id])->with('project')->field('name,apply_image,legal_person_image,card_reverse,card_positive',true)->find();
        if(!$stores) return $r;

        if($stores['status']!=1 && $stores['user_id']!=$user_id) return $r;

        $stores['add_time'] = date('Y-m-d H:i:s',$stores['add_time']);
        $stores['project_name'] = isset($stores['project']) ? $stores['project']['cat_name']: '';
        $stores['project_icon'] = isset($stores['project']) ? $stores['project']['icon']: '';
        unset($stores['project']);

        $stores['is_owner'] = $stores['user_id']==$user_id ? 1 : 2;
        $stores['transfer_people'] = $stores['transfer_num'] = 0;
        $transfer_total = StoresCardLog::where(['type'=>'transfer_out','user_id'=>$user_id])->field('count(id) as transfer_people,sum(number) as transfer_num')->find();
        if($transfer_total)  {
            $stores['transfer_people'] = $transfer_total['transfer_people'] ? $transfer_total['transfer_people'] : 0;
            $stores['transfer_num'] = $transfer_total['transfer_num'] ? stores_fotmat_number($transfer_total['transfer_num']) : 0;
        }
        $stores['transfer_currency_name'] = lang('uc_card');

        $r['result'] = $stores;
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        return $r;
    }

    /**
     * 兑换配置
     * @param $user_id
     * @return mixed
     */
    static function convert_num_to_card_list($user_id) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        $config_list = StoresConvertConfig::num_to_card();
        if(empty($config_list)) return $r;

        foreach ($config_list as &$config){
            $currency_user = CurrencyUser::getCurrencyUser($user_id,$config['currency_id']);
            $config['currency_num'] = $currency_user[StoresConvertConfig::NUM_FIELD];
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        return $r;
    }

    //IO积分可用  兑换到  IO积分卡包锁仓即I券
    static function convert_num_to_card($user_id,$currency_id,$to_currency_id,$from_num) {
        $r['code']=ERROR1;
        $r['message']=lang("parameter_error");
        $r['result']=null;

        $stores = self::where(['user_id'=>$user_id])->find();
        if(empty($stores) || $stores['status']!=1) {
            $r['message'] = lang('not_business');
            return $r;
        }

        $result = StoresConvertLog::convert_num_to_card($user_id,$currency_id,$to_currency_id,$from_num);
        return $result;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }

    public function project() {
        return $this->belongsTo('app\\common\\model\\StoresMainProject', 'main_project_id', 'id')->field('id,cat_name,icon');
    }
}