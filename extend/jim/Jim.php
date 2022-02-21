<?php
namespace jim;
use think\Db;
use think\Exception;

class Jim
{
    private $api_url = "https://api.im.jpush.cn";
    public $app_key = "810550174d26f1dfca3cd8ae";
    public $master_secret = "c9d5f6209b22d65a47a8f470";
    public $name_prefix = "RBZJSCross-";
    private $api_action = [
        'register' => '/v1/users/', //注册
        'userinfo' => '/v1/users/{username}', //获取用户信息
        'userstat' => '/v1/users/{username}/userstat', //用户在线状态查询
        '_userstat' => '/v1/users/userstat', //批量用户在线状态查询
        'messages' => '/v1/messages', //发送消息
        'admins' => '/v1/admins/', //Admin Register 管理员注册 (管理员api发送消息接口的权限)
    ];

    public function request($name = '', $action = [])
    {
        if (!method_exists($this, $name)) {
            exit("method not exists!");
        }
        return $this->$name($action);
    }

    /**
     * 发送消息
     * @param array $argc
     * @return mixed
     */
    private function messages($argc = [])
    {
        $method = "post";
        $url = $this->api_url . $this->api_action['messages'];
        $admin_user = "admin";
        $data = [
            'version' => 1,
            'target_type' => "single",
            'target_id' => $this->name_prefix . $argc["from_id"], //接收人
            'from_type' => $admin_user,
            //'from_id' => $this->name_prefix . $argc["target_id"],
            'from_id' => $admin_user, //发送人
            'target_appkey' => $this->app_key,
            'target_name' => $argc["from_name"],
            'from_name' => $argc["target_name"],
            'msg_type' => $argc["msg_type"],
            'no_offline' => true,
        ];

        if ($data['msg_type'] == 'text') {
            $data['msg_body'] = [
                'text' => $argc["msg_body"],
                'extras' => [
                    'send_type' => (!empty($argc["send_type"]) ? $argc["send_type"] : ''),
                    'send_id' => (!empty($argc["send_id"]) ? $argc["send_id"] : 0)
                ]
            ];
        }

        if ($data['msg_type'] == 'image') {
            $data['msg_body'] = [
                'media_id' => $argc["media_id"],
                'media_crc32' => $argc["media_crc32"],
                'width' => $argc["width"],
                'height' => $argc["height"],
                'format' => $argc["format"],
                'hash' => $argc["hash"],
                'fsize' => $argc["fsize"]
            ];
        }

        if ($data['msg_type'] == 'voice') {
            $data['msg_body'] = [
                'media_id' => $argc["media_id"],
                'media_crc32' => $argc["media_crc32"],
                'duration' => $argc["duration"],
                'hash' => $argc["hash"],
                'fsize' => $argc["fsize"]
            ];
        }

        $post = $this->curl($url, $data, $method);
        $post = json_decode($post, true);

        if (!empty($post['error']['code']) && $post['error']['code'] === 899016) {
            $this->admins(["username" => $admin_user]);
            return $this->messages($argc);
        }

        return $post;
    }

    /**
     * 注册
     * @param array $argc
     * @return mixed
     */
    private function register($argc = [])
    {
        $method = "post";
        $url = $this->api_url . $this->api_action['register'];
        $data = [
            'username' => $this->name_prefix . $argc['username'],
            'password' => $this->gen_pass($argc['username']),
        ];
        $data = [$data];

        $post = $this->curl($url, $data, $method);
        return $post;
    }

    /**
     * Admin Register 管理员注册 (管理员api发送消息接口的权限)
     * @param array $argc
     * @return mixed
     */
    private function admins($argc = [])
    {
        $method = "post";
        $url = $this->api_url . $this->api_action['admins'];
        $data = [
            'username' => $argc['username'],
            'password' => md5($argc['username']),
        ];

        $post = $this->curl($url, $data, $method);
        return $post;
    }

    /**
     * 获取用户信息
     * @param array $argc
     * @return mixed
     */
    private function userinfo($argc = [])
    {
        $method = "get";
        $url = $this->api_url . $this->api_action['userinfo'];
        $url = str_replace("{username}", $this->name_prefix . $argc['username'], $url);

        $post = $this->curl($url, $argc, $method);
        return $post;
    }

    /**
     * 用户在线状态查询
     * @param array $argc
     * @return mixed
     */
    private function userstat($argc = [])
    {
        $method = "get";
        $url = $this->api_url . $this->api_action['userstat'];
        $url = str_replace("{username}", $argc['username'], $url);

        $post = $this->curl($url, $argc, $method);
        return $post;
    }

    /**
     * 批量用户在线状态查询
     * @param array $argc
     * @return mixed
     */
    private function _userstat($argc = [])
    {
        $method = "get";
        $url = $this->api_url . $this->api_action['_userstat'];

        if(!empty($argc)){
            foreach ($argc as &$value){
                $value = $this->name_prefix . $value;
            }
        }

        $post = $this->curl($url, $argc, $method);
        return $post;
    }

    /**
     * 生成密码
     * @param string $username
     * @return string
     */
    private function gen_pass($username = "")
    {
        return md5($this->name_prefix . $username);
    }

    private function header()
    {
        $str = $this->app_key . ":" . $this->master_secret;
        $base64 = base64_encode($str);

        $header = [
            'Content-Type: application/json',
            'Authorization: Basic ' . $base64
        ];
        return $header;
    }

    private function curl($url = "", $argc = [], $method = 'get')
    {
        $is_json = true;
        $timeout = 30;

        return $this->_curl($url, $argc, $is_json, $method, $timeout, $this->header());
    }

    private function _curl($url, $data = null, $json = false, $method = 'POST', $timeout = 30, $header = [])
    {
        $ssl = substr(trim($url), 0, 8) == "https://" ? true : false;
        $ch = curl_init();
        $fields = $data;
        $headers = [];
        if ($json && is_array($data)) {
            $fields = json_encode($data);
            $headers = [
                "Content-Type: application/json",
                'Content-Length: ' . strlen($fields),
            ];
        }

        if (!empty($header)) {
            $headers = array_merge($header, $headers);
        }

        $opt = [
            CURLOPT_URL => $url,
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($ssl) {
            $opt[CURLOPT_SSL_VERIFYHOST] = 1;
            $opt[CURLOPT_SSL_VERIFYPEER] = false;
        }
        curl_setopt_array($ch, $opt);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}