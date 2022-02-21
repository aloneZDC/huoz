<?php

namespace app\backend\controller;

use app\common\model\Currency;
use app\common\model\OrdersRebotConfig;
use think\Db;
use think\Request;

class Trade extends AdminQuick
{
    //空操作
    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    /**
     * 免手续费人员列表
     */
    public function orders_free_fee()
    {
        $phone = input('phone');
        $member_id = input('member_id');
        $where = null;
        if (!empty($phone)) {
            if (checkEmail($phone)) {
                $where['c.email'] = $phone;
            } else {
                $where['c.phone'] = $phone;
            }
        }
        if (!empty($member_id)) {
            $where['c.member_id'] = $member_id;
        }

        $field = "a.*,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $list = Db::name('orders_free_fee')
            ->alias('a')
            ->field($field)
            ->join(config("database.prefix") . "member c", "c.member_id=a.member_id", "LEFT")
            ->where($where)
            ->order("a.add_time desc")
            ->paginate(25, null, ['query' => input()]);
        $show = $list->render();
        $this->assign('member_id', $member_id);
        $this->assign('phone', $phone);
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }

    //添加免手续费人员列表
    public function orders_free_fee_add()
    {
        $member_id = I('member_id', 0, 'intval');
        $model = M('orders_free_fee');
        if (IS_POST) {
            if (empty($member_id)) $this->error('请填写用户ID');

            $member = $model->where(['member_id' => $member_id])->find();
            if ($member) $this->error('已经存在');

            $r = $model->add([
                'member_id' => $member_id,
                'add_time' => time(),
            ]);
            if ($r !== false) {
                $this->success('修改成功');
            } else {
                $this->error('修改失败');
                return;
            }
        } else {
            $this->display();
        }
    }

    //删除免手续费人员列表
    public function orders_free_fee_del()
    {
        $id = I('id', 0, 'intval');
        $model = M('orders_free_fee');
        if (IS_POST) {
            if (empty($id)) $this->error('请选择');

            $info = $model->where(['id' => $id])->find();
            if (!$info) $this->error('不存在');

            $r = $model->where(['id' => $id])->delete();
            if ($r !== false) {
                self::output(1, '操作成功');
            } else {
                self::output(0, '操作失败');
                return;
            }
        } else {
            $this->display();
        }
    }

    /**
     * 挂单记录
     */
    public function trade(Request $request)
    {
        $type = input('types');
        $currency_id = input('currency_id');
        $phone = input('phone');
        $member_id = input('member_id');
        $trade_no = input('trade_no');
        $trade_id = input('trade_id');
        $is_fu = input('is_fu');
        $where = null;
        $datePicker = strtotime(input('datePicker'));
        $datePicker2 = strtotime(input('datePicker2'));
        $currency_trade_id = input('currency_trade_id');
        $user_type = input('user_type', 0);//用户类型 0-全部 1-正常用户 2-机器人
        if (!empty($currency_trade_id)) {
            $where['a.currency_trade_id'] = array("EQ", $currency_trade_id);
            $this->assign("currency_trade_id", $currency_trade_id);
        }
        if (!empty($type)) {
            $where['a.type'] = array("EQ", $type);
        }
        if (!empty($currency_id)) {
            $where['a.currency_id'] = array("EQ", $currency_id);
            $this->assign("currency_id", $currency_id);
        }

        $num = input('num');
        if (!empty($num)) {
            $where['a.num'] = $num;
        }

        $price = input('price');
        if (!empty($price)) {
            $where['a.price'] = $price;
        }

        if (!empty($phone)) {
            if (checkEmail($phone)) {
                $where['c.email'] = $phone;
            } else {
                $where['c.phone'] = $phone;
            }
        }

        if (!empty($trade_id)) {
            $where['a.trade_id'] = $trade_id;
        }
        if (!empty($trade_no)) {
            $where['a.trade_no'] = array('like', "%" . $trade_no . "%");
        }
        if (!empty($datePicker) && !empty($datePicker2)) {
            $where['a.add_time'] = array('between', array($datePicker, $datePicker2));
        }
        if (!empty($member_id)) {
            $where['c.member_id'] = array('like', "%" . $member_id . "%");
        }
        if (!empty($is_fu)) {
            $where['a.money'] = array('lt', 0);
        }
        if (empty($member_id)) {
            if ($user_type) {//1-普通用户 2-机器人
                $userList = [];
                if (!empty($currency_id)) {
                    $rebotFind = Db::name("OrdersRebotTrade")->where('currency_id', $currency_id)->field('buy_rebot_user_id,sell_rebot_user_id')->find();
                    if ($rebotFind['buy_rebot_user_id']) {
                        $userList[] = $rebotFind['buy_rebot_user_id'];
                    }
                    if (!in_array($rebotFind['sell_rebot_user_id'], $userList)) {
                        $userList[] = $rebotFind['sell_rebot_user_id'];
                    }
                } else {
                    $rebotSelect = Db::name("OrdersRebotTrade")->field('buy_rebot_user_id,sell_rebot_user_id')->select();
                    foreach ($rebotSelect as $key => $value) {
                        if (!in_array($value['buy_rebot_user_id'], $userList)) {
                            $userList[] = $value['buy_rebot_user_id'];
                        }
                        if (!in_array($value['sell_rebot_user_id'], $userList)) {
                            $userList[] = $value['sell_rebot_user_id'];
                        }
                    }
                }
                if (!empty($userList)) {
                    if ($user_type == 1) {//用户类型 0-全部 1-正常用户 2-机器人
                        $where['c.member_id'] = ['not in', $userList];
                    } else if ($user_type == 2) {
                        $where['c.member_id'] = ['in', $userList];
                    }
                }
            }
        }

        $field = "a.*,b.currency_name as b_name,d.currency_name as b_trade_name,c.email as email,c.member_id as member_id,c.ename as name,c.phone as phone";
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = Db::name('Trade')
            ->alias('a')
            ->field($field)
            ->join(config("database.prefix") . "currency b", "b.currency_id=a.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency d", "d.currency_id=a.currency_trade_id", "LEFT")
            ->join(config("database.prefix") . "member c", "c.member_id=a.member_id", "LEFT")
            ->where($where)
            ->order("a.add_time desc")
            ->paginate(25, null, ['query' => $request->get()])->each(function ($item, $key) {
                $item['type_name'] = getOrdersType($item['type']);
                return $item;
            });
        $show = $list->render();

        //积分类型
        $currency = Db::name('Currency')->field('currency_name,currency_id,is_trade_currency')->select();
        $this->assign('currency_id', $currency_id);
        $this->assign('currency_trade_id', $currency_trade_id);
        $this->assign('currency', $currency);
        $this->assign('type', $type);
        $this->assign('trade_no', $trade_no);
        $this->assign('phone', $phone);
        $this->assign('member_id', $member_id);
        $this->assign('num', $num);
        $this->assign('price', $price);
        $this->assign('is_fu', $is_fu);
        $this->assign('user_type', $user_type);
        $this->assign('userTypeList', ['0' => '全部', '1' => '正常用户', '2' => '机器人']);
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }

