<?php
return [
    'app_name' => '火种云仓', // 应用名称
    //支付宝AppId
    'app_id' => '2021002190665256',
    //商户私钥
    'merchant_private_key' => 'MIIEowIBAAKCAQEAnWWurUjjw2s3Txk1vU/fw0HfQ4exNuJrvy9zi6Bxxtued4T8XvXoPtVMhMOwOodCQYkrvirUYiGUx6hWCh1Lk+hVEpctyYT4waHRy81ROU2pkzE5ToCdEsCvdfC4vp2qq+ZOLzH7ooeYnPJOEUHSPPb8HiBYC/cffjKGylFisFamOjWcCD5+nVP0/nRuQaPVSMVbMmMBubZrWJ2ThTZuN5bAukG0uUPYrOpS/1M4ZnE70ZBngbiobnVO8Jpo8y1ddGi+SJCQJcc+hyEt93HPBBQK8bgdRIE4DJoXyfTk96dlerY8cmPOUkGfKMydrxnjFzPgNxj71YklvNMQgXPYNQIDAQABAoIBAF6dlVeNjX0XonPmD6vxq+1QV8ncc+cuTN7sw2SX2k1UD/qA8sSSxj2fMxRMHk/Qpz+GGcmDLZCf5zPuOWpzGc+bxigawOd3C6I6iEce8UilKt7UpEJQhhuTwOYBAs1zMfmLxEwSm9Wj9VXDQrKVArCrN/tULhljQIc4EFbmfZDKVB/ZtH6CyJ9hldx082vpZtNG0OH9EMiw2M73UWKdLQOJyJdskpq89U4aXqWFV2ObN9J4NJoFf2tXLshOJ4E3imrTMQkMzPncDQFMi3wk5Xt36nJNuUXXOj9UlgrM9QGZErlEfHOUgYLwGMcbNTfjG8HYSTyrPjf8/XGmthqkqQECgYEA0m98YyCGTMU7n2m3tyXvpj02vhDjlAIDgsrNhqMlTjKFwqIOFn83ivqHGZQ4WblfBxLKebxvdXKpXSmMp1EMIJX5Fu88VICj68qN+f74CVaKWT5wg9JGaRDT5TaeTNmL/V/bFzaBmQmVqtG2vS0IITpjizx3I3KY7et5yDHp3mUCgYEAv3pDz948qHWy+0UT8mQ3sB6SqubR78xuJgcDzJCq83bKk/xAjJUTm2ujXm6xJwhxmigsKcx/ikLKjZRwtgTNs0Z5Pu+AbtvlwR4xVhF6XAQcGO2UaPJyiS1SztiqrmBmfzJvtRNu31SQ2JyC8HkthQII5cbnbzMrV3WeybfuzZECgYEAnNARTDkfezP60zdPfP6bEDFLiuVBAblibmO8Nll64kPJ45kpkGAqvrkkVc5bE5mMk9PP6FmgYVAZn1/n/YLq25mGri2Gsp3OCVa+6A21PhgsLobWB5V5fK/ah1NlMXVn2V9F/41Rk/5NdqaiW2SkS0aciVR8n4C1L46rCKERWvUCgYBRUmIelKp6eZKMeSghmEK2gCWWg/XBvLdDW1NXvFF+mYMsGsRncKElLy/xWV3P0Bw/drRbBEletUNFojfEdoHOlC3Gdv27F5Wa0XHutfmbvvsX8z9G20Gd1SwIQakr6jFd8FPVOe2q2EW0WbSa4Txd8yFIRZPhfH/54zCPR5kaoQKBgHIdAck5WpQSUXrx1QRlRXEn9GtqAivIVZRXCyu8nBOXcwkNVT32MHKAXaW+QuwhJj7L4Zuj4P0SA0K5ftq0LC+awPguReOogniT/ZZa116/dE4HhQ1JVWylbVFZ8NnyXugZCoX+ruPjMnK4+znv/UUb5Podbh3j8mpEuVQjPpeP',
    //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
    'alipay_public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAnWWurUjjw2s3Txk1vU/fw0HfQ4exNuJrvy9zi6Bxxtued4T8XvXoPtVMhMOwOodCQYkrvirUYiGUx6hWCh1Lk+hVEpctyYT4waHRy81ROU2pkzE5ToCdEsCvdfC4vp2qq+ZOLzH7ooeYnPJOEUHSPPb8HiBYC/cffjKGylFisFamOjWcCD5+nVP0/nRuQaPVSMVbMmMBubZrWJ2ThTZuN5bAukG0uUPYrOpS/1M4ZnE70ZBngbiobnVO8Jpo8y1ddGi+SJCQJcc+hyEt93HPBBQK8bgdRIE4DJoXyfTk96dlerY8cmPOUkGfKMydrxnjFzPgNxj71YklvNMQgXPYNQIDAQAB',

    //异步通知地址
    'notify_url' => 'http://hj.dlyhdz.com/h5/WeChat/AliPayNotify',
    //同步跳转
    'return_url' => 'http://hj.dlyhdz.com/#/orders?id=0',
    //编码格式
    'charset' => "UTF-8",
    //签名方式
    'sign_type' => 'RSA2',
    //支付宝网关 （我使用的沙箱环境）
    'gatewayUrl' => 'https://openapi.alipay.com/gateway.do',
];