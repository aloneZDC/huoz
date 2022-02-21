<?php
namespace app\admin\controller;
use think\Db;
use think\Request;

class Chat extends Admin {
    /**
     * 列表
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     * Create by: Red
     * Date: 2019/8/21 10:42
     */
    public function index(Request $request){
        $across_id = input('across_id');
        if(!empty($across_id)) {
            $where['a.across_id'] = $across_id;
        } else {
            $where['a.across_id'] = ['gt',0];
        }

        $is_reply = input('is_reply','');
        if($is_reply!='') {
            $is_reply = intval($is_reply);
        } else {
            $is_reply = 0;
        }
        $where['a.is_reply'] = $is_reply;

        $list = Db::name('chat_kf')->alias('a')->field('a.*,b.phone,b.name,b.email,b.head')
            ->where($where)->join(config("database.prefix")."member b","a.across_id=b.member_id","LEFT")
            ->order("a.msg_id desc")->paginate(25,null,['query'=>$request->get()]);
        $show=$list->render();
        $this->assign('list',$list);
        $this->assign('page',$show);
       return $this->fetch();
    }

    public function reply() {
        $member_id = input('member_id', '', 'intval');
        $this->assign('member_id', $member_id);
       return $this->fetch();
    }

    public function chat() {
        $member_id = input('member_id', '', 'intval');
        $this->assign('member_id', $member_id);
       return $this->fetch();
    }

    public function send_messages()
    {
        $member_id = intval(input('member_id'));
        if(empty($member_id)) $this->ajaxReturn("",'用户不存在',0);

        $body = input('msg_body',0,'strval,htmlspecialchars');
        if(empty($body)) $this->ajaxReturn("",'内容不能为空',0);

        $data = [
            'across_id' => 0,
            'target_id' => $member_id,
            'msg_content' => $body,
            'msg_time' => time(),
            'msg_push_type' => 0,
        ];
        $flag = Db::name('chat_kf')->insertGetId($data);


        Db::name('chat_kf')->where('target_id='.$member_id.' or across_id='.$member_id)->update(['is_reply'=>1]);
        if(!$flag)$this->ajaxReturn("",'发送失败',0);

        $this->ajaxReturn("",'发送成功',1);
    }

    /**
     * 取得历史消息
     */
    public function get_messages()
    {
        $member_id = intval(input('member_id'));
        if(empty($member_id)) $this->ajaxReturn(0, '用户不存在',0);

        $member_info = Db::name('member')->where(['member_id' => $member_id])->find();
        $list = Db::name('chat_kf')->where('target_id='.$member_id.' or across_id='.$member_id)->order("msg_id asc")->select();
        $kf_head = model('Member')->kf_head;
        $default_head = model('Member')->default_head;
        $head = empty($member_info['head']) ? $default_head : $member_info['head'];
        if($list) {
            $oss_config = config('aliyun_oss');
            require_once (WEB_PATH.'/../app/extra/aliyun_oss.php');
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
                    $value['head'] = $head;
                    $value['nick'] = $member_info['nick'];
                } else {
                    $value['_position'] = 'l';
                    $value['head'] = $kf_head;
                    $value['nick'] = lang('lan_system');
                }
            }
        } else {
            $list = [];
        }
        $this->ajaxReturn($list,"",SUCCESS);
    }

    public function uploads() {
        $member_id = intval(input('member_id'));
        if(empty($member_id))$this->ajaxReturn("",'用户不存在',0);

        $img = input('img');
        if(empty($img))$this->ajaxReturn("",'文件不能为空',0);

        $attachments_list = $this->oss_base64_upload($img, 'chat_kf');
        if ($attachments_list['Code'] === 0 || count($attachments_list['Msg']) == 0 || empty($attachments_list)) {
            $this->ajaxReturn("",'发送失败',0);
        }

        $data = [
            'across_id' => 0,
            'target_id' => $member_id,
            'msg_content' => $attachments_list['Msg'][0],
            'msg_time' => time(),
            'msg_push_type' => 0,
        ];
        $flag = Db::name('chat_kf')->insertGetId($data);
        if(!$flag) $this->ajaxReturn("",'发送失败',0);

        $this->ajaxReturn(['src'=>$attachments_list['Msg'][0]],'发送成功',1);
    }
}
