<?php

namespace app\common\model;


use think\Model;
use think\model\relation\BelongsTo;
use think\model\relation\HasMany;

/**
 * Class ContractIncomeLog
 * 合约收益记录表
 * @package app\common\model
 */
class ContractLockLog extends Model
{
    /**
     * 账户类型枚举
     * @var array
     */
    const TYPE_ENUM = [
        1 => "合约亏损",
        2 => "合约释放",
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id')->field('currency_name, currency_id');
    }

    /**
     * 获取锁仓记录列表
     * @param integer $trade_id
     * @param integer $money_type
     * @param integer $status
     * @return string
     */
    public static function get_log_list($member_id, $type, $currency_id, $page, $length)
    {
        $where = [
            'member_id'=>$member_id,
            'currency_id'=>$currency_id,
        ];
        if ($type == 1) {//收入
            $where['type'] = ['IN', '1'];
        }
        else if ($type == 2) {//支出
            $where['type'] = ['IN', '2'];
        }
        else {
            //$where['type'] = ['IN', '1,2'];
        }
        $start = ($page - 1) * $length;
        $select = (new self)->with(['currency'])->where($where)->limit($start, $length)->order('create_time', 'desc')->select();
        $log_list = [];
        if (count($select) > 0) {
            foreach ($select as $key => $value) {
                $log_list[] = [
                    'id'=>$value['id'],
                    'type'=>$value['type'],
                    'type_name'=>self::TYPE_ENUM[$value['type']],
                    'currency_name'=>$value['currency']['currency_name'],
                    'num'=>$value['type'] == 1 ? '+'.$value['num'] : $value['num'],
                    'fee'=>$value['fee'],
                    'create_time'=>date('Y-m-d H:i:s', $value['create_time']),
                ];
            }
        }
        return $log_list;
    }

    /**
     * @param $type 1-亏损冻结 2-释放
     * @param $member_id
     * @param $currency_id
     * @param $num
     * @param int $fee
     * @return int|string
     */
    static function add_log($type,$member_id,$currency_id,$num,$fee=0){
        return self::insertGetId([
            'type' => $type,
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'num' => $num,
            'fee' => $fee,
            'create_time' => time(),
        ]);
    }
}