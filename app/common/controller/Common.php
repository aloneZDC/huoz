<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\controller;
use think\Config;
use think\Controller;
use think\db;
use think\Log;

class Common extends Controller
{
    protected $login_keep_time = 7200; //登录保持时间
    protected $config;
    protected $cache_name = '';
    protected $uuid = '';
    protected $lang = '';
    protected $coin_list = [
        'IOSCORE',
         'XRP',
        'USDTBB',
    ];

    public function _initialize()
    {
        $this->initConfig();
        $this->initUUid();
        $this->lang = $this->getLang(true);
//        $list=Db::name("currency")->field("currency_name")->select();
        //$this->coin_list=array_column($list,"currency_name");//显示全部交易对
    }

    //公共配置
    private function initConfig()
    {
        $this->config = model('Config')->byField();
    }

    private function initUUid()
    {
        $uuid = input('post.uuid','');
        if (empty($this->uuid) && !empty($uuid)) {
            $this->uuid = $uuid;
            $this->cache_name = 'uuid_'.$this->uuid;
        }
    }
    /**
     * HTTP转HTTPS
     * @param $string
     * @return mixed
     */
    protected static function http_to_https($string)
    {
        return str_replace("http://", "http://", $string);
    }

    /**
     * 实名认证文件上传
     * @param $member_id
     * @return array
     */
    protected function upload_auth($member_id)
    {

        $oss_config = config('aliyun_oss');
        $accessKeyId = $oss_config['accessKeyId'];
        $accessKeySecret = $oss_config['accessKeySecret'];
        $endpoint = $oss_config['endpoint'];
        $bucket = $oss_config['bucket'];


        $isCName = false;

        $file1_raw = null;
        $file2_raw = null;
        $file3_raw = null;

        if (empty($member_id) || !intval($member_id) > 0) {
            return ['Code' => 0, 'Msg' =>lang('lan_certificate_error')];
        }

        $ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint, $isCName);
        $image_type = ['pjpeg', 'jpeg', 'jpg', 'gif', 'bmp', 'png'];

        //debug($ossClient);
        $file_raw = [];
        //debug($_FILES);
        if ($_FILES['auth_1']['size'] > 0) {
            $file1_raw = file_get_contents($_FILES['auth_1']['tmp_name']);
            $name = substr(md5(base64_encode(time() . mt_rand(33, 126))), 8, 16);
            $extension = strtolower(pathinfo($_FILES['auth_1']['name'])['extension']);

            if (!in_array($extension, $image_type)) {
                return ['Code' => 0, 'Msg' => lang('lan_certificate_error')];
            }

            $object = "auth_photo/{$member_id}/{$name}." . $extension;
            $file_raw[] = [
                'file_name' => "auth_1",
                'file_raw' => $file1_raw,
                'object' => $object,
            ];
        } else {
            return ['Code' => 0, 'Msg' => lang('lan_upload_front_photo')];
        }

        if ($_FILES['auth_2']['size'] > 0) {
            $file2_raw = file_get_contents($_FILES['auth_2']['tmp_name']);
            $name = substr(md5(base64_encode(time() . mt_rand(33, 126))), 8, 16);
            $extension = strtolower(pathinfo($_FILES['auth_2']['name'])['extension']);

            if (!in_array($extension, $image_type)) {
                return ['Code' => 0, 'Msg' => lang('lan_certificate_error')];
            }

            $object = "auth_photo/{$member_id}/{$name}." . $extension;
            $file_raw[] = [
                'file_name' => "auth_2",
                'file_raw' => $file2_raw,
                'object' => $object,
            ];
        } else {
            return ['Code' => 0, 'Msg' =>lang('lan_upload_reverse_photo')];
        }

        if ($_FILES['auth_3']['size'] > 0) {
            $file3_raw = file_get_contents($_FILES['auth_3']['tmp_name']);
            $name = substr(md5(base64_encode(time() . mt_rand(33, 126))), 8, 16);
            $extension = strtolower(pathinfo($_FILES['auth_3']['name'])['extension']);

            if (!in_array($extension, $image_type)) {
                return ['Code' => 0, 'Msg' =>lang('lan_certificate_error')];
            }

            $object = "auth_photo/{$member_id}/{$name}." . $extension;
            $file_raw[] = [
                'file_name' => "auth_3",
                'file_raw' => $file3_raw,
                'object' => $object,
            ];
        } else {
            return ['Code' => 0, 'Msg' => lang('lan_certificate_error')];
        }

