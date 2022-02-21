<?php

namespace app\backend\controller;

use app\backend\model\BackendInformation;
use app\common\model\Reads;
use think\Exception;
use think\Request;

class Article extends AdminQuick
{
    protected $allow_delete = true;

    /**
     * 文章列表
     * @param Request $request
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function article_list(Request $request)
    {
        $where = [];
        $catId = $request->get('cat_id');
        if ($catId) $where['position_id'] = $catId;

        $list = \app\backend\model\Article::with(['category'])->where($where)->order(['article_id' => 'desc'])->paginate(null, null, ["query" => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        $categories = \app\common\model\ArticleCategory::where('status', 1)->select();
        return $this->fetch(null, compact('list', 'page', 'count', 'categories'));
    }

    /**
     * 文章添加
     * @param Request $request
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function article_add(Request $request)
    {
        if ($request->isPost()) {
            $form = $request->param('form/a');
            $form['add_time'] = time();
            $form['title'] = $form['en_title'] = $form['tc_title'];
            $form['content'] = $form['en_content'] = $form['tc_content'];
            $result = \app\backend\model\Article::insert($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }
        $categories = \app\common\model\ArticleCategory::where('status', 1)->select();
        return $this->fetch(null, compact('categories'));
    }

    /**
     * 文章修改
     * @param Request $request
     * @return array|mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function article_edit(Request $request)
    {
        if ($this->request->isPost()) {
            $id = $request->param('id', 0, 'intval');
            $form = $request->param('form/a');
            $info = \app\backend\model\Article::where(['article_id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);

            $form['title'] = $form['en_title'] = $form['tc_title'];
            $form['content'] = $form['en_content'] = $form['tc_content'];
            $result = \app\backend\model\Article::update($form, ['article_id' => $info['article_id']]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $id = $request->param('id', 0, 'intval');
        $info = \app\backend\model\Article::where(['article_id' => $id])->find();
        if (empty($info)) return $this->error("该记录不存在");

        $categories = \app\common\model\ArticleCategory::where('status', 1)->select();
        return $this->fetch(null, compact('info', 'categories'));
    }

    /**
     * 文章删除
     * @param Request $request
     * @return array
     */
    public function article_del(Request $request)
    {
        $id = $request->param('id', 0, 'intval');
        if (!$this->allow_delete) return $this->successJson(ERROR1, "特殊配置,不支持快捷删除", null);

        try {
            $info = \app\backend\model\Article::where(['article_id' => $id])->find();
            if (!$info) throw new \think\Exception("记录不存在,不能执行该操作");

            $flag = \app\backend\model\Article::where(['article_id' => $id])->delete();
            if ($flag === false) throw new \think\Exception("操作失败");

            return $this->successJson(SUCCESS, "操作成功", null);
        } catch (\think\Exception $e) {
            return $this->successJson(ERROR4, $e->getMessage(), null);
        }
    }

