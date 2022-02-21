<?php

namespace app\api\controller;

use app\common\model\Information as InformationModel;
use app\common\model\InformationCategory;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Request;

/**
 * Class Information
 * @package app\api\controller
 */
class Information extends Base
{
    protected $public_action = ['index', 'data', 'detail'];

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
     * @param Request|null $request
     */
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->information = new InformationModel();
        $this->category = new InformationCategory();
    }

    /**
     * 资讯首页数据
     * @param Request $request
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function index(Request $request)
    {
        $categories = $this->category->select();
        foreach ($categories as &$item) {
            if ($item['banner']) {
                $item['banner'] = json_decode($item['banner']);
            } else {
                $item['banner'] = null;
            }
        }
        return $this->output_new(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $categories]);
    }

    /**
     * 获取资讯列表
     * @param Request $request
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function data(Request $request)
    {
        $categoryId = $request->post('category_id', 'all');
        $page = $request->post('page', 1);
        $rows = $request->post('rows', 10);
        $where = [];
        if ('all' != $categoryId and $categoryId > 0) {
            $where = ['category_id' => $categoryId];
        }

        if ('en-us' == $this->lang) {
            // FROM_UNIXTIME(time,"%Y-%m-%d %H:%m:%s") as time
            $field = 'id, category_id, en_title as title, pic, FROM_UNIXTIME(create_time, "%Y-%m-%d %H:%m:%s") as create_time';
        } else {
            $field = 'id, category_id, title, pic, FROM_UNIXTIME(create_time, "%Y-%m-%d %H:%m:%s") as create_time';
        }
        $information = $this->information->with('category')->where('status', 1)->where($where)->field($field)->page($page, $rows)->select();
        if (empty($information)) {
            return $this->output_new(['code' => ERROR1, 'message' => lang('lan_No_data')]);
        }
        return $this->output_new(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $information]);
    }

    /**
     * 资讯详情
     * @param Request $request
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function detail(Request $request)
    {
        $id = $request->post('id');
        if (empty($id)) {
            return $this->output_new(['code' => ERROR1, 'message' => lang('lan_modifymember_parameter_error'), 'result' => null]);
        }
        if ('en-us' == $this->lang) {
            $field = 'id, category_id, en_title as title, en_content as content, pic, FROM_UNIXTIME(create_time, "%Y-%m-%d %H:%m:%s") as create_time';
        } else {
            $field = 'id, category_id, title, content, pic, FROM_UNIXTIME(create_time, "%Y-%m-%d %H:%m:%s") as create_time';
        }

        $data = $this->information->with('category')->where('id', $id)->where('status', 1)->field($field)->find();
        return $this->output_new(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $data]);
    }
}