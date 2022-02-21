<?php
namespace app\common\model;
use think\Db;
use think\Exception;
use think\Log;
use think\Model;

/**
 * 方舟机器人 方舟机器人要加到 flop_white 方舟白名单中
 * 当买单小于20个时 自动增加买单到20个
 * 当买单大于20个时 自动购买用户订单
 */
class FlopRobot extends Model
{
    //获取机器人列表
    static function getList() {
        return self::select();
    }

    static function isRobot($member_id) {
        return false; //可以自己和自己

        $info = self::where(['member_id'=>$member_id])->find();
        if($info) return true;
        return false;
    }

    //方舟机器人充值
    static function admRecharge($robot_member_id,$currency_id,$num=100000) {
        $currency_user = CurrencyUser::getCurrencyUser($robot_member_id,$currency_id);
        if(!$currency_user) return false;

        try {
            self::startTrans();

            //添加充值记录
            $pay_id = Db::name('pay')->insertGetId([
                'message' => '方舟机器人',
                'admin_id' => 0,
                'member_id' => $robot_member_id,
                'currency_id' => $currency_id,
                'money' => $num,
                'status' => 1,
                'add_time' => time(),
                'type' => 3,
            ]);
            if(!$pay_id) throw new Exception("添加充值记录失败");

            //添加账本
            $flag = AccountBook::add_accountbook($robot_member_id,$currency_id,13,'lan_admin_recharge','in',$num,$pay_id);
            if(!$flag) throw new Exception("添加账本失败");

            //增加资产
            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id']])->setInc('num',$num);
            if(!$flag) throw new Exception("增加资产失败");

            self::commit();
            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("方舟机器人充值失败：".$e->getMessage());
        }

        return false;
    }

    /**
     * 自动购买
     * @param $robot_member_id 方舟机器人ID
     * @param $currency_id 方舟币种ID
     * @param $pay_currency_id 支付币种ID
     * @return bool
     */
    static function buy($robot_member_id,$currency_id,$pay_currency_id) {
        $price = 1;
        $num = rand(1000,5000);

        $currency_user = CurrencyUser::getCurrencyUser($robot_member_id,$pay_currency_id);
        if(!$currency_user) return 0;

        if($currency_user['num']<$num*2) {
           //去充值
            $flag = self::admRecharge($robot_member_id,$pay_currency_id);
            if(!$flag) return 0;
        }

        //进行购买操作
        $res = FlopOrders::add_buy_orders($robot_member_id,$currency_id,$price,$num);
        if($res['code']!=SUCCESS) {
            Log::write("方舟机器人自动购买失败".$res['message']);
            //方舟关闭时 休眠10秒
            if($res['message']==lang('flop_trade_wait')) {
                return 10;
            }
            return 0;
        }
        return 1;
    }

    /**
     * 自动出售
     * @param $robot_member_id  方舟机器人ID
     * @param $orders_id 方舟订单ID
     * @param $currency_id 方舟币种ID
     * @param $num 出售数量
     * @return bool
     */
    static function sell($robot_member_id,$orders_id,$currency_id,$num) {
        $currency_user = CurrencyUser::getCurrencyUser($robot_member_id,$currency_id);
        if(!$currency_user) return 0;

        if($currency_user['num']<$num*2) {
            //去充值
            $flag = self::admRecharge($robot_member_id,$currency_id);
            if(!$flag) return 0;
        }

        $res = FlopTrade::sell_to_orders($robot_member_id,$orders_id,$num);
        if($res['code']!=SUCCESS) {
            Log::write("方舟机器人自动出售失败".$res['message']);
            //方舟关闭时 休眠10秒
            if($res['message']==lang('flop_trade_wait')) {
                return 10;
            }
            return 0;
        }
        return 1;
    }
}
