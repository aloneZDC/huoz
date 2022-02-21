<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/12
 * Time: 9:23
 */

namespace app\api\controller;


use app\common\model\AccountBook as AccountBookModel;
use app\common\model\ArkConfig;
use app\common\model\Config;
use app\common\model\Currency;
use app\common\model\CurrencyAssetChange;
use app\common\model\CurrencyLog;
use app\common\model\CurrencyPriceTemp;
use app\common\model\CurrencyTakeLog;
use app\common\model\CurrencyUser;
use app\common\model\CurrencyUserAwardLog;
use app\common\model\CurrencyUserBbTransfer;
use app\common\model\Member;
use app\common\model\MemberBind;
use app\common\model\QianbaoAddress;
use app\common\model\Recharge;
use app\common\model\TakePush;
use app\common\model\Tibi;
use app\common\model\Trade;
use app\common\model\UserAirLevel;
use app\common\model\UserCurrencyAuth;
use app\common\model\WalletAdminAddress;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Request;

class Wallet extends Base
{
    /**
     * @var string 充币和提币的签名
     */
    const SIGN = '20200403BI';
    protected $public_action = ['income', 'takeincome', 'take', 'summary_address'];

    // 获取用户的资产列表
    public function asset_list()
    {
        $is_bb = intval(input("post.is_bb"));
        $result = CurrencyUser::asset_list($this->member_id,$is_bb);
        $this->output_new($result);
    }

    /**
     * 获取用户的资产列表
     * Created by Red.
     * Date: 2018/12/12 11:50
     */
    public function asset_list_old()
    {
        $is_bb = intval(input("post.is_bb"));
        $tt = CurrencyUser::assetList($this->member_id, $is_bb);
//        $assetConversion=$this->getUsersAssetConversion1();
        if ($tt['code'] == SUCCESS) {
//            $btc=Db::name("currency")->where(['currency_mark'=>"BTC"])->field("currency_id")->find();
//            $exchange_rate_type = 'USD'; //strtoupper($this->exchange_rate_type);
//            $exchange_rate_type = 'CNY'; //strtoupper($this->exchange_rate_type);

//            $btc_price= CurrencyPriceTemp::get_price_currency_id($btc['currency_id'],$exchange_rate_type);
//            if(!$btc_price) $btc_price = 0;

            //币种价格来源是币币交易的币种
//            $bb_map = [
//                Currency::DNC_ID => [
//                    'currency_id' => Currency::DNC_ID,
//                    'currency_trade_id' => Currency::XRP_ID,
//                ],
//            ];

            $all_cny = 0;
            $all_usdt = 0;
            foreach ($tt['result'] as &$value) {
//                $cny = 0;
//                $trade_currency_id = explode(',',$value['trade_currency_id']);
//                if(isset($value['is_trade_currency'])==1 && !empty($trade_currency_id[0])) {
//                    $cny = Trade::getCurrencyRealMoney($value['currency_id'],$trade_currency_id[0],$exchange_rate_type);
//                } else {
//                    $cny = CurrencyPriceTemp::get_price_currency_id($value['currency_id'],$exchange_rate_type);
//                }
//                $value['cny'] = keepPoint($cny * $value['all_num'],6);
//                $value['new_price_unit']=$exchange_rate_type=="CNY"?"¥":"$";
//                if($value['currency_id']==Currency::KOI_ID) {
//                    $value['is_show_keep_num'] = 1;
//                } else {
//                    $value['is_show_keep_num'] = 2;
//                }

                $all_cny += $value['cny_num'];
                $all_usdt += $value['usdt_num'];
            }
            $result = $tt['result'];
//            $data['all_btc']=$btc_price>0 ? bcdiv($all_money,$btc_price,6) : 0;
            $data['all_cny'] = keepPoint($all_cny);
            $data['all_usdt'] = keepPoint($all_usdt, 6);
//            $data['new_price_unit']=$exchange_rate_type=="CNY"?"¥":"$";
            $data['list'] = $result;
            $data['other_list'] = [];

            // TODO: 暂时去掉详情或描述 yhl 2020-10-24
//            $data['desc'] = [
//                'contract_des' => lang('contract_des'),
//                'reg_award_des' => lang('reg_award_des'),
//                'node_award_des' => lang('node_award_des'),
//            ];

//            $other_list = HongbaoKeepLog::num_list($this->member_id);
//            if($other_list['code']==SUCCESS) {
//                $data['other_list'] = $other_list['result'];
//                foreach ($other_list['result'] as $item) {
//                    $data['all_money'] = keepPoint($data['all_money']+$item['money']);
//                }
//            }

            $tt['result'] = $data;
        }
        $this->output_new($tt);
    }

    public function currencyAuth()
    {
        $currencies = Currency::field('currency_id, currency_name, currency_logo as logo')->where('is_app_currency', 1)->select();
        foreach ($currencies as &$currency) {
            $currency['is_auth'] = UserCurrencyAuth::isAuth($this->member_id, $currency['currency_id']);
        }

        return $this->output_new([
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => $currencies
        ]);
    }

    public function setCurrencyAuth(Request $request)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        $currencyId = $request->post('currency_id', null, 'intval');
        $isAuth = $request->post('is_auth', null, 'intval');
        if (empty($currencyId) or !in_array($isAuth, [0, 1])) {
            return $this->output_new($r);
        }

        $auth = (new UserCurrencyAuth())->where('user_id', $this->member_id)->where('currency_id', $currencyId)->find();
        if (empty($auth)) {
            $auth = (new UserCurrencyAuth());
            $auth['user_id'] = $this->member_id;
            $auth['currency_id'] = $currencyId;
        }
        $auth['is_auth'] = $isAuth;
        if (!$auth->save()) {
            $r['message'] = lang('system_error_please_try_again_later');
            return $this->output_new($r);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_operation_success');
        return $this->output_new($r);
    }

    /**
     * 获取用户的充提币记录
     * @param $currency_id              币种id
     * @param $page                     页数(默认第1页)
     * @param $rows                     每页条目数(默认每页10条)
     * Created by Red.
     * Date: 2018/12/12 15:19
     */
    function getTibiList()
    {
        $currency_id = input("post.currency_id");
        $page = input("post.page");
        $rows = input("post.rows");
        $status = input("post.status", "all");
        $result = Tibi::getTibiList($this->member_id, $currency_id, $page, $rows, $status);
        $this->output_new($result);
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
        $name = input("post.names");
        $address = input("post.address");
        $currency_id = input("post.currency_id");
        $tag = input("post.tag");
        $result = QianbaoAddress::addAddress($this->member_id, $name, $address, $currency_id, $tag);
        $this->output_new($result);
    }

