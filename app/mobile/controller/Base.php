<?php
namespace app\mobile\controller;

use think\captcha\Captcha;

class Base  extends \app\common\controller\Common {
	public function _initialize() {
		parent::_initialize();
		
		$this->assign(['config'=>$this->config]);
	}

	/**
     * 请求来源判断
     * @param $method
     */
    protected  function method_filter($method = 'post')
    {
        $_method = $this->request->method(true);
        if (strtolower($method)!==strtolower($_method)) $this->output(10400, lang('lan_orders_illegal_request'));
    }

	protected function output($code, $msg = '', $data = []){
        header('Content-type: application/json;charset=utf-8');
        $return = ['code' => $code, 'message' => $msg, 'result' => $data];
        exit(json_encode($return));
    }
    /**
     * 验证码
     * @param string $captcha 验证码
     * @return bool
     */
    public function verifyCaptcha($captcha = '')
    {
        if ((new Captcha())->check($captcha)) {
            return true;
        }

        return false;
    }
}
