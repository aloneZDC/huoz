<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Author: wkp
 * Date: 2018/2/3
 * Time: 15:10
 */

namespace Admin\Controller;

use Admin\Controller\AdminController;
use Think\Exception;
use Think\Page;

class ConsumerToConsumerController extends AdminController
{
    public function _initialize()
    {
        parent::_initialize();
    }

    //空操作
    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    /**
     * 币参数设置
     */
    public function index()
    {
        $consumer_to_consumer = D('C2cCoinConfig');
        $coin_config = $consumer_to_consumer->getAllCoinConfig();
        $this->assign('coin_config', $coin_config);
        $this->display();
    }

    /**
     * 新增或修改币参数
     */
    public function addCoin()
    {
        $consumer_to_consumer = D('C2cCoinConfig');
        if (IS_POST) {
            $id = I('post.id');
            $data = I('post.');
            $data['award_current_id'] = $data['award_current_id']?:0;
            $data['award_ratio'] = $data['award_ratio']?:0;
            $data['award_start_time'] = strtotime($data['award_start_time'])?:0;
            $data['award_end_time'] = strtotime($data['award_end_time'])?:0;
            if ($r = $consumer_to_consumer->create()) {
                if (!$id) {
                    if ($consumer_to_consumer->add($data)) {
                        $this->success('添加成功', U('ConsumerToConsumer/index'));
                    } else {
                        $this->error('操作失败');
                    }
                } else {
                    if ($consumer_to_consumer->save($data)) {
                        $this->success('修改成功', U('ConsumerToConsumer/index'));
                    } else {
                        $this->error('操作失败');
                    }
                }
            } else {
                $this->error($consumer_to_consumer->getError());
            }
        } else {
            $id = I('get.id');
            $currency = M('Currency')->field('currency_id,currency_name,currency_mark')->select();
            $this->assign('currency', $currency);
            if ($id) {
                $coin_config = $consumer_to_consumer->getCoinConfig(array('id' => $id));
                $this->assign('coin_config', $coin_config);
            }
            $this->display();
        }
    }

    /**
     * 删除币参数
     * @param $id
     */
    public function delCoin($id)
    {
        $condition = array('id' => $id);
        $c2c_config = M('C2cCoinConfig')->where($condition)->find();
        if (M('c2c_order')->where(array('currency_id' => $c2c_config['currency_id']))->count()) {
            $this->ajaxReturn(array('msg' => '操作失败,此积分下有订单不能删除', 'code' => 0));
        }
        if ($c2c_config) {
            if (M('C2cCoinConfig')->where($condition)->delete()) {
                $this->ajaxReturn(array('msg' => '删除成功', 'code' => 1));
            } else {
                $this->ajaxReturn(array('msg' => '操作失败', 'code' => 0));
            }
        } else {
            $this->ajaxReturn(array('msg' => '信息不存在', 'code' => 0));
        }
    }

    /**
     * 银行列表
     */
    public function bankList()
    {
        $count = M('Banklist')->count();
        $Page = new Page($count, 15);
        $bank_list = M('Banklist')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $show = $Page->show();// 分页显示输出
        $this->assign('bank_list', $bank_list);
        $this->assign('page', $show);
        $this->display();
    }

    /**
     * 添加修改银行
     */
    public function addBank()
    {
        $model = D('Banklist');
        if (IS_POST) {
            $id = I('post.id');
            /*if ($_FILES["logo"]["tmp_name"]) {
                $_POST['logo'] = $this->upload($_FILES["logo"]);
                if (!$_POST['logo']) {
                    $this->error('非法上传');
                }
            }*/
            if ($r = $model->create()) {
                if (!$id) {
                    if ($model->add()) {
                        $this->success('添加成功', U('ConsumerToConsumer/bankList'));
                    } else {
                        $this->error('操作失败');
                    }
                } else {
                    if ($model->save()) {
                        $this->success('修改成功', U('ConsumerToConsumer/bankList'));
                    } else {
                        $this->error('操作失败');
                    }
                }
            } else {
                $this->error($model->getError());
            }
        } else {
            $id = I('get.id');
            if ($id) {
                $bank_list = $model->getBanklist(array('id' => $id));
                $this->assign('bank_list', $bank_list);
            }
            $this->display();
        }
    }

