<?php
namespace app\admin\controller;
use think\Db;

class Download extends Admin {
    public function _initialize(){
        parent::_initialize();
    }
    //空操作
    public function _empty(){
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }
    public function index(){
        $qr_code=Db::name('Config')->where(['key'=>['in','android_qr_code,iso_qr_code']])->select();
        $code=[];
        if(!empty($qr_code)){
            foreach ($qr_code as $value){
                if($value['key']=='android_qr_code'){
                    $code['android_qr_code']=$value['value'];
                }elseif($value['key']=='iso_qr_code'){
                    $code['iso_qr_code']=$value['value'];
                }

            }
        }

    	$currency=Db::name('currency')->field('currency_id,currency_name,qianbao_url')->select();
        $this->assign('list',$currency);
        $this->assign('qr_code',$code);
    	return $this->fetch();
    }
    //@标二维码上传
    public function qr_code(){
        if($_FILES['qr_code']['size'] > 0){
            $upload = $this->oss_upload($file=[], $path = 'qr_code');
            if (empty($upload)||empty($_POST['code'])) {
                $this->error('二维码上传失败');
            }
            $qr_code=$_POST['code'];
            $r=Db::name('Config')->where(['key'=>$qr_code])->setField('value', trim($upload['qr_code']));
            if($r){
               return $this->success('二维码上传成功',url('Download/index'));
            }else{
               return $this->error('二维码上传失败');
            }
        }else{
           return $this->error('没有要上传的文件');
        }
    }
    //处理钱包上传
    public function qianbaoDownload(){
    	if(!empty($_FILES)){
//    				$upload = new \Think\Upload();// 实例化上传类
//			    	$upload->maxSize   =     99999999 ;// 设置附件上传大小
//			    	$upload->exts      =     array('zip', 'rar','CAB ','ISO ');// 设置附件上传类型
//			    	$upload->savePath  =      './Public/Uploads/'; // 设置附件上传目录
//			    	// 上传文件
//			    	$info   =  $upload->upload();
//			    	if(!$info) {
//			    		// 上传错误提示错误信息
//			    		$this->error($upload->getError());exit();
//			    	}else{
//			    		// 上传成功
//			    		foreach ($info as $k=>$v){
//			    			$url=$v['savepath'].$v['savename'];
//			    			$r[]=M('Currency')->where('currency_id='.$k)->setField('qianbao_url','/Uploads'.ltrim($url,"."));
//			    		}
//			    		if(isset($r)){
//			    			$this->success('上传成功');
//			    		}else{
//			    			$this->error('上传失败');
//			    		}
//			    	}
            $file = [];
            foreach($_FILES as $key=>$value){
                if($value['size'] > 0){
                    $file[$key] = $value;
                }
            }
            $upload = $this->oss_upload($file, $path = 'wallets');
            if (empty($upload)) {
                $this->error('钱包上传失败');
            }
            foreach($upload as $k=>$v){
                $r = M('Currency')->where(array('currency_id' => $k))->setField('qianbao_url', trim($v));
                if(!$r){
                    $this->success('上传失败');
                }else{
                    $this->error('上传成功');
                }
            }

        }
    }
    public function biaogeDownload(){
//    	$upload = new \Think\Upload();// 实例化上传类
//    	$upload->maxSize   =     3145728 ;// 设置附件上传大小
//    	$upload->exts      =     array('xls', 'xlsx');// 设置附件上传类型
//    	$upload->savePath  =      './Public/Uploads/'; // 设置附件上传目录
//    	// 上传文件
//    	$info   =  $upload->upload();
//    	if(!$info) {
//    		// 上传错误提示错误信息
//    		$this->error($upload->getError());exit();
//    	}else{
//    		// 上传成功
//    		foreach ($info as $k=>$v){
//    			$url=$v['savepath'].$v['savename'];
//    			$url='/Uploads'.ltrim($url,".");
//    			$r=M('Config')->where("yang_config.key='biaoge_url'")->setField('value',$url);
//    		}
//    		if(isset($r)){
//    			$this->success('上传成功');
//    		}else{
//    			$this->error('上传失败');
//    		}
//    	}

        if($_FILES['biaoge']['size'] > 0){
            $upload = $this->oss_upload($file=[], $path = 'biaoge');
            if (empty($upload)) {
                $this->error('上传失败');
            }
            $r=M('Config')->where("yang_config.key='biaoge_url'")->setField('value', trim($upload['biaoge']));
            if(!$r){
                $this->success('上传失败');
            }else{
                $this->error('上传成功');
            }
        }else{
            $this->error('没有要上传的文件');
        }

    }

    //白皮书上传
    public function baipishuDownload(){
        if($_FILES['baipishu']['size'] > 0){
            $upload = $this->oss_upload($file=[], $path = 'baipishu');
            if (empty($upload)) {
                $this->error('上传失败');
            }
            $r=M('Config')->where("yang_config.key='baipishu_url'")->setField('value', trim($upload['baipishu']));
            if(!$r){
                $this->success('上传失败');
            }else{
                $this->error('上传成功');
            }
        }else{
            $this->error('没有要上传的文件');
        }

    }

    //分享会上传
    public function fenxiangDownload(){
        if($_FILES['fenxiang']['size'] > 0){
            $upload = $this->oss_upload($file=[], $path = 'fenxiang');
            if (empty($upload)) {
                $this->error('上传失败');
            }
            $r=M('Config')->where("yang_config.key='fenxiang_url'")->setField('value', trim($upload['fenxiang']));
            if(!$r){
                $this->success('上传失败');
            }else{
                $this->error('上传成功');
            }
        }else{
            $this->error('没有要上传的文件');
        }

    }
}