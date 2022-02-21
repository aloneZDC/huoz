<?php
namespace app\backend\controller;
use think\Db;
use think\Request;
use OSS\Core\OssException;

class Chat extends AdminQuick {
    protected $public_action = ['uploads'];
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
            $where['a.is_reply'] = $is_reply;
        }

        $list = Db::name('chat_kf')->alias('a')->field('a.*,b.phone,b.name,b.email,b.head')
            ->where($where)->join("member b","a.across_id=b.member_id","LEFT")
            ->order("a.msg_id desc")->paginate(25,null,['query'=>$request->get()]);
        $show=$list->render();
        $this->assign('list',$list);
        $this->assign('page',$show);
       return $this->fetch();
    }

    /**
     * 回复
     */
    public function reply() {
        $member_id = input('member_id', '', 'intval');
        $this->assign('member_id', $member_id);
       return $this->fetch();
    }

    /**
     * 聊天
     */
    public function chat() {
        $member_id = input('member_id', '', 'intval');
        $this->assign('member_id', $member_id);
       return $this->fetch();
    }

    /**
     * 发送消息
     */
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
     * 获取历史消息
     */
    public function get_messages()
    {
        $member_id = intval(input('member_id'));
        if(empty($member_id)) $this->ajaxReturn(0, '用户不存在',0);
        
        $list = Db::name('chat_kf')->where('target_id='.$member_id.' or across_id='.$member_id)->order("msg_id asc")->select();
        $member_info = Db::name('member')->where(['member_id' => $member_id])->find();
        // 头像
        $kf_head = model('Member')->kf_head;
        $default_head = model('Member')->default_head;
        $head = empty($member_info['head']) ? $default_head : $member_info['head'];
        if($list) {
            //require_once (WEB_PATH.'/../app/extra/aliyun_oss.php');
            $oss_config = config('aliyun_oss');
            foreach ($list as $key=> &$value){
                $value['msg_time'] = date('Y-m-d H:i:s',$value['msg_time']);
                if(strpos($value['msg_content'],$oss_config['endpoint'])===false) {
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

    /**
     * 上传图片
     */
    public function uploads() {
        $member_id = intval(input('member_id'));
        if(empty($member_id))$this->ajaxReturn("",'用户不存在',0);

        $img = input('img');
        if(empty($img))$this->ajaxReturn("",'文件不能为空',0);
        \think\Log::write(123);
        $attachments_list = $this->oss_base64_upload($img, 'chat_kf');
        if ($attachments_list['Code'] === 0 || count($attachments_list['Msg']) == 0 || empty($attachments_list)) {
            $this->ajaxReturn("",'发送失败',0);
        }
        \think\Log::write(456);
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

    /**
     * 阿里云OSS文件上传
     * @param array $file
     * @param string $path
     * @return array
     */
    protected function oss_base64_upload($images, $model_path = '', $model_type = 'images', $upload_path = '', $autoName = true)
    {

        $oss_config = config('aliyun_oss');
        $accessKeyId = $oss_config['accessKeyId'];
        $accessKeySecret = $oss_config['accessKeySecret'];
        $endpoint = $oss_config['endpoint'];
        $bucket = $oss_config['bucket'];
        $isCName = false;

        if (empty($images)) return ['Code' => 0, 'Msg' =>lang("lan_filelist_empty")];

        $file_raw = [];
        $file_type = ['pjpeg', 'jpeg', 'jpg', 'gif', 'bmp', 'png'];
        $preg_type = "image";
        $model_type = strtolower($model_type);
        if ($model_type == 'video') {
            $preg_type = $model_type;
            $file_type = ['mov', '3gp', 'mp4', 'avi'];
        }

        if (is_array($images) && count($images) > 0) {
            /*
             * $images 批量上传示例(值为一维单列或多列数组)
             * $images = [
             *      "base64/image1..........."
             *      "base64/image2..........."
             * ]
             */
            foreach ($images as $key => $value) {
                $value = trim($value);
                if (preg_match("/^(data:\s*$preg_type\/(\w+);base64,)/", $value, $result)) {
                    $type = strtolower($result[2]);
                    if (in_array($type, $file_type)) {
                        $file_raw[] = [
                            'raw' => base64_decode(str_replace($result[1], '', $value)), //文件流
                            'extension' => $type, //文件后缀
                            'index' => $key,
                        ];
                    } else {
                        return ['Code' => 0, 'Msg' => lang('lan_filetype_notexists')];
                    }
                } else {
                    return ['Code' => 0, 'Msg' => lang('lan_filebase64_error')];
                }
            }
        }

        if (is_string($images)) {
            /*
             * $images 上传单个示例，字符串
             * $images = "base64/image..........."
             */

            $images = trim($images);
            if (preg_match("/^(data:\s*$preg_type\/(\w+);base64,)/", $images, $result)) {
                $type = strtolower($result[2]);
                if (in_array($type, $file_type)) {
                    $file_raw[] = [
                        'raw' => base64_decode(str_replace($result[1], '', $images)), //文件流
                        'extension' => $type, //文件后缀
                        'index' => 0,
                    ];
                } else {
                    return ['Code' => 0, 'Msg' => lang('lan_filetype_notexists')];
                }
            } else {
                return ['Code' => 0, 'Msg' => lang('lan_filebase64_error')];
            }
        }

        if (empty($upload_path)) {
            $model_path = strstr('/', $model_path) ? $model_path : $model_path . '/';
            $upload_path = "{$model_type}/{$model_path}" . date('Y-m-d') . '/';
        }

        if (!isset($_SERVER['HTTPS']) ||
            $_SERVER['HTTPS'] == 'off'  ||
            $_SERVER['HTTPS'] == '') {
            $scheme = 'http';
        }
        else {
            $scheme = 'https';
        }

        $ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint, $isCName);
        $photo_list = [];
        try {
            if (!empty($file_raw)) {
                foreach ($file_raw as $value) {
                    $name = substr(md5(base64_encode($value['raw']) . base64_encode(time() . mt_rand(33, 126))), 8, 16);
                    if ($autoName === true) {
                        $file_name = $upload_path . $name . "." . strtolower($value['extension']);
                    } else {
                        $file_name = $upload_path;
                    }
                    $getOssInfo = $ossClient->putObject($bucket, $file_name, $value['raw']);
                    $getOssPdfUrl = $getOssInfo['info']['url']?:$scheme . '://' .$bucket.'.'.$endpoint.'/'.$file_name;

                    if ($getOssPdfUrl) {
                        $photo_list[$value['index']] = str_replace("http://", "https://", $getOssPdfUrl);
                    }
                }
            }
        } catch (OssException $e) {
            return ['Code' => 0, 'Msg' => $e->getMessage()];
        }

        return ['Code' => 1, 'Msg' => $photo_list];
    }
}