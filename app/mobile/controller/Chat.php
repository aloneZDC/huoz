<?php
/**
 *泰国会议报名
 */
namespace app\mobile\controller;
use think\Db;
use think\Exception;

class Chat extends Base{
	public function index(){
        $key = cookie('token');
        $token_id = cookie('uuid');
        $think_language = cookie('think_language');
        if(!empty($key)) cookie('token',$key);
        if(!empty($token_id)) cookie('uuid',$token_id);
        if(!empty($think_language)) cookie('think_language',$think_language);

        $this->assign('email',$this->config['contact_email']);
		return $this->fetch('chat/index');
	}

    public function send_messages()
    {
        $member_id = $this->checkLogin();
        if($member_id===false) $this->output(10100,lang('lan_modifymember_please_login_again'));

        $body = input('msg_body',0,'strval,htmlspecialchars');
        if(empty($body)) $this->output(10001,lang('lan_orders_send_failure'));

        $time = time();
        $is_auto_reply = false;
        $last_msg = Db::name('chat_kf')->where(['across_id'=>$member_id,'is_reply'=>0])->order('msg_id asc')->find();
        if($last_msg) {
            $auto_start = $last_msg['msg_time'] + intval($this->config['auto_reply']);
            if($auto_start<$time) {
                $is_auto = Db::name('chat_kf')->where(['msg_id'=>['gt',$last_msg['msg_id']],'target_id'=>$member_id,'msg_push_type'=>1])->find();
                if(!$is_auto) $is_auto_reply = true;
            }   
        }

        $data = [
            'across_id' => $member_id,
            'target_id' => 0,
            'msg_content' => $body,
            'msg_time' => $time,
            'msg_push_type' => 0,
        ];

        $flag = Db::name('chat_kf')->insertGetId($data);
        if(!$flag) $this->output(10001,lang('lan_orders_send_failure'));

        if($is_auto_reply) {
            Db::name('chat_kf')->insertGetId([
                'across_id' => 0,
                'target_id' => $member_id,
                'msg_content' => lang('lan_auto_reply'),
                'msg_time' => $time,
                'msg_push_type' => 1,
            ]);
        }

        $this->output(10000,lang('lan_user_send_success'));
    }

    /**
     * 取得历史消息
     */
    public function get_messages()
    {
        $member_id = $this->checkLogin();
        if($member_id===false) $this->output(10100,lang('lan_modifymember_please_login_again'));

        $member_id = intval($member_id);
        $list = Db::name('chat_kf')->where('target_id='.$member_id.' or across_id='.$member_id)->order("msg_id asc")->select();

        $default_head = model('Member')->default_head;
        if($list) {
            $oss_config = config('aliyun_oss');
            foreach ($list as $key=> &$value){
                $value['msg_time'] = date('Y-m-d H:i:s',$value['msg_time']);
                if(strpos($value['msg_content'], $oss_config['endpoint'])==false) {
                    $value['msg_content'] = htmlspecialchars_decode($value['msg_content']);
                    $value['type'] = 'txt';
                } else {
                    $value['msg_content'] = str_replace('https', 'http', $value['msg_content']);
                    $value['type'] = 'image';
                }
                if($member_id == $value['across_id']){
                    $value['_position'] = "r";
                    $value['head'] = $default_head;
                    $value['nick'] = '';
                } else {
                    $value['_position'] = 'l';
                    $value['head'] = $default_head;
                    $value['nick'] = lang('lan_system');
                }
            }
        } else {
            $list = [];
        }
        $this->output(10000,'',$list);
    }

    public function upload() {
        $member_id = $this->checkLogin();
        if($member_id===false) $this->output(10100,lang('lan_modifymember_please_login_again'));

        $img = input('img');
        if(empty($img)) $this->output(10001,lang('lan_network_busy_try_again'));

        $attachments_list = $this->oss_base64_upload($img, 'chat_kf');
        if ($attachments_list['Code'] === 0 || count($attachments_list['Msg']) == 0 || empty($attachments_list)) {
            $this->output(10001,lang('lan_orders_send_failure'));
        }

        $data = [
            'across_id' => $member_id,
            'target_id' => 0,
            'msg_content' => $attachments_list['Msg'][0],
            'msg_time' => time(),
            'msg_push_type' => 0,
        ];
        $flag = Db::name('chat_kf')->insertGetId($data);
        if(!$flag) $this->output(10001,lang('lan_orders_send_failure'));

        $this->output(10000,lang('lan_user_send_success'),['src'=>$attachments_list['Msg'][0]]);
    }


    private function checkLogin() {
        $key = urldecode(cookie('token'));
        $uuid = urldecode(cookie('uuid'));
        if(empty($key) || empty($uuid)) return false;

        $token = cache('uuid_'.$uuid,'',$this->login_keep_time);
        if(empty($token)) return false;
        if(!isset($token['user_id'])) return false;

        $user_id = intval($token['user_id']);
        if(empty($user_id)) return false;

        //防止多端登录
        $token_c = cache('auto_login_' . $user_id, '', $this->login_keep_time);
        if(empty($token_c) || $token_c!=$key) return false;

        return $user_id;
    }
}
