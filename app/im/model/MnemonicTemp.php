<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2019/5/14
 * Time: 17:20
 */

namespace app\im\model;


use app\common\model\Currency;
use app\common\model\Member;
use think\Exception;
use think\Log;

class MnemonicTemp extends Base
{
    /**
     * 创建一个新的助记词，并保存到数据库
     * @return array
     */
    static function create_mnemonic() {
        $res = [
            'code' => ERROR1,
            'message' => lang('lan_network_busy_try_again'),
            'result' =>  null,
        ];

        $mnemonic = self::create_new_mnemonic();
        $mt_encrypt = self::mnemonic_encrypt($mnemonic);
        $insert_id = self::insertGetId([
            'mt_mnemonic' =>$mnemonic,
            'mt_encrypt' => $mt_encrypt,
            'mt_status' => 2,
            'mt_time' => time(),
        ]);
        if(!$insert_id) {
            return $res;
        }

        $res['code'] = SUCCESS;
        $res['message'] = lang('lan_operation_success');
        $res['result'] = [
            'mnemonic_mark' => $mt_encrypt,
            'mnemonic' =>  $mnemonic,
        ];
        return $res;
    }

    // 助记词注册
    static function mnemonic_reg($uuid,$mnemonic,$mt_encrypt,$pwd,$repwd) {
        $r['message'] = lang("parameter_error");
        $r['code'] = ERROR1;
        $r['result'] = null;

        if (empty($uuid) || empty($mnemonic) || empty($mt_encrypt) ) {
            $r['message'] = lang('uuid mnemonic mt_encrypt empty');
            return $r;
        }

        if(empty($pwd)) {
            $r['message'] = lang('lan_password_not_empty');
            return $r;
        }
        if($pwd!=$repwd) {
            $r['message'] = lang('lan_password_is_different');
            return $r;
        }

        $real_decode = self::decryptionStr($pwd);
        $pwd = $real_decode['origin'];
        $realpwd = $real_decode['txt'];
        if($realpwd=="") {
            $r['message'] = lang('pwd decryption error');
            return $r;
        }

        if(!self::checkPwdFormat($realpwd)) {
            $r['message'] = lang('lan_password_format_error');
            return $r;
        }

        $real_decode = self::decryptionStr($mnemonic);
        $mnemonic = $real_decode['txt'];
        if($mnemonic=="") {
            $r['message'] = lang('mnemonic decryption error');
            return $r;
        }

        $mnemoic_info = self::where('mt_encrypt',$mt_encrypt)->where('mt_mnemonic',$mnemonic)->where('mt_status',2)->find();
        if(empty($mnemoic_info)) {
            $r['message'] = lang('mnemonic_error');
            return $r;
        }

        try {
            self::startTrans();

            $flag = self::where('mt_id',$mnemoic_info['mt_id'])->where('mt_status',2)->setField('mt_status',1);
            if(!$flag) throw new Exception(lang("lan_network_busy_try_again"));

            $time = time();
            $data = [
                'uuid' => $uuid,
                'pwd' => $pwd,
                'pwdtrade' => '',
                'pid' => 0,
                'reg_time' => $time,
                'active_time' => $time,
                'active_status' => 1,
                'status' => 1,
                'mnemonic_show' => $mnemoic_info['mt_mnemonic'],
                'ip' => get_client_ip_extend(),
                'login_ip' => get_client_ip_extend(),
                'login_time' => $time,
                'login_type' => 3,
            ];
            //添加新用户
            $member_id = Member::insertGetId($data);
            if (!$member_id) throw new Exception(lang("lan_network_busy_try_again"));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang("lan_operation_success");
            $r['result'] = $member_id;
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = lang('lan_reg_the_network_busy');
        }
        return $r;
    }

    // 助记词导入
    static function mnemonic_import($mnemonic,$uuid,$pwd,$repwd) {
        $r['message'] = lang("parameter_error");
        $r['code'] = ERROR1;
        $r['result'] = null;

        if (empty($mnemonic) || empty($uuid) ) return $r;

        if(empty($pwd)) {
            $r['message'] = lang('lan_password_not_empty');
            return $r;
        }
        if($pwd!=$repwd) {
            $r['message'] = lang('lan_password_is_different');
            return $r;
        }

        $real_decode = self::decryptionStr($pwd);
        $pwd = $real_decode['origin'];
        $realpwd = $real_decode['txt'];
        if($realpwd=="") return $r;

        if(!self::checkPwdFormat($realpwd)) {
            $r['message'] = lang('lan_password_format_error');
            return $r;
        }

        $real_decode = self::decryptionStr($mnemonic);
        $mnemonic = $real_decode['txt'];
        if($mnemonic=="") return $r;

        $member = Member::where('mnemonic_show',$mnemonic)->find();
        if(empty($member)) {
            $r['message'] = lang('mnemonic_error');
            return $r;
        }

        //更新密码
        Member::where('member_id',$member['member_id'])->update([
            'pwd' => $pwd,
            'login_ip' => get_client_ip_extend(),
            'login_time' => time(),
        ]);

        $result = MnemonicToken::login_token($uuid,$member['member_id']);
        if($result===false) {
            $r['message'] = lang('lan_network_busy_try_again');
            return $r;
        }

        $r['message'] = lang('lan_operation_success');
        $r['code'] = SUCCESS;
        $r['result'] = $result;
        return $r;
    }

