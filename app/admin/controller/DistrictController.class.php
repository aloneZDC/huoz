<?php

namespace Admin\Controller;

use Admin\Controller\AdminController;
use Think\Page;

class DistrictController extends AdminController
{
    // 空操作
    public function __construct()
    {
        parent::__construct();
    }

    public function districts($category_id='',$keywords='')
    {
        //添加各种查询条件
        $district = M('district');
        if ($category_id&&!$keywords) {
            $where='group_id='.$category_id;
        }elseif(!$category_id&&$keywords){
            $where[]="content like '%$keywords%'";

        }elseif($category_id&&$keywords){
            $where[]="group_id=$category_id and content like '%$keywords%'";
        }
        else{
            $where[] = "1=1";
        }
        $count = $district->alias("district")->where($where)->count();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count, 20);
        //将分页（点击下一页）需要的条件保存住，带在分页中
        foreach ($where as $key => $value) {
            $Page->parameter[$key]=urlencode($value);
        }
        // 分页显示输出
        $show = $Page->show();

        $field = "district.id,district_group.name,from_unixtime(district.add_time, '%Y-%m-%d %H:%i:%s') as add_time,district.content,yang_district_attachments.src";
        $result = $district->alias("district")
            ->join("left join yang_district_group as district_group on district.group_id = district_group.id")
            ->join('left join yang_district_attachments on district.id=yang_district_attachments.district_id')
            ->field($field)
            ->where($where)
            ->order("district.add_time desc")
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        foreach ($result as $key => $value) {
            $result[$key]['src']=unserialize($value['src']);
        }
        $this->get_category();
        //获取分类信息
        $this->assign('category_id',$category_id);
        $this->assign('result', $result);
        $this->assign('keywords',$keywords);
        $this->assign('page', $show); // 赋值分页输出
        $this->display('districts');
    }
    public function get_category(){
        //获取分类群组
        $sql='select id,name from yang_district_group';
        $res=M('district_group')->query($sql);
        $this->assign('cat',$res);

    }
    public function search_district(){
        //查找商圈信息
        $search=I('get.');
        if (!$search['category']&&!$search['keywords']) {
            //查询全部信息
            $this->districts();
        }elseif($search['category']&&!$search['keywords']){
            //使用分类查询信息
            $this->districts(trim($search['category']));
        }elseif(!$search['category']&&$search['keywords']){
            //使用关键词模糊查询
            $this->districts('',trim($search['keywords']));
        }else{
            //使用关键词和分类查询
            $this->districts(trim($search['category']),trim($search['keywords']));
        }

    }
    public function comments($keywords='')
    {
        //评论信息
        //查询条件判断
        if ($keywords) {
            $where[]="content like '%$keywords%'";
        }else{
            $where[]='1=1';
        }

        $district_comments = M('district_comments');
        $count = $district_comments->alias("district_comments")->where($where)->count();

        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count, 20);
        //将分页（点击下一页）需要的条件保存住，带在分页中
        foreach ($where as $key => $value) {
            $Page->parameter[$key]=urlencode($value);
        }
        // 分页显示输出
        $show = $Page->show();
        $res = $district_comments->alias("district_comments")->where($where)->order('add_time desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('page',$show);
        $this->assign('keywords',$keywords);
        $this->assign('comments',$res);
        $this->display('comments');
    }
    public function comments_update(){
        //更新评论内容
        if (IS_GET) {
            $comments_id=intval(I('get.id'));
            $comment=M('district_comments')->where('id='.$comments_id)->field('id,content')->find();
            $comment['content']=htmlspecialchars_decode($comment['content']);
            $this->assign('comment',$comment);
            $this->display();
        }else{
            $comment['id']=I('post.id');
            $comment['content']=I('post.content');
            $res=M('district_comments')->save($comment);
            if ($res) {
                $this->redirect('district/comments');
            }else{
                $this->error('修改失败');
            }
        }

    }
    public function district_update(){
        //修改动态
        if (IS_GET) {
            $district_id=intval(I('get.id'));
            $res=M('district')
                ->join('left join yang_district_group on yang_district_group.id=yang_district.group_id')
                ->field('yang_district_group.id as group_id,yang_district.content,yang_district.id')
                ->where('yang_district.id='.$district_id)
                ->find();
            $this->get_category();
            $res['content']=htmlspecialchars_decode($res['content']);
            $this->assign('district',$res);
            $this->display();
        }else{
            $district_content=I('post.');
            $res=M('district')->save($district_content);
            if ($res) {
                $this->redirect('district/districts');
            }else{
                $this->error('修改失败');
            }
        }

    }
    public function group()
    {
        //群组的信息
        $sql='select id,name,logo from yang_district_group';
        $group=M('district_group')->query($sql);
        $this->assign('group',$group);
        $this->display();
    }

    public function district_delete(){
        //删除商圈动态
        $district_id=intval(I('get.id'));
        $district=M('district');
        $district_info=$district->where('id='.$district_id)->field('view_count,group_id')->find();
        $group_id=$district_info['group_id'];
        $view_count=$district_info['view_count'];
        $res=$district->where(array('id'=>$district_id))->delete();
        if (!$res) {
            $this->error('删除失败');
        }else{
            //删除相关表里面对应的district_id字段的数据
            M('district_attachments')->where(['district_id' => $district_id])->delete();
            M('district_comments')->where(['district_id' => $district_id])->delete();
            M('district_likes')->where(['district_id' => $district_id])->delete();

            $district_group=M('district_group');
            //群组表信息总量 -1
            $district_group->where(['id' => $group_id])->setDec("note_count", 1);
            //群组表人气值 -view_count的数量
            $district_group->where(['id' => $group_id])->setDec("popularity_count", intval($view_count));

            $this->redirect('district/districts');
        }
    }
    public function district_del_all(){
        //批量删除
        $check=I('post.subBox');
        //获取评论总数
        $count=count($check);
        $check=implode(',',$check);
        if (!$check) {
            $this->error('请选择要删除的数据');
        }
        //获取总的view_count数量
        $model=M('district');
        $view_list=$model->where(array('id'=>array('in',$check)))->field('view_count')->select();
        $view_count=0;
        foreach ($view_list as $k => $v) {
            $view_count+=$v['view_count'];
        }
        $res=$model->where(array('id'=>array('in',$check)))->delete();
        if ($res) {
            //删除相关表里面对应的district_id字段的数据
            M('district_attachments')->where(array('district_id'=>array('in',$check)))->delete();
            M('district_comments')->where(array('district_id'=>array('in',$check)))->delete();
            M('district_likes')->where(array('district_id'=>array('in',$check)))->delete();

            $district_group=M('district_group');
            //群组表信息总量 -删除数量
            $district_group->where(['id' => $group_id])->setDec("note_count", $count);
            //群组表人气值 -view_count的数量
            $district_group->where(['id' => $group_id])->setDec("popularity_count", $view_count);
            $this->redirect('district/districts');
        }else{
            $this->error('批量删除失败');
        }
    }
    public function addGroup(){
        //添加群组
        if (IS_GET) {
            $this->display();
        }else{
            $group['name']=I('post.group_name');
            $group['logo']=I('post.logo_address_text');
            if (!$group['name']) {
                $this->error('群组名不能为空');
            }
            $model=M('district_group');
            $res=$model->where(array('name'=>$group['name']))->find();
            if ($res) {
                $this->error('群组已经存在');
            }else{
                //获取图片信息
                if ($_FILES['logo_address']['size']>0) {
                    $upload = $this->oss_upload($_FILES, 'images', true);
                    if(!$upload['logo_address']){
                        $this->error('图片上传失败');
                    }
                    $group['logo'] = $upload['logo_address'];
                }
                $res=$model->add($group);
                if ($res) {
                    $this->redirect('district/group');
                }else{
                    $this->error('添加失败');
                }
            }
        }
    }

    public function editGroup(){
        //修改群组
        if (IS_GET) {
            $group_id=intval(I('get.id'));
            $res=M('district_group')->where(array('id'=>$group_id))->find();
            if (!$res) {
                $this->error('记录不存在');
            }
            $this->assign('group',$res);
            $this->display();
        }else{
            $group['id']=I('post.group_id');
            $group['name']=I('post.group_name');
            $group['logo']=I('post.logo_address_text');
            if (!$group['name']) {
                $this->error('群组名不能为空');
            }
            $model=M('district_group');

            if ($_FILES['logo_address']['size']>0) {
                //获取图片信息
                $upload = $this->oss_upload($_FILES, 'images', true);
                if(!$upload['logo_address']){
                    $this->error('图片上传失败');
                }
                $group['logo'] = $upload['logo_address'];
            }
            $res=$model->save($group);
            if ($res) {
                $this->redirect('district/group');
            }else{
                $this->error('修改失败');
            }
        }

    }
    public function delGroup(){
        //删除群组
        $group_id=intval(I('get.id'));
        $res=M('district_group')->where(array('id'=>$group_id))->delete();
        if ($res) {
            $this->redirect('district/group');
        }else{
            $this->error('删除失败');
        }
    }
    public function comments_delete(){
        //删除评论
        $comments_id=intval(I('get.id'));
        $comments=M('district_comments');
        $district_id=$comments->where('id='.$comments_id)->field('district_id')->find();
        //获取动态ID
        $res=$comments->where(array('id'=>$comments_id))->delete();
        if ($res) {
            //动态评论数-1
            M('district')->where(array('id'=>$district_id['district_id']))->setDec("comment_count",1);
            $this->redirect('district/comments');
        }else{
            $this->error('删除失败');
        }
    }
    public function search_district_comments(){
        //查询评论
        $keywords=I('get.keywords');
        $this->comments(trim($keywords));
    }

