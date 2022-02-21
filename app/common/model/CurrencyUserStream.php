<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/10
 * Time: 15:25
 */

namespace app\common\model;


class CurrencyUserStream extends Base
{

    /**
     * 添加财务日志(资产资金变动前调用此方法)
     *
     * @param int $member_id 会员id
     * @param int $currency_id 币种id
     * @param int $currency_field 变动帐户 1可用num 2冻结frozen 3锁仓lock 4互转exchange 5奖励num_award
     * @param int $amount 变动数值
     * @param int $operation 操作 数量的变动：1增加,2减少
     * 变动类型:
          0充值/赠送 11:充提币 12提币手续费 21:币币交易挂单 22币币交易成交 23币币交易返还多扣的金额 24币币交易手续费 25返回手续费
     *    31:OTC广告 32:OTC交易 33OTC手续费 费 41:C2C 42C2C手续 51:锁仓 52:锁仓赠送 53:释放 54:解冻 61:注册奖励
     *    62:手续费奖励 63:推荐奖 64:领导奖 65:邀请奖励 71:认购 72：释放
     *    81:互转 91:转入持币生息 91:持币生息利息 93:持币生息分红 94:持币生息本金返还
     * @param int $type 变动类型
     * @param int $ralation_id 关联id
     * @param int $remark 备注
     */
   static public function addStream($member_id = 0, $currency_id = 0, $currency_field = 1, $amount = 0, $operation = 1, $type = 1, $ralation_id = 0, $remark = '')
    {
        switch ($currency_field) {
            case 1:
                $field = 'num';
                break;
            case 2:
                $field = 'forzen_num';
                break;
            case 3:
                $field = 'lock_num';
                break;
            case 4:
                $field = 'exchange_num';
                break;
            case 6:
                $field = 'num_award';
                break;
        }
        $current = CurrencyUser::where(['member_id'=> $member_id, 'currency_id'=>$currency_id])->value($field);
        list($usec, $sec) = explode(" ", microtime());
        $msec=round($usec*1000);
        $time = time();
        $data['serial_no'] = date('YmdHis', $time).$msec.mt_rand(1000, 9999);
        $data['member_id'] = $member_id;
        $data['currency_id'] = $currency_id;
        $data['currency_field'] = $currency_field;
        $data['amount'] = $amount;
//        $kok_price = $this->getBk($currency_id);
       //TODO
        $data['kok_num'] = 0;
        $data['current'] = $current ? $current : 0;
        $data['operation'] = $operation;
        if($operation == 1){
            $data['result'] = $current + $amount;
        }else{
            $data['result'] = $current - $amount;
        }
        $data['result'] = sprintf("%.8f", $data['result']);
        $data['type'] = $type;
        $data['ralation_id'] = $ralation_id;
        $data['remark'] = $remark;
        $data['create_time'] = $time;
        $object=new CurrencyUserStream($data);
        $res = $object->save();
        return $res;
    }
}