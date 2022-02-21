<?php

namespace WeChat;

class WeChatMp
{
    /**
     * 第一步：用户同意授权，获取code
     * @param string $authUrl 授权页面
     * @param string $expand 扩展参数，格式序列化加密
     * @return string 返回请求地址
     */
    public static function Authorize($authUrl, $expand = '')
    {
        $config = config('wechat');

        // 授权回调地址
        $redirect_url = request()->domain() . $config['redirect_uri'];

        // 扩展参数组装
        $extendData = [
            'page' => $authUrl,
            'serilize' => $expand,
        ];
        $redirect_url .= '?' . http_build_query($extendData);
        $redirect_uri = urlencode($redirect_url);

        // 组装请求参数
        $paramData = [
            'appid' => $config['app_id'],
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'snsapi_userinfo',
            'state' => 'STATE',
        ];

        // 返回请求地址
        $responseUrl = 'https://open.weixin.qq.com/connect/oauth2/authorize' . http_build_query($paramData) . '#wechat_redirect';
        return $responseUrl;
    }

    // 授权回调
    public static function AuthCallBack($code)
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        // 获取 access token
        $accessToken = self::AccessToken($code);
        if (isset($accessToken['errcode'])) {
            $r['message'] = "错误代码：" . $accessToken['errcode'] . ",错误信息：" . $accessToken['errmsg'];
            return $r;
        }

        // 获取用户信息
        $userInfo = self::UserInfo($accessToken['access_token'], $accessToken['openid']);
        if (isset($userInfo['errcode'])) {
            $r['message'] = "错误代码：" . $userInfo['errcode'] . ",错误信息：" . $userInfo['errmsg'];
            return $r;
        }

        $r['code'] = SUCCESS;
        $r['result'] = $userInfo;
        return $r;
    }

    /**
     * 第二步：通过code换取网页授权access_token
     * @param string $code 填写第一步获取的code参数
     * @return mixed
     */
    private static function AccessToken($code)
    {
        $config = config('wechat');
        $paramData = [
            'appid' => $config['app_id'],
            'secret' => $config['app_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];

        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token';
        $access_token = self::CurlGetSsl($url, $paramData);
        return $access_token;
    }

    /**
     * 微信登入第三步 获取用户信息
     * @param $access_token
     * @param string $openid 用户openid
     * @return mixed
     */
    private static function UserInfo($access_token, $openid)
    {
        $paramData = [
            'access_token' => $access_token,
            'openid' => $openid,
            'lang' => 'zh_CN',
        ];

        $user_info_url = 'https://api.weixin.qq.com/sns/userinfo';
        $user_info = self::CurlGetSsl($user_info_url, $paramData);
        return $user_info;
    }

    /**
     * 发送GET请求
     * @param string $url 请求地址
     * @param array $params 请求参数
     * @return mixed
     */
    private static function CurlGetSsl($url, $params = [])
    {
        // 组装参数
        if (!empty($params)) {
            $link = (strpos($url, '?') === false) ? '?' : '&';
            $url .= $link . http_build_query($params);
        }

        $curl = curl_init();
        // 设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        // 设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        // 设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($curl); // 执行命令
        curl_close($curl); // 关闭URL请求
        // 显示获得的数据
        return json_decode($data, true);
    }

}