//-------------------------------------------------------------------------------------------------------------------------------//


    /**
     * 商圈列表
     */
    public function districtGroupList()
    {
        $count = M('district_group')->count();
        $Page = new Page($count, 15);
        $district_list = M('district_group')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $show = $Page->show();// 分页显示输出
        $this->assign('district_list', $district_list);
        $this->assign('page', $show);
        $this->display();
    }

    /**
     * 添加商圈
     */
    public function addDistrictGroup()
    {
        $district_group = D('DistrictGroup');
        if (IS_POST) {
            $id = I('post.id');
            if ($_FILES["logo"]["tmp_name"]) {
                $_POST['logo'] = $this->upload($_FILES["logo"]);
                if (!$_POST['logo']) {
                    $this->error('非法上传');
                }
            }
            if ($r = $district_group->create()) {
                if (!$id) {
                    if ($district_group->add()) {
                        $this->success('添加成功', U('District/districtGroupList'));
                    } else {
                        $this->error('操作失败');
                    }
                } else {
                    if ($district_group->save()) {
                        $this->success('修改成功', U('District/districtGroupList'));
                    } else {
                        $this->error('操作失败');
                    }
                }
            } else {
                $this->error($district_group->getError());
            }
        } else {
            $id = I('get.id');
            if ($id) {
                $group = $district_group->getAdminPayment(array('id' => $id));
                $this->assign('district_group', $group);
            }
            $this->display();
        }
    }

    /**
     * 删除商圈
     * @param $id
     */
    public function delDistrictGroup($id)
    {
        $condition = array('id' => $id);
        $map['group_id'] = $id;
        if (M('District')->where($map)->count()) {
            $this->ajaxReturn(array('msg' => '操作失败，此商圈下有动态，不能删除', 'code' => 0));
        }
        if (M('DistrictGroup')->where($condition)->count()) {
            if (M('DistrictGroup')->where($condition)->delete()) {
                $this->ajaxReturn(array('msg' => '删除成功', 'code' => 1));
            } else {
                $this->ajaxReturn(array('msg' => '操作失败', 'code' => 0));
            }
        } else {
            $this->ajaxReturn(array('msg' => '信息不存在', 'code' => 0));
        }
    }

    /**
     * 动态列表
     */
    public function districtList()
    {
        $count = M('district')->count();
        $Page = new Page($count, 15);
        $district_list = M('district')->alias('d')
            ->join('yang_district_group as dg on dg.id = d.group_id')
            ->field('d.*,dg.name')
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $show = $Page->show();// 分页显示输出
        $this->assign('district_list', $district_list);
        $this->assign('page', $show);
        $this->display();
    }

    /**
     * 删除动态
     * @param $id
     */
    public function delDistrict($id)
    {
        $district_id = $id;
        $district = M('district');
        $district_attachments = M('district_attachments');
        $district_comments = M('district_comments');
        $district_likes = M('district_likes');
        $district_group = M("district_group");
        $district_info = $district->field("group_id,member_id,view_count")->where(['id' => $district_id])->find();
        if (empty($district_info)) {
            $this->ajaxReturn(array('msg' => '删除成功', 'code' => 1));
        }

        $district->where(['id' => $district_id])->delete();
        $district_attachments->where(['district_id' => $district_id])->delete();
        $district_comments->where(['district_id' => $district_id])->delete();
        $district_likes->where(['district_id' => $district_id])->delete();

        //群组表信息总量 -1
        $district_group->where(['id' => $district_info['group_id']])->setDec("note_count", 1);
        //群组表人气值 -1
        $district_group->where(['id' => $district_info['group_id']])->setDec("popularity_count", intval($district_info['view_count']));

        $this->ajaxReturn(array('msg' => '删除成功', 'code' => 1));
    }

    /**
     * 评论列表
     */
    public function commentList()
    {
        $count = M('district_comments')->count();
        $Page = new Page($count, 15);
        $list = M('district_comments')
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $show = $Page->show();// 分页显示输出
        $this->assign('district_comments_list', $list);
        $this->assign('page', $show);
        $this->display();
    }

    /**
     * 删除评论
     * @param $id
     */
    public function delComment($id)
    {
        $comment_id = $id;

        $district = M('district');
        $district_comments = M('district_comments');

        $district_id = $district_comments->field("district_id,member_id")->where(['id' => $comment_id])->find();
        if (empty($district_id)) {
            $this->ajaxReturn(array('msg' => '删除成功', 'code' => 1));
        }

        $district_info = $district->where(['id' => $district_id['district_id']])->find();
        if (empty($district_info)) {
            $this->ajaxReturn(array('msg' => '删除成功', 'code' => 1));
        }
        $comment_sum = $district_comments->where(['id' => $comment_id])->count();
        $comment_sum += $district_comments->where(['parent_id' => $comment_id])->count();

        $district_comments->where(['id' => $comment_id])->delete();
        $district_comments->where(['parent_id' => $comment_id])->delete();
        $district->where(['id' => $district_id['district_id']])->setDec('comment_count', $comment_sum);

        $comment_count = $district->field("comment_count")->where(['id' => $district_id['district_id']])->find()['comment_count'];
        if ($comment_count < 0) {
            $district->where(['id' => $district_id['district_id']])->save(['comment_count' => 0]);
        }

        $this->ajaxReturn(array('msg' => '删除成功', 'code' => 1));
    }
}