    /**根据用户id和币种id获取用户的常用地址列表
     * @param $currency_id      币种id
     * Created by Red.
     * Date: 2018/12/12 17:49
     */
    function getAddressList()
    {
        $currency_id = input("post.currency_id");
        $result = QianbaoAddress::getAddressList($this->member_id, $currency_id);
        $this->output_new($result);
    }

    /**
     * 删除一条常用地址
     * @param $id           地址id
     * Created by Red.
     * Date: 2018/12/12 19:07
     */
    function deleteAddress()
    {
        $id = input("post.id");
        $result = QianbaoAddress::deleteAddress($this->member_id, $id);
        $this->output_new($result);
    }

    /**
     * 获取前七天的资产变动列表
     * @param $currency_id      币种id
     * Created by Red.
     * Date: 2018/12/13 10:07
     */
    function getAssetChangeList()
    {
        $currency_id = input("post.currency_id");
        $result = CurrencyAssetChange::getAssetChangeList($this->member_id, $currency_id);
        $this->output_new($result);
    }

    /**
     * 根据币种id获取币种资产等信息
     * @param $currency_id  币种id
     * @throws Exception
     * Created by Red.
     * Date: 2018/12/13 11:53
     */
    public function getCurrencyUser()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $currency_id = input("post.currency_id");
        $cu = CurrencyUser::getCurrencyUser($this->member_id, $currency_id, "num");
        if (!empty($cu)) {
            $currency = Currency::where(['currency_id' => $currency_id])->field("currency_id,take_switch,currency_name,currency_mark,tcoin_fee,currency_min_tibi,currency_all_tibi,currency_logo,is_tag, fee_type, fee_number_flag, fee_greater, fee_less")->find()->toArray();

            // 20210322 暂时去掉提币开关
            if (empty($currency) /*or $currency['take_switch'] == 2*/) {
                $r['code'] = ERROR5;
                $r['message'] = lang('lan_coin_temporarily_closed');
                return $this->output_new($r);
            }
            //方舟抽水账户，免手续费
            $hxy_user_id = Config::get_value('hxy_user_id', '');//账户
            $reward_currency_id = ArkConfig::getValue('reward_currency_id');
            $integral_currency_id = ArkConfig::getValue('integral_currency_id');
            if ($hxy_user_id == $this->member_id && in_array($currency_id, [$reward_currency_id,$integral_currency_id])) {
                $currency['fee_number_flag'] = 0;
                $currency['fee_less'] = 0;
                $currency['fee_greater'] = 0;
            } else {
                $currency['fee_less'] = (double)$currency['fee_less'];
                $currency['fee_number_flag'] = (double)$currency['fee_number_flag'];
            }

            $airInfo = (new UserAirLevel())->getAirInfo($this->member_id);
            $airLevelTakeCoinFee = Config::get_value('air_level_take_coin_fee');
            if (1 == $currency['fee_type'] and $airInfo['level_id'] >= $airLevelTakeCoinFee) {
                $airLevelTakeCoinFeeRadio = Config::get_value('air_level_take_coin_fee_radio');
                $radio = $airLevelTakeCoinFeeRadio * .01;
                $currency['tcoin_fee'] = $radio;
            }
            /*if (Currency::USDT_ID == $currency_id) {
                $currency['currency_name'] = "OMNI";
            }*/

            $currency['num'] = $cu->num;
            $price = CurrencyPriceTemp::get_price_currency_id($currency_id, strtoupper($this->exchange_rate_type));
            $currency['cny'] = $price ? keepPoint($price, 2) : 0;
            $currency['new_price_unit'] = $this->exchange_rate_type == "CNY" ? "¥" : "$";
            $ERC20Currency = Currency::where('currency_id', Currency::ERC20_ID)->find();
            $TRC20Currency = Currency::where('currency_id', Currency::TRC20_ID)->find();
            if (Currency::USDT_ID == $currency_id and $ERC20Currency['take_switch'] == 1) {
                // 额外获取ERC20的信息
                $erc20 = $this->getERC20CurrencyUser(Currency::ERC20_ID);
                if (1 == $erc20['fee_type'] and $airInfo['level_id'] >= $airLevelTakeCoinFee) {
                    $airLevelTakeCoinFeeRadio = Config::get_value('air_level_take_coin_fee_radio');
                    $radio = $airLevelTakeCoinFeeRadio * .01;
                    $erc20['tcoin_fee'] = $radio;
                }
                $omniSwitch = Config::get_value('omni_switch', 2); // 默认开启 Omni
                if ($omniSwitch == 1) {
                    $result = [$erc20];
                } else {
                    $result = [$currency, $erc20];
                }
            } else if (Currency::USDT_ID == $currency_id and $TRC20Currency['take_switch'] == 1) {
                // 额外获取TRC20的信息
                $trc20 = $this->getERC20CurrencyUser(Currency::TRC20_ID);
                if (1 == $trc20['fee_type'] and $airInfo['level_id'] >= $airLevelTakeCoinFee) {
                    $airLevelTakeCoinFeeRadio = Config::get_value('air_level_take_coin_fee_radio');
                    $radio = $airLevelTakeCoinFeeRadio * .01;
                    $erc20['tcoin_fee'] = $radio;
                }
                $omniSwitch = Config::get_value('omni_switch', 2); // 默认开启 Omni
                if ($omniSwitch == 1) {
                    $result = [$trc20];
                } else {
                    $result = [$currency, $trc20];
                }
            } else {
                if ($currency['currency_id'] == Currency::KOI_ID) {
                    $currency['is_show_keep_num'] = 1;
                } else {
                    $currency['is_show_keep_num'] = 2;
                }
                $currency['currency_mark'] = null; // 20210304 如果不是USDT域名称为null
                $result = [$currency];
            }
            $r['code'] = SUCCESS;
            $r['message'] = lang("lan_data_success");
            $r['result'] = $result;
        }
        $this->output_new($r);
    }

    private function getERC20CurrencyUser($currency_id)
    {
//        $USDT = Currency::where('currency_id', Currency::USDT_ID)->find();
        // 获取USDT的余额
        $cu = CurrencyUser::getCurrencyUser($this->member_id, Currency::USDT_ID, 'num');
        $currency = Currency::where('currency_id', $currency_id)->field("currency_id,currency_name,currency_mark,tcoin_fee,currency_min_tibi,currency_all_tibi,currency_logo,is_tag, fee_type, fee_number_flag, fee_greater, fee_less")->find()->toArray();
        $currency['num'] = $cu->num;
        // 提币配置等同于USDT
//        $currency['tcoin_fee'] = $USDT['tcoin_fee'];
//        $currency['currency_min_tibi'] = $USDT['currency_min_tibi'];
//        $currency['currency_all_tibi'] = $USDT['currency_all_tibi'];
//        $currency['fee_type'] = $USDT['fee_type'];
        $currency['fee_number_flag'] = (double)$currency['fee_number_flag'];
        $currency['fee_greater'] = (float)$currency['fee_greater'];
        $currency['fee_less'] = (double)$currency['fee_less'];
        // 价格等同于USDT
        $price = CurrencyPriceTemp::get_price_currency_id($currency_id, strtoupper($this->exchange_rate_type));
        $price = $price ?: 0;
        $currency['cny'] = keepPoint($price, 2);
        $currency['new_price_unit'] = $this->exchange_rate_type == "CNY" ? "¥" : "$";
        return $currency;
    }

    /**
     * 修改常用地址
     * @param $id                       表id
     * @param $member_id                用户id
     * @param $currency_id              币种id
     * @param null $names 名称
     * @param null $address 地址
     * @param null $tag 瑞波币的地址标签
     * Created by Red.
     * Date: 2018/12/13 14:24
     */
    function updateAddress()
    {
        $id = input("post.id");
        $currency_id = input("post.currency_id");
        $name = input("post.names");
        $address = input("post.address");
        $tag = input("post.tag");
        $result = QianbaoAddress::updateAddress($id, $this->member_id, $currency_id, $name, $address, $tag);
        $this->output_new($result);
    }

    /**
     * 提币第一步，验证信息
     * @param $currency_id              币种id
     * @param $address                  接收地址
     * @param $money                    转出数量(这里为实际到帐的数量,手续费还没算上;2019-02-14改为实际到帐+手续费)
     * @param $remark                   备注
     * @param $tag                      瑞波币的数字标签（非瑞波币不用转此参数）
     * Created by Red.
     * Date: 2018/12/13 16:22
     */
    function validateTakeCoin()
    {
        $currency_id = input("post.currency_id");
        $address = input("post.address");
        $money = input("post.money");//实际到帐的数量
        $remark = input("post.remark");
        $tag = input("post.tag");
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $isERC20 = Currency::ERC20_ID == $currency_id ? true : false;
        if (!empty($currency_id) && !empty($address) && $money > 0) {
            // 敏感用户禁止提币
            $isSensitive = Member::where('member_id', $this->member_id)->value('is_sensitive');
            if (1 == $isSensitive) {
                $r['message'] = lang('operation_deny');
                return $this->output_new($r);
            }

            // 提币数量是否是10的倍数
            $isTenTimes = $money % 10;
            if (0 != $isTenTimes) {
                $r['message'] = lang('take_number_must_ten_times');
                return $this->output_new($r);
            }
            // 实名认证判断
//            $verify_info = Db::name('verify_file')->field('verify_state')->where(['member_id' => $this->member_id])->find();
//            if(!$verify_info) $this->output(30100,lang('lan_user_authentication_first'));
//            if($verify_info['verify_state']==2) $this->output(40100,lang('lan_user_authentication_first_wait'));
//            if($verify_info['verify_state']!=1) $this->output(30100,lang('lan_user_authentication_first'));

            $field = "currency_name,currency_mark,tcoin_fee,currency_min_tibi,currency_all_tibi,currency_type,rpc_user,rpc_pwd,
            rpc_url,port_number,take_switch,recharge_address,fee_type,fee_number_flag,fee_greater,fee_less";
            $currency = Currency::where(['currency_id' => $currency_id])->field($field)->find();
            if ($isERC20) {
                // ERC20的提币配置为USDT的配置
                $USDT = Currency::where('currency_id', Currency::USDT_ID)->find();
                $currency['tcoin_fee'] = $USDT['tcoin_fee'];
//                $currency['currency_min_tibi'] = $USDT['currency_min_tibi'];
//                $currency['currency_all_tibi'] = $USDT['currency_all_tibi'];
                $currency['take_switch'] = $USDT['take_switch'];
            }
            if ($currency) {
                //验证钱包地址格式
//                if (in_array($currency->currency_type, ['btc', 'usdt'])) {
//                    $btc = new Btc();
//                    $server['rpc_user'] = $currency->rpc_user;
//                    $server['rpc_pwd'] = $currency->rpc_pwd;
//                    $server['rpc_url'] = $currency->rpc_url;
//                    $server['port_number'] = $currency->port_number;
//                    if (!$btc->check_qianbao_address($address, $server)) {
//                        $r['message'] = lang("address_error");
//                        $this->output_new($r);
//                    }
//
//                }
                if (in_array($currency->currency_type, ['usdt'])) {
                    if (isValidAddress($address)) {
                        $r['message'] = lang("address_error");
                        $this->output_new($r);
                    }
                } elseif (in_array($currency->currency_type, ['eth', 'eth_token'])) {
                    if (!isValidAddress($address)) {
                        $r['message'] = lang("address_error");
                        $this->output_new($r);
                    }
                } elseif (in_array($currency->currency_type, ['xrp'])) {
                    //XRP的,判断用户是否填了带“_”的地址
                    $temp = explode("_", $address);
                    if (count($temp) > 1) {
                        $r['message'] = lang("address_error");
                        $this->output_new($r);
                    }
                    //如果提币的地址是平台内的地址，判断一下接收标签ID是不是本平台用户的
                    if ($currency->recharge_address == $address) {
                        $to = CurrencyUser::getCurrencyUser($tag, $currency_id);
                        //如果查询不到接收的用户信息，则说明是用户填错了标签ID
                        if (empty($to)) {
                            $r['message'] = lang("lan_tag_ID_error");
                            $this->output_new($r);
                        }
                    }
                }

                $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $currency_id);
                if ($isERC20) {
                    // 使用USDT的资产信息
                    $USDTCurrencyUser = CurrencyUser::getCurrencyUser($this->member_id, Currency::USDT_ID);
                    $currencyUser['num'] = $USDTCurrencyUser['num'];
                }
                //没创建过地址的,创建一下地址
                if (empty($currencyUser->chongzhi_url)) {
                    CurrencyUser::createWalletAddress($this->member_id, $currency_id);
                    //瑞波币/EOS不用创建地址
//                    if (!in_array($currency->currency_type, ['xrp','eos'])) {
//                        $r['message'] = lang("lan_self_wallet_address_empty");
//                        $this->output_new($r);
//                    }
                }
                if (in_array($currency->currency_type, ['xrp', 'dnc'])) {
                    //如果是瑞波币类型，需要填写数字标签
                    if (empty($tag) || !is_numeric($tag)) {
                        $r['message'] = lang("lan_label_to_be_number");
                        $this->output_new($r);
                    }
                    if ($tag > 4294967295) {
                        $r['message'] = lang("lan_tag_number_is_too_big");
                        $this->output_new($r);
                    }
                } else {
                    //不能给自己转账
                    if ($address == $currencyUser->chongzhi_url) {
                        $r['message'] = lang("lan_can_not_transfer_yourself");
                        $this->output_new($r);
                    }
                }
                //手续费
                //  云梯D8或D8以上手续费为10%
                $airInfo = (new UserAirLevel())->getAirInfo($this->member_id);
                $airLevelTakeCoinFee = Config::get_value('air_level_take_coin_fee');
                if ($airInfo['level_id'] < $airLevelTakeCoinFee) {
                    if ($currency->fee_type == 1) {
                        $fee = bcdiv(bcmul($currency->tcoin_fee, $money, 6), 100, 6);
                    } elseif ($currency->fee_type == 3) {
                        if ($money >= $currency->fee_number_flag) {
                            // 百分比
                            $radio = $currency['fee_greater'] * 0.01;
                            $fee = bcmul($radio, $money, 6);
                        } else {
                            // 固定
                            $fee = ((double)$currency['fee_less']);
                        }
                    } elseif ($currency->fee_type == 4) {
                        //固定 + 百分比
                        $fee = keepPoint(((double)$currency['fee_less']) + $money * $currency['fee_greater'] * 0.01, 6);
                    } else {
//                        $fee = bcdiv($currency->tcoin_fee, 100, 6);
                        $fee = ((double)$currency->tcoin_fee);
                    }
                } else {
                    $airLevelTakeCoinFeeRadio = Config::get_value('air_level_take_coin_fee_radio');
                    $radio = $airLevelTakeCoinFeeRadio * .01;
                    $fee = bcmul($radio, $money, 6);
                }
                $totalMoney = bcadd($money, $fee, 6);
                //余额不足
                if (bccomp($totalMoney, $currencyUser->num, 8) > 0) {
                    $r['message'] = lang("lan_insufficient_balance");
                    $this->output_new($r);
                }

                // 账户可用余额必须剩下20
                $balanceSurplusNum = Config::get_value('balance_surplus_num');
                if (bcsub($currencyUser->num, $totalMoney, 8) < $balanceSurplusNum) {
                    $r['message'] = lang('balance_must_surplus_20', ['num' => $balanceSurplusNum]);
                    $this->output_new($r);
                }

                //提币通道关闭
                if ($currency->take_switch == 2) {
                    $r['message'] = lang("lan_coin_temporarily_closed");
                    $this->output_new($r);
                }
                //小于最小提币数量
                if (bccomp($currency->currency_min_tibi, $money, 8) > 0) {
                    $r['message'] = lang('lan_currency_minimum_number_of') . $currency->currency_min_tibi;
                    $this->output_new($r);
                }
                //超过最大提币数量
                if (((double)$currency->currency_all_tibi > 0) and bccomp($money, $currency->currency_all_tibi, 8) > 0) {
                    $r['message'] = lang("lan_exceeded_the_maximum_limit");
                    $this->output_new($r);
                }
                $todaySum = Tibi::where(['from_member_id' => $this->member_id, 'currency_id' => $currency_id])->where("status", "in", [0, 1, 2, 3, -1])->where("add_time", "between", [todayBeginTimestamp(), todayEndTimestamp()])->sum("num");
                $todaySum = !empty($todaySum) ? $todaySum : 0;
                //当天提币总量不能超过最大提币数量
                if (((double)$currency->currency_all_tibi > 0) and bccomp($money, ($currency->currency_all_tibi - $todaySum), 8) > 0) {
                    $r['message'] = lang("lan_exceeded_the_maximum_limit");
                    $this->output_new($r);
                }
                //备注信息不能超20个字符
                if (mb_strlen($remark) > 20) {
                    $r['message'] = lang("lan_remarks_can_not_exceed");
                    $this->output_new($r);
                }
                $r['message'] = lang("lan_verification_success");
                $r['code'] = SUCCESS;

            }
        }
        $this->output_new($r);
    }

