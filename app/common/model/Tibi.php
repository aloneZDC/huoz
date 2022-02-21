<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/8
 * Time: 15:16
 */

namespace app\common\model;

use json;
use message\Btc;
use message\Eos;
use message\Eth;
use message\Xrp;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;

class Tibi extends Base
{
    protected $resultSetType = 'collection';

    const STATUS_SUCCESS = 1;
    const STATUS_WAIT_CONFIRM = 0;
    const STATUS_WAIT = -1;
    const STATUS_FAIL = -2;

    const STATUS_ENUM = [
        self::STATUS_SUCCESS => '已完成',
        self::STATUS_WAIT_CONFIRM => '等待确认中',
        self::STATUS_WAIT => '审核中',
        self::STATUS_FAIL => '已撤销',
    ];

    const ALL_STATUS_ARRAY = [self::STATUS_SUCCESS, self::STATUS_WAIT_CONFIRM, self::STATUS_WAIT, self::STATUS_FAIL];

    protected static function init()
    {
        //添加数据前操作，判断用户是否已插入相同的一条数据
        Tibi::beforeInsert(function ($tibi) {
            if (isset($tibi->ti_id) && isset($tibi->currency_id)) {
                $ti = self::where(['ti_id' => $tibi->ti_id, 'currency_id' => $tibi->currency_id])->field("ti_id")->find();
                if ($ti) {
                    return false;
                }
            }
        });
    }

    /**
     * 根据区块链上转账信息给用户充币
     * @param string $from_url 转账地址
     * @param string $to_url 接收地址（接收地址是本系统内的，不然用户资产无法增加）
     * @param double $money 数量
     * @param string $ti_id 交易hash
     * @param int $check_time 成功的时间
     * @param int $currency_id 币种id
     * @param int $add_time 开始时间
     * @param int $tag 接收瑞波币的数字标签
     * @return mixed
     * Created by Red.
     * Date: 2018/12/8 16:00
     */
    static function rechargeTibiAndMoneyAdd($from_url, $to_url, $money, $ti_id, $check_time, $currency_id, $add_time = null, $tag = null)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        // self::startTrans();
        try {
            if (!empty($to_url) && !empty($money) && $money > 0 && !empty($ti_id) && !empty($check_time) && is_numeric($currency_id)) {
                //判断交易hash是否已存在
                $find = self::where(['ti_id' => $ti_id, 'currency_id' => $currency_id])->field("ti_id")->find();
                if (!empty($find)) {
                    $r['code'] = SUCCESS;
                    $r['message'] = lang("lan_number_already_exists");
                    return $r;
                }
                $currency = Currency::where(['currency_id' => $currency_id])->field("currency_type,currency_mark,summary_fee_address,recharge_address")->find();
                //ETH的转帐方为充手续费的地址不给用户充值ETH
                if (in_array($currency->currency_mark, ['ETH']) && $currency->summary_fee_address == $from_url) {
                    $r['code'] = SUCCESS;
                    $r['message'] = lang("lan_number_already_exists");
                    return $r;
                }
                //瑞波币/EOS的用户信息根据tag标签判断
                if (in_array($currency->currency_type, ['xrp', 'eos', 'dnc'])) {
                    //充币地址不等于统一充币地址的通过
                    if ($currency->currency_type == 'xrp' or $currency->currency_type == 'dnc') {
                        $flag = WalletAdminAddress::checkIsRecharge($currency_id, $to_url);
                    } else {
                        $flag = $currency->recharge_address == $to_url;
                    }
                    if (!$flag) {
//                    if($currency->recharge_address!=$to_url){
                        $r['code'] = SUCCESS;
                        $r['message'] = lang("address_error");
                        return $r;
                    }
                    $to = CurrencyUser::getCurrencyUser($tag, $currency_id);
                    //查询用户保存的地址表获取地址对应的备注名字
                    if (!empty($to)) {
                        $qianbaoAddress = QianbaoAddress::where(['user_id' => $tag, 'currency_id' => $currency_id, 'qianbao_url' => $from_url, 'tag' => $tag])->field("names")->find();
                    }
                } elseif ($currency->currency_type == Currency::PUBLIC_CHAIN_NAME) {
                    $member_id = Member::where(['ename' => $from_url])->value('member_id');
                    if ($member_id) {
                        $to = CurrencyUser::where(["member_id" => $member_id, "currency_id" => $currency_id])->find();
                        //查询用户保存的地址表获取地址对应的备注名字
                        if (!empty($to)) {
                            $qianbaoAddress = QianbaoAddress::where(['user_id' => $to->member_id, 'currency_id' => $currency_id, 'qianbao_url' => $from_url])->field("names")->find();
                        }
                    }
                } // MTK - TRC20
                elseif ($currency->currency_mark == "MTK") {
                    $mtk_user = CurrencyUser::where(['chongzhi_url' => $to_url])->find();
                    $to = CurrencyUser::where(['member_id' => $mtk_user['member_id'], 'currency_id' => $currency_id])->find();
                }
                // bnb币安链
                elseif (in_array($currency->currency_mark, ['YLP', 'LLP'])) {
                    $bnb_user = CurrencyUser::where(['chongzhi_url' => $to_url])->find();
                    $to = CurrencyUser::where(['member_id' => $bnb_user['member_id'], 'currency_id' => $currency_id])->find();
                }
                else {
                    $to = CurrencyUser::where(["chongzhi_url" => $to_url, "currency_id" => $currency_id])->find();
                    //查询用户保存的地址表获取地址对应的备注名字
                    if (!empty($to)) {
                        $qianbaoAddress = QianbaoAddress::where(['user_id' => $to->member_id, 'currency_id' => $currency_id, 'qianbao_url' => $from_url])->field("names")->find();
                    }
                }
                if (empty($to)) {
                    $r['message'] = lang("lan_user_data_error");
                    return $r;
                }
                $tibiData = new Tibi();
                $tibiData->from_url = $from_url;
                $tibiData->to_member_id = $to->member_id;
                $tibiData->to_url = $to_url;
                $tibiData->num = $money;
                $tibiData->status = 3;
                $tibiData->ti_id = $ti_id;
                $tibiData->check_time = $check_time;
                $tibiData->currency_id = $currency_id;
                $tibiData->actual = $money;
                $tibiData->type = "2"; // TODO: 无type
                $tibiData->b_type = 0;
                $tibiData->transfer_type = "1";
                $tibiData->add_time = $check_time;
                if (!empty($qianbaoAddress)) $tibiData->names = $qianbaoAddress->names;
                if (!empty($add_time)) $tibiData->add_time = $add_time;
                if (!empty($tag)) $tibiData->tag = $tag;

                if ($tibiData->save()) {
                    //增加财务日志
//                    $addStream = CurrencyUserStream::addStream($to->member_id, $currency_id, 1, $money, 1, 11, $tibiData->id, '充币');
                    //增加账本
                    $bookContent = "lan_receive";
                    $bookCurrencyId = $currency_id;
                    if (Currency::ERC20_ID == $currency_id
                        || Currency::TRC20_ID == $currency_id) {
                        // ERC20的账本记录到USDT的账本
//                        $bookContent = "lan_ERC20_receive";
                        $bookCurrencyId = Currency::USDT_ID;
                    }
                    $addAccountBook = model('AccountBook')->addLog([
                        'member_id' => $to->member_id,
                        'currency_id' => $bookCurrencyId,
                        'type' => 5,
                        'content' => $bookContent,
                        'number_type' => 1,
                        'number' => $money,
                        'address' => $to_url,
                        'third_id' => $tibiData->id,
                        'name' => !empty($tibiData->names) ? $tibiData->names : "",
                    ]);
                    if (!$addAccountBook) {
                        throw new Exception(lang("lan_operation_failure"));
                    }

//                    if ($addStream) {
                    //增加每日充币统计
                    $everydayRecharge = WalletEverydayRecharge::addWalletEverydayRecharge($currency_id, $money, $check_time);
                    if ($everydayRecharge) {
                        // 如果是ERC20则增加USDT的资产
                        if (Currency::ERC20_ID == $currency_id
                            || Currency::TRC20_ID == $currency_id) {
                            $to = CurrencyUser::getCurrencyUser($to->member_id, Currency::USDT_ID);
                        }
                        $to->num += $money;
                        $to->real_num += $money;
                        //增加币资产数量
                        if ($to->save()) {
                            $r['code'] = SUCCESS;
                            $r['message'] = lang("lan_operation_success");
                        } else {
                            throw new Exception(lang("lan_operation_failure"));
                        }
                    } else {
                        throw new Exception(lang("lan_operation_failure"));
                    }
//                    } else {
//                        throw new Exception(lang("lan_operation_failure"));
//                    }

                } else {
                    throw new Exception(lang("lan_operation_failure"));
                }
            }
            // self::commit();
        } catch (Exception $exception) {
            //self::rollback();
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }

