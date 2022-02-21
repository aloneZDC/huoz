<?php
namespace app\admin\controller;
use think\Db;

class Flash extends Admin {
	//空操作

    /**
     * @var array
     */
    protected $type = [
//        0：为首页，1：为一元幻灯片，2：为手机幻灯片，3手机一元购幻灯片，4手机APP幻灯片, 5为APP资讯幻灯片, 6为游戏首页幻灯片,7为算能首页幻灯片git
        0 => "首页幻灯片",
        2 => "手机Banner",
        3 => "手机 APP Banner",
        6 => "游戏Banner",
        7 => "算能首页幻灯片",
        8 => "商城幻灯片",
        9 => '线下商城幻灯片',
        10 => '矿池幻灯片',
        11 => '发现幻灯片',
    ];

	public function _initialize(){
		parent::_initialize();
	}
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		$this->display('Public:404');
	}
	Public function add(){
		$flash=Db::name('Flash');
		if($_POST){
//			if($_FILES["Filedata"]["tmp_name"]){
//				$data['pic']=$this->upload($_FILES["Filedata"]);
//			}
            if(!empty($_FILES)&&isset($_FILES['Filedata']['size'])&&$_FILES['Filedata']['size']>0){
                $upload = $this->oss_upload($file=[], $path = 'huandeng_pics');
                if (empty($upload)) {
                   return $this->error('图片上传失败');
                }
                $data['pic'] = trim($upload['Filedata']);  //保存路径到数据库
            }
			if(!empty($_POST['jump_url'])){
				$data['jump_url']=$_POST['jump_url'];
			}
			if(!empty($_POST['sort'])){
				$data['sort']=$_POST['sort'];
			}
			if(!empty($_POST['title'])){
				$data['title']=$_POST['title'];
			}
			$data['add_time']=time();
			if(!empty($_POST['type'])){
			    $data['type']=$_POST['type'];
			}
			$data['lang'] = input('lang');
            $data['type'] = intval(input('type'));
            $data['bgcolor'] = input('bgcolor');
            $data['bgcolor2'] = input('bgcolor2');
            if(!empty($_POST['flash_id'])){
				$data['flash_id']=$_POST['flash_id'];
				$rs=$flash->update($data);
			}else{
				$rs=$flash->insertGetId($data);
			}

			if($rs === false){
               return $this->error('操作失败');
			}

            return $this->success('操作成功');
		}else{
		    $id=input("flash_id");
            $list=null;
			if(!empty($id)){
				$list=$flash->where(['flash_id'=>$id])->find();

			}
            $this->assign('flash',$list);
			$this->assign('type', $this->type);
			return $this->fetch();
		}
	}
	public function index(){
		$list=Db::name('Flash')->select();
		$this->assign('flash',$list);
		$this->assign('empty','暂无数据');
		$this->assign('type', $this->type);
		return $this->fetch();
    }
    public function del(){
	    $id=input("flash_id");
        $list=null;
    	if(!empty($id)){
            $list=Db::name('Flash')->where(["flash_id"=>$id])->delete();
        }
    	if($list){
    		return $this->success('删除成功');
    	}else{
    		return $this->error('删除失败');
    	}
    }
}
