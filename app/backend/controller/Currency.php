<?php

namespace app\backend\controller;

use app\common\model\AccountBook;
use app\common\model\ConvertConfig;
use app\common\model\ConvertLog;
use app\common\model\CurrencyUser;
use app\common\model\Recharge;
use app\common\model\RocketOrder;
use app\common\model\ShopConfig;
use app\common\model\Tibi;
use app\common\model\WalletEverydayRecharge;
use app\common\model\WechatTransfer;
use app\common\model\WithdrawConfig;
use think\Db;
use think\Request;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Exception;

/**
 * 财务管理
 * Class Currency
 * @package app\backend\controller
 */
class Currency extends AdminQuick
{
    protected $pid = 'currency_id';
    protected $allow_switch_field = ['recharge_switch', 'take_switch', 'release_switch', 'exchange_switch', 'is_line', 'fee_less', 'fee_number_flag',
        'currency_transfer_switch', 'mutual_switch'];
    protected $public_action = ['getnamebyid', 'child_currency_num', 'add', 'edit', 'withdraw_config'];
    protected $allow_delete = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model("currency");
    }

    protected function indexWhere(Request $request)
    {
        return ['status' => 1];
    }

    /**
     * 提现管理 - 列表
     * @return mixed
     * @throws DbException
     */
    public function withdraw_list()
    {
        if (input('mit')) {
            $message1 = input('message1');
            $id = input('id');
            $condition['id'] = $id;
            $data['message1'] = $message1;
            $data['admin_id1'] = session('admin_id');
//            if ($data['admin_id1'] != 16) {//一审：545931506@qq.com
//                $this->error('请联系meilf管理员进行一审');
//            }
            //判断是否有数据
            $find_data = WechatTransfer::where($condition)->find();
            //判断是否已在审核2提交
            if (session("admin_id") > 1) {
                if ($find_data['admin_id2'] == session('admin_id')) {
                    $this->error('已在审核2提交，不可重复提交');
                }
            }
            //判断是否已经有数据
            if ($find_data['message1']) {
                if (session("admin_id") == 1) {
                    $rs = WechatTransfer::where($condition)->update($data);
                } else {
                    $this->error('没有权限更改数据');
                }
            } else {
                $rs = WechatTransfer::where($condition)->update($data);
            }
            if ($rs != false) {
                $this->success('提交成功');
            } else {
                $this->error('提交失败');
            }
        }

        if (input('mit2')) {
            $message2 = input('message2');
            $id = input('id');
            $condition['id'] = $id;
            $data2['message2'] = $message2;
            $data2['admin_id2'] = session("admin_id");
//            if ($data2['admin_id2'] != 13) {//二审：415014503@qq.com
//                $this->error('请联系shop管理员进行二审');
//            }
            //判断是否有数据
            $find_data = WechatTransfer::where($condition)->find();
            //判断是否已在审核2提交
            if (session("admin_id") > 1) {
                if ($find_data['admin_id1'] == session("admin_id")) {
                    $this->error('已在审核1提交，不可重复提交');
                }
            }
            //判断是否已经有数据
            if ($find_data['message2']) {
                if (session("admin_id") == 1) {
                    $rs = WechatTransfer::where($condition)->update($data2);
                } else {
                    $this->error('没有权限更改数据');
                }
            } else {
                $rs = WechatTransfer::where($condition)->update($data2);
            }
            if ($rs != false) {
                $this->success('提交成功');
            } else {
                $this->error('提交失败');
            }
        }

        $where = [];
        $member_id = input('member_id');
        if (!empty($member_id)) $where['member_id'] = $member_id;
        $check_status = input('check_status');
        if (!empty($check_status)) {
            $where['check_status'] = $check_status;
            if ($check_status == 3) {
                $where['check_status'] = 0;
            }
        }
        $pay_status = input('pay_status');
        if (!empty($pay_status)){
            if ($pay_status == 1) {
                $where['pay_status'] = 0;
            } else {
                $where['pay_status'] = 1;
            }
        }

        $check_status = ['未处理', '通过', '拒绝'];
        $pay_status = ['未支付', '支付成功', '支付失败'];
        $export = $this->request->param('export');
        if ($export == 1) {
            $where = [];
            $member_id = input('member_id');
            if (!empty($member_id)) $where['a.member_id'] = $member_id;
            $status = input('check_status');
            if (!empty($status)) {
                $where['a.check_status'] = $status;
                if ($status == 3) {
                    $where['a.check_status'] = 0;
                }
            }
            $p_status = input('pay_status');
            if (!empty($p_status)) {
                if ($p_status == 1) {
                    $where['a.pay_status'] = 0;
                } else {
                    $where['a.pay_status'] = 1;
                }
            }
            $list = WechatTransfer::alias('a')
                ->join('wechat_bind b', 'a.member_id=b.member_id and b.status=1', 'left')
                ->join('member_bank c', 'a.member_id=c.member_id  and c.status=1', 'left')
                ->field('a.*,b.actual_name,c.truename as cactual_name,b.wechat_account,c.bankcard as bank_card,c.bankadd as open_bank')
                ->where($where)->order('a.id', 'desc')
                ->select();
            $list1 = [];
            if ($list) {
                foreach ($list as $key => $value) {
                    $list1[$key]['member_id'] = $value['member_id'];
                    $list1[$key]['actual_name'] = empty($value['actual_name']) ? $value['cactual_name'] : $value['actual_name'];
//                    $list1[$key]['wechat_account'] = "\t".empty($value['wechat_account']) ? $value['cwechat_account'] : $value['wechat_account']."\t";
                    $list1[$key]['bank_card'] = "\t".$value['bank_card']."\t";
                    $list1[$key]['open_bank'] = $value['open_bank'];
                    $list1[$key]['num'] = "\t".sprintf('%.2f', $value['fee'] + $value['amount'])."\t";
                    $list1[$key]['fee'] = "\t".$value['fee']."\t";
                    $list1[$key]['amount'] = "\t".$value['amount']."\t";
                    $list1[$key]['add_time'] = date('Y-m-d H:i:s', $value['add_time']);
                    $list1[$key]['check_status'] = $check_status[$value['check_status']];
                    $list1[$key]['desc'] = $value['desc'] ? $value['desc'] : '未填写审核备注';
                    $list1[$key]['message1'] = $value['message1'];
                    $list1[$key]['message2'] = $value['message2'];
                    $list1[$key]['pay_status_name'] = $pay_status[$value['pay_status']];
                }
            }
            $xlsCell = array(
                array('member_id', '会员ID', 20),
                array('actual_name', '真实姓名', 20),
                array('bank_card', '银行卡号', 40),
                array('open_bank', '开户银行', 20),
                array('num', '实际扣款金额', 20),
                array('fee', '手续费', 20),
                array('amount', '实际到账金额', 20),
                array('add_time', '提现时间', 20),
                array('check_status', '审核状态', 12),
                array('desc', '审核备注', 100),
                array('message1', '一审', 100),
                array('message2', '二审', 100),
                array('pay_status_name', '支付状态', 20),
            );
            $this->exportExcel("提现列表", $xlsCell, $list1);
            die();
        }

        $list = WechatTransfer::with(['wechatbind'])->where($where)->order('id', 'desc')->paginate(null, null, ["query" => $this->request->get()]);
        $hm_price = ShopConfig::get_value('hm_price',6.1);
        foreach ($list as &$value) {
            $value['check_status'] = $check_status[$value['check_status']];
            $value['desc'] = $value['desc'] ? $value['desc'] : '未填写审核备注';
            $value['pay_status_name'] = $pay_status[$value['pay_status']];
            $value['pay_num'] = keepPoint($value['amount']*$hm_price,2);

            $value['memberbank'] = ['actual_name' => '', 'wechat_account' => '', 'bank_card' => '', 'open_bank' => ''];
            if ($value['type'] == 2) {
                $memberbank = Db::name('member_bank')->where(['member_id' => $value['member_id']])->find();
                if ($memberbank) {
                    $memberbank['actual_name'] = $memberbank['truename'];
                    $memberbank['bank_card'] = $memberbank['bankcard'];
                    $memberbank['open_bank'] = $memberbank['bankadd'];
                    $value['memberbank'] = $memberbank;
                }
            }
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 提现管理 - 提现审核
     * @param Request $request
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function withdraw_review(Request $request)
    {
        if ($request->isPost()) {
            $r = ['code' => ERROR1, 'message' => '审核成功'];
            try {
                Db::startTrans();
                $saveData = $request->only(['id', 'form']);
                $info = WechatTransfer::where(['id' => $saveData['id']])->find();
                if (empty($info['message1']) || empty($info['message2'])) {
                    return ['code' => ERROR1, 'message' => '请先一审、二审后提交'];
                }
                if (empty($saveData['form']['desc'])) {
                    return ['code' => ERROR1, 'message' => '备注不能为空'];
                }
                if ($info['check_status'] != 0) {
                    return ['code' => ERROR1, 'message' => '该提现记录已审批，不能重复审批'];
                }
                $saveData['form']['update_time'] = time();
                $isUpdate = WechatTransfer::where('id', $saveData['id'])->update($saveData['form']);
                if (!$isUpdate) {
                    throw new Exception('审核失败' . '-' . __LINE__);
                }
                $info = WechatTransfer::where(['id' => $saveData['id']])->find();
                $totalMoney = keepPoint($info['fee'] + $info['amount']);
                $CurrencyUser = \app\common\model\CurrencyUser::getCurrencyUser($info['member_id'], $info['currency_id']);
                if ($saveData['form']['check_status'] == 2) {
                    // 钱包账户增加
                    $flag = \app\common\model\AccountBook::add_accountbook($info['member_id'], $info['currency_id'], 125, 'wechat_reduce_withdraw_refuse', 'in', $totalMoney, $info['id']);
                    if (!$flag) {
                        throw new Exception('审核失败' . '-' . __LINE__);
                    }

                    // 增加数量及手续费
                    $flag = \app\common\model\CurrencyUser::where([
                        'member_id' => $info['member_id'],
                        'currency_id' => $info['currency_id'],
                        'forzen_num' => $CurrencyUser->forzen_num
                    ])
                        ->inc('num', $totalMoney)
                        ->dec('forzen_num', $totalMoney)
                        ->update();
                    if (!$flag) {
                        throw new Exception('审核失败' . '-' . __LINE__);
                    }
                } elseif ($saveData['form']['check_status'] == 1) {
                    // 扣除数量及手续费
                    $flag = \app\common\model\CurrencyUser::where([
                        'member_id' => $info['member_id'],
                        'currency_id' => $info['currency_id'],
                        'forzen_num' => $CurrencyUser->forzen_num
                    ])
                        ->dec('forzen_num', $totalMoney)
                        ->update();
                    if (!$flag) {
                        throw new Exception('审核失败' . '-' . __LINE__);
                    }
                }
                Db::commit();
            } catch (Exception $exception) {
                Db::rollback();
                $r['message'] = $exception->getMessage();
            }
            $r['code'] = SUCCESS;
            return $r;
        }

        $id = $request->param('id', 0);
        $info = WechatTransfer::with(['wechatbind'])->where(['id' => $id])->find();
        return $this->fetch(null, compact('info'));
    }

    /**
     * 提现管理 - 确认转账
     * @param Request $request
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function withdraw_transfer(Request $request)
    {
        $id = $request->param('id', 0);
        $transferInfo = WechatTransfer::where(['id' => $id])->find();
        if (empty($transferInfo)) {
            return ['code' => ERROR1, 'message' => '找不到相关数据'];
        }

        if ($transferInfo['check_status'] != 1) {
            return ['code' => ERROR1, 'message' => '请先进行审核操作'];
        }

        if ($transferInfo['pay_status'] > 0) {
            return ['code' => ERROR1, 'message' => '已经转过账'];
        }

        // 线下转账
        if ($transferInfo['pay_type'] == 2) {
            $isUpdate = WechatTransfer::where('id', $transferInfo['id'])
                ->update([
                    'pay_status' => 1,
                    'payment_time' => time(),
                ]);
            if ($isUpdate) {
                return ['code' => SUCCESS, 'message' => '支付成功'];
            }
            return ['code' => ERROR1, 'message' => '支付失败'];
        }

        // 线上转账
//        return WeChatPay::WeChatTransfers($transferInfo['partner_trade_no']);
    }

    /**
     * 充值管理
     * @param Request $request
     * @return mixed
     * @throws DbException
     */
    public function recharge_list(Request $request)
    {
        $user_id = $request->get('user_id');
        if (!empty($user_id)) $where['p.member_id'] = $user_id;
        $where['p.type'] = 3;
        $list = Db::name('pay')->alias("p")
            ->field(['p.member_id', 'm.nick', 'm.phone', 'm.email', 'c.currency_name', 'p.money', 'p.add_time', 'p.message', 'a.username'])
            ->join(config("database.prefix") . "member m", "m.member_id=p.member_id", "LEFT")
            ->join(config("database.prefix") . "backend_users a", "a.id=p.admin_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "c.currency_id=p.currency_id", "LEFT")
            ->where($where)->order('add_time desc')->paginate(20, null, ['query' => $request->get()]);

        foreach ($list as $value) {
            if (empty($value['nick'])) $value['nick'] = !empty($value['phone']) ? $value['phone'] : $value['email'];
        }
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 充值操作
     * @param Request $request
     * @return array|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function recharge_admin(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();
            if (empty($data['member_id'])) {
                return ['code' => ERROR1, 'message' => '请输入充值人员'];
            }
            if (!isset($data['currency_id'])) {
                return ['code' => ERROR1, 'message' => '请输入积分类型'];
            }
            if (empty($data['money'])) {
                return ['code' => ERROR1, 'message' => '请输入充值金额'];
            }
            if (empty($data['message'])) {
                return ['code' => ERROR1, 'message' => '请输入充值备注'];
            }
            if (!Db::name('Member')->where(['member_id' => $data['member_id']])->find()) {
                return ['code' => ERROR1, 'message' => '用户不存在'];
            }

            Db::startTrans();

            $data['admin_id'] = session('admin_id');
            $data['status'] = 1;
            $data['add_time'] = time();
            $data['type'] = 3;//管理员充值类型

            $r[] = $pay_id = Db::name('pay')->insertGetId($data);
            if ($data['currency_id'] == 0) {
                $r[] = Db::name('Member')->where(array('member_id' => $data['member_id']))->setInc('rmb', $data['money']);
            } else {
                $money = $data['money'];
                $type = 13;
                $content = 'lan_admin_recharge';
                $number_type = 1;
                if ($data['money'] < 0) {
                    $money = abs($data['money']);
                    $type = 6510;
                    $content = 'lang_admin_recharge_minus';
                    $number_type = 2;
                }
                //添加账本信息
                $r[] = model('AccountBook')->addLog([
                    'member_id' => $data['member_id'],
                    'currency_id' => $data['currency_id'],
                    'type' => $type,
                    'content' => $content,
                    'number_type' => $number_type,
                    'number' => $money,
                    'add_time' => time(),
                    'third_id' => $pay_id,
                ]);
                $info = Db::name('currency_user')->lock(true)->where(['member_id' => $data['member_id'], 'currency_id' => $data['currency_id']])->find();
                if ($info) {
                    if ($number_type == 1) {
                        $r[] = Db::name('currency_user')->where(['member_id' => $data['member_id'], 'currency_id' => $data['currency_id']])->setInc("num", $money);
                    } else {
                        $r[] = Db::name('currency_user')->where(['member_id' => $data['member_id'], 'currency_id' => $data['currency_id']])->setDec("num", $money);
                    }
                } else {
                    if ($number_type == 1) {
                        $r[] = Db::name('Currency_user')->insertGetId([
                            'member_id' => $data['member_id'],
                            'currency_id' => $data['currency_id'],
                            'num' => $data['money'],
                        ]);
                    }
                }
            }
            $r[] = $this->addFinance($data['member_id'], 3, "管理员充值", $data['money'], 1, $data['currency_id']);
            $r[] = $this->addMessage_all($data['member_id'], -2, "管理员充值", "管理员充值" . getCurrencynameByCurrency($data['currency_id']) . ":" . $data['money']);
            if (!in_array(false, $r)) {
                Db::commit();
                return ['code' => SUCCESS, 'message' => '添加成功'];
            } else {
                Db::rollback();
                return ['code' => ERROR1, 'message' => '添加失败'];
            }
        }

        $currency = Db::name('Currency')->where(['is_line' => 1, 'status' => 1])->field('currency_name,currency_mark,currency_id,is_trade_currency,account_type')->order("sort asc")->select();
        return $this->fetch(null, compact('currency'));
    }

    /**
     * 获取用户名称
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getnamebyid()
    {
        $info = Db::name("Member")->where("member_id = {$_POST['id']}")->find();
        if ($info) {
            if (empty($info['nick'])) $info['nick'] = !empty($info['phone']) ? $info['phone'] : $info['email'];
            return ['code' => SUCCESS, 'message' => $info['nick']];
        }
        return ['code' => ERROR1, 'message' => '找不到相关用户'];
    }

    /**
     * 快捷闪兑 - 配置
     * @return mixed
     * @throws DbException
     */
    public function convert_config()
    {
        $where = [];
        $member_id = input('member_id');
        if (!empty($member_id)) $where['member_id'] = $member_id;

        $status = ['0' => '开启', '1' => '关闭'];
        $type = ['con_db_hz' => '代币闪兑区', 'con_jf_hz' => '积分闪兑区'];
        $list = ConvertConfig::with(['currency', 'to_currency'])->where($where)->paginate(null, null, ["query" => $this->request->get()]);
//        echo "<pre>";
//        print_r(collection($list)->toArray());exit;
//        foreach ($list as &$value) {
//            $value['status'] = $status[$value['status']];
//            $value['type'] = $type[$value['type']];
//        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 快捷闪兑 - 添加
     * @return array|mixed
     */
    public function convert_add()
    {
        if ($this->request->isPost()) {
            $saveData = $this->request->only(['form']);
            $isUpdate = ConvertConfig::insert($saveData['form']);
            if ($isUpdate) {
                return ['code' => SUCCESS, 'message' => '添加成功'];
            }
            return ['code' => ERROR1, 'message' => '添加失败'];
        }
        $currency = (new \app\common\model\Currency)->online_list();
        $type = ['con_db_hz' => '代币闪兑区', 'con_jf_hz' => '积分闪兑区'];
        return $this->fetch(null, compact('currency', 'type'));
    }

    /**
     * 快捷闪兑 - 编辑
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function convert_edit()
    {
        if ($this->request->isPost()) {
            $saveData = $this->request->only(['id', 'form']);
            $isUpdate = ConvertConfig::where('id', $saveData['id'])
                ->update($saveData['form']);
            if ($isUpdate) {
                return ['code' => SUCCESS, 'message' => '修改成功'];
            }
            return ['code' => ERROR1, 'message' => '修改失败'];
        }

        $id = $this->request->param('id', 0);
        $info = ConvertConfig::where(['id' => $id])->find();

        $currency = (new \app\common\model\Currency)->online_list();
        $type = ['con_db_hz' => '代币闪兑区', 'con_jf_hz' => '积分闪兑区'];
        return $this->fetch(null, compact('info', 'currency', 'type'));
    }

    /**
     * 快捷闪兑 - 记录
     * @return mixed
     * @throws DbException
     */
    public function convert_log()
    {
        $where = [];
        $member_id = input('member_id');
        if (!empty($member_id)) $where['user_id'] = $member_id;

        $status = ['未处理', '已完成'];
        $list = ConvertLog::with(['currency', 'toCurrency'])->where($where)->order('create_time', 'desc')->paginate(null, null, ["query" => $this->request->get()]);
        foreach ($list as &$value) {
            $value['status'] = $status[$value['status']];
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 添加财务日志方法
     * @param $member_id
     * @param $type
     * @param $content
     * @param $money
     * @param int $money_type 收入=1/支出=2
     * @param int $currency_id 积分类型id 0是rmb
     * @return false|int|string
     */
    protected function addFinance($member_id, $type, $content, $money, $money_type, $currency_id)
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

    /**
     * 添加消息库
     * @param int $member_id 用户ID -1 为群发
     * @param int $type 分类  4=系统  -1=文章表系统公告 -2 个人信息
     * @param String $title 标题
     * @param String $content 内容
     * @return bool|mixed  成功返回增加Id 否则 false
     */
    protected function addMessage_all($member_id, $type, $title, $content)
    {
        $data['u_id'] = $member_id;
        $data['type'] = $type;
        $data['title'] = $title;
        $data['content'] = $content;
        $data['add_time'] = time();
        $id = Db::name('Message_all')->insertGetId($data);
        if ($id) {
            return $id;
        } else {
            return false;
        }
    }

    /**
     * 充值审核
     * @param Request $request
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     * @throws \think\exception\PDOException
     */
    public function review_list(Request $request)
    {
        $userId = $request->get('user_id', null, 'intval');
        $status = $request->get('status', null, 'intval');

        $where = [];

        if (!empty($userId)) {
            $where['user_id'] = $userId;
        }

        if (!empty($status)) {
            $where['status'] = $status;
        }

        $list = Recharge::where($where)->with('currency')->order('id', 'desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        $enum = Recharge::STATUS_ZH_CN_MAP;
        return $this->fetch(null, compact('list', 'page', 'count', 'enum'));
    }

    /**
     * 一审
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function review_first()
    {
        $message1 = input('message1');
        $id = input('id');

        $data = [
            'message1' => $message1,
            'admin_id1' => session("admin_id"),
        ];

        //判断是否有数据
        $where['id'] = $id;
        $find_data = Recharge::where($where)->find();

        //判断是否已经有数据
        if ($find_data['message1']) {
            if (session("admin_id") == 1) {
                $rs = Recharge::where($where)->update($data);
                if ($rs === false) return ['code' => ERROR1, 'message' => '提交失败'];
            } else {
                return ['code' => ERROR1, 'message' => '没有权限更改数据'];
            }
        } else {
            $rs = Recharge::where($where)->update($data);
            if ($rs === false) return ['code' => ERROR1, 'message' => '提交失败'];
        }
        return ['code' => SUCCESS, 'message' => '提交成功'];
    }

    /**
     * 二审
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function review_second()
    {
        $message2 = input('message2');
        $id = input('id');
        $condition['id'] = $id;
        $data['message2'] = $message2;
        $data['admin_id2'] = session("admin_id");
        // 判断是否有数据
        $find_data = Recharge::where($condition)->find();
        // 判断是否已经有数据
        if ($find_data['message2']) {
            if (session("admin_id") == 1) {
                $rs = Recharge::where($condition)->update($data);
                if ($rs === false) return ['code' => ERROR1, 'message' => '提交失败'];
            } else {
                return ['code' => ERROR1, 'message' => '没有权限更改数据'];
            }
        } else {
            $rs = Recharge::where($condition)->update($data);
            if ($rs === false) return ['code' => ERROR1, 'message' => '提交失败'];
        }
        return ['code' => SUCCESS, 'message' => '提交成功'];
    }

    /**
     * 通过操作
     * @param Request $request
     * @return \json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function review_success(Request $request)
    {
        $r = ['code' => ERROR1, 'message' => "参数错误", 'result' => null];

        $id = $request->post('id', 0, 'intval');
        $tx = $request->post('tx', null);
        $verifyNumber = $request->post('verify_number', 0);
        if (empty($id) or empty($tx) or $verifyNumber <= 0) {
            return $r;
        }

        $find = Recharge::where(['id' => $id, 'status' => Recharge::STATUS_VERIFY])->find();
        if (empty($find['admin_id1']) or empty($find['admin_id2'])) {
            $r['message'] = "请先完成1审2审";
            return $r;
        }

        try {
            Db::startTrans();
            // 查询充币数据
            $data = Recharge::where('id', $id)->find();
            if ($data['status'] != Recharge::STATUS_VERIFY) {
                throw new Exception("该数据已审核!");
            }
            if ($data['currency_id'] == \app\common\model\Currency::ERC20_ID) { // ERC20 账本和资产跟随 USDT
                $data['currency_id'] = \app\common\model\Currency::USDT_ID;
            }
            $flag = AccountBook::add_accountbook($data['user_id'], $data['currency_id'], 5, 'lan_chongbi', 'in', $verifyNumber, $data['id']);
            if (empty($flag)) {
                throw new Exception("系统错误，请稍后再试!");
            }
            $userCurrency = CurrencyUser::getCurrencyUser($data['user_id'], $data['currency_id']);
            $userCurrency['num'] += $verifyNumber;
            if (!$userCurrency->save()) {
                throw new Exception("系统错误，请稍后再试!");
            }

            $data['tx'] = $tx;
            $data['verify_number'] = $verifyNumber;
            $data['status'] = Recharge::STATUS_SUCCESS;
            $data['verify_time'] = time();

            $rechange = $data;

            if (!$data->save()) {
                throw new Exception("系统错误，请稍后再试!");
            }

            //添加充币记录
            $check_time = time();
            $tibiData = new Tibi();
            $tibiData->to_member_id = $rechange['user_id'];
            $tibiData->to_url = $rechange['to'];
            $tibiData->num = $verifyNumber;
            $tibiData->status = 3;
            $tibiData->ti_id = $tx;
            $tibiData->check_time = $check_time;
            $tibiData->currency_id = $rechange['currency_id'];
            $tibiData->actual = $verifyNumber;
            $tibiData->b_type = 0;
            $tibiData->transfer_type = "1";
            $tibiData->add_time = $rechange['add_time'];
            if (!$tibiData->save()) {
                throw new Exception("添加充币记录失败");
            }

            //增加每日充币统计
            $everydayRecharge = WalletEverydayRecharge::addWalletEverydayRecharge($rechange['currency_id'], $verifyNumber, $check_time);
            if (!$everydayRecharge) {
                throw new Exception("增加每日充币统计失败");
            }

            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = "审核成功";
        } catch (\Exception $exception) {
            Db::rollback();
            $r['code'] = ERROR2;
            $r['message'] = $exception->getMessage();
        }
        return mobileAjaxReturn($r);
    }

    /**
     * 拒绝操作
     * @param Request $request
     * @return \json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function review_fail(Request $request)
    {
        $id = $request->post('id', 0, 'intval');
        $r = [
            'code' => ERROR1,
            'message' => "参数错误",
            'result' => null
        ];
        if (empty($id)) {
            return mobileAjaxReturn($r);
        }
        $find = Db::name("recharge")->where(['id' => $id, 'status' => Recharge::STATUS_VERIFY])->find();
        if (empty($find['admin_id1']) or empty($find['admin_id2'])) {
            $r['message'] = "请先完成1审2审";
            return mobileAjaxReturn($r);
        }
        try {
            Db::startTrans();
            // 查询充币数据
            $data = Recharge::where('id', $id)->find();
            if ($data['status'] != Recharge::STATUS_VERIFY) {
                throw new Exception("该数据已审核!");
            }

            $data['status'] = Recharge::STATUS_FAIL;
            $data['verify_time'] = time();
            if (!$data->save()) {
                throw new Exception("系统错误，请稍后再试!");
            }
            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = "审核成功";
        } catch (\Exception $exception) {
            Db::rollback();
            $r['message'] = $exception->getMessage();
        }

        return mobileAjaxReturn($r);
    }

    /**
     * 用户余额
     * @param Request $request
     * @return \json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function balance_log(Request $request)
    {
        $where = [];
        $member_id = $this->request->param('member_id', 0);
        if ($member_id) {
            $where['member_id'] = $member_id;
        }

        $list = \app\common\model\Member::field('member_id,ename')->where($where)->order(['reg_time' => 'desc'])->paginate(null, null, ["query" => $this->request->get()]);
        if ($list) {
            $today_start = strtotime(date('Y-m-d'));
            $today_end = $today_start + 86399;
            $where = ['gmo_status' => ['in', [\app\common\model\GoodsMainOrders::STATUS_PAID, \app\common\model\GoodsMainOrders::STATUS_SHIPPED, \app\common\model\GoodsMainOrders::STATUS_COMPLETE]]];
            foreach ($list as &$value) {
                $value['first_num'] = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 74])->value('num');//购物券
                $value['second_num'] = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 75])->value('num');//积分
                $res = \app\common\model\OrderReward::where(['member_id' => $value['member_id'], 'status' => 1])->field('sum(pers_num) as pers_num,sum(surplus_reward) as surplus_reward')->find();
                $value['share_integral'] = keepPoint($res['pers_num'] - $res['surplus_reward'], 2);

//                $pers_num = \app\common\model\OrderDaySummary::where(['member_id' => $value['member_id'], 'count_time' => $today_start])->value('pers_total');
//                $pers_total = \app\common\model\OrderTotal::where(['member_id' => $value['member_id']])->value('pers_total');
                $pers_num = \app\common\model\GoodsMainOrders::where(['gmo_user_id' => $value['member_id'], 'gmo_pay_time' => ['between', [$today_start, $today_end]]])->where($where)->sum('gmo_pay_num');
                $pers_total = \app\common\model\GoodsMainOrders::where(['gmo_user_id' => $value['member_id']])->where($where)->sum('gmo_pay_num');
                $value['today_pers_num'] = $pers_num;
                $value['pers_total'] = $pers_total;
            }
        }

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 下级资产数量
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function child_currency_num()
    {
        $member_id = intval(input('member_id'));

        //下级合约资产数量
        $currency_list = \app\common\model\Currency::field('currency_id,currency_name,is_trade_currency')->where(['is_line' => 1])->select();
        $currency_list = array_column($currency_list->toArray(), null, 'currency_id');
        foreach ($currency_list as &$currency) {
            $currency['num'] = $currency['forzen_num'] = $currency['hb_num'] = $currency['dnc_lock'] = $currency['contract_num'] = 0;
        }

        if ($member_id) {
            //下级资产总数量
            $num = Db::query('select currency_id,sum(dnc_lock) as dnc_lock,sum(dnc_other_lock) as dnc_other_lock,sum(num) as num,sum(forzen_num) as forzen_num from ' . config("database.prefix") . 'currency_user  
            where member_id in(
                select child_id from ' . config("database.prefix") . 'member_bind where member_id=' . $member_id . '
            ) group by currency_id');

            if ($num) {
                $num = array_column($num, null, 'currency_id');
                foreach ($num as $item) {
                    if ($item['currency_id'] && isset($currency_list[$item['currency_id']])) {
                        $currency_list[$item['currency_id']]['num'] = $item['num'];
                        $currency_list[$item['currency_id']]['forzen_num'] = $item['forzen_num'];
                        $currency_list[$item['currency_id']]['dnc_lock'] = $item['dnc_lock'] + $item['dnc_other_lock'];
                    }
                }
            }

            //下级合约冻结数量
            $contract_num = Db::query('select money_currency_id,sum(money_currency_num) as num from ' . config("database.prefix") . 'contract_order
			where  money_type=1 AND `status` IN (1,2,3,4) and member_id in (
				select child_id from ' . config("database.prefix") . 'member_bind where member_id=' . $member_id . '
			)');
            if ($contract_num) {
                $contract_num = array_column($contract_num, null, 'currency_id');
                foreach ($contract_num as $item) {
                    if ($item['money_currency_id'] && isset($currency_list[$item['money_currency_id']])) $currency_list[$item['money_currency_id']]['contract_num'] = $item['num'];
                }
            }
        }
        return $this->fetch(null, compact('currency_list'));
    }


    /**
     * 获取用户信息
     * @param $member_id 用户id
     * @param string $field
     * @return array|bool|\PDOStatement|string|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getMemberInfo($member_id, $field = "ename,email,phone")
    {
        if (!empty($member_id)) {
            return Db::name("member")->where(['member_id' => $member_id])->field($field)->find();
        }
        return null;
    }

    /**
     * 币种简介
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function introduce(Request $request)
    {
        $currency_id = input('currency_id');
        $Currency_introduce = Db::name('Currency_introduce');
        if ($request->isPost()) {
            $data = $request->post();

            $data['feature'] = input('feature');//主要特色
            $data['short'] = input('short');//不足之处
            $data['advantage'] = input('advantage');//综合优势

            $find = $Currency_introduce->where('currency_id', $currency_id)->find();
            if ($find) {
                $rs = $Currency_introduce->where('currency_id', $currency_id)->update($data);
            } else {
                $rs = $Currency_introduce->insertGetId($data);
            }
            if ($rs === false) {
                $this->error('操作失败');
                //return json(['code' => ERROR2, 'message' => '操作失败']);
            } else {
                $this->success("操作成功", url("wallet/introduce") . "?param=wallet&currency_id=" . $currency_id);
                //return json(['code' => SUCCESS, 'message' => '操作成功!']);
            }
        }
        $list = Db::name('Currency_introduce')->where('currency_id', $currency_id)->find();
        $currency = \app\common\model\Currency::where(['currency_id' => $currency_id])->find();
        if (!$list) {
            $list = [
                'currency_id' => $currency_id,
                'english_name' => $currency['currency_name'],
                'pushout_time' => '',
                'china_name' => '',
                'english_short' => $currency['currency_mark'],
                'designer' => '',
                'core_algorithm' => '',
                'release_date' => '',
                'block_speed' => '',
                'total_circulation' => '',
                'base_num' => 0,
                'stock' => 0,
                'proof_mode' => '',
                'difficulty_adjust' => '',
                'block_rewards' => '',
                'feature' => '',
                'short' => '',
                'advantage' => '',
                'daibi_turnover' => '',
                'white_paper' => '',
                'website' => '',
                'raise_price' => '',
                'block_query' => '',
            ];
        }
        return $this->fetch(null, compact('list', 'currency_id'));
    }

    /**
     * 总财务
     * @param Request $request
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function total_finance(Request $request)
    {
//        if ($request->isAjax()) {
//            $result = Db::name('currency_user_summary')->alias('a')
//                ->join('currency b', 'b.currency_id=a.currency_id', 'left')
//                ->field(['sum(a.charge)' => 'charge', 'sum(a.carry)' => 'carry',
//                    'sum(a.static)' => 'static', 'sum(a.dynamic)' => 'dynamic', 'a.currency_id', 'b.currency_name'])
//                ->group('a.currency_id')
//                ->select();
//
//            foreach ($result as &$item) {
//                // 未提现
//                $item['not_carry'] = CurrencyUser::where(['currency_id' => $item['currency_id']])->sum('num');
//            }
//            return ['code' => 0, 'msg' => '获取成功', 'count' => 2, 'data' => $result];
//        }

//        -- 未清算的 U (火箭计划）
//SELECT sum(money) FROM yang_rocket_order WHERE `status` = 0
//
//    -- 已燃烧的MTK (火箭计划)
//SELECT  sum(kmt_num) FROM yang_rocket_order


        $currency_result = [
            'usdt' => [
                'today_num' => 0,//今日充值U
                'total_num' => 0,//累计充值U
                'num' => 0,//钱包账户可用U
                'special_num' => 0,//可用U（特殊账号）
                'can_num' => 0,//可提币的U ( ＞ 50 U)
                'special_can_num' => 0,//可提币的U (特殊账号)
                'not_num' => 0,//未清算的 U (火箭计划）
                'special_not_num' => 0,//未清算的 U (特殊账号）
                'alr_num' => 0,//已提币的 U
                'currency_name' => 'USDT',
            ],
            'mtk' => [
                'today_num' => 0,//今日充值MTK
                'total_num' => 0,//累计充值MTK
                'num' => 0,//钱包账户可用MTK
                'can_num' => 0,//已燃烧的MTK (火箭计划）
                'not_num' => 0,//总流通MTK
                'alr_num' => 0,//已提币的 MTK
                'currency_name' => 'MTK',
            ],
            'jm' => [
                'total_num' => 0,//累计金米
                'surplus_num' => 0,//剩余金米
                'total_reward' => 0,//累计赠与收益
                'surplus_reward' => 0,//剩余赠与收益
            ]
        ];

        // 今日充值
        $today_begin = todayBeginTimestamp();
        $currency_success = Db::name('currency_log')->where(['status' => 3, 'types' => 9, 'add_time' => ['egt', $today_begin]])->select();
        foreach ($currency_success as $item) {
            if (!empty($item['trans'])) {
                $trans = json_decode($item['trans'], true);
                if ($trans['token'] == 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t') {
                    $currency_result['usdt']['today_num'] += $trans['amount'];
                } else if ($trans['token'] == 'TXMdEqpiNrMXR5We8cfh2g73vU7uB6gRxR') {
                    $currency_result['mtk']['today_num'] += $trans['amount'];
                }
            }
        }

        // 累计充值
        $currency_total = Db::name('currency_log')->where(['status' => 3, 'types' => 9])->select();
        foreach ($currency_total as $item) {
            if (!empty($item['trans'])) {
                $trans = json_decode($item['trans'], true);
                if (isset($trans['token'])) {
                    if ($trans['token'] == 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t') {
                        $currency_result['usdt']['total_num'] += $trans['amount'];
                    } else if ($trans['token'] == 'TXMdEqpiNrMXR5We8cfh2g73vU7uB6gRxR') {
                        $currency_result['mtk']['total_num'] += $trans['amount'];
                    }
                }
            }
        }

        // 特殊账号
        $special_member = \app\common\model\RocketConfig::getValue('special_user_id');
        $special_member = explode(',', $special_member);

        // 钱包账户可用
        $currency_user = CurrencyUser::where(['currency_id' => ['in', [5, 93]]])
            ->field('num,currency_id,member_id')
            ->select();
        foreach ($currency_user as $item) {
            if ($item['currency_id'] == 5) {
                // 钱包账户可用U
                $currency_result['usdt']['num'] += $item['num'];

                // 可提币的U ( ＞ 50 U)
                if ($item['num'] >= 50) {
                    $currency_result['usdt']['can_num'] += $item['num'];
                }

                // 可用U（特殊账号）
                if (in_array($item['member_id'], $special_member)) {
                    $currency_result['usdt']['special_num'] += $item['num'];

                    // 可提币的U (特殊账号)
                    if ($item['num'] >= 50) {
                        $currency_result['usdt']['special_can_num'] += $item['num'];
                    }
                }
            } else if ($item['currency_id'] == 93) {
                $currency_result['mtk']['num'] += $item['num'];
            }
        }

        // 已燃烧的MTK (火箭计划）
        // SELECT  sum(kmt_num) FROM yang_rocket_order
        $can_num_m = RocketOrder::sum('kmt_num');
        $currency_result['mtk']['can_num'] = $can_num_m;

        // 未清算的 U (火箭计划）
        // SELECT sum(money) FROM yang_rocket_order WHERE `status` = 0
        $not_num_u = RocketOrder::where(['status' => 0])->select();
        foreach ($not_num_u as $item) {
            $currency_result['usdt']['not_num'] += $item['money'];

            // 未清算的 U (特殊账号）
            if (in_array($item['member_id'], $special_member)) {
                $currency_result['usdt']['special_not_num'] += $item['money'];
            }
        }

        // 总流通MTK [总流通MTK= 钱包账户可用MTK+已燃烧的MTK (火箭计划）]
        $not_num_m = keepPoint($currency_result['mtk']['num'] + $currency_result['mtk']['can_num'], 6);
        $currency_result['mtk']['not_num'] = $not_num_m;

        // 已提币的
        $total_ti_bi = Db::name('tibi')->field('currency_id,sum(num) as num,sum(actual) as actual')->where([
            'transfer_type' => '1',
            'status' => ['in', [-2, -1, 0, 1]],
        ])->group('currency_id')->select();
        foreach ($total_ti_bi as $item) {
            if ($item['currency_id'] == 40) {
                $currency_result['usdt']['alr_num'] = $item['num'];
            } else if ($item['currency_id'] == 93) {
                $currency_result['mtk']['alr_num'] = $item['num'];
            }
        }

        $total_num = Db::name('accountbook')->where(['currency_id' => 98, 'number_type' => 1])->sum('number');
        $currency_result['jm']['total_num'] = sprintf('%.6f', $total_num);

        $surplus_num = Db::name('currency_user')->where(['currency_id' => 98])->sum('num');
        $currency_result['jm']['surplus_num'] = sprintf('%.6f', $surplus_num);

        $total_reward = Db::name('accountbook')->where(['currency_id' => 106, 'number_type' => 1])->sum('number');
        $currency_result['jm']['total_reward'] = sprintf('%.6f', $total_reward);

        $surplus_reward = Db::name('currency_user')->where(['currency_id' => 106])->sum('num');
        $currency_result['jm']['surplus_reward'] = sprintf('%.6f', $surplus_reward);

        return $this->fetch(null, ['currency_result' => $currency_result]);
    }

    /**
     * 每日明细
     * @param Request $request
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function day_finance(Request $request)
    {
        if ($request->isAjax()) {
            $page = $request->param('page', 1, 'intval');
            $limit = $request->param('limit', 10, 'intval');
            $result = Db::name('currency_user_summary')->alias('a')
                ->join('currency b', 'b.currency_id=a.currency_id', 'left')
                ->field(['a.charge', 'a.carry', 'a.static', 'a.dynamic', 'a.create_time', 'b.currency_name'])
                ->group('a.currency_id,a.create_time')
                ->order(['a.create_time' => 'desc'])
                ->page($page, $limit)
                ->select();
            foreach ($result as &$item) {
                $item['create_time'] = date('Y-m-d', $item['create_time']);
            }
            $count = Db::name('currency_user_summary')->group('create_time')->count();
            return ['code' => 0, 'msg' => '获取成功', 'count' => $count, 'data' => $result];
        }
        return $this->fetch();
    }

    /**
     * 通用添加
     * @param Request $request
     * @return array|mixed
     */
    public function add(Request $request)
    {
        if ($request->isPost()) {
            $form = input('form/a');
            $form = $this->addFilter($form);
            $form['add_time'] = time();
            $form['status'] = 1;
            $result = $this->model->save($form);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        }
        $this->addBeforeFetch();
        return $this->fetch();
    }

    /**
     * 通用编辑
     * @param Request $request
     * @return array|mixed|void
     */
    public function edit(Request $request)
    {
        if ($this->request->isPost()) {
            $id = intval(input('id'));

            $form = input('form/a');
            $info = $this->model->where([$this->pid => $id])->find();
            if (empty($info)) return $this->successJson(ERROR1, "该记录不存在", null);

            $form = $this->editFilter($form);
            $result = $this->model->save($form, [$this->pid => $info[$this->pid]]);
            if (false === $result) {
                return $this->successJson(ERROR1, "操作失败:" . $this->model->getError(), null);
            } else {
                return $this->successJson(SUCCESS, "操作成功", ['url' => url('')]);
            }
        }
        $id = intval(input('id'));
        $info = $this->model->where([$this->pid => $id])->find();
        if (empty($info)) return $this->error("该记录不存在");

        $this->editBeforeFetch();
        return $this->fetch(null, compact('info'));
    }

    //统计每日预约
    public function subscribe_list()
    {
        $where = [];
        $start_time = input('start_time');
        $end_time = input('end_time');
        if ($start_time && $end_time) {
            $where['count_time'] = ['BETWEEN', [strtotime($start_time), strtotime($end_time)]];
        }
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        $daochu = input('daochu');
        if ($daochu == 1) {
            $list = \app\common\model\RocketDaySummary::where($where)->order("id desc")->select();

            $list1 = [];
            if (!empty($list)) {
                foreach ($list as $key => $value) {
                    $list1[$key]['currency_id'] = '火米';
                    $list1[$key]['count_time'] = $value['count_time'] ? date('Y-m-d', $value['count_time']) : '';
                    $list1[$key]['num'] = $value['num'];
                }
            }
            $xlsCell = array(
                array('currency_id', '币种'),
                array('count_time', '时间', 20),
                array('num', '预约池充值总数', 20),
            );
            $this->exportExcel("每日预约池充值统计", $xlsCell, $list1);
            die();
        }
        $list = \app\common\model\RocketDaySummary::where($where)->order("id desc")->paginate(null, null, ["query" => $this->request->get()]);
        $page = $list->render();
        $count = $list->total();

        $info = [];
        $total_num = Db::name('accountbook')->where(['type' => 7121])->sum('number');
        $info['total_num'] = sprintf('%.6f', $total_num);//预约池总额
        $surplus_num = Db::name('rocket_welfare')->order('id desc')->value('num');
        $info['surplus_num'] = sprintf('%.6f', $surplus_num);//剩余预约池余额

        return $this->fetch(null, compact('list', 'page', 'count', 'info'));
    }

    /**
     * 火箭游戏统计
     * @param Request $request
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function rocket_statistics(Request $request)
    {
        $first_result = [
            'yesterday_num' => 0,//昨日预购补贴
            'total_num' => 0,//累计预购补贴
            'yesterday_share' => 0,//昨日分享奖
            'total_share' => 0,//累计分享奖
            'yesterday_manage' => 0,//昨日管理奖
            'total_manage' => 0,//累计管理奖
        ];
        $second_result = [
            'yesterday_service' => 0,//昨日服务津贴
            'total_service' => 0,//累计服务津贴
            'num' => 0,//闯关未结算
            'can_num' => 0,//闯关未结算（特殊账号）
        ];

        // 昨日
        $today_start = strtotime(date("Y-m-d"));
        $today_end = $today_start + 86399;
        $yesterday_num = \app\common\model\RocketRewardLog::where(['type' => 1, 'add_time' => ['between', [$today_start, $today_end]]])->sum('reward');
        $first_result['yesterday_num'] = $yesterday_num ? sprintf('%.4f', $yesterday_num): 0;
        $yesterday_share = \app\common\model\RocketRewardLog::where(['type' => 6, 'add_time' => ['between', [$today_start, $today_end]]])->sum('reward');
        $first_result['yesterday_share'] = $yesterday_share ? sprintf('%.4f', $yesterday_share): 0;
        $yesterday_manage = \app\common\model\RocketRewardLog::where(['type' => 7, 'add_time' => ['between', [$today_start, $today_end]]])->sum('reward');
        $first_result['yesterday_manage'] = $yesterday_manage ? sprintf('%.4f', $yesterday_manage): 0;
        $yesterday_service = \app\common\model\RocketRewardLog::where(['type' => 5, 'add_time' => ['between', [$today_start, $today_end]]])->sum('reward');
        $second_result['yesterday_service'] = $yesterday_service ? sprintf('%.4f', $yesterday_service): 0;

        // 累计
        $total_num = \app\common\model\RocketRewardLog::where(['type' => 1])->sum('reward');
        $first_result['total_num'] = $total_num ? sprintf('%.4f', $total_num): 0;
        $total_share = \app\common\model\RocketRewardLog::where(['type' => 6])->sum('reward');
        $first_result['total_share'] = $total_share ? sprintf('%.4f', $total_share): 0;
        $total_manage = \app\common\model\RocketRewardLog::where(['type' => 7])->sum('reward');
        $first_result['total_manage'] = $total_manage ? sprintf('%.4f', $total_manage): 0;
        $total_service = \app\common\model\RocketRewardLog::where(['type' => 5])->sum('reward');
        $second_result['total_service'] = $total_service ? sprintf('%.4f', $total_service): 0;

        $num = \app\common\model\RocketOrder::where(['status' => 0])->sum('money');
        $second_result['num'] = $num ? sprintf('%.4f', $num): 0;
        $special_user_id = \app\common\model\RocketConfig::getValue('special_user_id');
        $special_user_id = explode(',', $special_user_id);
        $can_num = \app\common\model\RocketOrder::where(['status' => 0])->whereIn('member_id', $special_user_id)->sum('money');
        $second_result['can_num'] = $can_num ? sprintf('%.4f', $can_num): 0;

        $currency_id = \app\common\model\RocketConfig::getValue('reward_currency_id');
        $currency_name = \app\common\model\Currency::where('currency_id', $currency_id)->value('currency_name');

        return $this->fetch(null, ['first_result' => $first_result, 'second_result' => $second_result, 'currency_name' => $currency_name]);
    }

    /**
     * 火箭游戏个人统计
     * @param Request $request
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function personal_statistics(Request $request)
    {
        $phone = input('phone');
        $where = [];
        if ($phone) {
            $where['member_id'] = Db::name('member')->where(['phone' => $phone])->value('member_id');
        }
        $member_id = input('member_id');
        if ($member_id) {
            $where['member_id'] = $member_id;
        }
        $is_pid = input('is_pid');
        if ($is_pid) {
            $memberids = Db::name('member_bind')->where(['member_id' => $member_id])->column('child_id');
            $member_id = array_merge([$member_id], $memberids);
            $where['member_id'] = ['in', $member_id];
        }
        set_time_limit(0);
        ignore_user_abort(1);
        $daochu = $request->param('daochu');
        if ($daochu == 1) {
            $list = \app\common\model\RocketMember::where($where)->field('id,member_id')->order("id desc")->select();

            $list1 = [];
            if (!empty($list)) {
                foreach ($list as $key => $value) {
                    $list1[$key]['member_id'] = $value['member_id'];

                    $user_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => ['in', [123, 5, 600, 7, 125]], 'number_type' => 1, 'currency_id' => 5])->sum('number');
                    $list1[$key]['user_num'] = $user_num ?: 0;

                    $system_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 13, 'number_type' => 1, 'currency_id' => 5])->sum('number');
                    $list1[$key]['system_num'] = $system_num ?: 0;

                    $withdraw_num1 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 6, 'number_type' => 2, 'currency_id' => 5])->sum('number');
                    $withdraw_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'number_type' => 2, 'currency_id' => 5])->sum('number');
                    $withdraw_num = sprintf('%.6f', $withdraw_num1 + $withdraw_num2);
                    $list1[$key]['withdraw_num'] = $withdraw_num ?: 0;

                    $give_withdraw_num1 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 6, 'number_type' => 2, 'currency_id' => 106])->sum('number');
                    $give_withdraw_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'number_type' => 2, 'currency_id' => 106])->sum('number');
                    $give_withdraw_num3 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 122, 'number_type' => 2, 'currency_id' => 106])->sum('number');
                    $give_withdraw_num = sprintf('%.6f', $give_withdraw_num1 + $give_withdraw_num2 + $give_withdraw_num3);
                    $list1[$key]['give_withdraw_num'] = $give_withdraw_num ?: 0;

                    $otc_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 9, 'currency_id' => 5])->sum('number');
                    $otc_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 11, 'currency_id' => 5, 'number_type' => 1])->sum('number');
                    $list1[$key]['otc_num'] = sprintf('%.6f', $otc_num + $otc_num2);

                    $otc_pay_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => ['in', [16, 33]], 'currency_id' => 5])->sum('number');
                    $otc_pay_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 11, 'currency_id' => 5, 'number_type' => 2])->sum('number');
                    $list1[$key]['otc_pay_num'] = sprintf('%.6f', $otc_pay_num + $otc_pay_num2);

                    $integral_num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 98])->sum('num');
                    $list1[$key]['integral_num'] = $integral_num ?: 0;

                    $stay_num = \app\common\model\RocketOrder::where(['member_id' => $value['member_id'], 'status' => 0])->sum('money');
                    $list1[$key]['stay_num'] = $stay_num ?: 0;

                    $num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 5])->sum('num');
                    $list1[$key]['num'] = $num ?: 0;

                    $give_num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 106])->sum('num');
                    $list1[$key]['give_num'] = $give_num ?: 0;

                    $subscribe_num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 102])->sum('num');
                    $list1[$key]['subscribe_num'] = $subscribe_num ?: 0;

                    $switch_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'number_type' => 1, 'currency_id' => 102])->sum('number');
                    $list1[$key]['switch_num'] = $switch_num ?: 0;

                    $transfer_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'number_type' => 2, 'currency_id' => 102])->sum('number');
                    $list1[$key]['transfer_num'] = $transfer_num ?: 0;

                    $subsidy_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7109, 'number_type' => 1, 'currency_id' => 5])->sum('number');
                    //$subsidy_num = \app\common\model\RocketRewardLog::where(['member_id' => $value['member_id'], 'type' => 1])->sum('reward');
                    $subsidy_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7109, 'number_type' => 1, 'currency_id' => 106])->sum('number');
                    $list1[$key]['subsidy_num'] = sprintf('%.6f', $subsidy_num + $subsidy_num2);

                    $share_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7102, 'number_type' => 1, 'currency_id' => 5])->sum('number');
                    $share_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7102, 'number_type' => 1, 'currency_id' => 106])->sum('number');
                    $list1[$key]['share_num'] = sprintf('%.6f', $share_num + $share_num2);

                    $manage_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7101, 'number_type' => 1, 'currency_id' => 5])->sum('number');
                    $manage_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7101, 'number_type' => 1, 'currency_id' => 106])->sum('number');
                    $list1[$key]['manage_num'] = sprintf('%.6f', $manage_num + $manage_num2);

                    $service_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7116, 'number_type' => 1, 'currency_id' => 5])->sum('number');
                    $service_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7116, 'number_type' => 1, 'currency_id' => 106])->sum('number');
                    $list1[$key]['service_num'] = sprintf('%.6f', $service_num + $service_num2);

                    $bonus_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7104, 'number_type' => 1, 'currency_id' => 5])->sum('number');
                    $bonus_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7104, 'number_type' => 1, 'currency_id' => 106])->sum('number');
                    $list1[$key]['bonus_num'] = sprintf('%.6f', $bonus_num + $bonus_num2);
                }
            }
            $xlsCell = array(
                array('member_id', '用户ID'),
                array('user_num', '累计用户充值', 20),
                array('system_num', '累计系统充值', 20),
                array('withdraw_num', '累计已提现', 20),
                array('give_withdraw_num', '累计赠与收益提现', 20),
                array('otc_num', 'OTC收入', 20),
                array('otc_pay_num', 'OTC支出', 20),
                array('integral_num', '累计金米', 20),
                array('stay_num', '闯关待结算', 20),
                array('num', '账户可用剩余', 20),
                array('give_num', '赠与收益剩余', 20),
                array('subscribe_num', '预约池剩余', 20),
                array('switch_num', '累计他人转入', 20),
                array('transfer_num', '累计转出他人', 20),
                array('subsidy_num', '累计预购补贴', 20),
                array('share_num', '累计分享奖', 20),
                array('manage_num', '累计管理奖', 20),
                array('service_num', '累计服务津贴', 20),
                array('bonus_num', '累计加权分红', 20),
            );
            $this->exportExcel("火种云仓个人账户（火米）相关财务统计", $xlsCell, $list1);
            die();
        }
        $list = \app\common\model\RocketMember::where($where)->field('id,member_id')->order("id desc")->paginate(null, null, ["query" => $this->request->get()]);
        $page = $list->render();
        $count = $list->total();

        if ($list) {
            foreach ($list as &$value) {
                $user_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => ['in', [123, 5, 600, 7, 125]], 'number_type' => 1, 'currency_id' => 5])->sum('number');
                $value['user_num'] = $user_num ?: 0;

                $system_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 13, 'number_type' => 1, 'currency_id' => 5])->sum('number');
                $value['system_num'] = $system_num ?: 0;

                $withdraw_num1 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 6, 'number_type' => 2, 'currency_id' => 5])->sum('number');
                $withdraw_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'number_type' => 2, 'currency_id' => 5])->sum('number');
                $withdraw_num3 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 122, 'number_type' => 2, 'currency_id' => 5])->sum('number');
                $withdraw_num = sprintf('%.6f', $withdraw_num1 + $withdraw_num2 + $withdraw_num3);
                $value['withdraw_num'] = $withdraw_num ?: 0;

                $integral_num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 98])->sum('num');
                $value['integral_num'] = $integral_num ?: 0;

                $num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 5])->sum('num');
                $value['num'] = $num ?: 0;

                $subscribe_num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 102])->sum('num');
                $value['subscribe_num'] = $subscribe_num ?: 0;

                $subsidy_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7109, 'number_type' => 1, 'currency_id' => 5])->sum('number');
                //$subsidy_num = \app\common\model\RocketRewardLog::where(['member_id' => $value['member_id'], 'type' => 1])->sum('reward');
                $subsidy_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7109, 'number_type' => 1, 'currency_id' => 106])->sum('number');
                $value['subsidy_num'] = sprintf('%.6f', $subsidy_num + $subsidy_num2);

                $share_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7102, 'number_type' => 1, 'currency_id' => 5])->sum('number');
                $value['share_num'] = $share_num ?: 0;

                $manage_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => ['in',[7101,7117,7122,7123]], 'number_type' => 1, 'currency_id' => 5])->sum('number');
                $manage_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => ['in',[7101,7117,7122,7123]], 'number_type' => 1, 'currency_id' => 106])->sum('number');
                $value['manage_num'] = sprintf('%.6f', $manage_num + $manage_num2);

                $service_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7116, 'number_type' => 1, 'currency_id' => 5])->sum('number');
                $service_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7116, 'number_type' => 1, 'currency_id' => 106])->sum('number');
                $value['service_num'] = sprintf('%.6f', $service_num + $service_num2);

                $stay_num = \app\common\model\RocketOrder::where(['member_id' => $value['member_id'], 'status' => 0])->sum('money');
                $value['stay_num'] = $stay_num ?: 0;

                $bonus_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7104, 'number_type' => 1, 'currency_id' => 5])->sum('number');
                $bonus_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7104, 'number_type' => 1, 'currency_id' => 106])->sum('number');
                $value['bonus_num'] = sprintf('%.6f', $bonus_num + $bonus_num2);

                $switch_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'number_type' => 1, 'currency_id' => 102])->sum('number');
                $value['switch_num'] = $switch_num ?: 0;

                $transfer_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'number_type' => 2, 'currency_id' => 102])->sum('number');
                $value['transfer_num'] = $transfer_num ?: 0;

                $give_withdraw_num1 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 6, 'number_type' => 2, 'currency_id' => 106])->sum('number');
                $give_withdraw_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'number_type' => 2, 'currency_id' => 106])->sum('number');
                $give_withdraw_num3 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 122, 'number_type' => 2, 'currency_id' => 106])->sum('number');
                $give_withdraw_num = sprintf('%.6f', $give_withdraw_num1 + $give_withdraw_num2 + $give_withdraw_num3);
                $value['give_withdraw_num'] = $give_withdraw_num ?: 0;

                $give_num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 106])->sum('num');
                $value['give_num'] = $give_num ?: 0;

                $otc_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 9, 'currency_id' => 5])->sum('number');
                $otc_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 11, 'currency_id' => 5, 'number_type' => 1])->sum('number');
                $value['otc_num'] = sprintf('%.6f', $otc_num + $otc_num2);

                $otc_pay_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => ['in', [16, 33]], 'currency_id' => 5])->sum('number');
                $otc_pay_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 11, 'currency_id' => 5, 'number_type' => 2])->sum('number');
                $value['otc_pay_num'] = sprintf('%.6f', $otc_pay_num + $otc_pay_num2);

                $change_withdraw_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'currency_id' => 106, 'number_type' => 1])->sum('number');
                $change_withdraw_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => ['in', [125,126]], 'currency_id' => 106])->sum('number');
                $value['change_withdraw_num'] = sprintf('%.6f', $change_withdraw_num + $change_withdraw_num2);
            }
        }

        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 自提区个人统计
     * @param Request $request
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function pickedup_statistics(Request $request)
    {
        $phone = input('phone');
        $where = [];
        if ($phone) {
            $where['member_id'] = Db::name('member')->where(['phone' => $phone])->value('member_id');
        }
        $member_id = input('member_id');
        if ($member_id) {
            $where['member_id'] = $member_id;
        }
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $list = \app\common\model\RocketMember::where($where)->field('id,member_id')->order("id desc")->paginate(null, null, ["query" => $this->request->get()]);
        $page = $list->render();
        $count = $list->total();
        if ($list) {
            foreach ($list as &$value) {
                $total_num = \app\common\model\MemberBind::where(['member_id' => $value['member_id']])->count('child_id');
                $value['total_num'] = $total_num;

                $quota_num = \app\common\model\MemberBind::alias('a')->join('accountbook b', 'a.child_id=b.member_id')->where(['a.member_id' => $value['member_id'], 'b.type' => 123])->sum('b.number');
                $value['quota_num'] = $quota_num;

                $personal_quota = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 123])->sum('number');
                $value['personal_quota'] = $personal_quota;

                $today_start = strtotime(date('Y-m-d', time()));
                $today_end = $today_start + 86399;
                $today_num = \app\common\model\MemberBind::alias('a')->join('member b', 'a.child_id=b.member_id')->where(['a.member_id' => $value['member_id'], 'b.reg_time' => ['between', [$today_start, $today_end]]])->count('b.member_id');
                $value['today_num'] = $today_num;

                $today_quota = \app\common\model\MemberBind::alias('a')->join('accountbook b', 'a.child_id=b.member_id')->where(['a.member_id' => $value['member_id'], 'b.type' => 123, 'b.add_time' => ['between', [$today_start, $today_end]]])->sum('b.number');
                $value['today_quota'] = $today_quota;

                $today_personal_quota = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 123, 'add_time' => ['between', [$today_start, $today_end]]])->sum('number');
                $value['today_personal_quota'] = $today_personal_quota;

                $total_tibi = \app\common\model\MemberBind::alias('a')->join('tibi b', 'a.child_id=b.to_member_id')->where(['a.member_id' => $value['member_id'], 'b.status' => 1, 'b.currency_id' => 40])->sum('b.num');
                $total_withdrawal = \app\common\model\MemberBind::alias('a')->join('wechat_transfer b', 'a.child_id=b.member_id')->where(['a.member_id' => $value['member_id'], 'b.check_status' => 1])->sum('b.amount');
                $value['total_withdrawal'] = sprintf('%.6f', $total_withdrawal + $total_tibi);

                $today_withdrawal = \app\common\model\MemberBind::alias('a')->join('accountbook b', 'a.child_id=b.member_id')->where(['a.member_id' => $value['member_id'], 'b.type' => ['in', [6, 122]], 'add_time' => ['between', [$today_start, $today_end]]])->sum('b.number');
                $value['today_withdrawal'] = $today_withdrawal;
            }
        }

        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 用户预约（火米）统计
     * @param Request $request
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function user_subscribe(){
        $member_id = input('member_id');
        $list = [];
        if ($member_id) {
            $today_start = strtotime(date('Y-m-d'));
            $today_end = $today_start + 86399;
            $month_start = strtotime(date('Y-m-01', strtotime(date("Y-m-d"))));
            $month_end = strtotime(date('Y-m-d', strtotime("$month_start +1 month -1 day")));
            $last_month_start = strtotime(date('Y-m-d', strtotime(date('Y-m-01') . ' -1 month')));
            $last_month_end = strtotime(date('Y-m-d', strtotime(date('Y-m-01') . ' -1 day')));
            $lately = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 2])->order('id desc')->value('add_time');
            $lately_start = strtotime(date('Y-m-d', $lately));
            $lately_end = $lately_start + 86399;

            //本人预购
            $today_num = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 1, 'add_time' => ['between', [$today_start, $today_end]]])->sum('num');
            $month_num = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 1, 'add_time' => ['between', [$month_start, $month_end]]])->sum('num');
            $last_month_num = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 1, 'add_time' => ['between', [$last_month_start, $last_month_end]]])->sum('num');
            $total_num = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 1])->sum('num');

            //本人抱彩
            $lately_num = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 2, 'add_time' => ['between', [$lately_start, $lately_end]]])->sum('num');
            $total_lately_num = Db::name('rocket_subscribe_transfer')->where(['member_id' => $member_id, 'type' => 2])->sum('num');

            $time_arr = [
                'today_start' => $today_start,
                'today_end' => $today_end,
                'month_start' => $month_start,
                'month_end' => $month_end,
                'last_month_start' => $last_month_start,
                'last_month_end' => $last_month_end,
            ];
            //大区信息
            $max_info = \app\common\model\RocketSubscribeTransfer::getCommunityInfo($member_id, 1, $time_arr);
            //小区信息
            $min_info = \app\common\model\RocketSubscribeTransfer::getCommunityInfo($member_id, 2, $time_arr);

            $arr = [
                'member_id' => $member_id,
                'today_num' => $today_num,
                'month_num' => $month_num,
                'last_month_num' => $last_month_num,
                'total_num' => $total_num,
                'lately_num' => $lately_num,
                'total_lately_num' => $total_lately_num,
                'max_info' => $max_info,
                'min_info' => $min_info
            ];
            $list[] = $arr;
        }
        $date = date('Y-m-d');
        return $this->fetch(null, compact('list', 'date'));
    }

    /**
     * 方舟游戏个人统计
     * @param Request $request
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function ark_statistics(Request $request)
    {
        $phone = input('phone');
        $where = [];
        if ($phone) {
            $where['member_id'] = Db::name('member')->where(['phone' => $phone])->value('member_id');
        }
        $member_id = input('member_id');
        if ($member_id) {
            $where['member_id'] = $member_id;
        }
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        $daochu = $request->param('daochu');
        if ($daochu == 1) {
            $list = \app\common\model\ArkMember::where($where)->field('id,member_id')->order("id desc")->select();

            $list1 = [];
            if (!empty($list)) {
                foreach ($list as $key => $value) {
                    $list1[$key]['member_id'] = $value['member_id'];

                    $user_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => ['in', [123, 5, 600, 7]], 'number_type' => 1, 'currency_id' => 103])->sum('number');
                    $list1[$key]['user_num'] = $user_num ?: 0;

                    $system_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 13, 'number_type' => 1, 'currency_id' => 103])->sum('number');
                    $list1[$key]['system_num'] = $system_num ?: 0;

                    $withdraw_num1 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 6, 'number_type' => 2, 'currency_id' => 103])->sum('number');
                    $withdraw_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'number_type' => 2, 'currency_id' => 103])->sum('number');
                    $withdraw_num = sprintf('%.6f', $withdraw_num1 + $withdraw_num2);
                    $list1[$key]['withdraw_num'] = $withdraw_num ?: 0;

                    $integral_num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 104])->sum('num');
                    $list1[$key]['integral_num'] = $integral_num ?: 0;

                    $stay_num = \app\common\model\ArkOrder::where(['member_id' => $value['member_id'], 'status' => 0])->sum('money');
                    $list1[$key]['stay_num'] = $stay_num ?: 0;

                    $num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 103])->sum('num');
                    $list1[$key]['num'] = $num ?: 0;

                    $subscribe_num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 105])->sum('num');
                    $list1[$key]['subscribe_num'] = $subscribe_num ?: 0;

                    $switch_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'number_type' => 1, 'currency_id' => 105])->sum('number');
                    $list1[$key]['switch_num'] = $switch_num ?: 0;

                    $transfer_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'number_type' => 2, 'currency_id' => 105])->sum('number');
                    $list1[$key]['transfer_num'] = $transfer_num ?: 0;

                    $subsidy_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7109, 'number_type' => 1, 'currency_id' => 103])->sum('number');
                    //$subsidy_num = \app\common\model\ArkRewardLog::where(['member_id' => $value['member_id'], 'type' => 1])->sum('reward');
                    $list1[$key]['subsidy_num'] = $subsidy_num ?: 0;

                    $share_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7102, 'number_type' => 1, 'currency_id' => 103])->sum('number');
                    $list1[$key]['share_num'] = $share_num ?: 0;

                    $manage_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7101, 'number_type' => 1, 'currency_id' => 103])->sum('number');
                    $list1[$key]['manage_num'] = $manage_num ?: 0;

                    $service_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7116, 'number_type' => 1, 'currency_id' => 103])->sum('number');
                    $list1[$key]['service_num'] = $service_num ?: 0;

                    $pledge_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7124, 'number_type' => 1, 'currency_id' => 103])->sum('number');
                    $list1[$key]['pledge_num'] = $pledge_num ?: 0;

                    $bonus_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7104, 'number_type' => 1, 'currency_id' => 103])->sum('number');
                    $list1[$key]['bonus_num'] = $bonus_num ?: 0;
                }
            }
            $xlsCell = array(
                array('member_id', '用户ID'),
                array('user_num', '累计用户充值', 20),
                array('system_num', '累计系统充值', 20),
                array('withdraw_num', '累计已提现', 20),
                array('integral_num', '累计金米', 20),
                array('stay_num', '闯关待结算', 20),
                array('num', '账户可用剩余', 20),
                array('subscribe_num', '预约池剩余', 20),
                array('switch_num', '累计他人转入', 20),
                array('transfer_num', '累计转出他人', 20),
                array('subsidy_num', '累计预购补贴', 20),
                array('share_num', '累计分享奖', 20),
                array('manage_num', '累计管理奖', 20),
                array('service_num', '累计服务津贴', 20),
                array('pledge_num', '累计质押赠送', 20),
                array('pledge_num', '累计加权分红', 20),
            );
            $this->exportExcel("火种云仓个人账户（Y令牌）相关财务统计", $xlsCell, $list1);
            die();
        }
        $list = \app\common\model\ArkMember::where($where)->field('id,member_id')->order("id desc")->paginate(null, null, ["query" => $this->request->get()]);
        $page = $list->render();
        $count = $list->total();

        if ($list) {
            foreach ($list as &$value) {
                $user_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => ['in', [123, 5, 600, 7]], 'number_type' => 1, 'currency_id' => 103])->sum('number');
                $value['user_num'] = $user_num ?: 0;

                $system_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 13, 'number_type' => 1, 'currency_id' => 103])->sum('number');
                $value['system_num'] = $system_num ?: 0;

                $withdraw_num1 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 6, 'number_type' => 2, 'currency_id' => 103])->sum('number');
                $withdraw_num2 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'number_type' => 2, 'currency_id' => 103])->sum('number');
                $withdraw_num3 = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 122, 'number_type' => 2, 'currency_id' => 103])->sum('number');
                $withdraw_num = sprintf('%.6f', $withdraw_num1 + $withdraw_num2 + $withdraw_num3);
                $value['withdraw_num'] = $withdraw_num ?: 0;

                $integral_num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 104])->sum('num');
                $value['integral_num'] = $integral_num ?: 0;

                $num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 103])->sum('num');
                $value['num'] = $num ? sprintf('%.6f', $num) : 0;

                $subscribe_num = \app\common\model\CurrencyUser::where(['member_id' => $value['member_id'], 'currency_id' => 105])->sum('num');
                $value['subscribe_num'] = $subscribe_num ?: 0;

                $subsidy_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7109, 'number_type' => 1, 'currency_id' => 103])->sum('number');
                //$subsidy_num = \app\common\model\ArkRewardLog::where(['member_id' => $value['member_id'], 'type' => 1])->sum('reward');
                $value['subsidy_num'] = $subsidy_num ?: 0;

                $share_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7102, 'number_type' => 1, 'currency_id' => 103])->sum('number');
                $value['share_num'] = $share_num ?: 0;

                $manage_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7101, 'number_type' => 1, 'currency_id' => 103])->sum('number');
                $value['manage_num'] = $manage_num ?: 0;

                $service_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7116, 'number_type' => 1, 'currency_id' => 103])->sum('number');
                $value['service_num'] = $service_num ?: 0;

                $stay_num = \app\common\model\ArkOrder::where(['member_id' => $value['member_id'], 'status' => 0])->sum('money');
                $value['stay_num'] = $stay_num ?: 0;

                $bonus_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7104, 'number_type' => 1, 'currency_id' => 103])->sum('number');
                $value['bonus_num'] = $bonus_num ?: 0;

                $switch_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'number_type' => 1, 'currency_id' => 105])->sum('number');
                $value['switch_num'] = $switch_num ?: 0;

                $transfer_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 600, 'number_type' => 2, 'currency_id' => 105])->sum('number');
                $value['transfer_num'] = $transfer_num ?: 0;

                $pledge_num = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'type' => 7124, 'number_type' => 1, 'currency_id' => 103])->sum('number');
                $value['pledge_num'] = $pledge_num ?: 0;
            }
        }

        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    //赠与收益-提现配置表
    public function withdraw_config() {
        if ($this->request->isPost()) {
            $saveData = $this->request->param('checkboxid/a');
            $saveData = implode(',', $saveData);
            $currency_id = 106;
            $isUpdate = WithdrawConfig::where(['currency_id' => $currency_id])->update(['data' => $saveData]);
            if ($isUpdate === false) {
                return $this->successJson(ERROR1, "更新失败", null);
            }
            return $this->successJson(SUCCESS, "更新成功", null);
        }

        $currency_id = 106;
        $info = WithdrawConfig::where(['currency_id' => $currency_id])->find();
        $info['data'] = explode(',', $info['data']);

        return $this->fetch(null, compact('info'));
    }

    //平台MTK统计
    public function mtk_list() {
        $list = [];
        $mtk_num = CurrencyUser::where(['currency_id' => 93])->sum('num');
        $list['mtk_num'] = $mtk_num;
        $m_num = CurrencyUser::where(['currency_id' => 99])->sum('num');
        $list['m_num'] = $m_num;
        $num = \app\common\model\YunMiningPay::where(['status' => 0])->sum('mtk_num');
        $list['num'] = $num;
        $produce_num = \app\common\model\YunMiningPay::where(['status' => 0])->sum('income_num');
        $list['produce_num'] = $produce_num;
        $extract_num = \app\common\model\YunMiningIncome::where(['type' => 1])->sum('num');
        $list['extract_num'] = $extract_num;

        return $this->fetch(null, compact('list'));
    }
}
