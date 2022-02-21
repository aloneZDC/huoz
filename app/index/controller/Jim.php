<?php
namespace app\index\controller;
use think\Db;
use think\Exception;

class Jim extends Base
{
    protected $public_action = ['chat','upload','send_messages','get_messages']; //无需登录即可访问
    private $token = '';

    //聊天页面
    public function chat() {
        $trade_id = intval(cookie('trade_id'));

        $config = [];
        if($this->member_id) {
            $this->assign('access_key',$this->token);
            $config = model('Jim')->getTradeConfig($this->member_id,$trade_id);
        }
        $this->assign(['access_key'=>$this->token,'config'=>$config]);
        return $this->fetch();
    }

    public function upload() {
        $img = input('img');
        if(empty($img)) $this->output(10102,lang('lan_network_busy_try_again'));

        $attachments_list = $this->oss_base64_upload($img, 'jim');
        if ($attachments_list['Code'] === 0 || count($attachments_list['Msg']) == 0 || empty($attachments_list)) {
            $this->output(10102,lang('lan_network_busy_try_again'));
        }

        $this->output(10000,lang('lan_operation_success'),['src'=>$attachments_list['Msg'][0]]);
    }

    public function send_messages()
    {
        $send_id = input('send_id','','strval');
        $send_type = input('send_type','','strval');
        $from_id = input('from_id',0,'intval');
        $target_id = input('target_id',0,'intval');
        $body = input('msg_body',0,'strval,htmlspecialchars');

        if(empty($send_id) || empty($send_type) || empty($from_id) || empty($target_id) || empty($body)) $this->output_jim(0,lang('lan_orders_send_failure'));

        $access_key = input('access_key','','strval');
        if(!$this->member_id) $this->output_jim(0,lang('lan_orders_send_failure'));

        $flag = model('Jim')->send_messages($send_id,$send_type,$from_id,$target_id,$body);
        if(!is_array($flag)) $this->output_jim(0,lang('lan_orders_send_failure'));

        $this->output_jim(1,$flag);
    }

    /**
     * 取得历史消息
     */
    public function get_messages()
    {
        $send_id = input('order_id');
        $order_user_id = intval(input('order_user_id'));
        $access_key = input('access_key','','strval');
        if(empty($send_id) || empty($order_user_id)) $this->output_jim(1,[]);

        if(empty($access_key)) $access_key = '';
        if(!$this->member_id) $this->output_jim(1,[]);

        $list = model('Jim')->get_messages($this->member_id,$send_id);
        $this->output_jim(1,$list);
    }

    private function output_jim($code=0,$msg=''){
        exit(json_encode(['Code' => $code, 'Msg' => $msg]));
    }
}