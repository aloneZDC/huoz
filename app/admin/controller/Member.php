<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 16-3-8
 * Time: 下午12:28
 */

namespace app\admin\controller;

use app\common\model\ContractOrder;
use app\common\model\HongbaoLog;
use think\Db;
use think\Exception;
use think\Request;


class Member extends Admin
{
    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 会员列表
     */
    public function index(Request $request)
    {
        $ename = input('ename');
        $email = input('email');
        $member_id = input('member_id');
        $name = input('name');
        $phone = input('phone');
        $pid = input('pid');
        $status = input('status');
        $invit_code = input('invit_code');
        $where=null;
        if (!empty($ename)) {
            $where['member.ename'] = $ename;
        }
        if (!empty($email)) {
            $where['member.email'] = $email;
        }
        if (!empty($member_id)) {
            $where['member.member_id'] = $member_id;
        }
        if (!empty($name)) {
            $where['member.name'] = $name;
        }
        if (!empty($phone)) {
            $where['member.phone'] = $phone;
        }
        if (!empty($pid)) {
            $where['member.pid'] = $pid;
        }
        if (!empty($invit_code)) {
            $where['member.invit_code'] = $invit_code;
        }
        if (!empty($status)) {
            $where['member.status'] = $status;
            $where['member.is_award'] = '0';

            $where['member.pid'] = array('neq', 0);
        }

        // 钱包地址筛选
        $chongzhi_url = input('chongzhi_url','');
        if(!empty($chongzhi_url)) {
           $CurrencyUser = \app\common\model\CurrencyUser::where('chongzhi_url',$chongzhi_url)->find();
           if(!empty($CurrencyUser->member_id)) {
               $where['member.member_id'] = $CurrencyUser->member_id;
           }else {
               $where['member.member_id'] = 0;
           }
        }

        $model = Db::name('member');
        $list = $model->alias('member')->where($where)
            ->field("member.*")->order("member.member_id desc ")
            ->paginate(15,null,['query'=>$request->get()]);
        $show=$list->render();
        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出

        $total = \app\common\model\Member::count();
        $active_total = \app\common\model\Member::where('active_status',1)->count();
        $this->assign('total', $total);// 赋值数据集
        $this->assign('active_total', $active_total);// 赋值分页输出
       return $this->fetch(); // 输出模板
    }


    /**
     * 启用或者禁用启用帐号
     * $member_id           用户id
     * $status              0:启用;2：禁用
     * Created by Red.
     * Date: 2019/1/22 14:33
     */
    function disableSwitch()
    {
        $member_id = input("member_id");
        $status = input("status");
        if($status == 1 && !in_array($this->admin['username'],['admin','admin1'])) {
            return $this->error("无权限启用");
        }

        if (!empty($member_id)) {
            $update = Db::name("member")->where(['member_id' => $member_id])->update(['status' => $status,'token_value'=>md5($member_id)]);
            if ($update) {
                //清除登录的token
                if ($status == 2) {
                    cache('auto_login_' . $member_id, null);//清除app的登录信息
                    cache('pc_auto_login_' . $member_id, null);//清除pc的登录信息
                }
                return $this->success("操作成功");
            } else {
                return $this->error("操作失败");
            }
        }
        return $this->error("参数错误");
    }

    /**
     * @Desc:开通老板根用户
     * @author: Administrator
     * @return array
     * @Date: 2018/12/27 0027 10:21
     */
    public function boss()
    {
        try {
            $boss_member_id = I("member_id");
            $boss_plan_count = M("boss_plan")->where(['member_id' => $boss_member_id])->count();
            $boss_plan_info_count = M("boss_plan_info")->where(['member_id' => $boss_member_id])->count();
            if ($boss_plan_count == 0 && $boss_plan_info_count == 0) {

                $a1 = M("boss_plan")->addAll([[
                    'member_id' => $boss_member_id,
                    'pid' => 0,
                    'status' => 3,
                    'is_admin' => 1,
                    'create_time' => time(),
                    'activate_time' => time(),
                    'confirm_time' => time(),
                ]]);
                $a = M("boss_plan_info")->add([
                    'member_id' => $boss_member_id,
                    'pid' => 0,
                    'votes' => 0,
                    'level' => 0,
                    'push_num' => 0,
                    'overdue_time' => 1,
                    'is_admin' => 1,
                ]);
                $this->ajaxReturn(['status' => 1, 'info' => '开通成功']);
            } else {
                $this->ajaxReturn(['status' => 0, 'info' => '已经是老板计划的成员']);
            }
        } catch (Exception $e) {
            $this->ajaxReturn(['status' => 0, 'info' => '开通失败']);
        }
    }

    /**
     * 存量统计表
     */
    public function balance_sum()
    {
        $email = I('email');
        $member_id = I('member_id');
        $name = I('name');
        $phone = I('phone');
        $pid = I('pid');
        $status = I('status');

        if (!empty($email)) {
            $where['member.email'] = $email;
        }
        if (!empty($member_id)) {
            $where['member.member_id'] = $member_id;
        }
        if (!empty($name)) {
            $where['member.name'] = $name;
        }
        if (!empty($phone)) {
            $where['member.phone'] = $phone;
        }
        if (!empty($pid)) {
            $where['member.pid'] = $pid;
        }
        if (!empty($status)) {
            $where['member.status'] = $status;
            $where['member.is_award'] = '0';

            $where['member.pid'] = array('neq', 0);
        }

        $model = M('member');

        $count = $model->alias('member')->where($where)->count();// 查询满足要求的总记录数
        $Page = new Page($count, 15);// 实例化分页类 传入总记录数和每页显示的记录数(25)

        //给分页传参数
        setPageParameter($Page, array('email' => $email, 'member_id' => $member_id, 'name' => $name, 'phone' => $phone, 'status' => $status, 'pid' => $pid));

        $show = $Page->show();// 分页显示输出

        $list = $model->alias('member')->where($where)->order("member.rmb desc ")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //$field = "member.*,ifnull(verify_file.verify_state, 0) as verify_state";
        //$list = $model->alias('member')->join("left join " . C("DB_PREFIX") . "verify_file as verify_file on member.member_id = verify_file.member_id")->field($field)->where($where)->order("member.rmb desc ")->limit($Page->firstRow.','.$Page->listRows)->select();

        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出
        $this->display(); // 输出模板
    }

    /**
     * 用户资料弹窗模版
     */
    public function member_details()
    {
        $user_id = input('member_id', 0, 'intval');
        if (!$user_id > 0) {
            $this->error("没有获取到用户ID。");
        }

        $this->assign('user_id', $user_id);
        $this->assign('module', Request::instance()->module());

        //红包锁仓待返还数量
        $hongbao_wait_back = HongbaoLog::where(['user_id'=>$user_id,'is_back'=>0])->sum('num');
        $this->assign('hongbao_wait_back',$hongbao_wait_back);

        //合约锁仓数量
        $contract_order = ContractOrder::where(['member_id'=>$user_id,'money_type'=>1,'status'=>3])->sum('money_currency_num');
        $this->assign('contract_order',$contract_order);

        //云攒金
        $air_num = \app\common\model\CurrencyUser::where('member_id',$user_id)->sum('air_num');
        $this->assign('air_num',$air_num);

        return $this->fetch();
    }

    /**
     * 会员信息
     */
    public function user_info()
    {
        $user_id = I('post.user_id');

        if (empty($user_id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '没有获取到用户ID']);
        }

        $user_fields = "member_id,email,name,phone,rmb,forzen_rmb,from_unixtime(reg_time, '%Y-%m-%d %H:%i:%s') reg_time,ifnull(remarks, '暂无用户备注') remarks";
        $currency_fields = "c.currency_name,c.account_type,c.is_trade_currency,cu.num,cu.forzen_num,dnc_lock,dnc_other_lock,keep_num,cu.lock_num,cu.num_award,cu.sum_award,cu.currency_id,cu.internal_buy,cu.remaining_principal,cu.release_lock";

        $user_info = Db::name('member')->field($user_fields)->where(['member_id' => $user_id])->find();

        if (empty($user_info)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '用户不存在']);
        }

        $user_currency = Db::name('currency_user')->alias("cu")->field($currency_fields)
            ->join(config("database.prefix")."currency c","c.currency_id=cu.currency_id","LEFT")
            ->where(['cu.member_id' => $user_id])->select();

        $tibi_model = Db::name('tibi');
        $trade_model = Db::name('trade');

        if (!empty($user_currency)) {
            foreach ($user_currency as $key => &$value) {
                $value['dnc_lock'] = keepPoint($value['dnc_lock']+$value['dnc_other_lock'],6);
//                if($value['is_trade_currency']==1) {
//                    $value['currency_name'] .= '(币币)';
//                }
                // 增加账户名称
                $value['currency_name'] .= ' - ' . lang('bfw_' . $value['account_type']);

                $value['chongbi_num'] = $tibi_model->field("ifnull(round(sum(num), 4), '0.0000') as chongbi_num")->where(['to_member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id'], 'status' => 3, 'transfer_type' => "1"])->find()['chongbi_num'];
                //内部互转充币
                $value['hzchongbi_num'] = $tibi_model->field("ifnull(round(sum(`actual`), 4), '0.0000') as hzchongbi_num")->where(['to_member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id'], 'transfer_type' => "2"])->find()['hzchongbi_num'];
                $value['tibi_num'] = $tibi_model->field("ifnull(round(sum(num), 4), '0.0000') as tibi_num")->where(['from_member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id'], 'status' => 1, 'transfer_type' => "1"])->find()['tibi_num'];
                //内部互转提币
                $value['hztibi_num'] = $tibi_model->field("ifnull(round(sum(num), 4), '0.0000') as hztibi_num")->where(['from_member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id'], 'transfer_type' => "2"])->find()['hztibi_num'];
                $value['buy_num'] = $trade_model->field("ifnull(round(sum(num), 4), '0.0000') as buy_num")->where(['member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id'], 'type' => 'buy'])->find()['buy_num'];
                $value['sell_num'] = $trade_model->field("ifnull(round(sum(num), 4), '0.0000') as sell_num")->where(['member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id'], 'type' => 'sell'])->find()['sell_num'];
//                $value['issue_num'] = M("issue")->alias("issue")->field("ifnull(sum(issue_log.num), '0.0000') as num")->join("yang_issue_log as issue_log on issue.id = issue_log.iid")->where(['issue_log.uid' => $user_info['member_id'], 'issue.currency_id' => $value['currency_id']])->find()['num']; //认购数量
                $value['pay_num'] = Db::name("pay")->field("ifnull(round(sum(money), 4), '0.0000') as money")->where(['member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id']])->find()['money']; //管理员充值
                $award_money = Db::name("currency_user_num_award")->field("sum(num_award) as money")->where(['member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id']])->find()['money']; //邀请奖励
                $value['sum'] = $value['num'] + $value['forzen_num'] + $value['num_award']+$value['lock_num'] + $value['release_lock'];  //总量
