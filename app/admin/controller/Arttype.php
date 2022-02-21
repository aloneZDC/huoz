<?php

namespace app\admin\controller;


use think\Db;
use think\Request;

class Arttype extends Admin
{
    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    /**
     * 显示文章分类列表
     * return array 文章分类，分页，查询信息
     */
    public function index(Request $request)
    {
        $art_cat = Db::name('Article_category');
        //为了获取不同传递方式的title
        $name = input('name');
        $names = input('keywords');
        $inquire['name']=null;
        $where=null;
        if (!empty($name) || !empty($names)) {
            // 如果传回的是post（keywords）就用post，否则用get（title）
            $title = $names ? $names : $name;
            $inquire['name'] = $title;
            //模糊
            $where['name'] = array('like', '%' . $title . '%');
        }

        $info = $art_cat->where($where)->order('parent_id asc,id asc')->paginate(20,null,['query'=>$request->get()]);
        $show=$info->render();
        $this->assign('inquire', $inquire);
        $this->assign('page', $show);
        $this->assign('info', $info);
       return $this->fetch();
    }

    /**
     * 添加文章分类
     * return id 添加成功信息的id
     */
    public function insert()
    {
        $art_cat = Db::name('Article_category');

        if ($_POST) {
            //确定分类别
            $data['parent_id'] = input('tp', '0', 'html_entity_decode');
            //分类名称
            $data['name'] = input('name');
            $data['name_en'] = input('name_en');
            $data['name_tc'] = input('name_tc');
            //关键字
            $data['keywords'] = input('keywords', '', 'html_entity_decode');
            //排序
            $data['sort'] = input('sort', '', 'html_entity_decode');
            $data['is_help'] = intval(input('is_help'));
            //加入数据库
            $re = $art_cat->insertGetId($data);
            if ($re === false) {
                $this->error('新增失败');
                return;
            } else {
                $this->success('新增成功');
                return;
            }
        } else {
            //遍历分类（无限级分类）
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
        }
        $this->assign('cat', $cat);
       return  $this->fetch();

    }

    /**
     * 文章分类语言转换
     */
    public function update_name()
    {
        $article_category = M('article_category');
        $result = $article_category->select();

        $baidu_transapi = A('Common/Baidutransapi');
        $zh = 'zh';
        $en = 'en';
        $cht = 'cht';
        $sql = "";
        $name_en_str = "";
        $name_tc_str = "";
        foreach ($result as $value){
            if(empty($value['name_en'])){
                $name_en_str .= $value['name']."\n";
            }

            if(empty($value['name_tc'])){
                $name_tc_str .= $value['name']."\n";
            }
        }

        if(!empty($name_en_str)){
            $name_en_str = rtrim($name_en_str, '\n');
            $name_en = $baidu_transapi->translate($name_en_str, $zh, $en);
            if(!empty($name_en['trans_result'])){
                foreach ($name_en['trans_result'] as $value){
                    $sql .= "charset=utf8;update yang_article_category set name_en = '{$value['dst']}' where name = '{$value['src']}';";
                }
            }
        }

        if(!empty($name_tc_str)){
            $name_tc_str = rtrim($name_tc_str, '\n');
            $name_tc = $baidu_transapi->translate($name_tc_str, $zh, $cht);
            if(!empty($name_tc['trans_result'])){
                foreach ($name_tc['trans_result'] as $value){
                    $sql .= "charset=utf8;update yang_article_category set name_tc = '{$value['dst']}' where name = '{$value['src']}';";
                }
            }
        }

        $update = true;
        if(!empty($sql)){
            $update = $article_category->query($sql);
        }

        if($update === false){
            $this->ajaxReturn(['Code' => 0, 'Msg' => '转换失败']);
        }

        $this->ajaxReturn(['Code' => 1, 'Msg' => '转换完成']);
    }

    /**
     * 修改文章
     * return array 文章分类，查询信息
     */
    public function update()
    {
        $art_cat = Db::name('Article_category');
        $id = intval(input('id'));//获取随url带来的参数Article_id
        //保存查询条件
        $where['id'] = $id;

        if ($_POST) {//是否有 post
            //分类名称
            $data['name'] = input('name');
            $data['name_en'] = input('name_en');
            $data['name_tc'] = input('name_tc');
            //关键字
            $data['keywords'] = input('keywords');
            //parend_id为所选id
            $data['parent_id'] = input('tp', '0', 'html_entity_decode');
            //排序
            $data['sort'] = input('sort');
            $data['is_help'] = intval(input('is_help'));
            //修改保存数据库
            $re = $art_cat->where($where)->update($data);
            if ($re) {
                return $this->success('修改成功', url('Arttype/index'));
            } else {
                return $this->error('修改失败');
            }
        } else {
            $info = $art_cat->where($where)->find();//查询选中信息，并传给模板显示
            //遍历分类（无限级分类）
            $cat = $art_cat->field('id,name,parent_id')->where('id', '<>', $info['id'])->order("id asc")->select();//查找1级分类
            if($cat){
                $cat=$this->get_tree($cat);
                foreach ($cat as $key=> $val) {
                    $str = '';
                    if ($val['level']>0){
                        for ($i = 0; $i < $val['level']; $i++) {
                            $str .= '|---';

                        }
                    }
                    $cat[$key]['name']=$str.$val['name'];
                    unset($str);
                }
            }

            $this->assign('cat', $cat);//显示分类
            $this->assign('info', $info);//显示选择分类的信息
            $this->assign('id', $id);//传递所选分类id、
           return $this->fetch();
        }
    }
    //商品无级分类
    function get_tree($arr=array(),$pid=0,$level=0){
        static $array=array();
        foreach($arr as $vl){
            if($vl['parent_id'] ==$pid){
                $vl['level']=$level;
                $array[]=$vl;
                $this->get_tree($arr,$vl['id'],$level+1);
            }
        }
        return $array;
    }
    /**
     * 删除文章
     * return boolen
     */
    public function delete()
    {
        $art_cat = Db::name('Article_category');
        $id = intval(input('id'));
        $re = $art_cat->where(['id'=>$id])->delete();
        if ($re) {
           return $this->success('删除成功');
        } else {
            $this->error('删除失败');
            return;
        }
    }
}


?>
