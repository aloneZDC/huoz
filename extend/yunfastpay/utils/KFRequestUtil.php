<?php
namespace yunfastpay\utils;

use think\Db;
use think\Exception;
use yunfastpay\utils\Des3Utils;
use yunfastpay\utils\HttpClientUtils;

class KFRequestUtil {
    /*
     * 秘钥类型域，机构秘钥:ORG、商户秘钥：MCHT、终端秘钥：TERM
     */
    static $typeField = "ORG";

    /*
     * secretKey是3des加密秘钥，测试时请替换成自己的测试秘钥，
     * 生产需要换生产秘钥，如果没有请联系广东快付的技术获取。
     * 这里可以使用机构秘钥或者商户秘钥。使用机构秘钥加密类型ORG，
     * 使用商户秘钥加密类型MCHT
     */
    static $secretKey = "QEZ3J2QIXMZBMHFNQ55BNW3S";

    /*
     * orgCd是机构号，测试时请替换成自己的测试机构号，
     * 生产需要换生产机构号，如果没有请联系广东快付的技术获取。
     */

    static $orgCd = "202111180001299";

    /*
     * 测试请求地址：http://test.api.route.hangmuxitong.com
     * 生产请求地址：https://api.yunfastpay.com
     */
    static $reqUrl = "http://test.api.route.hangmuxitong.com";

    public static function req($reqData){
        $config_params = config('yunfastpay');

        $encReqData = Des3Utils::encrypt(json_encode($reqData), $config_params['secret']);
        
        $data = [];
        $data["typeField"] = KFRequestUtil::$typeField;
        $data["keyField"] = $config_params['orgCd'];
        $data["dataField"] = $encReqData;
        if ($config_params['debug_mode'] === true) {
            \think\Log::write(json_encode($data));
        }

        $encRespStr = HttpClientUtils::send_request($config_params['proUrl'], json_encode($data));

        $respMsg = json_decode($encRespStr, true);
        if ($config_params['debug_mode'] === true) {
            \think\Log::write(json_encode($respMsg));
        }
        $respStr = Des3Utils::decrypt($respMsg["dataField"], $config_params['secret']);
        if ($config_params['debug_mode'] === true) {
            \think\Log::write($respStr);
        }

        return $respStr;
    }
}
