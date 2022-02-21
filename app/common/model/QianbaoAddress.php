<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/8
 * Time: 16:58
 */

namespace app\common\model;


use message\Btc;
use think\Exception;

class QianbaoAddress extends Base
{
    protected $resultSetType = 'collection';

    protected static function init()
    {
        //添加数据前操作，判断用户是否已插入相同的一条数据
        QianbaoAddress::beforeInsert(function ($qiaobao) {
            if (!empty($qiaobao->tag)) {
                $find = self::where(['user_id' => $qiaobao->user_id, 'currency_id' => $qiaobao->currency_id, "qianbao_url" => $qiaobao->qianbao_url, 'tag' => $qiaobao->tag])->find();
            } else {
                $find = self::where(['user_id' => $qiaobao->user_id, 'currency_id' => $qiaobao->currency_id, "qianbao_url" => $qiaobao->qianbao_url])->find();
            }
            if ($find) {
                return false;
            }
        });
    }

    /**
     * 添加一条常用地址
     * @param int $member_id 用户id
     * @param string $name 地址标签名
     * @param string $address 地址
     * @param int $currency_id 币种id
     * @param null $tag 瑞波币的标签
     * @return mixed
     *@throws Exception
     */
    static function addAddress($member_id, $name, $address, $currency_id, $tag = null)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        if (!empty($member_id) && !empty($name) && !empty($address) && !empty($currency_id)) {
            $currency=Currency::where(['currency_id'=>$currency_id])->field("currency_type,rpc_url,rpc_pwd,port_number,rpc_user")->find();
            if(!empty($currency)){
//                if(in_array($currency->currency_type,['btc','usdt'])){
//                   $btc=new Btc();
//                   $service['rpc_url']=$currency->rpc_url;
//                   $service['rpc_user']=$currency->rpc_user;
//                   $service['rpc_pwd']=$currency->rpc_pwd;
//                   $service['port_number']=$currency->port_number;
//                   $isAddress=$btc->check_qianbao_address($address,$service);
//                   if(!$isAddress){
//                       $r['message']=lang("address_error");
//                       return $r;
//                   }
//                }else
            if (in_array($currency->currency_type,['eth'])){
                    if(!isValidAddress($address)){
                        $r['message']=lang("address_error");
                        return $r;
                    }
                }

                if (!empty($tag)&&($currency->currency_type=="xrp"||$currency->currency_type=="eos")) {
                    //瑞波币的tag标签为数字
                    if (!is_numeric($tag)) {
                        $r['message'] = lang("lan_label_to_be_number");
                        return $r;
                    }
                    $find = self::where(['user_id' => $member_id, 'qianbao_url' => $address, 'currency_id' => $currency_id, 'tag' => $tag])->find();
                } else {
                    $find = self::where(['user_id' => $member_id, 'qianbao_url' => $address, 'currency_id' => $currency_id])->find();
                }

                if (empty($find)) {
                    $qianbao = new QianbaoAddress();
                    $qianbao->user_id = $member_id;
                    $qianbao->names = $name;
                    $qianbao->qianbao_url = $address;
                    $qianbao->status = 1;
                    $qianbao->add_time = time();
                    $qianbao->currency_id = $currency_id;
                    if(!empty($tag))$qianbao->tag=$tag;
                    if ($qianbao->save()) {
                        $r['code'] = SUCCESS;
                        $r['message'] = lang("lan_saved_successfully");
                        $r['result'] = $qianbao->id;
                    } else {
                        $r['message'] = lang("lan_save_failed");
                    }
                } else {
                    $r['message'] = lang("lan_address_already_exists");
                }
            }else{
                $r['message']=lang("system_parameter_setting_error");
            }

        }
        return $r;
    }

    /**
     * 根据用户id和币种id获取用户的常用地址列表
     * @param $member_id            用户id
     * @param $currency_id          币种id
     * @return mixed
     * Created by Red.
     * Date: 2018/12/12 17:47
     */
    static function getAddressList($member_id, $currency_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        if (!empty($member_id) && !empty($currency_id)) {
            $list = self::where(['user_id' => $member_id, 'currency_id' => $currency_id, 'status' => 1])->order("id desc")->select()->toArray();
            if ($list) {
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("not_data");
            }
        }
        return $r;
    }

    /**
     * 删除一条常用地址
     * @param $member_id            用户id
     * @param $id                   地址id
     * @return mixed
     * Created by Red.
     * Date: 2018/12/12 18:13
     */
    static function deleteAddress($member_id, $id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        if (!empty($member_id) && !empty($id)) {
            $delete = self::where(['user_id' => $member_id, 'id' => $id])->delete();
            $r['code'] = $delete ? SUCCESS : ERROR2;
            $r['message'] = $delete ? lang("lan_successfully_deleted") : lang("lan_failed_to_delete");
        }
        return $r;
    }

    /**
     * 修改常用地址
     * @param $id                       表id
     * @param $member_id                用户id
     * @param $currency_id              币种id
     * @param null $name                名称
     * @param null $address             地址
     * @param null $tag                 瑞波币的地址标签
     * @return mixed
     * Created by Red.
     * Date: 2018/12/13 14:19
     */
    static function updateAddress($id, $member_id, $currency_id, $name = null, $address = null, $tag = null)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        if (!empty($id) && !empty($member_id) && !empty($currency_id) && (!empty($name) || !empty($address) || !empty($tag))) {
            if (!empty($address)) {
                $currency=Currency::where(['currency_id'=>$currency_id])->field("currency_type")->find();
                if($currency->currency_type!="xrp"){
                    //查询一下要修改的地址是否已存在的地址相同
                    $find = self::where(['user_id' => $member_id, 'currency_id' => $currency_id, 'qianbao_url' => $address])
                        ->where("id", "<>", $id)->find();
                }else{
                    //查询一下要修改的地址是否已存在的地址相同
                    $find = self::where(['user_id' => $member_id, 'currency_id' => $currency_id, 'qianbao_url' => $address,"tag"=>$tag])
                        ->where("id", "<>", $id)->find();
                }

                if (!empty($find)) {
                    $r['message'] = lang("lan_address_already_exists");
                    return $r;
                }
            }
            $qianbao = self::where(['id' => $id, 'user_id' => $member_id,'currency_id'=>$currency_id])->find();
            if (!empty($qianbao)) {
                $qianbao=$qianbao->toArray();
                if (!empty($name)) $qianbao['names'] = $name;
                if (!empty($address)) $qianbao['qianbao_url'] = $address;
                if (!empty($tag)){
                    if(!is_numeric($tag)){
                        $r['message']=lang("lan_label_to_be_number");
                        return $r;
                    }
                    $qianbao['tag'] = $tag;
                }
                if (self::update($qianbao)) {
                    $r['code'] = SUCCESS;
                    $r['message'] = lang("lan_saved_successfully");
                } else {
                    $r['message'] = lang("lan_save_failed");
                }
            } else {
                $r['message'] = lang("lan_user_data_error");
            }
        }
        return $r;
    }

}