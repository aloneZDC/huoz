<?php
namespace Admin\Controller;
use Admin\Controller\AdminController;
class ShoppingController extends AdminController{
	public function _initialize(){
		parent::_initialize();
		$this->currency=M('Currency');
		$this->activity=M('yy_oneshop_activity');
	}
	//空操作
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		$this->display('Public:404');
	}
	//商品添加
	public function shopping(){
		if(IS_POST){
			foreach ($_POST as $k=>$v){
				$data[$k]=$v;
			}
			$data['add_time']=time();
			if($_FILES["Filedata"]["tmp_name"]){
				$data['goods_img']=$this->upload($_FILES["Filedata"]);
			}
			//生成缩略图片
			if(!empty($data['goods_img'])){
				$type = "big";
				$image = new \Think\Image();
				$image->open('.'.$data['goods_img']);
				$picname = substr($data['goods_img'],strrpos($data['goods_img'],"/")+1);
				$filename = substr($data['goods_img'],0,strrpos($data['goods_img'],"/")+1);
				$thumb_pic = '.'.$filename.$type."_".$picname;
				$image->thumb(200,200)->save($thumb_pic);
				$iden = true;
			}
			if($iden){
				$thumbPic = $filename.$type."_".$picname;
				$data['goods_thumb'] = $thumbPic;
			}
			$data['acttime']=strtotime($data['acttime'])?strtotime($data['acttime']):time();
			$rs=M('yy_goods')->add($data);
			if($rs){
				$this->success("添加成功");
			}else{
				$this->error('添加失败');
			}
		}
		$catId=M('yy_category')->field('cat_id,cat_name')->select();
		$this->assign('catId',$catId);
		$this->display();
	}
	//商品展示列表
	public function index(){
		$goods_id = I('goods_id');			//用于搜索
		if(!empty($goods_id)){
			$where['goods_id'] = $goods_id;
		}
		$count = M('yy_goods')->where($where)->count();
		$Page  = new \Think\Page($count,12);
		$show  = $Page->show();
		$list = M('yy_goods')->where($where)->order('add_time desc')->limit($Page->firstRow.','.$Page->listRows)->select();
		$this->assign('list',$list);
		$this->assign('empty','暂无数据');
		$this->assign('page',$show);
		$this->display();
	}
	//商品修改
	public function shopping_update(){
		$id = intval(I('id'));
		if(!empty($id)){
			$this->assign("id",$id);
			$where['goods_id'] = $id;
			$list = M('yy_goods')->where($where)->find();
			$pic = M('yy_goods_pics')->where($where)->select();
		}
		if(IS_POST){
			//多图处理
			$duotu_pic=$_POST['duotu_url'];//接收多图路径数组
			/* echo "<pre>";
			print_r($duotu_pic);
			echo "</pre>"; */
			foreach ($_POST as $k=>$v){
				$data[$k]=$v;
			}
			$whereg['goods_id'] = $_POST['id'];
			$data['add_time']=time();
			if($_FILES["Filedata"]["tmp_name"]){
				$data['goods_img']=$this->upload($_FILES["Filedata"]);
			}
			if($_POST['thumb']==1){
				//生成缩略图片
				if(!empty($list['goods_img'])){
					$type = "big";
					$image = new \Think\Image();
					$image->open('.'.$list['goods_img']);
					$picname = substr($list['goods_img'],strrpos($list['goods_img'],"/")+1);
					$filename = substr($list['goods_img'],0,strrpos($list['goods_img'],"/")+1);
					$thumb_pic = '.'.$filename.$type."_".$picname;
					$image->thumb(200,200)->save($thumb_pic);
					$iden = true;
				}
				if($iden){
					$thumbPic = $filename.$type."_".$picname;
					$data['goods_thumb'] = $thumbPic;
				}
			}
			$data['acttime']=strtotime($data['acttime'])?strtotime($data['acttime']):time();
			$rs=M('yy_goods')->where($whereg)->save($data);
			if($rs){
				//多图保存
				if($duotu_pic){
					foreach ($duotu_pic as $value){
						$data['pic']=$value;
						$data['goods_id']=$_POST['id'];
						M('yy_goods_pics')->add($data);
					}
					//更新一张图为封面图
					M('yy_goods_pics')->where('goods_id='.$_POST['id'])->limit(1)->setField('fengmian','1');
				}
				$this->success("修改成功",U('Shopping/index'));
			}else{
				$this->error("修改失败",U('Shopping/index'));
				if($duotu_pic){
					foreach ($duotu_pic as $value){
						$lc_pic_url=$value;
						if(file_exists($lc_pic_url)){
							unlink($lc_pic_url);
						}
					}
				}
			}
		}
		$catId=M('yy_category')->field('cat_id,cat_name')->select();//商品分类
		$this->assign('catId',$catId);
		$this->assign('list',$list);
		if(count($pic)>0){
			$this->assign('pic',$pic);
		}
		$this->assign('empty','暂无图片');
		$this->display();
	}
	//商品删除
	public function shopping_del(){
		$id = intval(I('id'));
		$rs = M('yy_goods')->delete($id);
		if($rs){
			$this->success('删除成功');
		}else{
			$this->error('删除失败');
			return;
		}
		$this->display();
	}
	//多图ajax标题修改
	public function duotu_edit_title(){
		$title = $_POST['title'];//获取标题
		$pic_id = $_POST['id'];//获取修改的编号
		$sql = M('yy_goods_pics')->where('pic_id='.$pic_id)->setField('title',$title);
		if(count($sql)>0){
			$value = "yes";//成功了
		}else{
			$value = "no";//失败了
		}
		$this->ajaxReturn($value);
	}
	
	//多图ajax排序修改
	public function duotu_edit_paixu(){
		$paixu = $_POST['paixu'];//获取排序编号
		$lc_id = $_POST['id'];//获取修改的编号
		$sql = M('yy_goods_pics')->where('pic_id='.$lc_id)->setField('sort_id',$paixu);
		if(count($sql)>0){
			$value = "yes";//成功了
		}else{
			$value = "no";//失败了
		}
		$this->ajaxReturn($value);
	}
	
	//多图ajax封面图片修改
	public function duotu_xuan_fengmian(){
		$product_id = $_POST['product_id'];//获取所属图文id
		$lc_id = $_POST['id'];//获取修改的编号
		//先设置所有图片不是封面图
		$sql = M('yy_goods_pics')->where('goods_id='.$product_id)->setField('fengmian','0');
		//再设置某张图片为封面图
		$sqlf = M('yy_goods_pics')->where('pic_id='.$lc_id)->setField('fengmian','1');
		if(count($sqlf)>0){
			$value = "yes";//成功了
		}else{
			$value = "no";//失败了
		}
		$this->ajaxReturn($value);
	}

	//多图ajax删除
	public function duotu_del(){
	$type=$_POST['type'];//获取操作类型(one and all)

	if($type=="one"){
		$lc_id = $_POST['id'];//接收要删除的图片的编号
		$select_rows = M('yy_goods_pics')->field('pic')->where('pic_id='.$lc_id)->find();
		$rs = M('yy_goods_pics')->delete($lc_id);
		if($rs && count($rs)>0){
			$value = "yes";
			//判断图片是否存在并删除
			if(file_exists($select_rows['pic'])){
				unlink($select_rows['pic']);
				}
			}else{
				$value = "no";
			}
			$this->ajaxReturn($value);
	}
	if($type=="all"){
		$lc_id = $_POST['id'];//接收要删除的图片的编号数组
		$picid= explode(",",$lc_id);//分割数组
		$shu=count($picid);//获取数组元素个数
		foreach ($picid as $value){
			/*查询删除的内容*/
			$select_rows = M('yy_goods_pics')->field('pic')->where('pic_id='.$value)->find();
			/*查询删除的内容end*/
			/*删除内容*/
			$rs = M('yy_goods_pics')->delete($value);
			if($rs && count($rs)>0){
				//判断图片是否存在并删除
				if(file_exists($select_rows['pic'])){
					unlink($select_rows['pic']);
				}
			}
			/*删除内容End*/
		}
		$value = "yes";
		$this->ajaxReturn($value);
		}
	}
}