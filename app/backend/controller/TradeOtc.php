<?php

namespace app\backend\controller;

use think\Db;
use think\Exception;
use think\Request;

class TradeOtc extends AdminQuick
{
    protected $config;
    protected $public_action = ['tradeotc_info', 'order_trade_info'];

    public function _initialize()
    {
        parent::_initialize();
        $config = Db::name("Config")->field('key,value')->select();
        foreach ($config as $k => $v) {
            $config[$v['key']] = $v['value'];
        }
        $this->config = $config;
    }

    // 挂单记录
    public function order_list()
    {
        $type = I('type');
        $currency_id = I('currency_id');
        $phone = I('phone');
        $member_id = I('member_id');
        $orders_id = I('orders_id');

        $datePicker = strtotime(I('datePicker'));
        $datePicker2 = strtotime(I('datePicker2'));
        $where = null;
        if (!empty($type)) {
            $where['a.type'] = $type;
        }
        if (!empty($currency_id)) {
            $where['a.currency_id'] = $currency_id;
        }

        if (!empty($phone)) {
            if (checkEmail($phone)) {
                $where['c.email'] = $phone;
            } else {
                $where['c.phone'] = $phone;
            }
        }

        if (!empty($orders_id)) {
            $where['a.orders_id'] = $orders_id;
        }
        if (!empty($datePicker) && !empty($datePicker2)) {
            $where['a.add_time'] = array('between', array($datePicker, $datePicker2));
        }
        if (!empty($member_id)) {
            $where['c.member_id'] = $member_id;
        }

        $status = I('status', '');
        if ($status != '') {
            $status = intval($status);
            $where['a.status'] = $status;
        }

        $field = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $list = Db::name('Orders_otc')
            ->alias('a')
            ->field($field)
            ->join(config("database.prefix") . "currency b", "a.currency_id = b.currency_id", "LEFT")
            ->join(config("database.prefix") . "member c", "a.member_id = c.member_id", "LEFT")
            ->where($where)
            ->order("a.orders_id desc")
            ->paginate(25, null, ['query' => input()])->each(function ($item, $key) {
                $item['type_name'] = getOrdersType($item['type']);
                return $item;
            });
        $show = $list->render();
        //积分类型
        $currency = Db::name('Currency')->field('currency_name,currency_id')->where(['is_otc' => 1])->select();
        $this->assign('currency', $currency);
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }

