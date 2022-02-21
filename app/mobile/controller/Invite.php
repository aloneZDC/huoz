<?php
namespace app\mobile\controller;

use think\Db;

class Invite extends Base
{
    public function index()
    {
    	$invit_code = input('id','','strval');
        $member=Db::name("member")->field("member_id,invit_code")->where(['invit_code'=>$invit_code])->find();
        if(empty($member)){
            return $this->error("参数错误");
        }
    	$this->assign('id',$member['member_id']);
    	$this->assign('member',$member);
        return $this->fetch();
    }

    public function qrcode() {
        require_once WEB_PATH.'../extend/phpqrcode'.DS.'phpqrcode.php';
        $invit_code = input('invit_code','','strval');
        $size = intval(input('size'));
        if($size>10 || $size<=0) $size = 4;
        $url = urldecode(url('mobile/Reg/mobile', ['invit_code'=>$invit_code], false , $domain = true));
        $object = new \QRcode();
        ob_clean();//这个一定要加上，清除缓冲区
        $object->png($url, false, 'Q', $size, '2');
        exit;
    }
}
