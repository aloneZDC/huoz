<?php

namespace app\backend\controller;

use think\Db;
use think\Exception;
use think\Request;
use think\exception\DbException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use app\common\model\Config;
use app\common\model\ArkGoods;
use app\common\model\ArkGoodsList;
use app\common\model\ArkOrder;
use app\common\model\ShopPayType;

class Arkgame extends AdminQuick
{
    protected $pid = 'id';
    protected $allow_switch_field = ['status'];
    protected $public_action = ['getarea', 'pickedup_add', 'pickedup_edit', 'refresh_token', 'goods_edit', 'goods_add', 'quick_switch'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('ark_goods');
    }

    /**
     * 主商品列表
     * @param Request $request
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index(Request $request)
    {
        $where = [];
        $title = $this->request->param('name');
        if ($title) {
            $where['g.name'] = ['like', "%{$title}%"];
        }
        $currency_id = $this->request->param('currency_id');
        if (!empty($currency_id)) {
            $where['g.currency_id'] = $currency_id;
        }

        $arkGoods = new ArkGoods();
        $list = $arkGoods->alias("g")
            ->join(config("database.prefix") . "currency c", "g.currency_id=c.currency_id", "LEFT")
            ->field(['g.*', 'c.currency_name'])
            ->where($where)->order("g.id desc,g.status asc")->paginate(null, null, ["query" => $this->request->get()]);
        $page = $list->render();
        $count = $list->total();

        // 币种
        $currency = Db::name('currency')->where('is_line', 1)->field(['currency_id', 'currency_name'])->order('currency_id', 'asc')->select();
        return $this->fetch(null, compact('list', 'page', 'count', 'currency'));
    }

    /**
     * 添加商品
     * @param Request $request
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function add(Request $request)
    {
        if ($this->request->isPost()) {
            $form = input('form/a');
            $form['start_time'] = strtotime($form['start_time']);
            if ($form['start_time'] == strtotime(date('Y-m-d'))) {
                return $this->successJson(ERROR1, "操作失败：请选择大于今天的日期", null);
            }
            $form['end_time'] = strtotime($form['end_time']);
            if ($form['end_time'] == strtotime(date('Y-m-d'))) {
                return $this->successJson(ERROR1, "操作失败：请选择大于今天的日期", null);
            }
            $form['add_time'] = time();
            $result = model('ark_goods')->insertGetId($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            } else {
                \app\common\model\ArkGoodsList::add_goods($result);//创建子商品
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        } else {
            // 币种
            $currency = Db::name('currency')->where('is_line', 1)->field(['currency_id', 'currency_name'])->order('currency_id', 'asc')->select();
            return $this->fetch(null, compact('currency'));
        }
    }

    /**
     * 修改商品
     * @param Request $request
     * @return array|mixed|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function edit(Request $request)
    {
        if ($this->request->isPost()) {
            $form = input('form/a');
            $id = intval($form['id']);
            unset($form['id']);
            $info = model('ark_goods')->where(['id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);

            $form['start_time'] = strtotime($form['start_time']);
            if ($form['start_time'] == strtotime(date('Y-m-d'))) {
                return $this->successJson(ERROR1, "操作失败：请选择大于今天的日期", null);
            }
            $form['end_time'] = strtotime($form['end_time']);
            if ($form['end_time'] == strtotime(date('Y-m-d'))) {
                return $this->successJson(ERROR1, "操作失败：请选择大于今天的日期", null);
            }

            $result = model('ark_goods')->save($form, ['id' => $info['id']]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        } else {
            $id = intval(input('id'));
            $info = model('ark_goods')->where(['id' => $id])->find();
            if (empty($info)) $this->error('该记录不存在');

            // 币种
            $currency = Db::name('currency')->where('is_line', 1)->field(['currency_id', 'currency_name'])->order('currency_id', 'asc')->select();
            return $this->fetch(null, compact('info', 'currency'));
        }
    }

    /**
     * 子商品列表
     * @return mixed
     */
    public function goods_list()
    {
        $where = [];
        $title = $this->request->param('name');
        if ($title) {
            $where['g.name'] = ['like', "%{$title}%"];
        }
        $currency_id = $this->request->param('currency_id');
        if (!empty($currency_id)) {
            $where['g.currency_id'] = $currency_id;
        }
        $goods_id = $this->request->param('goods_id');
        if (!empty($goods_id)) {
            $where['g.goods_id'] = $goods_id;
        }

        $RocketGoods = new ArkGoodsList();
        $list = $RocketGoods->alias("g")
            ->join(config("database.prefix") . "currency c", "g.currency_id=c.currency_id", "LEFT")
            ->field(['g.*', 'c.currency_name'])
            ->where($where)->order("g.id desc,g.status asc")->paginate(null, null, ["query" => $this->request->get()]);
        $page = $list->render();
        $count = $list->total();

        // 币种
        $currency = Db::name('currency')->where('is_line', 1)->field(['currency_id', 'currency_name'])->order('currency_id', 'asc')->select();
        // 主商品
        $goods = Db::name('ark_goods')->where('status', 1)->field(['id', 'name'])->order('id', 'asc')->select();
        return $this->fetch(null, compact('list', 'page', 'count', 'currency', 'goods'));
    }

