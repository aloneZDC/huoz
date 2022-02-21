<?php
namespace app\common\model;

use think\Model;

class EmailDomain extends Model
{
    static function get_list(){
        return self::select();
    }

    static function checkEmail($email) {
        $domain = explode('@',$email);
        if(count($domain)!=2) return false;

        $info = self::where(['domain'=>$domain[1]])->find();
        if(!$info) return false;

        return true;
    }
}