    /**
     * 委托记录
     */
    public function orders()
    {

        $status_id = input('status_id');
        $currency_id = input('currency_id');
        $email = input('email', '', 'trim');
        $orders_id = input('orders_id');
        $member_id = input('member_id');
        $types = input('types', "buy");
        $user_type = input('user_type', 0);//用户类型 0-全部 1-正常用户 2-机器人

        $where = [];
        $currency_trade_id = input('currency_trade_id');
        if (!empty($currency_trade_id)) {
            $where['a.currency_trade_id'] = $currency_trade_id;
//            $where.=" and a.currency_trade_id =".$currency_trade_id;
        }
        $this->assign("currency_trade_id", $currency_trade_id);
        if (!empty($types)) {
            $where['a.type'] = $types;
        }
        $this->assign("types", $types);
        if (!empty($currency_id)) {
            $where['a.currency_id'] = $currency_id;
//            $where .= " and a.currency_id = {$currency_id}";
        }
        $this->assign("currency_id", $currency_id);
        if (!empty($status_id) || $status_id === "0") {
            $where['a.status'] = $status_id;
//            $where .= " and a.status = {$status_id}";

        }
        $this->assign("status_id", $status_id);
        $num = input('num', '', 'trim');
        if (!empty($num)) {
            $where['a.num'] = $num;
//            $where .= " and a.num = '{$num}'";
        }
        $this->assign("num", $num);
        $price = input('price', '', 'trim');
        if (!empty($price)) {
            $where['a.price'] = $price;
//            $where .= " and a.price = '{$price}'";
        }
        $this->assign("price", $price);

        if (!empty($email)) {
            $where['c.email'] = $email;
//            $where .= " and c.email = '{$email}'";

        }
        $this->assign("email", $email);
        $phone = input('phone', '', 'trim');
        if (!empty($phone)) {
//            $where .= " and c.phone = '{$phone}'";
            $where['c.phone'] = $email;

        }
        $this->assign("phone", $phone);
        if (!empty($orders_id)) {
            $where['a.orders_id'] = $orders_id;
//            $where .= " and a.member_id = '{$member_id}'";

        }
        $this->assign("orders_id", $orders_id);
        if (!empty($member_id)) {
            $where['a.member_id'] = $member_id;
//            $where .= " and a.member_id = '{$member_id}'";

        } else {
            if ($user_type) {//1-普通用户 2-机器人
                $userList = [];
                if (!empty($currency_id)) {
                    $rebotFind = Db::name("OrdersRebotTrade")->where('currency_id', $currency_id)->field('buy_rebot_user_id,sell_rebot_user_id')->find();
                    if ($rebotFind['buy_rebot_user_id']) {
                        $userList[] = $rebotFind['buy_rebot_user_id'];
                    }
                    if (!in_array($rebotFind['sell_rebot_user_id'], $userList)) {
                        $userList[] = $rebotFind['sell_rebot_user_id'];
                    }
                } else {
                    $rebotSelect = Db::name("OrdersRebotTrade")->field('buy_rebot_user_id,sell_rebot_user_id')->select();
                    foreach ($rebotSelect as $key => $value) {
                        if (!in_array($value['buy_rebot_user_id'], $userList)) {
                            $userList[] = $value['buy_rebot_user_id'];
                        }
                        if (!in_array($value['sell_rebot_user_id'], $userList)) {
                            $userList[] = $value['sell_rebot_user_id'];
                        }
                    }
                }
                if (!empty($userList)) {
                    if ($user_type == 1) {//用户类型 0-全部 1-正常用户 2-机器人
                        $where['a.member_id'] = ['not in', $userList];
                    } else if ($user_type == 2) {
                        $where['a.member_id'] = ['in', $userList];
                    }
                }
            }
        }
        $this->assign("member_id", $member_id);
        $field = "a.*,b.currency_name as b_name,d.currency_name as b_trade_name,c.email,c.ename,c.phone";
        $order = $types == "sell" ? "a.price asc,a.add_time desc" : "a.price desc,a.add_time desc";
        $list = Db::name("Orders")->alias("a")->field($field)->where($where)->
        join(config("database.prefix") . "currency b", "b.currency_id=a.currency_id", "LEFT")->
        join(config("database.prefix") . "currency d", "d.currency_id=a.currency_trade_id", "LEFT")->
        join(config("database.prefix") . "member c", "c.member_id=a.member_id", "LEFT")->
        order($order)->paginate(15, null, ['query' => input()]);
        $show = $list->render();
        $where['a.type'] = "buy";
        $buy = Db::name("Orders")->alias("a")->where($where)->
        join(config("database.prefix") . "currency b", "b.currency_id=a.currency_id", "LEFT")->
        join(config("database.prefix") . "currency d", "d.currency_id=a.currency_trade_id", "LEFT")->
        join(config("database.prefix") . "member c", "c.member_id=a.member_id", "LEFT")->sum("num");
        $where['a.type'] = "sell";
        $sell = Db::name("Orders")->alias("a")->where($where)->
        join(config("database.prefix") . "currency b", "b.currency_id=a.currency_id", "LEFT")->
        join(config("database.prefix") . "currency d", "d.currency_id=a.currency_trade_id", "LEFT")->
        join(config("database.prefix") . "member c", "c.member_id=a.member_id", "LEFT")->sum("num");


        $url = Request::instance()->url();
        $buy_url = str_replace("types=sell", "types=buy", $url);
        $buy_url = strstr($buy_url, 'types=buy') !== false ? $buy_url : $buy_url . "?types=buy";
        $sell_url = str_replace("types=buy", "types=sell", $url);
        $sell_url = strstr($sell_url, 'types=sell') !== false ? $sell_url : $sell_url . "?types=sell";
        //积分类型
        $currency = Db::name('Currency')->field('currency_name,currency_id')->select();
        $this->assign('buy_url', $buy_url);
        $this->assign('sell_url', $sell_url);
        $this->assign('sell_num', $sell);
        $this->assign('buy_num', $buy);
        $this->assign('currency', $currency);
        $this->assign('user_type', $user_type);
        $this->assign('userTypeList', ['0' => '全部', '1' => '正常用户', '2' => '机器人']);
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }

    /**
     * 委托管理
     */
    public function orders_manage()
    {
        $currency_id = input('currency_id');
        $email = input('email', '', 'trim');
        $username = input('username', '', 'trim');
        $orders_id = input('orders_id', '', 'trim');
        $member_id = input('member_id', '', 'trim');
        $types = input('types', "buy");
        $user_type = input('user_type', 0);//用户类型 0-全部 1-正常用户 2-机器人
        if (!empty($types)) {
            $where['a.type'] = $types;
        }
        $this->assign("types", $types);
        $where['a.status'] = ['in', 'a.status', [0, 1]];
        $currency_trade_id = input('currency_trade_id');
        if (!empty($currency_trade_id)) {
            $where['a.currency_trade_id'] = $currency_trade_id;
        }
        $this->assign("currency_trade_id", $currency_trade_id);
        $num = input('num', '', 'trim');
        if (!empty($num)) {
            $where['a.num'] = $num;
        }
        $this->assign("num", $num);
        $price = input('price', '', 'trim');
        if (!empty($price)) {
            $where['a.price'] = $price;
        }
        $this->assign("price", $price);
        if (!empty($currency_id)) {
            $where['a.currency_id'] = $currency_id;
        }
        $this->assign("currency_id", $currency_id);
        if (!empty($email)) {
            $where['c.email'] = $email;
        }
        $this->assign("email", $email);
        $phone = input('phone', '', 'trim');
        if (!empty($phone)) {
            $where['c.phone'] = $phone;
        }
        $this->assign("phone", $phone);
        if (!empty($username)) {
            $where['c.name'] = $username;
        }
        $this->assign("username", $username);
        if (!empty($orders_id)) {
            $where['a.orders_id'] = $orders_id;
//            $where .= " and a.member_id = '{$member_id}'";

        }
        $this->assign("orders_id", $orders_id);
        if (!empty($member_id)) {
            $where['a.member_id'] = $member_id;
        } else {
            if ($user_type) {//1-普通用户 2-机器人
                $userList = [];
                if (!empty($currency_id)) {
                    $rebotFind = Db::name("OrdersRebotTrade")->where('currency_id', $currency_id)->field('buy_rebot_user_id,sell_rebot_user_id')->find();
                    if ($rebotFind['buy_rebot_user_id']) {
                        $userList[] = $rebotFind['buy_rebot_user_id'];
                    }
                    if (!in_array($rebotFind['sell_rebot_user_id'], $userList)) {
                        $userList[] = $rebotFind['sell_rebot_user_id'];
                    }
                } else {
                    $rebotSelect = Db::name("OrdersRebotTrade")->field('buy_rebot_user_id,sell_rebot_user_id')->select();
                    foreach ($rebotSelect as $key => $value) {
                        if (!in_array($value['buy_rebot_user_id'], $userList)) {
                            $userList[] = $value['buy_rebot_user_id'];
                        }
                        if (!in_array($value['sell_rebot_user_id'], $userList)) {
                            $userList[] = $value['sell_rebot_user_id'];
                        }
                    }
                }
                if (!empty($userList)) {
                    if ($user_type == 1) {//用户类型 0-全部 1-正常用户 2-机器人
                        $where['a.member_id'] = ['not in', $userList];
                    } else if ($user_type == 2) {
                        $where['a.member_id'] = ['in', $userList];
                    }
                }
            }
        }
        $this->assign("member_id", $member_id);

        $field = "a.*,b.currency_name as b_name,d.currency_name as b_trade_name,c.email,c.ename as name,c.phone";

        $order = $types == "sell" ? "a.price asc,a.add_time desc" : "a.price desc,a.add_time desc";
        $list = Db::name("Orders")->alias("a")->field($field)->where($where)->
        join(config("database.prefix") . "currency b", "b.currency_id=a.currency_id", "LEFT")->
        join(config("database.prefix") . "currency d", "d.currency_id=a.currency_trade_id", "LEFT")->
        join(config("database.prefix") . "member c", "c.member_id=a.member_id", "LEFT")->
        order($order)->paginate(25, null, ['query' => input()]);
        $show = $list->render();

        $where['a.type'] = "buy";
        $buy = Db::name("Orders")->alias("a")->where($where)->
        join(config("database.prefix") . "currency b", "b.currency_id=a.currency_id", "LEFT")->
        join(config("database.prefix") . "currency d", "d.currency_id=a.currency_trade_id", "LEFT")->
        join(config("database.prefix") . "member c", "c.member_id=a.member_id", "LEFT")->sum("num");
        $where['a.type'] = "sell";
        $sell = Db::name("Orders")->alias("a")->where($where)->
        join(config("database.prefix") . "currency b", "b.currency_id=a.currency_id", "LEFT")->
        join(config("database.prefix") . "currency d", "d.currency_id=a.currency_trade_id", "LEFT")->
        join(config("database.prefix") . "member c", "c.member_id=a.member_id", "LEFT")->sum("num");


        $url = Request::instance()->url();
        $buy_url = str_replace("types=sell", "types=buy", $url);
        $buy_url = strstr($buy_url, 'types=buy') !== false ? $buy_url : $buy_url . "?types=buy";
        $sell_url = str_replace("types=buy", "types=sell", $url);
        $sell_url = strstr($sell_url, 'types=sell') !== false ? $sell_url : $sell_url . "?types=sell";

        //积分类型
        $currency = Db::name('Currency')->field('currency_name,currency_id')->select();
        $this->assign('buy_url', $buy_url);
        $this->assign('sell_url', $sell_url);
        $this->assign('sell_num', $sell);
        $this->assign('buy_num', $buy);
        $this->assign('currency', $currency);
        $this->assign('user_type', $user_type);
        $this->assign('userTypeList', ['0' => '全部', '1' => '正常用户', '2' => '机器人']);
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }

