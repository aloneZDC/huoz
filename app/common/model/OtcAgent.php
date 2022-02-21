<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
use think\Exception;
use think\Db;

//银商申请
class OtcAgent extends Base {
    public function getRandNum($member_id) {
        return substr($member_id, 0,1).rand(100,999).substr($member_id,-2);
    }

    public function is_check($member_id) {
        $info = Db::name('otc_agent')->where(['member_id'=>$member_id])->find();
        if(!$info || $info['status']==2) return lang('lan_otc_auth_apply_first');
        if($info['status']==0) return lang('lan_user_auth_first_wait');

        return ['flag'=>true];
    }

    public function check_info($member_id) {
        $result = ['status'=>-1,'refuse_msg'=>''];
        $info = Db::name('otc_agent')->field('status,refuse_msg')->where(['member_id'=>$member_id])->find();
        if($info) $result = $info;

        return $result;
    }

    public function apply($member_id,$video_url,$say_num) {
        if(empty($say_num) || substr($member_id, 0,1)!=substr($say_num, 0,1) || substr($member_id, -2)!=substr($say_num, -2)) return lang('lan_orders_illegal_request');

        $verify_info = Db::name('verify_file')->field('verify_state')->where(['member_id' => $member_id])->find();
        if(!$verify_info) return lang('lan_user_authentication_first');
        if($verify_info['verify_state']==2) return lang('lan_user_authentication_first_wait');
        if($verify_info['verify_state']!=1) return lang('lan_user_authentication_first');

        Db::startTrans();
        try{
            $info = Db::name('otc_agent')->lock(true)->where(['member_id'=>$member_id])->find();
            if($info) {
                if($info['status']==0) throw new Exception(lang('lan_user_auth_first_wait'));
                if($info['status']==1) throw new Exception(lang('lan_otc_auth_success'));
            }

            $data = [
                'member_id' => $member_id,
                'video_url' => $video_url,
                'say_num' => $say_num,
                'status' => 0,
                'addtime' => time(),
            ];
            if($info) {
                $flag = Db::name('otc_agent')->where(['id'=>$info['id']])->update($data);
            } else {
                $flag = Db::name('otc_agent')->insertGetId($data);
            }
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();
            return ['flag'=>true];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }
}