//            if ($value['currency_id']==29){
//                    //挖矿
//                    $wa_num = M("mining_bonus")->field("sum(num) as wa_num")->where(['member_id' => $user_info['member_id'], 'currency_id' => $value['currency_id']])->find()['wa_num']; //邀请奖励
//                    //买币
//                    $value['must_buy_num'] = $trade_model->field("ifnull(round(sum(money), 6), '0.0000') as must_buy_num")->where(['member_id' => $user_info['member_id'], 'currency_trade_id' => $value['currency_id'], 'type' => 'buy'])->find()['must_buy_num'];
//                    $value['must_sell_num'] = $trade_model->field("ifnull(round(sum(money), 6), '0.0000') as must_sell_num")->where(['member_id' => $user_info['member_id'], 'currency_trade_id' => $value['currency_id'], 'type' => 'sell'])->find()['must_sell_num'];
//                    //买卖手续费
//                    $value['must_fee'] = $trade_model->field("ifnull(round(sum(fee), 6), '0.0000') as must_fee")->where(['member_id' => $user_info['member_id'], 'currency_trade_id' => $value['currency_id']])->find()['must_fee'];
//
//                    $value['balance'] = ($value['chongbi_num'] + $value['buy_num']) - ($value['sell_num'] + $value['tibi_num']) - ($value['num'] + $value['forzen_num'] + $value['lock_num']) + $value['issue_num'] + $value['pay_num'] + $award_money - $value['must_buy_num'] - $value['must_fee']+$wa_num+$value['must_sell_num']; //余额 = （充积分数量 + 购买量） - （卖出量  +  提积分数量 ） - （持有数量 + 冻结数量 +锁仓） + 认购数量 + 管理员充值 + 邀请奖励 +转换母币 -买卖手续费+挖矿分享
//                    //挖矿
//                    $value['wakuang'] = M('mining_bonus')->field("ifnull(sum(round(num, 4)), '0.0000') as wakuang")->where(['member_id' => $user_info['member_id'], 'column' => 1, 'currency_id' => '29'])->find()['wakuang'];
//                    //分红
//                    $value['fenhong'] = M('mining_bonus')->field("ifnull(sum(round(num, 4)), '0.0000') as fenhong")->where(['member_id' => $user_info['member_id'], 'column' => 1, 'currency_id' => '29'])->find()['fenhong'];
//
//
//
//                }else {

                $value['balance'] = ($value['chongbi_num'] +$value['hzchongbi_num']+ $value['buy_num']+$value['pay_num']+$award_money) - ($value['sell_num'] + $value['tibi_num']+$value['hztibi_num']+$value['num']+$value['forzen_num']); //余额 = （充积分数量 + 购买量） - （卖出量  +  提积分数量 ） - （持有数量 + 冻结数量 +锁仓） + 认购数量 + 管理员充值 + 邀请奖励
//                    $value['wakuang'] = '0.0000';
//                    $value['fenhong'] = '0.0000';
//                }

                // if (!($value['num'] + $value['tibi_num'] + $value['chongbi_num'] + $value['forzen_num'] + $value['num_award'] + $value['sum_award'] + $value['totalcharging'] + $value['buy_num'] + $value['sell_num']) > 0) {
                //     unset($user_currency[$key]);
                // }

//                $value['issue_num'] = number_format($value['issue_num'], 4, '.', '');
                $value['balance'] = number_format($value['balance'], 4, '.', ''); //余额
            }
        }

        //实际充值钱
        $user_info['totalcount'] = Db::name('pay')->field("ifnull(sum(round(count, 4)), '0.0000') as totalcount")->where(['member_id' => $user_info['member_id'], 'status' => 1, 'currency_id' => '0'])->find()['totalcount'];
        //管理员实际充值钱
        $user_info['total_adminmoney'] = Db::name('pay')->field("ifnull(sum(round(money, 4)), '0.0000') as total_adminmoney")->where(['member_id' => $user_info['member_id'], 'status' => 1, 'type' => 3, 'currency_id' => '0'])->find()['total_adminmoney'];
        //提现钱
//        $user_info['totalmoney'] = Db::name('bank')->alias('bank')->field("ifnull(sum(round(withdraw.money, 4)), '0.0000') as totalmoney")->join("yang_withdraw as withdraw ON withdraw.bank_id = bank.id and withdraw.uid = bank.uid")->join("yang_areas as b ON b.area_id = bank.address")->join("yang_areas as a ON a.area_id = b.parent_id")->where(['bank.uid' => $user_info['member_id'], 'withdraw.status' => ['in', [2, 4]]])->find()['totalmoney'];
        $user_info['totalmoney'] = 0;
        //余额
        $user_info['fifmoney'] = number_format($user_info['totalcount'] - $user_info['totalmoney'], 4);

        $result = [
            'user_info' => $user_info,
            'user_currency' => $user_currency,
        ];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    /**
     * 充值记录
     */
    public function user_pay_log()
    {
        $user_id = I('post.user_id');

        if (empty($user_id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '没有获取到用户ID']);
        }

        $pay_model = Db::name('pay');
        $fields = "pay.pay_id,pay.member_name,pay.member_id,pay.account,pay.money,pay.count,(case pay.status when 0 then '请付款' when 1 then '充值成功' 
        when 2 then '充值失败' when 3 then '已失效' else '暂无' end) status,pay.currency_id,(case when pay.currency_id > 0 then '充积分' else '充值' end)
         currency_type,(case pay.add_time when 0 then '-' else from_unixtime(pay.add_time, '%Y-%m-%d %H:%i:%s') end) add_time,pay.due_bank,pay.batch,pay.capital,
         pay.commit_name,(case pay.commit_time when 0 then '-' else from_unixtime(pay.commit_time, '%Y-%m-%d %H:%i:%s') end) commit_time,pay.audit_name,
         (case pay.audit_time when 0 then '-' else from_unixtime(pay.audit_time, '%Y-%m-%d %H:%i:%s') end) audit_time,m.email,m.phone,a.username,pay.message";

        $pay_list = $pay_model->alias('pay')->field($fields)
            ->join(config("database.prefix")."member m","m.member_id=pay.member_id","LEFT")
            ->join(config("database.prefix")."admin a","a.admin_id=pay.admin_id","LEFT")
            ->where(['pay.member_id' => $user_id, 'pay.status' => 1])->order('pay.add_time desc')->select();

        $pay_sum_money = $pay_model->field("ifnull(round(sum(money), 4), '0.0000') as totalczmoney,ifnull(round(sum(count), 4), '0.0000') as totalczcount")->where(['member_id' => $user_id, 'status' => 1, 'currency_id' => '0'])->find();
        $pay_sum_currency = $pay_model->field("ifnull(round(sum(money), 4), '0.0000') as totalcurrency")->where(['member_id' => $user_id, 'status' => 1, 'currency_id' => ['gt', '0']])->find();
        if (!empty($pay_list)) {
            foreach ($pay_list as &$value) {
                $value['currency_type'] = getCurrencynameByCurrency($value['currency_id']);
            }
        }
        $result = [
            'pay_list' => $pay_list,
            'pay_sum' => [
                'totalcurrency' => $pay_sum_currency['totalcurrency'], //充积分合计
                'totalczmoney' => $pay_sum_money['totalczmoney'], //充值合计
                'totalczcount' => $pay_sum_money['totalczcount'], //实际打款合计
            ]
        ];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    /**
     * 提现审核
     */
    public function user_withdraw_audit()
    {
        $user_id = I('post.user_id');

        if (empty($user_id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '没有获取到用户ID']);
        }

        $bank_model = M('bank');
        $finance_model = M('finance');

        $where = [
            'bank.uid' => $user_id,
            'withdraw.status' => ['in', [2, 4]]
        ];

        $fields = "withdraw.withdraw_id,withdraw.uid,withdraw.all_money,withdraw.withdraw_fee,withdraw.money,withdraw.order_num,(case withdraw.add_time when 0 then '-' else from_unixtime(withdraw.add_time, '%Y-%m-%d %H:%i:%s') end) add_time,(case withdraw.status when 2 then '通过' else '付款成功' end) status,bank.cardname,bank.cardnum,bank.bankname,a.area_name as aarea_name,b.area_name as barea_name,member.email";

        $withdraw_list = $bank_model->alias('bank')->field($fields)->join('yang_withdraw as withdraw on withdraw.bank_id = bank.id')->join("yang_areas as b ON b.area_id = bank.address")->join("yang_areas as a ON a.area_id = b.parent_id")->join("yang_member as member on withdraw.uid = member.member_id")->where($where)->order('withdraw.add_time desc, withdraw.status desc')->select();

        $finance_list = $finance_model->alias('finance')->field("finance.finance_id,finance.content,finance.money,(case finance.add_time when 0 then '-' else from_unixtime(finance.add_time, '%Y-%m-%d %H:%i:%s') end) add_time,(case finance.money_type when 1 then '收入' else '支出' end) moneytype,finance.ip,member.name as username,ifnull(currency.currency_name, '人民币') currency_name,ifnull(finance_type.name, '-') as typename")
            ->join('left join yang_member as member on member.member_id = finance.member_id')
            ->join('left join yang_finance_type as finance_type on finance_type.id = finance.type')
            ->join('left join yang_currency as currency on currency.currency_id = finance.currency_id')
            ->where(['finance.member_id' => $user_id])
            ->order('finance.add_time desc')
            ->select();

        $total_money = $bank_model->alias('bank')->field("ifnull(sum(withdraw.money), '0.0000') as totalmoney")->join("yang_withdraw as withdraw ON withdraw.bank_id = bank.id")->join("yang_areas as b ON b.area_id = bank.address")->join("yang_areas as a ON a.area_id = b.parent_id")->where($where)->find()['totalmoney'];

        $result = [
            'withdraw_list' => $withdraw_list, //提现记录
            'total_money' => $total_money, //提现金额合计
            'finance_list' => $finance_list, //财务日志
        ];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    /**
     * 积分列表
     * @return array
     */
    private function user_currency_list()
    {
        $currency_list = [
            [
                'currency_id' => -1,
                'currency_name' => '全部',
            ],
            [
                'currency_id' => 0,
                'currency_name' => '人民币',
            ],
        ];
        foreach ($this->currency as $value) {
            $currency_list[] = [
                'currency_id' => $value['currency_id'],
                'currency_name' => $value['currency_name'] . ' - ' . lang('bfw_' . $value['account_type']),
            ];
        }
        return $currency_list;
    }

    /**
     * 认购记录
     */
    public function user_rengou()
    {
        $user_id = I('post.user_id');
        $currency_id = I('post.currency_id', -1, 'trim,intval');

        if (empty($user_id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '没有获取到用户ID']);
        }

        $where = [
            'issue_log.uid' => $user_id,
        ];

        if ($currency_id >= 0) {
            $where['issue_log.buy_currency_id'] = $currency_id;
        }

        $model = M('issue_log');
        $fields = "issue_log.id,issue_log.iid,issue_log.num,issue_log.deal,issue_log.price,(case issue_log.add_time when 0 then '-' else from_unixtime(issue_log.add_time, '%Y-%m-%d %H:%i:%s') end) add_time,issue_log.buy_currency_id,ifnull(issue_log.remarks, '-') remarks,member.member_id,member.name,issue.title,issue_log.num*issue_log.price as count";

        $issue_log_list = $model->alias('issue_log')->field($fields)->join('left join yang_member as member on member.member_id = issue_log.uid')->join('left join yang_issue as issue on issue.id = issue_log.iid')->where($where)->order('issue_log.add_time desc')->select();

        if (!empty($issue_log_list)) {
            $currency_model = M("currency");
            foreach ($issue_log_list as &$value) {
                $value['currency_name'] = intval($value['buy_currency_id']) === 0 ? "人民币" : $currency_model->field('currency_id,currency_name')->where(['currency_id' => $value['buy_currency_id']])->find()['currency_name'];
                unset($value['buy_currency_id']);
            }
        }

        $count_fields = "ifnull(sum(issue_log.num), '0.0000') as buynum,ifnull(sum(issue_log.deal), '0.0000') as freezenum,ifnull(sum(issue_log.num*issue_log.price), '0.0000') as totalaggregate";
        $issue_log_count = $model->alias('issue_log')->field($count_fields)->join('left join yang_issue as issue on issue.id = issue_log.iid')->where($where)->find();

        $result = [
            'currency_list' => $this->user_currency_list(),
            'issue_log_list' => $issue_log_list,
            'issue_log_count' => $issue_log_count,
            'currency_id' => $currency_id,
        ];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    /**
     * 交易记录
     */
    public function user_transaction()
    {
        $user_id = I('post.user_id');
        $currency_id = I('post.currency_id', -1, 'trim,intval');

        if (empty($user_id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '没有获取到用户ID']);
        }

        $where = [
            'a.member_id' => $user_id,
        ];

        if ($currency_id >= 0) {
            $where['a.currency_id'] = $currency_id;
        }

        $model = Db::name('trade');
        $field = "a.*,b.currency_name as b_name,d.currency_name as b_trade_name,c.email as email,c.phone,c.member_id as member_id,c.name as name,c.phone as phone,(case a.add_time when 0 then '-' else from_unixtime(a.add_time, '%Y-%m-%d %H:%i:%s') end) add_time";

        $trade_list = Db::name('Trade')
            ->alias('a')
            ->field($field)
            ->join(config("database.prefix")."currency b","b.currency_id=a.currency_id","LEFT")
            ->join(config("database.prefix")."currency d","d.currency_id=a.currency_trade_id","LEFT")
            ->join(config("database.prefix")."member c","c.member_id=a.other_member_id","LEFT")
            ->where($where)
            ->order("a.add_time desc")
            ->select();

        if (!empty($trade_list)) {
            $currency_model = Db::name("currency");
            foreach ($trade_list as &$value) {
                $value['currency_name'] = intval($value['currency_id']) === 0 ? "人民币" : $currency_model->field('currency_id,currency_name')->where(['currency_id' => $value['currency_id']])->find()['currency_name'];
                $value['type_name'] = getOrdersType($value['type']);
                unset($value['currency_id']);
            }
        }

        $trade_buy_count = $model->alias('a')->field("round(ifnull(sum(a.num), '0.0000'), 4) as buynum,round(ifnull(sum(a.money), '0.0000'), 4) as buymoney")
            ->join(config("database.prefix")."currency c","c.currency_id=a.currency_id","LEFT")
            ->where($where)->where(['a.type' => 'buy'])->find();
        $trade_sell_count = $model->alias('a')->field("round(ifnull(sum(a.num), '0.0000'), 4) as sellnum,round(ifnull(sum(a.money), '0.0000'), 4) as sellmoney")
            ->join(config("database.prefix")."currency c","c.currency_id=a.currency_id","LEFT")
            ->where($where)->where(['a.type' => 'sell'])->find();

        $result = [
            'currency_list' => $this->user_currency_list(),
            'trade_list' => $trade_list,
            'trade_count' => [
                'buynum' => $trade_buy_count['buynum'],
                'buymoney' => $trade_buy_count['buymoney'],
                'sellnum' => $trade_sell_count['sellnum'],
                'sellmoney' => $trade_sell_count['sellmoney'],
            ],
            'currency_id' => $currency_id,
        ];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }
