<?php
namespace app\admin\controller;
use think\Request;

class Visa extends Admin
{
    //投票俱乐部列表
    public function index(Request $request)
    {
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

        $list = \app\common\model\Visa::with(['users','verify'])->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }


    public function edit(){
        if($this->request->isPost()){
            $id = input('id');
            $form = input('post.');

            $form['auth_time'] = time();
            $flag = \app\common\model\Visa::where('id',$id)->update($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = \app\common\model\Visa::where('id',$id)->find();
            return $this->fetch(null,compact('info'));
        }
    }
}
