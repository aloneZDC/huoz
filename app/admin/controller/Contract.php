<?php
namespace app\admin\controller;

use app\common\model\AccountBook;
use app\common\model\AccountBookType;
use app\common\model\ContractConfig;
use app\common\model\ContractIncomeLog;
use app\common\model\ContractLockLog;
use app\common\model\ContractOrder;
use app\common\model\ContractTrade;
use app\common\model\Currency;
use app\common\model\CurrencyUser;
use app\common\model\HongbaoKeepLog;
use think\Db;
use think\Exception;
use think\Request;

class Contract extends Admin {
    //空操作
    public function _empty(){
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    /**
     * 合约配置
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function config()
    {
        $configSelect = ContractConfig::select();
        $configList = [];
        foreach ($configSelect as $key => $value) {
            $configList[] = $value;
        }
        $this->assign('configList',$configList);
        return $this->fetch();
    }

    /**
     * 合约配置-更新
     */
    public function updateCofig(Request $request)
    {
        $config = $request->post('config/a');
        foreach ($config as $key => $value) {
            $data[] = [
                'cc_key'=>$key,
                'cc_value'=>$value,
            ];
        }
        
        $config = new ContractConfig();
        $save = $config->saveAll($data);

        if ($save === false) {
            return $this->error('修改失败!请重试');
        }

        return $this->success('修改成功!');
    }

    /**
     * 合约订单
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function order_list(Request $request)
    {
        $where = [];
        $member_id = input('member_id');
        $number = input('number');
        $money_type = input('money_type');
        $type = input('type');
        $buy_type = input('buy_type');
        $trade_id = input('trade_id');
        $closeout_type = input('closeout_type', -1);
        $status = input('status');
        $trust_status = input('trust_status', -1);
        $stop_profit_type = input('stop_profit_type');
        $stop_loss_type = input('stop_loss_type');

        if (!empty($member_id)) {
            $where['member_id'] = $member_id;
        }

        if (!empty($number)) {
            $where['number'] = ['like', '%'.$number.'%'];
        }

        if (!empty($money_type)) {
            $aa = explode('_', $money_type);
            $where['money_type'] = $aa[0];
            $where['money_currency_id'] = $aa[1];
        }
        else {
            /*$currency_id = ContractConfig::get_value('contract_currency_id', 35);
            $where['money_type'] = 1;
            $where['money_currency_id'] = $currency_id;
            $money_type = '1_'.$currency_id;*/
        }

        if (!empty($type)) {
            $where['type'] = $type;
        }

        if (!empty($buy_type)) {
            $where['buy_type'] = $buy_type;
        }

        if (!empty($trade_id)) {
            $where['trade_id'] = $trade_id;
        }

        if ($closeout_type >= 0) {
            $where['closeout_type'] = $closeout_type;
        }

        if (!empty($status)) {
            $where['status'] = $status;
        }

        if ($trust_status >= 0) {
            $where['trust_status'] = $trust_status;
        }

        if (!empty($stop_profit_type)) {
            $where['stop_profit_type'] = $stop_profit_type;
        }

        if (!empty($stop_loss_type)) {
            $where['stop_loss_type'] = $stop_loss_type;
        }

        $virtual_currency_name = ContractConfig::get_value('contract_virtual_currency_name', 'KOIC');
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = ContractOrder::with(['money_currency','currency','trade_currency'])
            ->where($where)
            ->order("id", "desc")
            ->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $money_total = ContractOrder::where($where)->sum('money_currency_num');
        $safe_total = ContractOrder::where($where)->sum('safe_currency_num');
        $fee_total = ContractOrder::where($where)->sum('fee_value');
        $income_total = ContractOrder::where($where)->sum('income_money');
        $money_total1 = $money_total + $income_total - $fee_total;

