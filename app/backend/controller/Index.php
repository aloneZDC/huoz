<?php

namespace app\backend\controller;

use app\backend\model\BackendMenu;
use OSS\Core\OssException;
use think\Log;
use think\Db;

/**
 * Class Index
 * @package app\backend\model
 */
class Index extends Admin
{
    protected $is_mobile_support = true;
    /**
     * 首页
     * @return mixed
     */
    public function index()
    {
        $menus = [
            [
                "icon" => "/static/moblie/images/icon1.png",
                "name" => "待汇总表",
                "url" => url('Wallet/summaryList', ['param' => 'summarylist'])
            ],
            [
                "icon" => "/static/moblie/images/icon2.png",
                "name" => "待汇总确认表",
                "url" => url('Wallet/waitSummaryList', ['param' => 'waitsummarylist'])
            ],
            [
                "icon" => "/static/moblie/images/icon3.png",
                "name" => "汇总记录表",
                "url" => url('Wallet/historySummary', ['param' => 'historysummary'])
            ],
            [
                "icon" => "/static/moblie/images/icon4.png",
                "name" => "提现积分审核表",
                "url" => url('Wallet/waitCash', ['param' => 'waitcash'])
            ],
            [
                "icon" => "/static/moblie/images/icon5.png",
                "name" => "提现积分待确认表",
                "url" => url('Wallet/confirmCashList', ['param' => 'confirmcashlist'])
            ],
            [
                "icon" => "/static/moblie/images/icon6.png",
                "name" => "结算审核",
                "url" => url("ProjectSide/examine", ['param' => 'examine'])
            ]
        ];
        return $this->fetch(null, compact('menus'));
    }

    public function welcome()
    {
        $info = array(
            '操作系统' => PHP_OS,
            '运行环境' => $_SERVER["SERVER_SOFTWARE"],
            '主机名' => $_SERVER['SERVER_NAME'],
            'WEB服务端口' => $_SERVER['SERVER_PORT'],
            '网站文档目录' => $_SERVER["DOCUMENT_ROOT"],
            '浏览器信息' => substr($_SERVER['HTTP_USER_AGENT'], 0, 40),
            '通信协议' => $_SERVER['SERVER_PROTOCOL'],
            '请求方法' => $_SERVER['REQUEST_METHOD'],
            'ThinkPHP版本' => THINK_VERSION,
            '上传附件限制' => ini_get('upload_max_filesize'),
            '执行时间限制' => ini_get('max_execution_time') . '秒',
            '服务器时间' => date("Y年n月j日 H:i:s"),
            '北京时间' => gmdate("Y年n月j日 H:i:s", time() + 8 * 3600),
            '服务器域名/IP' => $_SERVER['SERVER_NAME'] . ' [ ' . gethostbyname($_SERVER['SERVER_NAME']) . ' ]',
            '用户的IP地址' => $_SERVER['REMOTE_ADDR'],
            '剩余空间' => round((disk_free_space(".") / (1024 * 1024)), 2) . 'M',
        );
        return $this->fetch('index/welcome', ['info' => $info]);
    }

    public function img_upload() {
        $file = $this->request->file('file');
        if($file==null) return $this->successJson(ERROR1,"图片不能为空",null);

        if(!$file->checkImg()) return $this->successJson(ERROR3,"图片不能为空",null);

        $res = $this->oss_upload([$file->getInfo()]);
        if(empty($res) || !is_array($res)) return $this->successJson(ERROR3,"图片上传失败,请重试！",null);

        return $this->successJson(SUCCESS,"获取成功",['path'=>$res[0],'src'=>$res[0]]);
    }

    /**
     * 阿里云OSS文件上传
     * @param array $file
     * @param string $path
     * @return array
     */
    protected function oss_upload($file = [], $path = 'backend')
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
            return [];
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
                        $photo_list[$value['file_name']] = str_replace("http://", "http://", $getOssPdfUrl);
                    }
                }
            }
        } catch (OssException $e) {
            $this->error($e->getMessage());
        }

        return $photo_list;
    }

    public function  oss_file_upload(){
        $upload = $this->oss_upload($file = [], $path = 'backend_ke');
        if(!empty($upload['imgFile'])){
            return json_encode(['error'=>0,'url'=>$upload['imgFile']]);
        }else{
            return json_encode(['error'=>0,'message'=>'上传失败']);
        }
        exit;
    }

    public function menus(){
        $menus = array_values(BackendMenu::getMenus($this->admin['id']));
        return $this->successJson(SUCCESS,"获取成功", $menus);
    }

    public function upload_img() {
        $file = $this->request->file('file');
        if($file==null) return $this->successJson(ERROR1,"图片不能为空",null);

        if(!$file->checkImg()) return $this->successJson(ERROR3,"图片不能为空",null);

        $res = $this->oss_upload([$file->getInfo()]);
        if(empty($res) || !is_array($res)) return $this->successJson(ERROR3,"图片上传失败,请重试！",null);

        return $this->successJson(SUCCESS,"获取成功",$res);
    }
}
