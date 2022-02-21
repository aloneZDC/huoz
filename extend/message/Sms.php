<?php
namespace message;
use think\Db;
use think\Exception;

class Sms{
    protected $setting = [];
    protected $keyword = '';

    public function __construct($setting) {
        $this->setting = $setting;
    }

    public function send($phone, $code, $type,$country_code='86') {
        return false;
    }

    protected function getModuleByType($type) {
        $moduleId = 100;
        switch ($type) {
            case 'retradepwd': //修改交易密码
                $moduleId = 200;
                break;
            case 'tcoin': //提币
                $moduleId = 300;
                break;
            case 'findpwd': //找回密码
                $moduleId = 400;
                break;
            case 'transfer': //会员互转
                $moduleId = 500;
                break;
            case 'bindphone': //绑定手机
                $moduleId = 900;
                break;
            case 'modifyphone': //修改手机
                $moduleId = 600;
                break;
            case 'chongbi': //充币到账通知
                $moduleId = 700;
                break;
            case 'otc_pay': //OTC付款通知
                $moduleId = 800;
                break;
            case 'otc_fangxing': //OTC放行通知
                $moduleId = 1000;
                break;
            case 'otc_sell': //OTC出售通知
                $moduleId = 1100;
                break;
            default:
                break;
        }

        return $moduleId;
    }
}