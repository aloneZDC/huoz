<?php
//专区
namespace app\common\model;

use think\Model;

class CurrencyArea extends Model
{
    static function area_list($page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;

        $list = self::alias('a')->where(['a.status'=>1])->field('a.currency_id,a.title,a.img,a.price,a.detail,a.total_circulation,a.total_amount,a.amount,a.amout_unit,a.add_time,a.postage,c.currency_name,cc.currency_name as postage_currency_name')
            ->join(config("database.prefix") . "currency c", "a.currency_id=c.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency cc", "a.postage_currency_id=cc.currency_id", "LEFT")
            ->order('a.sort asc')
            ->page($page, $rows)->select();
        if(!$list) return $r;

        foreach ($list as &$item) {
            $item['add_time'] = date('Y-m-d H:i:s',$item['add_time']);
            $item['postage_currency_name'] = $item['postage_currency_name'] ? $item['postage_currency_name'] : '';
            $item['circulation'] = $item['amount'];

            $total = CurrencyUser::where(['currency_id'=>$item['currency_id']])->field('sum(num+forzen_num) as total')->find();
            $item['circulation'] = $total && $total['total'] ? floattostr($total['total']) : 0;
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    static function area_info($currency_id) {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_close");
        $r['result'] = null;

        $info = self::alias('a')->where(['a.currency_id'=>$currency_id,'a.status'=>1])->field('a.*,c.currency_name,cc.currency_name as postage_currency_name')
            ->join(config("database.prefix") . "currency c", "a.currency_id=c.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency cc", "a.postage_currency_id=cc.currency_id", "LEFT")->find();
        if(!$info) return $r;

        $info['banners'] = json_decode($info['banners'],true);
        $info['content'] = $info['content'] ? html_entity_decode($info['content']) : '';
        $info['add_time'] = date('Y-m-d H:i:s',$info['add_time']);
        $info['postage_currency_name'] = $info['postage_currency_name'] ? $info['postage_currency_name'] : '';
        $info['circulation'] = $info['amount'];

        $total = CurrencyUser::where(['currency_id'=>$currency_id])->field('sum(num+forzen_num) as total')->find();
        $info['circulation'] = $total && $total['total'] ? floattostr($total['total']) : 0;

        $cache_key = 'area_info_p3_'.$currency_id;
        $data = cache($cache_key);
        if(empty($data)) {
            $data = [];
            $user_currency_list = self::get_user_currency_list($currency_id,1,3);
            $orders_list = self::get_orders_list($currency_id,1,3);
            $data['user_currency_list'] = $user_currency_list['result'];
            $data['orders_list'] = $orders_list['result'];
            //缓存2秒
            if($user_currency_list['code']==SUCCESS || $orders_list['code']==SUCCESS) {
                $data['user_currency_list'] = $user_currency_list['result'];
                $data['orders_list'] = $orders_list['result'];
                cache($cache_key,$data,100);
            }
        }
        $info['user_currency_list'] = $data['user_currency_list'];
        $info['orders_list'] = $data['orders_list'];

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $info;
        return $r;
    }

    static function check($currency_id,$field='*') {
        $info = self::where(['currency_id'=>$currency_id])->field($field)->find();
        if(!$info || $info['status']!=1) return false;

        return $info;
    }

    //实时持有者排名
    static function get_user_currency_list($currency_id,$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;

        $info = self::where(['currency_id'=>$currency_id])->find();
        if(!$info || $info['status']!=1) return $r;

        $list = CurrencyUser::alias('a')->where(['a.currency_id'=>$currency_id])->field('(a.num+a.forzen_num) as total_num,m.phone,m.email')
            ->join(config("database.prefix")."member m", "a.member_id=m.member_id", "LEFT")
            ->order('total_num desc')
            ->having('total_num>0')
            ->page($page, $rows)->select();
        if(!$list) return $r;

        foreach ($list as &$item) {
            $item['account'] = !$item['phone'] ? substr($item['email'],0,3).'****'.substr($item['email'],-7) : substr($item['phone'],0,3).'****'.substr($item['phone'],-4);
            unset($item['phone'],$item['email']);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    //实时兑换排名
    static function get_orders_list($currency_id,$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;

        $info = self::where(['currency_id'=>$currency_id])->find();
        if(!$info || $info['status']!=1) return $r;

        $list = CurrencyAreaOrders::alias('a')->where(['a.cao_currency_id'=>$currency_id])->field('a.cao_user_id,sum(a.cao_num) as total_num,m.phone,m.email')
            ->join(config("database.prefix")."member m", "a.cao_user_id=m.member_id", "LEFT")
            ->group('cao_user_id')
            ->order('total_num desc')
            ->page($page, $rows)->select();
        if(!$list) return $r;

        foreach ($list as &$item) {
            $item['account'] = !$item['phone'] ? substr($item['email'],0,3).'****'.substr($item['email'],-7) : substr($item['phone'],0,3).'****'.substr($item['phone'],-4);
            unset($item['phone'],$item['email'],$item['cao_user_id']);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name,currency_mark');
    }
}
