<?php
namespace Admin\Controller;
use Admin\Controller\AdminController;
use Home\Controller\PublicController;
class QQlineController extends AdminController {
	//空操作
	public function _initialize(){
		parent::_initialize();
	}
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		$this->display('Public:404');
	}
	Public function add(){
		$Qqline=M('Qqline');
		if(IS_POST){
			
			if(!empty($_POST['agents'])){
				$data['agents']=$_POST['agents'];
			}
			if(!empty($_POST['sort'])){
				$data['sort']=$_POST['sort'];
			}
			if(!empty($_POST['qq'])){
				$data['qq']=$_POST['qq'];
			}
			if(!empty($_POST['alipay_id'])){
			    $data['alipay_id']=$_POST['alipay_id'];
			}
			if(!empty($_POST['margin'])){
			    $data['margin']=$_POST['margin'];
			}
			if(!empty($_POST['wechat_id'])){
			    $data['wechat_id']=$_POST['wechat_id'];
			}
			
			$data['time']=time();
			if(!empty($_POST['id'])){
				$data['id']=$_POST['id'];
				$rs=$Qqline->save($data);
			}else{
				$rs=$Qqline->add($data);
			}
			if($rs){
				$this->success('操作成功');
			}else{
				$this->error('操作失败');
			}
		}else{
			if(!empty($_GET['id'])){
				$list=$Qqline->where('id='.$_GET['id'])->find();
				$this->assign('Qqline',$list);
			}
			$this->display();
		}
	}
	public function index(){
		$list=M('Qqline')->select();
		$this->assign('Qqline',$list);
		$this->assign('empty','暂无数据');
		$this->display();
    }
    public function del(){
    	if(!empty($_GET['id'])){
            $list=M('Qqline')->where('id='.$_GET['id'])->delete();
        }
    	if($list){
    		$this->success('删除成功');
    	}else{
    		$this->error('删除失败');
    	}
    }
}