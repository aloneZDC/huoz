<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/24
 * Time: 14:44
 */

namespace app\common\model;


use think\Db;
use think\Exception;
use think\Log;

class MoneyInterestDaily extends Base
{
    /**
     * 根据用户存入的持币生息数据插入当天用户的利息生成记录数据
     * @param int $page
     * @param int $rows
     * @return mixed
     * Created by Red.
     * Date: 2018/12/24 18:04
     */
    static function addMoneyInterestDaily($page = 1, $rows = 1000)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        //获取还没生息的记录（当天存的不生息）
        $list = MoneyInterest::where(['status' => 0])->where("add_time", "<", todayBeginTimestamp())->where("end_time", ">", todayBeginTimestamp())->page($page, $rows)->order("id asc")->select();
        if (!empty($list)) {

            try {
                self::startTrans();
                foreach ($list as $value) {
                    //判断当天是否已插入过生息记录数据
                    $isInsert = self::isTodayInsert($value->member_id, $value->currency_id, $value->id);
                    if (!$isInsert) {
                        $data['member_id'] = $value->member_id;
                        $data['currency_id'] = $value->currency_id;
                        $data['interest_id'] = $value->id;
                        $data['num'] = $value->day_num;
                        $data['add_time'] = time();
                        $moneyDaily = new MoneyInterestDaily($data);
                        $save = $moneyDaily->save();
                        if (!$save) {
                            throw new Exception(lang("lan_operation_failure"));
                        }
                    }
                    //如果是到期了的，下面处理到期返还本金+利息的
                    if ($value->end_time > todayBeginTimestamp() && $value->end_time < todayEndTimestamp()) {
                        //修改为已生息状态
                        $update = MoneyInterest::where('id', $value->id)->setField('status', 1);
                        if ($update) {
                            $money = keepPoint($value->day_num * $value->days + $value->num, 6);
                            //增加账本
                            $addAccountBook = model('AccountBook')->addLog([
                                'member_id' => $value->member_id,
                                'currency_id' => $value->currency_id,
                                'type' => 12,
                                'content' => 'lan_interest_title',
                                'number_type' => 1,
                                'number' => $money,
                                'third_id' => $value->id,
                            ]);
                            if (!$addAccountBook) {
                                throw new Exception(lang("lan_operation_failure"));
                            }
                            $currencyUser = CurrencyUser::getCurrencyUser($value->member_id, $value->currency_id);
                            $currencyUser->num += $money;
                            if (!$currencyUser->save()) {
                                throw new Exception(lang("lan_operation_failure"));
                            }
                        } else {
                            throw new Exception(lang("lan_operation_failure"));
                        }
                    }
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("lan_operation_success");
                self::commit();
            } catch (Exception $exception) {
                self::rollback();
                $r['message'] = $exception->getMessage();
            }
        }
        return $r;
    }

    /**
     *  当天是否已插入生息记录
     * @param int $member_id            用户id
     * @param int $currency_id          币种id
     * @param int $interest_id          存入记录id
     * @return bool                 true:已插入过   false：未插入过
     * Created by Red.
     * Date: 2018/12/24 17:36
     */
    static function isTodayInsert($member_id, $currency_id, $interest_id)
    {
        if (!empty($member_id) && !empty($currency_id) && !empty($interest_id)) {
            $find = self::where(["member_id" => $member_id, "currency_id" => $currency_id, "interest_id" => $interest_id])->where("add_time", "between", [todayBeginTimestamp(), todayEndTimestamp()])->find();
            return $find ? true : false;
        }
        return true;
    }

