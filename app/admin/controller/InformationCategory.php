<?php


namespace app\admin\controller;


use app\common\model\Information as InformationModel;
use app\common\model\InformationCategory as InformationCategoryModel;
use think\exception\DbException;
use think\Request;

/**
 * Class InformationCategory
 * @package app\admin\controller
 */
class InformationCategory extends Admin
{

    /**
     * @var InformationCategoryModel
     */
    protected $category;

    /**
     * InformationCategory constructor.
     * @param Request|null $request
     */
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->category = new InformationCategoryModel();
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws DbException
     */
    public function index(Request $request)
    {
        $list = $this->category->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        foreach ($list as &$value) {
            $value['banner'] = json_decode($value['banner']);
        }
        return $this->fetch(null, compact('list','page'));
    }


    /**
     * @param Request $request
     * @return mixed|void
     */
    public function add(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();
            if ($_FILES['art_pic']['size'] > 0) {
                $upload = $this->multiple_upload($file = [], $path = 'article_pics');
                if (empty($upload)) {
                    $this->error('图片上传失败');
                    return false;
                }
                $data['banner'] = json_encode($upload);
            }
            $id = $this->category->insertGetId($data);
            if (false == $id) {
                $this->error("系统错误, 添加失败!");
            }
            $this->success("添加成功");
        }

        return $this->fetch();
    }

    public function edit(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();
            if ($_FILES['art_pic']['size'] > 0) {
                $upload = $this->multiple_upload($file = [], $path = 'article_pics');
                if (empty($upload)) {
                    $this->error('图片上传失败');
                    return false;
                }
                $data['banner'] = json_encode($upload);
            }
            $res = $this->category->update($data);
            if (false === $res) {
                $this->error("系统错误, 修改失败!");
            }
            $this->success("修改成功!");
        }
        $id = $request->param('id');
        $data = $this->category->where('id', $id)->find();
        return $this->fetch(null, compact('data'));
    }

    public function delete(Request $request)
    {
        $id = $request->param('id');
        $information = new InformationModel();
        $flag = $information->where('category_id', $id)->find();
        if (!empty($flag)) {
            $this->error('该分类下还有资讯不能删除!');
        }
        $res = $this->category->where('id', $id)->delete();
        if (!$res) {
            $this->error("系统错误删除失败!");
        }

        $this->success("删除成功!");
    }
}