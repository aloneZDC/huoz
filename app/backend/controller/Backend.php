<?php

namespace app\backend\controller;

use think\Request;

/**
 * 后台管理
 * Class Backend
 * @package app\backend\controller
 */
class Backend extends AdminQuick
{
    protected $allow_switch_field = ['status'];
    protected $public_action = ['menu_switch', 'menu_cat_switch', 'menu_add', 'menu_del', 'menu_edit'];

    /**
     * 配置管理
     * @param Request $request
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function config_update(Request $request)
    {
        if ($request->isPost()) {
            $form = $request->param('form/a');
            if (!empty($form)) {
                foreach ($form as $key => $item) {
                    \app\common\model\Config::where(['key' => $key])->update([
                        'value' => $item,
                    ]);
                }
            }
            return ['code' => SUCCESS, 'message' => '操作成功', 'data' => ['url' => url('')]];
        }
        $where = ['desc' => ['neq', ''], 'status' => 0];
        $list = \app\common\model\Config::where($where)->select();
        return $this->fetch(null, compact('list'));
    }

    /**
     * 列表菜单
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function menu_list(Request $request)
    {
        $where = [];
        $controller = $request->get('controller');
        if ($controller) $where['controller'] = ['like', "%{$controller}%"];
        $catId = $request->get('cat_id');
        if ($catId) $where['cat_id'] = $catId;

        $list = \app\backend\model\BackendMenu::with(['cat'])->where($where)->order(['id' => 'desc'])->paginate(null, null, ["query" => $this->request->get()]);
        $page = $list->render();
        $count = $list->total();
        $categories = \app\backend\model\BackendMenuCat::where(['status' => 1])->order(['sort_id' => 'asc'])->select();
        return $this->fetch(null, compact('list', 'page', 'count', 'categories'));
    }

    /**
     * 菜单添加
     * @param Request $request
     * @return array|mixed
     * @throws \think\exception\DbException
     */
    public function menu_add(Request $request)
    {
        $model = new \app\backend\model\BackendMenu();
        if ($request->isPost()) {
            $form = $request->param('form/a');
            $form['status'] = isset($form['status']) ? intval($form['status']) : 0;
            $result = $model->validate('BackendMenu.add')->save($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $categories = \app\backend\model\BackendMenuCat::where(['status' => 1])->order(['sort_id' => 'asc'])->select();
        return $this->fetch(null, compact('categories'));
    }

    /**
     * 菜单修改
     * @param Request $request
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function menu_edit(Request $request)
    {
        $model = new \app\backend\model\BackendMenu();
        if ($request->isPost()) {
            $id = $request->param('id', 0, 'intval');
            $form = $request->param('form/a');
            $info = $model->where(['id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);

            $form['status'] = isset($form['status']) ? intval($form['status']) : 0;
            $result = $model->validate('BackendMenu.edit')->save($form, ['id' => $info['id']]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $id = $request->param('id', 0, 'intval');
        $info = $model->where(['id' => $id])->find();
        if (empty($info)) $this->error('该记录不存在');

        $categories = \app\backend\model\BackendMenuCat::where(['status' => 1])->order(['sort_id' => 'asc'])->select();
        return $this->fetch(null, compact('info', 'categories'));
    }

    /**
     * 菜单删除
     * @param Request $request
     * @return array
     */
    public function menu_del(Request $request)
    {
        $id = $request->param('id', 0, 'intval');
        try {
            $info = \app\backend\model\BackendMenu::where(['id' => $id])->find();
            if (!$info) throw new \think\Exception("记录不存在,不能执行该操作");

            $flag = \app\backend\model\BackendMenu::where(['id' => $id])->delete();
            if ($flag === false) throw new \think\Exception("操作失败");

            return $this->successJson(SUCCESS, "操作成功", null);
        } catch (\think\Exception $e) {
            return $this->successJson(ERROR4, $e->getMessage(), null);
        }
    }

    /**
     * 菜单切换操作
     * @param Request $request
     * @return array
     */
    public function menu_switch(Request $request)
    {
        $id = $request->param('id', 0, 'intval');
        $field = $request->param('field', '');
        if (empty($field) || !in_array($field, $this->allow_switch_field)) return $this->successJson(ERROR1, "特殊配置,不支持快捷开关", null);

        $status = $request->param('status', 0, 'intval');
        if (!in_array($status, [0, 1, 2])) return $this->successJson(ERROR2, "非法操作", null);

        try {
            $info = \app\backend\model\BackendMenu::where([$this->pid => $id])->find();
            if (!$info) throw new \think\Exception("记录不存在,不能执行该操作");
            if (!isset($info[$field])) throw new \think\Exception("非法字段,不能执行该操作");

            $flag = \app\backend\model\BackendMenu::where(['id' => $id])->setField($field, $status);
            if ($flag === false) throw new \think\Exception("操作失败");

            return $this->successJson(SUCCESS, "操作成功", null);
        } catch (\think\Exception $e) {
            return $this->successJson(ERROR4, $e->getMessage(), null);
        }
    }

    /**
     * 菜单分类列表
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function menu_cat_list(Request $request)
    {
        $list = \app\backend\model\BackendMenuCat::order(['id' => 'desc'])->paginate(null, null, ["query" => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        $this->indexBeforeFetch();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 菜单分类添加
     * @param Request $request
     * @return array|mixed
     */
    public function menu_cat_add(Request $request)
    {
        $model = new \app\backend\model\BackendMenuCat();
        if ($request->isPost()) {
            $form = $request->param('form/a');
            $form['status'] = isset($form['status']) ? intval($form['status']) : 0;
            $result = $model->validate('BackendMenuCat.add')->save($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }
        return $this->fetch();
    }

    /**
     * 菜单分类修改
     * @param Request $request
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function menu_cat_edit(Request $request)
    {
        $model = new \app\backend\model\BackendMenuCat();
        if ($this->request->isPost()) {
            $id = $request->param('id', 0, 'intval');
            $form = $request->param('form/a');
            $info = $model->where(['id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);

            $form['status'] = isset($form['status']) ? intval($form['status']) : 0;
            $result = $model->validate('BackendMenuCat.edit')->save($form, ['id' => $info['id']]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $id = $request->param('id', 0, 'intval');
        $info = $model->where(['id' => $id])->find();
        if (empty($info)) $this->error('该记录不存在');
        return $this->fetch(null, compact('info'));
    }

    /**
     * 菜单分类切换操作
     * @param Request $request
     * @return array
     */
    public function menu_cat_switch(Request $request)
    {
        $id = $request->param('id', 0, 'intval');
        $field = $request->param('field', '');
        if (empty($field) || !in_array($field, $this->allow_switch_field)) return $this->successJson(ERROR1, "特殊配置,不支持快捷开关", null);

        $status = $request->param('status', 0, 'intval');
        if (!in_array($status, [0, 1, 2])) return $this->successJson(ERROR2, "非法操作", null);

        try {
            $info = \app\backend\model\BackendMenuCat::where(['id' => $id])->find();
            if (!$info) throw new \think\Exception("记录不存在,不能执行该操作");
            if (!isset($info[$field])) throw new \think\Exception("非法字段,不能执行该操作");

            $flag = \app\backend\model\BackendMenuCat::where(['id' => $id])->setField($field, $status);
            if ($flag === false) throw new \think\Exception("操作失败");

            return $this->successJson(SUCCESS, "操作成功", null);
        } catch (\think\Exception $e) {
            return $this->successJson(ERROR4, $e->getMessage(), null);
        }
    }

    /**
     * 菜单分类删除
     * @param Request $request
     * @return array
     */
    public function menu_cat_del(Request $request)
    {
        $id = $request->param('id', 0, 'intval');
        try {
            $info = \app\backend\model\BackendMenuCat::where(['id' => $id])->find();
            if (!$info) throw new \think\Exception("记录不存在,不能执行该操作");

            $flag = \app\backend\model\BackendMenuCat::where(['id' => $id])->delete();
            if ($flag === false) throw new \think\Exception("操作失败");

            return $this->successJson(SUCCESS, "操作成功", null);
        } catch (\think\Exception $e) {
            return $this->successJson(ERROR4, $e->getMessage(), null);
        }
    }

    /**
     * 用户列表
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function user_list(Request $request)
    {
        $list = \app\backend\model\BackendUsers::order(['id' => 'desc'])->paginate(null, null, ["query" => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        $this->indexBeforeFetch();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 用户添加
     * @param Request $request
     * @return array|mixed
     */
    public function user_add(Request $request)
    {
        $model = new \app\backend\model\BackendUsers();
        if ($request->isPost()) {
            $form = $request->param('form/a');
            $form['status'] = isset($form['status']) ? intval($form['status']) : 0;
            $form['type'] = isset($form['type']) ? intval($form['type']) : 1;
            if (!empty($form['password'])) $form['password'] = md5($form['password']);
            $form['last_login_time'] = $form['created_at'] = time();
            $form['menus'] = '';
            $result = $model->allowField('username,password,status,last_login_time,created_at,type,menus,email,remarks')->validate('BackendUsers.add')->save($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }
        return $this->fetch(null);
    }

    /**
     * 用户修改
     * @param Request $request
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function user_edit(Request $request)
    {
        $model = new \app\backend\model\BackendUsers();
        if ($request->isPost()) {
            $id = $request->param('id', 0, 'intval');
            $form = $request->param('form/a');
            $info = $model->where(['id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);

            $form['status'] = isset($form['status']) ? intval($form['status']) : 0;
            if (empty($form['password'])) {
                unset($form['password']);
            } else {
                $form['password'] = md5($form['password']);
            }
            $result = $model->allowField('password,status,email,remarks')->validate('BackendUsers.edit')->save($form, ['id' => $info['id']]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $id = $request->param('id', 0, 'intval');
        $info = $model->where(['id' => $id])->find();
        if (empty($info)) $this->error('该记录不存在');
        return $this->fetch(null, compact('info'));
    }

    /**
     * 用户授权
     * @param Request $request
     * @return array|mixed
     */
    public function user_auth(Request $request)
    {
        if ($request->isPost()) {
            $id = $request->param('id', 0, 'intval');
            $auths = $request->param('auth/a');
            $auths_txt = "";
            foreach ($auths as $key => $auth) {
                foreach ($auth as &$a) {
                    $a = trim($a);
                }
                if (is_array($auth)) $auths_txt .= $key . ":" . implode(',', $auth) . "\n";
            }
            $flag = \app\backend\model\BackendUsers::where(['id' => $id])->setField('menus', $auths_txt);
            if ($flag === false) {
                return $this->successJson(ERROR1, "操作失败", null);
            }
            cache('admin_menus_' . $id, null);
            \app\backend\model\BackendMenu::getMenus($id);
            return $this->successJson(SUCCESS, "操作成功", null);
        }

        $id = intval(input('id'));
        $menus = \app\backend\model\BackendMenu::getAllMenus();
        $admin_menus = \app\backend\model\BackendMenu::getAdminMenu($id);
        return $this->fetch(null, ['menus' => $menus, 'admin_menus' => $admin_menus['users_menus'], 'id' => $id]);
    }
}