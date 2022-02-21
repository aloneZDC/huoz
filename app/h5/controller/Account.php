<?php

namespace app\h5\controller;

use app\common\model\GoodsMainOrders;
use app\common\model\ShopConfig;
use think\Db;

class Account extends Base
{
    /**
     * 美立方,个人基本信息
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function memberinfo()
    {
//        $boss_plan = [
//            'title' => lang('lan_boss_plan_title'),
//            'info' => lang('lan_boss_plan_info'),
//            'support' => lang('lan_boss_plan_support'),
//        ];
//
//        $setting = [
//            'about' => url('mobile/News/detail', ['position_id' => 118]),
//            'support' => url('mobile/News/detail', ['position_id' => 178]),
//            'contact' => url('mobile/News/detail', ['position_id' => 179]),
//            'kf' => url('mobile/Chat/index'),
//        ];

        $member_info = Db::name('member')->where(['member_id' => $this->member_id])->find();

        // 国家地区
        $country = '';
        if (!empty($member_info['country_code'])) {
            $countrycode = Db::name("countries_code")->field($this->lang . '_name as name')->where(['phone_code' => $member_info['country_code']])->find();
            if ($countrycode) $country = $countrycode['name'];
        }

        // 头像
        $head = empty($member_info['head']) ? model('member')->default_head : $member_info['head'];

        // 身份证实名认证
//        $verify_state = Db::name('verify_file')->where(['member_id' => $this->member_id])->value('verify_state');
//        if (!$verify_state && !is_numeric($verify_state)) $verify_state = -1;

        // 银商申请记录表
//        $agent_status = model('OtcAgent')->check_info($this->member_id);
//        $send_type = $member_info['send_type'];
//        if (empty($send_type)) {
//            if (!empty($member_info['phone'])) {
//                $send_type = 1;
//            } elseif (!empty($member_info['email'])) {
//                $send_type = 2;
//            }
//        }

        $buyNew = 0;//是否已有购买 0-否 1-是
        $giftGoodsId = ShopConfig::get_value('new_gift_goods_id', 1);
        $ordersFind = GoodsMainOrders::alias('a')->join(config('database.prefix') . 'goods_orders b', 'b.go_id IN (a.gmo_go_id)', 'LEFT')
            ->where(['a.gmo_user_id' => $this->member_id, 'b.go_goods_id' => $giftGoodsId, 'a.gmo_status' => ['in', [GoodsMainOrders::STATUS_PAID, GoodsMainOrders::STATUS_SHIPPED, GoodsMainOrders::STATUS_COMPLETE]]])->find();

        if ($ordersFind) {
            $buyNew = 1;
        }

        //查询用户等级
//        $member_level = OrderTotal::where(['member_id'=>$this->member_id])->find();

        // 统计订单数量
        $OrdersNum = GoodsMainOrders::where(['gmo_user_id' => $this->member_id, 'gmo_status' => ['in', [1, 2, 3]]])
            ->field([
                'COUNT(CASE WHEN gmo_status=1 THEN 1 ELSE NULL END)' => 'nohair', // 未发
                'COUNT(CASE WHEN gmo_status=2 THEN 1 ELSE NULL END)' => 'unpaid', // 未付
                'COUNT(CASE WHEN gmo_status=3 THEN 1 ELSE NULL END)' => 'uncoll', // 未收
                'COUNT(CASE WHEN gmo_status=6 THEN 1 ELSE NULL END)' => 'unuse'  // 未使用
            ])
            ->find();

        // 农场配置
//        $OrchardConfig = OrchardConfig::byField();
        // 有效直推人数
//        $member_num = Db::name('member')->where(['pid' => $this->member_id, 'active_status' => 1])->count();
        // 是否购买喜庆的奖励
//        $sendGoodsId = ShopConfig::get_value('send_gift_goods_id', 1);
//        $directOrdersFind = GoodsMainOrders::alias('a')->join(config('database.prefix') . 'goods_orders b', 'b.go_id IN (a.gmo_go_id)', 'LEFT')->where(['a.gmo_user_id' => $this->member_id, 'b.go_goods_id' => $sendGoodsId, 'a.gmo_status' => ['in', [GoodsMainOrders::STATUS_PAID, GoodsMainOrders::STATUS_SHIPPED, GoodsMainOrders::STATUS_COMPLETE]]])->find();

//        $directOrdersFind = GoodsMainOrders::alias('a')->join(config('database.prefix') . 'goods_orders b', 'b.go_id IN (a.gmo_go_id)', 'LEFT')->where(['a.gmo_user_id' => $this->member_id, 'b.go_goods_id' => $sendGoodsId, 'a.gmo_status' => ['in', [GoodsMainOrders::STATUS_PAID, GoodsMainOrders::STATUS_SHIPPED, GoodsMainOrders::STATUS_COMPLETE]]])->find();

//        $is_direct_push = (!$directOrdersFind && $member_num >= $OrchardConfig['direct_push']) ? 1 : 0; // 是否弹出奖励公告 1弹 0不弹
//        $is_direct_push = 0; // TODO: 暂时不弹奖励公告
//        $bonus_level = Db::name('order_exponent')->where(['level' => $member_level['bonus_level']])->value('name');
//        $level_name = Db::name('members_level')->where(['level' => $member_level['member_level_id']])->value('title');



        $result = [
            'member_id' => $this->member_id,
            'username' => $member_info['ename'],
            //'invitation_url' => url('','',''),
            'invitation_code' => $member_info['invit_code'],
            'invit_url' => url('mobile/Invite/index', ['id' => $member_info['invit_code']], false, true),
            'reg_time' => date("Y-m-d", $member_info['reg_time']),
            'phone' => !empty($member_info['phone']) ? substr($member_info['phone'], 0, 3) . '****' . substr($member_info['phone'], 9, 2) : '',
            'account' => !empty($member_info['phone']) ? $member_info['phone'] : $member_info['email'],
            'idcard' => !empty($member_info['idcard']) ? substr($member_info['idcard'], 0, 3) . '****' . substr($member_info['idcard'], -3) : '',
            'cardtype' => $member_info['cardtype'], //1=身份证2=护照 3=军官证 4=其他
            'email' => !empty($member_info['email']) ? substr($member_info['email'], 0, 3) . '****' . substr($member_info['email'], -7) : '',
            'name' => $member_info['name'],
            'nick' => $member_info['nick'],
            'head' => $head,
            'country' => $country,
//            'verify_state' => $verify_state, //-1未认证 0未通过 1:已认证 2: 审核中
//            'verify_time' => $member_info['verify_time'] > 0 ? date('Y-m-d', $member_info['verify_time']) : '',
//            'agent_status' => $agent_status['status'],
            'stores_open' => 1,
            'login_type' => $member_info['send_type'],
            'is_set_safe_pwd' => !empty($member_info['pwdtrade']) ? 1 : 2, //是否设置过安全密码 1已设置 2没设置
//            'send_type' => $send_type,
            'google_verify_status' => $member_info['google_secret'] ? 1 : 2,
            'buy_new' => $buyNew,
//            'farm_switch' => $OrchardConfig['farm_switch'], // 农场开关 1开 0关
//            'is_direct_push' => $is_direct_push, // 是否弹出奖励公告 1弹 0不弹
//            'send_goods_id' => $sendGoodsId, // 超值奖励商品id
//            'member_level' => $member_level['member_level_id'], //用户等级
            'domain' => request()->domain(), // 当前域名
//            'bonus_level' => $bonus_level,//分红指数级别名称
//            'level_name' => $level_name//用户等级名称
        ];

        // 判断昵称
        if (empty($result['nick'])) $result['nick'] = !empty($result['phone']) ? $result['phone'] : $result['email'];

        $this->output(10000, lang('lan_operation_success'),
            ['member' => $result,
//                'boss_plan' => $boss_plan, 'setting' => $setting,
                'OrdersNum' => $OrdersNum]
        );
    }
}