    // 撤销订单
    public function cancel()
    {
        $orders_id = intval(I('orders_id'));
        $ordersInfo = Db::name('Orders_otc')->where(['orders_id' => $orders_id])->find();
        if (empty($ordersInfo)) $this->ajaxReturn("", "订单不存在", 0);

        //管理员撤销没有5小时限制
        //$limit = time() - $this->config['otc_cancel_limit'];
        //if($ordersInfo['add_time']>$limit) self::output(0, L('lan_order_otc_limit_time_delete'));

        //有未完成订单
        $count = Db::name('trade_otc')->where(['sell_orders' => $orders_id, 'type' => 'buy', 'status' => ['lt', 3]])->count();
        if ($count > 0) $this->ajaxReturn('', "有交易訂單,無法撤銷！", 0);

        Db::startTrans();
        try {
            //撤销广告直接扣除手续费
            if ($ordersInfo['type'] == 'sell') {
                $avail = $ordersInfo['avail_num'];

                //5小时内手续费 0.1%
                $limit = time() - $this->config['otc_cancel_limit'];
                $fee = 0;
                if ($ordersInfo['add_time'] > $limit) {
                    $cancel_fee = Db::name('currency')->where(['currency_id' => $ordersInfo['currency_id']])->value('currency_otc_cancel_fee');
                    if ($cancel_fee > 0) {
                        $cancel_fee = $cancel_fee / 100;
                        if ($cancel_fee > $ordersInfo['fee']) $cancel_fee = $ordersInfo['fee'];
                        $fee = keepPoint($avail * $cancel_fee, 6);
                    }
                }

                if ($fee > 0) {
                    $fee_ab = model('AccountBook')->addLog([
                        'member_id' => $ordersInfo['member_id'],
                        'currency_id' => $ordersInfo['currency_id'],
                        'type' => 33,
                        'content' => 'lan_otc_cancels_advertising_fee',
                        'number_type' => 2,
                        'number' => $fee,
                        'fee' => 0,
                        'to_member_id' => 0,
                        'to_currency_id' => 0,
                        'third_id' => $orders_id,
                    ]);
                    if (!$fee_ab) {
                        Db::rollback();
                        $this->ajaxReturn("", "添加手续费帐本失败", 0);
                    }
                    $flag = Db::name('currency_user')->where(['member_id' => $ordersInfo['member_id'], 'currency_id' => $ordersInfo['currency_id']])->setDec('forzen_num', $fee);
                    if (!$flag) {
                        Db::rollback();
                        $this->ajaxReturn("", "扣除手续费资金失败", 0);
                    }

                    $result = $this->addFinance($ordersInfo['member_id'], 23, 'OTC撤銷廣告手续费', $fee, 2, $ordersInfo['currency_id'], $ordersInfo['orders_id']);
                    if ($result === false) {
                        Db::rollback();
                        $this->ajaxReturn("", "添加财务记录失败", 0);
                    }
                }

                //返还手续费
                $fee_back = keepPoint($avail * $ordersInfo['fee'] - $fee, 6);
                //返还未成交数量
                $avail = keepPoint($avail + $fee_back, 6);

                if ($avail > 0) {
                    //添加账本
                    $result = model('AccountBook')->addLog([
                        'member_id' => $ordersInfo['member_id'],
                        'currency_id' => $ordersInfo['currency_id'],
                        'type' => 10,
                        'content' => 'lan_otc_cancel',
                        'number_type' => 1,
                        'number' => $avail,
                        'fee' => $fee,
                        'to_member_id' => 0,
                        'to_currency_id' => 0,
                        'third_id' => $ordersInfo['orders_id'],
                    ]);
                    if (!$result) {
                        Db::rollback();
                        $this->ajaxReturn("", "添加帐本失败", 0);
                    }

                    $flag = Db::name('currency_user')->where(['member_id' => $ordersInfo['member_id'], 'currency_id' => $ordersInfo['currency_id']])->update([
                        'num' => ['inc', $avail],
                        'forzen_num' => ['dec', $avail],
                    ]);
                    if (!$flag) {
                        Db::rollback();
                        $this->ajaxReturn("", "返回资金失败", 0);
                    }
                }
            }

            $flag = Db::name('Orders_otc')->where(['orders_id' => $ordersInfo['orders_id']])->setField('status', 3);
            if (!$flag) {
                Db::rollback();
                $this->ajaxReturn("", "修改状态失败", 0);
            }

            Db::commit();
            $this->ajaxReturn("", "操作成功", 1);
        } catch (Exception $e) {
            Db::rollback();
            $this->ajaxReturn("", "操作失败" . $e->getMessage(), 0);
        }
    }