    // 助记词导入
    static function mnemonic_create_wallet($member_id,$currency_parent,$currency_name,$currency_token,$public_key,$private_key,$pwd,$repwd) {
        $r['message'] = lang("parameter_error");
        $r['code'] = ERROR1;
        $r['result'] = null;

        if ($member_id<=0 || empty($public_key) || empty($private_key) ) {
            return $r;
        }

        if(empty($pwd)) {
            $r['message'] = lang('lan_password_not_empty1');
            return $r;
        }
        if($pwd!=$repwd) {
            $r['message'] = lang('lan_password_is_different2');
            return $r;
        }

        $real_decode = self::decryptionStr($pwd);
        $pwd = $real_decode['origin'];
        $realpwd = $real_decode['txt'];
        if($realpwd=="") {
            $r['message'] = lang('pwd decryption err');
            return $r;
        }

        $real_decode = self::decryptionStr($public_key);
        $public_key = $real_decode['origin'];
        $real_public_key = $real_decode['txt'];
        if($real_public_key=="") {
            $r['message'] = lang('public_key decryption err');
            return $r;
        }

        $real_decode = self::decryptionStr($private_key);
        $private_key = $real_decode['origin'];
        $real_private_key = $real_decode['txt'];
        if($real_private_key=="") {
            $r['message'] = lang('real_private_key decryption err');
            return $r;
        }

        if(!self::checkPwdFormat($realpwd)) {
            $r['message'] = lang('lan_password_format_error');
            return $r;
        }

        $member = Member::where('member_id',$member_id)->field('member_id')->find();
        if(empty($member)) {
            return $r;
        }

        $parent_id = 0;
        if(!empty($currency_parent)) {
            $real_decode = self::decryptionStr($currency_parent);
            $currency_parent = $real_decode['txt'];
            if($currency_parent=="") {
                $r['message'] = lang('currency_parent decryption err');
                return $r;
            }

            $parent_wallet = MnemonicWallet::where('public_key',$real_decode['origin'])->where('member_id',$member_id)->find();
            if(empty($parent_wallet)) {
                $r['message'] = lang('currency_parent 不存在');
                return $r;
            }

            $parent_id = $parent_wallet['id'];
        } else {
            $currency_system = Currency::where('currency_mark',$currency_name)->field('currency_id')->find();
            if(empty($currency_system)) return $r;
        }

        $flag = MnemonicWallet::insertGetId([
            'parent_id' => $parent_id,
            'member_id' => $member_id,
            'public_key' => $public_key,
            'private_key' => $private_key,
            'currency_name' => $currency_name,
            'currency_token' => empty($currency_token) ? '' : $currency_token,
            'pwd' => $pwd,
            'add_time' => time(),
        ]);
        if(!$flag) {
            $r['message'] = lang('lan_network_busy_try_again');
            return $r;
        }

        $r['message'] = lang('lan_operation_success');
        $r['code'] = SUCCESS;
        return $r;
    }



    /**
     * 创建一个助记词，并和数据库对比是否存在，返回一个数据库不存在的助记词
     * @return string
     * Created by Red
     * Date: 2019/5/15 18:32
     */
    static function create_new_mnemonic(){
        $rand_array= self::unique_rand(1,50665,12);
        include_once APP_PATH.'/extra/EnglishWords.php';
        $english_array=json_decode(english_words(),true);
        $mnemonic="";
        foreach ($rand_array as $value){
            $mnemonic.=empty($mnemonic)?$english_array[$value]:",".$english_array[$value];
        }
        $mnemonic_encrypt=self::mnemonic_encrypt($mnemonic);
        $find= self::where(['mt_encrypt'=>$mnemonic_encrypt])->find();
        if(empty($find)){
           return $mnemonic;
        }else{
           return self::create_new_mnemonic();
        }
    }

    static function mnemonic_encrypt($mnemonic) {
        $str = "imtoken" . $mnemonic . "wallet";
        return md5(md5(md5($str)));
    }

    static function unique_rand($min, $max, $num)
    {
        $count = 0;
        $return = array();
        while ($count < $num) {
            $return[] = mt_rand($min, $max);
            $return = array_flip(array_flip($return));
            $count = count($return);
        }
        //打乱数组，重新赋予数组新的下标
        shuffle($return);
        return $return;
    }

    //检测密码格式
    static function checkPwdFormat($pwd) {
        $length = strlen($pwd);
        if($length<6 || $length>20) return false;

        if (!preg_match('/^[a-zA-z0-9]{8,20}$/', $pwd) || preg_match('/^[A-Za-z]+$/', $pwd) || preg_match('/^[0-9]+$/', $pwd)) {
            return false;
        }
        return true;
    }

    static function password($value)
    {
        return md5(substr(md5(md5($value).config('extend.password_halt')),8));
    }

    static function decryptionStr($origin) {
        $res = [
            'origin' => $origin,
            'txt' => '',
        ];

        $txt = decryptionEncodeStr($res['origin']);
        if(!empty($txt)) {
            $res['txt'] = $txt;
            return $res;
        }

        $res['origin'] = urldecode($res['origin']);
        $txt = decryptionEncodeStr($res['origin']);
        if(!empty($txt)) {
            $res['txt'] = $txt;
            return $res;
        }

        return $res;
    }
}
