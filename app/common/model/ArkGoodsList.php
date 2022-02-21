<?php


namespace app\common\model;

use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Model;

class ArkGoodsList extends Model
{
    /**
     * 添加子商品
     * @param int $goods_id 主商品id
     * @param int $type     类型 1创建第一关 2创建下一关
     * @return bool
     */
    static function add_goods($goods_id, $type=1) {
        $data = ArkGoods::field('name,currency_id,price,min_payment,max_payment,profit,payment_rate,price_rate,start_time,run_daynum,lucky_Jackpot,fund_Jackpot,platform_Jackpot,kmt_rate,end_time')->where(['id' => $goods_id])->find();
        $res = self::where(['goods_id' => $goods_id])->order('id desc')->find();
        try{
            $flag = true;
            if ($data) {
                $data['level'] = 1;
                $data['last_settlement_time'] = $data['start_time'];
                if ($res) {//判断是否存在上一个商品的最后闯关结算时间
                    $data['start_time'] = 0;//闯关开始时间
                    $data['end_time'] = 0;//闯关结束时间
                    $data['last_settlement_time'] = 0;
                    if ($type == 2) {
                        $price_info = self::get_price_info($res);
                        if (!$price_info) return false;

                        $data['price'] = $price_info['price'];//闯关金额
                        $data['level'] = $res['level'] + 1;//闯关数
                        $data['max_payment'] = $price_info['max_payment'];//最大支付金额
                    }
                    $data['price_rate'] = $res['price_rate'];
                }
                $data['is_show'] = 2;//app不展示
                $data['goods_id'] = $goods_id;
                $data['add_time'] = time();
                $data = json_decode($data, true);
                $flag = self::insertGetId($data);
            }
            return $flag;
        } catch (\Exception $e) {
            Log::write($e->getMessage());
            return false;
        }
    }

    /**
     * 更新子商品
     * @param int $id 子商品id
     * @param string $today 结算时间
     * @param int $status   闯关状态：0未开启 1开启中 2已结束 3已结算
     * @return bool
     */
    static function update_goods($id, $today, $status=0) {
        $res = self::where(['id' => $id])->find();
        if ($res) {
            $data = [
                'last_settlement_time' => $today//最后闯关结算时间
            ];
            if ($status > 0) {
                $data['rocket_status'] = $status;
            }
            $flag = self::where(['id' => $id])->update($data);
            if ($flag === false) return false;
        }
        return true;
    }

    /**
     * 获取下一关闯关金额、最大支付金额
     * @param array $data 闯关信息
     */
    static function get_price_info($data) {
        $result = [];
        if ($data['price_rate'] == 0) {
            $data['price_rate'] = 1;
        }
        //闯关金额：上一关卡总额的1.3倍
        $price = keepPoint($data['price'] * $data['price_rate'],0);
        //最大支付金额（弃用）
//        $level = $data['level'];
//        $num = floor($level / 10);
//        $payment = ($num + 1) * 10;
//        $max_payment = keepPoint($data['max_payment'] + $payment,6);
        //最大支付金额
        $max_payment = 0;
        if ($data['level'] < 10) {//1~10每关增加10
            $max_payment = keepPoint($data['max_payment'] + 10,0);
        } elseif ($data['level'] < 20) {//11~20每关增加100
            $max_payment = keepPoint($data['max_payment'] + 100,0);
        } elseif ($data['level'] < 30) {//21~30每关增加500
            $max_payment = keepPoint($data['max_payment'] + 500,0);
        } elseif ($data['level'] >= 30) {//31~31以后每关增加1000
            $max_payment = keepPoint($data['max_payment'] + 1000,0);
        }

        if ($price && $max_payment) {
            $result = ['price' => $price, 'max_payment' => $max_payment];
        }
        return $result;
    }

    /**
     * 获取闯关列表
     * @param int $member_id 用户ID
     * @param int $goods_id  主闯关ID
     * @param int $page      页
     * @param int $rows      页数
     */
    static function get_list($member_id, $goods_id, $page, $rows = 15) {
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => null];
        if (empty($goods_id)) return $r;

        $list = self::field('id,name,price,finish_money,min_payment,max_payment,profit,kmt_rate,last_settlement_time,rocket_status,level,status,start_time,end_time,run_daynum')
            ->where(['goods_id' => $goods_id, 'is_show' => 1])
            ->page($page, $rows)
            ->order("id desc")
            ->select();
        if (!$list) return $r;

        $today = time();//当天
        foreach ($list as &$value) {
            $value['game_start_time'] = date('Y-m-d H:i', $value['start_time']) .'至'. date('Y-m-d H:i', $value['end_time']);//闯关开始时间
            $next_start_time = $value['start_time'];
            $next_end_time = $value['end_time'];
            $subscribe_start_time = $value['start_time'] - 1800;
            $subscribe_end_time = $value['start_time'];
            $value['subscribe_status'] = 0;//预约状态：0未开启 1开启中 2已结束
            if ($value['rocket_status'] < 2) {
                $value['rocket_status'] = 0;//闯关状态 0待推进 1推进中 2推进成功 3已结算 4已清退 5推进失败
                if (sprintf('%.2f', $value['price']) > sprintf('%.2f', $value['finish_money'])) {
                    $finish_money = $value['finish_money'];
                    $value['finish_money'] = 0;
                    if ($today >= $value['start_time'] && $today <= $value['end_time']) {
                        $value['rocket_status'] = 1;
                        $value['finish_money'] = $finish_money;
                    } elseif ($today > $value['end_time']) {
                        $value['game_start_time'] = date('Y-m-d H:i', $next_start_time).'至'.date('Y-m-d H:i', $next_end_time);
                        $value['rocket_status'] = 5;
                        $value['finish_money'] = $finish_money;
                    }
                }
            }
            if ($value['rocket_status'] != 3 && sprintf('%.2f', $value['price']) == sprintf('%.2f', $value['finish_money'])) {
                if ($today < $value['start_time']) {
                    $value['finish_money'] = 0;
                } else {
                    $value['rocket_status'] = 2;//推进成功
                    $value['game_start_time'] = date('Y-m-d H:i', $next_start_time).'至'.date('Y-m-d H:i', $next_end_time);
                }
            }
            if ($value['level'] == 1) {
                $value['level_name'] = '点火';
            } else {
                $value['level_name'] = '推进' . $value['level'];
            }
            if ($value['rocket_status'] == 3 && $value['status'] == 3) {
                $value['rocket_status'] = 4;//已清退
                $value['game_start_time'] = date('Y-m-d H:i', $next_start_time).'至'.date('Y-m-d H:i', $next_end_time);
            }
            if ($today >= $subscribe_start_time && $today <= $subscribe_end_time) {
                $value['subscribe_status'] = 1;//开启中
            } elseif ($today > $subscribe_end_time) {
                $value['subscribe_status'] = 2;//已结束
            }
            //当完成金额大于闯关金额时，完成金额等于闯关金额，前端进度条线上100%
            if (sprintf('%.2f', $value['finish_money']) > sprintf('%.2f', $value['price'])) {
                $value['finish_money'] = $value['price'];
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }
}