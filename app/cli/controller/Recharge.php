<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/14
 * Time: 19:48
 */

namespace app\cli\controller;


use app\common\model\CurrencyTakeCoin;
use app\common\model\CurrencyUser;
use app\common\model\Member;
use app\common\model\TakePush;
use app\common\model\Tibi;
use app\common\model\WalletAdminAddress;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\common\model\CurrencyLog;
use app\common\model\Currency;
use think\Db;
use think\Exception;
use think\Log;
use think\Request;

class Recharge extends Command
{
    protected function configure()
    {
        $this->setName('Recharge')->setDescription('This is a test');
    }

//    protected function execute(Input $input, Output $output)
//    {
//        $this->recharge();
//    }
    protected function execute(Input $input, Output $output)
    {
        Request::instance()->module('cli');
        $this->recharge();
    }

    /**
     * 处理自动充币
     * Created by Red.
     * Date: 2018/12/14 20:22
     */
    public function recharge()
    {
        $list = CurrencyLog::where(["status" => 0])->page(0, 1000)->select();
        //查询是否有未处理的数据
        if (!empty($list) && !$list->isEmpty()) {
            $list = $list->toArray();
            foreach ($list as $k => $value) {
                $curr = null;
                $currencyLog = new CurrencyLog();
                try {
                    $currencyLog->startTrans();
                    $mark = "未知";
                    $sureTime = time();//确认时间
                    $jsonData = json_decode($value['trans'], true);
                    if ($value['types'] == 1) {
                        $mark = "BTC";
                        $sureTime = isset($jsonData['time']) ? $jsonData['time'] : $sureTime;
                    } // USDT母币
                    elseif ($value['types'] == 2) {
                        $mark = "USDT";
                        $sureTime = isset($jsonData['blocktime']) ? $jsonData['blocktime'] : $sureTime;
                        //如果propertyid不是31，则跳过不处理该币
                        if (!isset($jsonData['propertyid']) || $jsonData['propertyid'] != 31 || $jsonData['valid'] != true) {
                            $clResult = CurrencyLog::where(['tx' => $value['tx']])->update(['status' => 4, 'update_time' => time()]);
                            if (!$clResult) {
                                Log::write('交易哈希：' . $value['tx'] . "修改状态为：4,时失败", 'INFO');
                                throw new Exception();
                            }
                            $currencyLog->commit();
                            continue;
                        }
                    } elseif ($value['types'] == 3) {
                        //有token是ETH的代币
                        if (isset($jsonData['token'])) {
                            //根据合约地址查询是哪个代币
                            $curr = Currency::where(['token_address' => $jsonData['token']])->field("currency_id,currency_type,currency_mark,tibi_address,recharge_address")->find();
                            if (!empty($curr)) {
                                $mark = $curr->currency_mark;
                            } else {
                                $clResult = CurrencyLog::where(['tx' => $value['tx']])->update(['status' => 4, 'update_time' => time()]);
                                if (!$clResult) {
                                    Log::write('交易哈希：' . $value['tx'] . "修改状态为：4,时失败", 'INFO');
                                    throw new Exception();
                                }
                                $currencyLog->commit();
                                continue;
                            }
                        } else {
                            $mark = "ETH";
                        }

                    } elseif ($value['types'] == 4) {
                        $mark = "XRP";
                    } elseif ($value['types'] == 5) {
                        $mark = "EOS";
                    } elseif ($value['types'] == 8) {
                        $mark = Currency::PUBLIC_CHAIN_NAME;
                        if (isset($jsonData['to'])) {
                            $public_chain_recharge = Currency::where(['recharge_address' => $jsonData['to']])->find();
                            if ($public_chain_recharge && $jsonData['to'] == $public_chain_recharge['recharge_address']) {
                                $mark = $public_chain_recharge['currency_mark'];
                            }
                        }
                    } // USDT充值
                    elseif ($value['types'] == 9) {
                        //有token是TRX的代币
                        if (isset($jsonData['token'])) {
                            //根据合约地址查询是哪个代币
                            $curr = Currency::where(['token_address' => $jsonData['token']])->field("currency_id,currency_type,currency_mark,tibi_address,recharge_address")->find();
                            if (!empty($curr)) {
                                $mark = $curr->currency_mark;
                            } else {
                                $clResult = CurrencyLog::where(['tx' => $value['tx']])->update(['status' => 4, 'update_time' => time()]);
                                if (!$clResult) {
                                    Log::write('交易哈希：' . $value['tx'] . "修改状态为：4,时失败", 'INFO');
                                    throw new Exception();
                                }
                                $currencyLog->commit();
                                continue;
                            }
                        } else {
                            $mark = "TRX";
                        }
                    } // FIL充值
                    elseif ($value['types'] == 10) {
                        // 低于0.1枚不到账
                        if (!isset($value['amount']) || $value['amount'] < 0.1) {
                            $clResult = CurrencyLog::where(['tx' => $value['tx']])->update(['status' => 5, 'update_time' => time()]);
                            if (!$clResult) {
                                Log::write('交易哈希：' . $value['tx'] . "修改状态为：5,时失败", 'INFO');
                                throw new Exception();
                            }
                            $currencyLog->commit();
                            continue;
                        }
                        $mark = "FIL";
                    }
                    // bnb币安链
                    elseif ($value['types'] == 12) {
                        //有token是TRX的代币
                        if (isset($jsonData['token'])) {
                            //根据合约地址查询是哪个代币
                            $curr = Currency::where(['token_address' => $jsonData['token']])
                                ->field("currency_id,currency_type,currency_mark,tibi_address,recharge_address")->find();
                            if (!empty($curr)) {
                                $mark = $curr->currency_mark;
                            } else {
                                $clResult = CurrencyLog::where(['tx' => $value['tx']])->update(['status' => 4, 'update_time' => time()]);
                                if (!$clResult) {
                                    Log::write('交易哈希：' . $value['tx'] . "修改状态为：4,时失败", 'INFO');
                                    throw new Exception();
                                }
                                $currencyLog->commit();
                                continue;
                            }
                        } else {
                            $mark = "BNB";
                        }
                    }

                    if (!isset($curr->currency_id)) {
                        $curr = Currency::where(['currency_mark' => $mark])->field("currency_id,currency_type,tibi_address,currency_mark,recharge_address")->find();
                    }
                    if (!empty($curr)) {
                        $currencyUser = null;
                        //用户信息
                        if ($mark == "XRP") {
                            //如果是本平台地址 充币转充币，提币  提币转充币,提币都不能处理
                            $isAdminAddress = WalletAdminAddress::checkIsAddress($curr->currency_id, $jsonData['src_address']);
                            if ($isAdminAddress) {
//                            if ($curr['tibi_address'] == $jsonData['src_address']) {
                                $clResult = CurrencyLog::where(['tx' => $value['tx']])->update(['status' => 5, 'update_time' => time()]); // 异常数据
                                if (!$clResult) {
                                    Log::write("交易哈希：" . $value['tx'] . "修改状态为：5,时失败", "INFO");
                                    throw new Exception();
                                }
                                $currencyLog->commit();
                                continue;
                            }

                            //xrp以des_tag定位用户id
                            if (isset($jsonData['des_tag'])) {
                                //充币要为统一充币地址
                                $isAdminRechargeAddress = WalletAdminAddress::checkIsRecharge($curr->currency_id, $jsonData['des_address']);
                                if ($isAdminRechargeAddress && $jsonData['currency'] == "XRP") {
//                                if($curr->recharge_address==$jsonData['des_address']&&$jsonData['currency']=="XRP"){
                                    $currencyUser = CurrencyUser::getCurrencyUser($jsonData['des_tag'], $curr->currency_id);
                                }
                            }
                        } elseif ($mark == "EOS") {
                            //eos以memo定位用户id
                            if (isset($jsonData['memo']) && is_numeric($jsonData['memo'])) {
                                //充币要为统一充币地址
                                if ($curr->recharge_address == $jsonData['to']) {
                                    $currencyUser = CurrencyUser::getCurrencyUser($jsonData['memo'], $curr->currency_id);
                                }
                            }
                        } elseif ($mark == "DNC") {
                            // dnc 充币地址和 XRP逻辑一样 用户ID 通过 memo 定位
                            $isAdminAddress = WalletAdminAddress::checkIsAddress($curr->currency_id, $jsonData['from']);
                            if ($isAdminAddress) {
                                $clResult = CurrencyLog::where(['tx' => $value['tx']])->update(['status' => 5, 'update_time' => time()]); // 异常数据
                                if (!$clResult) {
                                    Log::write("交易哈希：" . $value['tx'] . "修改状态为：5,时失败", "INFO");
                                    throw new Exception();
                                }
                                $currencyLog->commit();
                                continue;
                            }

                            if (isset($jsonData['memo'])) {
                                $isAdminRechargeAddress = WalletAdminAddress::checkIsRecharge($curr->currency_id, $jsonData['to']);
                                if ($isAdminRechargeAddress) {
                                    $currencyUser = CurrencyUser::getCurrencyUser($jsonData['memo'], $curr->currency_id);
                                }
                            }
                        } elseif ($curr->currency_type == Currency::PUBLIC_CHAIN_NAME) {
                            if (isset($jsonData['creator'])) {
                                //激活网体
                                $pid_name = $jsonData['creator'];
                                $child_name = isset($jsonData['name']) ? $jsonData['name'] : '';
                                $child_public_address = '';
                                if (isset($jsonData['owner']) && isset($jsonData['owner']['keys']) && isset($jsonData['owner']['keys'][0]) && isset($jsonData['owner']['keys'][0]['key'])) {
                                    $child_public_address = $jsonData['owner']['keys'][0]['key'];
                                }

                                $create_result = (new Member())->public_chain_reg($child_name, $child_public_address, $pid_name);
                                if ($create_result['code'] == SUCCESS) {
                                    $clResult = CurrencyLog::where(['tx' => $value['tx']])->update(['status' => 3, 'update_time' => time()]); // 异常数据
                                    if (!$clResult) {
                                        Log::write("交易哈希：" . $value['tx'] . "修改状态为：3,时失败", "INFO");
                                        throw new Exception();
                                    }
                                } else {
                                    //注册失败直接rollback 并标记处理失败
                                    $currencyLog->rollback();

                                    $clResult = CurrencyLog::where(['tx' => $value['tx']])->update(['status' => 2, 'update_time' => time(), 'message' => $create_result['message']]); // 异常数据
                                    if (!$clResult) {
                                        Log::write("交易哈希：" . $value['tx'] . "修改状态为：2,时失败" . $create_result['message'], "INFO");
                                    }
                                    continue;
                                }
                                $currencyLog->commit();
                                continue;
                            } elseif (!empty($jsonData['to'])) {
                                $publicchain_recharge = Currency::where(['recharge_address' => $jsonData['from']])->find();
                                if (empty($publicchain_recharge)) {
                                    //公链 钱包地址为用户名 根据转账地址查找对应用户
                                    $member_id = Member::where(['ename' => $jsonData['from']])->value('member_id');
                                    if ($member_id) {
                                        $currencyUser = CurrencyUser::getCurrencyUser($member_id, $curr->currency_id);
                                    }
                                }
                            }
                        } // MTK - TRC20
                        elseif ($mark == "MTK") {
                            $mtk_user = CurrencyUser::where(['chongzhi_url' => $value['ato']])->find();
                            $currencyUser = CurrencyUser::where(['member_id' => $mtk_user['member_id'], 'currency_id' => $curr->currency_id])->find();
                        }
                        // bnb币安链
                        elseif (in_array($mark, ['YLP', 'LLP'])) {
                            $mtk_user = CurrencyUser::where(['chongzhi_url' => $value['ato']])->find();
                            $currencyUser = CurrencyUser::where(['member_id' => $mtk_user['member_id'], 'currency_id' => $curr->currency_id])->find();
                        }
                        else {
                            $currencyUser = CurrencyUser::where(['chongzhi_url' => $value['ato'], 'currency_id' => $curr->currency_id])->find();
                        }

                        //地址是否属于平台内
                        if (!empty($currencyUser)) {
                            $clResult = CurrencyLog::where(['tx' => $value['tx']])->update(['status' => 3, 'update_time' => time()]);
                            if ($clResult) {
                                //添加充币记录,并且增加币数量
                                if ($mark == "XRP") {
                                    $result = Tibi::rechargeTibiAndMoneyAdd($jsonData['src_address'], $jsonData['des_address'], $value['amount'], strtolower($value['tx']), $sureTime, $curr->currency_id, $sureTime, $jsonData['des_tag']);
                                } elseif ($mark == "EOS" or $mark == "DNC") { // DNC json 数据 和 EOS相同
                                    $result = Tibi::rechargeTibiAndMoneyAdd($jsonData['from'], $jsonData['to'], $value['amount'], $value['tx'], $sureTime, $curr->currency_id, $sureTime, $jsonData['memo']);
                                } elseif ($curr->currency_type == Currency::PUBLIC_CHAIN_NAME) {
                                    $result = Tibi::rechargeTibiAndMoneyAdd($jsonData['from'], $jsonData['to'], $value['amount'], $value['tx'], $sureTime, $curr->currency_id, $sureTime);
                                } else {
                                    $result = Tibi::rechargeTibiAndMoneyAdd($value['afrom'], $value['ato'], $value['amount'], $value['tx'], $sureTime, $curr->currency_id, $sureTime);
                                }
                                if ($result['code'] == SUCCESS) {
                                    $member = Db::name("member")->where(['member_id' => $currencyUser->member_id])->field("email,phone,country_code")->find();
                                    if (!empty($member)) {
                                        if (!empty($member['phone'])) {
                                            model('sender')->addLog(1, $member['phone'], $member['country_code'], 'chongbi', ['code' => $mark . "::" . $value['amount']]);
                                        } else if (!empty($member['email'])) {
                                            model('sender')->addLog(2, $member['email'], $member['country_code'], 'chongbi', ['code' => $mark . "::" . $value['amount']]);
                                        }
                                    }
                                    Log::write("充币处理成功：" . date("Y-m-d H:i:s", time()));
                                } else {
                                    Log::write("添加记录失败，原因是：" . $result['message'], 'INFO');
                                    throw new Exception();
                                }
                            } else {
                                Log::write('交易哈希：' . $value['tx'] . "修改状态为：3,时失败", 'INFO');
                                throw new Exception();
                            }
                        } else {
                            //TODO 暂时提币后处理关闭
                            //处理如果是提币待确认的数据,XRP和BTC的数据不在这里处理
                            if (!in_array($mark, ["XRP"])) {
                                $currencyTakeCoin = CurrencyTakeCoin::where(["to_address" => $value['ato'], "status" => 1, "currency_id" => $curr->currency_id, "money" => floatval($value['amount'])])
                                    ->where("(txhash is null or txhash='')")->find();
                                if (!empty($currencyTakeCoin)) {
                                    $clResult = CurrencyLog::where(['tx' => $value['tx']])->update(['status' => 3, 'update_time' => time()]);
                                    if (!$clResult) {
                                        Log::write('交易哈希：' . $value['tx'] . "修改状态为：3,时失败", 'INFO');
                                        throw new Exception();
                                    }
                                    //自动处理提币后的操作
//                                    $result=CurrencyTakeCoin::updateTakeCoinStatus($currencyTakeCoin->id,2,$value['tx'],$sureTime);
                                    //处理提币后只补全currency_take_coin表的txhash字段
                                    $result = CurrencyTakeCoin::updateTakeCoin($currencyTakeCoin->id, $value['tx']);
                                    if ($result['code'] == SUCCESS) {
                                        Log::write("提币处理成功：" . date("Y-m-d H:i:s", time()));
                                    } else {
                                        Log::write("提币处理失败，原因是：" . $result['message'], 'INFO');
                                        throw new Exception();
                                    }
                                } else {
                                    $clResult = CurrencyLog::where(['tx' => $value['tx']])->update(['status' => 4, 'update_time' => time()]);
                                    if (!$clResult) {
                                        Log::write('交易哈希：' . $value['tx'] . "修改状态为：4,时失败", 'INFO');
                                    }
                                }
                            } else {
                                $clResult = CurrencyLog::where(['tx' => $value['tx']])->update(['status' => 4, 'update_time' => time()]);
                                if (!$clResult) {
                                    Log::write('交易哈希：' . $value['tx'] . "修改状态为：4,时失败", 'INFO');
                                }
                            }
                        }

                    } else {
                        Log::write("查询币种信息失败", 'INFO');
                        $clResult = CurrencyLog::where(['tx' => $value['tx']])->update(['status' => 2, 'update_time' => time()]);
                        if (!$clResult) {
                            Log::write('交易哈希：' . $value['tx'] . "修改状态为：2,时失败", 'INFO');
                        }
                    }
                    $currencyLog->commit();
                } catch (Exception $exception) {
                    $currencyLog->rollback();
                }
            }

        } else {
            sleep(2);
        }

        Log::write('币自动到帐处理完成时间' . date("Y-m-d H:i:s"), 'INFO');

    }
}