        if (isset($_FILES['auth_4']['size']) && $_FILES['auth_4']['size'] > 0) {
            $file3_raw = file_get_contents($_FILES['auth_4']['tmp_name']);
            $name = substr(md5(base64_encode(time() . mt_rand(33, 126))), 8, 16);
            $extension = strtolower(pathinfo($_FILES['auth_4']['name'])['extension']);

            if (!in_array($extension, $image_type)) {
                return ['Code' => 0, 'Msg' =>  lang('lan_certificate_error')];
            }

            $object = "auth_photo/{$member_id}/other_{$name}." . $extension;
            $file_raw[] = [
                'file_name' => "auth_4",
                'file_raw' => $file3_raw,
                'object' => $object,
            ];
        }
        //debug($file_raw);
        if (!isset($_SERVER['HTTPS']) ||
            $_SERVER['HTTPS'] == 'off'  ||
            $_SERVER['HTTPS'] == '') {
            $scheme = 'http';
        }
        else {
            $scheme = 'https';
        }

        $photo_list = [];
        try {
            if (!empty($file_raw)) {
                foreach ($file_raw as $value) {
                    $getOssInfo = $ossClient->putObject($bucket, $value['object'], $value['file_raw']);
                    $getOssPdfUrl = $getOssInfo['info']['url']?:$scheme . '://' .$bucket.'.'.$endpoint.'/'.$value['object'];
                    if ($getOssPdfUrl) {
                        $photo_list[$value['file_name']] = self::http_to_https($getOssPdfUrl);
                    }
                }
            }
        } catch (OssException $e) {
            return ['Code' => 0, 'Msg' => $e->getMessage()];
        }