    /**
     * 文章分类列表
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function article_cat_list(Request $request)
    {
        $list = \app\common\model\ArticleCategory::order(['id' => 'desc'])->paginate(null, null, ["query" => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 文章分类添加
     * @param Request $request
     * @return array|mixed
     * @throws \think\exception\DbException
     */
    public function article_cat_add(Request $request)
    {
        if ($request->isPost()) {
            $form = $request->param('form/a');
            $form['name'] = $form['name_en'] = $form['name_tc'];
            $form['parent_id'] = intval($form['parent_id']);
            $result = \app\common\model\ArticleCategory::insert($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $categories = \app\common\model\ArticleCategory::all();
        return $this->fetch(null, compact('categories'));
    }

    /**
     * 文章分类修改
     * @param Request $request
     * @return array|mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function article_cat_edit(Request $request)
    {
        if ($request->isPost()) {
            $id = $request->param('id', 0, 'intval');
            $form = $request->param('form/a');
            $info = \app\common\model\ArticleCategory::where(['id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);

            $form['name'] = $form['name_en'] = $form['name_tc'];
            $form['parent_id'] = intval($form['parent_id']);
            $result = \app\common\model\ArticleCategory::update($form, ['id' => $info['id']]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $id = $request->param('id', 0, 'intval');
        $info = \app\common\model\ArticleCategory::where(['id' => $id])->find();
        if (empty($info)) return $this->error("该记录不存在");

        $categories = \app\common\model\ArticleCategory::all();
        return $this->fetch(null, compact('info', 'categories'));
    }

    /**
     * 文章分类删除
     * @param Request $request
     * @return array
     */
    public function article_cat_del(Request $request)
    {
        $id = $request->param('id', 0, 'intval');
        if (!$this->allow_delete) return $this->successJson(ERROR1, "特殊配置,不支持快捷删除", null);

        try {
            $info = \app\common\model\ArticleCategory::where(['id' => $id])->find();
            if (!$info) throw new \think\Exception("记录不存在,不能执行该操作");

            $flag = \app\common\model\ArticleCategory::where(['id' => $id])->delete();
            if ($flag === false) throw new \think\Exception("操作失败");

            return $this->successJson(SUCCESS, "操作成功", null);
        } catch (\think\Exception $e) {
            return $this->successJson(ERROR4, $e->getMessage(), null);
        }
    }

    /**
     * 发现分类
     * @var string[]
     */
    protected $reads_type = [
        '1' => '资讯',
        '2' => '多图',
    ];

    /**
     * 发现列表
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function reads_list(Request $request)
    {
        $where['status'] = 1;
        $title = $request->get('title');
        if ($title) $where['title'] = ['like', "%{$title}%"];
        $type = $request->get('cat_id', 0);
        if ($type > 0) $where['type'] = $type;
        $list = Reads::where($where)->order(['article_id' => 'desc'])->paginate(null, null, ["query" => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        $reads_type = $this->reads_type;
        return $this->fetch(null, compact('list', 'page', 'count', 'reads_type'));
    }

    /**
     * 发现添加
     * @param Request $request
     * @return array|mixed
     */
    public function reads_add(Request $request)
    {
        if ($request->isPost()) {
            $form = $request->param('form/a');
            $form['add_time'] = time();
            $result = Reads::create($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败", null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }
        $this->assign('reads_type',$this->reads_type);
        return $this->fetch();
    }

    /**
     * 资讯编辑
     * @param Request $request
     * @return array|mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function reads_edit(Request $request)
    {
        if ($request->isPost()) {
            $id = $request->param('id', 0, 'intval');
            $info = Reads::where(['article_id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);
            $form = $request->param('form/a');
            $result = Reads::update($form, ['article_id' => $id]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败", null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $id = $request->param('id', 0, 'intval');
        $info = Reads::where(['article_id' => $id])->find();
        if (empty($info)) return $this->error("该记录不存在");

        $reads_type = $this->reads_type;
        return $this->fetch(null, compact('info','reads_type'));
    }

    /**
     * 资讯删除
     * @param Request $request
     * @return array
     */
    public function reads_del(Request $request)
    {
        try {
            $id = $request->param('id', 0, 'intval');
            $info = Reads::where(['article_id' => $id])->find();
            if (!$info) throw new Exception("记录不存在,不能执行该操作");

            $flag = Reads::where(['article_id' => $id])->delete();
            if ($flag === false) throw new Exception("操作失败");

            return $this->successJson(SUCCESS, "操作成功", null);
        } catch (Exception $e) {
            return $this->successJson(ERROR4, $e->getMessage(), null);
        }
    }

    /**
     * 资讯列表
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function info_list(Request $request)
    {
        $where['status'] = 1;
        $title = $request->get('title');
        if ($title) $where['title'] = ['like', "%{$title}%"];

        $list = \app\backend\model\BackendInformation::where($where)->order(['id' => 'desc'])->paginate(null, null, ["query" => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 资讯添加
     * @param Request $request
     * @return array|mixed
     */
    public function info_add(Request $request)
    {
        if ($request->isPost()) {
            $file_infos = $request->post('pic/a');
            if (count($file_infos) > 9) return $this->successJson(ERROR1, "美粉圈图片不能超过九张", null);

            $form['title'] = $request->post('title');
            $form['content'] = $request->post('content');
            $form['file_infos'] = json_encode($file_infos);
            $form['sort'] = $request->post('sort');
            $form['status'] = $request->post('status');
            $form['add_time'] = time();
            $result = BackendInformation::insertGetId($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败", null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }
        return $this->fetch();
    }

    /**
     * 资讯编辑
     * @param Request $request
     * @return array|mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function info_edit(Request $request)
    {
        if ($request->isPost()) {
            $id = $request->param('id', 0, 'intval');
            $info = BackendInformation::where(['id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);
            $file_infos = $request->post('pic/a');
            if (count($file_infos) > 9) return $this->successJson(ERROR1, "美粉圈图片不能超过九张", null);

            $form = [];
            $form['title'] = $request->post('title');
            $form['content'] = $request->post('content');
            $form['file_infos'] = json_encode($file_infos);
            $form['sort'] = $request->post('sort');
            $form['status'] = $request->post('status');
            $result = BackendInformation::where(['id' => $id])->update($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败", null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $id = $request->param('id', 0, 'intval');
        $info = BackendInformation::where(['id' => $id])->find();
        if (empty($info)) return $this->error("该记录不存在");
        $info['file_infos'] = json_decode($info['file_infos'], true);
        return $this->fetch(null, compact('info'));
    }

    /**
     * 资讯删除
     * @param Request $request
     * @return array
     */
    public function info_del(Request $request)
    {
        try {
            $id = $request->param('id', 0, 'intval');
            $info = BackendInformation::where(['id' => $id])->find();
            if (!$info) throw new Exception("记录不存在,不能执行该操作");

            $flag = BackendInformation::where(['id' => $id])->delete();
            if ($flag === false) throw new Exception("操作失败");

            return $this->successJson(SUCCESS, "操作成功", null);
        } catch (Exception $e) {
            return $this->successJson(ERROR4, $e->getMessage(), null);
        }
    }
}
