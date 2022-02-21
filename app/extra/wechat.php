<?php
return [
    'domain' => 'http://8.135.102.171:96', // 域名

    'app_id' => 'wxbd090df2f0f69ba1', // 公众账号ID
    'app_name' => '云农甲天下', // 应用名称
    'app_secret' => '', // 公众号开发者密钥
    'redirect_uri' => '/h5/WeChat/AuthCallBack', // 公众号 - 授权回调地址

    'open_id' => 'wx1a701b89e0722c37', // 开放平台 - 应用ID

    'mch_id' => '1605341558', // 商户号
//    'mch_id' => '1615672922', // 商户号
    'mch_key' => '0kXb90OXa9duEPTvpZAANceqUAJiRJ4e', // 商户平台设置的密钥key
//    'mch_key' => 'f5f9d7915011a68e51dc9f9148873a3d', // 商户平台设置的密钥key
    'unified_notify_url' => '/h5/WeChat/WeChatPayNotify', // 支付 - 通知地址
    'refund_notify_url' => '/h5/WeChat/RefundNotify', // 退款 - 通知地址

    // 商户证书
    'ssl_cert' => __DIR__ . '/cert/apiclient_cert.pem',
    'ssl_key' => __DIR__ . '/cert/apiclient_key.pem',

    // h5支付场景信息
    // 'h5_info' => ['h5_info' => ['type' => 'Android', 'app_name' => '云农甲天下', 'package_name' => 'com.yunnong.plus']],// 正式
    'h5_info' => ['h5_info' => ['type' => 'Android', 'app_name' => '火种云仓-Test', 'package_name' => 'com.sdhuoj.plus.test']], // 测试

];