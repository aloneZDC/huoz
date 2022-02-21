<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/13
 * Time: 9:35
 */

namespace app\common\model;


class CurrencyAssetChange extends Base
{
    protected $resultSetType = 'collection';

    /**获取前七天的资产变动列表
     * @param $member_id            用户id
     * @param $currency_id          币种id
     * @return mixed
     * Created by Red.
     * Date: 2018/12/13 10:02
     */
    static function getAssetChangeList($member_id, $currency_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        if (!empty($member_id) && !empty($currency_id)) {
            $date = date('m-d', strtotime('-7 days'));//7天前日期
            $list = self::where(['cac_currency_id' => $currency_id, 'cac_member_id' => $member_id])->field('cac_money,cac_time')->order('cac_id desc')->limit(7)->select()->toArray();

            //2018.12.17
            if($list){
                $length = count($list)-1;
                $info = $list[$length];
                $last_time = strtotime($info['cac_time']);
                $last_num = $info['cac_money'];

                foreach ($list as $key => $value) {
                    $value['cac_time'] = date('m-d',strtotime($value['cac_time']));
                    $list[$key] = $value;
                }
            } else {
                $last_time = time();
                $last_num = 0;
            }

            //兼容没有数据,或数据不足,没有数据
            $count = count($list);
            for ($i=0; $i < 7-$count; $i++) {
                $last_time -= 86400;
                $last_date_v = date('m-d',$last_time);
                $list[] = [
                    'cac_time' => $last_date_v,
                    'cac_money' => $last_num,
                ];
            }
            $list = array_reverse($list);

            if(!empty($list)){
               $r['code']=SUCCESS;
               $r['message']=lang("lan_data_success");
               $r['result']=$list;
            }else{
               $r['message']=lang("lan_not_data");
            }
        }
        return $r;
    }
}
