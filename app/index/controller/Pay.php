<?php

namespace app\index\controller;

use app\common\model\CurrencyUser;
use app\common\model\Currency;
use app\common\model\QianbaoAddress;
use app\common\model\Tibi;

class Pay extends Base
{
    //提币
    public function tcoin()
    {
        $currency_id = input("currency_id");
        if (!empty($currency_id)) {
            $page = input("page");//页码
            $rows = 10;
            //获取提币记录
            $result = Tibi::getTibiList($this->member_id, $currency_id, $page, $rows, "take");
            $data['list']=[];
            if ($result['code'] == SUCCESS) {
                $data['list'] = $result['result'];
            }
            //分页
            $data['count'] = Tibi::getTibiListCount($this->member_id, $currency_id, "take");
            $data['pages'] = $this->getPages($data['count'], $page, $rows);
            $data['addressList'] = [];
            //用户的地址本数据
            $addressResult = QianbaoAddress::getAddressList($this->member_id, $currency_id);
            if ($addressResult['code'] == SUCCESS) {
                $data['addressList'] = $addressResult['result'];
            }
            //用户可用资产
            $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $currency_id, "num");
            $data['user'] = $currencyUser->toArray();
            //币种数据
            $data['currency'] = Currency::where(['currency_id' => $currency_id])->field("currency_id,currency_name,currency_mark,tcoin_fee,currency_min_tibi,currency_all_tibi,currency_type")->find()->toArray();
            $this->assign($data);
        } else {
            return $this->error(lang("lan_modifymember_parameter_error"));
        }
        return $this->fetch('pay/tcoin');
    }

    //充币
    public function coin()
    {
        $currency_id = input("currency_id");//币种id
        $array = [];
        $list = [];
        $page = input("page");//页码
        $rows = 10;
        $result = Tibi::getTibiList($this->member_id, $currency_id, $page, $rows, "recharge");
        if ($result['code'] == SUCCESS) {
            $list = $result['result'];
        }
        if (!empty($currency_id)) {
            $currency = Currency::where(['currency_id' => $currency_id])->field("currency_name,currency_mark,recharge_address,currency_type")->find();
            $array['currency_name'] = $currency->currency_name;
            $array['currency_mark'] = $currency->currency_mark;
            $array['currency_type'] = $currency->currency_type;
            $createAddress = CurrencyUser::createWalletAddress($this->member_id, $currency_id);
            if ($createAddress['code'] == SUCCESS) {
                $array['tag'] = $this->member_id;
                if (in_array($currency->currency_type, ['xrp','eos'])) {
                    $array['address'] = $currency->recharge_address;
                } else {
                    $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $currency_id, "chongzhi_url");
                    $array['address'] = $currencyUser->chongzhi_url;
                }
            }else{
                $array['address'] = "";
            }
        }
        $count = Tibi::getTibiListCount($this->member_id, $currency_id, "recharge");
        $pages = $this->getPages($count, $page, $rows);
        $this->assign("pages", $pages);
        $this->assign("address", $array);
        $this->assign("list", $list);
        return $this->fetch('pay/coin');
    }

    /**
     * 删除一条帐本地址
     * Created by Red.
     * Date: 2019/2/25 15:41
     */
    function deleteAddress()
    {
        $id = input("post.id");
        $address = QianbaoAddress::deleteAddress($this->member_id, $id);;
       $this->mobileAjaxReturn($address);
    }
    /**添加一条常用地址
     * @param $names                 标签名
     * @param $address              地址
     * @param $currency_id          币种id
     * Created by Red.
     * Date: 2018/12/12 17:16
     */
    function addAddress()
    {
        $name = input("post.address_name");
        $address = input("post.address_url");
        $currency_id = input("post.currency_id");
        $tag = input("post.address_tag");
        $result = QianbaoAddress::addAddress($this->member_id, $name, $address, $currency_id, $tag);
        mobileAjaxReturn($result);
    }


}
