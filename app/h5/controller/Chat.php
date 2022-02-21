<?php


namespace app\h5\controller;

use app\common\model\Member;
use think\Db;

class Chat extends Base
{
    /**
     * 获取历史消息
     */
    public function get_messages()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_not_data");
        $r['result'] = [];

        $where = ['target_id|across_id'=>$this->member_id];
        $msg_id = input('msg_id',0,'intval');
        if ($msg_id) {
            $where['msg_id'] = ['gt', $msg_id];
        }

        $list = Db::name('chat_kf')->where($where)->order("msg_id asc")->select();

        $member_info = Db::name('member')->where(['member_id' => $this->member_id])->find();
        // 头像
        $kf_head = model('Member')->kf_head;
        $default_head = model('Member')->default_head;
        $head = empty($member_info['head']) ? $default_head : $member_info['head'];
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
                if($this->member_id == $value['across_id']){
                    $value['_position'] = "r";
                    $value['head'] = $head;
                    $value['nick'] = $member_info['nick'];
                } else {
                    $value['_position'] = 'l';
                    $value['head'] = $kf_head;
                    $value['nick'] = lang('lan_system');
                }
            }
            $r['code'] = SUCCESS;
            $r['message'] = lang("data_success");
            $r['result'] = $list;
        } else {
            $list = [];
            $r['code'] = SUCCESS;
            $r['message'] = lang("no_data");
        }
        return mobileAjaxReturn($r);
    }

    /**
     * 发送消息
     */
    public function send_messages()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = [];

        $body = input('msg_body',0,'strval,htmlspecialchars');
        if(empty($body)) {
            $r['message'] = lang('lan_orders_send_failure');
            return mobileAjaxReturn($r);
        }

        $time = time();
        $is_auto_reply = false;
        $last_msg = Db::name('chat_kf')->where(['across_id'=>$this->member_id,'is_reply'=>0])->order('msg_id asc')->find();
        if($last_msg) {
            $auto_start = $last_msg['msg_time'] + intval($this->config['auto_reply']);
            if($auto_start<$time) {
                $is_auto = Db::name('chat_kf')->where(['msg_id'=>['gt',$last_msg['msg_id']],'target_id'=>$this->member_id,'msg_push_type'=>1])->find();
                if(!$is_auto) $is_auto_reply = true;
            }
        }

        $data = [
            'across_id' => $this->member_id,
            'target_id' => 0,
            'msg_content' => $body,
            'msg_time' => $time,
            'msg_push_type' => 0,
        ];

        $flag = Db::name('chat_kf')->insertGetId($data);
        if(!$flag) {
            $r['message'] = lang('lan_orders_send_failure');
            return mobileAjaxReturn($r);
        }

        if($is_auto_reply) {
            Db::name('chat_kf')->insertGetId([
                'across_id' => 0,
                'target_id' => $this->member_id,
                'msg_content' => lang('lan_auto_reply'),
                'msg_time' => $time,
                'msg_push_type' => 1,
            ]);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_user_send_success');
        return mobileAjaxReturn($r);
    }

    /**
     * 上传图片
     */
    public function upload()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = [];

        $img = input('img');
        if(empty($img)) {
            $r['message'] = lang("lan_network_busy_try_again");
            return mobileAjaxReturn($r);
        }

        $attachments_list = $this->oss_base64_upload($img, 'chat_kf');
        if ($attachments_list['Code'] === 0 || count($attachments_list['Msg']) == 0 || empty($attachments_list)) {
            $r['message'] = lang("lan_orders_send_failure");
            return mobileAjaxReturn($r);
        }

        $data = [
            'across_id' => $this->member_id,
            'target_id' => 0,
            'msg_content' => $attachments_list['Msg'][0],
            'msg_time' => time(),
            'msg_push_type' => 0,
        ];
        $flag = Db::name('chat_kf')->insertGetId($data);
        if(!$flag) {
            $r['message'] = lang('lan_orders_send_failure');
            return mobileAjaxReturn($r);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_user_send_success');
        $r['result'] = ['src'=>$attachments_list['Msg'][0]];
        return mobileAjaxReturn($r);
    }
}
