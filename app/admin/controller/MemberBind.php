<?php
namespace app\admin\controller;

use app\common\model\HongbaoAwardLog;
use app\common\model\HongbaoChaiLog;
use app\common\model\HongbaoConfig;
use app\common\model\HongbaoLog;
use app\common\model\HongbaoTimeSetting;
use think\Db;
use think\Request;

class MemberBind extends Admin
{
    //树形结构
    public function ztree(){
        $where = [];
        $phone = input('phone');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['m.email'] = $phone;
            } else {
                $where['m.phone'] = $phone;
            }
        }
        $member_id=input('member_id','');
        if($member_id){
            $where['a.member_id']=$member_id;
        }

        //根据分类或模糊查找数据数量
        $userinfo=[];
        $users=[];
        if(!empty($member_id)||!empty($phone)){
            $userinfo = Db::name('member')->alias("a")->where($where)->find();
            if($userinfo){
                $account = $userinfo['phone'] ? $userinfo['phone']: $userinfo['email'];
                $users=["id"=>$userinfo['member_id'],"pId"=>0,"name"=>$userinfo['member_id'].'_'.$account,"open"=>false,"isParent"=>true];
                $total_child = Db::name('member_bind')->where(['member_id'=>$userinfo['member_id']])->count();
                $total_child_one = Db::name('member_bind')->where(['member_id'=>$userinfo['member_id'],'level'=>1])->count();
                $userinfo['next_leve_num'] = $total_child;
                $userinfo['total_child_one'] = $total_child_one;
            }
        }

        $where['phone']=$phone;
        $where['member_id']=$member_id;
        $users = json_encode($users);
        return $this->fetch('',compact('userinfo','where','users'));
    }
    /**
     * @标
     * 树形结构获取子节点数据
     */
    public function getChildNode()
    {
        $member_id = input('id');
        $model_member=Db::name('member');
        $model_bind=Db::name("member_bind");
        $user=$model_member->where(['member_id' => $member_id])->find();
        $userList = [];
        if($user){
            $list=$model_bind->where(['member_id'=>$user['member_id'],'level'=>1])->select();
            if($list) {
                foreach ($list as $key=> $val){
                    $child_count = $model_bind->where(['member_id'=>$val['child_id']])->count();
                    $child_count_one = $model_bind->where(['member_id'=>$val['child_id'],'level'=>1])->count();
                    if ($child_count>0) {
                        $isParent = true;
                    } else {
                        $isParent = false;
                    }
                    $member_child = Db::name('member')->where(['member_id'=>$val['child_id']])->field('phone,email')->find();
                    $account = '';
                    if($member_child) $account = $member_child['phone'] ? $member_child['phone']: $member_child['email'];
                    $userList[] = [
                        'name' => $val['child_id'].'_'.$account.'_直推'.$child_count_one.'_所有下级:'.$child_count,
                        'id' =>  $val['child_id'],
                        'pid' => $user['member_id'],
                        'isParent' => $isParent,
                    ];
                }
            }
        }
        return $this->ajaxReturn($userList);
    }
    /**
     * @标
     * 树形结构用户信息
     */
    public function getUserInfo(){
        $user_id = input('post.user_id');
        $userinfo = Db::name('member')->alias("a")->where(['member_id'=>$user_id])->find();
        if($userinfo){
            $total_child = Db::name('member_bind')->where(['member_id'=>$userinfo['member_id']])->count();
            $total_child_one = Db::name('member_bind')->where(['member_id'=>$userinfo['member_id'],'level'=>1])->count();
            $userinfo['next_leve_num'] = $total_child;
            $userinfo['total_child_one'] = $total_child_one;
        }
        $this->ajaxReturn($userinfo);
    }

    public function parents() {
        $member_id = input('member_id');
        $list = \app\common\model\MemberBind::alias('a')->field('a.*,m.email,m.phone')
            ->join(config("database.prefix")."member m", "a.member_id=m.member_id", "LEFT")
            ->where(['a.child_id'=>$member_id])->limit(1000)->order('level desc')->select();
        return $this->fetch(null, compact('list'));
    }
}