    /**
     *撤销订单
     */
    public function cancel()
    {
        $order_id = input('post.order_id');

        if (empty($order_id)) {
            $info['status'] = 0;
            $info['info'] = '撤销订单不正确';
            $this->ajaxReturn($info);
        }

        $where['orders_id'] = $order_id;
        $where['status'] = array('in', '0,1');
        $list = Db::name('Orders')->where("orders_id = '$order_id'")->find();

        if (empty($list)) {
            $info['status'] = 1;
            $info['info'] = '撤销订单有误';
            $this->ajaxReturn($info);
        }
        $member_id = $list['member_id'];

        /*$oneOrder = $this->getOneOrders($list['type'], $list['currency_id'], 0, $list['currency_trade_id']);//买1、卖1
        if ($list['orders_id'] == $oneOrder['orders_id']) {
            $info['status'] = 1;
            $info['info'] = '买1、卖1不能撤销';
            $this ->ajaxReturn($info);
        }*/

        $info = $this->cancelByOrderid($list);
        $this->ajaxReturn($info);
    }

    /**
     * 返回一条挂单记录
     * @param int $currencyId 积分类型id
     * @param float $price 交易价格
     * @return array 挂单记录
     */
    private function getOneOrders($type, $currencyId, $price, $trade_currency_id)
    {
        switch ($type) {
            case 'buy':
                $gl = 'egt';
                $order = 'price desc';
                break;
            case 'sell':
                $gl = 'elt';
                $order = 'price asc';
                break;
        }
        $where['currency_id'] = $currencyId;
        $where['currency_trade_id'] = $trade_currency_id;
        $where['type'] = $type;
        //$where['price']=array($gl,$price);
        $where['status'] = array('in', array(0, 1));
        return db('Orders')->where($where)->order($order . ',add_time asc')->find();
    }

    private function cancelByOrderid($one_order)
    {
        Db::startTrans();
        $r[] = Db::name('Orders')->where("orders_id={$one_order['orders_id']}")->setField('status', '-1');
        //返还资金
        switch ($one_order['type']) {
            case 'buy':
                //$money=($one_order['num']-$one_order['trade_num'])*$one_order['price']*(1+$one_order['fee']);
                $money = keepPoint(($one_order['num'] - $one_order['trade_num']) * $one_order['price'] * (1 + $one_order['fee']), 6);
                if ($money > 0) {
                    $r[] = model('AccountBook')->addLog([
                        'member_id' => $one_order['member_id'],
                        'currency_id' => $one_order['currency_trade_id'],
                        'number_type' => 1,
                        'number' => $money,
                        'type' => 17,
                        'content' => "lan_Return_funds",
                        //'fee'=> ($one_order['num'] - $one_order['trade_num']) * $one_order['price'] * $one_order['fee'],
                        'fee' => keepPoint(($one_order['num'] - $one_order['trade_num']) * $one_order['price'] * $one_order['fee'], 6),
                        'to_member_id' => 0,
                        'to_currency_id' => $one_order['currency_id'],
                        'third_id' => $one_order['orders_id'],
                    ]);
                    $r[] = $this->setUserMoney($one_order['member_id'], $one_order['currency_trade_id'], $money, 'inc', 'num');
                    $r[] = $this->setUserMoney($one_order['member_id'], $one_order['currency_trade_id'], $money, 'dec', 'forzen_num');
                }
                break;
            case 'sell':
                //$num=$one_order['num']-$one_order['trade_num'] ;
                $num = keepPoint($one_order['num'] - $one_order['trade_num'], 6);
                if ($num > 0) {
                    $r[] = model('AccountBook')->addLog([
                        'member_id' => $one_order['member_id'],
                        'currency_id' => $one_order['currency_id'],
                        'number_type' => 1,
                        'number' => $num,
                        'type' => 17,
                        'content' => "lan_Return_funds",
                        'fee' => 0,
                        'to_member_id' => 0,
                        'to_currency_id' => $one_order['currency_trade_id'],
                        'third_id' => $one_order['orders_id'],
                    ]);
                    $r[] = $this->setUserMoney($one_order['member_id'], $one_order['currency_id'], $num, 'inc', 'num');
                    $r[] = $this->setUserMoney($one_order['member_id'], $one_order['currency_id'], $num, 'dec', 'forzen_num');
                }
                break;
        }
        //更新订单状态
        if (!in_array(false, $r)) {
            Db::commit();
            $info['status'] = 1;
            $info['info'] = lang('lan_test_revocation_success');
            return $info;
        } else {
            Db::rollback();
            $info['status'] = -1;
            $info['info'] = lang('lan_safe_image_upload_failure');

            return $info;
        }
    }