    /**
     * 删除银行
     * @param $id
     */
    public function delBank($id)
    {
        $condition = array('id' => $id);
        if (M('member_bank')->where(array('bankname' => $id))->count()) {
            $this->ajaxReturn(array('msg' => '操作失败,此银行下有用户不能删除', 'code' => 0));
        }
        if (M('admin_bank')->where(array('bankname' => $id))->count()) {
            $this->ajaxReturn(array('msg' => '操作失败,此银行下有用户不能删除', 'code' => 0));
        }
        if (M('Banklist')->where($condition)->count()) {
            if (M('Banklist')->where($condition)->delete()) {
                $this->ajaxReturn(array('msg' => '删除成功', 'code' => 1));
            } else {
                $this->ajaxReturn(array('msg' => '操作失败', 'code' => 0));
            }
        } else {
            $this->ajaxReturn(array('msg' => '信息不存在', 'code' => 0));
        }
    }

    /**
     * 订单列表
     */
    public function orderList()
    {
        $type = I('type','','intval');
        $order_sn = I('order_sn');
        $member_id = I('member_id', '', 'intval');
        $status = I('status', '', 'intval');
        if ($type && $type != -1) {
            $where['o.type'] = $type;
        }
        if (is_numeric($status) && $status != -1) {
            $where['o.status'] = $status;
        }
        if (!empty($order_sn)) {
            $where['o.order_sn'] = array('like', '%' . $order_sn . '%');
        }
        if (!empty($member_id)) {
            $where['o.member_id'] = $member_id;
        }
        $count = M('c2c_order')->alias('o')->where($where)->count();// 查询满足要求的总记录数
        $Page = new Page($count, 15);// 实例化分页类 传入总记录数和每页显示的记录数(25)

        //给分页传参数
        setPageParameter($Page, array('type' => $type, 'order_sn' => $order_sn, 'member_id' => $member_id, 'status' => $status));

        $show = $Page->show();// 分页显示输出

        $field = "o.*,c.currency_mark";
        $order = M('c2c_order')->alias('o')
            ->join("left join " . C("DB_PREFIX") . "currency as c on c.currency_id = o.currency_id")
            ->field($field)->where($where)->order("o.add_time desc ")
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $status = array('0' => '待处理', '1' => '收款成功', '2' => '打款成功', '-1' => '已取消');
        foreach ($order as &$val) {
            $val['admin_time'] = !empty($val['admin_time']) ? $val['admin_time'] : '';
            $val['_status'] = $status[$val['status']];
            $val['type'] = $val['type'] == 1 ? '买入' : '卖出';
        }

        $this->assign('order_list', $order);
        $this->assign('page', $show);// 赋值分页输出
        $this->display();
    }