    /**查询区块链上的记录给用户的币资产增加币数量
     * @param $currency_id          币种id
     * @param $uid                  用户id
     * @return mixed
     * Created by Red.
     * Date: 2018/12/10 14:18
     */
    static function rechargeLog($currency_id, $uid)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        if (!empty($currency_id) && !empty($uid)) {
            $currency = Currency::where(['currency_id' => $currency_id])->find();//这个是货币
            if (!empty($currency)) {
                $currency_user = CurrencyUser::getCurrencyUser($uid, $currency_id);
                //获取用户地址信息
                if (!empty($currency_user)) {
                    //以太坊和以太坊代币
                    if (in_array($currency->currency_type, ['eth', 'eth_token'])) {
                        $eth = new Eth();

                        //以太坊的充币
                        if ($currency->currency_type == 'eth' && $currency->currency_mark == "ETH") {
                            $Transactions = $eth->getEthTransactionsList($currency_user->chongzhi_url);
                            if (!empty($Transactions)) {
                                //插入转帐记录
                                foreach ($Transactions as $val) {
                                    //转帐方地址是充手续费的则不添加记录，也不增加币数量(充手续费如果有改动过，则要在此过滤掉)
                                    if (!in_array($val['from'], [$currency->summary_fee_address])) {
                                        if ($val['isError'] == 0 && $val['to'] == $currency_user->chongzhi_url) {
                                            self::rechargeTibiAndMoneyAdd($val['from'], $val['to'], $val['value'], $val['hash'], $val['timeStamp'], $currency_id, $val['timeStamp']);
                                        }
                                    }

                                }
                            }
                            $r['code'] = SUCCESS;
                            $r['message'] = lang("lan_charge_money_successfully");
                        } elseif ($currency->currency_type == "eth_token") {
                            //以太坊代币的充币
                            $recording = $eth->getTokenHistory($currency_user->chongzhi_url, $currency->token_address);
                            if (!empty($recording)) {
                                foreach ((array)$recording as $value) {
                                    if ($value['success'] == true) {
                                        //添加充币记录
                                        self::rechargeTibiAndMoneyAdd($value['from'], $value['to'], $value['value'], $value['hash'], $value['timestamp'], $currency_id, $value['timestamp']);
                                    }
                                }
                            }
                            $r['code'] = SUCCESS;
                            $r['message'] = lang("lan_charge_money_successfully");
                        }
                    } elseif ($currency->currency_type == 'btc' && $currency->currency_mark == "BTC") {
                        //BTC的充币
                        $btc = new Btc();
                        $server['rpc_user'] = $currency->rpc_user;
                        $server['rpc_pwd'] = $currency->rpc_pwd;
                        $server['rpc_url'] = $currency->rpc_url;
                        $server['port_number'] = $currency->port_number;
                        $btcResult = $btc->trade_by_address($currency_user->chongzhi_url, $server);
                        if (!empty($btcResult)) {
                            foreach ($btcResult as $value) {
                                if (isset($value['blockhash']) && !empty($value['blockhash']) && isset($value['confirmations']) && $value['confirmations'] > 0) {
                                    $stattime = isset($value['time']) ? $value['time'] : $value['blocktime'];
                                    //查询到充币记录，保存充币记录，并把充币数量相加到资产，重复数据不会再保存
                                    if ($value['address'] == $currency_user->chongzhi_url && $value['category'] == "receive") {
                                        self::rechargeTibiAndMoneyAdd(null, $value['address'], $value['amount'], $value['txid'], $value['blocktime'], $currency_id, $stattime);
                                    }
                                }
                            }
                            $r['code'] = SUCCESS;
                            $r['message'] = lang("lan_charge_money_successfully");
                        }

                    } elseif ($currency->currency_type == 'usdt' && $currency->currency_mark == "USDT") {
                        //USDT的充币
                        $btc = new Btc();
                        $server['rpc_user'] = $currency->rpc_user;
                        $server['rpc_pwd'] = $currency->rpc_pwd;
                        $server['rpc_url'] = $currency->rpc_url;
                        $server['port_number'] = $currency->port_number;
                        $usdtResult = $btc->omni_trade_qianbao($currency_user->chongzhi_url, $server);
                        if (!empty($usdtResult)) {
                            foreach ($usdtResult as $value) {
                                //valid为true则是成功的,propertyid=31为USDT的币属性
                                if (isset($value['valid']) && $value['valid'] == true && $value['propertyid'] == 31) {
                                    $stattime = isset($value['blocktime']) ? $value['blocktime'] : time();
                                    //查询到充币记录，保存充币记录，并把充币数量相加到资产，重复数据不会再保存
                                    if ($value['referenceaddress'] == $currency_user->chongzhi_url) {
                                        self::rechargeTibiAndMoneyAdd($value['sendingaddress'], $value['referenceaddress'], $value['amount'], $value['txid'], $value['blocktime'], $currency_id, $stattime);
                                    }
                                }
                            }
                            $r['code'] = SUCCESS;
                            $r['message'] = lang("lan_charge_money_successfully");
                        }
                    }
                } else {
                    $r['message'] = lang("address_error");
                }
            }
        }

