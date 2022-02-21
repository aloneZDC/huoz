<?php
//翻牌币种
namespace app\common\model;
use think\Exception;
use think\Model;

class FlopHongbao extends Model
{
    //获取待拆红包列表
    static function getHongbaoList($user_id) {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        $time = time() + 3; //即将到期5秒
        $list = self::where(['user_id'=>$user_id,'stop_time'=>['gt',$time],'status'=>0])->field('id,start_time,stop_time')
            ->limit(100)->order('id asc')->select();
        if(!$list) return $r;

        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_data_success');
        $r['result'] = $list;
        return $r;
    }


    //添加可拆红包
    static function addHongbao($user_id,$trade_id,$trade_currency_id,$trade_num,$expire_time=300) {
        $time = time();
        return self::insertGetId([
            'user_id' => $user_id,
            'currency_id' => 0,
            'num' => 0,
            'flop_trade_id' => $trade_id,
            'flop_currency_id' => $trade_currency_id,
            'flop_trade_num' => $trade_num,
            'start_time' => $time,
            'stop_time' => $time+$expire_time,
            'status' => 0,
        ]);
    }

    //拆红包 奖励XRP 0.05%--0.1%
    static function openHongbao($user_id,$flop_hongbao_id) {
        $r['code'] = ERROR1;
        $r['message'] = lang('lan_orders_illegal_request');
        $r['result'] = null;

        self::where(['id'=>$flop_hongbao_id,'status'=>0])->update([
            'status'=>1,
            'chai_time'=>time(),
        ]);
        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_operation_success');
        $r['result'] = [
            'currency_name' => "",
            'hongbao_num' => 0,
        ];
        return $r;

        $hongbao = self::where(['id'=>$flop_hongbao_id,'user_id'=>$user_id])->find();
        if(empty($hongbao)) return $r;

        if($hongbao['status']!=0) {
            $r['message'] = lang('flop_hongbao_has_chai');
            return $r;
        }

        $time = time();
        if($hongbao['stop_time']<$time) {
            $r['message'] = lang('The_has_expired');
            return $r;
        }

        $hongbao_config = HongbaoConfig::get_key_value();

        $hongbao_currency = Currency::where(['currency_mark'=>$hongbao_config['flop_hongbao_currency_mark']])->field('currency_id,currency_name')->find();
        if(empty($hongbao_currency)) return $r;

        $currency_user = CurrencyUser::getCurrencyUser($user_id,$hongbao_currency['currency_id']);
        if(empty($currency_user)) {
            $r['message'] = lang('lan_network_busy_try_again');
            return $r;
        }

        //随机比例
        $percent = keepPoint(randomFloat($hongbao_config['flop_hongbao_min_percent'],$hongbao_config['flop_hongbao_max_percent']),2);
        //实际拆开红包数量
        $hongbao_num = keepPoint($hongbao['flop_trade_num'] * $percent/100,6);

        try{
            self::startTrans();

            //更改红包状态
            $flag = self::where(['id'=>$hongbao['id'],'status'=>0])->update([
                'currency_id' => $currency_user['currency_id'],
                'percent'=>$percent,
                'num'=>$hongbao_num,
                'status'=>1,
                'chai_time'=>$time,
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));


            if($hongbao_num>=0.000001) {
                //添加账本
                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],1007,'flop_hongbao','in',$hongbao_num,$hongbao['id'],0);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setInc('num',$hongbao_num);
                if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_operation_success');
            $r['result'] = [
                'currency_name' => $hongbao_currency['currency_name'],
                'hongbao_num' => $hongbao_num,
            ];
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }


    //获取已拆红包列表
    static function getList($user_id,$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        $where = [
            'a.user_id'=> $user_id,
            'a.status' => 1,
        ];
        $list = self::alias('a')->field('a.id,a.num,a.chai_time,b.currency_name')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->where($where)
            ->page($page, $rows)->order("a.id desc")->select();
        if(!$list) return $r;

        foreach ($list as &$item) {
            $item['chai_time'] = date('Y-m-d H:i:s',$item['chai_time']);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,phone,email');
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