    /**
     * 处理到期的生息记录(0点处理，则当天0:00-23:59:59也一并处理了)
     * @param int $page
     * @param int $rows
     * @throws Exception
     * @return mixed
     * Created by Red.
     * Date: 2019/1/5 16:13
     */
    static function dealWithMoneyInterest($page = 1, $rows = 1000)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        //获取还没生息的记录（当天存的不生息）
        $list = MoneyInterest::where(['status' => 0, 'type' => 1])->where("end_time", "<",todayEndTimestamp())->page($page, $rows)->order("id asc")->select();
        if (!empty($list)) {
            try {
                self::startTrans();
                foreach ($list as $value) {
                    //修改为已生息状态
                    $update = MoneyInterest::where('id', $value->id)->setField('status', 1);
                    if ($update) {
                        $money = keepPoint($value->day_num * $value->days + $value->num, 6);
                        //增加账本
                        $addAccountBook = model('AccountBook')->addLog([
                            'member_id' => $value->member_id,
                            'currency_id' => $value->currency_id,
                            'type' => 12,
                            'content' => 'lan_interest_title',
                            'number_type' => 1,
                            'number' => $money,
                            'third_id' => $value->id
                        ]);
                        if (!$addAccountBook) {
                            throw new Exception("异常1：".lang("lan_operation_failure"));
                        }
                        $currencyUser = CurrencyUser::getCurrencyUser($value->member_id, $value->currency_id);
                        $currencyUser->num += $money;
                        if (!$currencyUser->save()) {
                            throw new Exception("异常2：".lang("lan_operation_failure"));
                        }
                    } else {
                        throw new Exception("异常3：".lang("lan_operation_failure"));
                    }
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("lan_operation_success");
                self::commit();
            } catch (Exception $exception) {
                self::rollback();
                $r['message'] = $exception->getMessage();
            }
        }
        return $r;
    }

    public static function dealWithEverDayMoneyInterest($page, $rows)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $list = MoneyInterest::where(['status' => 0, 'type' => 2])->where("end_time", ">", todayEndTimestamp())->where('profit_time', '<', todayBeginTimestamp())->select();
        if (!empty($list)) {
            try {
                Db::startTrans();
                foreach ($list as $value) {
                    // 判断是否为最后一天生息
                    if ($value['end_time'] < time()) {
                        $update = MoneyInterest::where('id', $value->id)->setField('status',1);
                        if ($update) {
                            // 最后一日收益 = 每日收益 + 本金
                            $money = keepPoint($value->day_num + $value->num, 6);
                            //增加账本
                            $addAccountBook = model('AccountBook')->addLog([
                                'member_id' => $value->member_id,
                                'currency_id' => $value->currency_id,
                                'type' => 12,
                                'content' => 'lan_interest_title',
                                'number_type' => 1,
                                'number' => $money,
                                'third_id' => $value->id
                            ]);
                            if (!$addAccountBook) {
                                throw new Exception("异常1：".lang("lan_operation_failure"));
                            }
                            $currencyUser = CurrencyUser::getCurrencyUser($value->member_id, $value->currency_id);
                            $currencyUser->num += $money;
                            if (!$currencyUser->save()) {
                                throw new Exception("异常2：".lang("lan_operation_failure"));
                            }
                        } else {
                            throw new Exception("异常3：".lang("lan_operation_failure"));
                        }
                    } else {
                        // 结算每日收益
                        $addAccountBook = model('AccountBook')->addLog([
                            'member_id' => $value->member_id,
                            'currency_id' => $value->currency_id,
                            'type' => 12,
                            'content' => 'lan_interest_title',
                            'number_type' => 1,
                            'number' => $value->day_num,
                            'third_id' => $value->id
                        ]);
                        if (!$addAccountBook) {
                            throw new Exception("异常1：".lang("lan_operation_failure"));
                        }
                        $currencyUser = CurrencyUser::getCurrencyUser($value->member_id, $value->currency_id);
                        $currencyUser->num += $value->day_num;
                        if (!$currencyUser->save()) {
                            throw new Exception("异常2：".lang("lan_operation_failure"));
                        }
                        // 修改最后生息时间
                        $res = MoneyInterest::where('id', $value->id)->update(['profit_time' => time()]);
                        if (!$res) {
                            throw new Exception("异常2: " . lang('lan_operation_failure'));
                        }
                    }

                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("lan_operation_success");
                Db::commit();
            } catch (Exception $exception) {
                Db::rollback();
                $r['message'] = $exception->getMessage();
            }
        }
        return $r;
    }

}