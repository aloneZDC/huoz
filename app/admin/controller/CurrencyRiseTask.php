<?php


namespace app\admin\controller;

use app\common\model\Currency;
use think\Request;

/**
 * Class ArticleCategory
 * @package app\admin\controller
 */
class CurrencyRiseTask extends Admin
{
    /**
     * 列表
     * @param Request $request
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function index(Request $request)
    {
        $list = \app\admin\model\CurrencyRiseTask::order('id', 'desc')->with('currency')->paginate(null, null, $request->get());
        $page = $list->render();
        $count = \app\admin\model\CurrencyRiseTask::count('id');
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * @param Request $request
     * @return mixed|\think\response\Json
     */
    public function add(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();

            $data['currency_id'] = intval($data['currency_id']);
            $currency = Currency::where(['currency_id'=>$data['currency_id']])->find();
            if(empty($currency)) {
                return successJson(ERROR1,"币种不存在");
            }

            $result = \app\admin\model\CurrencyRiseTask::create($data);

            if (!$result) {
                return successJson(ERROR1,"系统错误, 添加失败!");
            }
            return successJson(SUCCESS,"添加成功");
        } else {
            $currency_list = Currency::select();
            $this->assign('currency_list',$currency_list);
        }

        return $this->fetch();
    }

    /**
     * 编辑分类
     * @param Request $request
     * @return mixed|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();

            $data['currency_id'] = intval($data['currency_id']);
            $currency = Currency::where(['currency_id'=>$data['currency_id']])->find();
            if(empty($currency)) {
                return successJson(ERROR1,"币种不存在");
            }

            $result = \app\admin\model\CurrencyRiseTask::update($data);
            if ($result === false) {
                return successJson(ERROR1,"修改失败");
            }
            return successJson(SUCCESS,"修改成功");
        } else {
            $currency_list = Currency::select();
        }

        $id = $request->get('id');
        $info = \app\admin\model\CurrencyRiseTask::where('id', $id)->find();
        return $this->fetch(null, compact('currency_list','info'));
    }

    /**
     * 删除分类
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function delete(Request $request)
    {
        $id = $request->post('id');

        $result = \app\admin\model\CurrencyRiseTask::where('id', $id)->delete();
        if (!$result) {
            return successJson("系统错误, 删除失败!", null, ERROR1);
        }

        return successJson("删除成功");
    }
}
