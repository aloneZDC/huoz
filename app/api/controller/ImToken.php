<?php
//imToken
namespace app\api\controller;

use app\im\model\MnemonicHot;
use app\im\model\MnemonicTemp;
use app\im\model\MnemonicToken;
use app\im\model\MnemonicWallet;

class ImToken extends Base
{
    protected $public_action  = ['create_mnemonic','submit_reg','submit_import','hot_token'];

    /**
     * 生成一个新的助记词
     */
    function create_mnemonic()
    {
        $result = MnemonicTemp::create_mnemonic();
        return $this->output_new($result);
    }

    /**
     * 注册帐号
     * @return \json
     * @throws \think\exception\PDOException
     * Created by Red
     * Date: 2019/5/20 14:00
     */
    function submit_reg()
    {
        $uuid = strval(input('post.uuid',''));
        $mnemonic = strval(input('post.mnemonic','')); //助记词
        $mt_encrypt = strval(input('post.mnemonic_mark','')); //助记词加密
        $pwd = strval(input('post.pwd',''));
        $repwd = strval(input('post.repwd',''));
        //注册
        $res = MnemonicTemp::mnemonic_reg($uuid,$mnemonic,$mt_encrypt,$pwd,$repwd);
        if($res['code']!=SUCCESS) {
            return $this->output_new($res);
        }

        $member_id = intval($res['result']);
        // 缓存登录信息
        $result = MnemonicToken::login_token($uuid,$member_id);
        if($result===false) {
            return $this->output_new([
                'code' => ERROR1,
                'message' => lang("lan_network_busy_try_again"),
                'result' => null
            ]);
        }

        $res['result'] = $result;
        return $this->output_new($res);
    }

    // 导入
    function submit_import() {
        $mnemonic = strval(input('post.mnemonic','')); //助记词
        $uuid = strval(input('post.uuid',''));
        $pwd = strval(input('post.pwd',''));
        $repwd = strval(input('post.repwd',''));

        //注册
        $res = MnemonicTemp::mnemonic_import($mnemonic,$uuid,$pwd,$repwd);
        return $this->output_new($res);
    }

    // 导入地址 获取以前的地址
    function init_wallet() {
        $res = [
            'code' => SUCCESS,
            'message' => lang('success'),
            'result' => MnemonicWallet::getWallet($this->member_id)
        ];
        return $this->output_new($res);
    }

    // 创建钱包
    function create_wallet() {
        $currency_parent = strval(input('post.parent_address'));
        $currency_name = strval(input('post.currency_name'));
        $currency_token = strval(input('post.currency_token',''));
        $private_key = strval(input('post.private_key')); //需加密
        $public_key = strval(input('post.public_key')); //需加密
        $pwd = strval(input('post.pwd'));  //需加密
        $repwd = strval(input('post.repwd')); //需加密

        //注册
        $res = MnemonicTemp::mnemonic_create_wallet($this->member_id,$currency_parent,$currency_name,$currency_token,$public_key,$private_key,$pwd,$repwd);
        return $this->output_new($res);
    }

    function hot_token() {
        $res = [
            'code' => SUCCESS,
            'message' => lang('success'),
            'result' => MnemonicHot::getHot()
        ];
        return $this->output_new($res);
    }

    function logout() {
        $res = [
            'code' => ERROR1,
            'message' => lang("lan_network_busy_try_again"),
            'result' => null
        ];
        $key = input('post.key', '', 'strval');
        $flag = MnemonicToken::logout($key,$this->member_id);
        if(!$flag) return $this->output_new($res);

        $res['code'] = SUCCESS;
        $res['message'] = lang('success');
        return $this->output_new($res);
    }
}
