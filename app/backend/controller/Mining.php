<?php

namespace app\backend\controller;

use app\common\model\CommonMiningConfig;
use app\common\model\CommonMiningMember;
use app\common\model\CommonMiningPay;
use app\common\model\CommonMiningProduct;
use app\common\model\YunMiningConfig;
use app\common\model\YunMiningIncome;
use app\common\model\YunMiningPay;
use think\Request;

class Mining extends AdminQuick
{

    public function yun_config_list(Request $request)
    {
        if ($request->isAjax()) {
            $page = $request->param('page', 1, 'intval');
            $limit = $request->param('limit', 10, 'intval');
            $list = YunMiningConfig::order(['id' => 'asc'])->page($page, $limit)->select();
            $count = YunMiningConfig::count();
            return ['code' => 0, 'message' => '成功', 'data' => $list, 'count' => $count];
        }
        return $this->fetch();
    }

    public function yun_config_edit(Request $request)
    {
        $id = $request->param('id', 0, 'intval');
        if ($request->isPost()) {
            $form = input('form/a');
            $flag = YunMiningConfig::where(['id' => $id])->update($form);
            if ($flag === false) {
                return ['code' => ERROR1, 'message' => '失败'];
            }
            return ['code' => SUCCESS, 'message' => '成功'];
        }
        $info = YunMiningConfig::where(['id' => $id])->find();
        return $this->fetch(null, compact('info'));
    }

