<?php
namespace app\h5\controller;

class Visa extends Base
{
    protected $is_decrypt = false; //不验证签名

    //申请详情
    public function info() {
        $res = \app\common\model\Visa::info($this->member_id);
        $this->output_new($res);
    }
    //申请条件检测
    public function check() {
        $res = \app\common\model\Visa::check($this->member_id);
        $this->output_new($res);
    }

    //申请
    public function apply() {
        $name = strval(input('post.name',''));
        $card_id = strval(input('post.card_id',''));
        $phone = strval(input('post.phone',''));
        $province_id = intval(input('post.province_id'));
        $city_id = intval(input('post.city_id'));
        $area_id = intval(input('post.area_id'));
        $address = strval(input('post.address',''));

        $res = \app\common\model\Visa::apply($this->member_id,$name,$card_id,$phone,$province_id,$city_id,$area_id,$address);
        $this->output_new($res);
    }
}
