<?php


namespace app\common\model;


use think\Db;
use think\Exception;

class Summary extends Base
{
    /**
     * 添加一条汇总数据
     * @param $address
     * @param $summary_address
     * @param $money
     * @param $currency_id
     * @param $status
     * @param int $uid
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Create by: Red
     * Date: 2019/8/24 18:12
     */
    static public function addSummary($address, $summary_address, $money, $currency_id, $status, $uid = 0)
    {
        if (!empty($address) && !empty($money) && $money > 0 && $currency_id > 0 && !empty($status) && $uid > 0) {
            $find = Db::name("Summary")->field("from_user_id")->where(['from_user_id' => $uid, 'currency_id' => $currency_id, 'status' => 1])->find();
            if (!empty($find)) {
                return ['code' => ERROR1, 'message' => '该用户已有一条待确认汇总数据，请先处理后再来操作'];
            }
            $data = [
                'from_address' => $address,
                'to_address' => $summary_address,
                'money' => $money,
                'status' => $status,
                'starttime' => time(),
                'currency_id' => $currency_id,
                'from_user_id' => $uid,
            ];
            $ins=Db::name("Summary")->insertGetId($data);
            if ($ins) {
                $currency_user=CurrencyUser::where(array('member_id' => $uid, 'currency_id' => $currency_id))->find();
                $real_num=$currency_user->real_num;
                $currency_user->real_num=$real_num>=0?$real_num:0;
                $currency_user->save();
                return ['code' => SUCCESS, 'result' => $ins, 'message' => '已进入待确认汇总'];
            } else {
                return ['code' => ERROR1, 'result' => $ins, 'message' => '保存汇总数据错误'];
            }
        } else {
            return ['code' => ERROR1, 'result' => [], 'message' => '参数错误'];
        }
    }


    /**
     * 操作汇总数据状态
     * @param $wsid                 汇总表id
     * @param $status               状态:status=2表示成功；status=3拒绝，不进行其它操作
     * @param null $txhash 交易编号
     * @param null $fees 手续费
     * @param null $to_address 汇总地址
     * @return mixed
     * Created by Red.
     * Date: 2018/10/20 10:54
     */
   static public function updateSummaryStatus($wsid, $status, $txhash = null, $fees = 0,$to_address=null)
    {

        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
       Db::startTrans();
        try {
            if (is_numeric($wsid) && is_numeric($status)) {
                if ($status == 2 && empty($txhash)) {
                    throw new Exception("汇总成功需要交易编号");
                }
                if ($status == 2 && empty($to_address)) {
                    throw new Exception("汇总成功需要汇总地址");
                }
                $summaryDB=Db::name("summary");
                $summary = $summaryDB->where(['id' => $wsid])->find();
                //只能操作待确认状态的数据
                if (!empty($summary) && $summary['status'] == 1) {
                    $summary['endtime'] = time();
                    if ($status == 2) {
                        $summary['status'] = $status;
                        $summary['txhash'] = $txhash;
                        $summary['fees'] = $fees;
                        $summary['to_address'] = $to_address;
                        if ($summaryDB->where(['id'=>$wsid])->update($summary)) {
                            //添加每日的汇总数据
                            $wesResult=WalletEverydaySummary::addWalletEverydaySummary($summary['currency_id'],$summary['money'],$summary['endtime']);
                            if(!$wesResult){
                                throw new Exception("添加每日汇总数据异常");
                            }
                            $r['code'] = SUCCESS;
                            $r['message'] = "汇总操作成功";
//                            $currency = Db::name("currency")->field("currency_mark")->where(['currency_id' => $summary['currency_id']])->find();
                            //BTC、XRP的汇总不操作用户帐号数据
//                            if (!empty($currency) && (in_array($currency['currency_mark'],['XRP','EOS']))) {
//                                Db::commit();
//                                $r['code'] = SUCCESS;
//                                $r['message'] = "汇总操作成功";
//                                return $r;
//
//                            }
//                            $ucD = Db::name("currency_user");
//                            $userCurrencyResult = $ucD->where(['member_id' => $summary['from_user_id'], 'currency_id' => $summary['currency_id']])->find();
//                            if (!empty($userCurrencyResult)) {
//                                if ($userCurrencyResult['real_num'] <= 0) {
//                                    $r['code'] = SUCCESS;
//                                    $r['message'] = "汇总操作成功";
//                                } else {
//                                    $userCurrencyResult['real_num'] -= $summary['money'];
//                                    if ($userCurrencyResult['real_num'] < 0) $userCurrencyResult['real_num'] = 0;
//                                    if ($ucD->where(['member_id' => $summary['from_user_id'], 'currency_id' => $summary['currency_id']])->update($userCurrencyResult)) {
//                                        $r['code'] = SUCCESS;
//                                        $r['message'] = "汇总操作成功";
//                                    } else {
//                                        throw new Exception("汇总操作失败");
//                                    }
//                                }
//                            } else {
//                                throw new Exception("用户数据有误");
//                            }
                        } else {
                            throw new Exception("汇总操作失败");
                        }
                    } elseif ($status == 3) {
                        $summary['status'] = $status;
                        if ($summaryDB->where(['id'=>$wsid])->update($summary)) {
                            $currency = Db::name("currency")->field("currency_mark")->where(['currency_id' => $summary['currency_id']])->find();
                            //BTC、XRP的汇总不操作用户帐号数据
                            if (!empty($currency) && ($currency['currency_mark'] == "BTC"||$currency['currency_mark'] == "XRP")) {
                                Db::commit();
                                $r['code'] = SUCCESS;
                                $r['message'] = "操作成功";
                                return $r;

                            }
                            $ucD = Db::name("currency_user");
                            $userCurrencyResult = $ucD->where(['member_id' => $summary['from_user_id'], 'currency_id' => $summary['currency_id']])->find();
                            if (!empty($userCurrencyResult)) {
                                $userCurrencyResult['real_num'] += $summary['money'];
                                if ($ucD->where(['member_id' => $summary['from_user_id'], 'currency_id' => $summary['currency_id']])->update($userCurrencyResult)) {
                                    $r['code'] = SUCCESS;
                                    $r['message'] = "汇总失败成功，数据已返回等汇总";
                                } else {
                                    $r['message'] = "汇总操作成功，但返回待汇总数据错误";
                                }
                            } else {
                                throw new Exception("用户数据有误");
                            }
                            $r['message'] = "操作成功";
                            $r['code'] = SUCCESS;
                        } else {
                            throw new Exception("汇总拒绝操作失败");
                        }
                    }
                }
                Db::commit();
            }
        } catch (Exception $exception) {
            $r['message'] = $exception->getMessage();
            Db::rollback();
        }
        return $r;

    }
}