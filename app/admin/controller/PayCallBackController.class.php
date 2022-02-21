<?php
namespace Admin\Controller;

use Common\Controller\CommonController;

class PayCallBackController extends CommonController
{
    private $host;

    public function __construct()
    {
        parent::__construct();

        $this->host = "//" . $_SERVER['HTTP_HOST'];

        //代付成功后的回调
        $action_name = [
            'chinagpay', //爱农支付
        ];
        if (!in_array(ACTION_NAME, $action_name)) {
            $this->error("Error Method.");
        }

        $this->pay_callback(ACTION_NAME);
    }

    /**
     * 爱农支付 -- 异步回调
     */
    private function chinagpay()
    {
        $fail = 'fail';
        $success = 'success';
        $callback = I('post.');

        if(empty($callback) || !isset($callback['respCode'])){
            exit($fail);
        }

        $order_id = $callback['merOrderId'];
        $resp_code = $callback['respCode'];
        $resp_msg = $callback['respMsg'];

        $time = time();
        $withdraw = isset($withdraw) ? $withdraw : M('withdraw');
        $withdraw_log = isset($withdraw_log) ? $withdraw_log : M('withdraw_log');
        $withdraw_log_con = isset($withdraw_log_con) ? $withdraw_log_con : M('withdraw_log_content');

        if(in_array($resp_code, [1001])){ //交易成功
            $state = 3;
            $state_con = 2;
        }elseif(in_array($resp_code, [1111])){ //交易进行中
            $state = 1;
            $state_con = 1;
        }else{ //交易失败||已退款
            $state = 5;
            $state_con = 3;
        }

        $withdraw_log->where(['batchNo' => $order_id])->save(['uptime' => $time, 'state' => $state]);
        $withdraw_log_con->where(['orderNumber' => $order_id])->save(['state' => $state_con]);
        logs($callback, 'chinagpay_callback', '/PayCallBack/chinagpay/' . date('Y-m-d'));
        exit($success);
    }

    /**
     * 重定向到具体的回调方法
     * @param string $method
     */
    private function pay_callback($method = 'chinagpay')
    {
        $this->$method();
    }
}