    // 挂单详情
    public function order_trade_info()
    {
        $orders_id = I('member_id', '', 'trim');
        $wheres['a.orders_id'] = $orders_id;
        $fields = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $orders_info = Db::name('Orders_otc')
            ->alias('a')
            ->field($fields)
            ->join(config("database.prefix") . "currency b", "a.currency_id = b.currency_id", "LEFT")
            ->join(config("database.prefix") . "member c", "a.member_id = c.member_id", "LEFT")
            ->where($wheres)
            ->order("a.orders_id desc")
            ->find();
        if ($orders_info) {
            $where['a.sell_orders'] = $orders_id;
            $where["a.type"] = "buy";
            $field = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";

            $list = Db::name('Trade_otc')
                ->alias('a')
                ->field($field)
                ->join(config("database.prefix") . "currency b", "a.currency_id = b.currency_id", "LEFT")
                ->join(config("database.prefix") . "member c", "a.member_id = c.member_id", "LEFT")
                ->where($where)
                ->order("a.trade_id desc")
                ->paginate(15, null, ['query' => input()])->each(function ($item, $key) {
                    $item['type_payment'] = "";
                    $item['type_sell_payment'] = "";
                    $item['type_buy_payment'] = "";
                    if ($item['money_type']) {
                        list($type, $type_id) = explode(":", $item['money_type']);

                        switch ($type) {
                            case 'bank':
                                $table = "member_bank";
                                $getField = "bankcard";
                                $payment_name = "银行卡";
                                break;
                            case 'wechat':
                                $table = "member_wechat";
                                $getField = "wechat";
                                $payment_name = "微信";
                                break;
                            case 'alipay':
                                $table = "member_alipay";
                                $getField = "alipay";
                                $payment_name = "支付宝";
                                break;
                        }

                        if (isset($table) && !empty($table)) {
                            $item['type_payment'] = $payment_name;
                            $item['type_sell_payment'] = Db::name($table)->where(['id' => $type_id, 'status' => 1])->value($getField);
                            $item['type_buy_payment'] = Db::name($table)->where(['member_id' => $item['member_id'], 'status' => 1])->value($getField);
                        }
                    }
                    $item['type_name'] = getOrdersType($item['type']);
                    return $item;
                });
            $show = $list->render();
            $orders_info['type_name'] = isset($orders_info['type_name']) ? $orders_info['type_name'] : "";
            $this->assign('orders_info', $orders_info);
            $this->assign('list', $list);
            $this->assign('page', $show);// 赋值分页输出
            $this->assign("empty", " <tr><td colspan='13'>暂无数据</td></td>");
            return $this->fetch();
        }
    }

