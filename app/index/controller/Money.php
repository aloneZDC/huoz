<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/24
 * Time: 9:22
 */

namespace app\index\controller;

use app\common\model\CurrencyUser;
use app\common\model\Member;
use app\common\model\MoneyInterest;
use app\common\model\MoneyInterestConfig;
use app\common\model\Currency;
use think\Db;

class Money extends Base
{

    public function index()
    {
        $currency_id = input('currency_id');
        $list['list'] = MoneyInterestConfig::getCurrencyList($this->member_id);
        $list['current_currency'] = [];
        $list['current_month'] = [];
        $list['current_month_list'] = [];
        if (!empty($currency_id)) {
            $list['current_currency'] = Db::name('MoneyInterestConfig')->alias("m")->where(['m.currency_id' => $currency_id])->field("m.*,c.currency_logo,c.currency_mark")->join('yang_currency c', 'm.currency_id = c.currency_id')->order("m.months asc")->find();
        } else {
            if (!empty($list['list'])) $list['current_currency'] = current($list['list']);
        }
        $find_currency_id = null;
        if (!empty($list['current_currency'])) {
            $find_currency_id = $currency_id ? $currency_id : $list['current_currency']['currency_id'];
            $list['current_month_list'] = MoneyInterestConfig::getConfigByCurrenciId($find_currency_id);
            if (!empty($list['current_month_list'])) {
                foreach ($list['current_month_list'] as &$value) {
                    $start_time = time();
                    $end_time = strtotime('+' . $value['months'] . ' months', $start_time); //结束时间
                    //加入天数
                    $days = intval(($end_time - $start_time) / 86400);
                    $value['days'] = $days;
                }
            }
            if (!empty($list['current_month_list'])) $list['current_month'] = current($list['current_month_list']);
        } else {
            $find_currency_id = $currency_id;
        }
        $list['currency_user_num'] = Db::name('currency_user')->where(['member_id' => $this->member_id, 'currency_id' => $find_currency_id])->value('num');
        $this->assign($list);
        return $this->fetch();
    }

    /**持币生息记录
     * @param int $page 当前页数(默认第1页)
     * @param int $rows 每页显示条目数(默认每页10条)
     * @param int $type 2为已生息,1为生息中
     * @return string
     * Created by Red.
     * Date: 2019/3/5 11:38
     */
    public function recond()
    {
        $page = input("page", 1);
        $rows = 10;
        $type = input("type");
        $data['list'] = MoneyInterest::getMoneyInterestList($this->member_id, $page, $rows, $type);
        if (!empty($data['list'])) {
            $typeList = ['0' => lang('lan_living_in_interest'), '1' => lang('lan_already_living'), '2' => lang('lan_money_expired')];
            foreach ($data['list'] as &$value) {
                $value['type_name'] = $typeList[$value['status']];
                $value['class'] = 'cancel';
                if ($value['status'] == 0) {
                    $value['class'] = 'income';
                } elseif ($value['status'] == 1) {
                    $value['class'] = 'out';
                }
                $value['estimated'] = keepPoint($value['day_num'] * $value['days'] + $value['num'], 6);
            }
        }
        $count = MoneyInterest::getMoneyInterestCount($this->member_id, $type);
        $data['pages'] = $this->getPages($count, $page, $rows);
        $this->assign($data);
        return $this->fetch();
    }


    /**
     * 持币生息操作提交
     * @param int $id 期数表id
     * @param float $num 数量
     * Created by Red.
     * Date: 2018/12/24 11:44
     */
    function addMoneyInterest()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        $id = input("post.id");
        $num = input("post.num");
        $paypwd = input("post.paypwd");
        if (!empty($id) && !empty($num) && !empty($paypwd)) {
            //验证支付密码
            $password = Member::verifyPaypwd($this->member_id, $paypwd);
            if ($password['code'] == SUCCESS) {
                $result = MoneyInterest::addMoneyInterest($this->member_id, $id, $num);
                if ($result['code'] == SUCCESS) {
                    $MoneyInterest = MoneyInterest::where(['id' => $result['result']])->find();
                    if (!empty($MoneyInterest)) {
                        $MoneyInterest = $MoneyInterest->toArray();
                        $MoneyInterest['add_time'] = date("Y-m-d H:i:s", $MoneyInterest['add_time']);
                        $MoneyInterest['end_time'] = date("Y-m-d H:i:s", $MoneyInterest['end_time']);
                        $MoneyInterest['estimate_money'] = keepPoint($MoneyInterest['day_num'] * $MoneyInterest['days'] + $MoneyInterest['num'], 6);
                    }
                    $result['result'] = $MoneyInterest;
                    mobileAjaxReturn($result);
                } else {
                    mobileAjaxReturn($result);
                }
            } else {
                mobileAjaxReturn($password);
            }

        }

        mobileAjaxReturn($r);

    }


}