<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/11 0011
 * Time: 16:59
 */
namespace app\index\controller;

use think\Exception;
use think\Page,think\Db;
class  Help extends  Base{
    protected $public_action = ['index'];
	
//	public function index(){
//        $allow_id = [32,184,32,154,118];
//        $team_id = intval(input('id',0));
//        if(empty($team_id) || !in_array($team_id, $allow_id)) $team_id = current($allow_id);
//
//        $cates = Db::name('article_category')->field('id,name_'.$this->lang.' as name')->where(['id'=>['in',$allow_id]])->select();
//
//        $info = Db::name('article')->field('article_id,position_id,'.$this->lang.'_title as title,'.$this->lang.'_content as content,art_pic,add_time')->where(['position_id'=>$team_id])->order('add_time desc')->find();
//        if($info){
//            $info['content'] = html_entity_decode($info['content']);
//            $info['add_time'] = date('Y/m/d H:i',$info['add_time']);
//        }else{
//            $info['position_id']=$team_id;
//            $info['content'] = "";
//            $info['title'] = "";
//        }
//        return $this->fetch('',['cates'=>$cates,'info'=>$info]);
//    }
    function index(){
       $article_id=input("id");
        $info=null;
        $cates=null;
        $one_cat=null;
        $two_cat=null;
        $lang = cookie('think_language');//默认null则为简体中文
        $one_cat=null;
       if(is_numeric($article_id)){
        $info=Db::name("article")->where(['article_id'=>$article_id])->find();//当前文章id的文章
        if(!empty($info)){
            $two_cat=Db::name("article_category")->where(['id'=>$info['position_id']])->find();//当前文章分类
            if(!empty($two_cat)&&$two_cat['parent_id']>0){
                $one_cat=Db::name("article_category")->where(['id'=>$two_cat['parent_id']])->find();//上级分类
            }
            if($lang=="en-us"){
                $info['tc_title']=$info['en_title'];
                $info['tc_content']=$info['en_content'];
                $two_cat['name']=$two_cat['name_en'];
                if(!empty($one_cat)){
                    $one_cat['name']=$one_cat['name_en'];
                }
            }
            //$one_cat=!empty($one_cat)?$one_cat:$two_cat;
            $cates=Db::name("article")->where(['position_id'=>$info['position_id']])->field("tc_title,article_id,en_title")
                ->page(1,10)->order("article_id desc")->select();//同分类下的文章
            if($cates){
                foreach ($cates as &$value){
                    if($lang=="en-us"){
                        $value['tc_title']=$value['en_title'];
                    }
                }
            }
        }
       }
        return $this->fetch('',['cates'=>$cates,'info'=>$info,'one'=>$one_cat,'two'=>$two_cat]);
    }
}