<?php
namespace app\mobile\controller;

use app\common\model\ArticleCategory;
use app\common\model\Reads;
use think\Db;

class News extends Base
{
    public function calculator() {
        return $this->fetch();
    }

    public function lists() {
        $active_id = input('id','','strval');
        $position_id = input('position_id','','strval');
        $this->assign('id',$active_id);
        $this->assign('position_id',$position_id);
        $refer = input('server.HTTP_REFERER','');
        $refer = $refer ?: input('refer','');
        $this->assign('refer',$refer);
        return $this->fetch();
    }

    public function listsDetails() {
        $article_id = input("article_id", '', 'intval');
        $zixun = [];
        if (!empty($article_id)) {
            $zixun = Reads::read($article_id);
            @$zixun['content'] = html_entity_decode($zixun['content']);
            @$zixun['add_time'] = date('Y-m-d H:i', $zixun['add_time']);
        }
        self::output(10000, '請求成功', $zixun ?: []);
    }

    public function detail()
    {
    	 $active_id = input('id','','strval');
    	 $position_id = input('position_id','','strval');
    	 $this->assign('id',$active_id);
    	 $this->assign('position_id',$position_id);
    	 $refer = input('server.HTTP_REFERER','');
    	 $refer = $refer ?: input('refer','');
    	 $this->assign('refer',$refer);
        return $this->fetch();
    }
    /**
     * @Desc:详情
     * @return array
     * @Date: 2018/12/11 0011 17:52
     * @author: Administrator
     */
    public function newsDetails()
    {
        $article_id = input("article_id", '', 'intval');
        $position_id = input("position_id", '', 'intval');
        if (!empty($article_id)) {
            $w['article_id'] = $article_id;
        } else {
            $w['position_id'] = $position_id;
        }
        $zixun = [];
        if (!empty($w)) {
            $lang = $this->getLang();
            $articleModel = db('Article');
            if (empty($lang)) {
                $field = 'c.article_id,c.title,c.content,c.art_pic,c.add_time,t.name as title_name';
            } else {
                $field = 'c.article_id,c.' . $lang . '_title as title,c.' . $lang . '_content as content,c.art_pic,c.add_time,t.name_' . $lang . ' as title_name';
            }
            $zixun = $articleModel->field($field)->alias('c')->join("article_category t ", "   c.position_id=t.id ", 'left')->where($w)->order('add_time desc')->find();
            @$zixun['content'] = html_entity_decode($zixun['content']);
//            @$zixun['add_time'] = date('Y/m/d H:i', $zixun['add_time']);
            @$zixun['add_time'] = '';
        }
        self::output(10000, '請求成功', $zixun ?: []);
    }

    public function helps() {
        $position_id = intval(input('id'));
        $cats = ArticleCategory::getArticles($this->getLang(),$position_id);
        return $this->fetch('',compact('cats'));
    }
}
