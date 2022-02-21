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
class  News extends  Base{
    protected $public_action = ['new_detail','newsList'];
    /**
     * @Desc:新闻列表
     * @author: Administrator
     * @return array
     * @Date: 2018/12/11 0011 17:02
     */
    public  function  newsList(){
        $lang = $this->getLang();
        if(empty($lang)) {
            $field = 'article_id,title,content,art_pic,add_time';
        } else {
            $field = 'article_id,'.$lang.'_title as title,'.$lang.'_content as content,art_pic,add_time';
        }
        $articleModel =db('Article');
        $type = input('type',1);
        $page_size = input('rows',10);
        $page = input('page',1);
        $where['position_id']=$type;
        $count=$articleModel->field($field)->where($where)->order('add_time desc')->count();
        $zixun = $articleModel->field($field)->where($where)->order('add_time desc')->limit(($page - 1) * $page_size, $page_size)->select();
        $pages = $this->getPages($count,$page,$page_size);
        if($zixun){
            foreach ($zixun as $key => $value) {
                $lenth = strlen($value['title']);
                if ($lenth >= 15) {
                    $value['title'] = strip_tags(html_entity_decode($value['title']));
                } else {
                    $value['title'] = trim(strip_tags(html_entity_decode($value['title'])));
                }
                $lenth = strlen($value['content']);
                if ($lenth >= 30) {
                    $value['content'] = mb_substr(strip_tags(html_entity_decode($value['content'])),0,100);
                } else {
                    $value['content'] = trim(strip_tags(html_entity_decode($value['content'])));
                }
                $list[$key] = $value;
            }
        }
        $allow_id = [129,1];
        $cates = Db::name('article_category')->field('id,name_'.$this->lang.' as name')->where(['id'=>['in',$allow_id]])->select();

        $this->assign('type',$type);
        return $this->fetch('news/news_list',['list'=>$list,'pages'=>$pages,'cates'=>$cates]);
    }

    /**
     * @Desc:详情
     * @author: Administrator
     * @return array
     * @Date: 2018/12/11 0011 17:52
     */
    public  function  new_detail(){
        $article_id = input("article_id",'','intval');
        $team_id = input("team_id",'','intval');
        if($team_id){
            $w['position_id']=$team_id;
        }else{
            $w['article_id']=$article_id;
        }



        $zixun = [];
        $art_list=[];
        if(!empty($w)){
            $lang = $this->getLang();
            $articleModel =db('Article');
            if(empty($lang)) {
                $field = 'c.article_id,c.title,c.content,c.art_pic,c.add_time,t.name as title_name,c.position_id';
            } else {
                $field = 'c.article_id,c.'.$lang.'_title as title,c.'.$lang.'_content as content,c.art_pic,c.add_time,t.name_'.$lang.' as title_name,c.position_id';
            }
            $zixun = $articleModel->field($field)->alias('c')->join("article_category t ","   c.position_id=t.id ",'left')->where($w)->order('add_time desc')->find();
            if($zixun){
                @$zixun['content'] = html_entity_decode( $zixun['content']);
                @$zixun['add_time'] = $zixun['add_time'];
                if(($zixun['position_id']=='1')||($zixun['position_id']=='181')){
                    $where['c.position_id']=1;
                    $zixun['position_id']=1;
                }else{
                    $where['c.position_id']=$zixun['position_id'];
                }
                $art_list = $articleModel->field($field)->alias('c')->join("article_category t ","   c.position_id=t.id ",'left')->where($where)->order('add_time desc')->limit(10)->select();
            }


        }
        return $this->fetch('news/new_detail',['art_one'=>$zixun,'art_list'=>$art_list,'type'=>$zixun['position_id']]);

    }

    /**
     * @Desc:联系我们
     * @author: Administrator
     * @return array
     * @Date: 2018/12/13 0013 15:53
     */
    public  function  aboutus(){
        self::output(10000,"請求成功", ['contact'=>[
            'email' => $this->config['email'],
            'qq1' => $this->config['qq1'],
            'qq2' => $this->config['qq2'],
            'weixin1' => $this->config['weixin_kf1'],
            'weixin2' => $this->config['weixin_kf2'],
        ]]);
    }
}