<?php
namespace app\index\controller;
// 下载中心
use Think\Db;

class Down extends Base
{
	protected $public_action = ['index','mobile'];
    public function index()
    {
        $qr_code=Db::name('config')->where(['key'=>['in','android_qr_code,iso_qr_code']])->select();
        $code['android_qr_code']="";
        $code['iso_qr_code']="";
        if(!empty($qr_code)){
            foreach ($qr_code as $key=>$val){
                if($val['key']=='android_qr_code'){
                    $code['android_qr_code']=$val['value'];
                }elseif ($val['key']=='iso_qr_code'){
                    $code['iso_qr_code']=$val['value'];
                }

            }
        }
        $list=Db::name("currency")->select();
        $currency_list = array();
        foreach ($list as $key=> $val){
            if($val['qianbao_url']){
                $currency_list[$key] = $val;
            }
        }

        return $this->fetch('down/index',['qr_code'=>$code,'list'=>$currency_list]);
    }

    public function mobile()
    {
        $qr_code=Db::name('config')->where(['key'=>'android_qr_code'])->find();
        return $this->fetch('down/mobile',['qr_code'=>$qr_code]);
    }
}