    public function yun_order_list(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $member_id = $request->param('member_id', 0, 'intval');
            if ($member_id > 0) $where['member_id'] = $member_id;

            $page = $request->param('page', 1, 'intval');
            $limit = $request->param('limit', 10, 'intval');
            $list = YunMiningPay::where($where)->order(['id' => 'desc'])->page($page, $limit)->select();
            $count = YunMiningPay::where($where)->count();
            $status = ['质押中', '已解仓'];
            foreach ($list as &$item) {
                $item['add_time'] = date('Y-m-d H:i:s', $item['add_time']);
                $item['status'] = $status[$item['status']];
                $already_quota = YunMiningIncome::where(['third_id' => $item['id'], 'type' => 1])->sum('num');
                $item['already_quota'] = keepPoint($already_quota, 4);
            }
            return ['code' => 0, 'message' => '成功', 'data' => $list, 'count' => $count];
        }
        return $this->fetch();
    }

    public function yun_income_list(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $member_id = $request->param('member_id', 0, 'intval');
            if ($member_id > 0) $where['member_id'] = $member_id;
            $type = $request->param('type', 0, 'intval');
            if ($type > 0) $where['type'] = $type;

            $page = $request->param('page', 1, 'intval');
            $limit = $request->param('limit', 10, 'intval');
            $list = YunMiningIncome::where($where)->order(['id' => 'desc'])->page($page, $limit)->select();
            $count = YunMiningIncome::where($where)->count();
            $status = [1 => '提取额度', 2 => '挖矿收益', 3 => '解仓', 4 => '收益回流'];
            foreach ($list as &$item) {
                $item['add_time'] = date('Y-m-d H:i:s', $item['add_time']);
                $item['type'] = $status[$item['type']];
            }
            return ['code' => 0, 'message' => '成功', 'data' => $list, 'count' => $count];
        }
        return $this->fetch();
    }

    /**
     * 矿机页面
     * @param Request $request
     * @return array|mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function common_product_list(Request $request)
    {
        if ($request->isAjax()) {
            $page = $request->param('page', 1, 'intval');
            $limit = $request->param('limit', 10, 'intval');
            $list = CommonMiningProduct::page($page, $limit)->select();
            foreach ($list as &$item) {
                $item['status'] = $item['status'] ? '开启' : '关闭';//状态1开启 0关闭
                $item['price_type'] = $item['price_type'] ? 'GAS' : '固定';//价格类型 0固定 1GAS
                $item['add_time'] = date('Y-m-d H:i:s', $item['add_time']);//创建时间
            }
            $count = CommonMiningProduct::count();
            return ['code' => 0, 'message' => '成功', 'data' => $list, 'count' => $count];
        }
        return $this->fetch();
    }

    /**
     * 添加矿机
     * @param Request $request
     * @return array|mixed
     */
    public function common_product_add(Request $request)
    {
        if ($request->isPost()) {
            $form = $request->param('form/a');
            $form['add_time'] = time();
            $flag = CommonMiningProduct::insert($form);
            if ($flag === false) {
                return ['code' => ERROR1, 'message' => '失败'];
            }
            return ['code' => SUCCESS, 'message' => '成功'];
        }
        return $this->fetch();
    }

    /**
     * 修改矿机
     * @param Request $request
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function common_product_edit(Request $request)
    {
        $id = input('id', 0, 'intval');
        if ($request->isPost()) {
            $form = $request->param('form/a');
            $flag = CommonMiningProduct::where(['id' => $id])->update($form);
            if ($flag === false) {
                return ['code' => ERROR1, 'message' => '失败'];
            }
            return ['code' => SUCCESS, 'message' => '成功'];
        }
        $info = CommonMiningProduct::where(['id' => $id])->find();
        return $this->fetch(null, compact('info'));
    }

    /**
     * 删除矿机
     * @param Request $request
     * @return array
     */
    public function common_product_del(Request $request)
    {
        $id = $request->param('id', 0, 'intval');
        $flag = CommonMiningProduct::where(['id' => $id])->delete();
        if ($flag === false) {
            return ['code' => ERROR1, 'message' => '失败'];
        }
        return ['code' => SUCCESS, 'message' => '成功'];
    }

    /**
     * 配置页面
     * @param Request $request
     * @return array|mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function common_config_list(Request $request)
    {
        if ($request->isAjax()) {
            $page = $request->param('page', 1, 'intval');
            $limit = $request->param('limit', 10, 'intval');
            $list = CommonMiningConfig::order(['id' => 'asc'])->page($page, $limit)->select();
            $count = CommonMiningConfig::count();
            return ['code' => 0, 'message' => '成功', 'data' => $list, 'count' => $count];
        }
        return $this->fetch();
    }

    /**
     * 添加矿机配置
     * @param Request $request
     * @return array|mixed
     */
    public function common_config_add(Request $request)
    {
        if ($request->isPost()) {
            $form = $request->param('form/a');
            $flag = CommonMiningConfig::insert($form);
            if ($flag === false) {
                return ['code' => ERROR1, 'message' => '失败'];
            }
            return ['code' => SUCCESS, 'message' => '成功'];
        }
        return $this->fetch();
    }

    /**
     * 修改矿机配置
     * @param Request $request
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function common_config_edit(Request $request)
    {
        $id = $request->param('id', 0, 'intval');
        if ($request->isPost()) {
            $form = input('form/a');
            $flag = CommonMiningConfig::where(['id' => $id])->update($form);
            if ($flag === false) {
                return ['code' => ERROR1, 'message' => '失败'];
            }

            // 清除满存价格
            $today_start = strtotime(date('Y-m-d'));
            cache('common_mining_price_' . $today_start, null);

            return ['code' => SUCCESS, 'message' => '成功'];
        }
        $info = CommonMiningConfig::where(['id' => $id])->find();
        return $this->fetch(null, compact('info'));
    }

    /**
     * 删除矿机配置
     * @param Request $request
     * @return array
     */
    public function common_config_del(Request $request)
    {
        $id = $request->param('id', 0, 'intval');
        $flag = CommonMiningConfig::where(['id' => $id])->delete();
        if ($flag === false) {
            return ['code' => ERROR1, 'message' => '失败'];
        }
        return ['code' => SUCCESS, 'message' => '成功'];
    }

    /**
     * 订单列表
     * @param Request $request
     * @return array|mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function common_order_list(Request $request)
    {
        if ($request->isAjax()) {
            $page = $request->param('page', 1, 'intval');
            $limit = $request->param('limit', 10, 'intval');

            $where = [];
            $member_id = $request->param('member_id', 0, 'intval');
            if ($member_id > 0) $where['member_id'] = $member_id;

            $list = CommonMiningPay::where($where)->order(['id' => 'desc'])->page($page, $limit)->select();
            foreach ($list as &$item) {
                $item['member_info'] = '用户id：' . $item['member_id'] . '<br />'
                    . '用户名：' . \app\common\model\Member::where(['member_id' => $item['member_id']])->value('ename');
                $item['product_info'] = '矿机id：' . $item['product_id'] . '<br />'
                    . '矿机名：' . \app\common\model\CommonMiningProduct::where(['id' => $item['product_id']])->value('name');
                $item['real_pay_num'] = 'USDT：' . $item['real_pay_num'] . '<br />'
                    . '积分：' . $item['real_pay_integral'];
                $item['release_day'] = '开始时间：' . date('Y-m-d', $item['start_day']) . '<br />'
                    . '结束时间：' . date('Y-m-d', $item['treaty_day']);
                $item['last_release'] = '时间：' . date('Y-m-d', $item['start_day']) . '<br />'
                    . '数量：' . $item['last_release_num'];
                $item['add_time'] = date('Y-m-d H:i:s', $item['add_time']);
            }

            $count = CommonMiningPay::where($where)->count();
            return ['code' => 0, 'message' => '成功', 'data' => $list, 'count' => $count];
        }
        return $this->fetch();
    }

    /**
     * 汇总列表
     * @param Request $request
     * @return array|mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function common_member_list(Request $request)
    {
        if ($request->isAjax()) {
            $page = $request->param('page', 1, 'intval');
            $limit = $request->param('limit', 10, 'intval');
            $where = [];
            $member_id = $request->param('member_id', 0, 'intval');
            if ($member_id > 0) $where['member_id'] = $member_id;

            $list = CommonMiningMember::where($where)->order(['id' => 'desc'])->page($page, $limit)->select();
            foreach ($list as &$item) {
                $item['member_info'] = '用户id：' . $item['member_id'] . '<br />'
                    . '用户名：' . \app\common\model\Member::where(['member_id' => $item['member_id']])->value('ename');
                $item['team_total'] = '直推业绩：' . $item['one_team_total'] . '<br />'
                    . '团队业绩：' . $item['team_num'];
                $item['product_info'] = '入金金额：' . $item['pay_num'] . '<br />'
                    . '矿机容量：' . $item['pay_tnum'];
                $item['recommend'] = '推荐奖1：' . $item['total_child1'] . '<br />'
                    . '推荐奖2：' . $item['total_child2'];
                $item['release'] = '线性释放：' . $item['total_child4'] . '<br />'
                    . '线性冻结：' . $item['total_child5'];

            }
            $count = CommonMiningMember::where($where)->count();
            return ['code' => 0, 'message' => '成功', 'data' => $list, 'count' => $count];
        }
        return $this->fetch();
    }
}