    /**
     * 商品订单
     * @return mixed
     * @throws DbException
     */
    public function order_list()
    {
        $order_code = $this->request->param('order_code');
        $where = [];
        if ($order_code) {
            $where['a.order_code'] = ['like', "%{$order_code}%"];
        }
        $gmo_status = $this->request->param('status');
        if (!empty($gmo_status)) {
            $where['a.status'] = $gmo_status;
        }
        $member_id = $this->request->param('member_id');
        if ($member_id) {
            $where['a.member_id'] = $member_id;
        }

        $list = ArkOrder::alias('a')->where($where)
            ->field("a.*,b.name,c.currency_name,d.phone,d.email")
            ->join(config('database.prefix') . 'ark_goods_list b', 'a.goods_list_id = b.id', 'LEFT')
            ->join(config('database.prefix') . 'currency c', 'b.currency_id = c.currency_id', 'LEFT')
            ->join(config('database.prefix') . 'member d', 'a.member_id = d.member_id', 'LEFT')
            ->order('a.id', 'desc')->paginate(null, null, ["query" => $this->request->get()]);
        if ($list) {
            foreach ($list as &$value) {
                $share_reward = Db::name('ark_reward_log')->where(['member_id' => $value['member_id'], 'type' => 2, 'third_id' => $value['id']])->sum('reward');
                $value['share_reward'] = $share_reward;//分享收益
                $team_reward = Db::name('ark_reward_log')->where(['member_id' => $value['member_id'], 'type' => 3, 'third_id' => $value['id']])->sum('reward');
                $value['team_reward'] = $team_reward;//团队收益
            }
        }
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    //获取城市
    public function getArea(Request $request)
    {
        $pid = $request->post('pid');
        $list = Db::name('areas')->where(['parent_id' => $pid])->select();
        return $this->successJson(SUCCESS, "获取成功", $list);
    }

    protected $flashType = [
//        0 => "首页幻灯片",
        2 => "手机Banner",
//        3 => "手机 APP Banner",
//        6 => "游戏Banner",
//        7 => "算能首页幻灯片",
//        8 => "商城幻灯片",
//        9 => '线下商城幻灯片',
//        10 => '矿池幻灯片',
//        11 => '发现幻灯片',
    ];

    /**
     * 轮播图列表
     * @param Request $request
     * @return mixed
     * @throws DbException
     */
    public function flash_list(Request $request)
    {
        $where['status'] = 1;
        $title = $request->get('title');
        if ($title) $where['title'] = ['like', "%{$title}%"];

        $list = \app\common\model\Flash::where($where)->order(['flash_id' => 'desc'])->paginate(null, null, ["query" => $request->get()]);
        $page = $list->render();
        $count = $list->total();

        $type = $this->flashType;
        return $this->fetch(null, compact('list', 'page', 'count', 'type'));
    }

    /**
     * 轮播图添加
     * @param Request $request
     * @return array|mixed
     */
    public function flash_add(Request $request)
    {
        if ($request->isPost()) {
            $form = $request->param('form/a');
            $form['add_time'] = time();
            $result = \app\common\model\Flash::create($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }
        $type = $this->flashType;
        return $this->fetch(null, compact('type'));
    }

    /**
     * 轮播图编辑
     * @param Request $reques
     * @return array|mixed|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function flash_edit(Request $reques)
    {
        if ($this->request->isPost()) {
            $id = $reques->param('id', 0, 'intval');
            $form = $reques->param('form/a');
            $info = \app\common\model\Flash::where(['flash_id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);

            $result = \app\common\model\Flash::update($form, ['flash_id' => $info['flash_id']]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            }
            return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
        }

        $id = $reques->param('id', 0, 'intval');
        $info = \app\common\model\Flash::where(['flash_id' => $id])->find();
        if (empty($info)) return $this->error("该记录不存在");
        $type = $this->flashType;
        return $this->fetch(null, compact('info', 'type'));
    }

    /**
     * 轮播图删除
     * @param Request $reques
     * @return array
     */
    public function flash_del(Request $reques)
    {
        $id = $reques->param('id', 0, 'intval');
        try {
            $info = \app\common\model\Flash::where(['flash_id' => $id])->find();
            if (!$info) throw new Exception("记录不存在,不能执行该操作");

            $flag = \app\common\model\Flash::where(['flash_id' => $id])->delete();
            if ($flag === false) throw new Exception("操作失败");

            return $this->successJson(SUCCESS, "操作成功", null);
        } catch (Exception $e) {
            return $this->successJson(ERROR4, $e->getMessage(), null);
        }
    }

    /**
     * 配置管理
     * @param Request $request
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function config(Request $request)
    {
        if ($request->isPost()) {
            $form = input('form/a');
            if (!empty($form)) {
                foreach ($form as $key => $item) {
                    \app\common\model\ArkConfig::where(['key' => $key])->update([
                        'value' => $item,
                    ]);
                }
            }
            return ['code' => SUCCESS, 'message' => '操作成功', 'data' => ['url' => url('')]];
        }
        $where = ['desc' => ['neq', '']];
        $list = \app\common\model\ArkConfig::where($where)->select();
        return $this->fetch(null, compact('list'));
    }

    /**
     * 修改子商品
     * @param Request $request
     * @return array|mixed|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function goods_edit(Request $request)
    {
        if ($this->request->isPost()) {
            $form = input('form/a');
            $id = intval($form['id']);
            unset($form['id']);
            $info = model('ark_goods_list')->where(['id' => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);
            $form['start_time'] = strtotime($form['start_time']);
            $form['end_time'] = strtotime($form['end_time']);
            if ($form['start_time'] >= $form['end_time']) {
                return $this->successJson(ERROR1, "开始时间或结束时间有误，请检查一下", null);
            }
            if ($form['start_time'] <= time()) {
                return $this->successJson(ERROR1, "开始时间需大于当前时间", null);
            }
            $result = model('ark_goods_list')->save($form, ['id' => $info['id']]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        } else {
            $id = intval(input('id'));
            $info = model('ark_goods_list')->where(['id' => $id])->find();
            if (empty($info)) $this->error('该记录不存在');

            // 币种
            $currency = Db::name('currency')->where('is_line', 1)->field(['currency_id', 'currency_name'])->order('currency_id', 'asc')->select();
            return $this->fetch(null, compact('info', 'currency'));
        }
    }

    //预约排队
    public function queue_list() {
        $where = [];
        $level = intval($this->request->param('level'));
        if ($level) {
            $where['level'] = $level;
        }
        $goods_id = $this->request->param('goods_id');
        if (!empty($goods_id)) {
            $where['goods_id'] = $goods_id;
        }

        $RocketGoods = new ArkGoodsList();
        $list = $RocketGoods->where($where)->order("id desc,status asc")->paginate(null, null, ["query" => $this->request->get()]);
        if ($list) {
            foreach ($list as &$value) {
                $value['start_time'] = date('m-d H:i', $value['start_time']);
                $queue_num = Db::name('ark_buy_list')->where(['goods_list_id' => $value['id'], 'type' => 2, 'status' => 1])->sum('actual_num');
                $value['queue_num'] = sprintf('%.2f', $queue_num);
                $people_num = Db::name('ark_buy_list')->where(['goods_list_id' => $value['id'], 'type' => 2, 'status' => 1])->count('member_id');
                $value['people_num'] = sprintf('%.2f', $people_num);
                $auto_num = Db::name('ark_buy_list')->where(['goods_list_id' => $value['id'], 'type' => 3, 'status' => 1])->sum('actual_num');
                $value['auto_num'] = sprintf('%.2f', $auto_num);
                $auto_people_num = Db::name('ark_buy_list')->where(['goods_list_id' => $value['id'], 'type' => 3, 'status' => 1])->count('member_id');
                $value['auto_people_num'] = sprintf('%.2f', $auto_people_num);
                $buy_money = Db::name('ark_order')->where(['goods_list_id' => $value['id'], 'type' => ['in', [1,4]]])->sum('money');
                $value['buy_money'] = sprintf('%.2f', $buy_money);
                $buy_num = Db::name('ark_order')->where(['goods_list_id' => $value['id'], 'type' => ['in', [1,4]]])->count('id');
                $value['buy_num'] = $buy_num;
                $list_money = Db::name('ark_buy_list')->where(['goods_list_id' => $value['id'], 'type' => ['in', [1,4]]])->sum('num');
                $value['list_money'] = sprintf('%.2f', $list_money);
                $list_num = Db::name('ark_buy_list')->where(['goods_list_id' => $value['id'], 'type' => ['in', [1,4]]])->count('id');
                $value['list_num'] = $list_num;
            }
        }
        $page = $list->render();
        $count = $list->total();

        $info = [];
        $total_num = Db::name('ark_subscribe_transfer')->sum('num');
        $info['total_num'] = sprintf('%.2f', $total_num);//预约池总额
        $surplus_num = Db::name('currency_user')->where(['currency_id' => 105])->sum('num');
        $info['surplus_num'] = sprintf('%.2f', $surplus_num);//剩余预约池余额
        $seniority = \app\common\model\ArkConfig::getValue('subscribe_seniority');
        $complete_num = Db::name('currency_user')->where(['currency_id' => 105, 'num' => ['egt', $seniority]])->sum('num');
        $info['complete_num'] = sprintf('%.2f', $complete_num);//剩余预约池余额（排除小于30u）
        // 主商品
        $goods = Db::name('ark_goods')->where('status', 1)->field(['id', 'name'])->order('id', 'asc')->select();
        return $this->fetch(null, compact('list', 'page', 'count', 'goods', 'info'));
    }

    /**
     * 新增子商品
     * @param Request $request
     * @return array|mixed|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function goods_add(Request $request)
    {
        if ($this->request->isPost()) {
            $form = input('form/a');
            $form['start_time'] = strtotime($form['start_time']);
            $form['end_time'] = strtotime($form['end_time']);
            $start_time = strtotime(date('Y-m-d', $form['start_time']));
            if ($start_time == strtotime(date('Y-m-d'))) {
                $today_start = time();
                $today_end = (time() + 3600);
                if ($form['start_time'] >= $today_start && $form['start_time'] <= $today_end) {
                    return $this->successJson(ERROR1, "开舱前一小时不能新增闯关，请修改闯关开始时间", null);
                }
            }
            $form['name'] = model('ark_goods')->where(['id' => $form['goods_id']])->value('name');
            $form['add_time'] = time();
            $result = model('ark_goods_list')->insertGetId($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        } else {
            //主闯关
            $goods = model('ark_goods')->where(['status' => 1])->select();
            if (empty($goods)) $this->error('该记录不存在');

            // 币种
            $currency = \app\common\model\ArkConfig::getValue('reward_currency_id');
            return $this->fetch(null, compact('goods', 'currency'));
        }
    }
}
