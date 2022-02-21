<?php

namespace app\admin\controller;

use Admin\Controller\AdminController;
use think\Db;
use Think\Page;
use think\Request;

class Pay extends Admin
{


    //空操作
    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    //财务日志
    public function index(Request $request)
    {
        $type_id = input('type_id');
        $name = input('name');
        $member_id = input('member_id');
        $date1 = input('date1');
        $date2 = input('date2') . ' 23:59:59';
        $where=null;
        if (!empty($type_id)) {
            $where['f.type'] = $type_id;
        }
        if (!empty($name)) {
            $uid = Db::name('Member')->where("name like '%{$name}%'")->find();
            $where['m.member_id'] = $uid['member_id'];
        }
        if (!empty($member_id)) {
            $where['m.member_id'] = $member_id;
        }
        if ($date1 && $date2) {
            $date1 = strtotime($date1);
            $date2 = strtotime($date2);
            $where['f.add_time'] = ['between', [$date1, $date2]];
        }

        //筛选
        $type = Db::name('Finance_type')->select();
        $this->assign('type', $type);
        //显示日志
        $list = Db::name('Finance')->alias("f")
            ->field('f.*,m.name as username,c.currency_name,ft.name as typename')
            ->join(config("database.prefix")."member m","m.member_id=f.member_id","LEFT")
            ->join(config("database.prefix")."finance_type ft","ft.id=f.type","LEFT")
            ->join(config("database.prefix")."currency c","c.currency_id=f.currency_id","LEFT")
            ->where($where)
            ->order('add_time desc')
            ->paginate(25,null,['query'=>input()])->each(function ($item,$key){
                if($item['currency_id']==0){
                    $item['currency_name']= $this->config['xnb_name'];
                }
                return $item;
            });
        $show=$list->render();
        $this->assign('datePicker1', input('date1'));
        $this->assign('datePicker2', input('date2'));
        $this->assign('name', $name);
        $this->assign('member_id', $member_id);
        $this->assign('empty', '暂未查询到数据');
        $this->assign('list', $list);
        $this->assign('page', $show); // 赋值分页输出
       return $this->fetch();
    }

    /**
     * 负资产
     */
    public function loss()
    {
        $email = I('email');
        $member_id = I('member_id');
        $name = I('name');
        $phone = I('phone');
        $pid = I('pid');
        $status = I('status');

        $where = "(member.rmb < 0 or member.forzen_rmb < 0 or currency_user.num < 0 or currency_user.forzen_num < 0)";

        if (!empty($email)) {
            $where = " and member.email line '%{$email}%'";
        }
        if (!empty($member_id)) {
            $where .= " and member.member_id = '{$member_id}'";
        }
        if (!empty($name)) {
            $where .= " and member.name line '%{$email}%'";
        }
        if (!empty($phone)) {
            $where .= " and member.phone line '%{$phone}%'";
        }
        if (!empty($pid)) {
            $where .= " and member.pid = '{$pid}'";
        }
        if (!empty($status)) {
            $where .= " and member.status = '{$status}'";
            $where .= " and member.is_award = '0'";
            $where .= " and member.pid <> '0'";
        }

        $member = M('member');
        $loss_log = M('loss_log');

        $count = $member->alias("member")->join("left join yang_currency_user as currency_user on member.member_id = currency_user.member_id")->where($where)->count();
        $Page = new Page($count, 10);// 实例化分页类 传入总记录数和每页显示的记录数

        //给分页传参数
        setPageParameter($Page, array('email' => $email, 'member_id' => $member_id, 'name' => $name, 'phone' => $phone, 'status' => $status, 'pid' => $pid));

        $show = $Page->show();// 分页显示输出

        $list = $member->alias("member")->join("left join yang_currency_user as currency_user on member.member_id = currency_user.member_id")->where($where)->limit($Page->firstRow, $Page->listRows)->select();

        if (!empty($list)) {
            foreach ($list as &$value) {
                $loss_log = $loss_log->alias("loss_log")->join("left join yang_admin as admin on admin.admin_id = loss_log.admin_id")->field("loss_log.id as log_id,loss_log.member_id,from_unixtime(loss_log.insertd_tm, '%Y-%m-%d %H:%i:%s') as insertd_tm,loss_log.remarks,admin.username as admin_name")->where(['loss_log.member_id' => $value['member_id']])->order('loss_log.insertd_tm desc')->select();
                if (!empty($loss_log)) {
                    $html = "";
                    foreach ($loss_log as $log) {
                        $html .= "<pre>处理人：{$log['admin_name']} &nbsp; 时间：{$log['insertd_tm']}<br>结果：{$log['remarks']}</pre><hr>";
                    }

                    $value['loss_log'] = rtrim($html, "<hr>");
                } else {
                    $value['loss_log'] = "-";
                }
            }
        }

        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出
        $this->display(); // 输出模板
    }

    /**
     * 负资产
     */
    public function currency_loss()
    {
        if (IS_AJAX) {
            $email = I('email', '');
            $member_id = I('member_id', '');
            $name = I('name', '');
            $phone = I('phone', '');
            $pid = I('pid', '');
            $status = I('status', '');
            $page = I('p', 1, 'intval');

            $where = "1 = 1";
            if (!empty($email)) {
                $where .= " and member.email line '%{$email}%'";
            }
            if (!empty($member_id)) {
                $where .= " and member.member_id = '{$member_id}'";
            }
            if (!empty($name)) {
                $where .= " and member.name line '%{$email}%'";
            }
            if (!empty($phone)) {
                $where .= " and member.phone line '%{$phone}%'";
            }
            if (!empty($pid)) {
                $where .= " and member.pid = '{$pid}'";
            }
            if (!empty($status)) {
                $where .= " and member.status = '{$status}'";
                $where .= " and member.is_award = '0'";
                $where .= " and member.pid <> '0'";
            }

            $model = [
                'member' => M('member'),
                'tibi' => M('tibi'),
                'trade' => M('trade'),
                'currency_user' => M('currency_user'),
                'issue' => M('issue'),
                'pay' => M('pay'),
                'currency_user_num_award' => M('currency_user_num_award'),
                'loss_log' => M("loss_log"),
            ];

            $pagesize = 100;
            $list = $this->get_currency_loss($model, $where, $page, $pagesize);
            $this->ajaxReturn(['Code' => 1, 'Msg' => $list]);
        } else {
            $this->display(); // 输出模板
        }
    }