    /**
     * 订单导出
     */
    public function exportOrder()
    {
        $type = I('type','','intval');
        $order_sn = I('order_sn','','intval');
        $member_id = I('member_id', '', 'intval');
        $status = I('status', '', 'intval');

        if ($type && $type != -1) {
            $where['o.type'] = $type;
        }
        if (is_numeric($status) && $status != -1) {
            $where['o.status'] = $status;
        }
        if (!empty($order_sn) && $order_sn != -1) {
            $where['o.order_sn'] = array('like', '%' . $order_sn . '%');
        }
        if (!empty($member_id) && $member_id != -1) {
            $where['o.member_id'] = $member_id;
        }
        $field = "o.id,o.order_sn,o.member_id,m.email,m.phone,c.currency_mark,o.number,o.price,o.money,o.type,o.status,o.add_time,o.pay_type";
        $order = M('c2c_order')->alias('o')
            ->join("left join " . C("DB_PREFIX") . "currency as c on c.currency_id = o.currency_id")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = o.member_id")
            ->field($field)->where($where)->order("o.add_time desc ")
            ->select();
        $status = array('0' => '待处理', '1' => '收款成功', '2' => '打款成功', '-1' => '已取消');
        $p_type = array('1' => '银行卡', '2' => '支付宝', '3' => '微信');
        foreach ($order as $key=>$val) {
            $order[$key]['status'] = $status[$val['status']];
            $order[$key]['type'] = $val['type'] == 1 ? '买入' : '卖出';
            $order[$key]['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
            $order[$key]['pay_type'] = $p_type[$val['pay_type']];
        }

        $xlsName  = "Order";
        $xlsCell  = array(
            array('id','编号'),
            array('order_sn','订单号'),
            array('member_id','会员ID'),
            array('email','邮箱'),
            array('phone','手机号'),
            array('currency_mark','积分类型'),
            array('number','数量'),
            array('price','单价'),
            array('money','总金额'),
            array('type','类型'),
            array('status','状态'),
            array('add_time','添加时间'),
            array('pay_type','支付方式'),
        );
        $xlsData  = $order;
        $this->exportExcel($xlsName,$xlsCell,$xlsData);
    }

    /**
     * 订单详情，订单处理
     */
    public function orderDetail()
    {
        if (IS_POST) {
            if (I('post.status') > 0) {
                $id = I('post.id', '', 'intval');
                $order = M('c2c_order')->where(array('id' => $id))->find();
                if ($order) {
                    $currency_id = $order['currency_id'];
                    $member_id = $order['member_id'];
                    $number = $order['number'];
                    $type = I('post.type');
                    try {
                        M()->startTrans();
                        $update_data = array(
                            'status' => I('post.status'),
                            'id' => I('post.id'),
                            'admin_time' => time(),
                        );
                        $up_order = M('c2c_order')->save($update_data);//更新到订单
                        if (!$up_order) {
                            throw new Exception('操作失败');
                        }
                        $currency_user = M('currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->find();
                        if ($type == 1) {//买
                            D('CurrencyUserStream')->addStream($member_id, $currency_id, 1, $number, 1, 41, 0, '买入C2C');

                            $update_currency = false;
                            if(!$currency_user) {
                                $update_currency = M('currency_user')->add([
                                    'member_id' => $member_id,
                                    'currency_id' => $currency_id,
                                    'num'=> $number,
                                ]);
                            } else {
                                //更新用户资金
                                $update_currency = M('currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setInc('num',$number);
                            }
                            //更新用户资金
                            if($update_currency){
                                // 买入成功，赠送奖励积分
                                if(!$this->awardCurrency($order)){
                                    throw new Exception('赠送奖励失败');
                                }
                            }
                        } elseif ($type == 2) {//卖
                            $update_data2 = array(
                                'forzen_num' => $currency_user['forzen_num'] - $number,
                            );

                            D('CurrencyUserStream')->addStream($member_id, $currency_id, 2, $number, 2, 41, 0, '卖出C2C');
                            $update_currency = M('currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->save($update_data2);//更新用户资金
                        }
                        if (!$update_currency) {
                            throw new Exception('操作失败');
                        }
                        M()->commit();
                        $this->success('提交成功', U('ConsumerToConsumer/orderList'));
                    } catch (Exception $e) {
                        M()->rollback();
                        $this->error($e->getMessage());
                    }

                }
            }
        } else {
            $id = I('get.id');
            $order = M('c2c_order')->alias('o')
                ->join("left join " . C("DB_PREFIX") . "currency as c on c.currency_id = o.currency_id")
                ->field("o.*,c.currency_mark")->where(array('id' => $id))->find();
            if ($order['type'] == 1) {//买
                switch ($order['pay_type']) {
                    case 1:
                        $pay = M('admin_bank')->alias('b1')
                            ->field('b1.*,b2.name')
                            ->join(('yang_banklist b2 on b1.bankname = b2.id'))
                            ->where(array('b1.id' => $order['pay_id']))->find();
                        break;
                    case 2:
                        $pay = M('admin_payment')->where(array('id' => $order['pay_id']))->find();
                        $pay['alipay'] = $pay['username'];
                        $pay['alipay_pic'] = $pay['qrcode'];
                        break;
                    case 3:
                        $pay = M('admin_payment')->where(array('id' => $order['pay_id']))->find();
                        $pay['wechat'] = $pay['username'];
                        $pay['wechat_pic'] = $pay['qrcode'];
                        break;
                }
            } elseif ($order['type'] == 2) {//卖
                switch ($order['pay_type']) {
                    case 1:
                        $pay = M('member_bank')->alias('b1')
                            ->field('b1.*,b2.name')
                            ->join(('yang_banklist b2 on b1.bankname = b2.id'))
                            ->where(array('b1.id' => $order['pay_id']))->find();
                        break;
                    case 2:
                        $pay = M('member_alipay')->where(array('id' => $order['pay_id']))->find();
                        break;
                    case 3:
                        $pay = M('member_wechat')->where(array('id' => $order['pay_id']))->find();
                        break;
                }
            }
            $order['pay_info'] = $pay;
            $this->assign('order', $order);
            $this->display();
        }

    }

    /**
     * 取消订单
     */
    public function cancelOrder()
    {
        $id = I('post.id', 'intval');
        $order = M('c2c_order')->where(array('id' => $id))->find();
        if ($order) {
            $currency_id = $order['currency_id'];
            $member_id = $order['member_id'];
            $number = $order['number'];
            $type = $order['type'];
            if($order['status']==-1){
                $this->ajaxReturn(array('status' => 0, 'info' => '已撤销'));
            }
            try {
                M()->startTrans();
                $update_data = array(
                    'status' => -1,
                    'id' => $id,
                    'admin_time' => time(),
                );
                $up_order = M('c2c_order')->save($update_data);//更新到订单
                if (!$up_order) {
                    throw new Exception('操作失败');
                }
                $currency_user = M('currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->find();
                if ($type == 1) {//买
                    $update_currency = true;
                } elseif ($type == 2) {//卖
                    $update_data2 = array(
                        'forzen_num' => $currency_user['forzen_num'] - $number,
                        'num' => $currency_user['num'] + $number,
                    );
                    
                    D('CurrencyUserStream')->addStream($member_id, $currency_id, 1, $number, 1, 41, 0, 'C2C取消订单-扣除冻结');
                    D('CurrencyUserStream')->addStream($member_id, $currency_id, 2, $number, 2, 41, 0, 'C2C取消订单-返还可用');
                    $update_currency = M('currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->save($update_data2);//更新用户资金
                }
                if (!$update_currency) {
                    throw new Exception('操作失败');
                }
                M()->commit();
                $this->ajaxReturn(array('status' => 1, 'info' => '取消成功'));
            } catch (Exception $e) {
                M()->rollback();
                $this->ajaxReturn(array('status' => 0, 'info' => $e->getMessage()));
            }
        }
    }

    /**
     * 银行卡列表
     */
    public function bankcardList()
    {
        $count = M('AdminBank')->count();
        $Page = new Page($count, 15);
        $bank_list = M('AdminBank')->alias('b1')
            ->field("b1.*,b2.name")
            ->join("yang_banklist b2 on b2.id = b1.bankname")
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $show = $Page->show();// 分页显示输出
        $this->assign('bank_list', $bank_list);
        $this->assign('page', $show);
        $this->display();
    }

    /**
     * 添加银行卡
     */
    public function addBankCard()
    {
        $admin_bank = D('AdminBank');
        if (IS_POST) {
            $id = I('post.id');
            $_POST['add_time'] = time();
            if ($r = $admin_bank->create()) {
                if (!$id) {
                    if ($admin_bank->add()) {
                        $this->success('添加成功', U('ConsumerToConsumer/bankcardList'));
                    } else {
                        $this->error('操作失败');
                    }
                } else {
                    if ($admin_bank->save()) {
                        $this->success('修改成功', U('ConsumerToConsumer/bankcardList'));
                    } else {
                        $this->error('操作失败');
                    }
                }
            } else {
                $this->error($admin_bank->getError());
            }
        } else {
            $id = I('get.id');
            $banklist = M('banklist')->field('id,name')->select();
            $this->assign('banklist', $banklist);
            if ($id) {
                $bank_card = $admin_bank->getAdminBank(array('id' => $id));
                $this->assign('bank_card', $bank_card);
            }
            $this->display();
        }
    }

    /**
     * 删除银行卡
     * @param $id
     */
    public function delBankCard($id)
    {
        $condition = array('id' => $id);
        if (M('c2c_order')->where(array('pay_type' => 1, 'pay_id' => $id))->count()) {
            $this->ajaxReturn(array('msg' => '操作失败，此银行卡下有订单，不能删除', 'code' => 0));
        }
        if (M('AdminBank')->where($condition)->count()) {
            if (M('AdminBank')->where($condition)->delete()) {
                $this->ajaxReturn(array('msg' => '删除成功', 'code' => 1));
            } else {
                $this->ajaxReturn(array('msg' => '操作失败', 'code' => 0));
            }
        } else {
            $this->ajaxReturn(array('msg' => '信息不存在', 'code' => 0));
        }
    }

    /**
     * 支付方式列表
     */
    public function paymentList()
    {
        $count = M('AdminPayment')->count();
        $Page = new Page($count, 15);
        $payment_list = M('AdminPayment')->alias('b1')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($payment_list as &$val) {
            $val['pay_name'] = $val['type'] == 2 ? '支付宝' : '微信';
        }
        $show = $Page->show();// 分页显示输出
        $this->assign('payment_list', $payment_list);
        $this->assign('page', $show);
        $this->display();
    }

    /**
     * 添加支付方式
     */
    public function addPayment()
    {
        $admin_payment = D('AdminPayment');
        if (IS_POST) {
            $id = I('post.id');
            $_POST['add_time'] = time();
            if ($_FILES["qrcode"]["tmp_name"]) {
                $_POST['qrcode'] = trim($this->oss_upload($_FILES, 'admin/ctrade')['qrcode']);
                if (!$_POST['qrcode']) {
                    $this->error('非法上传');
                }
            }
            if ($r = $admin_payment->create()) {
                if (!$id) {
                    if ($admin_payment->add()) {
                        $this->success('添加成功', U('ConsumerToConsumer/paymentList'));
                    } else {
                        $this->error('操作失败');
                    }
                } else {
                    if ($admin_payment->save()) {
                        $this->success('修改成功', U('ConsumerToConsumer/paymentList'));
                    } else {
                        $this->error('操作失败');
                    }
                }
            } else {
                $this->error($admin_payment->getError());
            }
        } else {
            $id = I('get.id');
            if ($id) {
                $payment = $admin_payment->getAdminPayment(array('id' => $id));
                $this->assign('admin_payment', $payment);
            }
            $this->display();
        }
    }

    /**
     * 删除支付方式
     * @param $id
     */
    public function delPayment($id)
    {
        $condition = array('id' => $id);
        $map['pay_id'] = $id;
        $map['pay_type'] = array('neq', 1);
        if (M('c2c_order')->where($map)->count()) {
            $this->ajaxReturn(array('msg' => '操作失败，此支付方式下有订单，不能删除', 'code' => 0));
        }
        if (M('AdminPayment')->where($condition)->count()) {
            if (M('AdminPayment')->where($condition)->delete()) {
                $this->ajaxReturn(array('msg' => '删除成功', 'code' => 1));
            } else {
                $this->ajaxReturn(array('msg' => '操作失败', 'code' => 0));
            }
        } else {
            $this->ajaxReturn(array('msg' => '信息不存在', 'code' => 0));
        }
    }

    /**
     * 成功买入积分奖励
     * @param $order 订单信息
     * [
     *  member_id
     *  currency_id
     *  number
     *  order_sn
     * ]
     */
    public function awardCurrency($order){
        $res = true;
        $member_id = $order['member_id'];
        $currency_id = $order['currency_id'];
        $num = $order['number'];
        $order_sn = $order['order_sn'];
        $coin_config = D('C2cCoinConfig')->where('currency_id='.$currency_id)->find();
        $time = time();
        // 开启奖励并在有效期内
        if($coin_config && ($coin_config['award_status'] == 1) && ($time>=$coin_config['award_start_time']) && ($time<$coin_config['award_end_time'])){
            // 买入量判断
            if($num>=$coin_config['award_limit_min']){
                $award_num = $num*$coin_config['award_ratio']/100; // 买入量*奖励百分比
                $currency_user = M('currency_user')->where(array('member_id' => $member_id, 'currency_id' => $coin_config['award_currency_id']))->find();
                if(!$currency_user){ // 账户不存在添加
                    $data_add['member_id'] = $member_id;
                    $data_add['currency_id'] = $coin_config['award_currency_id'];
                    $data_add['lock_num'] = $award_num;
                    $data_add['sum_award'] = $award_num;
                    $data_add['forzen_num'] = 0;
                    $data_add['status'] = 0;
                    D('CurrencyUserStream')->addStream($member_id, $coin_config['award_currency_id'], 4, $award_num, 1, 2, 0, '购买KOK赠送KOKcy');
                    $res = M('Currency_user')->add($data_add)>0;
                }else{
                    $data['lock_num'] =$currency_user['lock_num']+$award_num;
                    $data['sum_award'] =$currency_user['sum_award']+$award_num;

                    D('CurrencyUserStream')->addStream($member_id, $coin_config['award_currency_id'], 4, $award_num, 1, 2, 0, '购买KOK赠送KOKcy');
                    $res_save = M('currency_user')->where(array('member_id' => $member_id, 'currency_id' => $coin_config['award_currency_id']))->save($data);
                    $res = $res_save===false?false:true;
                }
                if($res){ // 添加日志
                    $data_log = [
                        'order_sn' => $order_sn,
                        'member_id' => $member_id,
                        'currency_id' => $currency_id,
                        'number' => $num,
                        'award_currency_id' => $coin_config['award_currency_id'],
                        'award_number' => $award_num,
                        'add_time' => time(),
                        'status' => 1,
                    ];
                    $this->addAwardLog($data_log);
                }
            }
        }
        return $res;
    }

    /**
     * 添加奖励记录
     * @param $data
     * @return mixed
     */
    public function addAwardLog($data)
    {
        return M('c2c_order_award')->add($data);
    }

    /**
     * 奖励记录
     */
    public function awardLogList()
    {
        $order_sn = I('order_sn');
        $member_id = I('member_id', '', 'intval');
        if (!empty($order_sn)) {
            $where['o.order_sn'] = array('like', '%' . $order_sn . '%');
        }
        if (!empty($member_id)) {
            $where['o.member_id'] = $member_id;
        }
        $count = M('c2c_order_award')->alias('o')->where($where)->count();// 查询满足要求的总记录数
        $Page = new Page($count, 15);// 实例化分页类 传入总记录数和每页显示的记录数(25)

        //给分页传参数
        setPageParameter($Page, array('order_sn' => $order_sn, 'member_id' => $member_id));

        $show = $Page->show();// 分页显示输出

        $field = "o.*,c.currency_mark,d.currency_mark as award_currency_mark";
        $award_list = M('c2c_order_award')->alias('o')
            ->join("left join " . C("DB_PREFIX") . "currency as c on c.currency_id = o.currency_id")
            ->join("left join " . C("DB_PREFIX") . "currency as d on d.currency_id = o.award_currency_id")
            ->field($field)->where($where)->order("o.add_time desc ")
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();

        //$status = array('0' => '待处理', '1' => '支付成功', '2' => '打款成功', '-1' => '已取消');
        //目前只有一种状态，买入订单支付完成才送

        $this->assign('award_list', $award_list);
        $this->assign('page', $show);// 赋值分页输出
        $this->display();
    }
}