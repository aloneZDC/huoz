<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/28
 * Time: 18:30
 */

namespace app\common\model;


use think\Db;
use Think\Exception;

class CurrencyTakeCoin extends Base
{
    protected $resultSetType = 'collection';

    /**
     * 待确认中数据，审核操作
     * @param $ctc_id           审核id
     * @param $status           审核状态:   status=2成功; status=3失败,status=4重新审核;
     * @param null $txhash 交易编号
     * @param null $endtime 成功或者失败时间
     * @return  array
     * @throws Exception
     */
    public static function updateTakeCoinStatus($ctc_id, $status, $txhash = null, $endtime = null, $fee = null, $admin_id = null)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        self::startTrans();
        try {
            $txhash = strval($txhash);
            if (is_numeric($ctc_id) && is_numeric($status)) {
                $takeCoin = self::where(['id' => $ctc_id])->find();
                if (!empty($takeCoin) && $takeCoin->status != 2) {
                    $sta = $takeCoin->status;
                    if (!empty($txhash)) $takeCoin->txhash = $txhash;
                    if (!empty($endtime)) $takeCoin->endtime = $endtime;
                    //管理操作的id
                    if (!empty($admin_id)) $takeCoin->admin_id = $admin_id;
                    $takeCoin->status = $status;
                    if ($takeCoin->save()) {
                        //成功状态，要把交易表的状态改成一致
                        if ($status == 2) {
                            $check = Tibi::checkTransfer($takeCoin->tibi_id, 1, $takeCoin->txhash, $takeCoin->endtime, $fee);
                            if ($check['code'] == SUCCESS) {
                                $r = $check;
                            } else {
                                throw new \Exception($check['message']);
                            }
                        } elseif ($status == 3) {
                            //失败状态，要把交易表的状态改成一致
                            $check = Tibi::checkTransfer($takeCoin->tibi_id, -2, $takeCoin->txhash, $takeCoin->endtime, $fee);
                            if ($check['code'] == SUCCESS) {
                                $r = $check;
                            } else {
                                throw new \Exception($check['message']);
                            }
                        } else if ($status == 4) {
                            //重新进入待审核
                            $check = Tibi::checkTransfer($takeCoin->tibi_id, -1);
                            if ($check['code'] == SUCCESS) {
                                $r = $check;
                            } else {
                                throw new \Exception($check['message']);
                            }
                        }
                    } else {
                        throw new \Exception("保存数据异常");
                    }
                }
                self::commit();
            }
        } catch (\Exception $exception) {
            self::rollback();
            $r['message'] = $exception->getMessage();
        }
        return $r;

    }

    /**
     * 更新交易哈希和手续费
     * @param $id           表id
     * @param $txhash       区块链的交易哈希
     * @param null $fee 区块链上的手续费
     * @return mixed
     * Created by Red.
     * Date: 2019/2/13 17:15
     */
    static function updateTakeCoin($id, $txhash)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        if (!empty($id) && !empty($txhash)) {
            $take = self::where(['id' => $id])->find();
            if (!empty($take)) {
                //如果已存在交易或者已状态不是待确认的状态，则不修改
                if (!empty($take->txhash) || $take->status != 1) {
                    $r['code'] = SUCCESS;
                    $r['message'] = lang("lan_operation_success");
                } else {
                    $take->txhash = $txhash;
                    $update = $take->save();
                    $r['code'] = $update ? SUCCESS : ERROR2;
                    $r['message'] = $update ? lang("lan_operation_success") : lang("lan_operation_failure");
                }
            }
        }
        return $r;
    }

    /*
        * 提币审核添加一条提币数据
        * @param $tibi_id        转账id(Tibi 的表id)
        * @return mixed
        * @throws Exception
        */
    static function addTakeCoin($tibi_id, $txhash = null)
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        try {
            $txhash = strval($txhash);
            if (is_numeric($tibi_id)) {
                //转账记录表数据
                $tibiD = Db::name("Tibi");
                $tibi = $tibiD->where(['id' => $tibi_id])->find();
                if (!empty($tibi)) {
                    $currencyD = Db::name("Currency");
                    $currency = $currencyD->where(['currency_id' => $tibi['currency_id']])->find();
                    if (!empty($currency)) {
                        //修改交易记录为待确认状态
                        $tibi['status'] = 0;
                        if (!empty($txhash)) {
                            $tibi['ti_id'] = $txhash;
                        }
                        if ($tibiD->where(['id' => $tibi_id])->update($tibi)) {
                            $CurrencyTakeCoinD = Db::name("CurrencyTakeCoin");
                            $takecoin['tibi_id'] = $tibi_id;
                            $takecoin['from_address'] = $currency['tibi_address'];
                            $takecoin['to_address'] = $tibi['to_url'];
                            $takecoin['status'] = 1;
                            $takecoin['money'] = $tibi['actual'];
                            $takecoin['currency_id'] = $tibi['currency_id'];
                            $takecoin['starttime'] = time();
                            if (!empty($txhash)) {
                                $takecoin['txhash'] = $txhash;
                            }
                            $ctc_id = $CurrencyTakeCoinD->insertGetId($takecoin);
                            if ($ctc_id) {
                                $r['message'] = "进入待确认状态中";
                                $r['code'] = SUCCESS;
                                $r['result'] = $ctc_id;
                            } else {
                                throw new Exception("添加记录失败");
                            }
                        } else {
                            throw new Exception("修改状态失败");
                        }
                    } else {
                        $r['message'] = "数据有误";
                    }
                } else {
                    $r['message'] = "转账记录数据有误";
                }
            }
        } catch (Exception $exception) {
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }
}