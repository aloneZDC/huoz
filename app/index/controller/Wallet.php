<?php

namespace app\index\controller;

use app\common\model\Currency;
use app\common\model\CurrencyUser;
use app\common\model\Member;
use app\common\model\MoneyInterest;
use app\common\model\MoneyInterestConfig;
use app\common\model\QianbaoAddress;
use app\common\model\Tibi;
use app\common\model\Transfer;
use message\Btc;

class Wallet extends Base
{   //钱包首页
    public function index()
    {
        $is_hide = input("is_hide");
        $listReault = CurrencyUser::assetList($this->member_id);
        $list = [];
        $totalMoney = $this->getUsersAssetConversion1();
        if ($listReault['code'] == SUCCESS) {
            $list = $listReault['result'];
            foreach ($list as $k => $value) {
                $all = $value['money'] + $value['num_award'] + $value['forzen_num'];
                if ($is_hide == 1 && $all == 0) {
                    unset($list[$k]);
                }
            }
        }
        $currency = model('currency')->online_list();
        $currency_id = input('currency_id', "", 'intval');
        $type = input('type', 0, 'intval'); //1收入 2支出
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $tab = input("tab");
        $where = [];
        if ($tab == 3) {
            $currency_id = 8;
            $where['a.type'] = 18;
        }
        $count = true;

        //账单或者互转日志（xrp钱包对xrp钱包）

        $account_list = model('AccountBook')->getLog($this->member_id, $currency_id, $type, $page, $page_size, $this->lang, $count, $where);
        $model_transfer = new Transfer();
        $r = $model_transfer->currency_xrp($this->member_id);
        $pages = $this->getPages($count, $page, $page_size);
        $this->assign("is_hide", $is_hide);
        $this->assign("list", $list);
        $this->assign("is_hide", $is_hide);
        $this->assign("totalMoney", $totalMoney);

        return $this->fetch('wallet/index', ['account_list' => $account_list, 'pages' => $pages, 'currency' => $currency, 'currency_id' => $currency_id, 'xrp_money' => $r['result']]);
    }



    /**
     * 提交提币申请
     * @param $currency_id              币种id
     * @param $address                  接收地址
     * @param $money                    转出数量(这里为实际到帐的数量,手续费还没算上;2019-02-14改为实际到帐+手续费)
     * @param $remark                   备注
     * @param $tag                      瑞波币的数字标签（非瑞波币不用转此参数）
     * @param $names                    地址的名称
     * @param $checkbox                 选中的勾选为：2
     * @param $paypwd                   支付密码
     * Created by Red.
     * Date: 2018/12/13 19:40
     */
    function submitTakeCoin()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $currency_id = input("post.currency_id");
//        $address = input("post.address");
        $money = input("post.money");//实际到帐的数量(手续费还没算上)
        $remark = input("post.remark");
//        $tag = input("post.tag");
        $address_id= input("post.address_id");
//        $checkbox = input("post.checkbox");//选中时为2
        $paypwd = input("post.paypwd");
        $phone_code = input("post.phone_code");
        if (!empty($currency_id) && !empty($address_id) && $money > 0 && !empty($paypwd) && !empty($phone_code)) {
         $address= QianbaoAddress::where(['id'=>$address_id,'user_id'=>$this->member_id])->find();
         if(!empty($address)){
             $result = model('Sender')->auto_check($this->member_id, "tcoin", $phone_code);
             if (is_string($result)) {
                 $r['message'] = $result;
             } else {
                 //验证支付密码
                 $password = Member::verifyPaypwd($this->member_id, $paypwd);
                 if ($password['code'] == SUCCESS) {
                     $tibi = Tibi::addTibi($this->member_id, $address->qianbao_url, $money, $currency_id, $remark, $address->tag);
                     if ($tibi['code'] == SUCCESS) {
                         model('Sender')->auto_check($this->member_id, "tcoin", $phone_code,true);
                         mobileAjaxReturn($tibi);
                     } else {
                         mobileAjaxReturn($tibi);
                     }
                 } else {
                     $r['message'] = $password['message'];
                 }
             }
         }else{
            $r['message']=lang("lan_change_Choose_integration_address");
         }

        }
        mobileAjaxReturn($r);
    }

    //互转（转账）@标
    public function transfers()
    {
        $model_transfer = new Transfer();
        $r = $model_transfer->currency_xrp($this->member_id);

        $model_transfer = new Transfer();
        $type = input('post.type', 'all');
        $page = input('post.page', 1, 'intval');
        $page_size = input('post.page_size', 10, 'intval');
        $count = true;
        $res = $model_transfer->detail_xrp($type, $this->member_id, $page, $page_size, $count);
        $pages = $this->getPages($count, $page, $page_size);
        return $this->fetch('wallet/transfers', ['xrp_money' => $r['result'], 'xrp_list' => $res['result'], 'pages' => $pages]);
    }

    //互转操作@标
    public function ajax_operation()
    {
        $account = input('post.account', '', 'trim');
        $to_member_id = input('post.to_member_id', 0, 'trim');
        $num = input('post.num', 0, 'trim');
        $pwd = input('post.pwd', '', 'trim');
        $phone_code = strval(input('phone_code',''));
        $model_transfer = new Transfer();
        $r = $model_transfer->transfer_accounts('1', $account, $this->member_id, $to_member_id, $num, $pwd,$phone_code);
        if ($r['code'] == 10000) {
            $data['status'] = 1;
        } else {
            $data['status'] = 0;
        }
        $data['info'] = $r['message'];
        mobileAjaxReturn($data);
    }

    /**
     * @desc 转账
     */
    public function accountQuery()
    {

        $model_transfer = new Transfer();
        $r = $model_transfer->currency_xrp($this->member_id);
        mobileAjaxReturn($r);

    }


}
