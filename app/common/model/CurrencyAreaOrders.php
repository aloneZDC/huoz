<?php
//专区
namespace app\common\model;

use think\Exception;
use think\Model;

class CurrencyAreaOrders extends Model
{
    /**
     * 添加订单
     * @param $user_id
     * @param $currency_id
     * @param $num
     * @param int $sa_id
     * @param int $self_mention
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    static function add_orders($user_id,$currency_id,$num,$sa_id=0,$self_mention=0,$mobile='') {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if(!isInteger($user_id) || !isInteger($currency_id) || !isInteger($num)) return $r;

        $currency = Currency::where(['currency_id'=>$currency_id])->field('currency_id,currency_name')->find();
        if(empty($currency)) {
            $r['message'] = lang('lan_close');
            return $r;
        }

        $area = CurrencyArea::where(['currency_id'=>$currency_id])->find();
        if(!$area) {
            $r['message'] = lang('lan_close');
            return $r;
        }

        //库存不足
        if($area['amount']<$num) {
            $r['message'] = lang('insufficient_inventory');
            return $r;
        }

        $total_price = $area['price'] * $num;
        $users_currency = CurrencyUser::getCurrencyUser($user_id,$currency_id);
        if(empty($users_currency) || $users_currency['num']<$total_price) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        //不是自提 且设置的有邮费
        $postage_num = $area['postage'] * $num;
        if($self_mention!=1 && $area['postage']>0 && $area['postage_currency_id']>0) {
            $postage_users_currency = CurrencyUser::getCurrencyUser($user_id,$area['postage_currency_id']);
            if(empty($postage_users_currency) || $postage_users_currency['num']<$postage_num) {
                $r['message'] = lang('insufficient_balance');
                return $r;
            }
        }

        //收获地址 自提不需要
        if($self_mention==1) {
            //自提 需要预留手机
            if(empty($mobile))  return $r;
            $cao_receive_name = $cao_address = '';
            $cao_mobile = $mobile;
        } else {
            //快递
            $address = ShopAddress::where(['sa_user_id' => $user_id, 'sa_id' => $sa_id])->find();
            //收货地址
            if (empty($address)) {
                $r['message'] = lang("incorrect_delivery_address_information");
                return $r;
            }

            $cao_receive_name = $address->sa_name;
            $cao_mobile = $address->sa_mobile;
            $cao_address = Areas::check_pca_address($address->sa_province, $address->sa_city, $address->sa_area) . $address->sa_address;
        }

        $status = 1;
        //到仓自提 直接待收货
        if($self_mention==1) $status = 3;
        try{
            self::startTrans();
            //添加订单记录
            $orders_id =  self::insertGetId([
                'cao_user_id' => $user_id,
                'cao_currency_id' => $currency_id,
                'cao_title' => $area['title'],
                'cao_img' => $area['img'],
                'cao_price' => $area['price'],
                'cao_num' => $num,
                'cao_total_price' => $total_price,
                'cao_code' => self::create_code(),
                'cao_add_time' => time(),
                'cao_status' => $status,
                'cao_receive_name' => $cao_receive_name,
                'cao_mobile' =>  $cao_mobile,
                'cao_address' => $cao_address,
                'cao_postage' => $postage_num,
                'cao_postage_currency_id' => $area['postage_currency_id'],
                'cao_self_mention' => $self_mention,
            ]);
            if(!$orders_id) throw new Exception(lang('operation_failed_try_again'));

            //减少库存
            $flag = CurrencyArea::where(['currency_id'=>$currency_id,'amount'=>$area['amount'] ])->setDec('amount',$num);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //添加账本 扣除资产
            $flag = AccountBook::add_accountbook($user_id,$currency_id,900,'special_area','out',$total_price,$orders_id,0);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            $flag = CurrencyUser::where(['cu_id'=>$users_currency['cu_id'],'num'=>$users_currency['num']])->setDec('num',$total_price);
            if(!$flag) throw new Exception(lang('operation_failed_try_again'));

            //邮费
            if($self_mention!=1 && $area['postage']>0 && $area['postage_currency_id']>0) {
                $flag = AccountBook::add_accountbook($user_id,$area['postage_currency_id'],901,'special_area_postage','out',$postage_num,$orders_id,0);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$postage_users_currency['cu_id'],'num'=>$postage_users_currency['num']])->setDec('num',$postage_num);
                if(!$flag) throw new Exception(lang('operation_failed_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //订单详情
    static function orders_info($user_id,$orders_id) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;

        $orders = self::where(['cao_user_id'=>$user_id,'cao_id'=>$orders_id])->with(['currency'])->find();
        if(empty($orders)) return $r;

        $orders['cao_add_time'] = date('Y-m-d H:i:s',$orders['cao_add_time']);
        $orders['cao_sure_time'] = $orders['cao_sure_time'] ? date('Y-m-d H:i:s',$orders['cao_sure_time']) : '';
        $orders['full_address'] = [];
        //自提地址
        if($orders['cao_self_mention']==1) {
            $area = CurrencyArea::where(['currency_id'=>$orders['cao_currency_id']])->field('full_address,longitude,latitude,mobile')->find();
            if($area) $orders['full_address'] = $area;
        }
        $orders['cao_postage_currency_name'] = '';
        if($orders['cao_postage_currency_id']>0 && $orders['cao_postage']>0) {
            $currency = Currency::where(['currency_id'=>$orders['cao_currency_id']])->field('currency_id,currency_name')->find();
            if($currency) $orders['cao_postage_currency_name'] = $currency['currency_name'];
        }
        $orders['cao_currency_name'] = $orders['currency'] ? $orders['currency']['currency_name'] : '';
        unset($orders['currency']);

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $orders;
        return $r;
    }

    static function create_code(){
        $code=date("YmdHi").randNum();
        $find=self::where(['cao_code'=>$code])->field("cao_code")->find();
        if(empty($find)){
            return $code;
        }else{
            return self::create_code();
        }
    }

    static function orders_list($user_id,$status=0,$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;

        $where['cao_user_id'] = $user_id;
        if($status) $where['cao_status'] = $status;
        $list = self::alias('a')->where($where)
            ->field('a.cao_id,a.cao_total_price,a.cao_price,a.cao_status,a.cao_num,a.cao_img,a.cao_title,a.cao_code,a.cao_add_time,a.cao_postage,c.currency_name,cc.currency_name as postage_currency_name')
            ->join(config("database.prefix") . "currency c", "a.cao_currency_id=c.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency cc", "a.cao_postage_currency_id=cc.currency_id", "LEFT")
            ->order('a.cao_id desc')
            ->page($page, $rows)->select();
        if(!$list) return $r;

        foreach ($list as &$item) {
            $item['cao_add_time'] = date('Y-m-d H:i:s',$item['cao_add_time']);
            $item['postage_currency_name'] = $item['postage_currency_name'] ? $item['postage_currency_name'] : '';
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'cao_currency_id', 'currency_id')->field('currency_id,currency_name,currency_mark');
    }

    public function postagecurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'cao_postage_currency_id', 'currency_id')->field('currency_id,currency_name,currency_mark');
    }

    static function confirm_order($user_id, $cao_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && isInteger($cao_id)) {
            $order = self::where(['cao_user_id' => $user_id, 'cao_id' => $cao_id])->find();
            //不是1已付款(待发货)，3已发货(待收货)状态不能操作
            if ($order['cao_status'] == 1 || $order['cao_status'] == 3) {
                $update = self::where(['cao_id' => $cao_id])->update(['cao_status' => 4, 'cao_sure_time' => time()]);
                if ($update) {
                    $r['message'] = lang("successful_operation");
                    $r['code'] = SUCCESS;
                } else {
                    $r['code'] = ERROR2;
                    $r['message'] = lang("operation_failed_try_again");
                }
            } else {
                $r['message'] = lang("operation_failed_try_again");
            }
        }
        return $r;
    }
}
