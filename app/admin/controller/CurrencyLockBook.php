<?php
namespace app\admin\controller;
use app\common\model\CurrencyNodeLock;
use think\Request;

class CurrencyLockBook extends Admin
{
    //太空计划
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
        $status = $this->request->get('status');
        if($status!='') $where['status'] = intval($status);

        $field = $this->request->get('field');
        if($field!='') $where['field'] = $field;

        $third_id = intval(input('third_id'));
        if($third_id) {
            $where['third_id'] = $third_id;
            $where['type'] = ['in',['award','t_award']];
        }

        $list = \app\common\model\CurrencyLockBook::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $field_list = \app\common\model\CurrencyLockBook::SUPPORT_FIELD;
        $field_type_list = \app\common\model\CurrencyLockBook::FIELD_TYPE_LIST_NAME;
        return $this->fetch(null, compact('list', 'page','field_list','field_type_list'));
    }

    public function move_index(Request $request) {
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

        //昨日入金汇总
        $today = todayBeginTimestamp();
        $yestoday_summary = \app\common\model\CurrencyNodeLock::field('sum(pay_num) as pay_num,sum(actual_num) as actual_num')->where(['create_time'=>['between',[$today-86400,$today-1] ]])->find();
        //今日入金汇总
        $today_summary = \app\common\model\CurrencyNodeLock::field('sum(pay_num) as pay_num,sum(actual_num) as actual_num')->where(['create_time'=>['between',[$today,$today+86399] ]])->find();

        $list = \app\common\model\CurrencyNodeLock::with(['users','currency','paycurrency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page','yestoday_summary','today_summary'));
    }

    public function move_recommand_index(Request $request) {
        $where = [];
        $user_id = $request->get('user_id');
        if ($user_id) $where['member_id'] = $user_id;

        $user_phone = $request->get('user_phone');
        if($user_phone) {
            if(checkEmail($user_phone)) {
                $user = \app\common\model\Member::where(['email'=>$user_phone])->find();
            } else {
                $user = \app\common\model\Member::where(['phone'=>$user_phone])->find();
            }
            if($user){
                $where['member_id'] = $user['member_id'];
            } else {
                $where['member_id'] = 0;
            }
        }
        $status = $this->request->get('status');
        if($status!='') $where['status'] = intval($status);

        $third_id = intval(input('third_id'));
        if($third_id) $where['third_id'] = $third_id;

        $where['field'] = \app\common\model\CurrencyNodeLock::LOCK_FIELD;
        $list = \app\common\model\CurrencyLockBook::with(['users','currency'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }
}
