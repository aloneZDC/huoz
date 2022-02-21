<?php


namespace app\admin\controller;


use think\Db;
use think\Request;

class Sender extends Admin
{

    private $status = [0, 1, 2, 3];
    private $status_enum = ["未处理", "正在处理", "处理成功", "处理失败"];

    public function emailHistory(Request $request)
    {
        $email = $request->get('email', null);
        $where = [];

        if ($email) {
            $where['email'] = $email;
        }


        $list = Db::name('email_task_history')->where($where)->order('id', 'desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count', 'status_enum'));
    }

    public function emailConfig()
    {
        $list = Db::name('email_system')->paginate(null, false);
        $page = $list->render();
        $total = $list->total();

        return $this->fetch(null, ['list' => $list, 'page' => $page, 'total' => $total]);
    }


    public function editEmailConfig(Request $request)
    {
          if ($request->isPost()) {
                $data = $request->post();
                $res = Db::name("email_system")->update($data);
                if (false === $res) {
                    $this->error("系统错误修改失败!");
                }
                $this->success("修改成功！", url('Sender/emailConfig'));
          }
          $id = $request->param('id');
          if (empty($id)) {
              return "参数错误";
          }
          $data = Db::name('email_system')->where('es_id', $id)->find();
          return $this->fetch(null, ['data' => $data]);
    }


    public function deleteEmailConfig(Request $request)
    {
        $id = $request->param('id');
        if (empty($id)) {
            $this->error('参数错误!', url('Sender/emailConfig'));
        }

        $res = Db::name('email_system')->where('es_id', $id)->delete();
        if ($res == false) {
            $this->error("系统错误操作失败", url('Sender/emailConfig'));
        }

        $this->success("删除成功!", url('Sender/emailConfig'));
    }

    public function addEmailConfig(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();
            $res = Db::name('email_system')->insert($data);
            if (empty($res)) {
                $this->error("系统错误操作失败");
            }
            $this->success("添加成功!", url('Sender/emailConfig'));
        }

        return $this->fetch();
    }

    public function emailTask(Request $request)
    {
        $status = $request->get('status', null);
        $email = $request->get('email', null);
        $where = [];

        if ($email) {
            $where['email'] = $email;
        }

        if (!is_null($status) and in_array($status, $this->status)) {
            $where['status'] = $status;
        }
        $status_enum = $this->status_enum;
        $list = Db::name('email_task')->where($where)->order('id', 'desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count', 'status_enum'));
    }

    public function phoneTask(Request $request)
    {
        $status = $request->get('status', null);
        $phone = $request->get('phone', null);
        $where = [];

        if ($phone) {
            $where['pt.phone'] = $phone;
        }

        if (in_array($status, $this->status) and !is_null($status)) {
            $where['pt.status'] = $status;
        }

        $status_enum = $this->status_enum;
        $list = Db::name('phone_task')->field('pt.*, cc.cn_name as cn_name')->alias('pt')->order('pt.id', 'desc')->join('countries_code cc', 'pt.country_code = cc.phone_code', 'LEFT')->where($where)->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();

        return $this->fetch(null, compact('list', 'page', 'count', 'status_enum'));
    }

    public function phoneConfig()
    {
        $list = Db::name('send_system')->select();

        return $this->fetch(null, ['list' => $list]);
    }


    public function phoneHistory(Request $request)
    {
        $phone = $request->get('phone', null);
        $where = [];
        if ($phone) {
            $where['pt.phone'] = $phone;
        }

        $list = Db::name('phone_task_history')->field('pt.*, cc.cn_name as cn_name')->alias('pt')->order('pt.id', 'desc')->join('countries_code cc', 'pt.country_code = cc.phone_code', 'LEFT')->where($where)->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();

        return $this->fetch(null, compact('list', 'page', 'count'));
    }
}