        return $this->fetch('', [
            'list' => $list,
            'member_id' => $member_id,
            'number' => $number,
            'money_type' => $money_type,
            'type' => $type,
            'buy_type' => $buy_type,
            'trade_id' => $trade_id,
            'closeout_type' => $closeout_type,
            'status' => $status,
            'trust_status' => $trust_status,
            'stop_profit_type' => $stop_profit_type,
            'stop_loss_type' => $stop_loss_type,
            'page' => $page,
            'virtual_currency_name' => $virtual_currency_name,
            'money_total' => $money_total,
            'safe_total' => $safe_total,
            'money_total1' => $money_total1,
            'fee_total' => $fee_total,
            'income_total' => $income_total,
            'empty' => '暂无数据',
            'tradeList' => ContractTrade::get_trade_list1(),
            'statusList' => ContractOrder::STATUS_ENUM,
            'trustStatusList' => ContractOrder::TRUST_STATUS_ENUM,
            'buyTypeList' => ContractOrder::BUY_TYPE_ENUM,
            //'moneyTypeList' => ContractOrder::MONEY_TYPE_ENUM,
            'moneyTypeList' => ContractOrder::get_money_type_list(),
            'typeList' => ContractOrder::TYPE_ENUM,
            'closeoutTypeList' => ContractOrder::CLOSEOUT_TYPE_ENUM,
            'stopTypeList' => ContractOrder::STOP_ENUM,
        ]);
    }

    /**
     * 收益记录
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function income_log(Request $request)
    {
        $where = [];
        $member_id = input('member_id');
        $type = input('type');

        if (!empty($member_id)) {
            $where['member_id'] = $member_id;
        }

        if (!empty($type)) {
            $where['type'] = $type;
        }

        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = ContractIncomeLog::with(['currency'])
            ->where($where)
            ->order("id", "desc")
            ->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        return $this->fetch('', [
            'list' => $list,
            'member_id' => $member_id,
            'type' => $type,
            'page' => $page,
            'empty' => '暂无数据',
            'typeList' => ContractIncomeLog::TYPE_ENUM,
        ]);
    }

    /**
     * 锁仓记录
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function lock_log(Request $request)
    {
        $where = [];
        $member_id = input('member_id');
        $currency_id = input('currency_id');
        $type = input('type');

        if (!empty($member_id)) {
            $where['member_id'] = $member_id;
        }

        if (!empty($currency_id)) {
            $where['currency_id'] = $currency_id;
        }

        if (!empty($type)) {
            $where['type'] = $type;
        }

        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = ContractLockLog::with(['currency'])
            ->where($where)
            ->order("id", "desc")
            ->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();

        $currency_list = [];
        $contract_currency_list_1 = explode(',', ContractConfig::get_value('contract_currency_list_1', '8,35,38'));//合约真实账户币种列表
        //真实账户
        foreach ($contract_currency_list_1 as $key => $value) {
            $currency = Currency::get($value);
            $currency_list[$value] = $currency['currency_name'];
        }
        return $this->fetch('', [
            'list' => $list,
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'type' => $type,
            'page' => $page,
            'empty' => '暂无数据',
            'typeList' => ContractLockLog::TYPE_ENUM,
            'currencyList' => $currency_list,
        ]);
    }

    /**
     * 统计
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function statistics(Request $request)
    {
        $where = [];
        $money_type = input('money_type');
        $type = input('type');
        $buy_type = input('buy_type');
        $trade_id = input('trade_id');
        $status = input('status');
        $user_type = input('user_type');
        $starttime = input("starttime");
        //if (empty($starttime)) $starttime = date('Y-m-d', strtotime(date('Y-m-d')) - 30 * 86400);
        $endtime = input("endtime");
        if (empty($endtime)) $endtime = date('Y-m-d');

        if (!empty($money_type)) {
            $aa = explode('_', $money_type);
            $where['money_type'] = $aa[0];
            $where['money_currency_id'] = $aa[1];
        }
        else {
            $currency_id = ContractConfig::get_value('contract_currency_id', 35);
            $where['money_type'] = 1;
            $where['money_currency_id'] = $currency_id;
            $money_type = '1_'.$currency_id;
        }
        if (!empty($type)) {
            $where['type'] = $type;
        }
        if (!empty($buy_type)) {
            $where['buy_type'] = $buy_type;
        }
        if (!empty($trade_id)) {
            $where['trade_id'] = $trade_id;
        }
        if (!empty($status)) {
            $where['status'] = $status;
        }
        else {
            $where['status'] = ['in','3,4,5'];
        }
        if ($user_type == '') {
            $user_type = 1;
        }
        if (!empty($user_type)) {
            $test_user_list = ContractConfig::get_value('contract_test_user_list', '');
            if ($user_type == 1) {
                $where['member_id'] = ['not in', $test_user_list];
            }
            else {
                $where['member_id'] = ['in', $test_user_list];
            }
        }

        $money_total_total = 0;
        $money_total1_total = 0;
        $safe_total_total = 0;
        $buy_fee_total_total = 0;
        $fee_total_total = 0;
        $win_total_total = 0;
        $loss_total_total = 0;
        $lock_total_total = 0;
        $platform_total_total = 0;

        empty($starttime) ? $startTime = 0 : $startTime = strtotime($starttime);
        $endTime = strtotime($endtime) + 86399;
        $where['add_time'] = ['between', [$startTime, $endTime]];
        $field = "FROM_UNIXTIME(add_time,'%Y-%m-%d') as `date`,SUM(money_currency_num) AS money_total,SUM(money_currency_num*lever_num) AS money_total1,SUM(safe_currency_num) AS safe_total,SUM(buy_fee) AS buy_fee_total,SUM(fee_value) AS fee_total,
SUM(IF(income_money>=0, income_money, 0)) AS win_total,SUM(IF(income_money<0, income_money, 0)) AS loss_total,SUM(lock_currency_num) AS lock_total";
        $list = ContractOrder::where($where)->field($field)
            ->group('date')
            ->order("add_time", "desc")
            ->select();

        if (count($list) > 0) {
            foreach ($list as $key => $value) {
                $list[$key]['money_total'] = floattostr($value['money_total']);
                $money_total_total += $value['money_total'];
                $list[$key]['money_total1'] = floattostr($value['money_total1']);
                $money_total1_total += $value['money_total1'];
                $list[$key]['safe_total'] = floattostr($value['safe_total']);
                $safe_total_total += $value['safe_total'];
                $list[$key]['buy_fee_total'] = floattostr($value['buy_fee_total']);
                $buy_fee_total_total += $value['buy_fee_total'];
                $list[$key]['fee_total'] = floattostr($value['fee_total']);
                $fee_total_total += $value['fee_total'];
                $list[$key]['win_total'] = floattostr($value['win_total']);
                $win_total_total += $value['win_total'];
                $list[$key]['loss_total'] = floattostr($value['loss_total']);
                $loss_total_total += $value['loss_total'];
                $list[$key]['lock_total'] = floattostr($value['lock_total']);
                $lock_total_total += $value['lock_total'];
                $platform_total = -($value['win_total']+$value['loss_total']-$value['safe_total']-$value['fee_total']);
                $platform_total_total += $platform_total;
                $list[$key]['platform_total'] = $platform_total;
            }
        }

        /*for ($i = strtotime($endtime); $i >= strtotime($starttime); $i -= 86400) {
            $startTime = $i;
            $endTime = $startTime + 86399;
            $where['add_time'] = ['between', [$startTime, $endTime]];
            $money_total = ContractOrder::where($where)->sum('money_currency_num');
            $money_total_total += $money_total;
            $money_total1 = ContractOrder::where($where)->sum('money_currency_num*lever_num');
            $money_total1_total += $money_total1;
            $safe_total = ContractOrder::where($where)->sum('safe_currency_num');
            $safe_total_total += $safe_total;
            $buy_fee_total = ContractOrder::where($where)->sum('buy_fee');
            $buy_fee_total_total += $buy_fee_total;
            $fee_total = ContractOrder::where($where)->sum('fee_value');
            $fee_total_total += $fee_total;
            $where1 = $where;
            $where1['income_money'] = ['egt', 0];
            $win_total = ContractOrder::where($where1)->sum('income_money');
            $win_total_total += $win_total;
            $where1['income_money'] = ['lt', 0];
            $loss_total = ContractOrder::where($where1)->sum('income_money');
            $loss_total_total += $loss_total;
            $lock_total = ContractOrder::where($where1)->sum('lock_currency_num');
            $lock_total_total += $lock_total;
            $platform_total = -($win_total + $loss_total - $safe_total - $fee_total);
            $platform_total_total += $platform_total;
            $list[] = [
                'date'=>date('Y-m-d', $i),
                'money_total'=>$money_total,
                'money_total1'=>$money_total1,
                'safe_total'=>$safe_total,
                'buy_fee_total'=>$buy_fee_total,
                'fee_total'=>$fee_total,
                'win_total'=>$win_total,
                'loss_total'=>$loss_total,
                'lock_total'=>$lock_total,
                'platform_total'=>$platform_total,
            ];
        }*/
        $total = [
            'date'=>'汇总',
            'money_total'=>$money_total_total,
            'money_total1'=>$money_total1_total,
            'safe_total'=>$safe_total_total,
            'buy_fee_total'=>$buy_fee_total_total,
            'fee_total'=>$fee_total_total,
            'win_total'=>$win_total_total,
            'loss_total'=>$loss_total_total,
            'lock_total'=>$lock_total_total,
            'platform_total'=>$platform_total_total,
        ];
        array_unshift($list, $total);

        if (input("daochu") == 1) {
            $xlsCell = array(
                array('date', '日期'),
                array('money_total', '总保证金'),
                array('money_total1', '总交易额(杠杆放大)'),
                array('safe_total', '总保险金'),
                array('buy_fee_total', '买入手续费总额'),
                array('fee_total', '手续费总额'),
                array('win_total', '赢总额'),
                array('loss_total', '输总额'),
                array('lock_total', '锁仓总额'),
                array('platform_total', '平台输赢总额'),
            );
            return export_excel("合约统计",$xlsCell,$list);
        }

        $statusList = ContractOrder::STATUS_ENUM;
        unset($statusList[1]);
        unset($statusList[2]);
        unset($statusList[6]);
        unset($statusList[7]);
        return $this->fetch('', [
            'list' => $list,
            'money_type' => $money_type,
            'type' => $type,
            'buy_type' => $buy_type,
            'trade_id' => $trade_id,
            'status' => $status,
            'user_type' => $user_type,
            'starttime' => $starttime,
            'endtime' => $endtime,
            'empty' => '暂无数据',
            //'moneyTypeList' => ContractOrder::MONEY_TYPE_ENUM,
            'moneyTypeList' => ContractOrder::get_money_type_list(),
            'typeList' => ContractOrder::TYPE_ENUM,
            'buyTypeList' => ContractOrder::BUY_TYPE_ENUM,
            'tradeList' => ContractTrade::get_trade_list1(),
            'statusList' => $statusList,
            'userTypeList' => ['0'=>'全部','1'=>'普通用户','2'=>'测试用户'],
        ]);
    }

    /**
     * 平仓
     */
    public function closeout(Request $request)
    {
        if ($request->isPost()) {
            $id = $request->param('id');
            $closeout_price = $request->param('closeout_price', 0, 'floatval');

            $orderFind = ContractOrder::get($id);
            if (!$orderFind) return successJson(ERROR1, '获取订单数据失败');
            if ($orderFind['status'] != 3) return successJson(ERROR1, '订单非持仓状态,无法平仓');

            $orderFind['closeout_price'] = $closeout_price;
            $orderFind['closeout_type'] = 4;
            $orderFind['closeout_time'] = time();
            $orderFind['next_closeout_time'] = time();//设置为当前时间，等待下一次平仓
            $orderFind['status'] = 4;
            $res = $orderFind->save();
            if (!$res) {
                return successJson(ERROR1, '系统错误修改失败');
            }
            return successJson(SUCCESS, "修改成功");
        }

        $id = $request->param('id');
        $data = ContractOrder::with(['money_currency'])->where('id', $id)->find();
        $price_time = strtotime(date('Y-m-d H:i:00'));
        $price = \app\common\model\ContractKline::get_price($data['trade_id'], 60, 'close_price', $price_time);
        $n = 1;
        while (!$price && $n < 10) {
            //当获取失败时，获取上一个周期的价格返回
            $price = \app\common\model\ContractKline::get_price($data['trade_id'], 60, 'close_price', $price_time - 60 * $n);
            $n++;
        }
        $data['close_price'] = $price;
        $virtual_currency_name = ContractConfig::get_value('contract_virtual_currency_name', 'KOIC');
        $income_money = ContractOrder::get_order_income($data, $price);
        $data['income_money'] = $income_money;
        return $this->fetch(null, [
            'data' => $data,
            'virtual_currency_name' => $virtual_currency_name,
            'tradeList' => ContractTrade::get_trade_list1(),
            'statusList' => ContractOrder::STATUS_ENUM,
            'buyTypeList' => ContractOrder::BUY_TYPE_ENUM,
            //'moneyTypeList' => ContractOrder::MONEY_TYPE_ENUM,
            'moneyTypeList' => ContractOrder::get_money_type_list(),
            'typeList' => ContractOrder::TYPE_ENUM,
            'closeoutTypeList' => ContractOrder::CLOSEOUT_TYPE_ENUM,
            'stopTypeList' => ContractOrder::STOP_ENUM,
        ]);
    }

    /**
     * 撤销委托
     */
    public function cancel_trust(Request $request)
    {
        $order_id = $request->param('id');

        $orderFind = ContractOrder::get($order_id);
        $tradeFind = ContractTrade::get($orderFind['trade_id']);
        if (!$orderFind || !$tradeFind) {
            return $this->error(lang('获取合约订单数据失败!'));
        }
        if ($orderFind['type'] != 2) {
            return $this->error(lang('获取合约订单数据失败!'));
        }
        if ($orderFind['status'] != 6) {
            return $this->error(lang('获取合约订单数据失败!'));
        }
        if ($orderFind['trust_status'] != 1) {
            return $this->error(lang('获取合约订单数据失败!'));
        }

        try {
            Db::startTrans();

            $orderFind['trust_status'] = 3;//委托状态 1-委托中 2-已成交 3-已撤销
            $orderFind['trust_cancel_time'] = time();
            $orderFind['status'] = 7;//6-委托中 7-撤销委托

            $res = $orderFind->save();
            if ($res === false) {
                throw new Exception(lang('operation_failed_try_again'));
            }

            // 保存用户资产
            $usersCurrency = CurrencyUser::getCurrencyUser($orderFind['member_id'], $orderFind['money_currency_id']);
            if(!$usersCurrency) throw new Exception("获取资产错误");

            if ($orderFind['money_type'] == 1) {
                $bookType = AccountBookType::FOREVER_CONTRACT_CANCEL;
                $content = 'forever_contract_cancel';
                // 账本
                $flag = AccountBook::add_accountbook($orderFind['member_id'], $orderFind['money_currency_id'], $bookType, $content, 'in', $orderFind['money_currency_num'], $order_id);
                if ($flag === false) {
                    throw new Exception(lang('system_error_please_try_again_later'));
                }

                $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setInc('num',$orderFind['money_currency_num']);
                if ($flag === false) {
                    throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                }

                $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setDec('forzen_num',$orderFind['money_currency_num']);
                if ($flag === false) {
                    throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                }

                if ($orderFind['safe_currency_num'] > 0) {
                    // 账本
                    $flag = AccountBook::add_accountbook($orderFind['member_id'], $orderFind['money_currency_id'], AccountBookType::FOREVER_CONTRACT_CANCEL_SAFE_RETURN, 'forever_contract_cancel_safe_return', 'in', $orderFind['safe_currency_num'], $order_id);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later'));
                    }

                    $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setInc('num',$orderFind['safe_currency_num']);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                    }

                    $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setDec('forzen_num',$orderFind['safe_currency_num']);
                    if ($flag === false) {
                        throw new Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
                    }
                }
            }
            else {//模拟账户

                $flag = CurrencyUser::where(['cu_id'=>$usersCurrency['cu_id']])->setInc('keep_num',$orderFind['money_currency_num']);
                if($flag === false) Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);

                //添加持仓记录
                $flag = HongbaoKeepLog::add_log('forever_contract_cancel',$orderFind['member_id'],$orderFind['money_currency_id'],$orderFind['money_currency_num'],$order_id);
                if($flag === false) Exception(lang('system_error_please_try_again_later').'-in line:'.__LINE__);
            }

            Db::commit();
            return $this->error('操作成功');
        } catch (Exception $exception) {
            Db::rollback();
            return $this->error('操作失败,'.$exception->getMessage());
        }
    }
}