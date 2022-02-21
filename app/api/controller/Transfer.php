<?php

namespace app\api\controller;


use app\common\model\Member;
use app\common\model\TransferToAsset;
use app\common\model\TransferToAssetConfig;
use app\common\model\TransferToBalance;
use app\common\model\TransferToFinancial;
use think\Exception;
use think\response\Json;

class Transfer extends Base
{
    public $public_action = ['receive', 'check_address'];

    /**
     * 转到砝码
     * @return mixed
     * @throws Exception
     */
    public function transfer_out()
    {
        $currency_id = intval(input('post.currency_id'));
        $to_address = strval(input('post.to_address'));
        $to_num = input('post.money'); //实际到账数量
        $check_box = input("check_box");
        $address_name = input("address_name");
        $address_num = input("address_num");
        $remark = input("remark");
        $paypwd = input("post.paypwd");
        $validateSmsCode = cache("validateSmsCode_member_id_" . $this->member_id,"");
        if (!$validateSmsCode) {
            $r['message'] = lang("lan_verification_not_pass");
            $r['result'] = null;
            $r['code'] = ERROR1;
            return $this->output_new();
        }
        $password = Member::verifyPaypwd($this->member_id, $paypwd);
        if (SUCCESS != $password['code'] or empty($paypwd)) {
            return $this->output_new($password);
        }

        $result = TransferToBalance::transfer_out($this->member_id, $currency_id, $to_address, $to_num, $check_box, $address_name, $address_num);
        if (SUCCESS == $result['code']) {
            cache("validateSmsCode_member_id_" . $this->member_id, NULL);//删除缓存
        }
        return $this->output_new($result);
    }

    /**
     * 接收定时任务的推送
     * @return mixed
     * @throws Exception
     */
    public function receive()
    {
        $this->check_sign();

        $currency_mark = input('post.currency_mark');
        $to_address = input('post.to_address');
        $to_num = input('post.to_num');
        $third_id = intval(input('post.third_id'));
        $result = TransferToBalance::transfer_in($currency_mark, $to_address, $to_num, $third_id);

        return json($result);
    }


    /**
     * 检测本平台地址是否存在
     * @return Json
     * @throws Exception
     */
    public function check_address() {
         $this->check_sign();

        $currency_mark = strval(input('post.currency_mark'));
        $to_address = strval(input('post.to_address'));
        $result = TransferToBalance::check_self_address_is_exist($currency_mark,$to_address);
        return json($result);
    }

    /**
     * @return void|mixed
     */
    private function check_sign()
    {
        $data = input('post.');
        $postsign = input("post.sign");
        $sign = createSign($data, TransferToBalance::KEY);
        if ($sign != $postsign) return json(['code' => ERROR12, 'msg' => lang('parameter_error'), 'result' => null]);
    }

    //资产包首页
    public function asset_index()
    {
        $asset_type = input('post.asset_type');
        $result =  TransferToAssetConfig::get_asset_currency($this->member_id,false,$asset_type);
        return $this->output_new($result);
    }

    //理财包首页
    public function financial_index()
    {
        $asset_type = input('post.asset_type');
        $result =  TransferToAssetConfig::get_asset_currency($this->member_id,true,$asset_type);
        return $this->output_new($result);
    }

    //兑换详情
    public function exchange_info() {
        $from_currency_id = intval(input('post.from_currency_id'));
        $to_currency_id = intval(input('post.to_currency_id'));
        $asset_type = input('post.asset_type');
        $result = TransferToAssetConfig::get_currency_info($this->member_id,$from_currency_id,$to_currency_id,$asset_type);
        return $this->output_new($result);
    }

    //兑换资产包
    public function exchange_to_asset() {
        $from_currency_id = intval(input('post.from_currency_id'));
        $from_num = input('post.from_num');
        $to_currency_id = intval(input('post.to_currency_id'));
        $asset_type = input('post.asset_type');
        $result = TransferToAsset::exchange($this->member_id,$from_currency_id,$from_num,$to_currency_id,$asset_type);
        return $this->output_new($result);
    }

    //资产包互转
    public function transfer_asset() {
        $currency_id = intval(input('post.currency_id'));
        $to_account = input('post.to_account');
        $num = input('post.num');
        $to_member_id = input('post.to_member_id');
        $asset_type = input('post.asset_type');
        $result = TransferToAsset::transfer($this->member_id,$to_account,$to_member_id,$currency_id,$num,$asset_type);
        return $this->output_new($result);
    }

    //转入理财包
    public function transfer_financial() {
        $currency_id = intval(input('post.currency_id'));
        $num = input('post.num');
        $asset_type = input('post.asset_type');
        $result = TransferToAsset::transfer_to_financial($this->member_id,$currency_id,$num,$asset_type);
        return $this->output_new($result);
    }

    //资产包详情列表
    public function asset_list() {
        $type = input('post.type');
        $currency_id = intval(input('post.currency_id'));
        $asset_type = input('post.asset_type');
        $page = input('post.page');
        $income_type = trim(input('post.income_type'));
        $result = TransferToAsset::get_list($this->member_id,$asset_type,$currency_id,$type,$income_type,$page);
        return $this->output_new($result);
    }

    //理财包详情列表
    public function financial_list() {
        $type = input('post.type');
        $currency_id = intval(input('post.currency_id'));
        $asset_type = input('post.asset_type');
        $page = input('post.page');
        $income_type = trim(input('post.income_type'));
        $result = TransferToFinancial::get_list($this->member_id,$asset_type,$currency_id,$type,$income_type,$page);
        return $this->output_new($result);
    }
}