        return $r;
    }


    /**
     * 后台审核提币记录操作
     * @param $tibi_id                交易id
     * @param $check_status           审核状态:0为提币中 1为提币成功  2为充值中 3位充值成功 8兑换 -1 审核中 -2 撤销
     * @param $no                     交易编号
     * @param $success_time           成功时间
     * @param $fee                    区块链的手续费
     * @return json
     * @throws Exception
     */
    public static function checkTransfer($tibi_id, $check_status, $no = null, $success_time = null, $fee = null)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        try {
            if (!empty($tibi_id) && !empty($check_status)) {
                $tibi = self::where(['id' => $tibi_id])->find();
                if (!empty($tibi)) {
                    if (!empty($no)) $tibi->ti_id = $no;
                    //只能操作check_status=-1:待审核和check_status=0:待节点确认状态的数据
                    if ($tibi->status == -1 || $tibi->status == 0) {
                        /*******提币成功的操作处理***********/
                        if ($check_status == 1 && !empty($success_time)) {
                            //数据库中不能存在相同的两个交易hash
                            $findNo = self::where(['ti_id' => $no])->field("id")->find();
                            if (!empty($findNo) && $tibi_id != $findNo->id) {
                                throw new Exception("该交易编号已存,请确认");
                            }

                            if (Currency::ERC20_ID == $tibi->currency_id || Currency::TRC20_ID == $tibi->currency_id) {
                                // ERC20 处理
                                $userCurrency = CurrencyUser::getCurrencyUser($tibi->from_member_id, Currency::USDT_ID);
                            } else {
                                $userCurrency = CurrencyUser::getCurrencyUser($tibi->from_member_id, $tibi->currency_id);
                            }
                            if (!empty($userCurrency)) {
                                //冻结金额要大于或等转账数据
                                if (bccomp($userCurrency->forzen_num, $tibi->num, 8) >= 0) {
                                    $userCurrency->forzen_num -= $tibi->num;
                                    //减掉冻结的金额
                                    if ($userCurrency->save()) {
                                        $tibi->blockchain_fee = $fee;
                                        $tibi->status = $check_status;
                                        $tibi->check_time = $success_time;
                                        if ($tibi->save()) {
//                                           //添加提币数据到每天统计表
                                            $wetResult = WalletEverydayTake::addWalletEverydayTake($tibi->currency_id, $tibi->actual, $success_time);
                                            if ($wetResult) {
                                                $r['code'] = SUCCESS;
                                                $r['message'] = "操作成功";
                                            } else {
                                                $r['code'] = ERROR4;
                                                throw new Exception("保存数据异常");
                                            }
                                        } else {
                                            $r['code'] = ERROR2;
                                            throw new Exception("保存数据异常");
                                        }
                                    } else {
                                        $r['code'] = ERROR3;
                                        throw new Exception("保存数据异常");
                                    }
                                } else {
                                    throw new Exception("资金数据异常");
                                }

                            }
                        } elseif ($check_status == -1) {
                            /*********重新审核*******/
                            $tibi->status = $check_status;
                            if ($tibi->save()) {
                                $r['code'] = SUCCESS;
                                $r['message'] = "已进入待审核中";
                            } else {
                                $r['message'] = "保存数据异常";
                            }
                        } elseif ($check_status == -2) {
                            /**********拒绝/撤销转账**********/
                            if (Currency::ERC20_ID == $tibi->currency_id || Currency::TRC20_ID == $tibi->currency_id) {
                                // ERC20 处理
                                $userCurrency = CurrencyUser::getCurrencyUser($tibi->from_member_id, Currency::USDT_ID);
                            } else {
                                $userCurrency = CurrencyUser::getCurrencyUser($tibi->from_member_id, $tibi->currency_id);
                            }
                            if (!empty($userCurrency)) {
                                //提币账本展示有 状态
                                $result = Db::name('accountbook')->where(['third_id' => $tibi_id, 'type' => 6, 'status' => 0])->setField('status', -1);
                                if (!$result) throw new Exception(lang("lan_operation_failure"));

                                //管理员撤销提币 增加账本
                                $addAccountBook = model('AccountBook')->addLog([
                                    'member_id' => $tibi->from_member_id,
                                    'currency_id' => ($tibi->currency_id == Currency::ERC20_ID || $tibi->currency_id == Currency::TRC20_ID) ? Currency::USDT_ID : $tibi->currency_id,
                                    'type' => 7,
                                    'content' => 'lan_admin_cancel_sender',
                                    'number_type' => 1,
                                    'number' => $tibi->num,
                                    'address' => $tibi->to_url,
                                    'third_id' => $tibi_id,
                                    'name' => !empty($tibi->names) ? $tibi->names : '',
                                ]);
                                if (!$addAccountBook) {
                                    throw new Exception(lang("lan_operation_failure"));
                                }
                                //冻结金额要大于或等转账数据
                                if (bccomp($userCurrency->forzen_num, $tibi->num, 8) >= 0) {
                                    $userCurrency->forzen_num -= $tibi->num;
                                    $userCurrency->num += $tibi->num;
                                    //减掉冻结的金额,加回可用金额
                                    if ($userCurrency->save()) {
                                        $tibi->status = $check_status;
                                        $tibi->check_time = time();
                                        if ($tibi->save()) {
                                            $r['code'] = SUCCESS;
                                            $r['message'] = "拒绝操作成功";
                                        } else {
                                            $r['code'] = ERROR4;
                                            throw new Exception("保存数据异常-数据保存失败1");
                                        }
                                    } else {
                                        $r['code'] = ERROR5;
                                        throw new Exception("保存数据异常-数据保存失败2");
                                    }
                                } else {
                                    $r['code'] = ERROR6;
                                    throw new Exception("资金数据异常-冻结金额小于转账数据");
                                }
                            } else {
                                throw new Exception("获取资产失败");
                            }
                        } elseif ($check_status == 0) {
                            //等确认中状态
                            $tibi->status = $check_status;
                            if ($tibi->save()) {
                                $r['code'] = SUCCESS;
                                $r['message'] = "重新进入待审核中";
                            } else {
                                $r['code'] = ERROR7;
                                throw new Exception("保存数据异常");
                            }
                        }
                    } else {
                        $r['message'] = "该数据不是可以审核的状态，请联系开发人员";
                    }
                }
            }
        } catch (Exception $exception) {
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }


    /**
     * 用户前台申请添加一条充提币记录
     * @param int $from_member_id 转账用户id
     * @param string $to_address 接收人地址
     * @param double $money 实际到帐的数量(手续费还没算上)
     * @param int $currency_id 币种id
     * @param string|null $remark 用户备注信息
     * @param int|null $tag 瑞波币/EOS的标签数字
     * @return mixed
     * @throws Exception
     */
    public static function addTibi($from_member_id, $to_address, $money, $currency_id, $remark = null, $tag = null)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        self::startTrans();
        try {
            if (!empty($from_member_id) && !empty($to_address) && !empty($money) && is_numeric($money) && $money > 0
                && is_numeric($currency_id)) {
                //把标签转义一下，EOS的可以传中文的
                if (!empty($tag)) $tag = htmlspecialchars($tag);
                $isERC20 = ($currency_id == Currency::ERC20_ID
                    || $currency_id == Currency::TRC20_ID) ? true : false;
                $currency = Currency::where(['currency_id' => $currency_id])->find();
//                if ($isERC20) {
                // 使用USDT的配置
//                    $currencyUSDT = Currency::where('currency_id', Currency::USDT_ID)->find();
//                    $currency['take_switch'] = $currencyUSDT['take_switch'];
//                    $currency['currency_min_tibi'] = $currencyUSDT['currency_min_tibi'];
//                    $currency['currency_all_tibi'] = $currencyUSDT['currency_all_tibi'];
//                }
                //提币通道关闭
                if ($currency->take_switch == 2) {
                    $r['message'] = $currency->currency_name . lang("lan_coin_temporarily_closed");
                    return $r;
                }
                //最小提币数量
                if (bccomp($currency->currency_min_tibi, $money, 8) > 0) {
                    $r['message'] = lang('lan_currency_minimum_number_of') . $currency->currency_min_tibi;
                    return $r;
                }
                //超过最大提币数量
                if (((double)$currency->currency_all_tibi > 0) and bccomp($money, $currency->currency_all_tibi, 8) > 0) {
                    $r['message'] = lang("lan_exceeded_the_maximum_limit");
                    return $r;
                }
                $todaySum = Tibi::where(['from_member_id' => $from_member_id, 'currency_id' => $currency_id])->where("status", "in", [0, 1, 2, 3, -1])->where("add_time", "between", [todayBeginTimestamp(), todayEndTimestamp()])->sum("num");
                $todaySum = !empty($todaySum) ? $todaySum : 0;
                //当天提币总量不能超过最大提币数量
                if (((double)$currency->currency_all_tibi > 0) and bccomp($money, ($currency->currency_all_tibi - $todaySum), 8) > 0) {
                    $r['message'] = lang("lan_exceeded_the_maximum_limit");
                    return $r;
                }

                //备注信息不能超20个字符
                if (mb_strlen($remark) > 20) {
                    $r['message'] = lang("lan_remarks_can_not_exceed");
                    return $r;
                }
                $from = CurrencyUser::getCurrencyUser($from_member_id, $currency_id);
                if (empty($from)) {
                    $r['message'] = lang("lan_user_data_error");
                    return $r;
                }
                if (!empty($currency)) {
                    //判断钱包地址格式是否正确
                    if (in_array($currency->currency_type, ['eth', 'eth_token'])) {
                        if (!isValidAddress($to_address)) {
                            $r['message'] = lang("address_error");
                            return $r;
                        }
                    } elseif (in_array($currency->currency_type, ['usdt'])) {
                        if (isValidAddress($to_address)) {
                            $r['message'] = lang("address_error");
                            return $r;
                        }
                    }
//                    elseif (in_array($currency->currency_type, ['btc', 'usdt'])) {
//                        $btc = new Btc();
//                        $server['rpc_user'] = $currency->rpc_user;
//                        $server['rpc_pwd'] = $currency->rpc_pwd;
//                        $server['rpc_url'] = $currency->rpc_url;
//                        $server['port_number'] = $currency->port_number;
//                        if (!$btc->check_qianbao_address($to_address, $server)) {
//                            $r['message'] = lang("address_error");
//                            return $r;
//                        }
//                    }
                    if (in_array($currency->currency_type, ['xrp', 'dnc'])) {
                        //如果是瑞波币类型，需要填写数字标签
                        if ('xrp' == $currency->currency_type) {
                            if (empty($tag) || !is_numeric($tag)) {
                                $r['message'] = lang("lan_label_to_be_number");
                                return $r;
                            }
                            if ($tag > 4294967295) {
                                $r['message'] = lang("lan_tag_number_is_too_big");
                                return $r;
                            }
                        }
                        //平台内互转，数字标签不能是自己标签
                        $isRecharge = WalletAdminAddress::checkIsRecharge($currency_id, $to_address);
                        if ($tag == $from_member_id && $isRecharge) {
                            $r['message'] = lang("lan_can_not_transfer_yourself");
                            return $r;
                        }
                        $from_address = $currency->recharge_address;
                    } elseif (in_array($currency->currency_type, ['eos'])) {
                        //EOS币类型，标签可以是中文
                        //平台内互转，数字标签不能是自己标签
                        if ($tag == $from_member_id && $to_address == $currency->recharge_address) {
                            $r['message'] = lang("lan_can_not_transfer_yourself");
                            return $r;
                        }
                        $from_address = $currency->recharge_address;
                    } // MTK - TRC20
                    elseif ($currency->currency_mark == 'MTK') {
                        $currencyUser = CurrencyUser::where(['member_id' => $from_member_id, 'currency_id' => Currency::TRC20_ID])->find();
                        if ($currencyUser->chongzhi_url == $to_address) {
                            $r['message'] = lang("lan_can_not_transfer_yourself");
                            return $r;
                        }
                        $from_address = $currencyUser->chongzhi_url;
                    } else {
                        //不能转帐给自己
                        if ($from->chongzhi_url == $to_address) {
                            $r['message'] = lang("lan_can_not_transfer_yourself");
                            return $r;
                        }
                        $from_address = $from->chongzhi_url;
                    }
                    //EOS币精确到小数点4位
                    if (in_array($currency->currency_type, ['eos'])) {
                        $money = keepPoint($money, 4);
                        $fee = bcdiv(bcmul($currency->tcoin_fee, $money, 6), 100, 4);
                        $totalMoney = bcadd($money, $fee, 4);
                    } else {
                        $hxy_user_id = Config::get_value('hxy_user_id', '');//账户
                        if ($hxy_user_id == $from_member_id) {
                            $fee = 0;//抽水账户提币，免手续费
                        } else {
                            // 云梯D8或D8以上手续费为10%
//                        $airInfo = (new UserAirLevel())->getAirInfo($from_member_id);
//                        $airLevelTakeCoinFee = Config::get_value('air_level_take_coin_fee');
//                        if ($airInfo['level_id'] < $airLevelTakeCoinFee) {
                            if ($currency->fee_type == 1) { // 百分比
                                $fee = bcdiv(bcmul($currency->fee_greater, $money, 6), 100, 6);
                            } elseif ($currency->fee_type == 3) { // 混合
                                if ($money >= $currency['fee_greater']) {
                                    // 百分比
                                    $radio = (double)$currency['fee_greater'] * 0.01;
                                    $fee = bcmul($radio, $money, 6);
                                } else {
                                    // 固定
                                    $fee = ((double)$currency['fee_less']);
                                }
                            } elseif ($currency->fee_type == 4) {
                                //固定 + 百分比
                                $fee = keepPoint(((double)$currency['fee_less']) + $money * $currency['fee_greater'] * 0.01, 6);
                            } else { // 固定
//                                $fee = bcdiv($currency->tcoin_fee, 100, 6);
//                            $fee = ((double)$currency->tcoin_fee);
                                $fee = ((double)$currency['fee_less']);
                            }
//                        } else {
//                            $airLevelTakeCoinFeeRadio = Config::get_value('air_level_take_coin_fee_radio');
//                            $radio = $airLevelTakeCoinFeeRadio * .01;
//                            $fee = bcmul($radio, $money, 6);
//                        }
                        }
                        $totalMoney = bcadd($money, $fee, 6);
                    }
                    //判断余额是否充足
                    if ($isERC20) {
                        // 使用USDT的余额信息
                        $USDTCurrencyUser = CurrencyUser::getCurrencyUser($from_member_id, Currency::USDT_ID);
                        $from->num = $USDTCurrencyUser->num;
                    }
                    if (bccomp($from->num, $totalMoney, 8) >= 0) {

                        // 账户可用余额必须剩下20
//                        $balanceSurplusNum = Config::get_value('balance_surplus_num');
//                        if (bcsub($from->num, $totalMoney, 8) < $balanceSurplusNum) {
//                            throw new Exception(lang('balance_must_surplus_20', ['num' => $balanceSurplusNum]));
//                        }

                        $tibiObject = new Tibi();
                        $tibiObject->to_url = $to_address;
                        $tibiObject->add_time = time();
                        $tibiObject->num = $totalMoney;
                        $tibiObject->currency_id = $currency_id;
                        $tibiObject->b_type = 0;
                        $tibiObject->from_url = $from_address;
                        $tibiObject->from_member_id = $from->member_id;
                        $tibiObject->fee = $fee;
                        $tibiObject->actual = $money;
                        if (!empty($remark)) $tibiObject->remark = $remark;
                        if (in_array($currency->currency_type, ['xrp', 'eos', 'dnc'])) $tibiObject->tag = $tag;//如果是瑞波币/EOS，需要标签数据
//                        if (!empty($currency->tcoin_fee) && $currency->tcoin_fee > 0) {
//                            $tibiObject->actual = keepPoint($money * (1 - $currency->tcoin_fee / 100), 6);//计算出扣除手续费后的数量
//                        } else {
//                            $tibiObject->actual = $money;
//                        }

                        //查询用户保存的地址表获取地址对应的备注名字
                        if (!empty($tag)) {
                            $qianbaoAddress = QianbaoAddress::where(['user_id' => $from->member_id, 'currency_id' => $currency_id, 'qianbao_url' => $to_address, 'tag' => $tag])->field("names")->find();
                        } else {
                            $qianbaoAddress = QianbaoAddress::where(['user_id' => $from->member_id, 'currency_id' => $currency_id, 'qianbao_url' => $to_address])->field("names")->find();
                        }
                        if (!empty($qianbaoAddress)) $tibiObject->names = $qianbaoAddress->names;
                        $to = null;
                        //如是瑞波币/EOS，判断是否统一充币地址，如果是则说明是平台内互转
                        if (in_array($currency->currency_type, ['xrp', 'eos', 'dnc'])) {
                            if ($currency->currency_type == 'xrp' or $currency->currency_type == 'dnc') {
                                $isAdminRechargeAddress = WalletAdminAddress::checkIsRecharge($currency_id, $to_address);
                                if ($isAdminRechargeAddress) {
                                    //如果是平台的瑞波币地址，则tag标签则为平台内的用户member_id
                                    $to = CurrencyUser::getCurrencyUser($tag, $currency_id);
                                    //如果查询不到接收的用户信息，则说明是用户填错了标签ID
                                    if (empty($to)) {
                                        throw new Exception(lang("lan_tag_ID_error"));
                                    }
                                }
                            } else {
                                if ($currency->recharge_address == $to_address) {
                                    //如果是平台的瑞波币地址，则tag标签则为平台内的用户member_id
                                    $to = CurrencyUser::getCurrencyUser($tag, $currency_id);
                                    //如果查询不到接收的用户信息，则说明是用户填错了标签ID
                                    if (empty($to)) {
                                        throw new Exception(lang("lan_tag_ID_error"));
                                    }
                                }
                            }
                        } // MTK - TRC20
                        elseif ($currency->currency_mark == 'MTK') {
                            $CurrencyUser = CurrencyUser::where(['chongzhi_url' => $to_address, 'currency_id' => Currency::TRC20_ID])->find();
                            $to = CurrencyUser::where(['member_id' => $CurrencyUser['member_id'], 'currency_id' => $currency_id])->find();
                        } else {
                            $to = CurrencyUser::where(['chongzhi_url' => $to_address, "currency_id" => $currency_id])->find();
                        }
                        // 如果接收地址是本平台的钱包，直接处理数据(不走区块链)
                        if (!empty($to)) { // 内转

                            // 计算手续费
                            $hxy_user_id = Config::get_value('hxy_user_id', '');//账户
                            if ($hxy_user_id == $from_member_id) {
                                $internal_fee = 0;//内部提币，免手续费
                            } else {
                                $internal_fee = bcdiv(bcmul($currency->tcoin_fee, $money, 6), 100, 6);
                            }
                            $tibiObject->fee = $internal_fee;
                            $internal_totalMoney = bcadd($money, $internal_fee, 6);
                            $tibiObject->num = $internal_totalMoney;

                            $tibiObject->to_member_id = $to->member_id;
                            // 平台内互转需要审核
                            $tibiObject->status = 1;// deprecated 提币成功的状态
                            $tibiObject->ti_id = getNonceStr(50);
                            $tibiObject->check_time = time();
                            //如果两个钱包地址都是本平台生成的，则是平台内转帐,不走区块链,所以transfer_type=“2”
                            $tibiObject->transfer_type = "2";
                            // 添加提币记录成功 (deprecated) (搜索 deprecated 打开下面的注释，并且搜索 new 删除下面的代码，即可平台互转不需审核。)
                            // 通过后台审核 (new)
                            if ($tibiObject->save()) {
                                //提币添加账本
                                $accountBookCurrencyId = $tibiObject->currency_id;
                                $accountBookContent = "lan_sender";
                                $toAccountBookContent = "lan_receive";
                                if ($isERC20) {
                                    // ERC20 记录到 USDT的账本下
                                    $accountBookCurrencyId = Currency::USDT_ID;
//                                    $accountBookContent = "lan_ERC20_sender";
                                    // deprecated 标记
//                                    $toAccountBookContent = "lan_ERC20_receive";
                                }
//                                else {
//                                    $accountBookCurrencyId = $tibiObject->currency_id;
//                                    $accountBookContent = "lan_sender";
//                                    // deprecated 标记
//                                    $toAccountBookContent = "lan_receive";
//                                }

                                // 发送方账本
                                $result = model('AccountBook')->addLog([
                                    'member_id' => $tibiObject->from_member_id,
                                    'currency_id' => $accountBookCurrencyId,
                                    'type' => 6,
                                    'content' => $accountBookContent,
                                    'number_type' => 2,
                                    'number' => $tibiObject->num,
                                    'fee' => $tibiObject->fee,
                                    'address' => $to_address,
                                    'status' => 0,
                                    'third_id' => $tibiObject->id,
                                    'name' => !empty($tibiObject->names) ? $tibiObject->names : "",
                                ]);
                                if (!$result) {
                                    throw new Exception(lang("lan_operation_failure"));
                                }

                                //提币手续费日志
//                                if ($tibiObject->fee > 0) {
//                                    $fromFeeStream = CurrencyUserStream::addStream($tibiObject->from_member_id, $tibiObject->currency_id, 1, $tibiObject->fee, 2, 12, $tibiObject->id, "提币手续费");
//                                    if (!$fromFeeStream) {
//                                        throw new Exception();
//                                    }
//                                }
                                //提币扣除日志
//                                $fromNumStream = CurrencyUserStream::addStream($tibiObject->from_member_id, $tibiObject->currency_id, 1, $tibiObject->actual, 2, 11, $tibiObject->id, "提币扣除");
//                                if (!$fromNumStream) {
//                                    throw new Exception();
//                                }

                                // deprecated 标记
                                // 充币用户地址本里的备注名
                                $chongQianbaoAddress = QianbaoAddress::where(['user_id' => $tibiObject->to_member_id, 'currency_id' => $currency_id, 'qianbao_url' => $from_address])->field("names")->find();
                                // 充币添加账本
                                $addAccountBook = model('AccountBook')->addLog([
                                    'member_id' => $tibiObject->to_member_id,
                                    'currency_id' => $accountBookCurrencyId,
                                    'type' => 5,
                                    'content' => $toAccountBookContent,
                                    'number_type' => 1,
                                    'number' => $tibiObject->actual,
                                    'address' => $tibiObject->from_url,
                                    'third_id' => $tibiObject->id,
                                    'name' => isset($chongQianbaoAddress->names) ? $chongQianbaoAddress->names : "",
                                ]);
                                if (!$addAccountBook) {
                                    throw new Exception(lang("lan_operation_failure"));
                                }

                                //转帐方减少提币数量
                                if ($isERC20) {
                                    $from = CurrencyUser::getCurrencyUser($from->member_id, Currency::USDT_ID);
                                    // deprecated 标记
                                    $to = CurrencyUser::getCurrencyUser($to->member_id, Currency::USDT_ID);
                                }
//                                $from->num -= $totalMoney;
//                                $from->forzen_num += $totalMoney;

                                $from->num -= $tibiObject->num;

                                // TODO 内转不需要加到冻结账户 hl 2020-11-25
                                // $from->forzen_num += $tibiObject->num;

                                // new 标记
                                /*if (!$from->save()) {
                                    $r['code'] = ERROR4;
                                    throw new Exception(lang("lan_operation_failure"));
                                }*/

                                $r['code'] = SUCCESS;
                                $r['message'] = lang("lan_operation_success");
                                $r['result'] = $tibiObject->id;

                                // deprecated 标记
                                if ($from->save()) {
                                    //接收方增加实际到账数量
                                    $to->num += $tibiObject->actual;
                                    if ($to->save()) {
                                        $r['code'] = SUCCESS;
                                        $r['message'] = lang("lan_operation_success");
                                        $r['result'] = $tibiObject->id;
                                    } else {
                                        $r['code'] = ERROR2;
                                        throw new Exception(lang("lan_operation_failure"));
                                    }
                                } else {
                                    $r['code'] = ERROR3;
                                    throw new Exception(lang("lan_operation_failure"));
                                }

                            } else {
                                $r['code'] = ERROR4;
                                throw new Exception(lang("lan_operation_failure"));
                            }

                        } else {
                            $tibiObject->status = -1;//审核中的状态
                            //走区块链,所以transfer_type=“1”
                            $tibiObject->transfer_type = "1";
                            if ($tibiObject->save()) {
                                //提币添加账本
                                //提币添加账本
                                $accountBookCurrencyId = $tibiObject->currency_id;
                                $accountBookContent = "lan_sender";
                                if ($isERC20) {
                                    // ERC20 记录到 USDT的账本下
                                    $accountBookCurrencyId = Currency::USDT_ID;
//                                    $accountBookContent = "lan_ERC20_sender";
                                }
//                                else {
//                                    $accountBookCurrencyId = $tibiObject->currency_id;
//                                    $accountBookContent = "lan_sender";
//                                }
                                $result = model('AccountBook')->addLog([
                                    'member_id' => $tibiObject->from_member_id,
                                    'currency_id' => $accountBookCurrencyId,
                                    'type' => 6,
                                    'content' => $accountBookContent,
                                    'number_type' => 2,
                                    'number' => $tibiObject->num,
                                    'fee' => $tibiObject->fee,
                                    'address' => $to_address,
                                    'status' => 0,
                                    'third_id' => $tibiObject->id,
                                    'name' => !empty($tibiObject->names) ? $tibiObject->names : '',
                                ]);
                                if (!$result) {
                                    throw new Exception(lang("lan_operation_failure"));
                                }

                                //扣除资产日志
//                                $fromNumStream = CurrencyUserStream::addStream($tibiObject->from_member_id, $tibiObject->currency_id, 1, $money, 2, 11, $tibiObject->id, "提币扣除");
//                                if (!$fromNumStream) {
//                                    $r['code'] = ERROR5;
//                                    throw new Exception(lang("lan_operation_failure"));
//                                }
                                //增加冻结资产日志
                                // $fromForzenStream = CurrencyUserStream::addStream($tibiObject->from_member_id, $tibiObject->currency_id, 2, $money, 1, 11, $tibiObject->id, "提币冻结");
//                                if (!$fromForzenStream) {
//                                    $r['code'] = ERROR6;
//                                    throw new Exception(lang("lan_operation_failure"));
//                                }
                                //转帐方减少提币数量
                                if ($isERC20) {
                                    $from = CurrencyUser::getCurrencyUser($from->member_id, Currency::USDT_ID);
                                }
                                $from->num -= $totalMoney;
                                $from->forzen_num += $totalMoney;
                                if ($from->save()) {
                                    $r['code'] = SUCCESS;
                                    $r['message'] = lang("lan_operation_success");
                                    $r['result'] = $tibiObject->id;
                                } else {
                                    $r['code'] = ERROR7;
                                    throw new Exception(lang("lan_operation_failure"));
                                }

                            } else {
                                $r['code'] = ERROR8;
                                throw new Exception(lang("lan_operation_failure"));
                            }
                        }
                    } else {
                        $r['code'] = ERROR9;
                        $r['message'] = lang("lan_insufficient_balance");
                    }

                }
            }
            self::commit();
        } catch (Exception $exception) {
            $r['message'] = $exception->getMessage();
            self::rollback();
        }
        return $r;
    }

    /**
     * 获取用户的充提币记录
     * @param $member_id                    用户id
     * @param $currency_id                  币种id
     * @param int $page 页数(默认第1页)
     * @param int $rows 每页条目数(默认每页10条)
     * @param string $status 状态：all:全部状态；recharge：充币；take：提币的状态包括：0为提币中 1为提币成功  -1 审核中 -2 撤销
     * @return mixed
     * Created by Red.
     * Date: 2018/12/12 14:32
     */
    static function getTibiList($member_id, $currency_id, $page = 1, $rows = 10, $status = "all")
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        if (!empty($member_id) && !empty($currency_id)) {
            $field = "id,to_member_id,to_url,names,add_time,num,ti_id,status,currency_id,fee,actual,from_url,from_member_id,remark,tag";
            $select = self::where(["currency_id" => $currency_id, "b_type" => 0]);
            if ($status == "recharge") {
                $select->where(["status" => 3]);
            } elseif ($status == "take") {
                //提币的状态包括：0为提币中 1为提币成功  -1 审核中 -2 撤销
                $select->whereIn("status", [0, 1, -1, -2]);
            }
            $list = $select->where("to_member_id=" . $member_id . " or from_member_id=" . $member_id)->field($field)->page($page, $rows)->order("add_time desc")->select()->toArray();
            if (!empty($list)) {
                $statusList = ['-1' => lang("lan_in_audit"), '-2' => lang("lan_rescinded"), '1' => lang("lan_successful_coin"), '3' => lang("lan_charge_money_successfully")];
                $currencyList = Currency::field("currency_name,currency_id,currency_mark,currency_logo")->select()->toArray();
                $cList = array_column($currencyList, null, "currency_id");
                foreach ($list as &$value) {
                    $value['status_str'] = "";
                    if (in_array($value['status'], [3, 1])) {//转账成功的
                        $value['status_str'] = lang("lan_successful_transfer");
                    } elseif (in_array($value['status'], [0, -1])) {//审核中，区块确认中
                        $value['status_str'] = lang("lan_block_confirmation");
                    } else {
                        $value['status_str'] = lang("lan_transfer_failed");
                    }
                    $value['is_open'] = 1;//1有详情，2没有
                    $value['currency_logo'] = $cList[$value['currency_id']]['currency_logo'];
                    $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
                    $value['currency_name'] = $cList[$value['currency_id']]['currency_name'];
                    $value['names'] = empty($value['names']) ? "" : $value['names'];
                    $value['status_type'] = isset($statusList[$value['status']]) ? $statusList[$value['status']] : lang("lan_in_audit");
                    if ($value['from_member_id'] == $member_id) {
                        //转出去的
                        $value['types'] = "out";
                        $value['address'] = $value['from_url'];
                    } else {
                        //充进来的
                        $value['types'] = "in";
                        $value['address'] = $value['to_url'];
                    }
//                    unset($value['from_url']);
//                    unset($value['to_url']);
                    unset($value['from_member_id']);
                    unset($value['to_member_id']);
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("lan_data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("lan_not_data");
            }

        }
        return $r;
    }

    /**
     * 获取记录的总数
     * @param $member_id            用户id
     * @param $currency_id          币种id
     * @param string $status 状态：all:全部状态；recharge：充币；take：提币的状态包括：0为提币中 1为提币成功  -1 审核中 -2 撤销
     * @return int|string
     * @throws Exception
     * Created by Red.
     * Date: 2019/2/20 9:42
     */
    static function getTibiListCount($member_id, $currency_id, $status = "all")
    {
        if (!empty($member_id) && !empty($currency_id)) {
            $select = self::where(["currency_id" => $currency_id, "b_type" => 0]);
            if ($status == "recharge") {
                $select->where(["status" => 3]);
            } elseif ($status == "take") {
                //提币的状态包括：0为提币中 1为提币成功  -1 审核中 -2 撤销
                $select->whereIn("status", [0, 1, -1, -2]);
            }
            return $select->where("to_member_id=" . $member_id . " or from_member_id=" . $member_id)->count("id");

        }
        return 0;
    }

    //

    /**提币不让通过操作
     * @param int $id 表id
     * @param null|int $admin_id 管理id
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * Create by: Red
     * Date: 2019/8/27 16:15
     */
    static public function rebut($id, $admin_id = null)
    {
        Db::startTrans();
        $data = Db::name("tibi")->where(['id' => $id])->find();
        try {
            if (!empty($data)) {
                $currency_id = $data['currency_id'];
                if ($data['currency_id'] == Currency::ERC20_ID
                    || $data['currency_id'] == Currency::TRC20_ID
                ) {
                    //ERC20的是USDT的币数据
                    $currency_id = Currency::USDT_ID;
                }
                //退回可用的帐本记录
                $da['member_id'] = $data['from_member_id'];
                $da['currency_id'] = $currency_id;
                $da['type'] = 7;
                $da['content'] = "lan_admin_cancel_sender";
                $da['number_type'] = 1;
                $da['number'] = $data['num'];
                $da['third_id'] = $data['id'];
                $accountbook = model("AccountBook")->addLog($da);
                if (!$accountbook) {
                    throw new Exception(lang("lan_operation_failure"));
                }
                $data['check_time'] = time();
                $data['status'] = -2;
                $data['operation_admin_id'] = $admin_id;
                $res[] = Db::name("tibi")->where(['id' => $id])->update($data);

                // 现版本提币直接扣除不需要判断冻结数量
                $find = Db::name("Currency_user")->where(array('member_id' => $data['from_member_id'], 'currency_id' => $currency_id))->find();
                if (bccomp($find['forzen_num'], $data['num'], 8) < 0) {
                    throw new Exception("异常：冻结金额小于转账数据");
                }
                $res[] = Db::name("Currency_user")->where(array('member_id' => $data['from_member_id'], 'currency_id' => $currency_id))->setInc("num", $data['num']);
                $res[] = Db::name("Currency_user")->where(array('member_id' => $data['from_member_id'], 'currency_id' => $currency_id))->setDec("forzen_num", $data['num']);
                if (!in_array(false, $res)) {
                    Db::commit();
                    $arr['code'] = SUCCESS;
                    $arr['message'] = lang('lan_test_revocation_success');
                } else {
                    Db::rollback();
                    $arr['code'] = ERROR1;
                    $arr['message'] = lang('lan_safe_image_upload_failure');
                }
            } else {
                $arr['code'] = ERROR2;
                $arr['message'] = lang('lan_safe_image_upload_failure');
            }

        } catch (Exception $e) {
            Db::rollback();
            $arr['message'] = $e->getMessage();
        }
        return $arr;
    }

    /**
     * 操作提币通过（此操作是把币提到区块链上）
     * @param int $id tibi表id
     * @param null|int $admin_id 管理员id
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * Create by: Red
     * Date: 2019/8/27 17:54
     */
    static public function applyTransfer($id, $admin_id = null)
    {
        $r['code'] = ERROR1;
        $tibi = Db::name("tibi")->where(['id' => $id])->find();
        if ($tibi['status'] != -1) {
            $r['message'] = '不是审核状态,提币失败！';
            return $r;
        }
        $currency = Db::name("currency")->where(['currency_id' => $tibi['currency_id']])->find();
        if (!$currency) {
            $r['message'] = lang('lan_eror_integral_type');
            return $r;
        }
        try {
            Db::startTrans();
            if (1 == $tibi['transfer_type']) {
                $r = CurrencyTakeCoin::addTakeCoin($id);
                if ($r['code'] != SUCCESS) {
                    throw new Exception("提币审核添加失败");
                }

                // 添加推送数据
                $res = TakePush::addData($tibi['currency_id'], $tibi['from_member_id'], $tibi['from_url'], $tibi['to_url'], $tibi['actual'], $tibi['tag']);
                if (empty($res)) {
                    throw new Exception("系统错误，推送失败请稍后再试...");
                }
            }
            else { // 内转审核
                // 平台互转审核通过
                $isERC20 = ($tibi['currency_id'] == Currency::ERC20_ID
                    || $tibi['currency_id'] == Currency::TRC20_ID) ? true : false;
                if ($isERC20) {
                    $to = CurrencyUser::getCurrencyUser($tibi['to_member_id'], Currency::USDT_ID);
                    $from = CurrencyUser::getCurrencyUser($tibi['from_member_id'], Currency::USDT_ID);
                } else {
                    $to = CurrencyUser::getCurrencyUser($tibi['to_member_id'], $currency['currency_id']);
                    $from = CurrencyUser::getCurrencyUser($tibi['from_member_id'], $currency['currency_id']);

                }

                $accountBookCurrencyId = $tibi['currency_id'];
                $toAccountBookContent = "lan_receive";
                if ($isERC20) {
                    // ERC20 记录到 USDT的账本下
                    $accountBookCurrencyId = Currency::USDT_ID;
//                    $toAccountBookContent = "lan_ERC20_receive";
                }
//                else {
//                    $accountBookCurrencyId = $tibi['currency_id'];
//                    $toAccountBookContent = "lan_receive";
//                }

                $chongQianbaoAddress = QianbaoAddress::where(['user_id' => $tibi['to_member_id'], 'currency_id' => $tibi['currency_id'], 'qianbao_url' => $currency['recharge_address']])->field("names")->find();

                $addAccountBook = model('AccountBook')->addLog([
                    'member_id' => $tibi['to_member_id'],
                    'currency_id' => $accountBookCurrencyId,
                    'type' => 5,
                    'content' => $toAccountBookContent,
                    'number_type' => 1,
                    'number' => $tibi['actual'],
                    'address' => $tibi['from_url'],
                    'third_id' => $tibi['id'],
                    'name' => isset($chongQianbaoAddress->names) ? $chongQianbaoAddress->names : "",
                ]);
                if (!$addAccountBook) {
                    $r['code'] = ERROR3;
                    throw new Exception(lang("lan_operation_failure"));
                }
                $to->num += $tibi['actual'];
                if (!$to->save()) {
                    $r['code'] = ERROR3;
                    throw new Exception(lang("lan_operation_failure"));
                }
                $from->forzen_num -= $tibi['num']; // 扣除冻结
                if (!$from->save()) {
                    $r['code'] = ERROR3;
                    throw new Exception(lang('lan_operation_failure'));
                }

                $flag = (new Tibi)->where('id', $tibi['id'])->update([
                    'status' => 1,
                    'operation_admin_id' => $admin_id
                ]);
                if (!$flag) {
                    $r['code'] = ERROR3;
                    throw new  Exception(lang("lan_operation_failure"));
                }
                $r['message'] = "审核成功";
                $r['code'] = SUCCESS;
                $r['result'] = $tibi['id'];
            }
//            if($ctcResult['code']==SUCCESS){
//                $currency_type = strtolower($currency['currency_type']);
//                switch (true) {
//                    case in_array($currency_type, ['eth','eth_token']):
//                        $result = self::ethCoinTransfer($tibi, $currency);
//                        break;
//                    case in_array($currency_type, ['usdt']):
//                        $result = self::usdtCoinTransfer($tibi['to_url'], $tibi['actual'], $currency);
//                        break;
//
//                    case in_array($currency_type, ['btc']):
//                        $result = self::bitCoinTransfer($tibi['to_url'], $tibi['actual'], $currency);
//                        break;
//                    case in_array($currency_type, ['xrp']):
//                        $result = self::xrpCoinTransfer($tibi, $currency, $ctcResult['result']);
//                        break;
//                    case in_array($currency_type, ['eos']):
//                        $result = self::eosCoinTransfer($tibi, $currency);
//                        break;
//                }
//                if($result){
//                    if($result['code'] == SUCCESS){
//                        $rr=Db::name("tibi")->where(['id'=>$id])->update(['ti_id'=>$result['result'],'status'=>0,'operation_admin_id'=>$admin_id]);
//                        if(!empty($result['result'])){
//                            if($rr){
//                                $r['message']="操作成功，已进入待确认状态";
//                                $r['code']=SUCCESS;
//                               Db::name("CurrencyTakeCoin")->where(['id'=>$ctcResult['result']])->update(['txhash'=>$result['result']]);
//                            }else{
//                                $r['message']="操作成功，但保存交易编号失败，请去区块浏览器查询确认";
//                                $r['code']=SUCCESS;
//                            }
//                        }else{
//                            $r['message']="操作成功，但没有返回交易编号。";
//                        }
//                    }else{
//                        throw new  Exception(!empty($result['message'])?$result['message']:"请求提币服务器失败");
//                    }
//                }
//            }else{
//                throw new  Exception($ctcResult['message']);
//            }
            Db::commit();
        } catch (Exception $exception) {
            $r['message'] = $exception->getMessage();
            Db::rollback();
        }
        return $r;
    }

    /**
     * eth提币方法
     * @param array $tibi 提币信息
     * @param array $currency 币种配置信息
     */
    static protected function ethCoinTransfer($tibi, $currency)
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        $eth = new Eth($currency['rpc_url1'], $currency['port_number1']);
        $money = $tibi['actual'];
        if ($currency['currency_type'] == 'eth_token') {
            // 预估手续费
            $fees = $eth->token_getTxUseFee($currency['tibi_address'], $tibi['to_url'], $currency['token_address'], $money);
            $gas = $fees['result']['gas']['number'];
            $gasPrice = $fees['result']['gasPrice']['number'];
            // 账户余额
            $balance = $eth->token_getBalance($currency['tibi_address'], $currency['token_address']);
            $balance = $balance['result'];
            if ($balance < $money) {
                $r['message'] = "帐户余额不足，无法提币";
            } else {
                $result = $eth->token_sendTransaction($currency['tibi_address'], $tibi['to_url'], $currency['token_address'], $money, $gasPrice, $gas, $currency['qianbao_key1']);
                if ($result['code'] == SUCCESS) {
                    $ti_id = isset($result['result']['result']) ? $result['result']['result'] : null;
                    $r['result'] = $ti_id;
                    $r['message'] = "提币成功";
                    $r['code'] = SUCCESS;
                    return $r;
                } else {
                    $r['message'] = $result['message'];
                    return $r;
                }
            }
        } else {
            $balance = $eth->getBalance($currency['tibi_address']);
            if (isset($balance['eth'])) {
                $balance = $balance['eth'];
                $fees = $eth->eth_fees();
                if (SUCCESS != $fees['code']) {
                    return ['code' => ERROR2, 'message' => '获取转账手续费异常'];
                }
                $fee = $fees['result']['fees'];
                if ($balance < ($fee + $money)) {
                    $r['message'] = "帐户余额不足，无法提币";;
                } else {
                    $r = $eth->personal_sendTransaction($currency['tibi_address'], $tibi['to_url'], $money, $currency['qianbao_key1'], $fees["result"]["gasPrice"], $fees["result"]["gas"]);
                    return $r;
                }
            } else {
                $r['message'] = "获取账户的ETH余额失败";
            }
        }
        return $r;
    }


    /**
     * USDT提币方法
     * @param string $url 钱包地址
     * @param float $money 提币数量
     * @param array $currency 币种配置信息
     */
    static protected function usdtCoinTransfer($url, $money, $currency)
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        $param['rpc_user'] = $currency['rpc_user1'];
        $param['rpc_pwd'] = $currency['rpc_pwd1'];
        $param['rpc_url'] = $currency['rpc_url1'];
        $param['port_number'] = $currency['port_number1'];
        $passwd = $currency['qianbao_key1'];
        $bitcoin = new Btc();
        $balance = $bitcoin->omni_getbalance($currency['tibi_address'], $param);
        if ($balance < $money) {
            $r['message'] = "帐户余额不足，无法提币";
            return $r;
        }
        $id = $bitcoin->omni_funded_send($currency['tibi_address'], $passwd, $url, $money, $currency['summary_fee_address'], $param);
        if ($id['code'] == SUCCESS) {
            $r['code'] = SUCCESS;
            $r['message'] = "提币操作成功";
            $r['result'] = $id['result'];
        } else {
            $r['message'] = $id['message'];
        }
        return $r;
    }

    /**
     * BTC提币的方法
     * @param string $url 转出的钱包地址
     * @param float $money 提币数量
     * @param array $currency 币种配置信息
     */
    static protected function bitCoinTransfer($url, $money, $currency)
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        $param['rpc_user'] = $currency['rpc_user1'];
        $param['rpc_pwd'] = $currency['rpc_pwd1'];
        $param['rpc_url'] = $currency['rpc_url1'];
        $param['port_number'] = $currency['port_number1'];
        $passwd = $currency['qianbao_key1'];
        $bitcoin = new Btc();
        $balance = $bitcoin->get_qianbao_balance($param);
        if ($balance < $money) {
            $r['message'] = "帐户余额不足，无法提币";
            return $r;
        }
        $id = $bitcoin->btc_transfer($passwd, $url, $currency['tibi_address'], $money, $param);
        if ($id) {
            $r['code'] = SUCCESS;
            $r['message'] = "提币操作成功";
            $r['result'] = $id;
        } else {
            $r['message'] = isset($bitcoin->error) ? $bitcoin->error : "转账出错";
        }
        return $r;
    }

    /**
     * xrp提币方法
     * @param array $tibi 提币信息
     * @param array $currency 币种配置信息
     * @param int $take_id currency_take_coin 表的id
     */
    static protected function xrpCoinTransfer($tibi, $currency, $take_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        if (!empty($tibi) && !empty($currency) && !empty($take_id)) {
            $xrp = new Xrp();
            $balance = $xrp->getBalance($currency['tibi_address']);
            $money = $tibi['actual'];//提币实际到账的数量
            if (($balance == 0 || $balance == '0') || ($balance > 0 && bccomp(($balance - 25), $money, 6) >= 0)) {
                $url = "http://" . $currency['rpc_url1'] . ":" . $currency['port_number1'];
                $result = $xrp->sendTrans($url, $money, $currency['tibi_address'], $currency['qianbao_key1'], $tibi['to_url'], $tibi['tag'], $take_id);
                return $result;
            } else {
                $r['message'] = "帐户余额不足，无法提币";
            }
        }
        return $r;

    }

    /**
     * eos提币方法
     * @param array $tibi 提币信息
     * @param array $currency 币种配置信息
     * @param int $take_id currency_take_coin 表的id
     */
    protected function eosCoinTransfer($tibi, $currency)
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        if (!empty($tibi) && !empty($currency)) {
            $eos = new Eos($currency['rpc_url1'], $currency['port_number1']);
            $balance = $eos->getbalance($currency['tibi_address']);
            $money = $tibi['actual'];//提币实际到账的数量
            if ($balance > 0 && bccomp($balance, $money, 6) >= 0) {
                $result = $eos->transfer($currency['tibi_address'], $tibi['to_url'], $money, $tibi['tag']);
                return $result;
            } else {
                $r['message'] = "帐户余额不足，无法提币";
            }
        }
        return $r;

    }

    /**
     * @return \think\model\relation\BelongsTo
     */
    public function fromUser()
    {
        return $this->belongsTo('app\\common\\model\\Member', 'from_member_id', 'member_id');
    }

    /**
     * @return \think\model\relation\BelongsTo
     */
    public function toUser()
    {
        return $this->belongsTo('app\\common\\model\\Member', 'to_member_id', 'member_id');
    }

    /**
     * @return \think\model\relation\BelongsTo
     */
    public function currency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id');
    }

    /**
     * @return \think\model\relation\BelongsTo
     */
    public function feeCurrency()
    {
        return $this->belongsTo('app\\common\\model\Currency', 'to_currency_id');
    }
}
