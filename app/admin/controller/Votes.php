<?php
namespace app\admin\controller;

use app\common\model\GameLockLog;
use app\common\model\UsersVotes;
use app\common\model\UsersVotesAward;
use app\common\model\UsersVotesConfig;
use app\common\model\UsersVotesPay;
use think\Db;
use think\Request;

class Votes extends Admin
{
    //投票俱乐部列表
    public function index(Request $request)
    {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['user_id'] = $user_id;

        $user_phone = $request->get('user_phone');
        if($user_phone) {
            if(checkEmail($user_phone)) {
                $user = \app\common\model\Member::where(['email'=>$user_phone])->find();
            } else {
                $user = \app\common\model\Member::where(['phone'=>$user_phone])->find();
            }
            if($user){
                $where['user_id'] = $user['member_id'];
            } else {
                $where['user_id'] = 0;
            }
        }
        $where['num'] = ['gt',0];

        $list = UsersVotes::with('users')->where($where)->order('add_time desc')->paginate(null, false, ['query' => $request->get()]);
        if($list){
            foreach ($list as &$value){
                $value['level'] = UsersVotesConfig::getLevelName($value['level']);
            }
        }
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }

    //投票俱乐部支付列表
    public function votes_pay(Request $request) {
        $where = [];
        $user_id = $request->param('user_id');
        if ($user_id) $where['user_id'] = $user_id;

        $user_phone = $request->param('user_phone');
        if($user_phone) {
            if(checkEmail($user_phone)) {
                $user = \app\common\model\Member::where(['email'=>$user_phone])->find();
            } else {
                $user = \app\common\model\Member::where(['phone'=>$user_phone])->find();
            }
            if($user){
                $where['user_id'] = $user['member_id'];
            } else {
                $where['user_id'] = 0;
            }
        }

        $list = UsersVotesPay::with('users')->where($where)->order('id desc')->paginate(null, false, ['query' => $request->param()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }

    //投票俱乐部收益列表
    public function votes_award(Request $request) {
        $where = [];
        $user_id = $request->param('user_id');
        if ($user_id) $where['user_id'] = $user_id;

        $user_phone = $request->param('user_phone');
        if($user_phone) {
            if(checkEmail($user_phone)) {
                $user = \app\common\model\Member::where(['email'=>$user_phone])->find();
            } else {
                $user = \app\common\model\Member::where(['phone'=>$user_phone])->find();
            }
            if($user){
                $where['user_id'] = $user['member_id'];
            } else {
                $where['user_id'] = 0;
            }
        }
        $third_id = $request->param('third_id');
        if($third_id) $where['third_id'] = $third_id;

        $list = UsersVotesAward::with('users')->where($where)->order('id desc')->paginate(null, false, ['query' => $request->param()]);
        $sum = UsersVotesAward::where($where)->sum('num');
        $page = $list->render();

        $release_list = [];
        if($third_id) $release_list = GameLockLog::where(['third_id'=>$third_id,'type'=>'release'])->order('id desc')->select();
        return $this->fetch(null, compact('list', 'page','sum','release_list'));
    }

    //投票等级配置
    public function votes_level_config() {
        $list = UsersVotesConfig::where(['uvs_type'=>'level'])->select();
        return $this->fetch(null, compact('list'));
    }

    //投票配置
    public function votes_config() {
        $list = UsersVotesConfig::where(['uvs_type'=>'base'])->select();
        return $this->fetch(null, compact('list'));
    }

    //投票配置更新
    public function votes_config_update() {
        $allow_field = ['uvs_value','uvs_available_percent','uvs_percent','uvs_desc'];
        $id = intval(input('id'));
        $info = UsersVotesConfig::where(['uvs_id'=>$id])->find();
        if(empty($info)) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'配置不存在']);

        $check_filed = $filed = input('field');
        if(empty($filed) || !in_array($filed,$allow_field)) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'不允许修改']);

        $value = input('value');
        if(in_array($check_filed,['uvs_available_percent','uvs_percent']) && ($value<0 || $value>100) ) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'比例填写错误']);

        $data = [$filed=>$value];
        if($info['uvs_type']=='base' && in_array($info['uvs_key'],['votes_game_release_percent','votes_award_game_lock_percent'])) {
            $data['uvs_value'] = $data['uvs_percent'] = $value;
        }
        $flag = UsersVotesConfig::where(['uvs_id'=>$info['uvs_id']])->update($data);
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }

    //修改等级
    public function votes_level_update(){
        $user_id = intval(input('user_id'));
        $level = intval(input('level'));
        $info = UsersVotes::where(['user_id'=>$user_id])->find();
        if($this->request->isGet()){
            $level_list = UsersVotesConfig::where(['uvs_type'=>'level'])->select();
            return $this->fetch(null, compact('info','level_list'));
        } else {
            $flag = UsersVotes::where(['user_id'=>$user_id])->setField('level',$level);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
            }
        }
    }

    //授权修改下级等级
    public function change_child_level() {
        $user_id = intval(input('id'));
        $value = intval(input('value'));
        $flag = UsersVotes::where(['user_id'=>$user_id])->setField('change_child_level_open',$value);
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }

    public function summary(Request $request) {
        $where = [];
        $list = Db::name('users_votes_summary')->where($where)->order('id desc')->paginate(null, false, ['query' => $request->param()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page','sum'));
    }
}