<?php
namespace app\api\controller;


use app\common\model\CurrencyUser;
use app\common\model\CurrencyUserTransfer;
use app\common\model\GameLockLog;
use app\common\model\Member;

class Votes extends Base
{
    //资产互转
    public function transfer()
    {
        // 防止高并发请求
        if (public_thread() === false) {
            $this->output(ERROR1, '请求太频繁，稍后再试');
        }
        $currency_id = intval(input('currency_id', 0));
        $target_user_id = intval(input('target_user_id', 0));
        $target_account = strval(input('target_account', ''));
        $type = strval(input('type', 'num'));
        $num = input('num', 0);
        $memo = strval(input('memo', ''));
        $paypwd = input("paypwd");
        $phone_code = input("phone_code");
        $target_account_verify = strval(input('target_account_verify', ''));

        $result = model('Sender')->auto_check($this->member_id, "transfer", $phone_code);
        if (is_string($result)) {
            $this->output_new([
                'code' => ERROR1,
                'message' => $result,
                'result' => null
            ]);
        }

        $password = Member::verifyPaypwd($this->member_id, $paypwd);
        if ($password['code'] == ERROR1) {
            $this->output_new($password);
        }

        // 敏感用户禁止转账
        $isSensitive = Member::where('member_id', $this->member_id)->value('is_sensitive');
        if (1 == $isSensitive) {
            return $this->output_new([
                'code' => ERROR1,
                'message' => lang('operation_deny'),
                'result' => null
            ]);
        }

        $target = Member::where('member_id', $target_user_id)->find();
        if ( !$target || ( $target['phone'] != $target_account and $target['email'] != $target_account) ) {
            return $this->output_new([
                'code' => ERROR1,
                'message' => lang('lan_account_member_not_exist'),
                'result' => null
            ]);
        }

//        $currency_user = CurrencyUser::where(['currency_id'=>$currency_id,'chongzhi_url'=>$target_account])->find();
//        if($currency_user) {
//            $target = Member::where('member_id', $currency_user['member_id'])->find();
//            if($target) $target_account = $target['ename'];
//        } else {
//            $target = Member::where('ename', $target_account)->find();
//        }
//
//        if ( !$target || ( $target['phone'] != $target_account_verify and $target['email'] != $target_account_verify) ) {
//            return $this->output_new([
//                'code' => ERROR1,
//                'message' => lang('lan_account_member_not_exist'),
//                'result' => null
//            ]);
//        }

        $result = CurrencyUserTransfer::transfer($this->member_id,$currency_id,$target['member_id'],$target_account,$num,$type,$memo);
        $this->output_new($result);
    }

    public function transfer_config() {
        $currency_id = intval(input('currency_id',0));
        $type = strval(input('type','num'));
        $result = CurrencyUserTransfer::get_config($this->member_id,$currency_id,$type);
        $this->output_new($result);
    }

    //IO券记录
    public function game_lock_list() {
        $income_type = input('income_type','strval,trim');
        if($income_type=='ins') $income_type = 'in';
        $page = intval(input("post.page"));
        $result = GameLockLog::get_list($this->member_id,$income_type,$page);
        $this->output_new($result);
    }
}
