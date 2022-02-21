<?php

namespace app\h5\controller;

use Alipay\Alipay;
use think\Log;
use WeChat\WeChatMp;
use WeChat\WeChatPay;

class WeChat extends Base
{
    protected $public_action = ['AuthCallBack', 'WeChatPayNotify', 'RefundNotify', 'AliPayNotify','YunFastPayNotify'];

    // 用户同意授权
    public function Authorize()
    {
        $authUrl = input('auth_url'); // 授权页面
        $expand = input('expand'); // 扩展参数
        $responseUrl = WeChatMp::Authorize($authUrl, $expand);
        $this->redirect($responseUrl);
    }

    // 微信授权回调
    public function AuthCallBack()
    {
        $code = input('code');
        $responseData = WeChatMp::AuthCallBack($code);
        $this->output_new($responseData);
    }

    // 支付结果通知 - 微信
    public function WeChatPayNotify()
    {
        $xmlData = file_get_contents("php://input");
        Log::write('微信支付回调：php://input|' . $xmlData);
        return WeChatPay::WeChatPayNotify($xmlData);
    }

    // 退款结果通知 - 微信
    public function RefundNotify()
    {
        $xmlData = file_get_contents("php://input");
        Log::write('微信退款回调：php://input|' . $xmlData);
        return WeChatPay::RefundNotify($xmlData);
    }

    // 支付结果通知 - 支付宝
    public function AliPayNotify()
    {
        $input = input();
        Log::write('支付结果通知：php://input|' . json_encode($input));
        echo Alipay::AliPayNotify($input);
    }

    // 支付结果通知 - 快付
    public function YunFastPayNotify()
    {
        $input = input();
        Log::write('支付结果通知：php://input|' . json_encode($input));
        echo \yunfastpay\PayNotice::notify();
    }
}
