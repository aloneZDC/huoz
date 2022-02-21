<?php
// +------------------------------------------------------
// | Author:
// +------------------------------------------------------
namespace app\h5\controller;

use think\captcha\Captcha;
use think\Config;
use think\Exception;
use think\Db;

class Base extends \app\common\controller\Common
{
    protected $public_action = []; //无需登录即可访问
    protected $is_method_filter = false; //是否验证请求方式,默认不验证
    protected $login_keep_time = 7200; //登录保持时间
    protected $member_id = false;
    protected $member = [];
    protected $exchange_rate_type = NEW_PRICE_UNIT; //类型：USD：美元，CNY：人民币
    protected $is_decrypt = true;

    public function _initialize()
    {
        parent::_initialize();

        $this->getLang(true);
        //用户请求后重置过期时间
        $this->reLogin();
        //请求方式验证
        if ($this->is_method_filter) $this->method_filter('post');
        //登录验证
        $this->public_action = array_flip(array_change_key_case(array_flip($this->public_action), CASE_LOWER));
        $action = strtolower($this->request->action());
        if (!$this->checkLogin() && !in_array($action, $this->public_action)) {
            $this->output_new([
                'code' => 10100,
                'message' => lang('lan_modifymember_please_login_first'),
                'result' => [
                    'dowm_url' => Version_ios_DowUrl,
                ],
            ]);
        }
    }

    //用户请求后重置过期时间
    protected function reLogin()
    {
        $this->checkLogin(true);
    }

    //检测是否登录
    protected function checkLogin($reset = false)
    {
        if ($this->member_id) return true;

        $key = input('post.key', '', 'strval');
        if (!empty($key)) {
            $token_id = input("post.token_id", '', 'intval');
            $token = cache('auto_login_' . $token_id, '', $this->login_keep_time);
            if ($token === $key) {
                $this->member_id = $token_id;
                if (!empty($this->uuid)) $this->member = cache($this->cache_name, '', $this->login_keep_time);

                if ($reset) {
                    //重置过期时间
                    cache('auto_login_' . $token_id, $token, $this->login_keep_time);
                    if (!empty($this->uuid)) cache($this->cache_name, $this->member, $this->login_keep_time);
                }
                return true;
            } else {
                $userInfo = Db::name('member')->where('member_id', $token_id)->find();
                if ($userInfo['token_value'] === $key) {
                    $token = $userInfo['token_value'];
                    $this->member_id = $token_id;
                    if (!empty($this->uuid)) $this->member = cache($this->cache_name, '', $this->login_keep_time);

                    if ($reset) {
                        //重置过期时间
                        cache('auto_login_' . $token_id, $token, $this->login_keep_time);
                        if (!empty($this->uuid)) cache($this->cache_name, $this->member, $this->login_keep_time);
                    }
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 请求来源判断
     * @param $method
     */
    protected function method_filter($method = 'post')
    {
        $_method = $this->request->method(true);
        if (strtolower($method) !== strtolower($_method)) {
            $this->output_new([
                'code' => 10400,
                'message' => lang('lan_orders_illegal_request'),
                'result' => null,
            ]);
        }
    }

    /**
     *10000 成功
     *10100 请先登录
     *10001 错误码
     *10002 错误码
     *...
     */
    protected function output($code, $msg = '', $data = [])
    {
        header('Content-type: application/json;charset=utf-8');
        $data = ['code' => $code, 'message' => $msg, 'result' => $data];
        //不加密模式
        exit(json_encode($data));
    }

    /**输出json格式数据
     * @param array $data
     * Created by Red.
     * Date: 2018/7/9 10:45
     */
    protected function output_new($data = [])
    {
        header('Content-Type:application/json; charset=utf-8');
        if (func_num_args() > 2) {
            $args = func_get_args();
            array_shift($args);
            $info = array();
            $info['code'] = $data;
            $info['message'] = array_shift($args);
            $info['result'] = array_shift($args);
            $data = $info;
        }
        exit(json_encode($data));
    }
}
