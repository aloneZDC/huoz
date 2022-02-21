<?php
namespace app\admin\controller;

use app\common\model\HongbaoAwardLog;
use app\common\model\HongbaoChaiLog;
use app\common\model\HongbaoConfig;
use app\common\model\HongbaoLog;
use app\common\model\HongbaoTimeSetting;
use think\Db;
use think\Request;

class Hongbao extends Admin
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
        $is_super = input('is_super');
        if($is_super==1) $where['super_num'] = ['gt',0];

        $hongbao_config = HongbaoConfig::get_key_value();
        $hongbao_continue_time=$hongbao_config['hongbao_continue_time'];
        $hongbao_back_time=$hongbao_config['hongbao_back_time'];
        $timeSetting = HongbaoTimeSetting::getTimeSetting();

        $list = HongbaoLog::with(['users','currency','backcurrency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        foreach ($list as &$value){
            $next_time_start = HongbaoTimeSetting::getNextTime($timeSetting,$value['last_open'],$hongbao_continue_time);
            //下一次拆红包时间  0为待返还
            $value['next_time_start'] = ($next_time_start < ($value['create_time']+$hongbao_back_time)) ? $next_time_start : 0;
            $value['next_time_stop'] = $value['next_time_start']>0 ? $value['next_time_start']+$hongbao_continue_time : 0;
        }

        $page = $list->render();
        $wait_back = HongbaoLog::where(['is_back'=>0])->sum('num');
        return $this->fetch(null, compact('list', 'page','wait_back'));
    }

    function chai_log() {
        $log_id = intval(input('log_id'));
        $where['log_id'] = $log_id;
        $list = HongbaoChaiLog::with(['users','currency'])->where($where)->order('id desc')->select();
        return $this->fetch(null, compact('list'));
    }

    function award_log() {
        $third_id = intval(input('third_id'));
        $where['third_id'] = $third_id;
        $list = HongbaoAwardLog::with(['users','currency'])->where($where)->order('id desc')->select();
        return $this->fetch(null, compact('list'));
    }


    //投票配置
    public function config() {
        $list = HongbaoConfig::order('type asc,uvs_id asc')->select();
        return $this->fetch(null, compact('list'));
    }

    //投票配置更新
    public function config_update() {
        $allow_field = ['value'];
        $id = intval(input('id'));
        $info = HongbaoConfig::where(['uvs_id'=>$id])->find();
        if(empty($info)) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'配置不存在']);

        $check_filed = $filed = input('field');
        if(empty($filed) || !in_array($filed,$allow_field)) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'不允许修改']);

        $value = input('value');
        if(in_array($check_filed,['hongbao_fee','hongbao_award_percent2','hongbao_award_percent1','hongbao_min_percent','hongbao_max_percent']) && ($value<0 || $value>100) ) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'比例填写错误']);

        $data = [$filed=>$value];
        $flag = HongbaoConfig::where(['uvs_id'=>$info['uvs_id']])->update($data);
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }

    public function time_setting() {
        $list = HongbaoTimeSetting::order('hour asc,minute asc')->select();
        return $this->fetch(null, compact('list'));
    }

    public function time_add(){
        if($this->request->isPost()){
            $hour = intval(input('hour'));
            $minute = intval(input('minute'));
            $flag = HongbaoTimeSetting::insertGetID([
                'hour' => $hour,
                'minute' => $minute
            ]);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            return $this->fetch(null);
        }
    }

    public function time_delete() {
        $hour = intval(input('hour'));
        $minute = intval(input('minute'));
        $flag = HongbaoTimeSetting::where(['hour'=>$hour,'minute'=>$minute])->delete();
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }
}
