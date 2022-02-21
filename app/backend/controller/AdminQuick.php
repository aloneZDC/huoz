<?php
namespace app\backend\controller;

use think\Exception;
use think\Request;

class AdminQuick extends Admin
{
    protected $allow_switch_field = [];
    protected $allow_delete = false;
    protected  $model = null;
    protected  $pid = 'id';

    /**
     * 使用时请先定义model 及 主键ID
     * 通用快速切换操作
     * @return \think\response\Json
     */
    public function quick_switch(){
        $id = intval(input('id'));
        $field = input('field');
        if(empty($field) || !in_array($field,$this->allow_switch_field)) return $this->successJson(ERROR1,"特殊配置,不支持快捷开关",null);

        $status = intval(input('status'));
        if(!in_array($status,[0,1,2])) return $this->successJson(ERROR2,"非法操作",null);

        if($this->model==null) return $this->successJson(ERROR3,"非法操作",null);

        try{
            $info = $this->model->where([$this->pid=>$id])->find();
            if(!$info) throw new Exception("记录不存在,不能执行该操作");
            if(!isset($info[$field])) throw new Exception("非法字段,不能执行该操作");

            $flag = $this->model->where([$this->pid=>$id])->setField($field,$status);
            if($flag===false) throw new Exception("操作失败");

            return $this->successJson(SUCCESS,"操作成功",null);
        } catch(Exception $e){
            return $this->successJson(ERROR4,$e->getMessage(),null);
        }
    }

    public function quick_delete() {
        $id = intval(input('id'));
        if(!$this->allow_delete) return $this->successJson(ERROR1,"特殊配置,不支持快捷删除",null);

        if($this->model==null) return $this->successJson(ERROR3,"非法操作",null);

        try{
            $info = $this->model->where([$this->pid=>$id])->find();
            if(!$info) throw new Exception("记录不存在,不能执行该操作");

            $flag = $this->model->where([$this->pid=>$id])->delete();
            if($flag===false) throw new Exception("操作失败");

            return $this->successJson(SUCCESS,"操作成功",null);
        } catch(Exception $e){
            return $this->successJson(ERROR4,$e->getMessage(),null);
        }
    }


    //通用列表
    public function index(Request $request)
    {
        $where = $this->indexWhere($request);
        $with = $this->indexWith();
        if(!empty($with)) {
            $this->model->with($with);
        }

        $list = $this->model->where($where)->order($this->pid." desc")->paginate(null, null, ["query" => $this->request->get()]);
        $page = $list->render();
        $count = $list->total();
        $this->indexBeforeFetch();
        return $this->fetch(null, compact('list','page','count'));
    }

    //通用列表查询条件
    protected function indexWhere(Request $request) {
        return [];
    }
    //通用列表
    protected function indexWith() {
        return [];
    }
    //通用列表渲染前
    protected function indexBeforeFetch() {}


    //通用添加
    public function add(Request $request)
    {
        if ($request->isPost()) {
            $form = input('form/a');
            $form = $this->addFilter($form);
            $result = $this->model->save($form);
            if (false === $result) {
                return $this->successJson(ERROR1,"操作失败:" . $this->model->getError(), null);
            } else {
                return $this->successJson(SUCCESS,"操作成功", ['url' => url('')]);
            }
        }
        $this->addBeforeFetch();
        return $this->fetch();
    }

    protected function addBeforeFetch() {}
    protected function addFilter($form) {
        return $form;
    }

    //通用编辑
    public function edit(Request $request)
    {
        if($this->request->isPost()) {
            $id = intval(input('id'));

            $form = input('form/a');
            $info = $this->model->where([$this->pid=>$id])->find();
            if(empty($info)) return $this->successJson(ERROR1,"该记录不存在",null);

            $form = $this->editFilter($form);
            $result = $this->model->save($form,[$this->pid=>$info[$this->pid]]);
            if(false === $result){
                return $this->successJson(ERROR1,"操作失败:".$this->model->getError(),null);
            } else {
                return $this->successJson(SUCCESS,"操作成功",['url'=>url('')]);
            }
        } else {
            $id = intval(input('id'));
            $info = $this->model->where([$this->pid=>$id])->find();
            if(empty($info)) return $this->error("该记录不存在");

            $this->editBeforeFetch();
            return $this->fetch(null,compact('info'));
        }
    }

    protected function editBeforeFetch() {}

    protected function editFilter($form) {
        return $form;
    }
}