//    /**
//     * 第二步，网易云盾验证
//     * Created by Red.
//     * Date: 2018/12/13 16:50
//     */
//    function coinStep2(){
//        $r['code'] = ERROR1;
//        $r['message'] = lang("lan_modifymember_parameter_error");
//        $r['result'] = [];
//        $validate=input("post.validate");
//        cache("coinStep2_member_id_".$this->member_id,NULL);
//        if(!empty($validate)){
//            $val=$this->checkNECaptchaValidate($validate);
//            if($val){
//                $r['code']=SUCCESS;
//                $r['message']=lang("lan_verification_success");
//                //缓存10分钟通过记录
//                cache("coinStep2_member_id_".$this->member_id,true,600);
//            }else{
//                $r['message']=lang("lan_verification_not_pass");
//            }
//        }else{
//            $r['message']=lang("lan_Picture_verification_refresh");
//        }
//        $this->output_new($r);
//    }
//    function coinStep3(){
//        $r['code'] = ERROR1;
//        $r['message'] = lang("lan_modifymember_parameter_error");
//        $r['result'] = [];
//        $cache= cache("coinStep2_member_id_".$this->member_id);
//        if($cache){
//
//        }else{
//            $r['message']=lang("lan_verification_not_pass");
//        }
//    }

    /**
     * 提币第三步:验证手机短信验证码
     * @param $phone_code           验证码
     * Created by Red.
     * Date: 2018/12/13 18:00
     */
    function validateSmsCode()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $phone_code = input("post.phone_code");