//    public function user_transaction()
//    {
//        $user_id = I('post.user_id');
//        $currency_id = I('post.currency_id', -1, 'trim,intval');
//
//        if(empty($user_id)){
//            $this->ajaxReturn(['Code' => 0, 'Msg' => '没有获取到用户ID']);
//        }
//
//        $where = [
//            'trade.member_id' => $user_id,
//        ];
//
//        if($currency_id >= 0){
//            $where['trade.currency_id'] = $currency_id;
//        }
//
//        $model = M('trade');
//        $fields = "trade.trade_id,trade.trade_no,trade.num,trade.price,trade.money,round(ifnull(trade.fee, '0.0000'), 4) fee,(case trade.type when 'buy' then '买入' when 'sell' then '卖出' when 'onebuy' then '一积分购' else '未知状态' end) as type_name,(case trade.add_time when 0 then '-' else from_unixtime(trade.add_time, '%Y-%m-%d %H:%i:%s') end) as add_time,trade.currency_id,member.email";
//
//        $trade_list = $model->alias('trade')->field($fields)
//            ->join('LEFT JOIN yang_member as member on trade.member_id = member.member_id ')
//            ->where($where)->order('trade.add_time desc')->select();
//
//        if(!empty($trade_list)){
//            $currency_model = M("currency");
//            foreach ($trade_list as &$value){
//                $value['currency_name'] = intval($value['currency_id']) === 0 ? "人民币" : $currency_model->field('currency_id,currency_name')->where(['currency_id' => $value['currency_id']])->find()['currency_name'];
//                unset($value['currency_id']);
//            }
//        }
//
//        $trade_buy_count = $model->alias('trade')->field("round(ifnull(sum(trade.num), '0.0000'), 4) as buynum,round(ifnull(sum(trade.money), '0.0000'), 4) as buymoney")->join('LEFT JOIN yang_currency AS currency ON trade.currency_id = currency.currency_id')->where($where)->where(['trade.type' => 'buy'])->find();
//        $trade_sell_count = $model->alias('trade')->field("round(ifnull(sum(trade.num), '0.0000'), 4) as sellnum,round(ifnull(sum(trade.money), '0.0000'), 4) as sellmoney")->join('LEFT JOIN yang_currency AS currency ON trade.currency_id = currency.currency_id')->where($where)->where(['trade.type' => 'sell'])->find();
//
//        $result = [
//            'currency_list' => $this->user_currency_list(),
//            'trade_list' => $trade_list,
//            'trade_count' => [
//                'buynum' => $trade_buy_count['buynum'],
//                'buymoney' => $trade_buy_count['buymoney'],
//                'sellnum' => $trade_sell_count['sellnum'],
//                'sellmoney' => $trade_sell_count['sellmoney'],
//            ],
//            'currency_id' => $currency_id,
//        ];
//        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
//    }

    /**
     * 提积分记录
     */
    public function user_turn_out_currency()
    {
        $user_id = I('post.user_id');
        $currency_id = I('post.currency_id', -1, 'trim,intval');

        if (empty($user_id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '没有获取到用户ID']);
        }

        $where = [
            'tibi.from_member_id' => $user_id,
            'tibi.status' => ['in', [0, 1]],
        ];

        if ($currency_id >= 0) {
            $where['tibi.currency_id'] = $currency_id;
        }
        $where['tibi.transfer_type'] = "1";
        $model = Db::name('tibi');
        $fields = "tibi.id,(case tibi.status when 0 then '提积分中' when 1 then '提积分成功' when 2 then '充值中' when 3 then '充值成功' end) status,tibi.to_url,round(tibi.num, 4) num,round(tibi.actual, 4) `actual`,tibi.currency_id,(case tibi.add_time when 0 then '-' else from_unixtime(tibi.add_time, '%Y-%m-%d %H:%i:%s') end) as add_time,m.email,tibi.message1,tibi.message2,tibi.ti_id";

        $tibi_list = $model->alias('tibi')->field($fields)
            ->join(config("database.prefix")."member m","m.member_id=tibi.from_member_id","LEFT")
            ->where($where)->order("tibi.add_time desc")->select();

        if (!empty($tibi_list)) {
            $currency_model = Db::name("currency");
            foreach ($tibi_list as &$value) {
                $value['currency_name'] = intval($value['currency_id']) === 0 ? "人民币" : $currency_model->field('currency_id,currency_name')->where(['currency_id' => $value['currency_id']])->find()['currency_name'];
                unset($value['currency_id']);
            }
        }

        $tibi_count = $model->alias('tibi')->field("round(ifnull(sum(tibi.actual), '0.0000'), 4) as totalcurrency")
            ->join(config("database.prefix")."currency c","c.currency_id=tibi.currency_id","LEFT")
            ->where($where)->find();

        $result = [
            'currency_list' => $this->user_currency_list(),
            'tibi_list' => $tibi_list,
            'tibi_count' => [
                'totalcurrency' => $tibi_count['totalcurrency'],
            ],
            'currency_id' => $currency_id,
        ];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    /**
     * 充积分记录
     */
    public function user_turn_on_currency()
    {
        $user_id = I('post.user_id');
        $currency_id = I('post.currency_id', -1, 'trim,intval');

        if (empty($user_id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '没有获取到用户ID']);
        }

        $where = [];

        if ($currency_id >= 0) {
            $where['tibi.currency_id'] = $currency_id;
        }

        $model = Db::name('tibi');
        $fields = "tibi.id,(case tibi.status when 0 then '提积分中' when 1 then '提积分成功' when 2 then '充值中' when 3 then '充值成功' end) status,tibi.from_url,tibi.num,tibi.actual,tibi.currency_id,(case tibi.add_time when 0 then '-' else from_unixtime(tibi.add_time, '%Y-%m-%d %H:%i:%s') end) as add_time,m.email,tibi.message1,tibi.message2,tibi.ti_id";

        $tibi_list = $model->alias('tibi')->field($fields)
            ->join(config("database.prefix")."member m","m.member_id=tibi.to_member_id","LEFT")
            ->where(['tibi.to_member_id' => $user_id, 'tibi.status' => ['in', [2, 3]]])->where($where)->order("tibi.add_time desc")->select();

        if (!empty($tibi_list)) {
            $currency_model = Db::name("currency");
            foreach ($tibi_list as &$value) {
                $value['currency_name'] = intval($value['currency_id']) === 0 ? "人民币" : $currency_model->field('currency_id,currency_name')->where(['currency_id' => $value['currency_id']])->find()['currency_name'];
               if($value['currency_name']=="XRP"){
                   $value['ti_id']=strtoupper($value['ti_id']);
               }
                unset($value['currency_id']);
            }
        }

        $tibi_count = $model->alias('tibi')->field("round(ifnull(sum(tibi.num), '0.0000'), 4) as totalnum,round(ifnull(sum(tibi.actual), '0.0000'), 4) as totalactual")
           ->join(config("database.prefix")."currency c","c.currency_id=tibi.currency_id","LEFT")
            ->where(['tibi.to_member_id' => $user_id, 'tibi.status' => 3])->where($where)->find();

        $result = [
            'currency_list' => $this->user_currency_list(),
            'tibi_list' => $tibi_list,
            'tibi_count' => [
                'totalnum' => $tibi_count['totalnum'],
                'totalactual' => $tibi_count['totalactual'],
            ],
            'currency_id' => $currency_id,
        ];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    /**
     * 添加会员
     */
    public function addMember()
    {
        if ($_POST) {
            $_POST['ip'] = get_client_ip_extend();
            $_POST['reg_time'] = time();
                if ($_POST['pwd'] == $_POST['pwdtrade']) {
                    $this->error('支付密码不能和密码一样');
                    return;
                }
                Db::startTrans();
                try{

                    $member=new \app\common\model\Member();
                    $_POST['pwd']=$member->password($_POST['pwd']);
                    $_POST['pwdtrade']=$member->password($_POST['pwdtrade']);
                    $member->allowField(true)->save($_POST);
                    $insert_id=$member->member_id;
                    if ($insert_id) {
                        //添加到role表
                        $add = [
                            'member_id' => $insert_id,
                            'pid' => $_POST['pid']
                        ];
                       $add_result= Db::name('role')->insert($add);
                        if (!$add_result) {
                            throw new Exception("服务器繁忙,请稍后重试1");
                        }
                        Db::commit();
                        $this->success('添加成功', url('Member/index'));
                        return;
                    } else {
                        throw new Exception("服务器繁忙,请稍后重试2");
                    }
                }catch (Exception $exception){
                    Db::rollback();
                    return $this->error($exception->getMessage());
                }



        } else {
            return $this->fetch();
        }
    }

    /**
     * 添加个人信息
     */
    public function saveModify()
    {
        $member_id = input('member_id', '', 'intval');
        $M_member = Db::name('Member');
        if ($_POST) {
                $_POST['status'] = 1;//0=有效但未填写个人信息1=有效并且填写完个人信息2=禁用
                $where['member_id'] = $_POST['member_id'];
                $r = $M_member->where($where)->update($_POST);
                if ($r) {
                    $this->success('添加成功', url('Member/index'));
                    return;
                } else {
                    $this->error('服务器繁忙,请稍后重试');
                    return;
                }
        } else {
            $where['member_id'] = $member_id;
            $list = $M_member->where($where)->find();
            $this->assign('list', $list);
           return $this->fetch();
        }
    }

    /**
     * 显示自己推荐列表
     */
    public function show_my_invit(Request $request)
    {
        $member_id = input("member_id");
        if (empty($member_id)) {
            $this->error('参数错误');
            return;
        }

        $my_invit = Db::name('Member')
            ->where(['pid'=>$member_id])
            ->order("reg_time desc")
            ->paginate(15,null,['query'=>$request->get()]);
        $show=$my_invit->render();
        if ($my_invit) {
            $this->assign('my_invit', $my_invit);
            $this->assign('page', $show);// 赋值分页输出
           return $this->fetch(); // 输出模板
        } else {
            $this->error('抱歉,您还没有推荐其他人');
            return;
        }
    }

    /**
     * 实名认证审核
     */
    public function member_verify()
    {
        $model = Db::name('verify_file');
        if ($_POST) {
            $member_id = input("post.id");
            $verify_state = input("post.type", 1, 'intval');
            $info = $model->where(['member_id' => $member_id])->select();
            if (empty($info)) $this->ajaxReturn(['Code' => 0, 'Msg' => "操作失败"]);
            $r = $this->verify($verify_state, $info);
            $this->ajaxReturn(['Code' => $r['code'], 'Msg' => $r['message']]);
        } else {
            $where['vf.verify_state'] = input("verify_state", 2, 'intval');
            $type = $where['vf.verify_state'];
            $mid=input("member_id", '', 'trim');
            if (!empty($mid)) {
                $where['vf.member_id'] = $mid;
            }

            $email = input('email');
            $name = input('name');
            $phone =input('phone');
            $member_id = intval(input('member_id'));

            if (!empty($email)) {
                $where['m.email'] = $email;
            }
            if (!empty($name)) {
                $where['m.name'] = $name;
            }
            if (!empty($phone)) {
                $where['m.phone'] = $phone;
            }
            if(!empty($member_id)) {
                $where['vf.member_id'] = $member_id;
            }
            $where['vf.cardtype'] = array('in', [1, 2, 5]);
            $field = "m.member_id,m.email,m.phone,vf.name,vf.idcard,vf.pic1,vf.pic2,vf.pic3,vf.addtime,
            vf.passport_img,vf.license_img,vf.verify_state,vf.country_code,vf.sex,vf.nation_id,vf.cardtype";
            $list = $model->alias("vf")
                ->join(config("database.prefix")."member m","m.member_id=vf.member_id")
                ->field($field)
                ->where($where)
                ->order("addtime desc")
                ->paginate(25,null,['query'=>input()])->each(function ($item,$key){
                    $nation['nation_name'] = '无';
                    $country['cn_name'] = '无';
                    if (!empty($item['nation_id'])) {
                        $nation = Db::name('Nation')->field('nation_name')->where(['nation_id' => $item['nation_id']])->find();
                    }
                    if (!empty($item['country_code'])) {
                        $country = Db::name('CountriesCode')->field('tc_name,cn_name')->where(['phone_code' => $item['country_code']])->find();
                    }
                    $item['nation_id'] = isset($nation['nation_name'])?$nation['nation_name']:"";
                    $item['country_code'] = isset($country['cn_name'])?$country['cn_name']:"";
                    $item['sex'] = str_replace(array(0, 1, 2), array('无', '男', '女'), $item['sex']);
                    $item['cardtype'] = str_replace(array(0, 1, 2, 5), array('无', '身份证', '护照', '驾照'), $item['cardtype']);
                    return $item;
                });
            $show=$list->render();
            $this->assign('type', $type);
            $this->assign('list', $list);
            $this->assign('page', $show);// 赋值分页输出
            return $this->fetch(); // 输出模板
        }
    }

    //批量实名验证审核@标
    public function review()
    {


        $verify_state = input("post.type", 1, 'intval');
        $arr_id =$_POST['list'];
        if (empty($arr_id)) {
            $arr_id[] = 0;
        }
        $where['member_id'] = ['in', $arr_id];
        $info = Db::name('verify_file')->where($where)->select();
        if (empty($info)) $this->ajaxReturn(['Code' => 0, 'Msg' => "操作失败"]);
        $r = $this->verify($verify_state, $info);
        $this->ajaxReturn(['Code' => $r['code'], 'Msg' => $r['message']]);
    }

    private function verify($verify_state, $info = array())
    {
        $r['code'] = 0;
        $r['message'] = "审核失败";
        if (!in_array($verify_state, [0, 1])) {
            $r['code'] = 0;
            $r['message'] = "参数错误";
            return $r;
        }
        try {
            Db::startTrans();
            foreach ($info as $key => $val) {
                $update_data1 = Db::name('verify_file')->where(['member_id' => $val['member_id']])->update(['verify_state' => $verify_state,'admin_id'=>$this->admin['admin_id']]);
                if (!$update_data1) {
                    throw new Exception('审核失败');
                }
                if ($verify_state == 1) {
                    if ($val['sex'] == 2) {
                        $val['sex'] = 0;
                    }
                    $data = [
                        'name' => $val['name'],
                        'cardtype' => $val['cardtype'],
                        'idcard' => $val['idcard'],
                        'verify_time' => time(),
                        'gender' => $val['sex'],
                        'nation_id' => $val['nation_id'],
                        'country_id' => $val['country_id']
                    ];
                    $update_data2 = Db::name('member')->where(['member_id' => $val['member_id']])->update($data);
                    if (!$update_data2) {
                        throw new Exception('审核失败');
                    }

                    // 认证成功赠送代金券
                    $config = model('config')->byField();
                    if(!empty($config['is_gift_voucher'])) {
                        $voucher_config = Db::name('voucher_config')->where(['id'=>$config['voucher_register_id']])->find();
                        if($config['voucher_num'] > 0) {
                            $data = [];
                            for($i = 0; $i < $config['voucher_num']; $i++) {
                                $data[$i] = [
                                    'member_id' => $val['member_id'],
                                    'voucher_id' => $voucher_config['id'],
                                    'cny' => $voucher_config['cny'],
                                    'expire_time' => strtotime("+{$voucher_config['validity']} day"),
                                ];
                            }
                            $result = Db::name('voucher_member')->insertAll($data);
                            if(!$result) throw new Exception(lang('lan_network_busy_try_again'));
                        }
                    }
                }
            }
            Db::commit();
            $r['message'] = "审核成功";
            $r['code'] = 1;
        } catch (Exception $e) {
            Db::rollback();
            $r['message'] = $e->getMessage();
        }

        return $r;

    }

    /**
     * 视频认证审核
     */
    public function member_video_verify()
    {
        $model = M('verify_file');
        if (IS_POST) {
            $member_id = I("post.id");
            $verify_state = I("post.type", 1);

            $update = true;
            if ($verify_state == 1) {
                $update = $model->where(['member_id' => $member_id])->save(['video_verify_state' => $verify_state, 'addtime' => time()]);
            }

            if ($verify_state == 2) {
                $update = $model->where(['member_id' => $member_id])->delete();
            }

            $jpush = A("Api/Jpush");
            if ($update === false) {
                //推送到APP
                $jpush->index('video_certification', 'err', $member_id);

                $this->ajaxReturn(['Code' => 0, 'Msg' => "审核失败"]);
            }

            //推送到APP
            $jpush->index('video_certification', 'suc', $member_id);

            $this->ajaxReturn(['Code' => 1, 'Msg' => "审核成功"]);
        } else {
            //删除无效信息
//            $delete_empty_sql = "delete from ".C("DB_PREFIX")."verify_file where member_id = 0";
//            $model->query($delete_empty_sql);

            $where[] = "length(trim(verify_file.video)) > 0";
            $where['verify_file.verify_state'] = 1;
            $where['verify_file.video_verify_state'] = I("get.video_verify_state", 0, 'intval');

            if (!empty(I("get.member_id", '', 'trim'))) {
                $where['verify_file.member_id'] = I("get.member_id");
            }

            $email = I('email');
            $name = I('name');
            $phone = I('phone');

            if (!empty($email)) {
                $where['member.email'] = array('like', '%' . $email . '%');
            }
            if (!empty($name)) {
                $where['member.name'] = array('like', '%' . $name . '%');
            }
            if (!empty($phone)) {
                $where['member.phone'] = array('like', '%' . $phone . '%');
            }

            $count = $model->alias("verify_file")->join("left join " . C("DB_PREFIX") . "member as member on member.member_id = verify_file.member_id")->where($where)->count();// 查询满足要求的总记录数
            $Page = new Page($count, 25);// 实例化分页类 传入总记录数和每页显示的记录数(25)

            //给分页传参数
            setPageParameter($Page, ['video_verify_state' => $where['verify_file.video_verify_state']]);

            $show = $Page->show();// 分页显示输出
            // 进行分页数据查询 注意limit方法的参数要使用Page类的属性

            $field = "member.member_id,member.email,member.phone,member.name,member.idcard,verify_file.video_verify_state,verify_file.video,verify_file.addtime";
            $list = $model->alias("verify_file")
                ->join("left join " . C("DB_PREFIX") . "member as member on member.member_id = verify_file.member_id")
                ->field($field)
                ->where($where)
                ->order("addtime desc")
                ->limit($Page->firstRow . ',' . $Page->listRows)->select();

            $this->assign('type', $where['verify_file.video_verify_state']);
            $this->assign('list', $list);
            $this->assign('page', $show);// 赋值分页输出
            $this->display(); // 输出模板
        }
    }

    /**
     * 修改会员
     */
    public function saveMember()
    {
        $member_id = input('member_id', '', 'intval');
        $M_member = Db::name('Member');
        if ($_POST) {
            $member=new \app\common\model\Member();
            $member_id = input('member_id', '', 'intval');
            $where['member_id'] = $member_id;
            $list = $M_member->where($where)->find();
            //头像上传
//            $upload = new Upload();// 实例化上传类
//            $upload->maxSize   =     3145728 ;// 设置附件上传大小
//            $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
//            $upload->rootPath  =     './Uploads/'; // 设置附件上传根目录
//            $upload->savePath  =     'Member/Head/'; // 设置附件上传（子）目录
            // 上传文件
            if (!$_FILES['head']['error']) {
                $file_path = $this->upload($_FILES["head"]);
            }
            $_POST['head'] = empty($file_path) ? input('headold') : $file_path;
            //头像上传end
            if ($_POST['pwd'] == $_POST['pwdtrade'] && $_POST['pwd'] != null) {
                $this->error('交易密码不能和密码一致');
                return;
            }
            if (!empty($_POST['nick']) && $_POST['nick'] != $list['nick']) {
                $where = null;
                $where['member_id'] = array('NEQ', $member_id);
                $where['nick'] = $_POST['nick'];
                if ($M_member->field('nick')->where($where)->select()) {
                    $this->error('昵称重复');
                    return;
                }
            }
            if ($_POST['phone'] != $list['phone']) {
                $where = null;
                $where['member_id'] = array('NEQ', $member_id);
                $where['phone'] = $_POST['phone'];
                if ($M_member->field('phone')->where($where)->select()) {
                    $this->error('手机号重复');
                    return;
                }
            }

            if ($_POST['email'] != $list['email']) {
                $where = [];
                $where['email'] = $_POST['email'];
                if ($M_member->field('email')->where($where)->select()) {
                    $this->error('邮箱重复');
                    return;
                }
            }

            $pwd = input('pwd', '');
            $_POST['pwd'] = !empty($pwd) ? $member->password($pwd) : $list['pwd'];
            if(!empty($pwd)) $_POST['pwd_error'] = 0;

            $pwdtrade = input('pwdtrade', '');
            $_POST['pwdtrade'] = !empty($pwdtrade) ? $member->password($pwdtrade) : $list['pwdtrade'];
            if(!empty($pwdtrade)) $_POST['pwdtrade_error'] = 0;
            $_POST['declara_time'] = time();
            $_POST['declara_id'] = time() + 20160504;

            $verify_state = input("verify_state", 0, 'intval');
            $video_verify_state = input("post.video_verify_state", 0, 'intval');
            unset($_POST['verify_state']);
            unset($_POST['video_verify_state']);


            $r=$member->allowField(true)->isUpdate(true,['member_id'=>$member_id])->save($_POST);
            if ($r) {
                //禁用用户则清空登录token
                if ($_POST['status'] == 2) {
                    cache('auto_login_' . $member_id, null);//清除app的登录信息
                    cache('pc_auto_login_' . $member_id, null);//清除pc的登录信息
                }
                $verify_file = Db::name('verify_file');
                $info = $verify_file->where(['member_id' => $member_id])->find();
                $verify['verify_state'] = $verify_state;
                $verify['video_verify_state'] = $video_verify_state;
                $verify['addtime'] = time();
                if (empty($info)) {
                    $verify['member_id'] = $member_id;
                    $verify_file->insert($verify);
                } else {
                    $verify_file->where(['member_id' => $member_id])->update($verify);
                }

                //exit("<script>alert('修改成功');history.go(-2);</script>");
              return  $this->success('修改成功', url('member/index'));
            } else {
                $this->error('修改失败');
                return;
            }
        } else {
            if ($member_id) {
                $where['m.member_id'] = $member_id;
                $field = "m.*,ifnull(vf.verify_state, 0) as verify_state";
                $list = $M_member->alias('m')
                    ->join(config("database.prefix")."verify_file vf","vf.member_id=m.member_id","LEFT")
                    ->field($field)->where($where)->find();
                $this->assign('list', $list);
               return $this->fetch();
            } else {
                $this->error('参数错误');
                return;
            }
        }
    }

    //is_md5 是否已经md5一次
    public function passwordmd5($value, $is_md5 = false)
    {
        if (!$is_md5) $value = md5($value);
        return md5(substr(md5($value . password_halt), 8));
    }

    /**
     * 删除会员
     */
    public function delMember()
    {
        $member_id = I('get.member_id', '', 'intval');
        $M_member = M('Member');
        //判断还有没有余额
        $where['member_id'] = $member_id;
        $member = $M_member->where($where)->find();
        $member_currency = M('Currency_user')->where($where)->find();
        if (!empty($member['rmb']) || !empty($member['forzen_rmb']) || !empty($member_currency['num']) || !empty($member_currency['forzen_num'])) {
            $this->error('因账户有剩余余额,禁止删除');
            return;
        }
        $r[] = $M_member->delete($member_id);
        $r[] = M('Currency_user')->where($where)->delete();
        $r[] = M('Finance')->where($where)->delete();
        $r[] = M('Orders')->where($where)->delete();
        $r[] = M('Trade')->where($where)->delete();
        $r[] = M('Withdraw')->where('uid=' . $member_id)->delete();
        $r[] = M('Pay')->where($where)->delete();
        if ($r) {
            $this->success('删除成功', U('Member/index'));
            return;
        } else {
            $this->error('删除失败');
            return;
        }
    }

    /**
     * ajax判断邮箱
     * @param $email
     */
    public function ajaxCheckEmail($email)
    {
        $email = urldecode($email);
        $data = array();
        if (!checkEmail($email)) {
            $data['status'] = 0;
            $data['msg'] = "邮箱格式错误";
        } else {
            $M_member = Db::name('Member');
            $where['email'] = $email;
            $r = $M_member->where($where)->find();
            if ($r) {
                $data['status'] = 0;
                $data['msg'] = "邮箱已存在";
            } else {
                $data['status'] = 1;
                $data['msg'] = "";
            }
        }
        return $this->ajaxReturn($data);
    }

    /**
     * ajax验证昵称是否存在
     */
    public function ajaxCheckNick($nick)
    {
        $nick = urldecode($nick);
        $data = array();
        $M_member = Db::name('Member');
        $where['nick'] = $nick;
        $r = $M_member->where($where)->find();
        if ($r) {
            $data['msg'] = "昵称已被占用";
            $data['status'] = 0;
        } else {
            $data['msg'] = "";
            $data['status'] = 1;
        }
        return $this->ajaxReturn($data);
    }

    /**
     * ajax手机验证
     */
    function ajaxCheckPhone($phone)
    {
        $phone = urldecode($phone);
        $data = array();
        if (!checkMobile($phone)) {
            $data['msg'] = "手机号不正确！";
            $data['status'] = 0;
        } else {
            $M_member = Db::name('Member');
            $where['phone'] = $phone;
            $r = $M_member->where($where)->find();
            if ($r) {
                $data['msg'] = "此手机已经绑定过！请更换手机号";
                $data['status'] = 0;
            } else {
                $data['msg'] = "";
                $data['status'] = 1;
            }
        }
        return $this->ajaxReturn($data);
    }

    /**
     * 查看个人积分类型
     */
    public function show()
    {
        $currency = Db::name('Currency_user');
        $member = Db::name('Member');
        $member_id = input('member_id');
        if (empty($member_id)) {
            $this->error('参数错误', url('Member/index'));
        }
        $where['member_id'] = $member_id;
        $info = $currency->alias("cu")->join(config("database.prefix")."currency c","c.currency_id=cu.currency_id","LEFT")
            ->where($where)->paginate(100);
        $show=$info->render();
        $member_info = $member->field('member_id,name,phone,email')->where($where)->find();
        $this->assign('member_info', $member_info);
        $this->assign('info', $info);
        $this->assign('page', $show);
       return $this->fetch();
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
            ->join("yang_areas as a ON a.area_id = b.parent_id")
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
     * 通过ID完成奖励
     * @param unknown $id
     */
    public function successByid()
    {
        $id = intval(I('post.id'));
        //判断是否$id为空
        if (empty ($id)) {
            $datas['status'] = 3;
            $datas['info'] = "参数错误";
            $this->ajaxReturn($datas);
        }
        $where_pass['member_id'] = $id;
        $pass = M('Member')->where($where_pass)->find();

        //判断是否已经奖励
        if ($pass['is_award']) {
            $datas['status'] = 7;
            $datas['info'] = "已经奖励";
            $this->ajaxReturn($datas);
        }

        //判断是否已经奖励
        if (empty ($pass['status'])) {
            $datas['status'] = 8;
            $datas['info'] = "还没验证,不给通过";
            $this->ajaxReturn($datas);
        }
        //是否开启奖励机制

        //$config=$this->config;
        $config = M('Config');
        $coin_number = $config->where('id=77')->getField('value');
        $is_award = $config->where('id=82')->getField('value');
        $coin_currency = $config->where('id=78')->getField('value');
        $one_percentage = $config->where('id=79')->getField('value');
        $two_percentage = $config->where('id=80')->getField('value');
        $three_percentage = $config->where('id=81')->getField('value');
        if ($is_award) {
            //验证与不验证
            $where_null['member_id_one'] = $id;
            $not_null = M('Currency_user_num_award')->where($where_null)->find();

            if ($not_null) {
                //一层奖励
                $where['member_id'] = $id;
                $data_one = M('Member')->where($where)->find();
                $data_one_pid = $data_one['pid'];
                if ($data_one_pid) {
                    //计算分成积分数
                    $currency_user = M('Currency_user');
                    $where['member_id'] = $data_one_pid;
                    $where['currency_id'] = $coin_currency;
                    $coin = ($coin_number) * ($one_percentage) * 0.01;

                    $currency_user->where($where)->setInc('num_award', $coin);
                    $currency_user->where($where)->setInc('sum_award', $coin);
                    //添加一层记录
                    $data1_where['member_id_one'] = $id;
                    $data1_where['tier'] = 1;
                    // $data1['member_id']=$data_one_pid;
                    // $data1['member_id_superior']=$id;
                    // $data1['member_id_one']=$id;
                    // $data1['currency_id']=$coin_currency;
                    //$data1['tier']='1';
                    $data1['num_award'] = $coin;
                    //  $data1['add_time']=time();
                    $re = M('Currency_user_num_award')->where($data1_where)->save($data1);

                    //查询上层邀请人
                    $where2['member_id'] = $data_one_pid;
                    $data_two = M('Member')->where($where2)->find();
                    $data_two_pid = $data_two['pid'];
                    //二层奖励
                    if ($data_two_pid) {


                        //计算分成积分数
                        $currency_user = M('Currency_user');
                        $where_two1['member_id'] = $data_two_pid;
                        $where_two1['currency_id'] = $coin_currency;


                        $coin1 = ($coin_number) * ($two_percentage) * 0.01;

                        $currency_user->where($where_two1)->setInc('num_award', $coin1);
                        $currency_user->where($where_two1)->setInc('sum_award', $coin1);
                        //添加二层记录
                        $data2_where['member_id_one'] = $id;
                        $data2_where['tier'] = 2;
                        //$data2['member_id']=$data_two_pid;
                        // $data2['member_id_superior']=$data_one_pid;
                        // $data2['member_id_one']=$id;
                        // $data2['currency_id']=$coin_currency;
                        // $data2['tier']='2';
                        $data2['num_award'] = $coin1;
                        //$data2['add_time']=time();
                        // $data2['member_id_two']=$data_one_pid;
                        $re = M('Currency_user_num_award')->where($data2_where)->save($data2);


                        //查询上层邀请人
                        $where_two2['member_id'] = $data_two_pid;
                        $data_three = M('Member')->where($where_two2)->find();
                        $data_three_pid = $data_three['pid'];
                        //三层奖励
                        if ($data_three_pid) {


                            //计算分成积分数
                            $currency_user = M('Currency_user');
                            $where_three1['member_id'] = $data_three_pid;
                            $where_three1['currency_id'] = $coin_currency;

                            $coin2 = ($coin_number) * ($three_percentage) * 0.01;
                            //添加三层记录
                            $data3_where['member_id_one'] = $id;
                            $data3_where['tier'] = 3;
                            //  $data3['member_id']=$data_three_pid;
                            //   $data3['member_id_superior']=$data_two_pid;
                            //  $data3['member_id_one']=$id;
                            // $data3['currency_id']=$coin_currency;
                            //  $data3['tier']='3';
                            $data3['num_award'] = $coin2;
                            //  $data3['member_id_two']=$data_one_pid;
                            //  $data3['member_id_three']=$data_two_pid;
                            //  $data3['add_time']=time();
                            $re = M('Currency_user_num_award')->where($data3_where)->save($data3);

                            $currency_user->where($where_three1)->setInc('num_award', $coin2);
                            $currency_user->where($where_three1)->setInc('sum_award', $coin2);
                            //查询上层邀请人
                            /* $where2['member_id']= $data_one_pid;
	                         $data_three=M('Member')->where($where2)->find();
	                        $data_three_pid=$data_three['pid'];*/

                        }
                    }
                }


            } else {
                //一层奖励
                $where['member_id'] = $id;
                $data_one = M('Member')->where($where)->find();
                $data_one_pid = $data_one['pid'];
                if ($data_one_pid) {
                    //计算分成积分数
                    $currency_user = M('Currency_user');
                    $where['member_id'] = $data_one_pid;
                    $where['currency_id'] = $coin_currency;
                    $coin = ($coin_number) * ($one_percentage) * 0.01;

                    $currency_user->where($where)->setInc('num_award', $coin);
                    $currency_user->where($where)->setInc('sum_award', $coin);
                    //添加一层记录
                    $data1['member_id'] = $data_one_pid;
                    $data1['member_id_superior'] = $id;
                    $data1['member_id_one'] = $id;
                    $data1['currency_id'] = $coin_currency;
                    $data1['tier'] = '1';
                    $data1['num_award'] = $coin;
                    $data1['add_time'] = time();
                    $re = M('Currency_user_num_award')->data($data1)->add();

                    //查询上层邀请人
                    $where2['member_id'] = $data_one_pid;
                    $data_two = M('Member')->where($where2)->find();
                    $data_two_pid = $data_two['pid'];
                    //二层奖励
                    if ($data_two_pid) {


                        //计算分成积分数
                        $currency_user = M('Currency_user');
                        $where_two1['member_id'] = $data_two_pid;
                        $where_two1['currency_id'] = $coin_currency;


                        $coin1 = ($coin_number) * ($two_percentage) * 0.01;

                        $currency_user->where($where_two1)->setInc('num_award', $coin1);
                        $currency_user->where($where_two1)->setInc('sum_award', $coin1);
                        //添加二层记录
                        $data2['member_id'] = $data_two_pid;
                        $data2['member_id_superior'] = $data_one_pid;
                        $data2['member_id_one'] = $id;
                        $data2['currency_id'] = $coin_currency;
                        $data2['tier'] = '2';
                        $data2['num_award'] = $coin1;
                        $data2['add_time'] = time();
                        $data2['member_id_two'] = $data_one_pid;
                        $re = M('Currency_user_num_award')->data($data2)->add();


                        //查询上层邀请人
                        $where_two2['member_id'] = $data_two_pid;
                        $data_three = M('Member')->where($where_two2)->find();
                        $data_three_pid = $data_three['pid'];
                        //三层奖励
                        if ($data_three_pid) {


                            //计算分成积分数
                            $currency_user = M('Currency_user');
                            $where_three1['member_id'] = $data_three_pid;
                            $where_three1['currency_id'] = $coin_currency;

                            $coin2 = ($coin_number) * ($three_percentage) * 0.01;
                            //添加三层记录
                            $data3['member_id'] = $data_three_pid;
                            $data3['member_id_superior'] = $data_two_pid;
                            $data3['member_id_one'] = $id;
                            $data3['currency_id'] = $coin_currency;
                            $data3['tier'] = '3';
                            $data3['num_award'] = $coin2;
                            $data3['member_id_two'] = $data_one_pid;
                            $data3['member_id_three'] = $data_two_pid;
                            $data3['add_time'] = time();
                            $re = M('Currency_user_num_award')->data($data3)->add();

                            $currency_user->where($where_three1)->setInc('num_award', $coin2);
                            $currency_user->where($where_three1)->setInc('sum_award', $coin2);
                            //查询上层邀请人
                            /* $where2['member_id']= $data_one_pid;
	                     $data_three=M('Member')->where($where2)->find();
	                    $data_three_pid=$data_three['pid'];*/

                        }
                    }
                }
            }
        }
        //通过状态为2
        $data['is_award'] = 1;
        //$data ['check_time'] = time();
        //  $data ['admin_uid'] =$_SESSION['admin_userid'];
        //更新数据库
        $re = M('Member')->where("member_id = '{$id}'")->save($data);


        if ($re == false) {
            $datas['status'] = 0;
            $datas['info'] = "操作失败";
            $this->ajaxReturn($datas);
        }

        $datas['status'] = 1;
        $datas['info'] = "操作成功";
        $this->ajaxReturn($datas);
    }

    /**
     * 不通过奖励
     * @param unknown $id
     */
    public function falseByid()
    {
        $id = intval(I('post.id'));
        //判断是否$id为空
        if (empty ($id)) {
            $this->error("参数错误");
            return;
        }

        //不通过状态为2
        $data ['is_award'] = 2;
        //更新数据库
        $res = M('Member')->where("member_id = '{$id}'")->save($data);

        if ($res == false) {
            $datas['status'] = 2;
            $datas['info'] = "不通过，操作失败";
            $this->ajaxReturn($datas);
        }

        $datas['status'] = 1;
        $datas['info'] = "不通过，操作成功";
        $this->ajaxReturn($datas);
    }

    public function orderList()
    {
        $type = I('type', '', 'intval');
        $order_sn = I('order_sn');
        $member_id = I('user_id', '', 'intval');
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
        $where['o.status'] = 2;
        $count = M('c2c_order')->alias('o')->where($where)->count();// 查询满足要求的总记录数
        $Page = new Page($count, 15);// 实例化分页类 传入总记录数和每页显示的记录数(25)

        //给分页传参数
        setPageParameter($Page, array('type' => $type, 'order_sn' => $order_sn, 'member_id' => $member_id, 'status' => $status));

        $show = $Page->show();// 分页显示输出

        $field = "o.*,c.currency_mark";
        $order = M('c2c_order')->alias('o')
            ->join("left join " . C("DB_PREFIX") . "currency as c on c.currency_id = o.currency_id")
            ->field($field)->where($where)->order("o.add_time desc ")
            ->select();
//            ->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $status = array('0' => '待处理', '1' => '收款成功', '2' => '打款成功', '-1' => '已取消');
        foreach ($order as &$val) {
            $val['admin_time'] = !empty($val['admin_time']) ? $val['admin_time'] : '';
            $val['_status'] = $status[$val['status']];
            $val['type'] = $val['type'] == 1 ? '买入' : '卖出';
        }
        $this->assign('order_list', $order);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('gettype', "ajax");// 赋值分页输出
        $tpl = $this->fetch();
        $this->ajaxReturn(['Code' => 1, 'Msg' => $tpl]);
    }

    //查看OTC广告交易记录
    public function orders_trade_log()
    {
        $membe_id = I('user_id');
        $currency_id = I('post.currency_id');
        $where['a.member_id'] = $membe_id;
        if ($currency_id > 0) {
            $where['a.currency_id'] = $currency_id;
        }
        $where['a.status'] = array('not in', [4]);
        //获取挂单记录
        $field = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $list = Db::name('Trade_otc')
            ->alias('a')
            ->field($field)
            ->join(config("database.prefix")."currency b","b.currency_id=a.currency_id","LEFT")
            ->join(config("database.prefix")."member c","c.member_id=a.member_id","LEFT")
            ->where($where)
            ->order("a.trade_id desc")
            ->select();
        if ($list) {
            foreach ($list as $key => $vo) {
                if ($vo['type'] == "buy") {
                    $list[$key]['change_num'] = $vo['num'] - $vo['fee'];
                } elseif ($vo['type'] == "sell") {
                    $list[$key]['change_num'] = -($vo['num'] + $vo['fee']);
                }
                $list[$key]['type_name'] = getOrdersType($vo['type']);
                $list[$key]['add_time'] = date('Y-m-d H:i:s', $vo['add_time']);
                $list[$key]['buy_payment'] = '';
                $list[$key]['sell_payment'] = '';
                $list[$key]['payment_type'] = '';
                //获取卖家银行卡信息
                if (!empty($vo['money_type'])) {
                    $payment = explode(':', $vo['money_type']);
                    if ($vo['type'] == 'buy') {
                        if ($payment[0] == 'bank') {
                            $model = Db::name('member_bank');
                            $re = $model->field('truename,bankname,bankadd,bankcard')->where(['id' => $payment[1]])->find();
                            $list[$key]['payment_type'] = '银行卡';
                            $list[$key]['sell_payment'] = $re['bankname'] . $re['bankadd'] . $re['bankcard'];
                            //买家默认账号
                            $buy_payment = $model->where(['member_id' => $vo['member_id'], 'status' => 1])->find();
                            $list[$key]['buy_payment'] = $buy_payment['bankname'] . $buy_payment['bankadd'] . $buy_payment['bankcard'];
                        } else if ($payment[0] == 'wechat') {
                            $model = Db::name('member_wechat');
                            $re = $model->field('truename,wechat')->where(['id' => $payment[1]])->find();
                            $list[$key]['payment_type'] = '微信';
                            $list[$key]['sell_payment'] = $re['wechat'];
                            //买家默认账号
                            $buy_payment = $model->where(['member_id' => $vo['member_id'], 'status' => 1])->find();
                            $list[$key]['buy_payment'] = $buy_payment['wechat'];
                        } else {
                            $model = Db::name('member_alipay');
                            $re = $model->field('truename,alipay')->where(['id' => $payment[1]])->find();
                            $list[$key]['payment_type'] = '支付宝';
                            $list[$key]['sell_payment'] = $re['alipay'];
                            //买家默认账号
                            $buy_payment = $model->where(['member_id' => $vo['member_id'], 'status' => 1])->find();
                            $list[$key]['buy_payment'] = $buy_payment['alipay'];
                        }

                    } else {
                        if ($payment[0] == 'bank') {
                            $model = Db::name('member_bank');
                            $re = $model->field('truename,bankname,bankadd,bankcard')->where(['id' => $payment[1]])->find();
                            $list[$key]['payment_type'] = '银行卡';
                            $list[$key]['sell_payment'] = $re['bankname'] . $re['bankadd'] . $re['bankcard'];
                            //买家默认账号
                            $buy_payment = $model->where(['member_id' => $vo['other_member'], 'status' => 1])->find();
                            $list[$key]['buy_payment'] = $buy_payment['bankname'] . $buy_payment['bankadd'] . $buy_payment['bankcard'];
                        } else if ($payment[0] == 'wechat') {
                            $model = Db::name('member_wechat');
                            $re = $model->field('truename,wechat')->where(['id' => $payment[1]])->find();
                            $list[$key]['payment_type'] = '微信';
                            $list[$key]['sell_payment'] = $re['wechat'];
                            //买家默认账号
                            $buy_payment = $model->where(['member_id' => $vo['other_member'], 'status' => 1])->find();
                            $list[$key]['buy_payment'] = $buy_payment['wechat'];
                        } else {
                            $model = Db::name('member_alipay');
                            $re = $model->field('truename,alipay')->where(['id' => $payment[1]])->find();
                            $list[$key]['payment_type'] = '支付宝';
                            $list[$key]['sell_payment'] = $re['alipay'];
                            //买家默认账号
                            $buy_payment = $model->where(['member_id' => $vo['other_member'], 'status' => 1])->find();
                            $list[$key]['buy_payment'] = $buy_payment ? $buy_payment['alipay'] : '';
                        }

                    }
                }

                if ($vo['status'] == 0) {
                    $list[$key]['status'] = '未付款';
                } else if ($vo['status'] == 1) {
                    $list[$key]['status'] = '待放行';
                } else if ($vo['status'] == 2) {
                    $list[$key]['status'] = '申诉中';
                } else if ($vo['status'] == 3) {
                    $list[$key]['status'] = '已完成';
                } else if ($vo['status'] == 4) {
                    $list[$key]['status'] = '已取消';
                }

            }
        }

        //交易记录
        $field1 = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $ordersInfo = Db::name('Orders_otc')
            ->alias('a')
            ->field($field1)
            ->join(config("database.prefix")."currency b","b.currency_id=a.currency_id","LEFT")
            ->join(config("database.prefix")."member c","c.member_id=a.member_id","LEFT")
            ->where($where)
            ->order("a.orders_id desc")
            ->select();
        if ($ordersInfo) {
            foreach ($ordersInfo as $key => $vo) {
                $ordersInfo[$key]['add_time']=date('Y-m-d H:i:s', $vo['add_time']);
                $ordersInfo[$key]['type_name'] = getOrdersType($vo['type']);
                if ($vo['status'] == 0) {
                    $ordersInfo[$key]['status'] = '未成交';
                } else if ($vo['status'] == 1) {
                    $ordersInfo[$key]['status'] = '部分成交';
                } else if ($vo['status'] == 2) {
                    $ordersInfo[$key]['status'] = '已成交';
                } else if ($vo['status'] == 3) {
                    $ordersInfo[$key]['status'] = '已撤销';
                }
            }
        }
        $result = ['order_list' => $ordersInfo, 'trade_list' => $list, 'currency_list' => $this->user_currency_list(), 'currency_id' => $currency_id];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);

    }

    /**
     * 赠送记录
     * Created by Red.
     * Date: 2019/1/15 11:22
     */
    public function rewardList()
    {
        $membe_id = I('user_id');
        $currency_id = I('post.currency_id');
        $where['member_id'] = $membe_id;
        if ($currency_id > 0) {
            $where['currency_id'] = $currency_id;
        }

        $finance = M('Currency_user_num_award');
        $currency = M('Currency_user');
        $list = $finance->where($where)->order('add_time desc')->select();
        foreach ($list as $k => $v) {
            $list[$k]['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
            $list[$k]['currency_id'] = getCurrencynameByCurrency($v['currency_id']);
            $list[$k]['tier'] = ($v['tier'] == 0) ? ' 注册奖励' : '邀请奖励';
        }
        //可用数量
        $re = $currency->field("round(ifnull(sum(num), '0.0000'), 4) as num,round(ifnull(sum(forzen_num), '0.0000'), 4) as forzen_num,round(ifnull(sum(num_award), '0.0000'), 4) as num_award,round(ifnull(sum(sum_award), '0.0000'), 4) as sum_award,round(ifnull(sum(lock_num), '0.0000'), 4) as lock_num,round(ifnull(sum(exchange_num), '0.0000'), 4) as exchange_num")->where($where)->find();
        $result = ['trade_list' => $list, 'currency_list' => $this->user_currency_list(), 'count' => $re, 'currency_id' => $currency_id];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    /**
     * 持币生息列表
     */
    public function money_interest()
    {
        $member_id = I('user_id');
        $currency_id = I('currency_id');


        $where['c.member_id'] = $member_id;
        if ($currency_id > 0) $where['a.currency_id'] = $currency_id;

        $field = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";


        $list = M('money_interest')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->order(" a.id desc ")
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
            $list[$k]['end_time'] = date('Y-m-d H:i:s', $v['end_time']);
            $list[$k]['phone'] = !empty($v['phone']) ? $v['phone'] : $v['email'];
            if ($v['status'] == 1) {
                $list[$k]['status'] = '已生息';
            } elseif ($v['status'] == 2) {
                $list[$k]['status'] = '已取消';
            } else {
                $list[$k]['status'] = '生息中';
            }

        }
        $where1['member_id'] = $member_id;
        if (!empty($currency_id)) $where1['currency_id'] = $currency_id;
        $sum = M('money_interest')->where($where1)->sum('num');
        //积分类型
        $currency = M('Currency')->field('currency_name,currency_id')->select();
        $result = ['trade_list' => $list, 'currency_list' => $currency, 'num' => $sum, 'currency_id' => $currency_id];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    /**
     * 内部互转
     * @throws Exception
     * Created by Red.
     * Date: 2019/1/15 18:13
     */
    function mutualTransfer()
    {
        $member_id = I('user_id');
        $currency_id = I('currency_id');
        $where=null;
        if (!empty($currency_id) && $currency_id > 0) {
            $where['t.currency_id'] = $currency_id;
        }
        $where['t.b_type'] = 0;
        $where['t.transfer_type'] = "2";//内部互转类型
        if (!empty($member_id)) {
            $where["t.from_member_id|t.to_member_id"]=$member_id;
//            $where['_string'] = ' (t.from_member_id =' . $member_id . ') OR ( t.to_member_id =' . $member_id . ')';
        }

        $field = "t.*,m.email,m.name,m.phone,c.currency_name,
        c.currency_type,m.remarks,tm.name as tname,tm.phone as tphone, tm.email as temail";

        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = Db::name("Tibi")->alias("t")->field($field)->where($where)
            ->join(config("database.prefix")."member m","m.member_id=t.from_member_id","LEFT")
            ->join(config("database.prefix")."member tm","tm.member_id=t.to_member_id","LEFT")
            ->join(config("database.prefix")."currency c","c.currency_id=t.currency_id","LEFT")
            ->order("add_time desc")->select();
        if (!empty($list)) {
            foreach ($list as &$value) {
                $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
                if ($value['from_member_id'] == $member_id) {
                    $value['num'] = -$value['num'];
                    $value['actual'] = -$value['actual'];
                }
            }
        }
        $result = ['currency_list' => $this->user_currency_list(), 'list' => $list, 'currency_id' => $currency_id];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);

    }

    /**
     * 用户帐本记录
     * Created by Red.
     * Date: 2019/1/16 19:48
     */
    function accountbook()
    {
        $type_list = [
            'flop' => ['name'=>'方舟','ids'=>[1000,1001,1002,1003,1005,1006,1007,1008,1009] ],
            'hongbao' => ['name'=>'锦鲤红包','ids'=>[950,951,952] ],
            'contract' => ['name'=>'合约','ids'=>[1100,1101,1102,1103,1104,1105,1106,1107,1108] ],
            'air' => ['name'=>'云梯','ids'=>[1400,1401,1402,1403,1404,1405,1406,1407] ],
        ];

        $member_id = I('user_id');
        $currency_id = I('currency_id');
        $type_get = input('type','');
        if($type_get && isset($type_list[$type_get])) {
            $where['type'] = ['in',$type_list[$type_get]['ids']];
        }

        $page=I("page",1);
        $rows=I("rows",10);
        if (!empty($currency_id) && $currency_id > 0) {
            $where['currency_id'] = $currency_id;
        }
        $where['member_id'] = $member_id;
        $count=Db::name("accountbook")->where($where)->count("id");
        // 实例化分页类 传入总记录数和每页显示的记录数
        $list = Db::name("accountbook")->where($where)->order("id desc")/*->page($page,$rows)*/->select();
        if(!$list) $list = [];
        $list2 = Db::name("accountbook_admin")->where($where)->order("id desc")/*->page($page,$rows)*/->select();
        if(!$list2) $list2 = [];

        foreach ($list2 as $item2) {
            $list[] = $item2;
        }
        $list2 =  [];

        if (!empty($list)) {
            $currencyList = $this->user_currency_list();
            $currencyList = array_column($currencyList, null, 'currency_id');
            $accounType = Db::name("accountbook_type")->field("id,name_tc")->select();
            $typeList = array_column($accounType, null, "id");
            foreach ($list as &$value) {
                $type = $value['type'];
                if($value['type']==24){
                    $value['ad_remark'] .= $value['ad_remark']."<a target='_blank' href='".U('BossPlan/bouns_detail',['today'=>date('Y-m-d',$value['add_time']),'member_id'=>$value['member_id']])."'>动态分红详情</a>";
                }
                $value['type'] = isset($typeList[$value['type']]) ?  $typeList[$value['type']]['name_tc'] : '';
                $value['currency_name'] = isset($currencyList[$value['currency_id']]) ? $currencyList[$value['currency_id']]['currency_name'] : '';
                $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
                $value['number'] = $value['number_type'] == 1 ? $value['number'] : -$value['number'];
                $value['after'] = bcadd($value['number'],$value['current'],8);
                $value['change'] = $value['number_type'] == 1 ? "收入" : "支出";
                $value['from_member_id'] = "";
                $value['from_phone'] = "";
                $value['from_email'] = "";
                $value['toMemberId'] = "";
                $value['to_phone'] = "";
                $value['to_email'] = "";
                $value['currency_pair'] = "";
                if ($value['third_id'] > 0 || is_numeric($value['content']) || $value['to_member_id'] > 0) {
                    switch ($type) {
                        case 5:
                            //充币类型
                            $tibi = Db::name("tibi")->where(['id' => $value['third_id']])->field("to_member_id,from_member_id,transfer_type")->find();
                            if ($tibi['transfer_type'] == "2") {
                                $value['type'] = "平台内" . $value['type'];
                            }
                            if (!empty($tibi)) {
                                if ($tibi['to_member_id'] > 0) {
                                    $toMember = $this->getMemberInfo($tibi['to_member_id']);
                                    $value['toMemberId'] = $tibi['to_member_id'];
                                    $value['to_phone'] = $toMember['phone'];
                                    $value['to_email'] = $toMember['email'];
                                }
                                if ($tibi['from_member_id'] > 0) {
                                    $fromMember = $this->getMemberInfo($tibi['from_member_id']);
                                    $value['from_member_id'] = $tibi['from_member_id'];
                                    $value['from_phone'] = $fromMember['phone'];
                                    $value['from_email'] = $fromMember['email'];
                                }
                            }
                            break;
                        case 6:
                            //提币类型
                            $tibi = Db::name("tibi")->where(['id' => $value['third_id']])->field("to_member_id,from_member_id,transfer_type")->find();
                            if ($tibi['transfer_type'] == "2") {
                                $value['type'] = "平台内" . $value['type'];
                            }
                            if (!empty($tibi)) {
                                if ($tibi['to_member_id'] > 0) {
                                    $toMember = $this->getMemberInfo($tibi['to_member_id']);
                                    $value['toMemberId'] = $tibi['to_member_id'];
                                    $value['to_phone'] = $toMember['phone'];
                                    $value['to_email'] = $toMember['email'];
                                }
                                if ($tibi['from_member_id'] > 0) {
                                    $fromMember = $this->getMemberInfo($tibi['from_member_id']);
                                    $value['from_member_id'] = $tibi['from_member_id'];
                                    $value['from_phone'] = $fromMember['phone'];
                                    $value['from_email'] = $fromMember['email'];
                                }
                            }
                            break;
                        case 9:
                            //otc交易类型
                            $tradeOtc = Db::name("trade_otc")->where(['trade_id' => $value['third_id']])->field("member_id,other_member")->find();
                            if (!empty($tradeOtc)) {
                                if ($tradeOtc['other_member'] > 0) {
                                    $toMember = $this->getMemberInfo($tradeOtc['other_member']);
                                    $value['toMemberId'] = $tradeOtc['other_member'];
                                    $value['to_phone'] = $toMember['phone'];
                                    $value['to_email'] = $toMember['email'];
                                }
                                if ($tradeOtc['member_id'] > 0) {
                                    $fromMember = $this->getMemberInfo($tradeOtc['member_id']);
                                    $value['from_member_id'] = $tradeOtc['member_id'];
                                    $value['from_phone'] = $fromMember['phone'];
                                    $value['from_email'] = $fromMember['email'];
                                }
                            }

                            break;
                        case 11:
                            //币币交易类型
                            if ($value['to_member_id'] > 0) {
                                $toMember = $this->getMemberInfo($value['to_member_id']);
                                $value['toMemberId'] = $value['to_member_id'];
                                $value['to_phone'] = $toMember['phone'];
                                $value['to_email'] = $toMember['email'];
                            }
                            if ($value['member_id'] > 0) {
                                $fromMember = $this->getMemberInfo($value['member_id']);
                                $value['from_member_id'] = $value['member_id'];
                                $value['from_phone'] = $fromMember['phone'];
                                $value['from_email'] = $fromMember['email'];
                            }
                            if($value['to_currency_id']>0){
                                $value['currency_pair']= isset($currencyList[$value['to_currency_id']]) ? $value['currency_name']."/". $currencyList[$value['to_currency_id']]['currency_name'] : '';
                            }
                            break;
                        case 18:
                            //内转帐
                            if ($value['to_member_id'] > 0 || is_numeric($value['content'])) {
                                $uid = $value['to_member_id'] > 0 ? $value['to_member_id'] : $value['content'];
                                $toMember = $this->getMemberInfo($uid);
                                $value['toMemberId'] = $uid;
                                $value['to_phone'] = $toMember['phone'];
                                $value['to_email'] = $toMember['email'];
                            }
                            if ($value['member_id'] > 0) {
                                $fromMember = $this->getMemberInfo($value['member_id']);
                                $value['from_member_id'] = $value['member_id'];
                                $value['from_phone'] = $fromMember['phone'];
                                $value['from_email'] = $fromMember['email'];
                            }
                            break;


                    }
                }
            }
        }
        $result = ['currency_list' => $this->user_currency_list(), 'list' => $list,'list2'=>$list2 , 'currency_id' => $currency_id,'type'=>$type_get,'type_list'=>$type_list];
        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    //xrp账本@标
    public function xrp_log()
    {
        $where = [];
        $member_id = I('user_id', '');

        if (I('user_id')) {
            $where['a.l_member_id'] = $member_id;
        }

        $status = I('status', '');
        if ($status != '') {
            $status = intval($status);
            if ($status == 1) {
                $where['a.l_value'] = array('gt', 0);
            } elseif ($status == 2) {
                $where['a.l_value'] = array('lt', 0);
            }

        }
        $type = I('type', '');
        if ($type > 0) {
            $type = intval($type);
            $where['a.l_type'] = $type;
        }
        $start_time = I('start_time', '');
        $end_time = I('end_time', '');
        if (empty($start_time) && empty($end_time)) {
            $where['a.l_time'] = array(array('egt', strtotime(date('Y-m-d'))), array('lt', strtotime(date("Y-m-d", strtotime("+1 day")))));
        } else {

            if (strtotime($start_time) == strtotime($end_time)) {
                $where['a.l_time'] = array(array('egt', strtotime($start_time)), array('lt', strtotime($end_time) + 86400));
            } elseif (strtotime($start_time) > strtotime($end_time)) {
                $end_time = $start_time;
                $where['a.l_time'] = array(array('egt', strtotime($start_time)), array('lt', strtotime($start_time) + 86400));
            }
        }

        $state = I('state', '');
        if ($state != '') {
            $state = intval($state);
            if ($state == 1) {
                $model_plan = Db::name("xrp_log");
            } elseif ($state == 2) {
                $model_plan = Db::name("innovate_log");
            } elseif ($state == 3) {
                $model_plan = M("xrpj_log");
            }
        } else {
            $model_plan = M("xrp_log");
        }
        $income_where=$where;
        $pay_where=$where;
        $income_where['a.l_value']=array('gt',0);
        $pay_where['a.l_value']=array('lt',0);
        $sum1=$model_plan->alias("a")->field('sum(l_value) as num')->where($income_where)->find();
        $arr_sum[0]=empty($sum1['num'])?'0.000000':$sum1['num'];
        $sum2=$model_plan->alias("a")->field('sum(l_value) as num')->where($pay_where)->find();
        $arr_sum[1]=empty($sum2['num'])?'0.000000':$sum2['num'];
        $sum3=M('boss_plan_count')->where(['member_id'=>$member_id])->find();
        $arr_sum[2]=empty($sum3['num1'])?'0.000000':$sum3['num1'];
        $arr_sum[3]=empty($sum3['num2'])?'0.000000':$sum3['num2'];
        $arr_sum[4]=empty($sum3['num3'])?'0.000000':$sum3['num3'];
        $arr_sum[5]=empty($sum3['num4'])?'0.000000':$sum3['num4'];
        $arr_sum[6]=empty($sum3['num5'])?'0.000000':$sum3['num5'];
        $arr_sum[7]=empty($sum3['num6'])?'0.000000':$sum3['num6'];
        $arr_sum[8]=empty($sum3['num7'])?'0.000000':$sum3['num7'];
        $arr_sum[9]=empty($sum3['num8'])?'0.000000':$sum3['num8'];
        $arr_sum[10]=empty($sum3['total_profit'])?'0.000000':$sum3['total_profit'];
        $sum4=M('boss_plan_info')->field('xrpz_num,xrpj_num,xrpz_new_num')->where(['member_id'=>$member_id])->find();
        $arr_sum[11]=empty($sum4['xrpz_num'])?'0.000000':$sum4['xrpz_num'];
        $arr_sum[12]=empty($sum4['xrpj_num'])?'0.000000':$sum4['xrpj_num'];
        $arr_sum[13]=empty($sum4['xrpz_new_num'])?'0.000000':$sum4['xrpz_new_num'];
//        //根据分类或模糊查找数据数量
//        $count = $model_plan->field('count(a.l_member_id) as num')->alias("a")
//            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.l_member_id")
//            ->where($where)
//            ->find();
//        // 实例化分页类 传入总记录数和每页显示的记录数
//        $Page = new \Think\Page ($count['num'], 10);
//        //给分页传参数
//        setPageParameter($Page, array('name' => ""));
//        //分页显示输出性
//        $show = $Page->show();
        $field="a.*,m.email,m.phone";
        $log = $model_plan->alias("a")
            ->join("left join " . C("DB_PREFIX") . "member as m on m.member_id = a.l_member_id")
            ->field($field)
            ->where($where)
//            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order("a.l_time desc")
            ->select();
        //  echo $model_plan->getLastSql();die;
        if ($log) {
            foreach ($log as $key => $val) {
                $log[$key]['l_title'] = L($val['l_title']);
                $log[$key]['to_member_id'] = "无";
                $log[$key]['to_phone'] = "无";
                $log[$key]['l_current_num']="无";
                $log[$key]['l_change_num']="无";
                $log[$key]['l_fee']="0";
               if(empty($val['phone'])){
                   $log[$key]['phone']=$val['email'];
               }
                if(!empty($val['l_current_num'])&&$val['l_current_num']>0){
                    $log[$key]['l_current_num']=$val['l_current_num'];
                }
                if(!empty($val['l_change_num'])&&$val['l_change_num']>0){
                    $log[$key]['l_change_num']=$val['l_change_num'];
                }

                if ($val['l_type'] == 10) {
                    $nick_info = $this->getMemberInfo($val['l_title']);
                    $log[$key]['to_member_id'] = $val['l_title'];
                    $nick = empty($nick_info) ? "******" : $nick_info['phone'] > 0 ? $nick_info['phone'] : $nick_info['email'];
                    $log[$key]['to_phone'] = $nick_info['phone'] > 0 ? $nick_info['phone'] : $nick_info['email'];
                    if ($val['l_value'] > 0) {
                        $log[$key]['l_title'] = L('lan_mutual_transfer2') . $nick . L('lan_mutual_transfer3');
                    } elseif ($val['l_value'] < 0) {
                        $log[$key]['l_title'] = L('lan_mutual_transfer1') . $nick;
                    }

                }
                if ($val['l_type'] == 11) {
                    $log[$key]['l_title'] = $val['l_votes'] . L('lan_accountbook_boss_plan_ticket');
                }
                if ($val['l_type'] == 12) {
                    $log[$key]['to_member_id'] = $val['l_title'];
                    $nick_info = $this->member_info($val['l_title']);
                    $nick = empty($nick_info) ? "******" : $nick_info['phone'] > 0 ? $nick_info['phone'] : $nick_info['email'];
                    $log[$key]['to_phone'] = $nick_info['phone'] > 0 ? $nick_info['phone'] : $nick_info['email'];
                    $log[$key]['l_title'] = L('lan_accountbook_boss_plan_wei') . $nick . L('lan_accountbook_boss_plan_active') . $val['l_votes'] . L('lan_accountbook_boss_plan_ticket');
                }

                if($val['l_type']>=5 && $val['l_type']<10) {
                    $log[$key]['l_url'] = U('BossPlan/bouns_detail',['today'=>date('Y-m-d',$val['l_time']),'member_id'=>$val['l_member_id']]);
                } elseif ($val['l_type']==4) {
                    $log[$key]['l_url'] = U('BossPlan/reward_bouns_detail',['today'=>date('Y-m-d',$val['l_time']),'member_id'=>$val['l_member_id']]);
                }

                $log[$key]['l_type_explain']=L($val['l_type_explain']);
                $log[$key]['l_time']=date('Y-m-d H:i:s',$val['l_time']);
                if($state==1){
                    if($val['l_type'] == 9){
                        $log[$key]['l_fee']=$val['l_transfer_fee'];
                    }elseif($val['l_type'] == 10){
                        $log[$key]['l_fee']=$val['l_xrp_fee'];
                    }
                }
                if($state==1||$state==2){
                    $log[$key]['l_unit']='xrp';
                }elseif($state==3){
                    $log[$key]['l_unit']='xrpj';
                    $log[$key]['l_fee']=$val['l_xrpj_fee'];
                }else{
                    $log[$key]['l_unit']='xrp';
                    if($val['l_type'] == 9){
                        $log[$key]['l_fee']=$val['l_transfer_fee'];
                    }elseif($val['l_type'] == 10){
                        $log[$key]['l_fee']=$val['l_xrp_fee'];
                    }
                }
                $log[$key]['l_value']=$val['l_value']>0?'+'.$val['l_value']:$val['l_value'];
                $log[$key]['l_state']=$val['l_value']>0?"收入":"支出";
                $log[$key]['l_type']=$this->type_arr(1)[$val['l_type']];

            }
        }


        $arr['state']=$state;
        $arr['type']=$type;
        $arr['status']=$status;
        $arr['start_time']=empty($start_time)?date('Y-m-d'):$start_time;
        $arr['end_time']=empty($end_time)?date('Y-m-d'):$end_time;
       // ,'page'=>$show
      //  print_r($arr_sum);die;
        $result=['list'=>$log,'where'=>$arr,'state_arr'=>$this->type_arr(5),'type_arr'=>$this->type_arr(1),'status_arr'=>$this->type_arr(6),'arr_sum'=>$arr_sum];
        $this->ajaxReturn(['Code'=>1,'Msg'=>$result]);
    }

    private function member_info($member_id, $ield = "member_id,phone,nick,reg_time,email")
    {
        $member = M('member')->field($ield)->where(['member_id' => $member_id])->find();
        if (!empty($member)) {
            if (empty($member['nick'])) {
                $member['nick'] = $member['phone'];
                if (empty($member['phone'])) {
                    $member['nick'] = $member['email'];
                }
            }
        }

        return empty($member) ? [] : $member;
    }


    private function type_arr($type){
        if($type==1){
            return array('1'=>'基础分红','2'=>'增加分红','3'=>'一级分红','4'=>'幸运赠送','5'=>'推荐奖励','6'=>'社区奖励','7'=>'平级奖励','8'=>'管理奖励','9'=>'划转','10'=>'平台内转账','11'=>'激活 ','12'=>'认购','13'=>'管理员充值');
        }elseif($type==2){
            return array('1'=>'基础分红','2'=>'增加分红','3'=>'一级分红','4'=>'幸运赠送','5'=>'推荐奖励','6'=>'社区奖励','7'=>'平级奖励','8'=>'管理奖励','9'=>'划转','10'=>'平台内转账');
        }elseif($type==3){
            return array('11'=>'激活 ','12'=>'认购','13'=>'管理员充值');
        }elseif($type==4){
            return array('1'=>'基础分红','2'=>'增加分红','3'=>'一级分红','4'=>'幸运赠送','5'=>'推荐奖励','6'=>'社区奖励','7'=>'平级奖励','8'=>'管理奖励');
        }elseif($type==5){
            return array('1'=>'瑞波钻','2'=>'创新区','3'=>'瑞波金');
        }elseif ($type==6){
            return array('1'=>'收入','2'=>'支出');
        }


    }

    protected function getMemberInfo($member_id, $field = "email,phone")
    {
        if (!empty($member_id)) {
            return Db::name("member")->where(['member_id' => $member_id])->field($field)->find();
        }
        return null;
    }

    //登录历史
    public function login_log()
    {
        $phone=I('phone');
        $member_id = I('member_id');

        if(!empty($phone)) {
            if(checkEmail($phone)) {
                $where['c.email'] = $phone;
            } else {
                $where['c.phone'] = $phone;
            }
        }
        if(!empty($member_id)) $where['c.member_id'] = $member_id;

        $field = "a.uuid,a.platform,a.login_ip,a.login_time,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $count      = M('member_login')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->count();// 查询满足要求的总记录数
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('phone'=>$phone,'member_id'=>$member_id));

        $show       = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M('member_login')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->order(" a.id desc ")
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        //积分类型
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }

    /**
     * 注册白名单列表
     */
    public function whitelist(Request $request)
    {
        if ($_POST) {

            $account = input('post.account', '', 'strval');
            if (Db::name('RegWhitelist')->where('account', $account)->find()) {
                return $this->error('邮箱或手机号已添加白名单');
            }

            try {
                Db::startTrans();
                $data['account'] = $account;
                $data['add_time'] = time();
                $flag = Db::name('RegWhitelist')->insertGetId($data);
                if (!$flag) throw new Exception('添加白名单记录失败-in line:'.__LINE__);

                Db::commit();
                return $this->success('添加成功');
            }
            catch (Exception $exception) {
                Db::rollback();
                return $this->error('添加失败,'.$exception->getMessage());
            }
        } else {
            $account = input('account');
            $where = [];
            if (!empty($account)) $where['account'] = $account;
            $list = Db::name('RegWhitelist')->where($where)->order('add_time desc')->paginate(20,null,['query'=>input()]);
            $show=$list->render();
            $this->assign('page', $show);
            $this->assign('list', $list);
            $this->assign('account', $account);

            return $this->fetch();
        }
    }

    /**
     * 删除注册白名单列表
     */
    public function del_whitelist(Request $request)
    {
        $id = input('id');

        if(empty($id)){
            return $this->error('操作失败,ID错误');
        }
        $find = Db::name("RegWhitelist")->where('id', $id)->find();
        if(!$find){
            return $this->error('操作失败,ID错误');
        }

        $save =  Db::name("RegWhitelist")->where('id', $id)->delete();

        if ($save === false) {
            return $this->error('操作失败!请重试');
        }

        return $this->success('操作成功');
    }
}
