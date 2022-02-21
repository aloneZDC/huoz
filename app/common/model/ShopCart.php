<?php


namespace app\common\model;


use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Model;

class ShopCart extends Model
{
    /**
     *  添加购物车
     * @param int $user_id 用户id
     * @param int $goods_id 商品id
     * @param int $format_id 规格id
     * @param int $num 数量
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function add_shop_cart($user_id,$goods_id,$num, $format_id){
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if(isInteger($user_id)&&isInteger($goods_id)&&isInteger($num)){
            $goods=Db::name("goods")->where(['goods_status'=>Goods::STATUS_UP,'goods_id'=>$goods_id])->find();
            if(!empty($goods)){
                if ($format_id <= 0) {
                    $formatList = GoodsFormat::get_format_list($goods_id);
                    if (count($formatList) > 0) {
                        $r['message'] = lang("请选择商品规格");
                        return $r;
                    }
                }
                else {
                    $formatInfo = GoodsFormat::where(['goods_id'=>$goods_id, 'id'=>$format_id])->find();
                    if (!$formatInfo) {
                        $r['message'] = lang("商品规格错误，请重新选择");
                        return $r;
                    }
                }
                // 只有乐购区可加入购物车
                $goodsCategory = GoodsCategory::get($goods['category_id']);
                if($goodsCategory['pid'] != 1) {
                    $r['code']=ERROR1;
                    $r['message']=lang("parameter_error");
                    return $r;
                }

                $shop_cart=self::where(['sc_user_id'=>$user_id,'sc_goods_id'=>$goods_id,'sc_format_id'=>$format_id])->find();
                if(!empty($shop_cart)){
                    //已添加过则追加
                    $shop_cart->sc_num+=$num;
                    $shop_cart->sc_time=time();
                    if($shop_cart->save()){
                        $r['code']=SUCCESS;
                        $r['message']=lang("added_successfully");
                    }else{
                        $r['message']=lang("add_failed");
                    }
                }else{
                    $data['sc_user_id']=$user_id;
                    $data['sc_goods_id']=$goods_id;
                    $data['sc_num']=$num;
                    $data['sc_format_id']=$format_id;
                    $data['sc_time']=time();
                    $data['sc_admin_id'] = $goods['goods_admin_id'];
                    $add=Db::name("shop_cart")->insertGetId($data);
                    if($add){
                        $r['code']=SUCCESS;
                        $r['message']=lang("added_successfully");
                    }else{
                        $r['message']=lang("add_failed");
                    }
                }
            }
        }
        return $r;
    }


    public function goods()
    {
        return $this->belongsTo(Goods::class, 'sc_goods_id')->where('goods_status', Goods::STATUS_UP)->with(['currency', 'other_currency'])->field('goods_id,goods_title,goods_img,goods_price,category_id, goods_currency_id, goods_currency_num, goods_currency_type, goods_currency_other_id, goods_currency_other_num, goods_currency_other_type, goods_postage');
    }
    public function format()
    {
        return $this->belongsTo(GoodsFormat::class, 'sc_format_id')->field('id,name,goods_price,goods_market');
    }
    /**
     * 获取购物车列表
     * @param int $user_id          用户id
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function get_shop_cart($user_id,$sc_id=null){
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if(isInteger($user_id)){
//            $field="sc_id,goods_id,sc_num,goods_title,goods_img,goods_price,currency_name";
            $where = [];
//            $query=Db::name("shop_cart")->alias("sc")->field($field);
            if(!empty($sc_id)){
                //根据购物车表id，多个用","隔开
                $sc_id=str_replace("，",",",$sc_id);
                $sc_id=explode(",",$sc_id);
//                $query->where("sc_id","in",$sc_id);
                $where['sc_id'] = ["in", $sc_id];
            }
            $where['sc_user_id'] = $user_id;

            $list = static::where($where)->with(['goods','format'])->order('sc_time desc')->select();
            if($list){
                foreach ($list as $key => $value) {
                    if ((!empty($value['sc_format_id']) && empty($value['format'])) || empty($value['goods']['goods_price'])) {
                        unset($list[$key]);
                        continue;
                    }
                }
                $list = array_values($list);
                if (!$list) {
                    $r['message']=lang("no_data");
                    return $r;
                }

                $total=0;
                $num=0;
                $other_total=0;
                $postage_total = 0;
                foreach ($list as &$value){
                    $value = $value->toArray();
                    $value['goods']['format'] = !empty($value['format']) ? $value['format'] : [];
                    if(!empty($value['format'])){
                        $value['goods']['goods_price']=floattostr($value['format']['goods_price']);
                        $value['goods']['goods_market']=floattostr($value['format']['goods_market']);
                    }
                    elseif(!empty($value['goods'])){
                        $value['goods']['goods_price']=!empty($value['goods']['goods_price']) ? floattostr($value['goods']['goods_price']) : 0;
                        $value['goods']['goods_market']=!empty($value['goods']['goods_market']) ? floattostr($value['goods']['goods_market']) : 0;
                    }

                    unset($value['format']);
                    //$value['goods']['goods_price']=floatval($value['goods']['goods_price']);
                    $value['goods']['goods_img']=!empty($value['goods']['goods_img'])?explode(",",$value['goods']['goods_img']):null;
                    $value['goods']['goods_currency_num'] = floatval($value['goods']['goods_currency_num']);
                    $value['goods']['goods_currency_other_num'] = floatval($value['goods']['goods_currency_other_num']);
                    $value['goods']['goods_postage'] = floatval($value['goods']['goods_postage']);
                    $total+=bcmul($value['sc_num'],$value['goods']['goods_price'],6);
//                    $total += bcmul($value['sc_num'], $value['goods']['goods_currency_num'], 6);
//                    $other_total += bcmul($value['sc_num'], $value['goods']['goods_currency_other_num'], 6);
                    $postage_total += bcmul($value['sc_num'], $value['goods']['goods_postage'], 6);
                    $num+=$value['sc_num'];

                    $goodsCategory = GoodsCategory::get($value['goods']['category_id']);
                    if (!empty($goodsCategory)) {
                        $goods_category_pid = GoodsCategory::get($goodsCategory['pid']);
                        $value['goods']['category_pid'] = $goods_category_pid['id'];
                        $value['goods']['category_type'] = $goods_category_pid['name'];
                    }
                }

                $r['code']=SUCCESS;
                $r['message']=lang("data_success");
                $r['result']=[
                    'total'=>$total,
                    'goods_total'=>bcadd($total, $postage_total, 2),
//                    'other_total' => $other_total,
                    'list'=>$list,
                    'num'=>$num,
                    'postage_total' => $postage_total,
                    'hm_price'=>ShopConfig::get_value('hm_price', 6.1)
                ];
            }else{
                $r['message']=lang("no_data");
            }
        }
        return $r;
    }

    /**
     * 删除购物车数据
     * @param string $sc_id 表id,多个用","隔开
     * @param int $user_id 用户id
     * @return mixed
     */
    static function delete_shop_cart($sc_id,$user_id){
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if(!empty($sc_id)&&isInteger($user_id)){
            $sc_id=str_replace("，",",",$sc_id);
            $scids=explode(",",$sc_id);
           $delele=self::where(['sc_user_id'=>$user_id])->where("sc_id","in",$scids)->delete();
           if($delele){
               $r['code']=SUCCESS;
               $r['message']=lang("comments_delete_success");
           }else{
               $r['message']=lang("operation_failed");
           }
        }
        return $r;
    }

    /**
     * 修改购物车数量
     * @param int $user_id 用户id
     * @param int $sc_id 购物车id
     * @param int $num 修改的数量
     * @return mixed
     * @throws Exception
     * @throws PDOException
     */
    static function update_shop_cart($user_id,$sc_id,$num){
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if(isInteger($user_id)&&isInteger($num)&&isInteger($sc_id)){
            $update=Db::name("shop_cart")->where(['sc_id'=>$sc_id,'sc_user_id'=>$user_id])->update(['sc_num'=>$num]);
            if($update){
                $r['code']=SUCCESS;
                $r['message']=lang("success_operation");
            }else{
                $r['message']=lang("operation_failed");
            }
        }
        return $r;
    }
}