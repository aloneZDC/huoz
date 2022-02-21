<?php

namespace app\backend\controller;

use app\common\model\OrchardConfig;
use app\common\model\OrchardDetails;
use app\common\model\OrchardLevel;
use app\common\model\OrchardOrders;
use app\common\model\OrchardService;
use app\common\model\OrchardSowing;
use app\common\model\OrchardTree;
use app\common\model\OrchardUser;
use think\Db;

class Orchard extends AdminQuick
{
    /**
     *  配置列表
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function config_list()
    {
        $list = OrchardConfig::where('desc', '<>', '')->order('id asc')->select();
        return $this->fetch(null, compact('list'));
    }

    /**
     * 修改配置
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function config_update()
    {
        $allow_field = ['value'];
        $id = intval(input('id'));
        $info = OrchardConfig::where(['id' => $id])->find();
        if (empty($info)) return ['code' => ERROR1, 'message' => '配置不存在'];

        $filed = input('field');
        if (empty($filed) || !in_array($filed, $allow_field)) return ['code' => ERROR1, 'message' => '不允许修改'];

        $value = input('value');
        $data = [$filed => $value];
        $flag = OrchardConfig::where(['id' => $info['id']])->update($data);
        if ($flag === false) {
            return ['code' => ERROR1, 'message' => '修改失败'];
        }
        return ['code' => SUCCESS, 'message' => '修改成功'];
    }

    /**
     * 农庄晋级
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function grade_list()
    {
        $where = [];
        $list = OrchardLevel::where($where)
            ->field(['id', 'level', 'title', 'worker', 'active_max', 'active_min', 'expend', 'breed', 'pid', 'reward', 'status'])
            ->order(['sort' => 'asc', 'id' => 'asc'])->paginate(null, null, ["query" => $this->request->get()]);
        $titleTree = OrchardLevel::column('title', 'id');
        foreach ($list as &$value) {
            if ($value['pid'] > 0) {
                $value['pid'] = $titleTree[$value['pid']];
            }
        }
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 修改晋级
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function grade_update()
    {
        if ($this->request->isPost()) {
            $saveData = $this->request->only(['id', 'form']);
            $isUpdate = OrchardLevel::where('id', $saveData['id'])->update($saveData['form']);
            if ($isUpdate) {
                return ['code' => SUCCESS, 'message' => '修改成功'];
            }
            return ['code' => ERROR1, 'message' => '修改失败'];
        }

        $id = $this->request->param('id', 0);
        $info = OrchardLevel::where(['id' => $id])->find();
        $currency = \app\common\model\Currency::where(['is_line' => 1])->field(['currency_id', 'currency_name'])->select();
        $titleTree = OrchardLevel::field(['title', 'id'])->select();
        return $this->fetch(null, compact('info', 'currency', 'titleTree'));
    }

    /**
     * 果园用户
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function user_list()
    {
        $where = [];
        $member_id = $this->request->param('member_id', 0);
        if ($member_id) $where['t1.member_id'] = $member_id;

        $list = OrchardUser::alias('t1')->where($where)
            ->join(config("database.prefix") . "member t2", ['t1.member_id = t2.member_id'], "LEFT")
            ->join(config("database.prefix") . "orchard_level t3", ['t1.level = t3.id'], "LEFT")
            ->field(['t1.*', 't2.ename', 't3.title'])
            ->order($this->pid . " desc")->paginate(null, null, ["query" => $this->request->get()]);

        foreach ($list as $value) {
            $value['direct_push'] = OrchardUser::direct_push($value);
            $value['nurture'] = OrchardUser::nurture($value['member_id'], $value['level']);
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 果树列表
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function tree_list()
    {
        $list = OrchardTree::where(null)->order($this->pid . " asc")->paginate(null, null, ["query" => $this->request->get()]);

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 修改果树
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function tree_update()
    {
        if ($this->request->isPost()) {
            $saveData = $this->request->only(['id', 'form']);
            $isUpdate = OrchardTree::where('id', $saveData['id'])->update($saveData['form']);
            if ($isUpdate) {
                return ['code' => SUCCESS, 'message' => '修改成功'];
            }
            return ['code' => ERROR1, 'message' => '修改失败'];
        }

        $id = $this->request->param('id', 0);
        $info = OrchardTree::where(['id' => $id])->find();
        return $this->fetch(null, compact('info'));
    }

    /**
     * 果树订单
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function order_list()
    {
        $where = [];
        $member_id = $this->request->param('member_id', 0);
        if ($member_id) $where['t1.member_id'] = $member_id;

        $list = OrchardOrders::alias('t1')
            ->join(config("database.prefix") . "member t2", "t1.member_id=t2.member_id", "LEFT")
            ->join(config("database.prefix") . "orchard_tree t3", "t1.tree_id=t3.id", "LEFT")
            ->field(['t1.*', 't2.ename', 't3.title'])
            ->where($where)->order($this->pid . " desc")->paginate(null, null, ["query" => $this->request->get()]);

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 果树播种
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function sowing_list()
    {
        $where = [];
        $member_id = $this->request->param('member_id', 0);
        if ($member_id) $where['t1.member_id'] = $member_id;

        $list = OrchardSowing::alias('t1')
            ->join(config("database.prefix") . "member t2", "t1.member_id=t2.member_id", "LEFT")
            ->join(config("database.prefix") . "orchard_tree t3", "t1.tree_id=t3.id", "LEFT")
            ->field(['t1.*', 't2.ename', 't3.title'])
            ->where($where)->order($this->pid . " desc")->paginate(null, null, ["query" => $this->request->get()]);

        foreach ($list as &$value) {
            $value['fert_time'] = date('Y-m-d H:i:s', $value['fert_time']);
            $value['status_name'] = OrchardSowing::STATUS_ZH_CN_MAP[$value['status']];
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 果树收获
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function reward_list()
    {

        $where['t1.status'] = OrchardSowing::STATUS_ALREADY;
        $id = $this->request->param('id', 0);
        if ($id) $where['t1.id'] = $id;
        $member_id = $this->request->param('member_id', 0);
        if ($member_id) $where['t1.member_id'] = $member_id;

        $list = OrchardSowing::alias('t1')
            ->join(config("database.prefix") . "member t2", "t1.member_id=t2.member_id", "LEFT")
            ->join(config("database.prefix") . "orchard_tree t3", "t1.tree_id=t3.id", "LEFT")
            ->field(['t1.*', 't2.ename', 't3.title'])
            ->where($where)->order($this->pid . " desc")->paginate(null, null, ["query" => $this->request->get()]);

        foreach ($list as &$value) {
            $value['fruit_time'] = date('Y-m-d H:i:s', $value['fruit_time']);
            // $value['seed_num'] = bcadd($value['seed_num'],$value['exchange_num'],6);
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 推荐奖
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function recom_list()
    {
        $where['t1.type'] = 6303;
        $member_id = $this->request->param('member_id', 0);
        if ($member_id) $where['t1.member_id'] = $member_id;

        $list = Db::name('accountbook')->alias('t1')->where($where)
            ->join([config("database.prefix") . 'currency' => 't2'], ['t1.currency_id = t2.currency_id'], 'left')
            ->join([config("database.prefix") . 'goods_main_orders' => 't3'], ['t1.third_id = t3.gmo_id'], 'left')
            ->join([config("database.prefix") . 'member' => 't4'], ['t1.member_id = t4.member_id'], 'left')
            ->join([config("database.prefix") . 'member' => 't5'], ['t3.gmo_user_id = t5.member_id'], 'left')
            ->field(['t1.member_id', 't4.ename', 't1.number', 't1.add_time', 't2.currency_name', 't3.gmo_user_id' => 'child_id', 't5.ename' => 'child_name'])
            ->order('t1.add_time', 'desc')
            ->paginate(null, null, ["query" => $this->request->get()]);

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 服务奖
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function service_list()
    {
        $where = [];
        $member_id = $this->request->param('member_id', 0);
        if ($member_id) $where['t1.member_id'] = $member_id;

        $child_id = $this->request->param('child_id', 0);
        if ($child_id) $where['t1.child_id'] = $child_id;

        $list = OrchardService::alias('t1')->where($where)
            ->join([config("database.prefix") . 'orchard_orders' => 't2'], ['t1.order_id = t2.id'], 'left')
            ->join([config("database.prefix") . 'orchard_tree' => 't3'], ['t2.tree_id = t3.id'])
            ->join([config("database.prefix") . 'member' => 't4'], ['t1.member_id = t4.member_id'], 'left')
            ->join([config("database.prefix") . 'member' => 't5'], ['t1.child_id = t5.member_id'], 'left')
            ->field(['t1.member_id', 't4.ename', 't1.child_id', 't5.ename' => 'child_name', 't3.title',
                't1.total_price', 't1.bind_level', 't1.ratio', 't1.rewards', 't1.seed_num', 't1.pulp_num', 't1.add_time',])
            ->order('t1.add_time', 'desc')
            ->paginate(null, null, ["query" => $this->request->get()]);

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 管理奖
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function manage_list()
    {
        $where = [];
        $member_id = $this->request->param('member_id', 0);
        if ($member_id) $where['t1.member_id'] = $member_id;

        $child_id = $this->request->param('child_id', 0);
        if ($child_id) $where['t1.child_id'] = $child_id;

        $list = OrchardDetails::alias('t1')->where($where)
            ->join([config("database.prefix") . 'orchard_orders' => 't2'], ['t1.order_id = t2.id'], 'left')
            ->join([config("database.prefix") . 'orchard_tree' => 't3'], ['t2.tree_id = t3.id'])
            ->join([config("database.prefix") . 'member' => 't4'], ['t1.member_id = t4.member_id'], 'left')
            ->join([config("database.prefix") . 'member' => 't5'], ['t1.child_id = t5.member_id'], 'left')
            ->join([config("database.prefix") . 'orchard_level' => 't6'], ['t1.level = t6.id'], 'left')
            ->field(['t1.member_id', 't4.ename', 't6.title' => 'level_title', 't1.child_id', 't5.ename' => 'child_name', 't3.title',
                't1.total_price', 't1.bind_level', 't1.ratio', 't1.rewards', 't1.seed_num', 't1.pulp_num', 't1.add_time'])
            ->order('t1.add_time', 'desc')
            ->paginate(null, null, ["query" => $this->request->get()]);

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }
}