    /**
     * ajax获取列表
     * @param $model
     * @param $where
     * @param $page
     * @param $pagesize
     * @param array $_list
     * @param int $index
     * @return array
     */
    public function get_currency_loss($model, $where, $page, $pagesize, $_list = [], $index = 0)
    {
        $start = ($page - 1) * $pagesize;
        $field = "member.member_id,member.email,ifnull(member.pid, '-') as pid,member.name,member.phone,member.rmb,member.forzen_rmb,from_unixtime(member.reg_time, '%Y-%m-%d %H:%i:%s') as reg_time,(case member.status when 1 then '正常' when 2 then '禁用' else '未填写个人信息' end) as status";
        $list = $model['member']->alias("member")->field($field)->where($where)->limit($start, $pagesize)->select();

        if (!empty($list)) {
            foreach ($list as $key => $member) {
                $currency_user_list = $model['currency_user']->alias("currency_user")->where(['currency_user.member_id' => $member['member_id']])->select();
                if (!empty($currency_user_list)) {
                    foreach ($currency_user_list as $_currency_user) {
                        $value['chongbi_num'] = $model['tibi']->field("ifnull(round(sum(num), 4), '0.0000') as chongbi_num")->where(['user_id' => $member['member_id'], 'currency_id' => $_currency_user['currency_id'], 'status' => 3])->find()['chongbi_num'];
                        $value['tibi_num'] = $model['tibi']->field("ifnull(round(sum(num), 4), '0.0000') as tibi_num")->where(['user_id' => $member['member_id'], 'currency_id' => $_currency_user['currency_id'], 'status' => 1])->find()['tibi_num'];
                        $value['buy_num'] = $model['trade']->field("ifnull(round(sum(num), 4), '0.0000') as buy_num")->where(['member_id' => $member['member_id'], 'currency_id' => $_currency_user['currency_id'], 'type' => 'buy'])->find()['buy_num'];
                        $value['sell_num'] = $model['trade']->field("ifnull(round(sum(num), 4), '0.0000') as sell_num")->where(['member_id' => $member['member_id'], 'currency_id' => $_currency_user['currency_id'], 'type' => 'sell'])->find()['sell_num'];
                        $value['issue_num'] = $model['issue']->alias("issue")->field("ifnull(sum(issue_log.num), '0.0000') as num")->join("yang_issue_log as issue_log on issue.id = issue_log.iid")->where(['issue_log.uid' => $member['member_id'], 'issue.currency_id' => $_currency_user['currency_id']])->find()['num']; //认购数量
                        $value['pay_num'] = $model['pay']->field("ifnull(round(sum(money), 4), '0.0000') as money")->where(['member_id' => $member['member_id'], 'currency_id' => $_currency_user['currency_id']])->find()['money']; //管理员充值
                        $award_money = $model['currency_user_num_award']->field("sum(num_award) as money")->where(['member_id' => $member['member_id'], 'currency_id' => $_currency_user['currency_id']])->find()['money']; //邀请奖励

                        $value['balance'] = ($value['chongbi_num'] + $value['buy_num']) - ($value['sell_num'] + $value['tibi_num']) - ($value['num'] + $value['forzen_num']) + $value['issue_num'] + $value['pay_num'] + $award_money; //余额 = （充积分数量 + 购买量） - （卖出量  +  提积分数量 ） - （持有数量 + 冻结数量） + 认购数量 + 管理员充值 + 邀请奖励
                        $value['balance'] = number_format($value['balance'], 4, '.', ''); //余额

                        if ($value['balance'] < 0 && $value['balance'] != '-0.0000' || ($value['num'] + $value['tibi_num'] + $value['chongbi_num'] + $value['forzen_num'] + $value['num_award'] + $value['sum_award'] + $value['totalcharging'] + $value['buy_num'] + $value['sell_num']) < 0) {
                            $loss_log = $model['loss_log']->alias("loss_log")->join("left join yang_admin as admin on admin.admin_id = loss_log.admin_id")->field("loss_log.id as log_id,loss_log.member_id,from_unixtime(loss_log.insertd_tm, '%Y-%m-%d %H:%i:%s') as insertd_tm,loss_log.remarks,admin.username as admin_name")->where(['loss_log.member_id' => $member['member_id']])->order('loss_log.insertd_tm desc')->select();
                            if (!empty($loss_log)) {
                                $html = "";
                                foreach ($loss_log as $value) {
                                    $html .= "<pre>处理人：{$value['admin_name']} &nbsp; 时间：{$value['insertd_tm']}<br>结果：{$value['remarks']}</pre><hr>";
                                }

                                $member['loss_log'] = rtrim($html, "<hr>");
                            } else {
                                $member['loss_log'] = "-";
                            }

                            $_list[] = $member;
                            break;
                        }
                    }
                }
            }
        }

//         if(empty($_list) && $index < 1){
//             usleep(10000000);
//             $index++;
//             unset($list);
//             return self::get_currency_loss($model, $where, $page+1, $pagesize, $_list, $index);
//         }

        if ($where !== "1 = 1") {
            $end = 1;
            $type = 'search';
        } else {
            $end = empty($list) ? 1 : 0;
            $type = 'list';
        }

        return ['page' => $page, 'pagesize' => $pagesize, 'list' => $_list, 'end' => $end, 'type' => $type];
    }

