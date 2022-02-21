<?php
namespace app\common\model;
use think\Exception;
use think\Log;
use think\Model;

/**
 * BFL矿池表
 * Class BflPool
 * @package app\common\model
 */
class BflPool extends Model
{
    const DEFAULT_CURRENCY = 69;
    const FUND = 'Fund'; //基金矿池
    const MINERAL = 'Mineral'; //矿源矿池
    const HOLE = 'Hole'; //黑洞矿池
    const AIRDROP = 'Airdrop'; //空投矿池
    const Reward = 'Reward'; //奖励矿池
    const ANGEL = 'Angel'; //天使矿池
    const lab = 'Lab'; //实验室
    const FLOW = 'Flow'; //流动矿池

    static function getOne($currency_id,$name) {
        return self::where('currency_id',$currency_id)->where('name',$name)->field('id,name,block_address,total_num,cur_num')->find();
    }

    static function getIn($currency_id,array $name) {
        return self::where('currency_id',$currency_id)->where(['name'=>['in',$name] ])->field('id,name,block_address,total_num,cur_num')->select();
    }

    static function getList($currency_id) {
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $list = self::where('currency_id',$currency_id)->where('is_show',1)->field('name,block_address,total_num,cur_num')->select();

        $total = self::where('currency_id',$currency_id)->where('is_show',1)->sum('total_num');
        $flow_key = -1;
        $hole_key = -1;
        foreach ($list as $key=>&$item) {
            $item['name'] = lang($item['name']);
            if($item['name']==self::HOLE) {
                $hole_key = $key;
            } elseif ($item['name']==self::FLOW) {
                $flow_key = $key;
            }
        }

        if($flow_key>=0 && $hole_key>=0 && $list[$hole_key]['cur_num']+$list[$flow_key]['cur_num']>$total ) {
            $list[$flow_key]['cur_num'] = keepPoint($total-$list[$hole_key]['cur_num']);
        }

        $r['result'] = $list;
        return $r;
    }

    /**
     * 减少来源区块名称 的 cur_num 数量
     * @param $from
     * @param $num
     * @return int|true
     * @throws Exception
     */
    static function fromDec($from_pool,$num) {
        return self::where('id',$from_pool['id'])->where('cur_num',$from_pool['cur_num'])->setDec('cur_num',$num);
    }

    /**
     * 增加目标区块名称 的 cur_num 数量
     * @param $to
     * @param $num
     * @return int|true
     * @throws Exception
     */
    static function toInc($to_pool,$num) {
        return self::where('id',$to_pool['id'])->where('cur_num',$to_pool['cur_num'])->setInc('cur_num',$num);
    }

    /***
     *
     * @param $to_pool
     * @param $num
     * @return array
     * @throws Exception
     */
    static function fromToTask($from_name,$to_name,$num,$third_type,$third_id,$currency_id=0) {
        $r = [
            'code' => ERROR1,
            'message' => lang('operation_failed_try_again'),
            'result' => null
        ];
        if($currency_id==0) $currency_id = self::DEFAULT_CURRENCY;

        try {
            //减去来源矿源矿池
            $from_pool = self::getOne($currency_id,$from_name);
            if (!$from_pool) return $r;

            if($from_pool['cur_num']<$num) {
                $r['message'] = lang('bfl_pool_not_enough');
                return $r;
            }

            $flag = self::fromDec($from_pool, $num);
            if (!$flag) return $r;

            //增加目标源矿源矿池
            $to_pool = self::getOne($currency_id,$to_name);
            if (!$to_pool) return $r;

            $flag = self::toInc($to_pool, $num);
            if (!$flag) return $r;

            //添加task
            $flag = BflPoolTask::addTask($currency_id,$from_name, $to_name, $num, $third_type, $third_id);
            if (!$flag) return $r;

            $r = [
                'code' => SUCCESS,
                'message' => lang('success'),
                'result' => null
            ];
        } catch (Exception $e) {
           $r['message'] = $e->getMessage();
        }


        return $r;
    }
}