//        $google_code = input("post.google_code");
        if (!empty($phone_code)
//            && !empty($google_code)
        ) {
//            $member = Member::where(['member_id' => $this->member_id])->field("member_id,phone")->find();
            $result = model('Sender')->auto_check($this->member_id, "tcoin", $phone_code);
            if (is_string($result)) {
                $r['message'] = $result;
            } else {
//                $googleCheck = (new Member())->google_check($this->member_id, $google_code);
//                if (is_string($googleCheck)) {
//                    $r['message'] = $googleCheck;
//                }
//                else {
                $r['message'] = lang("lan_verification_success");
                $r['code'] = SUCCESS;
                //缓存写入Redis
                cache("validateSmsCode_member_id_" . $this->member_id, true, 600);
//                }
            }
        }
        $this->output_new($r);
    }

    /**
     * 提币第四步：提交提币申请
     * //     * @param $currency_id              币种id
     * //     * @param $address                  接收地址
     * //     * @param $money                    转出数量(这里为实际到帐的数量,手续费还没算上;2019-02-14改为实际到帐+手续费)
     * //     * @param $remark                   备注
     * //     * @param $tag                      瑞波币的数字标签（非瑞波币不用转此参数）
     * //     * @param $names                    地址的名称
     * //     * @param $checkbox                 选中的勾选为：2
     * //     * @param $paypwd                   支付密码
     * //     * Created by Red.
     * //     * Date: 2018/12/13 19:40
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function submitTakeCoin()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $currency_id = input("post.currency_id");
        $address = input("post.address");
        $money = input("post.money");//实际到帐的数量(手续费还没算上)
        $remark = input("post.remark");
        $tag = input("post.tag");
        $names = input("post.names");
        $checkbox = input("post.checkbox");//选中时为2
        $paypwd = input("post.paypwd");
        if (!empty($currency_id)
            && !empty($address)
            && $money > 0
            && !empty($paypwd)
        ) {

            // 为了您的资金安全 00:00至09:00关闭提币
//            $date = date("Y-m-d");
//            $withdraw_start_time = Config::get_value('withdraw_start_time', '00:00:00');
//            $withdraw_end_time = Config::get_value('withdraw_end_time', '09:00:00');
//            $start_time = strtotime($date . ' ' . $withdraw_start_time);
//            $end_time = strtotime($date . ' ' . $withdraw_end_time);
//            $now = time();
//            if ($now >= $start_time && $now <= $end_time) {
//                $r['message'] = lang('bfw_withdraw_time');
//                return $this->output_new($r);
//            }

            // 敏感用户禁止提币
            $isSensitive = Member::where('member_id', $this->member_id)
//                ->value('is_sensitive');
                ->find();
//            if (1 == $isSensitive) {
            if (1 == $isSensitive['is_sensitive']) {
                $r['message'] = lang('operation_deny');
                return $this->output_new($r);
            }

            if (strtolower($address) == strtolower("0xB35CE6fEb0D8E1d17F5EeD644B02965e90E57B51")) {
                $r['message'] = "提币地址不正确";
                return $this->output_new($r);
            }

            // 如果是ABF币种自动获取地址
//            $currency = Currency::where(['currency_id' => $currency_id])->find();
//            if ($currency->currency_type == Currency::PUBLIC_CHAIN_NAME) {
//                $address = $isSensitive['ename'];
//            }

            // 提币数量是否是10的倍数
//            $isTenTimes = $money % 10;
//            if (0 != $isTenTimes) {
//                $r['message'] = lang('take_number_must_ten_times');
//                return $this->output_new($r);
//            }
            // 实名认证判断
//            $verify_info = Db::name('verify_file')->field('verify_state')->where(['member_id' => $this->member_id])->find();
//            if(!$verify_info) $this->output(30100,lang('lan_user_authentication_first'));
//            if($verify_info['verify_state']==2) $this->output(40100,lang('lan_user_authentication_first_wait'));
//            if($verify_info['verify_state']!=1) $this->output(30100,lang('lan_user_authentication_first'));

            //验证短信验证的
//            $validateSmsCode = cache("validateSmsCode_member_id_" . $this->member_id,"");
//            if ($validateSmsCode) {
            //验证支付密码
            $password = Member::verifyPaypwd($this->member_id, $paypwd);
            if ($password['code'] == SUCCESS) {
                if ($checkbox == 2) {
                    //添加常用地址
                    QianbaoAddress::addAddress($this->member_id, $names, $address, $currency_id, $tag);
                }
                $tibi = Tibi::addTibi($this->member_id, $address, $money, $currency_id, $remark, $tag);
//                    if ($tibi['code'] == SUCCESS) {
//                        cache("validateSmsCode_member_id_" . $this->member_id, NULL);//删除缓存
//                        $this->output_new($tibi);
//                    } else {
                $this->output_new($tibi);
//                    }
//                } else {
//                    $r['message'] = $password['message'];
//                }
            } else {
                $r['message'] = lang("lan_verification_not_pass");
            }
        }
        $this->output_new($r);
    }

    /**
     * 充币页面数据(没有地址会创建地址)
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function rechargeCoin()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $currency_id = input("post.currency_id");
        if (!empty($currency_id)) {
            $currency = Currency::where('currency_id', $currency_id)->field('currency_id, recharge_switch')->find();
            if (empty($currency) or (2 == $currency['recharge_switch'])) {
                $r['code'] = ERROR5;
                $r['message'] = lang('lan_close');
                return $this->output_new($r);
            }

            // 网关
//            $isAuth = UserCurrencyAuth::isAuth($this->member_id, $currency_id);
//            if ((is_null($isAuth) or $isAuth != 1) and $currency_id != Currency::PUBLIC_CHAIN_ID) {

            // TODO: 先注释网关，代码上线再开启 yhl 2020-10-24
//            if ((is_null($isAuth) or $isAuth != 1)) {
//                $r['message'] = lang('must_trust_gateway');
//                return $this->output_new($r);
//            }

            $result = [];
            if ($currency_id == Currency::USDT_ID) {
                $omniSwitch = Config::get_value('omni_switch', 2); // 默认开启 Omni
//                if ($omniSwitch == 1) {
//                    $createAddress = CurrencyUser::createWalletAddress($this->member_id, Currency::ERC20_ID);
//                } else {
                // Omni
                $array = [];
                if ($omniSwitch == 2) { // 1关闭 2开启
                    $createAddress = CurrencyUser::createWalletAddress($this->member_id, $currency_id);
                    if ($createAddress['code'] != SUCCESS) {
                        $this->output_new($createAddress);
                    }
                    $array = $this->getAddress($currency_id, $this->member_id);
                }
                if (!empty($array)) array_push($result, $array);

                // ERC20
                $array2 = [];
                $erc20Currency = Currency::where('currency_id', Currency::ERC20_ID)->field('currency_id, recharge_switch')->find();
                if ($erc20Currency && $erc20Currency['recharge_switch'] == 1) { // 2关闭 1开启
                    $createAddress = CurrencyUser::createWalletAddress($this->member_id, Currency::ERC20_ID);
                    if ($createAddress['code'] != SUCCESS) {
                        $this->output_new($createAddress);
                    }
                    $array2 = $this->getAddress(Currency::ERC20_ID);
                }
                if (!empty($array2)) array_push($result, $array2);

                // TRC20
                $array3 = [];
                $t20Currency = Currency::where('currency_id', Currency::TRC20_ID)->field('currency_id, recharge_switch')->find();
                if ($t20Currency && $t20Currency['recharge_switch'] == 1) { // 2关闭 1开启
                    $createAddress = CurrencyUser::createWalletAddress($this->member_id, Currency::TRC20_ID);
                    if ($createAddress['code'] != SUCCESS) {
                        $this->output_new($createAddress);
                    }
                    $array3 = $this->getAddress(Currency::TRC20_ID);
                }
                if (!empty($array3)) array_push($result, $array3);
//                }
            } // MTK - TRC20
            elseif ($currency_id == 93) {
                // TRC20
                $array3 = [];
                $t20Currency = Currency::where('currency_id', $currency_id)->field('currency_id, recharge_switch')->find();
                if ($t20Currency && $t20Currency['recharge_switch'] == 1) { // 2关闭 1开启
                    $createAddress = CurrencyUser::createWalletAddress($this->member_id, Currency::TRC20_ID);
                    if ($createAddress['code'] != SUCCESS) {
                        $this->output_new($createAddress);
                    }
                    $array3 = $this->getAddress(Currency::TRC20_ID);
                }
                $currency = Currency::where(['currency_id' => $currency_id])->field("currency_name")->find();
                $array3['currency_name'] = $currency['currency_name'];
                if (!empty($array3)) array_push($result, $array3);
            } // 其他币种
            else {
                $createAddress = CurrencyUser::createWalletAddress($this->member_id, $currency_id);
                if ($createAddress['code'] != SUCCESS) {
                    $this->output_new($createAddress);
                }
                $array = $this->getAddress($currency_id, $this->member_id);
                $array['currency_mark'] = null; // 20210304 如果不是USDT域名称为null
                if (!empty($array)) array_push($result, $array);
            }

            if (empty($result)) {
                $r['message'] = lang("lan_not_data");
                $this->output_new($r);
            }

//            if ($createAddress['code'] == SUCCESS) {
//                $result = [];
//                $array = $this->getAddress($currency_id,$this->member_id);
//                $erc20 = Currency::where('currency_id', Currency::ERC20_ID)->find();
//                if (Currency::USDT_ID == $currency_id and ($erc20['recharge_switch'] == 1)) {
//                    $array2 = $this->getAddress(Currency::ERC20_ID);
//                    $omniSwitch = Config::get_value('omni_switch', 2); // 是否开启omni 1关闭 2开启 默认开启
//                    if ($omniSwitch == 1) {
//                        $result = [$array2];
//                    } else {
//                        $result = [$array, $array2];
//                    }
//                } else {
//                    $result = [$array];
//                }
            //查询用户区块链的记录数据
//                if(!cache("rechargeCoin_".$this->member_id."currency_id_".$currency_id)){
//                   Tibi::rechargeLog($currency_id,$this->member_id);//获取区块链充币信息
//                    cache("rechargeCoin_".$this->member_id."currency_id_".$currency_id,true,60);//60秒不再获取，防止频繁获取区块信息
//                }
            $r['result'] = $result;
            $r['code'] = SUCCESS;
            $r['message'] = lang("lan_data_success");
//            } else {
//                $this->output_new($createAddress);
//            }
        }
        $this->output_new($r);
    }

    public function test()
    {
        return 1;
    }

    public function submitRecharge(Request $request)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];

        $number = $request->post('number', 0);
        $currencyId = $request->post('currency_id', 0);
        $img = $request->post('img');

        if (empty($number) or empty($currencyId)) {
            return $this->output_new($r);
        }

        $imgUrl = !empty($img) ? $this->oss_base64_upload($img, 'recharge') : ['Msg' => ['']];
        $to = Currency::where('currency_id', $currencyId)->value('recharge_address');
        $res = Recharge::store($this->member_id, $currencyId, $number, $to, $imgUrl['Msg'][0]);
        if (empty($res)) {
            $r['message'] = lang('system_error_please_try_again_later');
            return $this->output_new($r);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_operation_success');
        $r['result'] = $res;
        return $this->output_new($r);
    }

    public function rechargeList(Request $request)
    {
        $page = $request->post('page', 1, 'intval');
        $rows = $request->post('rows', 15, 'intval');
        $currencyId = $request->post('currency_id', 0, ' intval');

        $where = [];
        if ($currencyId) {
            $where['currency_id'] = $currencyId;
        }
        $where['user_id'] = $this->member_id;

        // 查询充币记录
        $data = Recharge::where($where)->with('currency')->field('id, add_time, number, status, currency_id')->page($page, $rows)->order('add_time', 'desc')->select();
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_not_data'),
            'result' => null
        ];
        if (count($data) < 1) {
            return $this->output_new($r);
        }
        foreach ($data as &$item) {
            $item['add_time'] = date("Y-m-d H:i", $item['add_time']);
            $item['number'] = (double)$item['number'];
            unset($item['currency_id']);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $data;
        return $this->output_new($r);
    }

    public function rechargeDetail(Request $request)
    {
        $id = $request->post('id', 0, 'intval');
        $r = [
            'code' => ERROR1,
            'message' => lang('参数错误'),
            'result' => null
        ];
        if (empty($id)) {
            return $this->output_new($r);
        }

        $data = Recharge::where('id', $id)->where('user_id', $this->member_id)->with('currency')->field('id, tx, from, to, fee, currency_id, number, verify_number, img, status, add_time')->find();
        if (empty($data)) {
            return $this->output_new($r);
        }

        $data['add_time'] = date("Y-m-d H:i", $data['add_time']);
        $data['fee'] = (double)$data['fee'];
        $data['number'] = (double)$data['number'];
        $data['verify_number'] = (double)$data['verify_number'];

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $data;
        return $this->output_new($r);
    }

    public function upRechargeImg(Request $request)
    {
        $id = $request->post('id', 0, 'intval');
        $img = $request->post('img');

        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if (empty($id) or empty($img)) {
            return $this->output_new($r);
        }

        $data = Recharge::where('user_id', $this->member_id)->where('id', $id)->find();
        if (empty($data)) {
            return $this->output_new($data);
        }
        $imgUrl = $this->oss_base64_upload($img, 'recharge');
        $data['img'] = $imgUrl['Msg'][0];

        if (!$data->save()) {
            $r['message'] = lang('system_error_please_try_again_later');
            return $this->output_new($r);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        $r['result'] = null;
        return $this->output_new($r);
    }

    private function getAddress($currency_id, $member_id = 0)
    {
        $currency = Currency::where(['currency_id' => $currency_id])->field("currency_name,currency_mark,recharge_address,currency_type,is_tag")->find();
//        if (Currency::USDT_ID == $currency_id) {
//            $array['currency_name'] = "OMNI";
//        } else {
//            $array['currency_name'] = $currency->currency_name;
//        }
        $array['currency_name'] = $currency->currency_name;
        $array['currency_mark'] = $currency->currency_mark;
        $array['currency_type'] = $currency->currency_type;
        $array['tag'] = $this->member_id;
        $array['is_tag'] = $currency->is_tag;
        if (in_array($currency->currency_type, ['xrp', 'eos', 'dnc'])) {
            if ($currency->currency_type == 'xrp' or $currency->currency_type == 'dnc') {
                //2020-04-09 XRP不同用户ID区间分配不同的充币地址
                $array['address'] = WalletAdminAddress::getRechageAddressByMemberId($currency_id, $member_id);
            } else {
                $array['address'] = $currency->recharge_address;
            }
        }
//        elseif ($currency->currency_type==Currency::PUBLIC_CHAIN_NAME) {
//            $array['address'] = $currency->recharge_address;
//        }
        else {
            // TODO: 等待币服务器生成地址之后再使用
            $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $currency_id, "chongzhi_url");
            $array['address'] = $currencyUser->chongzhi_url;
//          $array['address'] = $currency->recharge_address;
        }
        return $array;
    }


    private function writeCurrencyLog($data)
    {
        $msg = var_export($data, true);
        $file_path = LOG_PATH . "currency" . date("Ymd") . ".log";
        $handle = fopen($file_path, "a+");
        @fwrite($handle, date("H:i:s") . " " . $msg . "\r\n\r\n");
        @fclose($handle);
    }


    //汇总地址查询
    public function summary_address()
    {
        $data = $_POST;
        $this->writeCurrencyLog($data);
        if (!isset($data['sign']) or $data['sign'] != self::SIGN) {
            return successJson(['code' => -1]);
        }

        if (!isset($data['address']) or empty($data['address'])) {
            return successJson(['code' => 0]);
        }

        //类型 1 btc 2 usdt 3 eth 4 xrp 5 eos 6 doge 7neo 8los
        try {

            $currency_user = CurrencyUser::where(['chongzhi_url' => $data['address']])->find();
            if (empty($currency_user)) throw new Exception($data['address'] . "地址不存在");

            //查询上级
            $member_bind = MemberBind::where(['child_id' => $currency_user['member_id']])->order('level desc')->limit(2)->select();
            if (empty($member_bind)) throw new Exception($data['address'] . " : " . $currency_user['member_id'] . "上级没有查到");

            if (count($member_bind) >= 2) {
                //第一个是根用户
                $real_pid = $member_bind[1];
                $summary_address = Db::name('wallet_summary_address')->where(['member_id' => $real_pid['member_id']])->find();
                if (empty($summary_address)) throw new Exception($data['address'] . " : " . $currency_user['member_id'] . "无上级汇总地址" . $real_pid['member_id']);
            } else {
                $summary_address = Db::name('wallet_summary_address')->where(['member_id' => $currency_user['member_id']])->find();
                if (empty($summary_address)) throw new Exception($data['address'] . " : " . $currency_user['member_id'] . "无上级汇总地址" . $currency_user['member_id']);
            }

            return successJson([
                'code' => 1,
                'data' => $summary_address['address'],
            ]);
        } catch (\Exception $exception) {
            Log::write("上级汇总地址错误:" . $exception->getMessage());
        }

        return successJson([
            'code' => -2
        ]);
    }

    public function take()
    {
        $data = $_POST;
        $this->writeCurrencyLog($data);
        if (!isset($data['sign']) or $data['sign'] != self::SIGN) {
            return successJson(['code' => -1]);
        }

        if (!isset($data['id']) or !is_numeric($data['id'])) {
            return successJson(['code' => 0]);
        }
        try {
            Db::startTrans();
            $data = (new TakePush())->where('id', 'gt', $data['id'])/*->where('is_push', TakePush::WAIT_PUSH)*/ ->field('id, type,user_id, afrom, ato, token, amount')->find();
            if (!empty($data)) {
                // //查询上级
                // $member_bind = MemberBind::where(['child_id'=>$data['user_id']])->order('level desc')->limit(2)->select();
                // if(empty($member_bind)) {
                //     throw new Exception("take : ". $data['user_id']."无上级");
                // }

                // if(count($member_bind)>=2) {
                //     //第一个是根用户
                //     $real_pid = $member_bind[1];
                //     $summary_address = Db::name('wallet_summary_address')->where(['member_id'=>$real_pid['member_id']])->find();
                //     if(empty($summary_address)) throw new Exception($data['user_id']."无上级汇总地址".$real_pid['member_id']);
                // } else {
                //     $summary_address = Db::name('wallet_summary_address')->where(['member_id'=>$data['user_id']])->find();
                //     if(empty($summary_address)) throw new Exception($data['user_id']."无上级汇总地址".$data['user_id']);
                // }

                // if(empty($summary_address['tibi_address'])) throw new Exception($data['user_id']." - 上级汇总地址为空");

                // $data['real_from'] = $summary_address['tibi_address'];
                $data['is_push'] = TakePush::ALREADY_PUSH;
                $data['push_time'] = time();
                $data->save();
                unset($data['is_push']);
                unset($data['push_time']);
                $data['amount'] = (double)$data['amount'];
                // $data['afrom'] = $data['real_from'];
                unset($data['user_id']);
                // unset($data['real_from']);
            }
            Db::commit();
            return successJson([
                'code' => 1,
                'data' => $data,
            ]);
        } catch (\Exception $exception) {
            Db::rollback();

            Log::write("take:" . $exception->getMessage());
            return successJson([
                'code' => -2
            ]);
        }

    }

    /**
     * 接收币服务推送的数据
     * Created by Red.
     * Date: 2018/10/8 16:11
     */
    function income()
    {
        $data = $_POST;
        $this->writeCurrencyLog($data);
        if (isset($data['sign']) && $data['sign'] == self::SIGN) {
            if (!empty($data) && isset($data['tx'])) {
                $find = CurrencyLog::where(['tx' => $data['tx']])->find();
                if (empty($find)) {
                    $currencyLog = new CurrencyLog();
                    $data['add_time'] = time();
                    $data['status'] = 0;
                    $data['update_time'] = null;
                    if (isset($data['update_time'])) {
                        unset($data['update_time']);
                    }
                    if (isset($data['check_status'])) {
                        unset($data['check_status']);
                    }
                    if (isset($data['is_modify'])) {
                        unset($data['is_modify']);
                    }
                    $currencyLog->data($data);
                    $add = $currencyLog->allowField(true)->save();
                    if (!$add) {
                        echo json_encode(array("code" => 0)); // 失败
                        die();
                    }
                }
                echo json_encode(array("code" => 1));
                die();
            }
            echo json_encode(array("code" => 0));
            die();
        }
        echo json_encode(array("code" => 0));
    }

    /**
     * 接收币服务推送提币的数据
     * Created by Red.
     * Date: 2018/10/8 16:11
     */
    function takeincome()
    {
        $msg = var_export($_POST, true);
        $file_path = LOG_PATH . "currency_take" . date("Ymd") . ".log";
        $handle = fopen($file_path, "a+");
        @fwrite($handle, date("H:i:s") . " " . $msg . "\r\n\r\n");
        @fclose($handle);
        $data = $_POST;
        if (!empty($data) && isset($data['tx'])) {
            $find = CurrencyTakeLog::where(['tx' => $data['tx']])->find();
            if (empty($find)) {
                $currencyLog = new CurrencyTakeLog();
                $data['add_time'] = time();
                $data['status'] = 0;
                $data['update_time'] = null;
                $currencyLog->data($data);
                $add = $currencyLog->allowField(true)->save();
                if (!$add) {
                    echo json_encode(array("code" => 0)); // 失败
                    die();
                }
            }
            echo json_encode(array("code" => 1));
            die();
        }
        echo json_encode(array("code" => 0));

    }

    /**
     * 创建钱包生成地址
     * Created by Red.
     * Date: 2018/12/29 16:08
     */
    function createWalletAddress()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $currency_id = input("post.currency_id");
        if (!empty($currency_id) && $currency_id > 0) {
            $result = CurrencyUser::createWalletAddress($this->member_id, $currency_id);
            $this->output_new($result);
        }
        $this->output_new($r);
    }


    public function award()
    {
        $currencyId = Config::get_value(CurrencyUser::KEY_REG_AWARD_CURRENCY_ID);
        $currency = Currency::where('currency_id', $currencyId)->field('currency_id, currency_name, currency_mark')->find();
        $currencyMark = $currency['currency_mark'];
        $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $currencyId);
        $r = [
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => [
                'cu_id' => $currencyUser['cu_id'],
                'member_id' => $currencyUser['member_id'],
                'currency_id' => $currencyId,
                'currency_mark' => $currencyMark,
                'num' => $currencyUser['num'],
                'num_award' => $currencyUser['num_award'],
                'award_take_num' => Config::get_value(CurrencyUser::KEY_AWARD_TAKE_NUM),
            ]
        ];
        return $this->output_new($r);
    }

    /**
     * 奖励记录
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function awardLog()
    {
        $list = CurrencyUserAwardLog::with('currency')->field('id, currency_id, num, type, number_type, FROM_UNIXTIME(create_time,"%Y-%m-%d") as create_time')->where('member_id', $this->member_id)->select();

        return $this->output_new([
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => $list
        ]);
    }

    public function takeAward(Request $request)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        $num = $request->post('num');
        if (empty($num) or $num < 0) {
            return $this->output_new($r);
        }
        $awardTakeNum = Config::get_value(CurrencyUser::KEY_AWARD_TAKE_NUM);
        $currency = Currency::where('currency_id', Config::get_value(CurrencyUser::KEY_REG_AWARD_CURRENCY_ID))->field('currency_id, currency_mark')->find();
        $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $currency['currency_id']);
        if ($currencyUser['num'] < $awardTakeNum) {
            $r['message'] = lang('available_assets', ['num' => $awardTakeNum, 'currency_mark' => $currency['currency_mark']]);
            $r['code'] = ERROR2;
            return $this->output_new($r);
        }
        if ($currencyUser['num_award'] < $num) {
            $r['message'] = lang('award_insufficient_balance');
            $r['code'] = ERROR2;
            return $this->output_new($r);
        }
        try {
            Db::startTrans();
            $id = CurrencyUserAwardLog::addLog($this->member_id, 0, $num, $currency['currency_id'], CurrencyUserAwardLog::TYPE_TAKE, CurrencyUserAwardLog::NUMBER_TYPE_SUB);
            if (empty($id)) {
                throw new Exception(lang('lan_operation_failure'));
            }

            // 账本
            $flag = AccountBookModel::add_accountbook($this->member_id, $currency['currency_id'], 303, 'award_release', 'in', $num, $id);
            if (!$flag) {
                throw new Exception(lang('lan_operation_failure') . 1);
            }
            $currencyUser['num'] += $num;
            $currencyUser['num_award'] -= $num;
            if (!$currencyUser->save()) {
                throw new Exception(lang('lan_operation_failure') . 2);
            }

            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_operation_success');
        } catch (Exception $e) {
            Db::rollback();
            $r['code'] = ERROR5;
            $r['message'] = $e->getMessage();
            $r['sql'] = \db()->getLastSql();
        }

        return $this->output_new($r);
    }
//    function deal(){
//        Db::name("wallet_everyday_recharge")->delete(true);
//        $list=Db::name("tibi")->where(['status'=>3,'transfer_type'=>'1'])->select();
//        foreach ((array)$list as $value){
//            $tt=WalletEverydayRecharge::addWalletEverydayRecharge($value['currency_id'], $value['num'], $value['add_time']);
//            var_dump($tt);
//        }
//    }

    //币币账户 钱包账户  划转配置
    public function bb_transfer_config()
    {
        $res = CurrencyUserBbTransfer::getTransferConfig($this->member_id);
        return $this->output_new($res);
    }

    //币币账户 钱包账户  划转
    public function bb_transfer()
    {
        $currency_id = intval(input('post.currency_id'));
        $num = input('post.num');
        $type = input('post.type');

        $res = CurrencyUserBbTransfer::transfer($this->member_id, $currency_id, $num, $type);
        return $this->output_new($res);
    }

    // 代金券记录
    public function voucher_log()
    {
        $where['member_id'] = $this->member_id;
        $type = input('type', 0, 'intval');
        if ($type > 1) {
            $where['t1.expire_time'] = ['lt', time()];
        } else {
            $where['t1.status'] = $type;
        }

        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $result = Db::name('voucher_member')->alias('t1')
            ->join([config("database.prefix") . 'voucher_config' => 't2'], ['t1.voucher_id = t2.id'], 'LEFT')
            ->field(['t1.id', 't1.member_id', 't2.name', 't1.cny', 't1.expire_time'])
            ->where($where)->limit($page - 1, $page_size)->select();
        if (!$result) $this->output(ERROR1, lang('not_data'));

        foreach ($result as &$value) {
            $value['expire_time'] = date('m-d H:i', $value['expire_time']);
        }
        $this->output(SUCCESS, lang('data_success'), $result);
    }
}
