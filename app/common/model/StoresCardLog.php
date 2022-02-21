<?php
//线下商家 卡包记录表  I券
namespace app\common\model;

use think\Model;

class StoresCardLog extends Model {
    /**
     * @param string $type 类型 convert兑换 transfer_in互转转入 transfer_out互转转出 transfer_financial划转到O券 shop购物扣除
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
                if($type=='in') {
                    $where['a.type'] = [ 'in',['convert','asset_transfer_in'] ];
                } elseif($type=='out') {
                    $where['a.type'] = [ 'in',['transfer_out','transfer_financial','shop'] ];
                }
            }
            $field = "a.number,a.create_time,a.type";
            $list = self::field($field)->alias('a')->where($where)
                ->page($page, $rows)->order("a.id desc")->select();

            if (!empty($list)) {
                foreach ($list as &$value){
                    $value['title'] = '';
                    if($value['type']=='convert') {
                        $value['title'] = lang('convert');
                    } elseif($value['type']=='transfer_in'){
                        $value['title'] = lang('asset_transfer_in');
                    }elseif($value['type']=='transfer_out'){
                        $value['title'] = lang('asset_transfer_out');
                    }elseif($value['type']=='transfer_financial'){
                        $value['title'] = lang('asset_out');
                    }elseif($value['type']=='shop'){
                        $value['title'] = lang('shopping');
                    }
                    if(in_array($value['type'],['convert','asset_transfer_in'])) {
                        $value['number'] = '+'.$value['number'];
                    } elseif (in_array($value['type'],['transfer_out','transfer_financial','shop'])){
                        $value['number'] = '-'.$value['number'];
                    }
                    $value['currency_name'] = lang('uc_card');
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
}