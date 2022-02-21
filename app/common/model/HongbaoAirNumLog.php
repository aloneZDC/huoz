<?php
//红包项目  持仓量记录表
namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

class HongbaoAirNumLog extends Model {
    /**
     * @param string $type flop方舟 hongbao锦鲤红包 air云梯入金 space_recommand太空计划 space_release太空计划释放日燃料  space_power太空计划动力源
     * @param $user_id
     * @param $currency_id
     * @param $number
     * @param int $third_id
     * @param int $base_num
     * @param int $percent
     * @return int|string
     */
    static function add_log($type,$user_id,$currency_id,$number,$third_id=0,$base_num=0,$percent=0){
        return self::insertGetId([
            'type' => $type,
            'user_id' => $user_id,
            'currency_id' => $currency_id,
            'number' => $number,
            'third_id' => $third_id,
            'base_num' => $base_num,
            'percent' => $percent,
            'create_time' => time(),
        ]);
    }

    static function get_list($user_id,$type='', $page = 1, $rows = 10) {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && $rows <= 100) {
            $where = [
                'a.user_id' => $user_id,
            ];
            if(!empty($type)) {
                if($type=='ins') {
                    $where['a.type'] = [ 'in',['flop','hongbao','space_recommand','space_release','space_power'] ];
                } elseif($type=='out') {
                    $where['a.type'] = [ 'in',['air'] ];
                }
            }
            $field = "a.number,a.create_time,a.type";
            $list = self::field($field)->alias('a')->where($where)
                ->page($page, $rows)->order("a.id desc")->select();
            if (!empty($list)) {
                foreach ($list as &$value){
                    $value['title'] = '';
                    if($value['type']=='flop') {
                        $value['title'] = lang('flop_release');
                    } elseif($value['type']=='hongbao'){
                        $value['title'] = lang('hongbao_back');
                    } elseif($value['type']=='air'){
                        $value['title'] = lang('air_income');
                    } else {
                        $value['title'] = lang($value['type']);
                    }

                    if(in_array($value['type'],['flop','hongbao','space_recommand','space_release','space_power'])) {
                        $value['number'] = '+'.$value['number'];
                    } elseif (in_array($value['type'],['air'])) {
                        $value['number'] = '-'.$value['number'];
                    }
                    $value['currency_name'] = lang('air_num');
                    $value['create_time'] = date('Y-m-d H:i:s',$value['create_time']);
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("lan_No_data");
            }
        }
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }

    static function num($user_id) {
        $r = [
            'currency_name' => lang('air_num'),
            'air_num' => 0,
            'cny' => 0,
            'currency_logo' => FlopOrders::KOIC_LOGO,
            'currency_id' => Currency::XRP_PLUS_ID,
            'exchange_switch' => 2
        ];

        $currency = Currency::where(['currency_mark'=>$r['currency_id']])->field('currency_id,currency_name,currency_logo')->find();
        if($currency) {
            $user_currency = CurrencyUser::getCurrencyUser($user_id,$currency['currency_id']);
            if($user_currency) {
                $r['air_num'] = $user_currency['air_num'];
                $r['currency_logo'] = $currency['currency_logo'];
            }
            $currency_price = CurrencyPriceTemp::get_price_currency_id($currency['currency_id'],'CNY');
            if($currency_price) $r['cny'] = keepPoint($currency_price * $r['air_num'],2);

            $transfer_config = (new CurrencyUserTransferConfig)->where('currency_id', $currency['currency_id'])->where('type', 'air_num')->value('is_open');
            if (!empty($transfer_config)) {
                $r['exchange_switch'] = $transfer_config;
            }
        }
        return $r;
    }
}
