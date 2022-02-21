<?php

namespace app\common\model;

use OSS\Core\OssException;
use think\Log;
use think\Model;

class MemberContract extends Model
{
    static function order_contract($member_id)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (empty($member_id)) return $r;
        $result = [
            'contract_list' => [
                'https://io-app.oss-cn-shanghai.aliyuncs.com/contract/hzyc_template/1.jpg',
                'https://io-app.oss-cn-shanghai.aliyuncs.com/contract/hzyc_template/2.jpg',
                'https://io-app.oss-cn-shanghai.aliyuncs.com/contract/hzyc_template/3.jpg',
                'https://io-app.oss-cn-shanghai.aliyuncs.com/contract/hzyc_template/4.jpg',
            ],
            'contract_status' => 0// 状态 0未签 1已签
        ];

        $member_contract = self::where(['member_id' => $member_id])->find();
        if ($member_contract) {
            $result_json = json_decode($member_contract['contract_text']);
            $result['contract_list'][1] = $result_json[1];
            $result['contract_list'][3] = $result_json[2];
            $result['contract_status'] = 1;
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    static function submit_autograph($member_id, $autograph)
    {
        $r = ['code' => ERROR1, 'message' => lang("parameter_error"), 'result' => null];
        if (empty($member_id) || empty($autograph)) return $r;

        $member_contract = self::where(['member_id' => $member_id])->find();
        // 判断是否签名
        if ($member_contract) {
            $r['message'] = lang('no_signed');
            return $r;
        }

        // 生成签名文件
        $result = self::generate_contract($autograph, $member_id);
        if ($result === false) {
            $r['message'] = lang('operation_failed');
            return $r;
        }

        // 更新状态
        $res = self::create([
            'member_id' => $member_id,
            'contract_text' => json_encode($result),
            'add_time' => time()
        ]);
        if ($res === false) {
            $r['message'] = lang('operation_failed');
            return $r;
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_operation_success');
        return $r;
    }

    static function generate_contract($watermark_path, $member_id)
    {
        if (empty($watermark_path)) return false;
        // 目录
        $catalogue = 'hzyc_upload';

        if (preg_match("/^(data:\s*image\/(\w+);base64,)/", $watermark_path, $result)) {
            $image_type = strtolower($result[2]);
            if (in_array($image_type, ['jpeg', 'jpg', 'png'])) {
                $mkdir_catalogue = ROOT_PATH . '/public/contract/' . $catalogue . '/';
                if (!is_dir($mkdir_catalogue))
                    mkdir($mkdir_catalogue, 0777, true);
                $new_file_path = $mkdir_catalogue . $member_id . '.' . $image_type;
                $upload_result = file_put_contents($new_file_path, base64_decode(str_replace($result[1], '', $watermark_path)));
                if (empty($upload_result)) return false;
                $result_image_watermark = self::oss_upload('contract/' . $catalogue . '/' . $member_id . '.' . $image_type, $mkdir_catalogue . $member_id . '.' . $image_type);
            } else {
                return false;
            }
        } else {
            return false;
        }

        //合同模板
        $template_image_2 = imagecreatefromjpeg(ROOT_PATH . '/public/contract/template/2.jpg');
        $template_image_5 = imagecreatefromjpeg(ROOT_PATH . '/public/contract/template/4.jpg');

        //插入合同编号
        $contract_code = date('Y-m-d') . ' ' . $member_id;
        $font_path = ROOT_PATH . '/public/static/font/hagin.otf';
        $black = imagecolorallocate($template_image_2, 15, 23, 25);//字体颜色
        imageTtfText($template_image_2, 40, 0, 1550, 430, $black, $font_path, $contract_code);
        imageTtfText($template_image_2, 40, 0, 850, 750, $black, $font_path, $member_id);

        // 签字日期
        imageTtfText($template_image_5, 40, 0, 1600, 2520, $black, $font_path, date('Y-m-d'));

        // 盖章日期
        imageTtfText($template_image_5, 40, 0, 500, 2520, $black, $font_path, date('Y-m-d'));

        //签名图像
        $watermark_image = imagecreatefrompng($new_file_path);

        //合并合同图片
        imagecopy($template_image_2, $watermark_image, 1800, 650, 0, 0, imagesx($watermark_image), imagesy($watermark_image));
        imagecopy($template_image_5, $watermark_image, 1850, 2320, 0, 0, imagesx($watermark_image), imagesy($watermark_image));

        //输出合并后合同图片
        imagejpeg($template_image_2, $mkdir_catalogue . $member_id . '_2.jpg');
        $result_image_2 = self::oss_upload('contract/' . $catalogue . '/' . $member_id . '_2.jpg', $mkdir_catalogue . $member_id . '_2.jpg');
        imagejpeg($template_image_5, $mkdir_catalogue . $member_id . '_4.jpg');
        $result_image_5 = self::oss_upload('contract/' . $catalogue . '/' . $member_id . '_4.jpg', $mkdir_catalogue . $member_id . '_4.jpg');

        if (empty($result_image_2)
            || empty($result_image_5)
        ) return false;
        return [$result_image_watermark, $result_image_2, $result_image_5];
    }

    static function oss_upload($object, $content)
    {
        $oss_config = config('aliyun_oss');
        $accessKeyId = $oss_config['accessKeyId'];
        $accessKeySecret = $oss_config['accessKeySecret'];
        $endpoint = $oss_config['endpoint'];
        $bucket = $oss_config['bucket'];
        $ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint, false);
        try {
            if (!empty($object)
                && !empty($content)
            ) {
                $getOssInfo = $ossClient->uploadFile($bucket, $object, $content);
                $scheme = (!isset($_SERVER['HTTPS']) ||
                    $_SERVER['HTTPS'] == 'off' ||
                    $_SERVER['HTTPS'] == '') ? 'http' : 'https';
                return $getOssInfo['info']['url'] ?: $scheme . '://' . $bucket . '.' . $endpoint . '/' . $object;
            }
        } catch (OssException $e) {
            Log::write("合同上传:失败" . $e->getMessage());
            return false;
        }
    }
}