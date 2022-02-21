<?php

namespace app\admin\controller;

use think\Db;
use think\Request;

class Art extends Admin
{
    //空操作
    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    /**
     * 显示文章列表
     * return array 文章分类，分页，查询信息
     */
    public function index(Request $request)
    {
        $article =  Db::name('Article');
        $art_cat = Db::name('Article_category');
        //为了获取不同传递方式的title
        $name = input('title');
        $names = input('keywords');
        $where=null;
        $inquire['title']=null;
        $inquire['category']=null;
        if (input('category') != 0 || input('id') != 0) {
            $inquire['category'] = input('id') ? input('id') : input('category');
            $where ['ac.id'] = input('id') ?input('id') : input('category');
        }
        if (!empty($name) || !empty($names)) {
            // 如果传回的是post（keywords）就用post，否则用get（title）
            $title = $names ? $names : $name;
            $inquire['title'] = $title;
            //模糊
            $where['a.title'] = array('like', '%' . $title . '%');
        }
        //根据分类或模糊查找数据数量
//        $count = $article->alias("a")->join(config("database.prefix")."article_category ac","a.position_id=ac.id","LEFT")
//            ->where($where)->count();

//        $Page = new \Think\Page ($count, 20); // 实例化分页类 传入总记录数和每页显示的记录数
//        //将分页（点击下一页）需要的条件保存住，带在分页中
//        $Page->parameter = array(
//            'yang_article_category.id' => $where ['ac.id'],
//            'yang_article.title' => $title
//        );

        //显示文章分类
        $cat = $art_cat->select();

        $info = $article->alias("a")->join(config("database.prefix")."article_category ac","a.position_id=ac.id","LEFT")
            ->where($where)->order('add_time desc, ac.sort')->paginate(20,null,['query'=>$request->get()])->each(function ($item, $key){
                $item['title'] = mb_substr((strip_tags(html_entity_decode($item['tc_title']))), 0, 20, 'utf-8');
                $item['content'] = mb_substr((strip_tags(html_entity_decode($item['tc_content']))), 0, 40, 'utf-8');
               return $item;
            });

        $show= $info->render();;
//        foreach ($info as $k => $v) {
//            $info[$k]['title'] = mb_substr((strip_tags(html_entity_decode($v['tc_title']))), 0, 20, 'utf-8');
//            $info[$k]['content'] = mb_substr((strip_tags(html_entity_decode($v['tc_content']))), 0, 40, 'utf-8');
//        }
        $this->assign('inquire', $inquire);
        $this->assign('cat', $cat);
        $this->assign('page', $show);
        $this->assign('info', $info);
       return $this->fetch();
    }

    /**
     * 添加文章
     * return id 添加成功信息的id
     */
    public function insert()
    {
        $article = Db::name('Article');
        $art_cat = Db::name('Article_category');

        if ($_POST) {
//     		$data['status'] = I('post.status');//状态 0 不显示，1显示
            $data['position_id'] = input('post.category');//类型  根据类型表的id 对比
            $data['add_time'] = time();//添加时间
            $data['sign'] = input('sign');//是否标红
            $data['title'] = input('post.en_title');//标题
            $data['content'] = input('post.en_content', '', 'htmlentities');//内容
            $data['en_title'] = input('post.en_title');//标题
            $data['en_content'] = input('post.en_content', '', 'htmlentities');//内容
            $data['tc_title'] = input('post.tc_title');//标题
            $data['tc_content'] = input('post.tc_content', '', 'htmlentities');//内容
            $data['sort'] = input('post.sort', 99, 'intval');

            if ($_FILES['art_pic']['size'] > 0) {
                $upload = $this->oss_upload($file = [], $path = 'article_pics');
                if (empty($upload)) {
                    $this->error('图片上传失败');
                }
                $data['art_pic'] = trim($upload['art_pic']);  //保存路径到数据库
            }

            $re = $article->insertGetId($data);//加入数据库
            if ($re === false) {
               return $this->error('新增失败');
            } else {
                //写入Message_all消息表
//                if ($data['position_id'] == 32) {
//                    $content = mb_substr($data['content'], 0, 10, 'utf-8')
//                        . "<br/><a href=" . U('Home/Art/details', array('id' => $re)) . ">点击显示详情</a>";
//                    if ($this->addMessage_all(-1, 3, $data['title'], $content) === false) {
//                        $this->error('服务器繁忙,请稍后重试');exit;
//                    }
//                    $this->success('新增成功', U('Art/index'));exit;
//                }
               return $this->success('新增成功',url("art/index"));
            }
        } else {
            $cat = $art_cat->order("id asc")->where('parent_id = 0')->select();//查找1级分类
            //以一级为基础，形成遍历树
            foreach ($cat as $k => $v) {
                //加入二级分类，以一级id为查询条件
                $cat[$k]['children'] = $art_cat->where("parent_id = {$v['id']}")->order("id asc")->select();
                foreach ($cat[$k]['children'] as $kk => $vv) {
                    //加入三级分类，以二级id为查询条件
                    $cat[$k]['children'][$kk]['childrens'] = $art_cat->where("parent_id = {$vv['id']}")->select();
                }
            }
            $this->assign('cat', $cat);
           return $this->fetch();
        }
    }

