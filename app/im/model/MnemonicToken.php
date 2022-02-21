<?php
namespace app\im\model;

use think\Db;

class MnemonicToken extends Base
{
    // 登录
    static function login_token($uuid,$member_id) {
        $token = base64_encode(md5($member_id.time().$uuid). "|" . $member_id);

        // 增加登录记录
        $flag = self::insertGetId([
            'uuid' => $uuid,
            'token' => $token,
            'ip' => get_client_ip_extend(),
            'member_id' => $member_id,
            'add_time' => time(),
        ]);
        if(!$flag) return false;

        cache('auto_login_'.$token,$member_id);
        return [
            'token_id' => $member_id,
            'key' => $token,
        ];
    }

    static function checkLogin($token,$member_id) {
        if(empty($token) || empty($member_id)) return false;

        $token_id = intval(input("post.token_id"));
        $member_id = intval(cache('auto_login_' . $token));
        if ($token_id === $member_id) {
            return $member_id;
        } else {
            $loginToken = self::where('token',$token)->find();
            if(empty($loginToken) || $loginToken['member_id']!=$token_id) return false;

            cache('auto_login_'.$token,$loginToken['member_id']);
            return $loginToken['member_id'];
        }
    }

    static function logout($token,$member_id) {
        if(empty($token) || empty($member_id)) return false;

        $loginToken = self::where('token',$token)->find();
        if(empty($loginToken) || $loginToken['member_id']!=$member_id) return false;

        cache('auto_login_'.$token,$loginToken['member_id'],null);
        self::where('id',$loginToken['id'])->delete();

        return true;
    }
}