    /**
     * 保存负资产人员处理记录
     */
    public function save_loss()
    {
        $data['member_id'] = I("post.member_id", '', 'intval');
        $data['remarks'] = I("post.content", '', 'strval');

        if (empty($data['member_id'])) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '保存失败，没有获取到用户ID']);
        }

        if (empty($data['remarks'])) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '请填写处理结果信息']);
        }

        $data['admin_id'] = $this->admin['admin_id'];

        if (empty($data['admin_id'])) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '请先登录']);
        }

        $data['insertd_tm'] = time();

        $insert = M("loss_log")->add($data);
        if ($insert === false) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '保存失败，插入失败']);
        }

        $this->ajaxReturn(['Code' => 1, 'Msg' => '保存成功']);
    }

    /**
     * 获取负资产人员处理记录
     */
    public function get_loss()
    {
        $data['member_id'] = I("get.member_id", '', 'intval');

        if (empty($data['member_id'])) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '获取失败，没有获取到用户ID']);
        }

        $result = M("loss_log")->alias("loss_log")->join("left join yang_admin as admin on admin.admin_id = loss_log.admin_id")->field("loss_log.id as log_id,loss_log.member_id,from_unixtime(loss_log.insertd_tm, '%Y-%m-%d %H:%i:%s') as insertd_tm,loss_log.remarks,admin.username as admin_name")->where(['loss_log.member_id' => $data['member_id']])->order('loss_log.insertd_tm desc')->select();

        $this->ajaxReturn(['Code' => 1, 'Msg' => $result]);
    }

    //人工充值审核页面
    public function payByMan(Request $request)
    {
        $status = input('status');
        $member_name = input('member_name');
        $member_phone = input('member_phone');
        $member_email = input('member_email');
        $member_id = input('member_id');
        $capital = input('capital');
        $datePicker1 = strtotime(input('datePicker1'));
        $datePicker2 = strtotime(input('datePicker2'));
        $batch = input('batch');
        $exception = input('exception');
        if (input('mit')) {
            $due_bank = input('due_bank');
            $batch = input('batch');
            $capital = input('capital');
            $hid = input('hid');
            $payMit =Db::name('Pay');
            $condition['pay_id'] = $hid;
            $data['due_bank'] = $due_bank;
            $data['batch'] = $batch;
            $data['capital'] = $capital;
            $data['commit_name'] = $this->admin['username'];
            $data['commit_time'] = time();
            $rs = $payMit->where($condition)->update($data);

            if ($rs) {
              return  $this->success('提交成功');
            } else {
               return $this->errror('提交失败');
            }
        }
        $where=null;
        if (!empty($status)) {
            if ($status == 4) {
                $where['p.status'] = 0;
            } else {
                $where['p.status'] = $status;
            }
        }
        if (!empty($member_name)) {
            $where['p.member_name'] = array('like', "%" . $member_name . "%");

        }
        if (!empty($member_phone)) {
            $where['m.phone'] = array('like', "%" . $member_phone . "%");

        }

        if (!empty($member_email)) {
            $where['m.email'] = array('like', "%" . $member_email . "%");

        }

        if (!empty($member_id)) {
            $where['p.member_id'] = array('like', "%" . $member_id . "%");

        }
        if (!empty($capital)) {
            $where['p.capital'] = array('like', "%" . $capital . "%");

        }
        if (!empty($datePicker1) && !empty($datePicker2)) {
            $where['add_time'] = array('between', array($datePicker1, $datePicker2));
            $datePicker1 = date("Y-m-d", $datePicker1);
            $datePicker2 = date("Y-m-d", $datePicker2);
            $this->assign('datePicker1', $datePicker1);
            $this->assign('datePicker2', $datePicker2);
        }else{
            $this->assign('datePicker1', "");
            $this->assign('datePicker2', "");
        }
        if (!empty($batch)) {
            $where['p.batch'] = array('like', "%" . $batch . "%");

        }
        if (!empty($exception) && $exception === 'on') {
            $where['p.status'] = 1;
            $where['p.count - p.capital'] = array('gt', 20);

        }

        $list = Db::name('Pay')->alias("p")
            ->field('p.*,m.email,m.phone,a.username')
            ->join(config("database.prefix")."member m","m.member_id=p.member_id")
            ->join(config("database.prefix")."admin a","a.admin_id=p.admin_id")
            ->where($where)
            ->order('add_time desc')
            ->paginate(20,null,['query'=>$request->get()])->each(function ($item,$key){
                $item['status'] = payStatus($item['status']);
                $item['currency_id'] = getCurrencynameByCurrency($item['currency_id']);
                return $item;
            });
        $show=$list->render();
        $this->assign('exception', $exception);
        $this->assign('batch', $batch);
        $this->assign('member_email', $member_email);
        $this->assign('capital', $capital);
        $this->assign('member_phone', $member_phone);
        $this->assign('member_id', $member_id);
        $this->assign('member_name', $member_name);
        $this->assign('status', $status);
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->assign('empty', '暂无数据');
        $this->assign('admin_id', $this->admin['admin_id']);
        return $this->fetch();
    }

    //人工充值统计
    public function statistics()
    {
        $member_name = I('member_name');
        $member_id = I('member_id');
        $datePicker1 = strtotime(I('datePicker1'));
        $datePicker2 = strtotime(I('datePicker2'));

        $where['status'] = 1;//只统计充值成功的
        $where['account'] = array('neq', '');//银行卡号不为空
        if (!empty($member_name)) {
            $where['yang_pay.member_name'] = array('like', "%" . $member_name . "%");
            $this->assign('member_name', $member_name);
        }
        if (!empty($member_id)) {
            $where['yang_pay.member_id'] = array('like', "%" . $member_id . "%");
            $this->assign('member_id', $member_id);
        }
        if (!empty($datePicker1) && !empty($datePicker2)) {
            $where['add_time'] = array('between', array($datePicker1, $datePicker2));
            $datePicker1 = date("Y-m-d", $datePicker1);
            $datePicker2 = date("Y-m-d", $datePicker2);
            $this->assign('datePicker1', $datePicker1);
            $this->assign('datePicker2', $datePicker2);
        }

        $count = M('Pay')->field('count(*)')->where($where)->group('member_id')->select();
        $Page = new \Think\Page(count($count), 20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        $Page->parameter = array(
            'member_id' => $member_id,
            'member_name' => $member_name,
            'datePicker1' => $datePicker1,
            'datePicker2' => $datePicker2,
        );
        $show = $Page->show();// 分页显示输出
        $list = M('Pay')->field('pay_id, max(add_time) as add_time, sum(money) as money, member_id, member_name')->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->group('member_id')->order('add_time desc')->select();

        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->assign('empty', '暂无数据');
        $this->assign('sum_money', M('Pay')->field('sum(money) as sum_money')->where($where)->find()['sum_money']);
        $this->assign('admin_id', $this->admin['admin_id']);
        $this->display();
    }

    //提现统计
    public function paystatistics()
    {
        $member_name = I('member_name');
        $member_id = I('member_id');
        $status = I('status');
        $datePicker1 = strtotime(I('datePicker1'));
        $datePicker2 = strtotime(I('datePicker2'));

        // $where['status'] = 1;//只统计充值成功的
        //$where['account'] = array('neq', '');//银行卡号不为空
        if (!empty($member_name)) {
            $where['yang_withdraw.member_name'] = array('like', "%" . $member_name . "%");
            $this->assign('member_name', $member_name);
        }
        if (!empty($status)) {
            $where['yang_withdraw.status'] = $status;
            $this->assign('status', $status);
        }
        if (!empty($member_id)) {
            $where['yang_withdraw.member_id'] = array('like', "%" . $member_id . "%");
            $this->assign('uid', $member_id);
        }

        if (!empty($datePicker1) && !empty($datePicker2)) {
            $where['add_time'] = array('between', array($datePicker1, $datePicker2));
            $datePicker1 = date("Y-m-d", $datePicker1);
            $datePicker2 = date("Y-m-d", $datePicker2);
            $this->assign('datePicker1', $datePicker1);
            $this->assign('datePicker2', $datePicker2);
        }

        $count = M('withdraw')->where($where)->group('year(FROM_UNIXTIME(pay_time)),month(FROM_UNIXTIME(pay_time)),day(FROM_UNIXTIME(pay_time))')->select();
        $Page = new \Think\Page(count($count), 20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        $Page->parameter = array(
            'uid' => $member_id,
            'status' => $status,
            'datePicker1' => $datePicker1,
            'datePicker2' => $datePicker2,
        );
        $show = $Page->show();// 分页显示输出
        $list = M('withdraw')->field(' year(FROM_UNIXTIME(pay_time)) as year,month(FROM_UNIXTIME(pay_time)) as month,day(FROM_UNIXTIME(pay_time)) as day,sum(money) as money,sum(all_money) as all_money,sum(withdraw_fee) as withdraw_fee,status,count(*) as count')
            ->where($where)->limit($Page->firstRow . ',' . $Page->listRows)
            ->group('year(FROM_UNIXTIME(pay_time)),month(FROM_UNIXTIME(pay_time)),day(FROM_UNIXTIME(pay_time))')
            ->order('add_time desc')
            ->select();


        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->assign('empty', '暂无数据');
        //$this->assign('sum_money', M('Pay')->field('sum(money) as sum_money')->where($where)->find()['sum_money']);
        $this->assign('sum_money_all', M('withdraw')->field('sum(all_money) as sum_money_all')->where($where)->find()['sum_money_all']);
        $this->assign('sum_money', M('withdraw')->field('sum(money) as sum_money')->where($where)->find()['sum_money']);
        $this->assign('withdraw_fee', M('withdraw')->field('sum(withdraw_fee) as withdraw_fee')->where($where)->find()['withdraw_fee']);
        $this->assign('admin_id', $this->admin['admin_id']);
        $this->display();
    }

    //快捷支付充值
    public function alipay()
    {

        $uid = session('admin_userid');

        $admin_username = M('Admin')->where('admin_id=' . $uid . '')->field('username,admin_id')->select();

        $this->username = $admin_username;
        $status = I('status');
        $member_name = I('member_name');
        $member_phone = I('member_phone');
        $member_email = I('member_email');
        $member_id = I('member_id');
        $count = I('count');
        $datePicker = strtotime(I('datePicker'));
        $datePicker2 = strtotime(I('datePicker2'));
        $payMit = M('pay');
        if (I('mit')) {
            $due_bank = I('due_bank');
            $batch = I('batch');
            $capital = I('capital');
            $hid = I('hid');
            $condition['pay_id'] = $hid;
            $data['due_bank'] = $due_bank;
            $data['batch'] = $batch;
            $data['capital'] = $capital;
            $data['commit_name'] = $admin_username[0]['username'];
            $data['commit_time'] = time();
            $rs = $payMit->where($condition)->save($data);

            if ($rs != false) {
                $this->success('提交成功');
            } else {
                $this->errror('提交失败');
            }
        }

        if (!empty($status) || $status === "0") {
            $where['yang_pay.status'] = $status;
        }

        if (!empty($member_name)) {
            $where['yang_pay.member_name'] = array('like', "%" . $member_name . "%");
        }

        if (!empty($member_phone)) {
            $where['yang_pay.phone'] = array('like', "%" . $member_phone . "%");
        }

        if (!empty($member_email)) {
            $where['yang_pay.email'] = array('like', "%" . $member_email . "%");
        }

        if (!empty($member_id)) {
            $where['yang_pay.member_id'] = array('like', "%" . $member_id . "%");
        }

        if (!empty($count)) {
            $where['yang_pay.count'] = array('like', "%" . $count . "%");
        }

        if (!empty($datePicker) && !empty($datePicker2)) {
            $where['yang_pay.add_time'] = array('between', array($datePicker, $datePicker2));
        }

        $where['yang_pay.due_bank'] = ['like', "%即时到帐%"];

        $count = $payMit->where($where)->join('left join yang_member on yang_member.member_id=yang_pay.member_id')->count();

        $Page = new \Think\Page($count, 20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('status' => $status, 'member_name' => $member_name, 'member_phone' => $member_phone, 'member_email' => $member_email, 'member_id' => $member_id, 'datePicker' => $datePicker, 'datePicker2' => $datePicker2));
        $show = $Page->show();// 分页显示输出
        $list = $payMit
            ->field('yang_pay.*,yang_member.email,yang_member.phone')
            ->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
            ->where($where)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('add_time desc')
            ->select();
        //dump($list,true,'<pre>',false);exit;
        foreach ($list as $k => $v) {
            $list[$k]['status'] = payStatus($v['status']);
        }
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->assign('empty', '暂无数据');
        $this->display();
    }

    //人工充值审核处理
    public function payUpdate()
    {
        $uid = session('admin_userid');
        $admin_username = M('Admin')->where('admin_id=' . $uid . '')->field('username')->select();
        $pay = M('Pay');
        $where['pay_id'] = $_POST['pay_id'];
        //$this->ajaxReturn($_POST['status']);
        $list = $pay->where($where)->find();
        if ($list['status'] != 0) {
            $data['status'] = -1;
            $data['info'] = "请不要重复操作";
            $this->ajaxReturn($data);
        }
        $member_id = M('Member')->where("member_id='" . $list['member_id'] . "'")->find();
        $jpush = A("Api/Jpush");
        if ($_POST['status'] == 1) {
            //$pay->where($where)->setField('status',1);

            $pay->where($where)->setField(array('status' => 1, 'audit_name' => $admin_username[0]['username'], 'audit_time' => time()));
            //修改member表钱数
            $rs = M('Member')->where("member_id='" . $list['member_id'] . "'")->setInc('rmb', $list['count']);
            $xnb = $list['count'];//换算成交易积分//$this->config['bili'];
            //添加财务日志
            $this->addFinance($member_id['member_id'], 6, "线下充值" . $xnb . "。", $xnb, 1, 0);

            //推送到APP
            $jpush->index('recharge', 'suc', $member_id['member_id']);

            //添加信息表
            $this->addMessage_all($member_id['member_id'], -2, '人工充值成功', '您申请的人工充值已成功，充值金额为' . $xnb);
        } elseif ($_POST['status'] == 2) {
            $rs = $pay->where($where)->setField('status', 2);

            //推送到APP
            $jpush->index('recharge', 'err', $member_id['member_id']);

            //添加信息表
            $this->addMessage_all($member_id['member_id'], -2, '人工充值失败', '您申请的人工充值失败,请重新处理');
        } else {
            $data['status'] = 0;
            $data['info'] = "操作有误";
            $this->ajaxReturn($data);
        }
        if ($rs) {
            $data['status'] = 1;
            $data['info'] = "修改成功";
            $this->ajaxReturn($data);
        } else {
            $data['status'] = 2;
            $data['info'] = "修改失败";
            $this->ajaxReturn($data);
        }
    }


    //瑞铂金充值
    public function xrpj_recharge()
    {
        if (IS_POST) {
            if (empty($_POST['member_id'])) {
                $this->error('请输入充值人员');
            }

            if (!isset($_POST['currency_id'])) {
                $this->error('请输入积分类型');
            }

            if (empty($_POST['money'])) {
                $this->error('请输入充值金额');
            }
            if (empty($_POST['message'])) {
                $this->error('请输入充值备注');
            }
            if (!M('Member')->where("member_id = {$_POST['member_id']}")->find()) {
                $this->error('用户不存在');
            }

            if (!M('boss_plan')->where("member_id = {$_POST['member_id']} and status=3")->find()) {
                $this->error('尚未加入老板计划');
            }


            $member_id = I('member_id', '', 'intval');
            $currency_id = I('currency_id', '', 'intval');

            M()->startTrans();//开启事务
            $data['message'] = I('message');
            $data['admin_id'] = session('admin_userid');
            $data['member_id'] = $member_id;
            $data['currency_id'] = $currency_id;
            $data['money'] = I('money');
            $data['status'] = 1;
            $data['add_time'] = time();
            $data['type'] = 7;//管理员充值类型
            M()->startTrans();//开启事务
            $r[] = $pay_id = M('pay')->add($data);
            if ($data['currency_id'] == -1) {
                $r[] = M('boss_plan_info')->where(array('member_id' => $data['member_id']))->setInc('xrpj_num', $data['money']);

                $r[] = M('xrpj_log')->add([
                    'l_member_id' => $data['member_id'],
                    'l_value' => $data['money'],
                    'l_time' => time(),
                    'l_title' => 'lan_admin_recharge',
                    'l_type' => 13,
                    'l_type_explain' => 'lan_recharge',
                ]);
            }
            if (!in_array(false, $r)) {
                M()->commit();
                $this->success('添加成功');

            } else {
                M()->rollback();
                $this->error('添加失败');
            }
        } else {
            $count = M('pay')->where("type = 7")->count();// 查询满足要求的总记录数
            $Page = new \Think\Page($count, 20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
            $show = $Page->show();// 分页显示输出
            $list = M('pay')
                ->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
                ->join('left join yang_admin on yang_admin.admin_id=yang_pay.admin_id')
                ->where("type = 7")->limit($Page->firstRow . ',' . $Page->listRows)->order('add_time desc')->select();
            $this->assign('page', $show);

            $this->assign('list', $list);
            $currency = M('Currency')->field('currency_name,currency_id')->select();
            $this->assign('currency', $currency);
            $this->display();
        }
    }

    //GAC糖果赠送
    public function recharge_lock()
    {
        if (IS_POST) {
            if (empty($_POST['member_id'])) {
                $this->error('请输入充值人员');
            }

            if (!isset($_POST['currency_id'])) {
                $this->error('请输入积分类型');
            }

            if (empty($_POST['money'])) {
                $this->error('请输入充值金额');
            }
            if (empty($_POST['message'])) {
                $this->error('请输入充值备注');
            }
            if (!M('Member')->where("member_id = {$_POST['member_id']}")->find()) {
                $this->error('用户不存在');
            }

            $member_id = I('member_id', '', 'intval');
            $currency_id = I('currency_id', '', 'intval');
            $money = I('post.money',0);

            M()->startTrans();//开启事务
            $data['message']=$_POST['message'].' (赠送)';
            $data['admin_id'] = session('admin_userid');
            $data['member_id'] = $member_id;
            $data['currency_id'] = $currency_id;
            $data['money'] = $money;
            $data['status'] = 1;
            $data['add_time'] = time();
            $data['type'] = 8;//管理员锁仓充值
            $data['rate']=100;
            $data['lock_money']=0;
            $data['exchange_money']=0;
            M()->startTrans();//开启事务
            $r[] = $pay_id = M('pay')->add($data);

            //添加锁仓记录
            $r[] = M('currency_gac_reward_forzen')->add([
                'member_id' => $data['member_id'],
                'num' => $data['money'],
                'type' => 10,
                'title' => 'lan_exchange_admin_pay',
                'from_num' => 0,
                'ratio' => 0,
                'add_time' => time(),
                'third_id' => 0,
            ]);

            //增加锁仓资产
            $info = M('currency_user')->lock(true)->where(['member_id' => $data['member_id'], 'currency_id' => $data['currency_id']])->find();
            if ($info) {
                $r[] = M('Currency_user')->where(array('member_id' => $data['member_id'], array('currency_id' => $data['currency_id'])))->setInc('num_award', $data['money']);
            } else {
                $r[] = M('Currency_user')->add([
                    'member_id' => $data['member_id'],
                    'currency_id' => $data['currency_id'],
                    'num' => 0,
                    'num_award' => $data['money'],
                ]);
            }

            if (!in_array(false, $r)) {
                M()->commit();
                $this->success('添加成功');
            } else {
                M()->rollback();
                $this->error('添加失败');
            }
        } else {
            $count = M('pay')->where("type = 8")->count();// 查询满足要求的总记录数
            $Page = new \Think\Page($count, 20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
            $show = $Page->show();// 分页显示输出
            $list = M('pay')
                ->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
                ->join('left join yang_admin on yang_admin.admin_id=yang_pay.admin_id')
                ->where("type = 8")->limit($Page->firstRow . ',' . $Page->listRows)->order('add_time desc')->select();
            $this->assign('page', $show);

            $this->assign('list', $list);
            $currency = M('Currency')->field('currency_name,currency_id')->where(['currency_mark'=>'GAC'])->select();
            $this->assign('currency', $currency);
            $this->display();
        }
    }

    /**
     * 添加管理员充值
     */
    public function admRecharge(Request $request)
    {
        if ($_POST) {
            if (empty($_POST['member_id'])) {
                $this->error('请输入充值人员');
            }

            if (!isset($_POST['currency_id'])) {
                $this->error('请输入积分类型');
            }

            if (empty($_POST['money'])) {
                $this->error('请输入充值金额');
            }
            if (empty($_POST['message'])) {
                $this->error('请输入充值备注');
            }
            if (!Db::name('Member')->where("member_id = {$_POST['member_id']}")->find()) {
               return $this->error('用户不存在');
            }

            $member_id = input('post.member_id', '', 'intval');
            $currency_id = input('post.currency_id', '', 'intval');

            Db::startTrans();
            $data['message'] = input('message');
            $data['admin_id'] =session('admin_userid');
            $data['member_id'] = $member_id;
            $data['currency_id'] = $currency_id;
            $data['money'] = input('money');
            $data['status'] = 1;
            $data['add_time'] = time();
            $data['type'] = 3;//管理员充值类型
            $r[] = $pay_id = Db::name('pay')->insertGetId($data);
            if ($data['currency_id'] == 0) {
                $r[] = Db::name('Member')->where(array('member_id' => $data['member_id']))->setInc('rmb', $data['money']);
            } else {
                //添加账本信息
                $r[] =model('AccountBook')->addLog([
                    'member_id' => $data['member_id'],
                    'currency_id' => $data['currency_id'],
                    'type' => 13,
                    'content' => 'lan_admin_recharge',
                    'number_type' => 1,
                    'number' => $data['money'],
                    'add_time' => time(),
                    'third_id' => $pay_id,
                ]);
                $info = Db::name('currency_user')->lock(true)->where(['member_id' => $data['member_id'], 'currency_id' => $data['currency_id']])->find();
                if ($info) {
                    $r[] = Db::name('currency_user')->where(['member_id'=>$data['member_id'],'currency_id'=>$data['currency_id']])->setInc("num",$data['money']);
                } else {
                    $r[] = Db::name('Currency_user')->insertGetId([
                        'member_id' => $data['member_id'],
                        'currency_id' => $data['currency_id'],
                        'num' => $data['money'],
                    ]);
                }
            }
            $r[] = $this->addFinance($data['member_id'], 3, "管理员充值", $data['money'], 1, $data['currency_id']);
            $r[] = $this->addMessage_all($data['member_id'], -2, "管理员充值", "管理员充值" . getCurrencynameByCurrency($data['currency_id']) . ":" . $data['money']);
            if (!in_array(false, $r)) {
                Db::commit();
               return $this->success('添加成功');
            } else {
                Db::rollback();
               return $this->error('添加失败');
            }
        } else {
            $user_id = input('user_id');
            if (!empty($user_id)) $where['p.member_id'] = $user_id;
            $where['type'] = 3;
            $list = Db::name('pay')->alias("p")->field('p.*,m.*,a.username')
                ->join(config("database.prefix")."member m","m.member_id=p.member_id","LEFT")
                ->join(config("database.prefix")."admin a","a.admin_id=p.admin_id","LEFT")
                ->where($where)->order('add_time desc')->paginate(20,null,['query'=>input()]);
            $show=$list->render();
            $this->assign('page', $show);
            $this->assign('list', $list);
            $currency = Db::name('Currency')->where('is_line', 1)->field('currency_name,currency_id,is_trade_currency,account_type')->order("sort asc")->select();
            foreach($currency as &$val) {
                $val['currency_name'] = $val['currency_name'].' - '.lang('bfw_'.$val['account_type']);
            }
            $this->assign('currency', $currency);
            $this->assign('user_id', $user_id);

            $total = Db::name('pay')->alias("p")->field('sum(money) as money,currency_id')->group('currency_id')->select();
            $this->assign('total', $total);
            return $this->fetch();
        }
    }

    /**
     * 添加管理员扣除
     */
    public function admReduce(Request $request)
    {
        if ($_POST) {
            if (empty($_POST['member_id'])) {
                $this->error('请输入充值人员');
            }

            if (!isset($_POST['currency_id'])) {
                $this->error('请输入积分类型');
            }

            if (empty($_POST['money'])) {
                $this->error('请输入充值金额');
            }
            if (empty($_POST['message'])) {
                $this->error('请输入充值备注');
            }
            if (!Db::name('Member')->where("member_id = {$_POST['member_id']}")->find()) {
                return $this->error('用户不存在');
            }

            $member_id = input('post.member_id', '', 'intval');
            $currency_id = input('post.currency_id', '', 'intval');

            $money = input('money');
            $currency_user = \app\common\model\CurrencyUser::getCurrencyUser($member_id,$currency_id);
            if(empty($currency_user) || $currency_user['num']<$money) {
                return $this->error('用户余额不足');
            }

            Db::startTrans();
            $data['message'] = input('message');
            $data['admin_id'] =session('admin_userid');
            $data['member_id'] = $member_id;
            $data['currency_id'] = $currency_id;
            $data['money'] = $money;
            $data['status'] = 1;
            $data['add_time'] = time();
            $data['type'] = 3;//管理员充值类型
            $r[] = $pay_id = Db::name('reduce')->insertGetId($data);

            //添加账本信息
            $r[] =model('AccountBook')->addLog([
                'member_id' => $data['member_id'],
                'currency_id' => $data['currency_id'],
                'type' => 800,
                'content' => 'admin_dec',
                'number_type' => 2,
                'number' => $data['money'],
                'add_time' => time(),
                'third_id' => $pay_id,
            ]);
            $r[] = Db::name('currency_user')->where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setDec("num",$data['money']);

            if (!in_array(false, $r)) {
                Db::commit();
                return $this->success('添加成功');
            } else {
                Db::rollback();
                return $this->error('添加失败');
            }
        } else {
            $user_id = input('user_id');
            if (!empty($user_id)) $where['p.member_id'] = $user_id;
            $where['type'] = 3;
            $list = Db::name('reduce')->alias("p")->field('p.*,m.*,a.username')
                ->join(config("database.prefix")."member m","m.member_id=p.member_id","LEFT")
                ->join(config("database.prefix")."admin a","a.admin_id=p.admin_id","LEFT")
                ->where($where)->order('add_time desc')->paginate(20,null,['query'=>input()]);
            $show=$list->render();
            $this->assign('page', $show);
            $this->assign('list', $list);
            $currency = Db::name('Currency')->where('is_line', 1)->field('currency_name,currency_id,is_trade_currency,account_type')->order("sort asc")->select();
            foreach($currency as &$val) {
                $val['currency_name'] = $val['currency_name'].' - '.lang('bfw_'.$val['account_type']);
            }
            $this->assign('currency', $currency);
            $this->assign('user_id', $user_id);

            $total = Db::name('reduce')->alias("p")->field('sum(money) as money,currency_id')->group('currency_id')->select();
            $this->assign('total', $total);
            return $this->fetch();
        }
    }

    public function getnamebyid()
    {

        $info = Db::name("Member")->where("member_id = {$_POST['id']}")->find();
        if ($info) {
            if (!empty($info['name'])) {
                $get_name = $info['name'];
            } elseif (!empty($info['phone'])) {
                $get_name = $info['phone'];
            } elseif (!empty($info['ename'])) {
                $get_name = $info['ename'];
            } elseif (!empty($info['nick'])) {
                $get_name = $info['nick'];
            } else {
                $get_name = $info['email'];
            }
        } else {
            $get_name = '';
        }

       $this->ajaxReturn($get_name);
    }

    public function excel_payByMan()
    {

        $uid = session('admin_userid');

        $admin_username = M('Admin')->where('admin_id=' . $uid . '')->field('username,admin_id')->select();

        $this->username = $admin_username;
        $status = I('status');
        $member_name = I('member_name');
        $member_phone = I('member_phone');
        $member_email = I('member_email');
        $member_id = I('member_id');
        $datePicker = strtotime(I('datePicker'));
        $datePicker2 = strtotime(I('datePicker2'));
        if (I('mit')) {
            $due_bank = I('due_bank');
            $batch = I('batch');
            $capital = I('capital');
            $hid = I('hid');
            $payMit = M('Pay');
            $condition['pay_id'] = $hid;
            $data['due_bank'] = $due_bank;
            $data['batch'] = $batch;
            $data['capital'] = $capital;
            $data['commit_name'] = $admin_username[0]['username'];
            $data['commit_time'] = time();
            $rs = $payMit->where($condition)->save($data);

            if ($rs != false) {
                $this->success('提交成功');
            } else {
                $this->errror('提交失败');
            }
        }

        if (!empty($status) || $status === "0") {
            $where['yang_pay.status'] = $status;
        }
        if (!empty($member_name)) {
            $where['yang_pay.member_name'] = array('like', "%" . $member_name . "%");

        }
        if (!empty($member_phone)) {

            $where['yang_pay.phone'] = array('like', "%" . $member_phone . "%");

        }

        if (!empty($member_email)) {


            $where['yang_pay.email'] = array('like', "%" . $member_email . "%");
        }

        if (!empty($member_id)) {
            $where['yang_pay.member_id'] = array('like', "%" . $member_id . "%");
        }
        if (!empty($datePicker) && !empty($datePicker2)) {
            $where['add_time'] = array('between', array($datePicker, $datePicker2));
        }


        $count = M('Pay')->where($where)->join('left join yang_member on yang_member.member_id=yang_pay.member_id')->count();

        $Page = new \Think\Page($count, 20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('status' => $status, 'member_name' => $member_name, 'member_phone' => $member_phone, 'member_email' => $member_email, 'member_id' => $member_id));
        $show = $Page->show();// 分页显示输出
        $list = M('Pay')
            ->field('yang_pay.*,yang_member.email,yang_member.phone')
            ->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
            ->where($where)
            //->limit($Page->firstRow.','.$Page->listRows)
            ->order('add_time desc')
            ->select();
        //dump($list,true,'<pre>',false);exit;
        foreach ($list as $k => $v) {
            $list[$k]['status'] = payStatus($v['status']);
            $list[$k]['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
            $list[$k]['trade_time'] = date('Y-m-d H:i:s', $v['trade_time']);
            if ($v['type'] == 13 || $v['currency_id'] > 0) {

                $list[$k]['type'] = '充积分';
            } else {

                $list[$k]['type'] = '充值';
            }
        }
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->assign('empty', '暂无数据');

        $xlsName = "User";
        $xlsCell = array(
            array('pay_id', '订单号'),

            array('email', '汇款人账号'),
            array('member_id', '汇款人ID'),
            array('member_name', '汇款人'),
            array('phone', '手机'),
            array('account', '银行卡号'),
            array('money', '充值钱数'),
            array('count', '实际打款'),
            array('status', '状态'),


            array('type', '充值类型'),
            array('add_time', '时间'),

            array('due_bank', '收款行'),
            array('batch', '交易流水号'),
            array('capital', '实收资金'),

            array('status', '状态')
        );
        // $xlsModel = M('Post');
        $xlsData = $list;
        $this->exportExcel($xlsName, $xlsCell, $xlsData);


        $this->display();
    }

    //excel财务日志
    public function excel_index()
    {
        $type_id = I('type_id');
        $name = I('name');
        $member_id = I('member_id');
        $datePicker = strtotime(I('datePicker'));
        $datePicker2 = strtotime(I('datePicker2'));
        if (!empty($type_id)) {
            $where['type'] = $type_id;
        }
        if (!empty($name)) {
            $uid = M('Member')->where("name like '%{$name}%'")->find();
            $where['yang_member.member_id'] = $uid['member_id'];
        }
        if (!empty($member_id)) {
            $where['yang_member.member_id'] = $member_id;
        }
        if (!empty($datePicker) && !empty($datePicker2)) {
            $where['yang_finance.add_time'] = array('between', array($datePicker, $datePicker2));
        }

        //筛选
        $type = M('Finance_type')->select();
        $this->assign('type', $type);
        //显示日志
        $count = M('Finance')
            ->field('yang_finance.*,yang_member.name as username,yang_currency.currency_name,yang_finance_type.name as typename')
            ->join('left join yang_member on yang_member.member_id=yang_finance.member_id')
            ->join('left join yang_finance_type on yang_finance_type.id=yang_finance.type')
            ->join('left join yang_currency on yang_currency.currency_id=yang_finance.currency_id')
            ->where($where)->count(); // 查询满足要求的总记录数
        $Page = new Page ($count, 25); // 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('type_id' => $type_id, 'name' => $name, 'member_id' => $member_id));

        $show = $Page->show(); // 分页显示输出

        $list = M('Finance')
            ->field('yang_finance.*,yang_member.name as username,yang_currency.currency_name,yang_finance_type.name as typename')
            ->join('left join yang_member on yang_member.member_id=yang_finance.member_id')
            ->join('left join yang_finance_type on yang_finance_type.id=yang_finance.type')
            ->join('left join yang_currency on yang_currency.currency_id=yang_finance.currency_id')
            //->limit($Page->firstRow.','.$Page->listRows)
            ->where($where)
            ->order('add_time desc')
            ->select();
        //echo M('Finance')->_sql();
        foreach ($list as $k => $v) {
            if ($v['currency_id'] == 0) {
                $list[$k]['currency_name'] = $this->config['xnb_name'];
                $list[$k]['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
                if ($v['money_type'] == 1) {

                    $list[$k]['money_type'] = '收入';
                } else {

                    $list[$k]['money_type'] = '支出';
                }
            }
        }
        $this->assign('empty', '暂未查询到数据');
        $this->assign('list', $list);
        $this->assign('page', $show); // 赋值分页输出


        $xlsName = "User";
        $xlsCell = array(
            array('finance_id', '日志编号'),

            array('username', '所属'),
            array('typename', '财务类型'),
            array('content', '内容'),
            array('money', '金额'),
            array('currency_name', '积分类型'),
            array('money_type', '收入/支出'),
            array('add_time', '时间')
        );
        // $xlsModel = M('Post');
        $xlsData = $list;
        $this->exportExcel($xlsName, $xlsCell, $xlsData);


        $this->display();
    }

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
        /* <td>'.$list_user[0]["remarks"].'</td> */
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

        //财务日志
        $e .= '|';
        $wherec['yang_finance.member_id'] = $uid_tx;
        $countc = M('Finance')
            ->field('yang_finance.*,yang_member.name as username,yang_currency.currency_name,yang_finance_type.name as typename')
            ->join('left join yang_member on yang_member.member_id=yang_finance.member_id')
            ->join('left join yang_finance_type on yang_finance_type.id=yang_finance.type')
            ->join('left join yang_currency on yang_currency.currency_id=yang_finance.currency_id')
            ->where($wherec)->count();
        $list = M('Finance')
            ->field('yang_finance.*,yang_member.name as username,yang_currency.currency_name,yang_finance_type.name as typename')
            ->join('left join yang_member on yang_member.member_id=yang_finance.member_id')
            ->join('left join yang_finance_type on yang_finance_type.id=yang_finance.type')
            ->join('left join yang_currency on yang_currency.currency_id=yang_finance.currency_id')
            ->where($wherec)
            ->order('add_time desc')
            ->select();
        foreach ($list as $k => $v) {
            if ($v['currency_id'] == 0) {
                $list[$k]['currency_name'] = $this->config['xnb_name'];
            }
            $list[$k]['moneytype'] = $v['money_type'] == 1 ? '收入' : '支出';
        }
        for ($i = 0; $i < $countc; $i++) {
            $e .= '<tr>
	                     		<td>' . $list[$i]["finance_id"] . '</td>
								<td>' . $list[$i]["username"] . '</td>
								<td>' . $list[$i]["typename"] . '</td>
								<td>' . $list[$i]["content"] . '</td>
								<td>' . $list[$i]["money"] . '</td>
								<td>' . $list[$i]["currency_name"] . '</td>
								<td>' . $list[$i]["moneytype"] . '</td>
								<td>' . date("Y-m-d H:i:s", $list[$i]["add_time"]) . '</td>
								<td>' . $list[$i]["ip"] . '</td></tr>';
        }
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
     * 自动到帐数据日志
     * @throws \think\Exception
     * Created by Red.
     * Date: 2019/1/18 14:46
     */
    function currencyLogList()
    {
        $types = input("types");//币类型1btc 2usdt 3eth(代币) 4xrp
        $status = input("status");//0未处理 1正在处理 2失败 3成功,4处理(地址不存在系统内)
        $from = input("afrom");
        $to = input("ato");
        $tx = input("tx");
        $where = null;
        $tx = strtolower($tx);
        if (!empty($types)) {
            $where['types'] = $types;
        }
        if ($status > 0) {
            if ($status == 5) {
                $where['status'] = 0;
            } else {
                $where['status'] = $status;
            }
        }
        if (!empty($from)) {
            $where['afrom'] = array('like', "%" . $from . "%");
        }
        if (!empty($to)) {
            $where['ato'] = array('like', "%" . $to . "%");
        }
        if (!empty($tx)) {
            $where['tx'] = array('like', "%" . $tx . "%");
        }
        $currencyList = ["1" => "btc", "2" => "usdt", "3" => "eth(代币)", "4" => "xrp",'5'=>'eos'];
        $statusList = ["5" => "未处理", "1" => "正在处理", "2" => "失败", "3" => "(充/提)成功", "4" => "处理完成"];
        $list = Db::name("currency_log")->where($where)->order("add_time desc")->paginate(30,null,['query'=>input()])->each(function ($item,$key){
            $currencyList = ["1" => "btc", "2" => "usdt", "3" => "eth(代币)", "4" => "xrp",'5'=>'eos'];
            $statusList = ["5" => "未处理", "1" => "正在处理", "2" => "失败", "3" => "(充/提)成功", "4" => "处理完成"];
            $item['json'] = json_decode($item['trans'], true);
            $item['add_time'] = date("Y-m-d H:i:s", $item['add_time']);
            $item['update_time'] = !empty($item['update_time']) ? date("Y-m-d H:i:s", $item['update_time']) : null;
            $item['currency_name'] = $currencyList[$item['types']];
            $item['status_name'] = $item['status'] == 0 ? "未处理" : $statusList[$item['status']];
            $item['check_status_name'] = $item['check_status'] == 1 ? "否" : "是";
            $item['is_modify'] = $item['is_modify'] == 1 ? "否" : "是";
            $item['trans']=json_decode($item['trans'],true);
            if($item['types']==4){
                $item['tx']=strtoupper($item['tx']);
            }
            return $item;
        });
        $show=$list->render();
        $data['types'] = $types;
        $data['status'] = $status;
        $data['afrom'] = $from;
        $data['ato'] = $to;
        $data['tx'] = $tx;
        $this->assign("currencyList", $currencyList);
        $this->assign("statusList", $statusList);
        $this->assign("list", $list);
        $this->assign("page", $show);
        $this->assign("data", $data);
        return $this->fetch();
    }

    /**
     * 修改自动到帐数据日志的人工处理状态
     * Created by Red.
     * Date: 2019/1/18 17:57
     */
    function updateCheckStatus()
    {
        $tx = input("tx");
        if (!empty($tx)) {
            $update = Db::name("currency_log")->where(['tx' => $tx])->update(['check_status' => 2]);
            if ($update) {
                return $this->success("修改成功");
            } else {
                return $this->error("修改失败");
            }
        }
        return $this->error("修改异常");
    }

    /**
     * 修改自动到帐数据日志的处理状态
     * Created by Red.
     * Date: 2019/1/18 17:58
     */
    function updateStatus()
    {
        $tx = input("tx");
        if (!empty($tx)) {
            $update = Db::name("currency_log")->where(['tx' => $tx])->update(['status' => 0]);
            if ($update) {
                return $this->success("修改成功");
            } else {
                return $this->error("修改失败");
            }
        }
        return $this->error("修改异常");
    }

    /**获取用户信息
     * @param $member_id
     * @param string $field
     * @return array|false|mixed|null|\PDOStatement|string|\think\Model
     * Created by Red.
     * Date: 2019/1/25 15:59
     */
    protected function getMemberInfo($member_id, $field = "email,phone")
    {
        if (!empty($member_id)) {
            return Db::name("member")->where(['member_id' => $member_id])->field($field)->find();
        }
        return null;
    }

    /**
     * 账本记录
     * @throws \think\Exception
     * Created by Red.
     * Date: 2019/1/25 15:59
     */
    function accountbookList()
    {
        $get['email']=$email = input("email");
        $get['phone']=$phone = input("phone");
        $get['member_id']=$member_id = input("member_id");
        $get['id']=$id = input("id");
        $get['currency_id']=$currency_id = input("currency_id");
        $get['types']=$type = input("types");
        $get['number_type']=$number_type = input("number_type");
        $where = [];
        if (!empty($email)) {
            $where['m.email'] = $email;
        }
        if (!empty($phone)) {
            $where['m.phone'] = $phone;
        }
        if (!empty($member_id)) {
            $where['a.member_id'] = $member_id;
        }
        if (!empty($currency_id)) {
            $where['a.currency_id'] = $currency_id;
        }
        if (!empty($type)) {
            $where['a.type'] = $type;
        }
        if (!empty($number_type)) {
            $where['a.number_type'] = $number_type;
        }
        if (!empty($id)) {
            $where['a.id'] = $id;
        }
        $field = "a.*,m.email,m.phone,at.name_tc,c.currency_name,d.currency_name as to_currency_name";
        $cList = Db::name("currency")->field("currency_id,currency_mark")->select();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $list = Db::name("accountbook")->alias('a')->field($field)->where($where)
            ->join(config("database.prefix")."member m","m.member_id=a.member_id","LEFT")
            ->join(config("database.prefix")."accountbook_type at","at.id=a.type","LEFT")
            ->join(config("database.prefix")."currency c","c.currency_id=a.currency_id","LEFT")
            ->join(config("database.prefix")."currency d","d.currency_id=a.to_currency_id","LEFT")
            ->order("id desc")->paginate(15,null,['query'=>input()])->each(function ($value,$key){
                    $type = $value['type'];
                    $value['type'] = $value['name_tc'];
                    $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
                    $value['number'] = $value['number_type'] == 1 ? $value['number'] : -$value['number'];
                    $value['after'] = $value['number'] + $value['current'];
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
                                    $value['from_phone'] = $value['phone'];
                                    $value['from_email'] = $value['email'];
                                }
                                if ($value['to_currency_id'] > 0) {
                                    $value['currency_pair'] = $value['currency_name'] . "/" . $value['to_currency_name'];
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
                    $value['current'] = keepPoint($value['current'], 6);
                    $value['number'] = keepPoint($value['number'], 6);
                    $value['after'] = keepPoint($value['after'], 6);
                    if (empty($value['from_member_id']) && (empty($value['toMemberId']) || $value['to_member_id'] == 0)) {
                        $value['from_member_id'] = $value['member_id'];
                        $value['from_phone'] = $value['phone'];
                        $value['from_email'] = $value['email'];
                    }
                return $value;
            });
        $show=$list->render();
        $numberTypeList = ['1' => "收入", "2" => "支出"];
        $typeList = Db::name("accountbook_type")->select();
        $this->assign("numberTypeList", $numberTypeList);
        $this->assign("typeList", $typeList);
        $this->assign("page", $show);
        $this->assign("cList", $cList);
        $this->assign("list", $list);
        $this->assign("data", $get);
      return  $this->fetch();
    }

    /**
     * 弹出修改账本备注框
     * Created by Red.
     * Date: 2019/1/27 17:52
     */
    function updateRemark()
    {
        if (isset($_POST['id']) && isset($_POST['ad_remark'])) {
            $update = Db::name("accountbook")->where(['id' => $_POST['id']])->update(['ad_remark' => $_POST['ad_remark']]);
            if ($update) {
                return $this->ajaxReturn(['code' => SUCCESS, 'message' => "修改成功"]);
            } else {
                return $this->ajaxReturn(['code' => ERROR1, 'message' => "没有修改任何东西"]);
            }
        }
        $id = input("id");
        $accountbook = Db::name("accountbook")->where(['id' => $id])->find();
        $this->assign("accountbook", $accountbook);
        $this->assign("id", $id);
       return $this->fetch();
    }

    /**
     * 修改自动到账日志里的XRP标签ID
     * Created by Red.
     * Date: 2019/1/28 17:41
     */
    function updateTag()
    {
        $tx = I("tx");
        if (!empty($tx)) {
            $log = M("currency_log")->where(['tx' => $tx])->find();
            if (!empty($log)) {
                //$log['afrom']截断后下标为1的不为空，说明是有标签
                $fromUrl=explode("_",$log['afrom']);
                $json=json_decode($log['trans'],true);
               //有des_tag说明是有标签的
                if(!isset($json['des_tag'])||$fromUrl[1]==""){
                    $this->assign("tx",$tx);
                   return $this->display();
                }else{
                   return $this->error("该数据有标签，不可修改");
                }
            }
        }
        return $this->error("参数错误");
    }

    /**
     * 提交修改标签ID
     * Created by Red.
     * Date: 2019/1/29 11:36
     */
    function submitUpdateTag(){
        $tx = I("tx");
        $tag=I("tag");
        $r['code']=ERROR1;
        $r['message']="参数错误";
        if(!empty($tx)&&!empty($tag)){
            if(is_numeric($tag)){
                $log=M("currency_log")->where(['tx'=>$tx])->find();
                if(!empty($log)){
                    $trans=json_decode($log['trans'],true);
                    $trans['des_tag']=$tag;
                    $log['ato']=$log['ato'].$tag;
                    $log['is_modify']=2;
                    $log['check_status']=2;
                    $log['trans']=json_encode($trans);
                   $update=M("currency_log")->where(['tx'=>$tx])->save($log);
                   if($update){
                       $r['code']=SUCCESS;
                       $r['message']="修改标签ID成功";
                   }else{
                       $r['message']="修改标签ID失败";
                   }
                }else{
                    $r['message']="数据有误";
                }
            }else{
                $r['message']="标签ID必须为整数";
            }

        }
        return $this->ajaxReturn($r);
    }

//    public function temp40()
//    {
//            $list=M("temp40")->where(['is_give'=>1])->select();
//            if(!empty($list)){
//                foreach ($list as $k=>$value){
//                    $member=M("member")->where(['member_id'=>$value['member_id']])->field("member_id")->find();
//                    if(!empty($member)){
//                        $member_id = $value['member_id'];
//                        $currency_id = 9;
//                        $money = 6000;
//                        M()->startTrans();//开启事务
//                        $data['message']='升V用户，亿指示充值(赠送)';
//                        $data['admin_id'] = $_SESSION['admin_userid'];
//                        $data['member_id'] = $member_id;
//                        $data['currency_id'] = $currency_id;
//                        $data['money'] = $money;
//                        $data['status'] = 1;
//                        $data['add_time'] = time();
//                        $data['type'] = 8;//管理员锁仓充值
//                        $data['rate']=100;
//                        $data['lock_money']=$data['money'];
//                        $data['exchange_money']=0;
//                        M()->startTrans();//开启事务
//                        $r[] = $pay_id = M('pay')->add($data);
//                        //添加锁仓记录
//                        $r[] = M('currency_gac_forzen')->add([
//                            'member_id' => $data['member_id'],
//                            'num' => $data['money'],
//                            'type' => 10,
//                            'title' => 'lan_exchange_admin_pay',
//                            'from_num' => 0,
//                            'ratio' => 0,
//                            'add_time' => time(),
//                            'third_id' => 0,
//                        ]);
//
//                        //增加锁仓资产
//                        $info = M('currency_user')->lock(true)->where(['member_id' => $data['member_id'], 'currency_id' => $data['currency_id']])->find();
//                        if ($info) {
//                            $r[] = M('Currency_user')->where(array('member_id' => $data['member_id'], array('currency_id' => $data['currency_id'])))->setInc('num_award', $data['money']);
//                        } else {
//                            $r[] = M('Currency_user')->add([
//                                'member_id' => $data['member_id'],
//                                'currency_id' => $data['currency_id'],
//                                'num' => 0,
//                                'num_award' => $data['money'],
//                            ]);
//                        }
//                        $r[]= M("temp40")->where(['member_id'=>$member_id])->save(['is_give'=>2]);
//                        if (!in_array(false, $r)) {
//                            M()->commit();
//                            var_dump("第 ".$k." 条处理ID：".$member_id."  成功");
//                        } else {
//                            M()->rollback();
//                            var_dump("处理ID：".$member_id."  失败");
//                        }
//                    }
//
//                }
//            }
//            var_dump("处理完成");
//
//    }
//
//    public function temp208()
//    {
//        $list=M("temp208")->where(['is_give'=>1])->select();
//        if(!empty($list)){
//            foreach ($list as $k=>$value){
//                $member=M("member")->where(['member_id'=>$value['member_id']])->field("member_id")->find();
//                if(!empty($member)){
//                    $member_id = $value['member_id'];
//                    $currency_id = 9;
//                    $money = 10000;
//                    M()->startTrans();//开启事务
//                    $data['message']='升V用户，亿指示充值(赠送)';
//                    $data['admin_id'] = $_SESSION['admin_userid'];
//                    $data['member_id'] = $member_id;
//                    $data['currency_id'] = $currency_id;
//                    $data['money'] = $money;
//                    $data['status'] = 1;
//                    $data['add_time'] = time();
//                    $data['type'] = 8;//管理员锁仓充值
//                    $data['rate']=100;
//                    $data['lock_money']=$data['money'];
//                    $data['exchange_money']=0;
//                    M()->startTrans();//开启事务
//                    $r[] = $pay_id = M('pay')->add($data);
//                    //添加锁仓记录
//                    $r[] = M('currency_gac_forzen')->add([
//                        'member_id' => $data['member_id'],
//                        'num' => $data['money'],
//                        'type' => 10,
//                        'title' => 'lan_exchange_admin_pay',
//                        'from_num' => 0,
//                        'ratio' => 0,
//                        'add_time' => time(),
//                        'third_id' => 0,
//                    ]);
//
//                    //增加锁仓资产
//                    $info = M('currency_user')->lock(true)->where(['member_id' => $data['member_id'], 'currency_id' => $data['currency_id']])->find();
//                    if ($info) {
//                        $r[] = M('Currency_user')->where(array('member_id' => $data['member_id'], array('currency_id' => $data['currency_id'])))->setInc('num_award', $data['money']);
//                    } else {
//                        $r[] = M('Currency_user')->add([
//                            'member_id' => $data['member_id'],
//                            'currency_id' => $data['currency_id'],
//                            'num' => 0,
//                            'num_award' => $data['money'],
//                        ]);
//                    }
//                    $r[]= M("temp208")->where(['member_id'=>$member_id])->save(['is_give'=>2]);
//                    if (!in_array(false, $r)) {
//                        M()->commit();
//                        var_dump("第 ".$k." 条处理ID：".$member_id."  成功");
//                    } else {
//                        M()->rollback();
//                        var_dump("处理ID：".$member_id."  失败");
//                    }
//                }
//
//            }
//        }
//        var_dump("处理完成");
//
//    }
}
