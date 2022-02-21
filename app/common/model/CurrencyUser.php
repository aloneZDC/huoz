<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;

use message\Btc;
use message\Eth;
use PDOStatement;
use think\Exception;
use think\Log;
use think\Model;
use think\Db;

class CurrencyUser extends Base
{
    /**
     * 注册赠送奖励币种
     * @var string
     */
    const KEY_REG_AWARD_CURRENCY_ID = "reg_award_currency_id";

    const KEY_AWARD_TAKE_NUM = "award_take_num";

    protected $resultSetType = 'collection';

    protected static function init()
    {
        //添加数据前操作，判断用户是否已插入相同的一条数据
        CurrencyUser::beforeInsert(function ($CurrencyUser) {
            $User = self::where(['member_id' => $CurrencyUser->member_id, 'currency_id' => $CurrencyUser->currency_id])->find();
            if ($User) {
                return false;
            }
        });
    }

    // M令牌释放到MTK
    static function m_to_mtk_release($CurrencyUser, $config)
    {
        if ($CurrencyUser['num'] < 1) {
            Log::write(" 数量太小没必要释放啦" . $CurrencyUser['cu_id']);
            return false;
        }

        // 随机比例
        $rand_percent = explode(',', $config['rand_percent']);
        $rl = mt_rand() / mt_getrandmax();
        $rand_num = keepPoint($rand_percent[0] + ($rl * ($rand_percent[1] - $rand_percent[0])));
        $rand_num = min($rand_num, $rand_percent[1]);

        // 封顶
        $m_max_num = $CurrencyUser['num'];
        if ($CurrencyUser['num'] >= $config['m_max_num']) {
            $m_max_num = $config['m_max_num'];
        }

        // 释放数量
        $release_num = keepPoint($m_max_num * $rand_num / 100, 6);
        $release_num = min($CurrencyUser['num'], $release_num);
        try {
            self::startTrans();
            //增加账本 扣除资产
            $flag = AccountBook::add_accountbook($CurrencyUser['member_id'], $CurrencyUser['currency_id'], 7202,
                'release_static', 'out', $release_num, 0);
            if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
            $flag = CurrencyUser::where(['cu_id' => $CurrencyUser['cu_id'], 'num' => $CurrencyUser['num']])->setDec('num', $release_num);
            if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

            // MTK帐户
            $mtk_currency_user = CurrencyUser::getCurrencyUser($CurrencyUser['member_id'], $config['mtk_currency_id']);
            //增加账本 扣除资产
            $flag = AccountBook::add_accountbook($mtk_currency_user['member_id'], $mtk_currency_user['currency_id'], 7202,
                'release_static', 'in', $release_num, 0);
            if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
            $flag = CurrencyUser::where(['cu_id' => $mtk_currency_user['cu_id'], 'num' => $mtk_currency_user['num']])->setInc('num', $release_num);
            if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

            self::commit();
            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("M令牌释放到MTK:" . $e->getMessage());
        }
        return false;
    }

    /**
     *获取数量
     * @param member_id 用户ID
     * @param currency_id 币种ID
     * @param is_lock 是否锁表
     * @param field 获取字段名称
     */
    public function getNum($member_id, $currency_id, $field = 'num', $is_lock = false)
    {
        $info = Db::name('currency_user')->where(['member_id' => $member_id, 'currency_id' => $currency_id]);
        if ($is_lock) $info = $info->lock(true);
        $number = $info->value($field);
        if ($number === null) $number = 0;
        return $number;
    }

    //获取字段
    public function getAndCreate($member_id, $currency_id, $field = false)
    {
        try {

        } catch (Exception $e) {

        }
    }

    /**
     *创建新纪录
     * @param member_id 用户ID
     * @param currency_id 币种ID
     */


    public function createLog($member_id, $currency_id)
    {
        $id = Db::name('currency_user')->insertGetID([
            'member_id' => $member_id,
            'currency_id' => $currency_id,
        ]);
        return $id;
    }

