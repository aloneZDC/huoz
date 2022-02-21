<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/22
 * Time: 14:47
 */

namespace app\common\model;


use think\Exception;

class MoneyInterest extends Base
{

    // 设置返回数据集的对象名
    protected $resultSetType = 'collection';

    /**
     * 添加持币生息
     * @param int $member_id 用户id
     * @param int $id 生息配置表的id
     * @param double $num 转入数量
     */
    static function addMoneyInterest($member_id, $id, $num)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];

        self::startTrans();
        try {
            if (!empty($member_id) && !empty($id) && !empty($num) && $num > 0) {
                $setting = MoneyInterestConfig::where(['id' => $id])->find();
                if (empty($setting)) throw new Exception(lang('lan_Invalid_currency'));
                $setting = $setting->toArray();
                $num = keepPoint($num, 6);
                if ($num <= 0) throw new Exception(lang('lan_quantity_cannot_less_equal'));
                if ($setting['min_num'] > 0 && $num < $setting['min_num']) throw new Exception(lang('lan_num_not_less_than') . $setting['min_num']);
                if ($setting['max_num'] > 0 && $num > $setting['max_num']) throw new Exception(lang('lan_num_not_greater_than') . $setting['max_num']);

                $curr_num = CurrencyUser::where(['member_id' => $member_id, 'currency_id' => $setting['currency_id']])->lock(true)->value("num");
                if (!$curr_num || empty($curr_num) || $curr_num < $num) throw new Exception(lang("lan_trade_underbalance"));

                $start_time = time();
                // $end_time = time() + ($setting['days'] * 86400);
                 $end_time = strtotime('+' . $setting['months'] . ' months', $start_time); //结束时间

                //加入天数
                $days = $setting['days'];
                //日利息
                if (MoneyInterestConfig::TYPE_EXPIRE == $setting['type']) {
                    $day_num = keepPoint($num * ($setting['rate'] / 100) / 365, 6);
                } else {
                    $day_num = keepPoint($num * ($setting['day_rate'] / 100), 6);
                }

                $data = [
                    'member_id' => $member_id,
                    'currency_id' => $setting['currency_id'],
                    'num' => $num,
                    'months' => $setting['months'],
                    'days' => $days,
                    'day_num' => $day_num,
                    'rate' => $setting['rate'],
                    'status' => 0,
                    'add_time' => $start_time,
                    'end_time' => $end_time,
                    'type' => $setting['type'],
                    'days_rate' => $setting['day_rate']
                ];
                $moneyInterest = new MoneyInterest($data);
                $log = $moneyInterest->save();

                if (!$log) throw new Exception(lang("lan_network_busy_try_again"));

                //添加财务日志
                $flag = CurrencyUserStream::addStream($member_id, $setting['currency_id'], 1, $num, 2, 91, $moneyInterest->id, '转入持币生息');
                if (!$flag) throw new Exception(lang("lan_network_busy_try_again"));


                //转入持币生息添加账本
                $result = model('AccountBook')->addLog([
                    'member_id' => $member_id,
                    'currency_id' => $setting['currency_id'],
                    'type' => 12,
                    'content' => 'lan_interest_title',
                    'number_type' => 2,
                    'number' => $num,
                    'status' => 1,
                    'third_id' => $moneyInterest->id
                ]);
                if (!$result) {
                    throw new Exception(lang("lan_operation_failure"));
                }
                //扣除用户资产
                $flag1 = CurrencyUser::where(['member_id' => $member_id, 'currency_id' => $setting['currency_id']])->setDec('num', $num);
                if (!$flag1) throw new Exception(lang("lan_network_busy_try_again"));

                self::commit();
                $r['code'] = SUCCESS;
                $r['message'] = lang('lan_operation_success');
                $r['result'] = $moneyInterest->id;
            }
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    /**
     * 获取用户的持币生息列表记录
     * @param $member_id
     * @param int $page
     * @param int $rows
     * @param int $status       3为已失效，2为已生息，1为生息中;不传或其它则获取全部(和数据库中的status是不对应的)
     * @return false|mixed|null|\PDOStatement|string|\think\Collection
     * Created by Red.
     * Date: 2018/12/22 18:36
     */
    static function getMoneyInterestList($member_id, $page = 1, $rows = 10,$status=null)
    {
        if (!empty($member_id)) {
            $where['member_id']=$member_id;
            if($status==1){
                $where['status']=0;
            }elseif ($status==2){
                $where['status']=1;
            }elseif ($status==3){
                $where['status']=2;
            }
            $list = self::where($where)->page($page, $rows)->order("id desc")->select()->toArray();
            if (!empty($list)) {
                $currencyList = Currency::field("currency_id,currency_name as currency_mark")->select()->toArray();
                $currencyList = array_column($currencyList, null, "currency_id");
                foreach ($list as &$value) {
                    if (2 == $value['type']) {
                        if (1 == $value['status']) {
                            $start_time = strtotime(date("Y-m-d"), $value['profit_time']);
                        } else {
                            $start_time = strtotime(date("Y-m-d", time()));
                        }
                        $day = ($start_time - $value['add_time']) / 86400;
                        if ($day > 0) {
                            $interest = ceil($day) * $value['day_num']; // 利息
                            $value['estimate_money'] = keepPoint($value['num'] + $interest, 6);
                            $value['days'] = ceil($day);
                        } else {
                            $value['estimate_money'] = $value['num'];
                            $value['days'] = 0;
                        }
                    } else {
                        $value['estimate_money'] = keepPoint($value['day_num'] * $value['days'] + $value['num'], 6);
                    }
                    $value['currency_mark'] = isset($currencyList[$value["currency_id"]]['currency_mark']) ? $currencyList[$value["currency_id"]]['currency_mark'] : lang("lan_Invalid_currency");
                    $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
                    $value['end_time'] = date("Y-m-d H:i:s", $value['end_time']);
                }
                return $list;
            }
        }
        return null;
    }

    /**
     * 获取用户的持币生息记录总数
     * @param $member_id
     * @param int $status       3为已失效，2为已生息，1为生息中;不传或其它则获取全部(和数据库中的status是不对应的)
     * @return false|mixed|null|\PDOStatement|string|\think\Collection
     * Created by Red.
     * Date: 2018/12/22 18:36
     */
    static function getMoneyInterestCount($member_id,$status=null)
    {
        if (!empty($member_id)) {
            $where['member_id']=$member_id;
            if($status==1){
                $where['status']=0;
            }elseif ($status==2){
                $where['status']=1;
            }elseif ($status==3){
                $where['status']=2;
            }
            return self::where($where)->count("id");
        }
        return 0;
    }




}