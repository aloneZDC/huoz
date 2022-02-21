<?php
namespace app\common\model;

use think\Db;
use think\Exception;

class Visa extends Base
{
    const NOT_APPLY = -1; //未申请
    const SUCCESS = 1; //1申请成功
    const FAIL = 2; //申请失败
    const AUTH = 3; //审核中

    static function info($member_id) {
        $r['code']= SUCCESS;
        $r['message']=lang('success_operation');
        $r['result']= [
            'apply_status' => self::NOT_APPLY,
            'apply_member_id' => $member_id,
            'apply_info' => [],
        ];

        $info = self::where('member_id',$member_id)->find();
        if($info) {
            $info['add_time'] = date('Y-m-d',$info['add_time']);
            $info['auth_time'] = date('Y-m-d',$info['auth_time']);
            $r['result']['apply_info'] = $info;
            $r['result']['apply_status'] = $info['status'];
        }
        return $r;
    }

    static function check($member_id) {
        $r['code']=ERROR1;
        $r['message']='';
        $r['result']= null;

        $verify_info = Db::name('verify_file')->field('verify_state')->where(['member_id' => $member_id])->find();
        if(!$verify_info) {
            $r['code'] = 30100;
            $r['message']= lang('lan_user_authentication_first');
            return $r;
        }

        if($verify_info['verify_state']==2) {
            $r['code'] = 40100;
            $r['message']= lang('lan_user_authentication_first_wait');
            return $r;
        }

        if($verify_info['verify_state']!=1) {
            $r['code'] = 30100;
            $r['message']= lang('lan_user_authentication_first');
            return $r;
        }

        $min_level = intval(HongbaoConfig::getValue('hongbao_visa_apply_air_level',7));
        if($min_level>0) {
            $air_level = UserAirLevel::where(['user_id'=>$member_id])->find();
            if(!$air_level || $air_level['level_id']<($min_level+1)) {
                $r['code'] = ERROR1;
                $r['message']= lang('visa_air_limit',['level'=>$min_level]);
                return $r;
            }
        }

        $r['code']=SUCCESS;
        $r['message']= lang('success_operation');
        $r['result']= null;
        return $r;
    }

    //申请
    static function apply($member_id,$name,$card_id,$phone,$province_id,$city_id,$area_id,$address) {
        $apply_check = self::check($member_id);
        if($apply_check['code']!=SUCCESS) return $apply_check;

        $r['code'] = ERROR1;
        $r['message']='';
        $r['result']= null;
        $info = self::where('member_id',$member_id)->find();
        if($info && $info['status']!=2) {
            $r['message'] = lang('visa_cannot_repeat_apply');
            return $r;
        }

        if(empty($name) || !preg_match('/^[\x{4e00}-\x{9fa5}]+$/u',$name)){
            $r['message']= lang('visa_name_not_true');
            return $r;
        }

        if(empty($card_id) || !is_idcard($card_id)) {
            $r['message']= lang('lan_member_idcard_error');
            return $r;
        }

        if(empty($phone) || !checkMobile($phone)) {
            $r['message']= lang('The_phone_number_is_not_correct');
            return $r;
        }

        $full_address = Areas::check_pca_address($province_id,$city_id,$area_id);
        if(!$full_address || empty($full_address) || empty($address)) {
            $r['message'] = lang('visa_address_error');
            return $r;
        }

        $full_address .= $address;
        $data = [
            'member_id' => $member_id,
            'name' => $name,
            'card_id' => $card_id,
            'phone' => $phone,
            'province_id' => $province_id,
            'city_id' => $city_id,
            'area_id' => $area_id,
            'address' => $address,
            'full_address' => $full_address,
            'add_time' => time(),
            'auth_time' => time(),
            'status' => self::AUTH,
            'msg' => '您本次VISA借记卡的申请已受理，请耐心等待,谢谢！',
        ];
        try{
            if($info) {
                $flag = self::where('id',$info['id'])->update($data);
            } else {
                $flag = self::insertGetId($data);
            }
        } catch (Exception $e) {
            $flag = false;
        }


        if(!$flag) {
            $r['message'] = lang('operation_failed_try_again');
            return $r;
        }

        $r['code']=SUCCESS;
        $r['message']=lang('success_operation');
        $r['result']= null;
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,phone,email');
    }
    public function verify() {
        return $this->belongsTo('app\\common\\model\\VerifyFile', 'member_id', 'member_id')->field('member_id,pic1,pic2,pic3,idcard');
    }
}