    /**
     * 根据用户id和币种id获取用户一条币资产数据，用户没有则创建一条默认数据
     * @param $member_id
     * @param $currency_id
     * @return CurrencyUser|array|false|mixed|null|PDOStatement|string|Model
     * Created by Red.
     * Date: 2018/12/8 11:13
     */
    static function getCurrencyUser($member_id, $currency_id, $field = null)
    {
        if (!empty($member_id) && !empty($currency_id)) {
            $currencyUser = self::where(['member_id' => $member_id, 'currency_id' => $currency_id])->field($field)->find();
            if ($currencyUser) {
                return $currencyUser;
            } else {
                //防止不存在的用户id创建用户资产信息
                $member = Member::where(['member_id' => $member_id])->field("member_id")->find();
                if ($member) {
                    $cu = new CurrencyUser();
                    $cu->member_id = $member_id;
                    $cu->currency_id = $currency_id;
                    if ($cu->save()) {
                        return self::get($cu->cu_id);
                    }
                }

            }
        }
        return null;
    }

    /**
     * 创建钱包地址
     * @param int $member_id 用户id
     * @param int $currency_id 币种id
     * @return mixed
     * Created by Red.
     * Date: 2018/12/11 15:26
     * @throws
     */
    static function createWalletAddress($member_id, $currency_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        if ($currency_id == Currency::USDT_ID) {
            $omniSwitch = Config::get_value('omni_switch', 2); // 默认开启 Omni
            if ($omniSwitch == 1) {
                $currency_id = Currency::ERC20_ID;
            }
        }

        self::startTrans();
        try {
            if (!empty($member_id) && !empty($currency_id)) {
                $currencyUser = self::getCurrencyUser($member_id, $currency_id);
                if (strlen($currencyUser->chongzhi_url) > 4) {
                    //已创建过
                    $r['code'] = SUCCESS;
                    $r['message'] = lang("lan_create_successfully");
                } else {
                    //未创建过的
                    $currency = Currency::where(['currency_id' => $currency_id])->find();
                    if (!empty($currency)) {
//                        if (true) { // TODO: 等待币服务器生成地址之后再注释if
//                            $r['code'] = SUCCESS;
//                            $r['message'] = lang("lan_create_successfully");
//                        }
                        //瑞波币的用统一一个地址，所以不需要创建
                        if (in_array($currency->currency_type, ['xrp', 'eos', 'dnc'])) {
                            $r['code'] = SUCCESS;
                            $r['message'] = lang("lan_create_successfully");
                        } else {
                            //BTC和USDT的创建
                            if (in_array($currency->currency_type, ['btc', 'usdt'])) {
                                $other_currency = Db::name("currency_user")->alias("a")->field("a.chongzhi_url")->where("member_id=" . $member_id . " and chongzhi_url is not null and chongzhi_url<>'' and (currency_type='btc' or currency_type='usdt')")
                                    ->join(config("database.prefix") . 'currency c', 'a.currency_id = c.currency_id', "LEFT")->find();
                                $address = null;
                                if (!empty($other_currency)) {
                                    //BTC/USDT共用一个地址，如果现在是BTC，则查询相对的币种USDT有没有生成过地址
                                    //有生成过则拿过来使用，不创建新的
                                    $address = $other_currency['chongzhi_url'];
                                }
                                if (empty($address)) {
                                    $btc_address = CurrencyAddressBtc::get_address();
                                    if (!empty($btc_address)) {
                                        $address = $btc_address->cab_address;
                                        $btc_address->cab_time = time();
                                        $btc_address->cab_is_use = 2;
                                        $btc_address->cab_member_id = $member_id;
                                        if (!$btc_address->save()) {
                                            $r['code'] = ERROR9;
                                            throw new Exception(lang("lan_create_failed"));
                                        }
                                    }
                                }
                                if (!empty($address)) {
                                    $currencyUser->chongzhi_url = $address;
                                    if ($currencyUser->save()) {
                                        $r['code'] = SUCCESS;
                                        $r['message'] = lang("lan_create_successfully");
                                    } else {
                                        $r['code'] = ERROR11;
                                        throw new Exception(lang("lan_create_failed"));
                                    }
                                } else {
                                    $r['code'] = ERROR10;
                                    throw new Exception(lang("lan_create_failed"));
                                }

//                                $btc = new Btc();
//                                $server['rpc_user'] = $currency->rpc_user;
//                                $server['rpc_pwd'] = $currency->rpc_pwd;
//                                $server['rpc_url'] = $currency->rpc_url;
//                                $server['port_number'] = $currency->port_number;
//                                $address = $btc->qianbao_new_address($member_id, $server);
//                                if ($address['code']==SUCCESS) {
//                                    $currencyUser->chongzhi_url = $address['result'];
//                                    if ($currencyUser->save()) {
//                                        $r['code'] = SUCCESS;
//                                        $r['message'] = lang("lan_create_successfully");
//                                    } else {
//                                        $r['code'] = ERROR2;
//                                        throw new Exception(lang("lan_save_failed"));
//                                    }
//                                } else {
//                                    $r['code'] = ERROR3;
//                                    throw new Exception(lang($address['message']));
//                                }

                            } // 以太坊和以太坊代币的创建
                            elseif (in_array($currency->currency_type, ['eth', 'eth_token'])) {
                                //查询以太坊和以太坊代币的是否创建过地址，创建过则用相同的,否则新创建一个
                                //$currencyList = Currency::where('currency_type', ['=', 'eth'], ['=', 'eth_token'], 'or')->field("currency_id")->column("currency_id");
                                $same = CurrencyUser::alias("a")->where("member_id=" . $member_id . " and chongzhi_url is not null and chongzhi_url<>'' and (currency_type='eth' or currency_type='eth_token')")
                                    ->join('currency c', 'a.currency_id = c.currency_id')->find();
//                                $array = [];
//                                if(!empty($currencyList)){
//                                    foreach ($currencyList as $vo) {
//                                        $tt->where('currency_id', ['=',$vo], 'or');
//                                    }
//                                }
                                //  $same = $tt->fetchSql(true)->find();var_dump($same);die();
                                if (!empty($same)) {
                                    $currencyUser->chongzhi_url = $same->chongzhi_url;
                                    if ($currencyUser->save()) {
                                        $r['code'] = SUCCESS;
                                        $r['message'] = lang("lan_create_successfully");
                                    } else {
                                        $r['code'] = ERROR4;
                                        throw new Exception(lang("lan_save_failed"));
                                    }
                                } else {
                                    $count = CurrencyAddressEth::where(['cae_is_use' => 1])->count("cae_id");
                                    //从已创建的地址列表上取
                                    if ($count > 0) {
                                        $page = rand(1, $count);
                                        $ethAddressList = CurrencyAddressEth::where(['cae_is_use' => 1])->page($page, 1)->select();
                                        if (!empty($ethAddressList)) {
                                            $ethAddress = $ethAddressList[0];
                                            $currencyUser->chongzhi_url = $ethAddress->cae_address;
                                            if ($currencyUser->save()) {
                                                $ethAddress->cae_is_use = 2;
                                                $ethAddress->cae_member_id = $member_id;
                                                if ($ethAddress->save()) {
                                                    $r['code'] = SUCCESS;
                                                    $r['message'] = lang("lan_create_successfully");
                                                } else {
                                                    $r['code'] = ERROR5;
                                                    throw new Exception(lang("lan_save_failed"));
                                                }
                                            } else {
                                                $r['code'] = ERROR6;
                                                throw new Exception(lang("lan_save_failed"));
                                            }
                                        }
                                    } else {
                                        $r['code'] = ERROR8;
                                        throw new Exception(lang("lan_create_failed"));
                                        //新创建一个地址
//                                        $eth = new Eth($currency->rpc_url, $currency->port_number);
//                                        $result = $eth->personal_newAccount();
//                                        if ($result['code'] == SUCCESS) {
//                                            $currencyUser->chongzhi_url = $result['result']['result'];
//                                            if ($currencyUser->save()) {
//                                                $r['code'] = SUCCESS;
//                                                $r['message'] = lang("lan_create_successfully");
//                                            } else {
//                                                $r['code'] = ERROR7;
//                                                throw new Exception(lang("lan_save_failed"));
//                                            }
//                                        } else {
//                                            $r['code'] = ERROR8;
//                                            throw new Exception(lang("lan_create_failed"));
//                                        }
                                    }
                                }
                            } elseif (in_array($currency->currency_type, ['trx', 'trx_token'])) {
                                //从已创建的地址列表上取
                                $trxAddress = CurrencyAddressTrx::where(['cae_is_use' => 1])->find();
                                if ($trxAddress) {
                                    $currencyUser->chongzhi_url = $trxAddress->cae_address;
                                    if ($currencyUser->save()) {
                                        $trxAddress->cae_is_use = 2;
                                        $trxAddress->cae_member_id = $member_id;
                                        if ($trxAddress->save()) {
                                            $r['code'] = SUCCESS;
                                            $r['message'] = lang("lan_create_successfully");
                                        } else {
                                            $r['code'] = ERROR5;
                                            throw new Exception(lang("lan_save_failed"));
                                        }
                                    } else {
                                        $r['code'] = ERROR6;
                                        throw new Exception(lang("lan_save_failed"));
                                    }
                                } else {
                                    $r['code'] = ERROR8;
                                    throw new Exception(lang("lan_create_failed"));
                                }
                            } elseif (in_array($currency->currency_type, [Currency::PUBLIC_CHAIN_NAME])) {

                                //去中心化的钱包 不需要生成充币地址  使用统一的
//                                $r['code'] = SUCCESS;
//                                $r['message'] = lang("lan_create_successfully");

                                // ABF 中心化
                                $Member = Member::where(['member_id' => $member_id])->find();
                                $address = $Member->ename;

//                                $tft_address=CurrencyAddressTft::get_address();
//                                if(!empty($tft_address)){
//                                    $address=$tft_address->cae_address;
//                                    $tft_address->cae_time=time();
//                                    $tft_address->cae_is_use=2;
//                                    $tft_address->cae_member_id=$member_id;
//                                    if(!$tft_address->save()){
//                                        $r['code'] = ERROR9;
//                                        throw new Exception(lang("lan_create_failed"));
//                                    }
//                                }
//
                                if (!empty($address)) {
                                    $currencyUser->chongzhi_url = $address;
                                    if ($currencyUser->save()) {
                                        $r['code'] = SUCCESS;
                                        $r['message'] = lang("lan_create_successfully");
                                    } else {
                                        $r['code'] = ERROR11;
                                        throw new Exception(lang("lan_create_failed"));
                                    }
                                } else {
                                    $r['code'] = ERROR10;
                                    throw new Exception(lang("lan_create_failed"));
                                }
                            } // FIL币种
                            elseif (in_array($currency->currency_type, ['fil', 'fil_token'])) {
                                //从已创建的地址列表上取
                                $filAddress = CurrencyAddressFil::where(['cae_is_use' => 1])->find();
                                if ($filAddress) {
                                    $currencyUser->chongzhi_url = $filAddress->cae_address;
                                    if ($currencyUser->save()) {
                                        $filAddress->cae_is_use = 2;
                                        $filAddress->cae_member_id = $member_id;
                                        if ($filAddress->save()) {
                                            $r['code'] = SUCCESS;
                                            $r['message'] = lang("lan_create_successfully");
                                        } else {
                                            $r['code'] = ERROR5;
                                            throw new Exception(lang("lan_save_failed"));
                                        }
                                    } else {
                                        $r['code'] = ERROR6;
                                        throw new Exception(lang("lan_save_failed"));
                                    }
                                } else {
                                    $r['code'] = ERROR8;
                                    throw new Exception(lang("lan_create_failed"));
                                }
                            } // bnb币安链
                            elseif (in_array($currency->currency_type, ['bnb', 'bnb_token'])) {
                                //从已创建的地址列表上取
                                $bnbAddress = CurrencyAddressBnb::where(['cae_is_use' => 1])->find();
                                if ($bnbAddress) {
                                    $currency_res = Currency::where(['currency_type' => 'bnb_token'])->select();
                                    foreach ($currency_res as $value) {
                                        $res_currency_user = CurrencyUser::getCurrencyUser($member_id, $value['currency_id']);
                                        $up_currency_user = CurrencyUser::where(['cu_id' => $res_currency_user['cu_id']])->update([
                                            'chongzhi_url' => $bnbAddress->cae_address,
                                        ]);
                                        if (!$up_currency_user) {
                                            throw new Exception(lang("lan_save_failed"));
                                        }
                                    }
                                    $bnbAddress->cae_is_use = 2;
                                    $bnbAddress->cae_member_id = $member_id;
                                    if ($bnbAddress->save()) {
                                        $r['code'] = SUCCESS;
                                        $r['message'] = lang("lan_create_successfully");
                                    } else {
                                        $r['code'] = ERROR5;
                                        throw new Exception(lang("lan_save_failed"));
                                    }
                                } else {
                                    $r['code'] = ERROR8;
                                    throw new Exception(lang("lan_create_failed"));
                                }
                            }

                        }
                    }
                }
            }
            self::commit();
        } catch (Exception $exception) {
            self::rollback();
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }

    /**
     * 获取用户的资产列表
     * @param $member_id 用户id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function asset_list($member_id,$is_bb=0)
    {
        $r = ['code' => ERROR1, 'message' => lang('not_data'), 'result' => null];
        if (empty($member_id)) return $r;

        $where = [
            'is_line' => 1,
            'is_app_currency' => 1,
            'status' => 1,
            'account_type' => ['in', ['wallet', 'holding']]
        ];
        if ($is_bb) {
            $where['is_trade_currency'] = 1;
        }
        $currency_list = Currency::where($where)
            ->field(['currency_id', 'currency_name', 'currency_mark', 'recharge_switch', 'take_switch', 'account_type'])
            ->order(['currency_id' => 'asc', 'sort' => 'asc'])->select();
        if (empty($currency_list)) return $r;

        foreach ($currency_list as &$value) {
            $currency_user = self::getCurrencyUser($member_id, $value['currency_id']);
            $value['money'] = $currency_user->num ?: 0;
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang("lan_data_success");
        $r['result'] = $currency_list;
        return $r;
    }

    /**
     * 获取用户的资产列表
     * @param $member_id        用户id
     * @return CurrencyUser|array|false|mixed|null|PDOStatement|string|Model
     * Created by Red.
     * Date: 2018/12/12 10:49
     */
    static function assetList($member_id, $is_bb = 0)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        if (!empty($member_id)) {
            $where = ['is_line' => 1];
            if ($is_bb) {
                $where['is_trade_currency'] = 1;
            } else {
                $where['is_app_currency'] = 1;
            }
            $where['status'] = 1;//启用
            $is_bb_open = Config::get_value('wallet_to_bb_is_open', 0);

            //上线的币种
            $currencyList = Currency::where($where)->field("currency_id,currency_name,currency_logo,currency_mark,rgb,take_switch,recharge_switch, currency_transfer_switch, exchange_switch,trade_transfer_currency,is_trade_currency,trade_currency_id,account_type")->order("sort asc")->select()->toArray();
            if ($currencyList) {
                foreach ($currencyList as &$value) {
                    $currencyUser = self::getCurrencyUser($member_id, $value['currency_id']);
                    if (!empty($currencyUser)) {
                        $value['money'] = keepPoint($currencyUser->num, 6);
                        $value['num_award'] = keepPoint($currencyUser->num_award, 6);
                        $value['forzen_num'] = keepPoint($currencyUser->forzen_num, 6);
                        $value['keep_num'] = keepPoint($currencyUser->keep_num, 6);
                        $value['lock_num'] = keepPoint($currencyUser->lock_num, 6); // 推荐冻结
                        $value['release_lock'] = keepPoint($currencyUser->release_lock, 6); // 产出冻结
                        $value['is_auth'] = UserCurrencyAuth::isAuth($member_id, $value['currency_id']);
                        // 兑换开关判断
//                        $convertFlag = ConvertConfig::where('currency_id', $value['currency_id'])->find();
//                        $value['convert_switch'] = !empty($convertFlag) ? 1 : 2;
//                        $num = 0;
//                        $flag = MoneyInterest::where(['member_id' => $member_id, 'status' => 0, 'currency_id' => $value['currency_id']])->sum('num');
//                        if ($flag > 0) {
//                            $num = $flag;
//                        }
//                        $value['money_num'] = keepPoint($num, 6);
//                        $allNum = $value['money'] + $value['forzen_num'] + $value['money_num']; //+ $value['keep_num']
                        $allNum = $value['money'] + $value['lock_num'] + $value['release_lock'] + $value['forzen_num'];
//                        $value['all_num'] = $allNum;
                        $value['all_num'] = keepPoint($allNum, 6);

                        $trade_currency_id = explode(',', $value['trade_currency_id']);
                        if (isset($value['is_trade_currency']) == 1 && !empty($trade_currency_id[0])) {
                            $value['cny_price'] = Trade::getCurrencyRealMoney($value['currency_id'], $trade_currency_id[0], 'CNY');
                            $value['usdt_price'] = Trade::getCurrencyRealMoney($value['currency_id'], $trade_currency_id[0], 'USD');
                        } else {
                            $value['cny_price'] = CurrencyPriceTemp::get_price_currency_id($value['currency_id'], 'CNY');
                            $value['usdt_price'] = CurrencyPriceTemp::get_price_currency_id($value['currency_id'], 'USD');
                        }
                        $value['cny_num'] = bcmul($value['all_num'], $value['cny_price'], 2);
                        $value['usdt_num'] = bcmul($value['all_num'], $value['usdt_price'], 6);
                    } else {
                        $value['money'] = $value['num_award'] = $value['forzen_num'] = $value['keep_num'] = $value['cny'] = $value['usd'] = $value['all_num'] = 0;
                    }
                    $value['bb_transfer'] = $is_bb_open == 1 && $value['trade_transfer_currency'] > 0 ? 1 : 2;
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("lan_data_success");
                $r['result'] = $currencyList;
            } else {
                $r['message'] = lang("lan_not_data");
            }
        }
        return $r;
    }

    /**
     * 钱包头部信息
     * @param int $member_id 用户ID
     * @param int $currency_id 币种id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function top_info($member_id, $currency_id)
    {
        $list = ['currency_name' => '', 'num' => 0, 'mtk_price' => 0, 'mtk_rate' => 0];
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => $list];
        $where = [
            'a.member_id' => $member_id,
            'a.currency_id' => $currency_id
        ];
        $currency_info = CurrencyUser::getCurrencyUser($member_id, $currency_id);
        if (!$currency_info) {
            return $r;
        }

        $list = self::alias('a')->field('a.cu_id,a.num,b.currency_name')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->where($where)
            ->find();
        if (!$list) return $r;
        $level = RocketMember::where(['member_id' => $member_id])->value('level');
        $list['level'] = $level;
        //$price = Db::name('mtk_currency_price')->order('id desc')->value('price');
        $price = Trade::getLastTradePrice(99, 5);
        $list['mtk_price'] = sprintf('%.6f', $price);
        $list['mtk_rate'] = 0.02;

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }


}