    /**
     * 修改文章
     * return array 文章分类，查询信息
     */
    public function update()
    {
        $article = Db::name('Article');
        $art_cat = Db::name('Article_category');
        $id = intval(input('id'));//获取随url带来的参数Article_id
        $this->assign("id", $id);//付给页面的隐藏签中

        $where['article_id'] = $id;//保存查询条件

        if (request()->isPost()) {//是否有 post
            $data['position_id'] = input('category');//类型  根据类型表的id 对比
            $data['sign'] = input('sign');//是否标红
            if ($_FILES['art_pic']['size'] > 0) {
                $upload = $this->oss_upload($file = [], $path = 'article_pics');
                if (empty($upload)) {
                    $this->error('图片上传失败');
                }
                $data['art_pic'] = trim($upload['art_pic']);  //保存路径到数据库
            }

            $model = $article;
            $data['tc_title'] = input('tc_title');//标题
            $data['tc_content'] = input('tc_content', '', 'htmlentities');//内容
            $data['title'] = input('tc_title');//标题
            $data['content'] = input('tc_content', '', 'htmlentities');//内容
            $data['en_title'] = input('en_title');//标题
            $data['en_content'] = input('en_content', '', 'htmlentities');//内容
            $data['sort'] = input('sort', 99, 'intval');
            $re = $model->where($where)->update($data);//修改保存数据库
            if ($re === false) {
               return $this->error('修改失败');
            } else {
               return $this->success('修改成功');
            }
			exit;
        } else {
            $cat = $art_cat->order("id asc")->where('parent_id = 0')->select();//查找1级分类
            //以一级为基础，形成遍历树
            foreach ($cat as $k => $v) {
                //加入二级分类，以一级id为查询条件
                $cat[$k]['children'] = $art_cat->where("parent_id = {$v['id']}")->order("id asc")->select();
                foreach ($cat[$k]['children'] as $kk => $vv) {
                    //加入三级分类，以二级id为查询条件
                    $cat[$k]['children'][$kk]['childrens'] = $art_cat->where("parent_id = {$vv['id']}")->select();
                }
            }

            $info = $article->where(['article_id' => $id])->find();//查询选中信息，并传给模板显示

            $this->assign('cat', $cat);
            $this->assign('info', $info);
           return $this->fetch();
        }
    }
        public  function  oss_file_upload(){
            $upload = $this->oss_upload($file = [], $path = 'article_pics');
            if(!empty($upload['imgFile'])){
                echo json_encode(['error'=>0,'url'=>$upload['imgFile']]);
            }else{
                echo json_encode(['error'=>0,'message'=>'上传失败']);

            }
            exit;
        }
    /**
     * 删除文章
     * return boolen
     */
    public function delete()
    {
        $article = Db::name('Article');
        $article_en = Db::name('article_en');
        $article_tc = Db::name('article_tc');
        if ($_POST) {
            $id = $_POST;
            if (isset($id['subBox'])&&is_array($id['subBox'])) {
                foreach ($id['subBox'] as $val) {
                    $re = $article->where(['article_id'=>$val])->delete();
                    $article_en->where(['article_id'=>$val])->delete();
                    $article_tc->where(['article_id'=>$val])->delete();
                }
            }
            if ($re) {
               return $this->success('删除成功');
            } else {
               return $this->error('删除失败');
            }
			exit;
        } else {
            $id = intval(input('id'));
            $re = $article->where(['article_id'=>$id])->delete();
            if ($re) {
                $article_en->where(['article_id'=>$id])->delete();
                $article_tc->where(['article_id'=>$id])->delete();
                return $this->success('删除成功');
            } else {
                return $this->error('删除失败');
            }
			exit;
        }
    }
}