    // 交易记录
    public function trade_otc_list()
    {
        $type = I('type');
        $currency_id = I('currency_id');
        $phone = I('phone');
        $member_id = I('member_id');
        $trade_no = I('trade_no');
        $trade_id = I('trade_id');
        $only_number = I('only_number');

        $datePicker = strtotime(I('datePicker'));
        $datePicker2 = strtotime(I('datePicker2'));
        $where = null;
        if (!empty($type)) {
            $where['a.type'] = $type;
        }
        if (!empty($currency_id)) {
            $where['a.currency_id'] = $currency_id;
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
            $where['a.trade_no'] = $trade_no;
        }

        if (!empty($only_number)) {
            $where['a.only_number'] = $only_number;
        }

        if (!empty($datePicker) && !empty($datePicker2)) {
            $where['a.add_time'] = array('between', array($datePicker, $datePicker2));
        }
        if (!empty($member_id)) {
            $where['c.member_id'] = $member_id;
        }

        $status = I('status', '');
        if ($status != '') {
            $status = intval($status);
            $where['a.status'] = $status;
        }
        $field = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";

        //导出数据
        if (I("daochu") == 2) {
            $list = M('Trade_otc')
                ->alias('a')
                ->field($field)
                ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
                ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
                ->where($where)
                ->order(" a.trade_id desc ")
                ->select();
            if ($list) {
                $statusList = ['0' => '未付款', '1' => '待放行', '2' => '申诉中', '3' => '已完成', '4' => '已取消',];
                foreach ($list as $key => $vo) {
                    $list[$key]['type_name'] = getOrdersType($vo['type']);
                    $list[$key]['add_time'] = date("Y-m-d H:i:s", $vo['add_time']);
                    $list[$key]['phone'] = "\t" . (!empty($vo['phone']) ? $vo['phone'] : $vo['email']) . "\t";
                    $list[$key]['status'] = $statusList[$vo['status']];
                    $list[$key]['only_number'] = "\t" . strval($vo['only_number']) . "\t";
                }
            }

            $xlsCell = array(
                array('trade_no', '买卖对'),
                array('only_number', '订单号'),
                array('member_id', '会员ID'),
                array('name', '姓名'),
                array('phone', '账户'),
                array('currency_name', '币种'),
                array('num', '数量'),
                array('price', '单价'),
                array('money', '总价格'),
                array('fee', '手续费'),
                array('type_name', '类型'),
                array('add_time', '添加时间'),
                array('status', '状态'),
            );
            $this->exportExcel("OTC交易记录", $xlsCell, $list);
            die();
        }
        $list = Db::name('Trade_otc')
            ->alias('a')
            ->field($field)
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "member c", "a.member_id = c.member_id", "LEFT")
            ->where($where)
            ->order("a.trade_id desc")
            ->paginate(15, null, ['query' => input()])->each(function ($item, $key) {
                $item['type_name'] = getOrdersType($item['type']);
                return $item;
            });
        $show = $list->render();
        //积分类型
        $currency = Db::name('Currency')->field('currency_name,currency_id')->where(['is_otc' => 1])->select();
        $this->assign('trade_no', $trade_no);
        $this->assign('only_number', $only_number);
        $this->assign('datePicker', I('datePicker'));
        $this->assign('datePicker2', I('datePicker2'));
        $this->assign('phone', $phone);
        $this->assign('member_id', $member_id);
        $this->assign('currency_id', $currency_id);
        $this->assign('status', $status);
        $this->assign('currency', $currency);
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }

    // 交易记录
    public function tradeotc_info()
    {
        $trade_id = I('trade_id');
        $where['trade_id'] = $trade_id;
        //获取挂单记录
        $field = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone,c.nick";

        $trade_info = Db::name('Trade_otc')
            ->alias('a')
            ->field($field)
            ->join(config("database.prefix") . "currency b", "a.currency_id = b.currency_id", "LEFT")
            ->join(config("database.prefix") . "member c", "a.member_id = c.member_id", "LEFT")
            ->where($where)
            ->order("a.trade_id desc")
            ->find();

        $extend_id = '';
        if ($trade_info['type'] == 'buy') {
            $extend_id = 'kd_' . $trade_info['other_trade_id'] . '_' . $trade_info['trade_id'];
            //获取卖家信息
            $sell_trade_info = Db::name('Trade_otc')
                ->alias('a')
                ->field($field)
                ->join(config("database.prefix") . "currency b", "a.currency_id = b.currency_id", "LEFT")
                ->join(config("database.prefix") . "member c", "a.member_id = c.member_id", "LEFT")
                ->where(['trade_id' => $trade_info['other_trade_id']])
                ->order("a.trade_id desc")
                ->find();
            $sell_trade_info['type_name'] = getOrdersType($sell_trade_info['type']);
            $sell_trade_info['add_time'] = date('Y-m-d H:i:s', $sell_trade_info['add_time']);
            $payment = explode(':', $sell_trade_info['money_type']);
            $sell_trade_info['sell_payment'] = "";
            if ($payment[0] == 'bank') {
                $model = Db::name('member_bank');
                $re = $model->field('truename,bankname,bankadd,bankcard')->where(['id' => $payment[1]])->find();
                $sell_trade_info['payment_type'] = '银行卡';
                $sell_trade_info['sell_payment'] = $re['bankname'] . $re['bankadd'] . $re['bankcard'];
            } else if ($payment[0] == 'wechat') {
                $model = Db::name('member_wechat');
                $re = $model->field('truename,wechat')->where(['id' => $payment[1]])->find();
                $sell_trade_info['payment_type'] = '微信';
                $sell_trade_info['sell_payment'] = $re['wechat'];
            } else {
                $model = Db::name('member_alipay');
                $re = $model->field('truename,alipay')->where(['id' => $payment[1]])->find();
                $sell_trade_info['payment_type'] = '支付宝';
                $sell_trade_info['sell_payment'] = $re['alipay'];
            }

            //获取买家信息
            $buy_trade_info = $trade_info;
            $buy_trade_info['type_name'] = getOrdersType($buy_trade_info['type']);
            $buy_trade_info['add_time'] = date('Y-m-d H:i:s', $buy_trade_info['add_time']);
            $buy_trade_info['buy_payment'] = $sell_trade_info['sell_payment'];

        } else {
            $extend_id = 'kd_' . $trade_info['trade_id'] . '_' . $trade_info['other_trade_id'];
            //获取卖家信息
            $sell_trade_info = $trade_info;
            $sell_trade_info['type_name'] = getOrdersType($sell_trade_info['type']);
            $sell_trade_info['add_time'] = date('Y-m-d H:i:s', $sell_trade_info['add_time']);
            $payment = explode(':', $sell_trade_info['money_type']);
            $sell_trade_info['sell_payment'] = "";
            if ($payment[0] == 'bank') {
                $model = Db::name('member_bank');
                $re = $model->field('truename,bankname,bankadd,bankcard')->where(['id' => $payment[1]])->find();
                $sell_trade_info['payment_type'] = '银行卡';
                $sell_trade_info['sell_payment'] = $re['bankname'] . $re['bankadd'] . $re['bankcard'];
            } else if ($payment[0] == 'wechat') {
                $model = Db::name('member_wechat');
                $re = $model->field('truename,wechat')->where(['id' => $payment[1]])->find();
                $sell_trade_info['payment_type'] = '微信';
                $sell_trade_info['sell_payment'] = $re['wechat'];
            } else {
                $model = Db::name('member_alipay');

                $re = $model->field('truename,alipay')->where(['id' => $payment[1]])->find();
                $sell_trade_info['payment_type'] = '支付宝';
                $sell_trade_info['sell_payment'] = $re['alipay'];
            }
            //获取买家信息
            $buy_trade_info = Db::name('Trade_otc')
                ->alias('a')
                ->field($field)
                ->join(config("database.prefix") . "currency b", "a.currency_id = b.currency_id", "LEFT")
                ->join(config("database.prefix") . "member c", "a.member_id = c.member_id", "LEFT")
                ->where(['trade_id' => $trade_info['other_trade_id']])
                ->order("a.trade_id desc")
                ->find();
            $buy_trade_info['type_name'] = getOrdersType($buy_trade_info['type']);
            $buy_trade_info['add_time'] = date('Y-m-d H:i:s', $buy_trade_info['add_time']);
            $buy_trade_info['buy_payment'] = $sell_trade_info['sell_payment'];
        }

        //获取聊天记录
        $message = Db::name('im')->where(['msg_extend' => $extend_id])->select();
        foreach ($message as $k => $v) {
            if ($v['msg_push_type'] == 0) {
                if (strpos($v['msg_content'], 'http') !== false) {
                    $message[$k]['msg_content'] = '<img src="' . $v['msg_content'] . '" alt="" style="width:100px;height:100px">';
                }
                $message[$k]['msg_time'] = $v['msg_time'] / 1000;
                $message[$k]['nick'] = Db::name('member')->field('nick')->where(['member_id' => $v['across_id']])->find()['nick'];
            } else {
                unset($message[$k]);
            }
        }
        $buy_trade_info['sell_payment'] = isset($buy_trade_info['sell_payment']) ? $buy_trade_info['sell_payment'] : "";
        $this->assign('buy_trade_info', $buy_trade_info);
        $this->assign('sell_trade_info', $sell_trade_info);
        $this->assign('message', $message);


        return $this->fetch();
    }

    //处理申诉
    public function appeal()
    {
        $trade_id = intval(I('trade_id'));
        if (empty($trade_id)) $this->ajaxReturn("", "记录不存在", 0);

        $tradeInfo = Db::name('Trade_otc')->where(['trade_id' => $trade_id])->find();
        if (empty($tradeInfo)) $this->ajaxReturn("", "记录不存在", 0);

        if ($tradeInfo['status'] != 2) $this->ajaxReturn("", "非申诉记录", 0);

        $other_tradeInfo = Db::name('Trade_otc')->where(['trade_id' => $tradeInfo['other_trade_id']])->find();
        if (empty($tradeInfo)) $this->ajaxReturn("", "数据异常,被诉方不存在", 0);

        $result = intval(I('result'));
        if ($result) {
            if ($tradeInfo['type'] == 'buy') {
                //买方胜
                $this->buyWin($tradeInfo, $other_tradeInfo);
            } else {
                //卖方胜
                $this->sellWin($other_tradeInfo, $tradeInfo);
            }
        } else {
            if ($tradeInfo['type'] == 'buy') {
                //卖方败
                $this->sellWin($tradeInfo, $other_tradeInfo);
            } else {
                //卖方败
                $this->buyWin($other_tradeInfo, $tradeInfo);
            }
        }

        $this->ajaxReturn("", "操作成功", 1);
    }

    /**
     * 添加财务日志方法
     * @param unknown $member_id
     * @param unknown $type
     * @param unknown $content
     * @param unknown $money
     * @param unknown $money_type 收入=1/支出=2
     * @param unknown $currency_id 积分类型id 0是rmb
     * @return
     */
    public function addFinance($member_id, $type, $content, $money, $money_type, $currency_id)
    {
        $data['member_id'] = $member_id;
        $data['type'] = $type;
        $data['content'] = $content;
        $data['money_type'] = $money_type;
        $data['money'] = $money;
        $data['add_time'] = time();
        $data['currency_id'] = $currency_id;
        $data['ip'] = get_client_ip_extend();
        $list = Db::name('Finance')->insertGetId($data);
        if ($list) {
            return $list;
        } else {
            return false;
        }
    }

    //买家胜诉 即已付款,直接放行 卖家增加败诉记录
    private function buyWin($buyTrade, $sellTrade)
    {
        Db::startTrans();
        try {
            $time = time();
            $flag = Db::name('Trade_otc')->where(['trade_id' => $buyTrade['trade_id'], 'status' => 2])->update([
                'status' => 3,
                'allege_status' => 1,
                'update_time' => $time,
            ]);
            if (!$flag) {
                Db::rollback();
                $this->ajaxReturn("", "修改买家状态失败", 0);
            }

            $flag = Db::name('Trade_otc')->where(['trade_id' => $sellTrade['trade_id'], 'status' => 2])->update([
                'status' => 3,
                'allege_status' => 0,
                'update_time' => $time,
            ]);
            if (!$flag) {
                Db::rollback();
                $this->ajaxReturn("", "修改卖家状态失败", 0);
            }

            if ($sellTrade['fee'] > 0) {
                $result = $this->addFinance($sellTrade['member_id'], 24, 'OTC交易手续费', $sellTrade['fee'], 2, $sellTrade['currency_id'], $sellTrade['trade_id']);
                if ($result === false) {
                    Db::rollback();
                    $this->ajaxReturn("", "添加财务记录失败", 0);
                }
            }

            //减少数量及手续费
            $num = $sellTrade['num'] + $sellTrade['fee'];
            $flag = Db::name('currency_user')->where(['member_id' => $sellTrade['member_id'], 'currency_id' => $sellTrade['currency_id']])->setDec('forzen_num', $num);
            if (!$flag) {
                Db::rollback();
                $this->ajaxReturn("", "扣除卖家冻结资产失败", 0);
            }


            if ($buyTrade['fee'] > 0) {
                $result = $this->addFinance($buyTrade['member_id'], 24, 'OTC交易手续费', $buyTrade['fee'], 2, $buyTrade['currency_id'], $buyTrade['trade_id']);
                if ($result === false) {
                    Db::rollback();
                    $this->ajaxReturn("", "添加财务记录失败", 0);
                }
            }

            //买家加币,后扣费 减去手续费
            $num = $buyTrade['num'] - $buyTrade['fee'];
            $flag = model('AccountBook')->addLog([
                'member_id' => $buyTrade['member_id'],
                'currency_id' => $buyTrade['currency_id'],
                'type' => 9,
                'content' => 'lan_otc_buy',
                'number_type' => 1,
                'number' => $num,
                'fee' => $buyTrade['fee'],
                'to_member_id' => $sellTrade['member_id'],
                'to_currency_id' => $buyTrade['currency_id'],
                'third_id' => $buyTrade['trade_id'],
                'add_time' => time(),
            ]);
            if (!$flag) {
                Db::rollback();
                $this->ajaxReturn("", "添加帐本记录失败", 0);
            }

            $flag = Db::name('currency_user')->where(['member_id' => $buyTrade['member_id'], 'currency_id' => $buyTrade['currency_id']])->setInc('num', $num);
            if (!$flag) {
                Db::rollback();
                $this->ajaxReturn("", "买家资产增加失败", 0);
            }


            $flag = Db::name('member')->where(['member_id' => $sellTrade['member_id']])->update([
                'trade_allnum' => Db::raw('trade_allnum+1'),
                'fail_allnum' => Db::raw('fail_allnum+1'),
            ]);

            if (!$flag) {
                Db::rollback();
                $this->ajaxReturn("", "修改用户数据失败", 0);
            }

            Db::commit();
            $this->ajaxReturn("", "操作成功", 1);
        } catch (Exception $e) {
            Db::rollback();
            $this->ajaxReturn("", "操作失败"/*. $e->getMessage() . '---' . $e->getLine() . '---' . $e->getCode() . '---' . $e->getFile()*/, 0);
        }
    }

    //卖家胜诉 即未付款 直接取消订单
    private function sellWin($buyTrade, $sellTrade)
    {
        Db::startTrans();
        try {
            $order_otc = Db::name('orders_otc')->where(['orders_id' => $buyTrade['sell_orders']])->find();
            if (!$order_otc) {
                Db::rollback();
                $this->ajaxReturn("", "广告不存在", 0);
            }

            if ($order_otc['type'] == 'buy') {
                $other_tradeInfo = $sellTrade;

                //如果是买单 要返回给卖家资产
                $all_num = keepPoint($other_tradeInfo['num'] + $other_tradeInfo['fee'], 6);
                $result = model('AccountBook')->addLog([
                    'member_id' => $other_tradeInfo['member_id'],
                    'currency_id' => $other_tradeInfo['currency_id'],
                    'type' => 9,
                    'content' => 'lan_otc_sell_to_buy_cancel',
                    'number_type' => 1,
                    'number' => $all_num,
                    'fee' => $other_tradeInfo['fee'],
                    'to_member_id' => 0,
                    'to_currency_id' => 0,
                    'third_id' => $other_tradeInfo['trade_id'],
                ]);
                if (!$result) {
                    Db::rollback();
                    $this->ajaxReturn("", "卖家资产返还错误", 0);
                }

                $flag = Db::name('currency_user')->where(['member_id' => $other_tradeInfo['member_id'], 'currency_id' => $other_tradeInfo['currency_id']])->update([
                    'num' => ['inc', $all_num],
                    'forzen_num' => ['dec', $all_num],
                ]);
                if (!$flag) {
                    Db::rollback();
                    $this->ajaxReturn("", "卖家资产返还错误2", 0);
                }
            }

            $time = time();
            $flag = Db::name('Trade_otc')->where(['trade_id' => $buyTrade['trade_id'], 'status' => 2])->update([
                'status' => 4,
                'allege_status' => 0,
                'update_time' => $time,
            ]);
            if (!$flag) {
                Db::rollback();
                $this->ajaxReturn("", "修改买家状态失败", 0);
            }

            $flag = Db::name('Trade_otc')->where(['trade_id' => $sellTrade['trade_id'], 'status' => 2])->update([
                'status' => 4,
                'allege_status' => 1,
                'update_time' => $time,
            ]);
            if (!$flag) {
                Db::rollback();
                $this->ajaxReturn("", "修改卖家状态失败", 0);
            }

            //减去已交易量
            $flag = Db::name('orders_otc')->where(['orders_id' => $buyTrade['sell_orders']])->setInc('avail_num', $buyTrade['num']);
            if (!$flag) {
                Db::rollback();
                $this->ajaxReturn("", "修改交易量失败", 0);
            }

            $flag = Db::name('member')->where(['member_id' => $sellTrade['member_id']])->setInc('appeal_succnum');
            if (!$flag) {
                Db::rollback();
                $this->ajaxReturn("", "修改数据失败", 0);
            }

            Db::commit();
            $this->ajaxReturn("", "操作成功", 1);
        } catch (Exception $e) {
            Db::rollback();
            $this->ajaxReturn("", "操作失败" . $e->getMessage(), 0);
        }
    }

    /**
     * @Desc:会员支付方式查询
     * @return array
     * @Date: 2019/1/2 0002 17:53
     * @author: Administrator
     */
    public function user_pay()
    {
        $w = [];
        $user_id = I('user_id', '', 'trim');
        $user_no = I('user_no', '', 'trim');
        empty($user_id) ?: $w['m.member_id'] = $user_id;
        empty($user_no) ?: $w['m.email|m.phone'] = $user_no;
        $field = "m.member_id,m.nick,m.name,m.phone,m.email,mw.wechat,mw.status as w_status,mw.wechat_pic,a.alipay,a.status as a_status,a.alipay_pic,mb.bankcard,mb.status as b_status,bl.name as bankname,mb.bankadd";

        $list = Db::name("member")->alias('m')
            ->join(config("database.prefix") . "member_alipay a", "m.member_id=a.member_id", "LEFT")
            ->join(config("database.prefix") . "member_wechat mw", "m.member_id=mw.member_id", "LEFT")
            ->join(config("database.prefix") . "member_bank mb", "m.member_id=mb.member_id", "LEFT")
            ->join(config("database.prefix") . "banklist bl", "bl.id = mb.bankname", "LEFT")
            ->where($w)
            ->group('m.member_id')
            ->field($field)
            ->order('m.member_id desc')
            ->paginate(15, null, ['query' => input()]);
        $show = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $show);
        return $this->fetch();
    }

    public function user_pay_info()
    {
        $w = null;
        $user_id = I('member_id', '', 'trim');
        empty($user_id) ?: $w['m.member_id'] = $user_id;
        $field = "m.member_id,m.nick,m.name,m.phone,m.email,wechat.wechat,wechat.wechat_pic,alipay.alipay,alipay.alipay_pic,bank.bankcard,b2.name as bankname,bank.bankadd,wechat.status as w_status,alipay.status as a_status,bank.status as b_status";
        $list = model("member")->alias('m')
            ->join(config("database.prefix") . "member_alipay alipay", "m.member_id=alipay.member_id", "LEFT")
            ->join(config("database.prefix") . "member_wechat wechat", "m.member_id=wechat.member_id", "LEFT")
            ->join(config("database.prefix") . "member_bank bank", "m.member_id=bank.member_id", "LEFT")
            ->join(config("database.prefix") . "banklist b2", "b2.id = bank.bankname", "LEFT")
            ->where($w)
            ->field($field)->select();

        $info = [];
        foreach ($list as $v) {
            if (isset($info[$v['member_id']])) {
                $info[$v['member_id']]['member_id'] = $v['member_id'];
                $info[$v['member_id']]['name'] = !empty($v['name']) ? $v['name'] : $v['nick'];
                $info[$v['member_id']]['phone'] = !empty($v['phone']) ? $v['phone'] : $v['email'];
                $info[$v['member_id']]['wechat'] = array_unique(array_merge_recursive($info[$v['member_id']]['wechat'], [[$v['wechat'], $v['wechat_pic'], $v['w_status']]]), SORT_REGULAR);
                $info[$v['member_id']]['alipay'] = array_unique(array_merge_recursive($info[$v['member_id']]['alipay'], [[$v['alipay'], $v['alipay_pic'], $v['a_status']]]), SORT_REGULAR);
                $info[$v['member_id']]['bank'] = array_unique(array_merge_recursive($info[$v['member_id']]['bank'], [[$v['bankcard'], $v['bankname'] . $v['bankadd'], $v['b_status']]]), SORT_REGULAR);
            } else {
                $info[$v['member_id']]['member_id'] = $v['member_id'];
                $info[$v['member_id']]['name'] = !empty($v['name']) ? $v['name'] : $v['nick'];
                $info[$v['member_id']]['phone'] = !empty($v['phone']) ? $v['phone'] : $v['email'];
                $info[$v['member_id']]['wechat'][] = [$v['wechat'], $v['wechat_pic'], $v['w_status']];
                $info[$v['member_id']]['alipay'][] = [$v['alipay'], $v['alipay_pic'], $v['a_status']];
                $info[$v['member_id']]['bank'][] = [$v['bankcard'], $v['bankname'] . $v['bankadd'], $v['b_status']];

            }
        }
        $this->assign('info', $info[$user_id]);
        return $this->fetch();
    }
}