<?php
namespace Admin\Controller;
use Admin\Controller\AdminController;
class ShopclassfiyController extends AdminController{
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
	//商品顶级分类展示
	public function index(){
		$count = M('yy_category')->count();
		$Page  = new \Think\Page($count,12);
		$show  = $Page->show();
		$list=M('yy_category')->limit($Page->firstRow.','.$Page->listRows)->select();
		foreach ($list as $key => $vo){
				$num=M('yy_goods')->field('count(*) as num')->where("cat_id=".$vo['cat_id'])->select();	
				$list[$key]['cat_num']=$num[0]['num'];
		}
		$this->assign('list',$list);
		$this->assign('empty','暂无数据');
		$this->assign('page',$show);
		$this->display();
	}
	//添加商品顶级分类
	public function class_add(){
		$art_cat = M('yy_category');
		if(IS_POST){	
			//确定分类别
			$data['parent_id']=I('parent_id','0','html_entity_decode');
			//分类名称
			$data['cat_name']=I('cat_name');
			//关键字
			$data['keywords'] =  I('keywords','','html_entity_decode');
			//分类描述
			$data['cat_desc'] =  I('cat_desc','','html_entity_decode');
			//排序
			$data['sort'] = I('sort','0','html_entity_decode');
			$data['add_time']=time();
			//print_r($data);
			$rs=M('yy_category')->add($data);
			if($rs){
				$this->success("添加成功");
			}else{
				$this->error('添加失败');
			}
		}else{
			//遍历分类（无限级分类）
			$cat = $art_cat->order("cat_id asc")->where('parent_id = 0')->select();//查找1级分类
			//以一级为基础，形成遍历树
			foreach($cat as $k=>$v){
				//加入二级分类，以一级id为查询条件
				$cat[$k]['children']= $art_cat->where("parent_id = {$v['cat_id']}")->order("cat_id asc")->select();
				foreach($cat[$k]['children'] as $kk=>$vv){
					//加入三级分类，以二级id为查询条件
					$cat[$k]['children'][$kk]['childrens']=$art_cat->where("parent_id = {$vv['cat_id']}")->select();
				}
			}
		}
		$this->assign('cat',$cat);
		$this->display();
	}
	//修改商品顶级分类
	public function class_update(){
		$art_cat = M('yy_category');
		$id = intval(I('id'));
		if(!empty($id)){
			$this->assign("id",$id);
			$where['cat_id'] = $id;
			$list = M('yy_category')->where($where)->find();	
		}
		if(IS_POST){
			$wherec['cat_id'] = $_POST['id'];
			//确定分类别
			$data['parent_id']=I('parent_id','0','html_entity_decode');
			//分类名称
			$data['cat_name']=I('cat_name');
			//关键字
			$data['keywords'] =  I('keywords','','html_entity_decode');
			//分类描述
			$data['cat_desc'] =  I('cat_desc','','html_entity_decode');
			//排序
			$data['sort'] = I('sort','0','html_entity_decode');
			$data['add_time']=time();
			//dump($data);
			$rs=M('yy_category')->where($wherec)->save($data);
			if($rs){
			 $this->success("修改成功");
			 }else{
			 $this->error("修改失败");
			}
		}else{
			//为了做到修改时，本身是子级分类，分类类型显示的是父级分类，父级分类显示的是本身
			//通过商品id查询得到商品父级id,父级为0表示本身是顶级，不需要判断
			//父级不为0，表示是子级分类，通过  cat_id=$list['parent_id'] 查询得到该商品父级商品的 id以及名称 
			//后台修改中的分类类型要包括全部顶级父级以及该商品的父级
			if($list['parent_id']!=0){
				$parent = $art_cat->field('parent_id,cat_name')->where('cat_id='.$list['parent_id'])->find();
				$cat = $art_cat->order("cat_id asc")->where('parent_id = 0 or parent_id='.$parent['parent_id'])->select();
			}else{
				$cat = $art_cat->order("cat_id asc")->where('parent_id = 0')->select();
			}
		}
		$this->assign('cat',$cat);
		$this->assign('parent',$parent);
		$this->assign('list',$list);
		$this->display();
	}
	//商品顶级分类删除
	public function class_del(){
		$id = intval(I('id'));
		$re = M('yy_category')->delete($id);
		if($re){
			$this->success('删除成功');
		}else{
			$this->error('删除失败');
			return;
		}
		$this->display();
	}
}