    /**
     * 设置账户资金
     * @param int $currency_id 积分类型ID
     * @param int $num 交易数量
     * @param char $inc_dec setDec setInc 是加钱还是减去
     * @param char forzen_num num
     */
    protected function setUserMoney($member_id, $currency_id, $num, $inc_dec, $field)
    {
        $inc_dec = strtolower($inc_dec);
        $field = strtolower($field);
        //允许传入的字段
        if (!in_array($field, array('num', 'forzen_num'))) {
            return false;
        }
        //如果是RMB
        if ($currency_id == 0) {
            //修正字段
            switch ($field) {
                case 'forzen_num':
                    $field = 'forzen_rmb';
                    break;
                case 'num':
                    $field = 'rmb';
                    break;
            }
            switch ($inc_dec) {
                case 'inc':
                    $msg = Db::name('Member')->where(array('member_id' => $member_id))->setInc($field, $num);
                    break;
                case 'dec':
                    $msg = Db::name('Member')->where(array('member_id' => $member_id))->setDec($field, $num);
                    break;
                default:
                    return false;
            }
            return $msg;
        } else {
            switch ($inc_dec) {
                case 'inc':
                    $msg = Db::name('Currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setInc($field, $num);
                    break;
                case 'dec':
                    $msg = Db::name('Currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setDec($field, $num);
                    break;
                default:
                    return false;
            }
            return $msg;
        }
    }

    /**
     * 设置订单状态
     * @param int $status 0 1 2 -1
     * @param int $orders_id 订单id
     * @return  boolean
     */
    protected function setOrdersStatusByOrdersId($status, $orders_id)
    {
        return M('Orders')->where("orders_id=$orders_id")->setField('status', $status);
    }

    //获取财务管理->人工充值管理中的数据,涉及到yang_member、yang_pay
    public function xiangDan()
    {
        //取会员ID、将会员ID和状态赋给字段
        $uid = I('uid');
        $where['yang_pay.member_id'] = $uid;
        $where['yang_pay.status'] = 1;
        //取总记录数
        $count = M('Pay')->where($where)->join('left join yang_member on yang_member.member_id=yang_pay.member_id')->count();
        //取结果
        $list = M('Pay')
            ->field('yang_pay.*,yang_member.email,yang_member.phone')
            ->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
            ->where($where)
            ->order('add_time desc')
            ->select();
        //将状态字段的数值用文字来表示
        foreach ($list as $k => $v) {
            $list[$k]['status'] = payStatus($v['status']);
        }
        //循环打印结果
        for ($i = 0; $i < $count; $i++) {
            if ($list[$i]["currency_id"] > 0) {
                $currency_type = "充积分";
            } else {
                $currency_type = "充值";
            }
            $a .= '<tr><td>' . $list[$i]["pay_id"] . '</td>
                     		<td>' . $list[$i]["email"] . '</td>
							<td>' . $list[$i]["member_name"] . '</td>
							<td>' . $list[$i]["member_id"] . '</td>
							<td>' . $list[$i]["account"] . '</td>
							<td>' . $list[$i]["money"] . '</td>
							<td>' . $list[$i]["count"] . '</td>
							<td>' . $list[$i]["status"] . '</td>
							<td>' . $currency_type . '</td>
							<td>' . date("Y-m-d H:i:s", $list[$i]["add_time"]) . '</td>
							<td>' . $list[$i]["due_bank"] . '</td>
							<td>' . $list[$i]["batch"] . '</td>
							<td>' . $list[$i]["capital"] . '</td>
							<td>' . $list[$i]["commit_name"] . '</td>
							<td>' . date("Y-m-d H:i:s", $list[$i]["commit_time"]) . '</td>
							<td>' . $list[$i]["audit_name"] . '</td>
							<td>' . date("Y-m-d H:i:s", $list[$i]["audit_time"]) . '</td></tr>';
        }

        //充值统计
        $totalcz = M('Pay')
            ->field('sum(money) as totalczmoney,sum(count) as totalczcount')
            ->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
            ->where($where)
            ->where('yang_pay.currency_id=0')
            ->select();
        //充积分统计
        $totalcb = M('Pay')
            ->field('sum(money) as totalcurrency')
            ->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
            ->where($where)
            ->where('yang_pay.currency_id>0')
            ->select();
        $a .= '<tr><td><span style="font-size:16px;color:#2F4F4F;">充值合计：</span></td><td>' . $totalcz[0]['totalczmoney'] . '</td>
			 <td><span style="font-size:16px;color:#2F4F4F;">充积分合计：</span></td><td>' . $totalcb[0]['totalcurrency'] . '</td>
			 <td><span style="font-size:16px;color:#2F4F4F;">实际打款合计：</span></td><td>' . $totalcz[0]['totalczcount'] . '</td></tr>';

        $this->ajaxReturn($a);
    }

    //获取众筹管理->众筹记录中的数据,涉及到yang_member、yang_issue、yang_issue_log
    public function xiangdan_zc()
    {
        $uid_zc = I('uid_zc');
        $option_val = I('option_val');
        $where['yang_member.member_id'] = $uid_zc;
        if ($option_val !== '') {
            if ($option_val != -1) {
                $where['yang_issue_log.cid'] = $option_val;
            }
        }
        $count = M('Issue_log')->join('left join yang_member on yang_member.member_id=yang_issue_log.uid')->join('left join yang_issue on yang_issue.id=yang_issue_log.iid')->where($where)->count();
        $log = M('Issue_log')
            ->field('yang_issue_log.*,yang_member.member_id,yang_member.name,yang_issue.title,yang_issue_log.num*yang_issue_log.price as count')
            ->join('left join yang_member on yang_member.member_id=yang_issue_log.uid')
            ->join('left join yang_issue on yang_issue.id=yang_issue_log.iid')
            ->order('add_time desc')
            ->where($where)->select();
        foreach ($log as $key => $vo) {
            $log[$key]['buy_name'] = $vo['buy_currency_id'] == 0 ? "人民币" : M('Currency')->where("currency_id='{$vo['buy_currency_id']}'")->find()['currency_name'];
        }
        $curName = M("Currency")->field('currency_id,currency_name')->select();
        if ($option_val == "") {
            $b .= '<select name="currency_id" id="select_zc">
                 <option value="-1" selected>全部</option>
					<option value="0">人民币</option>';
        } else {
            if ($option_val == -1) {
                $b .= '<select name="currency_id" id="select_zc">
                 <option value="-1" selected>全部</option>';
            } else {
                $b .= '<select name="currency_id" id="select_zc">
                 <option value="-1">全部</option>';
            }
            if ($option_val == 0) {
                $b .= '<option value="0" selected>人民币</option>';
            } else {
                $b .= '<option value="0">人民币</option>';
            }
        }
        foreach ($curName as $key => $cur) {
            if ($option_val == $cur["currency_id"]) {
                $b .= '<option value=' . $cur["currency_id"] . ' id="currencyId" selected>' . $cur["currency_name"] . '</option>';
            } else {
                $b .= '<option value=' . $cur["currency_id"] . ' id="currencyId">' . $cur["currency_name"] . '</option>';
            }
        }

        $b .= '</select>|';
        for ($i = 0; $i < $count; $i++) {
            $b .= '<tr><td>' . $log[$i]["iid"] . '</td>
                     		<td>' . $log[$i]["title"] . '</td>
							<td>' . $log[$i]["name"] . '</td>
							<td>' . $log[$i]["member_id"] . '</td>
							<td>' . $log[$i]["num"] . '</td>
							<td>' . $log[$i]["deal"] . '</td>
							<td>' . $log[$i]["price"] . '</td>
							<td>' . $log[$i]["count"] . '</td>
							<td>' . date("Y-m-d H:i:s", $log[$i]["add_time"]) . '</td>
							<td>' . $log[$i]["buy_name"] . '</td>
							<td>' . $log[$i]["remarks"] . '</td></tr>';
        }

        //统计
        $totalzc = M('Issue_log')
            ->field('sum(yang_issue_log.num) as buynum,sum(yang_issue_log.deal) as freezenum,sum(yang_issue_log.num*yang_issue_log.price) as totalaggregate')
            ->join('left join yang_member on yang_member.member_id=yang_issue_log.uid')
            ->join('left join yang_issue on yang_issue.id=yang_issue_log.iid')
            ->where($where)
            ->select();
        $b .= '<tr><td><span style="font-size:16px;color:#2F4F4F;">购买数量合计：</span></td><td>' . $totalzc[0]['buynum'] . '</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">冻结数量合计：</span></td><td>' . $totalzc[0]['freezenum'] . '</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">购买总额合计：</span></td><td>' . $totalzc[0]['totalaggregate'] . '</td></tr>';

        $this->ajaxReturn($b);
    }


    //会员管理->会员列表,涉及到yang_member
    public function xiangDan_user()
    {
        $uid_user = I('uid_user');
        $where['yang_currency_user.member_id'] = $uid_user;
        $filed = 'yang_member.*,yang_currency.currency_name,yang_currency_user.num,yang_currency_user.forzen_num,yang_currency_user.num_award,yang_currency_user.sum_award,yang_currency_user.currency_id';
        $count = M('Currency_user')->field($filed)->join('yang_currency ON yang_currency_user.currency_id = yang_currency.currency_id')
            ->join('yang_member on yang_currency_user.member_id = yang_member.member_id')
            ->where($where)->count();
        $list_user = M('Currency_user')->field($filed)->join('yang_currency ON yang_currency_user.currency_id = yang_currency.currency_id')
            ->join('yang_member on yang_currency_user.member_id = yang_member.member_id')
            ->where($where)
            ->select();
        $c .= '<tr><td>' . $list_user[0]["member_id"] . '</td>
                     		<td>' . $list_user[0]["email"] . '</td>
							<td>' . $list_user[0]["name"] . '</td>
							<td>' . $list_user[0]["phone"] . '</td>
							<td>' . $list_user[0]["rmb"] . '</td>
							<td>' . $list_user[0]["forzen_rmb"] . '</td>
							<td>' . date("Y-m-d H:i:s", $list_user[0]["reg_time"]) . '</td></tr>';
        $c .= '<tr>
                        <th>积分类型名称</th>
                        <th>持有数量</th>
						<th>冻结数量</th>
						<th>剩余奖励</th>
						<th>总奖励</th> 
						<th>充积分数量</th>
						<th>购买量</th>
						<th>卖出量</th>
                    </tr>';
        for ($i = 0; $i < $count; $i++) {
            //充积分数据
            $wherea['yang_tibi.currency_id'] = $list_user[$i]["currency_id"];
            $wherea['yang_tibi.user_id'] = $uid_user;
            $wherea['yang_tibi.status'] = 3;
            $totalcb = M('Tibi')
                ->field('sum(num) as totalcharging')
                ->join("yang_member on yang_tibi.user_id=yang_member.member_id")
                ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")
                ->where($wherea)
                ->select();
            //交易数据
            $whereb['c.member_id'] = $uid_user;
            $whereb['a.currency_id'] = $list_user[$i]["currency_id"];
            $totaljybuy = M('Trade')
                ->alias('a')
                ->field('sum(a.num) as jybuynum')
                ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
                ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
                ->where($whereb)
                ->where("a.type='buy'")
                ->select();

            $totaljysell = M('Trade')
                ->alias('a')
                ->field('sum(a.num) as jybuynum')
                ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
                ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
                ->where($whereb)
                ->where("a.type='sell'")
                ->select();
            $c .= '<tr><td>' . $list_user[$i]["currency_name"] . '</td>
							<td>' . $list_user[$i]["num"] . '</td>
							<td>' . $list_user[$i]["forzen_num"] . '</td>
							<td>' . $list_user[$i]["num_award"] . '</td>
							<td>' . $list_user[$i]["sum_award"] . '</td>
							<td>' . $totalcb[0]['totalcharging'] . '</td>
							<td>' . $totaljybuy[0]['jybuynum'] . '</td>
							<td>' . $totaljysell[0]['jybuynum'] . '</td></tr>';
        }

        //实际充值钱、提现钱、用户备注
        $c .= '|';
        $wherec['yang_pay.member_id'] = $uid_user;
        $wherec['yang_pay.status'] = 1;
        $totalcz = M('Pay')
            ->field('sum(count) as totalcount')
            ->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
            ->where($wherec)
            ->where('yang_pay.currency_id=0')
            ->select();
        $c .= $totalcz[0]['totalcount'];
        $c .= '|';
        $whered['yang_bank.uid'] = $uid_user;
        $whered['yang_withdraw.status'] = array('in', '2,4');
        $totaltx = M('bank')
            ->field('sum(money) as totalmoney')
            ->join("yang_withdraw ON yang_withdraw.bank_id = yang_bank.id")
            ->join("yang_areas as b ON b.area_id = yang_bank.address")
            ->join("yang_areas as a ON a.area_id = b.parent_id ")
            ->join("yang_member as c on yang_withdraw.uid=c.member_id")
            ->where($whered)
            ->select();
        $c .= $totaltx[0]['totalmoney'];
        $c .= '|';
        $c .= $totalcz[0]['totalcount'] - $totaltx[0]['totalmoney'];
        $c .= '|';
        $list_user_remark = M('Currency_user')->field('yang_member.remarks')->join('yang_currency ON yang_currency_user.currency_id = yang_currency.currency_id')
            ->join('yang_member on yang_currency_user.member_id = yang_member.member_id')
            ->where($where)
            ->select();
        $c .= $list_user_remark[0]["remarks"];

        $this->ajaxReturn($c);
    }


    //交易管理->交易记录,涉及到yang_trade、yang_currency、yang_member
    public function xiangDan_jy()
    {
        $uid_jy = I('uid_jy');
        $option_valjy = I('option_valjy');
        $where['c.member_id'] = $uid_jy;
        if ($option_valjy !== '') {
            if ($option_valjy != -1) {
                $where['a.currency_id'] = $option_valjy;
            }
        }
        $field = "a.*,b.currency_name as b_name,c.email as email";
        $count = M('Trade')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->count();
        $list_jy = M('Trade')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->order(" a.add_time desc ")
            ->select();
        foreach ($list_jy as $key => $vo) {
            $list_jy[$key]['type_name'] = getOrdersType($vo['type']);
        }
        $curName_jy = M('Currency')->field('currency_id,currency_name')->select();
        if ($option_valjy == "") {
            $d .= '<select name="currency_id3" id="select_jy">
                 <option value="-1" selected>全部</option>
					<option value="0">人民币</option>';
        } else {
            if ($option_valjy == -1) {
                $d .= '<select name="currency_id3" id="select_jy">
                 <option value="-1" selected>全部</option>';
            } else {
                $d .= '<select name="currency_id3" id="select_jy">
                 <option value="-1">全部</option>';
            }
            if ($option_valjy == 0) {
                $d .= '<option value="0" selected>人民币</option>';
            } else {
                $d .= '<option value="0">人民币</option>';
            }
        }
        foreach ($curName_jy as $key => $cur) {
            if ($option_valjy == $cur["currency_id"]) {
                $d .= '<option value=' . $cur["currency_id"] . ' id="currencyId3" selected>' . $cur["currency_name"] . '</option>';
            } else {
                $d .= '<option value=' . $cur["currency_id"] . ' id="currencyId3">' . $cur["currency_name"] . '</option>';
            }
        }
        $d .= '</select>|';
        for ($i = 0; $i < $count; $i++) {
            $d .= '<tr><td>' . $list_jy[$i]["trade_id"] . '</td>
                     		<td>' . $list_jy[$i]["trade_no"] . '</td>
							<td>' . $list_jy[$i]["email"] . '</td>
							<td>' . $list_jy[$i]["b_name"] . '</td>
							<td>' . $list_jy[$i]["num"] . '</td>
							<td>' . $list_jy[$i]["price"] . '</td>
							<td>' . $list_jy[$i]["money"] . '</td>
							<td>' . number_format($list_jy[$i]["fee"], 4, '.', '') . '</td>
							<td>' . $list_jy[$i]["type_name"] . '</td>
							<td>' . date("Y-m-d H:i:s", $list_jy[$i]["add_time"]) . '</td></tr>';
        }

        //统计买
        $totaljybuy = M('Trade')
            ->alias('a')
            ->field('sum(a.num) as jybuynum,sum(a.money) as jybuymoney')
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->where("a.type='buy'")
            ->select();
        //统计卖
        $totaljysell = M('Trade')
            ->alias('a')
            ->field('sum(a.num) as jybuynum,sum(a.money) as jybuymoney')
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->where("a.type='sell'")
            ->select();
        $d .= '<tr><td><span style="font-size:16px;color:#2F4F4F;">买入量合计：</span></td><td>' . $totaljybuy[0]['jybuynum'] . '</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">买入金额合计：</span></td><td>' . $totaljybuy[0]['jybuymoney'] . '</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">卖出量合计：</span></td><td>' . $totaljysell[0]['jybuynum'] . '</td>
				 <td><span style="font-size:16px;color:#2F4F4F;">卖出金额合计：</span></td><td>' . $totaljysell[0]['jybuymoney'] . '</td></tr>';

        $this->ajaxReturn($d);
    }


    //财务管理->提现审核,涉及到yang_bank、yang_withdraw、yang_areas、yang_member
    public function xiangDan_tx()
    {
        $uid_tx = I('uid_tx');
        $where['yang_bank.uid'] = $uid_tx;
        $where ['yang_withdraw.status'] = array('in', '2,4');
        $count = M('Withdraw')->join("yang_bank ON yang_withdraw.bank_id = yang_bank.id")->join("yang_member on yang_withdraw.uid=yang_member.member_id")->where($where)->count();
        $field = "yang_withdraw.*,yang_bank.cardname,yang_bank.cardnum,yang_bank.bankname,b.area_name as barea_name,a.area_name as aarea_name,c.email";
        $list_tx = M('bank')->field($field)
            ->join("yang_withdraw ON yang_withdraw.bank_id = yang_bank.id")
            ->join("yang_areas as b ON b.area_id = yang_bank.address")
            ->join("yang_areas as a ON a.area_id = b.parent_id ")
            ->join("yang_member as c on yang_withdraw.uid=c.member_id")
            ->where($where)
            ->order('yang_withdraw.status desc,yang_withdraw.add_time desc')
            ->select();
        foreach ($list_tx as $key => $v) {
            if ($v['status'] == 2) {
                $list_tx[$key]['status'] = '通过';
            } else {
                $list_tx[$key]['status'] = '付款成功';
            }

        }
        for ($i = 0; $i < $count; $i++) {
            $e .= '<tr><td>' . $list_tx[$i]["withdraw_id"] . '</td>
                     		<td>' . $list_tx[$i]["cardname"] . '</td>
							<td>' . $list_tx[$i]["uid"] . '</td>
							<td>' . $list_tx[$i]["bankname"] . '</td>
							<td>' . $list_tx[$i]["cardnum"] . '</td>
							<td>' . $list_tx[$i]["aarea_name"] . '&nbsp;&nbsp;' . $list_tx[$i]["barea_name"] . '</td>
							<td>' . $list_tx[$i]["all_money"] . '</td>
							<td>' . $list_tx[$i]["withdraw_fee"] . '</td>
							<td>' . $list_tx[$i]["money"] . '</td>
							<td>' . $list_tx[$i]["order_num"] . '</td>
							<td>' . date("Y-m-d H:i:s", $list_tx[$i]["add_time"]) . '</td>
							<td>' . $list_tx[$i]["status"] . '</td></tr>';
        }

        //统计
        $totaltx = M('bank')
            ->field('sum(money) as totalmoney')
            ->join("yang_withdraw ON yang_withdraw.bank_id = yang_bank.id")
            ->join("yang_areas as b ON b.area_id = yang_bank.address")
            ->join("yang_areas as a ON a.area_id = b.parent_id ")
            ->join("yang_member as c on yang_withdraw.uid=c.member_id")
            ->where($where)
            ->select();
        $e .= '<tr><td><span style="font-size:16px;color:#2F4F4F;">实际金额合计：</span></td><td>' . $totaltx[0]['totalmoney'] . '</td></tr>';

        $this->ajaxReturn($e);
    }


    //钱包积分类型管理->提积分记录,涉及到yang_tibi、yang_currency、yang_member
    public function xiangDan_tb()
    {
        $option_valtb = I('option_valtb');
        $uid_tb = I('uid_tb');
        $where['yang_tibi.user_id'] = $uid_tb;
        $where['yang_tibi.status'] = 1;
        if ($option_valtb !== '') {
            if ($option_valtb != -1) {
                $where['yang_tibi.currency_id'] = $option_valtb;
            }
        }
        $count = M("Tibi")->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->count();
        $field = "yang_tibi.*,yang_member.email,yang_currency.currency_name";
        $list_tb = M("Tibi")->field($field)->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->select();
        foreach ($list_tb as $key => $v) {
            $list_tb[$key]['status'] = $v['status'] == 1 ? "通过" : "等待转出";
        }
        $curName_tb = M('Currency')->field('currency_id,currency_name')->select();
        if ($option_valtb == "") {
            $f .= '<select name="currency_id2" id="select_tb">
                 <option value="-1" selected>全部</option>
					<option value="0">人民币</option>';
        } else {
            if ($option_valtb == -1) {
                $f .= '<select name="currency_id2" id="select_tb">
                 <option value="-1" selected>全部</option>';
            } else {
                $f .= '<select name="currency_id2" id="select_tb">
                 <option value="-1">全部</option>';
            }
            if ($option_valtb == 0) {
                $f .= '<option value="0" selected>人民币</option>';
            } else {
                $f .= '<option value="0">人民币</option>';
            }
        }
        foreach ($curName_tb as $key => $cur) {
            if ($option_valtb == $cur["currency_id"]) {
                $f .= '<option value=' . $cur["currency_id"] . ' id="currencyId2" selected>' . $cur["currency_name"] . '</option>';
            } else {
                $f .= '<option value=' . $cur["currency_id"] . ' id="currencyId2">' . $cur["currency_name"] . '</option>';
            }
        }
        $f .= '</select>|';
        for ($i = 0; $i < $count; $i++) {
            $f .= '<tr><td>' . $list_tb[$i]["email"] . '</td>
                     		<td>' . $list_tb[$i]["currency_name"] . '</td>
							<td>' . $list_tb[$i]["url"] . '</td>
							<td>' . $list_tb[$i]["num"] . '</td>
							<td>' . $list_tb[$i]["actual"] . '</td>
							<td>' . date("Y-m-d H:i:s", $list_tb[$i]["add_time"]) . '</td>
							<td>' . $list_tb[$i]["status"] . '</td></tr>';
        }

        //统计
        $totaltb = M('Tibi')
            ->field('sum(actual) as totalcurrency')
            ->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")
            ->where($where)
            ->select();
        $f .= '<tr><td><span style="font-size:16px;color:#2F4F4F;">实际数量合计：</span></td><td>' . $totaltb[0]['totalcurrency'] . '</td></tr>';

        $this->ajaxReturn($f);
    }


    //钱包积分类型管理->充积分记录,涉及到yang_tibi、yang_currency、yang_member
    public function xiangDan_cb()
    {
        $option_valcb = I('option_valcb');
        $uid_cb = I('uid_cb');
        $where['yang_tibi.user_id'] = $uid_cb;
        $where['yang_tibi.status'] = 3;
        if ($option_valcb !== '') {
            if ($option_valcb != -1) {
                $where['yang_tibi.currency_id'] = $option_valcb;
            }
        }
        $count = M("Tibi")->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->count();
        $field = "yang_tibi.*,yang_member.email,yang_currency.currency_name";
        $list_cb = M("Tibi")->field($field)->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->select();
        foreach ($list_cb as $key => $v) {
            $list_cb[$key]['status'] = $v['status'] == 3 ? "通过" : "等待转出";
        }
        $curName_cb = M('Currency')->field('currency_id,currency_name')->select();
        if ($option_valcb == "") {
            $f .= '<select name="currency_id4" id="select_cb">
                 <option value="-1" selected>全部</option>
					<option value="0">人民币</option>';
        } else {
            if ($option_valcb == -1) {
                $f .= '<select name="currency_id4" id="select_cb">
                 <option value="-1" selected>全部</option>';
            } else {
                $f .= '<select name="currency_id4" id="select_cb">
                 <option value="-1">全部</option>';
            }
            if ($option_valcb == 0) {
                $f .= '<option value="0" selected>人民币</option>';
            } else {
                $f .= '<option value="0">人民币</option>';
            }
        }
        foreach ($curName_cb as $key => $cur) {
            if ($option_valcb == $cur["currency_id"]) {
                $f .= '<option value=' . $cur["currency_id"] . ' id="currencyId4" selected>' . $cur["currency_name"] . '</option>';
            } else {
                $f .= '<option value=' . $cur["currency_id"] . ' id="currencyId4">' . $cur["currency_name"] . '</option>';
            }
        }
        $f .= '</select>|';
        for ($i = 0; $i < $count; $i++) {
            $f .= '<tr><td>' . $list_cb[$i]["email"] . '</td>
                     		<td>' . $list_cb[$i]["currency_name"] . '</td>
							<td>' . $list_cb[$i]["url"] . '</td>
							<td>' . $list_cb[$i]["num"] . '</td>
							<td>' . $list_cb[$i]["actual"] . '</td>
							<td>' . date("Y-m-d H:i:s", $list_cb[$i]["add_time"]) . '</td>
							<td>' . $list_cb[$i]["status"] . '</td></tr>';
        }

        //统计
        $totalcb = M('Tibi')
            ->field('sum(num) as totalcharging')
            ->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")
            ->where($where)
            ->select();
        $f .= '<tr><td><span style="font-size:16px;color:#2F4F4F;">实际数量合计：</span></td><td>' . $totalcb[0]['totalcharging'] . '</td></tr>';

        $this->ajaxReturn($f);
    }


    /**
     * 导出excel
     */
    public function expdata()
    {
        $type = I('type');
        $currency_id = I('currency_id');
        $email = I('email');
        $datePicker = strtotime(I('datePicker'));
        $datePicker2 = strtotime(I('datePicker2'));
        if (!empty($type)) {
            $where['a.type'] = array("EQ", $type);
        }
        if (!empty($currency_id)) {
            $where['a.currency_id'] = array("EQ", $currency_id);
        }
        if (!empty($email)) {
            $where['c.email'] = array('like', "%" . $email . "%");
        }
        if (!empty($datePicker) && !empty($datePicker2)) {
            $where['a.add_time'] = array('between', array($datePicker, $datePicker2));
        }
        $field = "a.*,b.currency_name as b_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $count = M('Trade')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->count();// 查询满足要求的总记录数
        $Page = new Page($count, 25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('type' => $type, 'currency_id' => $currency_id, 'email' => $email));

        $show = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M('Trade')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->order(" a.add_time desc ")
            //->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        if ($list) {
            foreach ($list as $key => $vo) {
                $list[$key]['type_name'] = getOrdersType($vo['type']);
                $list[$key]['add_time'] = date('Y-m-d H:i:s', $vo['add_time']);

            }
        }
        //积分类型

        $currency = M('Currency')->field('currency_name,currency_id')->select();
        $this->assign('currency', $currency);
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出


        $xlsName = "User";
        $xlsCell = array(
            array('trade_id', '成交编号'),
            array('trade_no', '订单号'),
            array('email', '买家email'),
            array('member_id', '会员ID'),
            array('name', '姓名'),
            array('phone', '手机'),
            array('b_name', '积分类型'),
            array('num', '数量'),
            array('price', '单价'),
            array('money', '总价'),
            array('fee', '手续费'),
            array('type_name', '类型'),
            array('add_time', '成交时间')
        );
        // $xlsModel = M('Post');
        $xlsData = $list;
        $this->exportExcel($xlsName, $xlsCell, $xlsData);


        $this->display();
    }

    /**
     * 导出excel orders
     */
    public function excel_orders()
    {
        $status_id = I('status_id');
        $currency_id = I('currency_id');
        $email = I('email');
        $datePicker = strtotime(I('datePicker'));
        $datePicker2 = strtotime(I('datePicker2'));
        if (!empty($currency_id)) {
            $where['a.currency_id'] = array("EQ", $currency_id);
        }
        if (!empty($status_id) || $status_id === "0") {
            $where['a.status'] = array('EQ', $status_id);
        }
        if (!empty($email)) {
            $where['c.email'] = array('like', "%" . $email . "%");
        }
        if (!empty($datePicker) && !empty($datePicker2)) {
            $where['a.add_time'] = array('between', array($datePicker, $datePicker2));
        }

        $field = "a.*,b.currency_name as b_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $count = M('Orders')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->order(" a.add_time desc ")->count();// 查询满足要求的总记录数
        $Page = new Page($count, 25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('status_id' => $status_id, 'currency_id' => $currency_id, 'email' => $email));

        $show = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M('Orders')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->order(" a.add_time desc ")
            //->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        //积分类型

        if ($list) {
            foreach ($list as $key => $vo) {
                $list[$key]['type_name'] = getOrdersType($vo['type']);
                $list[$key]['add_time'] = date('Y-m-d H:i:s', $vo['add_time']);
                $list[$key]['trade_time'] = date('Y-m-d H:i:s', $vo['trade_time']);
                $list[$key]['fee'] = $vo['num'] * $vo['fee'] * $vo['price'];
                $list[$key]['status'] = getOrdersStatus($vo['status']);
            }
        }
        $currency = M('Currency')->field('currency_name,currency_id')->select();
        $this->assign('currency', $currency);
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出

        $xlsName = "User";
        $xlsCell = array(
            array('order_id', '委托编号'),

            array('email', '用户邮箱'),
            array('member_id', '会员ID'),
            array('name', '姓名'),
            array('phone', '手机'),
            array('b_name', '积分类型'),
            array('price', '价格'),
            array('num', '挂单数量'),
            array('trade_num', '成交数量'),
            array('fee', '手续费'),
            array('type_name', '类型'),
            array('add_time', '挂单时间'),
            array('trade_time', '成交时间'),

            array('status', '状态')
        );
        // $xlsModel = M('Post');
        $xlsData = $list;
        $this->exportExcel($xlsName, $xlsCell, $xlsData);

        $this->display();
    }

    /**
     * k线刷单设置列表
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Create by: Red
     * Date: 2019/9/24 11:46
     */
    function kline_list()
    {
        $field = "ca.*,c1.currency_name,c2.currency_name as trade_name";
        $list = Db::name("currency_autotrade")->alias("ca")->field($field)
            ->join(config("database.prefix") . "currency c1", "c1.currency_id=ca.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c2", "c2.currency_id=ca.trade_currency_id", "LEFT")
            ->select();
        $this->assign("list", $list);
        return $this->fetch();
    }

    /**
     * 删除一条设置数据
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * Create by: Red
     * Date: 2019/9/24 11:51
     */
    function kline_delete()
    {
        $id = input("id");
        if (is_numeric($id)) {
            $del = Db::name("currency_autotrade")->where(['id' => $id])->delete();
            if ($del) {
                return $this->success("删除成功");
            }
            return $this->error("删除失败");
        }
        return $this->error("参数错误");
    }

    /**
     * 添加或修改页面
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Create by: Red
     * Date: 2019/9/24 15:34
     */
    function kline_add_update()
    {
        if ($_POST) {
            if ($_POST['currency_id'] == $_POST['trade_currency_id']) {
                return $this->error("两个币种不能相同");
            }
            if ($_POST['id'] > 0) {
                $find_oney = Db::name("currency_autotrade")->where(['id' => $_POST['id']])->find();
                if (!empty($find_oney)) {
                    if ($find_oney['currency_id'] != $_POST['currency_id'] || $find_oney['trade_currency_id'] != $_POST['trade_currency_id']) {
                        $find_currency = Db::name("currency_autotrade")->where(['currency_id' => $_POST['currency_id'], 'trade_currency_id' => $_POST['trade_currency_id']])->find();
                        if (!empty($find_currency)) {
                            return $this->error("币种对已存在");
                        }
                    }
                    //修改的
                    $update = Db::name("currency_autotrade")->where(['id' => $_POST['id']])->update($_POST);
                    if ($update) {
                        return $this->success("修改成功");
                    } else {
                        return $this->error("修改失败");
                    }
                }

            } else {
                //增加的
                unset($_POST['id']);
                $find_currency = Db::name("currency_autotrade")->where(['currency_id' => $_POST['currency_id'], 'trade_currency_id' => $_POST['trade_currency_id']])->find();
                if (!empty($find_currency)) {
                    return $this->error("币种对已存在");
                }
                $add = Db::name("currency_autotrade")->insertGetId($_POST);
                if ($add) {
                    return $this->success("添加成功");
                } else {
                    return $this->error("添加失败");
                }
            }
        }
        $curr = Db::name("currency")->field('currency_id,currency_name')->select();
        $id = input("id");
        $find = null;
        if (!empty($id)) {
            $find = Db::name("currency_autotrade")->where(['id' => $id])->find();
        }
        $this->assign("curr", $curr);
        $this->assign("id", $id);
        $this->assign("list", $find);
        return $this->fetch();
    }


    function configs()
    {
        return $this->fetch();
    }

    /**
     * 机器人交易对
     */
    public function rebot_trade()
    {

        $field = "a.*,b.currency_name as b_name,d.currency_name as b_trade_name";
        $list = Db::name("OrdersRebotTrade")->alias("a")->field($field)
            ->join(config("database.prefix") . "currency b", "b.currency_id=a.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency d", "d.currency_id=a.trade_currency_id", "LEFT")
            ->select();
        //->buildSql();
        //var_dump($sql);

        //$list = [];
        $this->assign('list', $list);
        $this->assign('typeList', ['1' => '平台币机器人', '2' => '主流币机器人']);
        $this->assign('switchList', ['0' => '关闭', '1' => '开启']);
        $this->assign('operateList', ['0' => '横盘', '1' => '拉盘', '2' => '砸盘']);
        $this->assign('trendList', ['0' => '正常', '1' => '上升', '2' => '下降']);
        $this->assign('cancelList', ['1' => '尾部', '2' => '全部']);
        return $this->fetch();
    }

    /**
     * 机器人配置
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function rebot_config()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $find = Db::name("OrdersRebotTrade")->where('id', $data['id'])->find();
            if (!$find) {
                return $this->error('ID错误,修改失败!请重试');
            }

            foreach ($data as $key => $value) {
                if (in_array($key, ['rebot_operate_type', 'rebot_price_rate', 'rebot_cancel_order_type', 'rebot_heng_trend', 'rebot_heng_order_max', 'rebot_heng_order_min', 'buy_rebot_order_max', 'buy_rebot_order_min', 'buy_rebot_order_num', 'buy_rebot_trade_num_max', 'buy_rebot_trade_num_min', 'sell_rebot_order_max', 'sell_rebot_order_min', 'sell_rebot_order_num', 'sell_rebot_trade_num_max', 'sell_rebot_trade_num_min'])) {
                    if ($data[$key] != $find[$key]) $data['next_cancel_order_all'] = 1;
                }
            }

            $save = Db::name("OrdersRebotTrade")->update($data);

            if ($save === false) {
                return $this->error('修改失败!请重试');
            }

            return $this->success('修改成功!', url('Trade/rebot_trade'));
        }

        $id = input('id');

        $sql = "SHOW FULL COLUMNS FROM `yang_orders_rebot_trade`;";
        $result = Db::query($sql);
        $fieldList = [];
        foreach ($result as $key => $value) {
            $fieldList[$value['Field']] = $value['Comment'];
        }

        $field = "a.*,b.currency_name as b_name,d.currency_name as b_trade_name";
        $find = Db::name("OrdersRebotTrade")->alias("a")->field($field)->where('id', $id)
            ->join(config("database.prefix") . "currency b", "b.currency_id=a.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency d", "d.currency_id=a.trade_currency_id", "LEFT")
            ->find();
        $configList = [];
        $tradeName = '';
        if ($find) {
            $tradeName = $find['b_name'] . '/' . $find['b_trade_name'];
            $rebotType = $find['rebot_type'];
            foreach ($find as $key => $value) {
                if (!in_array($key, ['id', 'currency_id', 'trade_currency_id', 'b_name', 'b_trade_name', 'next_cancel_order_all', 'rebot_type'])) {
                    $configList[] = [
                        'orc_key' => $key,
                        'orc_value' => $value,
                        'orc_des' => $fieldList[$key],
                    ];
                }
            }
        }

        $this->assign('configList', $configList);
        $this->assign('fieldList', $fieldList);
        $this->assign('tradeName', $tradeName);
        $this->assign('rebotType', $rebotType);
        $this->assign('typeList', ['1' => '平台币机器人', '2' => '主流币机器人']);
        $this->assign('id', $id);
        return $this->fetch();
    }

    /**
     * 机器人配置-拉盘、砸盘
     */
    public function pan(Request $request)
    {
        $id = input('id');
        $type = input('type');

        if (empty($id)) {
            $info['status'] = 0;
            $info['info'] = '操作失败,ID错误';
            $this->ajaxReturn($info);
        }
        $find = Db::name("OrdersRebotTrade")->where('id', $id)->find();
        if (!$find) {
            $info['status'] = 0;
            $info['info'] = '操作失败,ID错误';
            $this->ajaxReturn($info);
        }

        $data = ['rebot_operate_type' => $type, 'next_cancel_order_all' => 1];
        $save = Db::name("OrdersRebotTrade")->where('id', $id)->update($data);

        if ($save === false) {
            $info['status'] = 0;
            $info['info'] = '修改失败!请重试';
            $this->ajaxReturn($info);
        }

        $info['status'] = 1;
        $info['info'] = '操作成功!';
        $this->ajaxReturn($info);
    }

    /**
     * 机器人配置-横盘(正常、向上、向下)
     */
    public function heng(Request $request)
    {
        $id = input('id');
        $trend = input('trend');

        if (empty($id)) {
            $info['status'] = 0;
            $info['info'] = '操作失败,ID错误';
            $this->ajaxReturn($info);
        }
        $find = Db::name("OrdersRebotTrade")->where('id', $id)->find();
        if (!$find) {
            $info['status'] = 0;
            $info['info'] = '操作失败,ID错误';
            $this->ajaxReturn($info);
        }

        $data = [
            'rebot_operate_type' => 0,
            'rebot_heng_trend' => $trend,
            'next_cancel_order_all' => 1
        ];
        $save = Db::name("OrdersRebotTrade")->where('id', $id)->update($data);

        if ($save === false) {
            $info['status'] = 0;
            $info['info'] = '修改失败!请重试';
            $this->ajaxReturn($info);
        }

        $info['status'] = 1;
        $info['info'] = '操作成功!';
        $this->ajaxReturn($info);
    }

    /**
     * 机器人统计
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function rebot_statistics(Request $request)
    {
        $currency = Currency::where('currency_mark', 'DNCBB')->find() ?: Currency::get(40);
        $currency_trade = Currency::where('currency_mark', 'KOIBB')->find() ?: Currency::get(39);
        $userList = [];
        $buyUserId = OrdersRebotConfig::get_value('buy_reboot_user_id');//卖单机器人用户id 0-代表未设置
        $buyMoney = 0;
        $sellMoney = 0;
        if ($buyUserId) {
            $userList[] = $buyUserId;
            $buyMoney = $this->getUserMoney($buyUserId, $currency_trade['currency_id'], 'num');
        }
        $sellUserId = OrdersRebotConfig::get_value('sell_reboot_user_id');//卖单机器人用户id 0-代表未设置
        if ($sellUserId) {
            if (!in_array($sellUserId, $userList)) {
                $userList[] = $sellUserId;
            }
            $sellMoney = $this->getUserMoney($buyUserId, $currency['currency_id'], 'num');
        }
        $where = [
            'currency_id' => $currency['currency_id'],
            'currency_trade_id' => $currency_trade['currency_id'],
            'member_id' => ['in', $userList],
        ];
        //$rebot_type = input('rebot_type');
        $starttime = input("starttime");
        $endtime = input("endtime");
        if (empty($endtime)) $endtime = date('Y-m-d');

        /*if (!empty($rebot_type)) {
            if ($rebot_type == 1) {
                $where['member_id'] = $buyUserId;
            }
            else {
                $where['member_id'] = $sellUserId;
            }
        }
        else {
            $where['member_id'] = ['in', $userList];
        }*/

        empty($starttime) ? $startTime = 0 : $startTime = strtotime($starttime);
        $endTime = strtotime($endtime) + 86399;
        $where['add_time'] = ['between', [$startTime, $endTime]];
        if (empty($userList)) {
            $list = [];
        } else {
            $field = "FROM_UNIXTIME(add_time,'%Y-%m-%d') as `date`,SUM(IF(`type`='buy',num,0)) AS buy_num_total,SUM(IF(`type`='sell',num,0)) AS sell_num_total,SUM(IF(`type`='buy' AND `other_member_id` IN (" . join($userList) . "),num,0)) AS buy_num_total1,SUM(IF(`type`='sell' AND `other_member_id` IN (" . join($userList) . "),num,0)) AS sell_num_total1,SUM(IF(`type`='buy',money,0)) AS buy_money_total,SUM(IF(`type`='sell',money,0)) AS sell_money_total,SUM(IF(`type`='buy' AND `other_member_id` IN (" . join($userList) . "),money,0)) AS buy_money_total1,SUM(IF(`type`='sell' AND `other_member_id` IN (" . join($userList) . "),money,0)) AS sell_money_total1";
            $a = Db::name('Trade')->where($where)->field($field)
                ->group('date')
                ->order("add_time", "desc")
                ->buildSql();
            //var_dump($a);
            $field = "FROM_UNIXTIME(add_time,'%Y-%m-%d') as `date`,SUM(IF(`type`='buy',num,0)) AS buy_order_total,SUM(IF(`type`='sell',num,0)) AS sell_order_total,SUM(IF(`type`='buy',trade_num,0)) AS buy_trade_total,SUM(IF(`type`='sell',trade_num,0)) AS sell_trade_total";
            $b = Db::name('Orders')->where($where)->field($field)
                ->group('date')
                ->order("add_time", "desc")
                ->buildSql();
            //var_dump($b);
            $list = Db::table($a . ' a')->join($b . ' b', 'a.date=b.date')->select();

            if (count($list) > 0) {
                $total = [
                    'date' => '汇总',
                ];
                foreach ($list as $key => $value) {
                    foreach ($value as $key1 => $val1) {
                        if ($key1 != 'date') {
                            $list[$key][$key1] = floattostr($val1);
                            //$str = $key1.'_total';
                            //$$str += floattostr($val1);
                            if (array_key_exists($key1, $total)) {
                                $total[$key1] += floattostr($val1);
                            } else {
                                $total[$key1] = floattostr($val1);
                            }
                        }
                    }
                    $list[$key]['buy_num_total2'] = floattostr($value['buy_num_total'] - $value['buy_num_total1']);
                    if (array_key_exists('buy_num_total2', $total)) {
                        $total['buy_num_total2'] += floattostr($value['buy_num_total'] - $value['buy_num_total1']);
                    } else {
                        $total['buy_num_total2'] = floattostr($value['buy_num_total'] - $value['buy_num_total1']);
                    }
                    $list[$key]['buy_money_total2'] = floattostr($value['buy_money_total'] - $value['buy_money_total1']);
                    if (array_key_exists('buy_money_total2', $total)) {
                        $total['buy_money_total2'] += floattostr($value['buy_money_total'] - $value['buy_money_total1']);
                    } else {
                        $total['buy_money_total2'] = floattostr($value['buy_money_total'] - $value['buy_money_total1']);
                    }
                    $list[$key]['sell_num_total2'] = floattostr($value['sell_num_total'] - $value['sell_num_total1']);
                    if (array_key_exists('sell_num_total2', $total)) {
                        $total['sell_num_total2'] += floattostr($value['sell_num_total'] - $value['sell_num_total1']);
                    } else {
                        $total['sell_num_total2'] = floattostr($value['sell_num_total'] - $value['sell_num_total1']);
                    }
                    $list[$key]['sell_money_total2'] = floattostr($value['sell_money_total'] - $value['sell_money_total1']);
                    if (array_key_exists('sell_money_total2', $total)) {
                        $total['sell_money_total2'] += floattostr($value['sell_money_total'] - $value['sell_money_total1']);
                    } else {
                        $total['sell_money_total2'] = floattostr($value['sell_money_total'] - $value['sell_money_total1']);
                    }
                }
                array_unshift($list, $total);
            }

            if (input("daochu") == 1) {
                $xlsCell = array(
                    array('date', '日期'),
                    array('buy_order_total', '买单挂单数量'),
                    array('buy_trade_total', '买单挂单成交数量'),
                    array('sell_order_total', '卖单挂单数量'),
                    array('sell_trade_total', '卖单挂单成交数量'),
                    array('buy_num_total', '买单成交数量'),
                    array('buy_num_total2', '买单外部成交数量'),
                    array('buy_money_total', '买单成交金额'),
                    array('buy_money_total2', '买单外部成交金额'),
                    array('sell_num_total', '卖单成交数量'),
                    array('sell_num_total2', '卖单外部成交数量'),
                    array('sell_money_total', '卖单成交金额'),
                    array('sell_money_total2', '卖单外部成交金额'),
                );
                return export_excel("机器人统计", $xlsCell, $list);
            }
        }
        return $this->fetch('', [
            'list' => $list,
            'empty' => '暂无数据',
            'currency' => $currency['currency_name'],
            'currency_trade' => $currency_trade['currency_name'],
            'buyUserId' => $buyUserId,
            'buyMoney' => $buyMoney,
            'sellUserId' => $sellUserId,
            'sellMoney' => $sellMoney,
            //'rebot_type' => $rebot_type,
            'starttime' => $starttime,
            'endtime' => $endtime,
            //'rebotTypeList' => ['0'=>'全部','1'=>'买单机器人','2'=>'卖单机器人'],
        ]);
    }
}