        return ['Code' => 1, 'Msg' => $photo_list];
    }
    /**
     * 阿里云OSS文件上传
     * @param array $file
     * @param string $path
     * @return array
     */
    protected function oss_upload($file = [], $path = 'file')
    {
        $oss_config = config('aliyun_oss');
        $accessKeyId = $oss_config['accessKeyId'];
        $accessKeySecret = $oss_config['accessKeySecret'];
        $endpoint = $oss_config['endpoint'];
        $bucket = $oss_config['bucket'];
        $isCName = false;
        $arr = array();

        $ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint, $isCName);

        $file = !empty($file) ? $file : $_FILES;
        if (empty($file)) {
            $this->error("没有可上传文件");
        }

        $date_path = date("Y-m-d");
        foreach ($file as $key => $value) {
            $file_raw = file_get_contents($value['tmp_name']);
            $name = substr(md5($value['name'] . time() . mt_rand(33, 126)), 8, 16) . '.' . strtolower(pathinfo($value['name'])['extension']);
            $object = $path . "/{$date_path}/" . $name;
            $arr[] = [
                'file_name' => $key,
                'file_raw' => $file_raw,
                'object' => $object,
            ];
        }
        if (!isset($_SERVER['HTTPS']) ||
            $_SERVER['HTTPS'] == 'off'  ||
            $_SERVER['HTTPS'] == '') {
            $scheme = 'http';
        }
        else {
            $scheme = 'https';
        }
        $photo_list = [];
        try {
            if (!empty($arr)) {
                foreach ($arr as $value) {

                    $getOssInfo = $ossClient->putObject($bucket, $value['object'], $value['file_raw']);
                    $getOssPdfUrl =  $getOssInfo['info']['url']?:$scheme . '://' .$bucket.'.'.$endpoint.'/'.$value['object'];
                    if ($getOssPdfUrl) {
                        $photo_list[$value['file_name']] = self::http_to_https($getOssPdfUrl);
                    }
                }
            }
        } catch (OssException $e) {
            $this->error($e->getMessage());
        }

        return $photo_list;
    }
    //base64文件大小
    protected function checkFileSize($img,$size=5242880) {
        $start = strpos($img, 'base64,');
        if($start) $img = substr($img, $start+7);
        $img = base64_decode($img);
        if(strlen($img)>$size) return false;

        return true;
    }

    //base64图片上传
    protected function base64Upload($img,$path='bank'){
        if(!$this->checkFileSize($img)) return lang('lan_picture_to_big');

        $img = @json_decode(base64_decode($img), true);
        //上传附件
        $attachments_list = $this->oss_base64_upload($img, $path);

        if (empty($attachments_list) || $attachments_list['Code'] === 0 || count($attachments_list['Msg']) == 0) {
            return $attachments_list['Msg'];
        }
        return ['path'=>$attachments_list['Msg'][0]];
    }

        /**
     * 获取语言包-数据库对应字段
     */
    protected function getLang($flag=true) {
        $lang = input('language');
        if(empty($lang)) $lang = cookie('think_language');

        if(empty($lang) || !in_array($lang, config('extend.lang_list'))) $lang = config('default_lang');
        if($flag) \think\Lang::load(APP_PATH . 'lang' . DS . $lang . EXT);

        switch ($lang) {
            case 'en-us':
                return 'en';
            case 'zh-tw':
                return 'tc';
        }
        return 'tc';
    }

    /**
     * 添加财务日志方法
     * @param unknown $member_id
     * @param unknown $type
     * @param unknown $content
     * @param unknown $money
     * @param unknown $money_type 收入=1/支出=2
     * @param unknown $currency_id 积分类型id 0是rmb
     * @return
     */
    public function addFinance($member_id, $type, $content, $money, $money_type, $currency_id, $trade_id=0)
    {
        $data = [
            'member_id' => $member_id,
            'trade_id' => $trade_id,
            'type' => $type,
            'content' => $content,
            'money_type' => $money_type,
            'money' => $money,
            'add_time' => time(),
            'currency_id' => $currency_id,
            'ip' => get_client_ip_extend(),
        ];

        $list = Db::name('finance')->insertGetId($data);
        if ($list) {
            return $list;
        } else {
            return false;
        }
    }

    /**
     * Base64上传文件
     * @param $images
     * @param string $model_path
     * @param string $model_type
     * @param string $upload_path
     * @param bool $autoName
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
        if (!Config::get("app_encrypt")) {
            exit(json_encode($data));
        } else {
            //加密返回
            $data = ir_gzip_encode($data);
            echo $data;
            exit();
        }
    }

    /**输出json格式数据
     * @param array $data
     * Created by Red.
     * Date: 2018/7/9 10:45
     */
    protected function output_new($data = []){
        header('Content-Type:application/json; charset=utf-8');
        if (func_num_args() > 2) {
            $args = func_get_args();
            array_shift($args);
            $info = array();
            $info['code'] = $data;
            $info['message'] = array_shift($args);
            $info['result'] = array_shift($args);
            $data=$info;
        }
        //不加密模式
        if (!Config::get("app_encrypt")) {
            exit(json_encode($data));
        } else {
            //加密返回
            $data = ir_gzip_encode($data);
            echo $data;
            exit();
        }
    }

    /**
     * 加密返回的
     * @param $data
     * Create by: Red
     * Date: 2019/9/7 10:30
     */
    protected function ajaxReturn($data) {
        header('Content-Type:application/json; charset=utf-8');
        //不加密模式
        if (!Config::get("app_encrypt")) {
            exit(json_encode($data));
        } else {
            //加密返回
            $data = ir_gzip_encode($data);
            echo $data;
            exit();
        }
    }

    /**
     * 不加密返回
     * @param $data
     * Create by: Red
     * Date: 2019/9/7 10:30
     */
    protected function mobileAjaxReturn($data){
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($data));
    }
    /*
  * 获取语音包
  */
    protected function getLangNamePc() {
        $lang = cookie('think_language');
        $lang=strtolower($lang);
        if(empty($lang) || strpos(config('LANG_LIST'), $lang)===false) $lang = config('DEFAULT_LANG');

        $name = '';
        switch ($lang) {
            case 'en-us':
                $name = 'en';
                break;
            case 'zh-tw';
                $name = 'tc';
                break;
            default:
                break;
        }
        return $name;
    }
    protected function _deny($message)
    {
        $this->output(10001, $message);
    }

    public function _empty()
    {
        $this->output(10001, 'deny');
    }

}
