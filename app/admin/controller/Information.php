<?php

namespace app\admin\controller;

use app\common\model\Information as InformationModel;
use app\common\model\InformationCategory;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Request;

/**
 * Class Information
 * @package app\admin\controller
 */
class Information extends Admin
{

    /**
     * @var InformationModel
     */
    protected $information;

    /**
     * @var InformationCategory
     */
    protected $category;

    /**
     * Information constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->information = new InformationModel();
        $this->category = new InformationCategory();
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws DbException
     */
    public function index(Request $request)
    {
        $where = [];
        $categoryId = $request->get('category_id');
        $title = $request->get('keywords');
        if ($categoryId) {
            $where['category_id'] = $categoryId;
        }

        if ($title) {
            $where['title'] = ['like', "%{$title}%"];
        }


        $list = $this->information->with('category')->where($where)->order('id desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $categories = $this->category->field('id, name')->select();

        return $this->fetch(null, compact('list', 'page', 'categories'));
    }

    /**
     * @param Request $request
     * @return bool|mixed
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    public function add(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();
            if ($_FILES['art_pic']['size'] > 0) {
                $upload = $this->oss_upload($file = [], $path = 'article_pics');
                if (empty($upload)) {
                    $this->error('图片上传失败');
                    return false;
                }
                $data['pic'] = trim($upload['art_pic']);
            }
            $data['create_time'] = time();
            // 入库
            $id = $this->information->insertGetId($data);
            if ($id === false) {
                $this->error("系统错误, 请稍后再试");
            }

            $this->success("添加成功!");
        }
        $categories = $this->category->field('id, name')->select();
        return $this->fetch(null, compact('categories'));
    }

    /**
     * @param Request $request
     * @return bool|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function edit(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();
            if ($_FILES['art_pic']['size'] > 0) {
                $upload = $this->oss_upload($file = [], $path = 'article_pics');
                if (empty($upload)) {
                    $this->error('图片上传失败');
                    return false;
                }
                $data['pic'] = trim($upload['art_pic']);
            }

            $res = $this->information->update($data);
            if ($res === false) {
                $this->error("系统错误请稍后再试!");
                return false;
            }
            $this->success("修改成功");
        }
        $id = $request->param('id');
        $data = $this->information->where('id', $id)->find();
        $categories = $this->category->field('id, name')->select();

        return $this->fetch(null, compact('data', 'categories'));
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function delete(Request $request)
    {
        $id = $request->param('id');
        $res = $this->information->where('id', $id)->delete();
        if ($res == false) {
            $this->error("系统错误, 删除失败!");
            return false;
        }
        $this->success("删除成功!");
        return true;
    }
}