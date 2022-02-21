<?php

namespace app\backend\controller;

use app\common\model\CurrencyUserTransfer;
use app\common\model\InsideOrder;
use app\common\model\InsideTrade;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Request;
use app\common\model\Tibi;
use app\common\model\Currency;
use app\common\model\CurrencyTakeCoin;
use app\common\model\CurrencyLog;
use app\common\model\WalletEverydayTake;
use app\common\model\WalletEverydayRecharge;

/**
 * 钱包管理
 * Class Wallet
 * @package app\admin\controller
 */
class Wallet extends Admin
{
    protected $public_action = ['qrcode_img','query_wallet'];

    protected $takeService;

    protected $is_mobile_support = true; // mobile/test/index.html

    public function __construct(Request $request = null)
    {
        parent::__construct($request);

        // 币种信息
        $currencyAll = Db::name("Currency")->where(['status' => 1])->select();
        $this->assign('currencyAll', $currencyAll);
    }

    /**
     * 充币纪录列表
     * @param Request $request
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function recharge(Request $request)
    {
        $where = [];
        $cuid = input('currency_id');

        $phone = input('phone');
        $email = input('email');
        $member_id = input('member_id');
        $status = input('status');
        $url = input('url');
        $start = input('start');
        $end = input('end');

        if (!empty($cuid)) {
            $where['tb.currency_id'] = $cuid;
        }
        $where['tb.status'] = 3;
        $where['tb.b_type'] = 0;
        $where['tb.transfer_type'] = "1";//区块链充币类型
        if (!empty($phone)) {
            $where['m.phone'] = array('like', '%' . $phone . '%');
        }
        if (!empty($email)) {
            $where['m.email'] = array('like', '%' . $email . '%');
        }
        if (!empty($member_id)) {
            $where['m.member_id'] = $member_id;
        }
        if (!empty($status)) {
            $where['tb.status'] = $status;
        }
        if (!empty($url)) {
            $where['tb.from_url'] = array('like', '%' . $url . '%');
        }
        if (!empty($start)) {
            $start = strtotime($start);
            if (empty($end)) {
                $where['tb.add_time'] = ['egt', $start];
            } else {
                $end = strtotime($end);
                $where['tb.add_time'] = ['between', [$start, $end]];
            }
        }
        $field = "tb.*,m.email,m.member_id,m.name,m.phone,c.currency_name,c.currency_type,m.remarks";
        $list = Db::name("Tibi")->alias("tb")->field($field)->where($where)->where("tb.to_member_id", "exp", "is not null")
            ->join(config("database.prefix") . "member m", "m.member_id=tb.to_member_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "c.currency_id=tb.currency_id", "LEFT")
            ->order("add_time desc")->paginate(20, null, ['query' => input()]);
        $show = $list->render();
        $count = $list->total();

        //读取积分类型表
        $curr = Db::name("Currency")->where(['status' => 1])->select();
        //$curr = array_column($curr,null,'currency_id');

        //XRP昨日充值总数量
        $today = todayBeginTimestamp();
        $chong_sum = Tibi::where([
            'status' => 3,
            'add_time' => ['between', [$today - 86400, $today - 1]]
        ])->field('currency_id,sum(actual) as actual')->group('currency_id')->select();
        $this->assign("chong_sum", $chong_sum);

        $today_chong_sum = Tibi::where([
            'status' => 3,
            'add_time' => ['egt', $today]
        ])->field('currency_id,sum(actual) as actual')->group('currency_id')->select();
        return $this->fetch('wallet/recharge', ['list' => $list, 'page' => $show, 'count' => $count, 'currencys' => $curr, 'today_chong_sum' => $today_chong_sum]);
    }


    /**
     * 提币纪录
     * @param Request $request
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function cash(Request $request)
    {
        $where = [];
        $currencyId = $request->get('currency_id');
        $checkStatus = $request->get('wt_check_status');
        $start = $request->get('start');
        $end = $request->get('end');
        $isExcel = $request->get('is_excel');

        if ($currencyId) {
            $where['wt_currency_id'] = $currencyId;
        }

        if ($checkStatus) {
            $where['wt_check_status'] = $checkStatus;
        }

        if ($start and $end) {
            $start = strtotime($start);
            $end = strtotime($end) + 86400;
            $where['wt_start_time'] = ['between', [$start, $end]];
        }

        if ($isExcel == 1) {
            // 导出Excel
            $list = WalletTransfer::with(['currency', 'feeCurrency'])
                ->where($where)
                ->where('wt_to_user_id is null')
                ->order('wt_id', 'desc')
                ->select();

            $data = [];
            $statusArray = [
                1 => "等待中",
                2 => "转账成功",
                3 => "不通过",
            ];

            $checkStatusArray = [
                1 => "待审核",
                2 => "通过",
                3 => "不通过",
                4 => "待节点确认",
            ];
            foreach ($list as $key => $value) {
                array_push($data, [
                    'id' => $value['wt_id'],
                    'wt_from_user_id' => $value['wt_from_user_id'],
                    'currency_name' => $value['currency']['currency_name'],
                    'wt_money' => floattostr($value['wt_money']),
                    'wt_fees' => floatval($value['wt_fees']) . " " . $value['feeCurrency']['currency_name'],
                    'wt_no' => $value['wt_no'],
                    'wt_from_address' => $value['wt_from_address'],
                    'wt_to_address' => $value['wt_to_address'],
                    'wt_total_money' => floattostr($value['wt_total_money']),
                    'wt_remark' => $value['wt_remark'],
                    'wt_status' => $statusArray[$value['wt_status']],
                    'wt_check_status' => $checkStatusArray[$value['wt_check_status']],
                    'wt_start_time' => date("Y-m-d H:i:s", $value['wt_start_time']),
                    'wt_success_time' => date("Y-m-d H:i:s", $value['wt_success_time']),
                ]);
            }

            return exportExcel([
                "ID",
                "转账人ID",
                "币种",
                "转账金额",
                "手续费 币种",
                "转账编号",
                "提币地址",
                "接收人地址",
                "总数量",
                "备注",
                "状态",
                "审核状态",
                "转账开始时间",
                "转账成功时间",
            ], $data, "提币纪录");
        }

        $list = WalletTransfer::with(['currency', 'feeCurrency'])
            ->where('wt_to_user_id is null')
            ->where($where)
            ->order('wt_id', 'desc')
            ->paginate($this->pageRows, null, ["query" => $request->get()]);
        $page = $list->render();
        $count = WalletTransfer::where('wt_to_user_id is null')->count();
        $currencys = WalletCurrency::all();
        return $this->fetch('wallet/cash', ['list' => $list, 'page' => $page, 'count' => $count, 'currencys' => $currencys]);
    }

    /**
     * 提币纪录2
     * @param Request $request
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function historyTake(Request $request)
    {
        $list = TakeCoin::with(['currency', 'transfer' => function ($query) {
            return $query->with('fromUser');
        }])->order('wtc_id', 'desc')->paginate($this->pageRows, null, ['query' => $request->get()]);
        $page = $list->render();
        $count = TakeCoin::count('wtc_id');
        return $this->fetch('Wallet/historyTake', compact('list', 'page', 'count'));
    }

    /**
     * 二维码
     * @param Request $request
     * @return mixed|string
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function qrcode(Request $request)
    {
        $id = input("id");
        $field = "t.*,c.currency_name,c.currency_type";
        $find = Db::name("tibi")->alias("t")->field($field)
            ->join("currency c", "c.currency_id=t.currency_id", "left")
            ->where(['t.id' => $id, 't.status' => -1])
            ->find();
        if (is_null($find['admin_id1']) or is_null($find['admin_id2'])) {
            return "请先完成1审和2审";
        }

        if (empty($find)) {
            echo "不是审核的数据，请刷新页面";
        }

        $this->assign('data', $find);
        return $this->fetch();
    }

    /**
     * 生成二维码
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * Create by: Red
     * Date: 2019/12/18 14:46
     */
    public function qrcode_img()
    {
        $to_url = input("to_url");
        $currency_type = input("currency_type");
        $money = input("money");
        if (!empty($to_url)) {
            if (in_array($currency_type, ['btc', 'usdt'])) {
                $qrcode = "bitcoin:" . $to_url . "?amount=" . $money;
            } else {
                $qrcode = $to_url;
            }
        }
        require_once EXTEND_PATH . 'phpqrcode' . DS . 'phpqrcode.php';
        \QRcode::png($qrcode, false, 'Q', '6', '2');
        header("Content-type: image/png");
        die();
    }

    /**
     * 平台转账纪录
     * @param Request $request
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function transfer(Request $request)
    {
        $list = WalletTransfer::with(['currency', 'feeCurrency'])->where('wt_type', '1')->order('wt_id', 'desc')->paginate($this->pageRows, null, ["query" => $request->get()]);
        $page = $list->render();
        $count = WalletTransfer::where('wt_type', '1')->count();

        return $this->fetch('wallet/transfer', ['list' => $list, 'page' => $page, 'count' => $count]);
    }

    /**
     * 提币审核
     * @param Request $request
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function waitCash(Request $request)
    {
        $mit = input('mit', '');
        if ($mit == 'mit') {
            $message1 = input('message');
            $id = input('id');
            $condition['id'] = $id;
            $data['message1'] = $message1;
            $data['admin_id1'] = session("admin_id");

            //判断是否有数据
            $find_data = Db::name('tibi')->where($condition)->find();
            if(in_array($find_data['currency_id'],[103,104])) {
                $data['message2'] = $message1;
                $data['admin_id2'] = session("admin_id");
            }
            //判断是否已在审核2提交
            if (session("admin_id") > 1) {
                if ($find_data['admin_id2'] == session("admin_id")) {
                    $this->error('已在审核2提交，不可重复提交');
                }
            }
            //判断是否已经有数据
            if ($find_data['message1']) {
                if (session("admin_id") == 1) {
                    $rs = Db::name('tibi')->where($condition)->update($data);
                } else {
                    $this->error('没有权限更改数据');
                }
            } else {
                $rs = Db::name('tibi')->where($condition)->update($data);
            }
            if ($rs != false) {
                $this->success('提交成功');
            } else {
                $this->error('提交失败');
            }
        }

        if ($mit == 'mit2') {
            $message2 = input('message');
            $id = input('id');
            $condition['id'] = $id;
            $data2['message2'] = $message2;
            $data2['admin_id2'] = session("admin_id");
            //判断是否有数据
            $find_data = Db::name('tibi')->where($condition)->find();
            if(in_array($find_data['currency_id'],[103,104])) {
                $data2['message1'] = $message2;
                $data2['admin_id1'] = session("admin_id");
            }
            //判断是否已在审核2提交
            if (session("admin_id") > 1) {
                if ($find_data['admin_id1'] == session("admin_id")) {
                    $this->error('已在审核1提交，不可重复提交');
                }
            }
            //判断是否已经有数据
            if ($find_data['message2']) {
                if (session("admin_id") == 1) {
                    $rs = Db::name('tibi')->where($condition)->update($data2);
                } else {
                    $this->error('没有权限更改数据');
                }
            } else {
                $rs = Db::name('tibi')->where($condition)->update($data2);
            }
            if ($rs != false) {
                return $this->success('提交成功');
            } else {
                return $this->error('提交失败');
            }
        }
        $where = [];
        $member_id = input('user_id');
        if ($member_id) {
            $where['from_member_id'] = $member_id;
        }
        $to_address = input('to_address');
        if ($to_address) {
            $where['to_url'] = $to_address;
        }
        $currency_id = input('currency_id');
        if ($currency_id) {
            $where['currency_id'] = $currency_id;
        }
        $list = Tibi::with(['fromUser', 'currency', 'feeCurrency'])->where([
            'transfer_type' => 1,
            'status' => -1,
        ])->where($where)->order('id', 'desc')->paginate($this->pageRows, null, ["query" => $request->get()]);
        $page = $list->render();
        $count = Tibi::where([
            'transfer_type' => 1,
            'status' => -1,
        ])->where($where)->count();
        $currencys = Currency::where('is_line', 1)->select();
        return $this->fetch(null, ['list' => $list, 'page' => $page, 'count' => $count,'currencys'=>$currencys]);
    }

    /**
     * 提币提交
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function passTake(Request $request)
    {
        if (!$this->verifyC($request->post('captcha'))) {
            return successJson(ERROR2, "亲, 验证码输错了哦!", null);
        }

        $r['code'] = ERROR1;
        $r['message'] = "参数错误!";
        $r['data'] = null;

        $id = $request->post('id');
        if (!is_numeric($id)) {
            return successJson($r);
        }

        $transfer = Tibi::where(['id' => $id])->find();
        if (empty($transfer)) {
            return successJson($r);
        }

        if ($transfer['status'] != -1) {
            $r['message'] = "该数据不可审核, 请刷新后重试!";
            return successJson($r);
        }
        if (empty($transfer['message1']) || empty($transfer['message2'])) {
            $r['message'] = "一审或者二审没有审核";
            return successJson($r);
        }
        // 币种信息
        $currency = Currency::where(['currency_id' => $transfer['currency_id']])->find();
        if (empty($currency)) {
            return successJson($r);
        }

        $result = Tibi::applyTransfer($id, session("admin_id"));
        return successJson($result);

        /*switch ($currency['currency_bt_id']) {
            case BlockchainType::BTC_TOKEN_TYPE_ID:
                // USDT
                return $this->takeService->USTDTakeCoin($currency, $transfer);
            case BlockchainType::BTC_TYPE_ID:
                // BTC
                return $this->takeService->BTCTakeCoin($currency, $transfer);
            case BlockchainType::ETH_AND_TOKEN_TYPE_ID:
                // ETH
                return $this->takeService->ETHTakeCoin($currency, $transfer);
            default:
                return successJson("该币种暂不可提币!", null, ERROR4);
        }*/
    }

    /**
     * 提交一审和二审
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * Create by: Red
     * Date: 2019/10/25 17:29
     */
    function submit_instance()
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $wt_id = input("post.wt_id");
        $type = input("post.type");
        $instance = input("post.instance");
        if (!empty($wt_id) && !empty($type) && !empty($instance)) {
            $trafer = Db::name("wallet_transfer")->where(['wt_check_status' => 1, 'wt_id' => $wt_id])->find();
            if (!empty($trafer)) {
                $update_data = null;
                $admin_id = session('admin_id');
                //判断一审和二审不能为同一个管理员
                if ($type == "one") {
                    if ($admin_id != 1 && $trafer['wt_second_admin_id'] == $admin_id) {
                        $r['message'] = "你不能同时操作一审二审";
                        return json($r);
                    }
                    $update_data['wt_first_instance'] = $instance;
                    $update_data['wt_first_admin_id'] = $admin_id;
                } else {
                    if ($admin_id != 1 && $trafer['wt_first_admin_id'] == $admin_id) {
                        $r['message'] = "你不能同时操作一审二审";
                        return json($r);
                    }
                    $update_data['wt_second_instance'] = $instance;
                    $update_data['wt_second_admin_id'] = $admin_id;
                }
                $update = Db::name("wallet_transfer")->where(['wt_id' => $wt_id])->update($update_data);
                if ($update) {
                    $r['code'] = SUCCESS;
                    $str = $type == "one" ? "一审" : "二审";
                    $r['message'] = $str . "提交成功";
                } else {
                    $r['message'] = "没有任何修改";
                }

            } else {
                $r['message'] = "该数据不是待审核数据,请刷新后重试";
            }
        }
        return json($r);
    }

    /**
     * 拒绝提币
     * @param Request $request
     * @return \think\response\Json
     */
    public function refuseTake(Request $request)
    {
        if (!$this->verifyC($request->post('captcha'))) {
            return successJson(ERROR2, "亲, 验证码输错了哦!", null);
        }

        $id = $request->post('id');
        if (!is_numeric($id)) {
            return successJson(ERROR1, "参数错误!", null);
        }
        try {
            Db::startTrans();
            $result = Tibi::rebut($id, session("admin_id"));
            Db::commit();
        } catch (Exception $exception) {
            Db::rollback();
            return successJson(ERROR1, $exception->getMessage(), null);
        }

        return successJson($result);
    }

    /**
     * 验证码验证
     * @param string $c
     * @return boolean
     */
    private function verifyC($c)
    {
        if (!Open::verifyCaptcha($c)) {
            return false;
        }
        return true;
    }

    /**
     * 提币待确认列表
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function confirmCashList(Request $request)
    {
        $where['ctc.status'] = 1;
        $list = CurrencyTakeCoin::alias("ctc")
            ->field("ctc.id,t.from_member_id,m.ename,c.currency_id,c.currency_name,ctc.money,ctc.txhash,ctc.from_address,ctc.to_address,ctc.starttime")
            ->where($where)
            ->join("currency c", "c.currency_id=ctc.currency_id", "LEFT")
            ->join("tibi t", "t.id=ctc.tibi_id", "LEFT")
            ->join("member m", "t.from_member_id=m.member_id", "LEFT")
            ->paginate($this->pageRows, null, ['query' => $request->get()]);

        $show = $list->render();
        $currency = Currency::where(['status' => 1])->field('currency_id,currency_mark')->select();
        $count = $list->total();
        $this->assign("currency", $currency);
        $this->assign("list", $list);
        $this->assign("page", $show);
        $this->assign("count", $count);
        return $this->fetch();
    }

    /**
     * 提币成功
     * @param Request $request
     * @return mixed|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function successTake(Request $request)
    {
        if ($request->isPost()) {
//            if (!$this->verifyC($request->post('captcha'))) {
//                return successJson(ERROR2, "亲, 验证码输错了哦!", null);
//            }
            if (!is_numeric($request->post('wtc_id'))) {
                return successJson(ERROR1, "参数错误, 请重试!", null);
            }
            $takeCoin = CurrencyTakeCoin::where('id', $request->post('wtc_id'))->find();
            if (empty($takeCoin)) {
                return successJson(ERROR1, "数据异常", null);
            }
            $HASHFlag = CurrencyTakeCoin::where('txhash', $request->post('hash'))->value('id');
            if (!empty($HASHFlag)) {
                return successJson(ERROR1, "哈希已存在", null);
            }
            $hash = empty($takeCoin['txhash']) ? $request->post('hash') : $takeCoin['txhash'];

            $result = CurrencyTakeCoin::updateTakeCoinStatus($request->post('wtc_id'), 2, $hash, time(), $request->post('fee'));
            return successJson($result);
        }
        $data = CurrencyTakeCoin::where('id', $request->get('id'))->find();
        $currency = Currency::where(['currency_id' => $data['currency_id']])->find();
        // 查询币服务器推送过来的哈希
        if (empty($data['txhash'])) {
            $tibi = Tibi::where(['id' => $data['tibi_id']])->find();
            $logWhere = [];
            $data['to_address'] = strtolower($data['to_address']);
            if (in_array($currency['trade_currency_id'], [4, 5])) {
                $logWhere['ato'] = ['like', "%{$data['to_address']}_{$tibi['tag']}%"];
            } else {
                $logWhere['ato'] = ['like', "%{$data['to_address']}%"];
            }

            $logWhere['amount'] = (double)$tibi['actual'];
            $log = CurrencyLog::where($logWhere)->find();
            if (!is_null($log)) {
                $data['txhash'] = $log['tx'];
            }
        }
        $this->assign('bt_id', $currency['trade_currency_id']);
        return $this->fetch('wallet/successTake', ['data' => $data]);
    }

    /**
     * 提币失败
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function failTake(Request $request)
    {
//        if (!$this->verifyC($request->post('captcha'))) {
//            return successJson(ERROR2, "亲, 验证码输错了哦!", null);
//        }
        if (!is_numeric($request->post('wtc_id'))) {
            return successJson(ERROR1, "参数错误, 请重试!", null);
        }

        $takeCoin = CurrencyTakeCoin::where(['id' => $request->post('wtc_id')])->find();
        if (empty($takeCoin)) {
            return successJson(ERROR1, "数据异常", null);
        }

        $result = CurrencyTakeCoin::updateTakeCoinStatus($request->post('wtc_id'), 3, $takeCoin['txhash'], time());

        return successJson($result);
    }

    /**
     * 重新审核
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\exception\PDOException
     */
    public function restartTake(Request $request)
    {
//        if (!$this->verifyC($request->post('captcha'))) {
//            return successJson(ERROR2, "亲, 验证码输错了哦!");
//        }
        if (!is_numeric($request->post('wtc_id'))) {
            return successJson(ERROR1, "参数错误, 请重试!");
        }


        $result = CurrencyTakeCoin::updateTakeCoinStatus($request->post('wtc_id'), 4);
        return successJson($result);
    }

    /**
     * 每日充币统计
     * @param Request $request
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function dayRechargeCount(Request $request)
    {
        $where = [];
        $currencyId = $request->get('currency_id');
        $start = $request->get('start');
        $end = $request->get('end');
        $isExcel = $request->get('is_excel');

        if ($currencyId) {
            $where['wer_currency_id'] = $currencyId;
        }

        if ($start) {
            $end = $end ? $end : $start;
            $where['wer_time'] = ['between', [$start, date("Y-m-d", strtotime($end))]];
        }

        if ($isExcel == 1) {
            // 导出Excel
            $list = WalletEverydayRecharge::with('currency')->where($where)->order('wer_id', 'desc')->select();
            $data = [];
            foreach ($list as $key => $value) {
                array_push($data, [
                    'wer_id' => $value['wer_id'],
                    'currency_name' => $value['currency']['currency_name'],
                    'wer_time' => $value['wer_time'],
                    'wer_total' => floattostr($value['wer_total']),
                ]);
            }

            return exportExcel([
                "ID",
                "币种名称",
                "时间",
                "总数",
            ], $data, "每日充币统计");
        }

        $list = WalletEverydayRecharge::with('currency')->where($where)->order('wer_id', 'desc')->paginate($this->pageRows, null, $request->get());
        $page = $list->render();
        $count = $list->total();
        $currencys = Currency::where(['status' => 1])->select();
        return $this->fetch('wallet/rechargeCount', ['list' => $list, 'page' => $page, 'count' => $count, 'currencys' => $currencys]);
    }

    /**
     * 每日提币统计
     * @param Request $request
     * @return mixed|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function dayCashCount(Request $request)
    {
        $where = [];
        $currencyId = $request->get('currency_id');
        $start = $request->get('start');
        $end = $request->get('end');
        $isExcel = $request->get('is_excel');

        if ($currencyId) {
            $where['wet_wc_id'] = $currencyId;
        }

        if ($start) {
            $end = $end ? $end : $start;
            $where['wet_time'] = ['between', [$start, date("Y-m-d", strtotime($end))]];
        }

        if ($isExcel == 1) {
            // 导出Excel
            $list = WalletEverydayTake::with('currency')->order('wet_id', 'desc')->where($where)->select();
            $data = [];
            foreach ($list as $key => $value) {
                array_push($data, [
                    'wet_id' => $value['wet_id'],
                    'currency_name' => $value['currency']['currency_name'],
                    'wet_time' => $value['wet_time'],
                    'wet_total' => floattostr($value['wet_total']),
                ]);
            }

            return exportExcel([
                "ID",
                "币种名称",
                "时间",
                "总数",
            ], $data, "每日提币统计");
        }

        $list = WalletEverydayTake::with('currency')->order('wet_id', 'desc')->where($where)
            ->paginate($this->pageRows, null, $request->get());
        $page = $list->render();
        $count = $list->total();
        $currencys = Currency::all();
        return $this->fetch('wallet/cashCount', compact('list', 'page', 'count', 'currencys'));
    }

    /**
     * 币种列表
     * @param Request $request
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function currency(Request $request)
    {
        $list = Currency::order('currency_sort', 'asc')->paginate($this->pageRows, null, ["query" => $request->get()]);
        $page = $list->render();
        $count = Currency::count();
        return $this->fetch(null, ['list' => $list, 'page' => $page, 'count' => $count]);
    }

    /**
     * 修改币种开关接口
     * @param Request $request
     * @return \think\response\Json
     */
    public function updateCurrencySwitch(Request $request)
    {
        $value = $request->post('value');
        $type = $request->post('type');
        $res = Currency::where('currency_id', $request->post('id'))->update([
            $type => $value
        ]);
        if ($res === false) {
            return json(['code' => ERROR2, 'message' => '系统错误!']);
        }

        return json(['code' => SUCCESS, 'message' => '修改成功!']);
    }

    /**
     * 修改币种页面
     * @param Request $request
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function editCurrency(Request $request)
    {
        $id = $request->get('id');
        $currency = Currency::where('currency_id', $id)->find();
        $types = BlockchainType::all();
        $currencys = Currency::all();
        return $this->fetch('wallet/editCurrency', ['currency' => $currency, 'types' => $types, 'currencys' => $currencys]);
    }

    /**
     * 修改币种
     * @param Request $request
     * @return \think\response\Json
     */
    public function updateCurrency(Request $request)
    {
        $data = $request->post();
        if ($data['currency_price_type'] != 3) {
            unset($data['currency_fee_currency_id']);
        }
        $res = Currency::where('')->update($data);
        if ($res === false) {
            return json(['code' => ERROR2, 'message' => '修改失败!请重试']);
        }

        return json(['code' => SUCCESS, 'message' => '修改成功!']);
    }

    /**
     * 待汇总列表
     * @param Request $request
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function summaryList(Request $request)
    {
        $user_id = $request->get('user_id');
        $user_nickname = $request->get('user_nickname');
        $currency_id = $request->get('currency_id');

        $where = [];
        $userWhere = [];
        if ($user_id) {
            $where['us_user_id'] = $user_id;
        }

        if ($currency_id) {
            $where['us_currency_id'] = $currency_id;
        }

        if ($user_nickname) {
            $userWhere['user_nickname'] = ['like', "%{$user_nickname}%"];
        }

        $list = WalletUserSummary::hasWhere('user', $userWhere)
            ->with('currency')
            ->where('us_num', '>', 0)
            ->where('us_currency_id', 'neq', WalletCurrency::BTC_ID)// 列表中去掉BTC
            ->where($where)
            ->order('us_id', 'desc')
            ->paginate($this->pageRows, null, ["page" => $request->get()]);
        $page = $list->render();
        $count = WalletUserSummary::where('us_num', '>', 0)->count();

        $currencys = WalletCurrency::where('currency_id', 'neq', WalletCurrency::BTC_ID)->select();

        // 查询BTC信息
        $wcR = WalletCurrency::where('currency_id', 'eq', WalletCurrency::BTC_ID)->find();
        $btc = new Btc();
        $server['rpc_user'] = $wcR['currency_recharge_rpcuser'];
        $server['rpc_pwd'] = $wcR['currency_recharge_rpcpwd'];
        $server['rpc_url'] = $wcR['currency_recharge_url'];
        $server['port_number'] = $wcR['currency_recharge_prot'];
        $btcMoney = $btc->get_qianbao_balance($server);

        return $this->fetch(null, [
            'list' => $list,
            'page' => $page,
            'count' => $count,
            'currencys' => $currencys,
            'btcMoney' => number_format($btcMoney, 6, '.', ''),
            'btcSummaryAddress' => $wcR['currency_summary_address']
        ]);
    }


    /**
     * 导出待汇总纪录Excel格式
     * @param Request $request
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function exportSummaryExcel(Request $request)
    {
        $list = WalletUserSummary::with(['user', 'currency'])->where('us_num', '>', 0)->order('us_id', 'desc')->select();
        $data = [];
        foreach ($list as $key => &$value) {
            array_push($data, [
                'us_id' => $value->us_id,
                'user_email' => $value->user->user_email,
                'user_id' => $value->user->user_id,
                'user_nickname' => $value->user->user_nickname,
                'us_num' => $value->us_num,
                'currency_name' => $value->currency->currency_name,
                'wa_address' => $value->user->getAddress($value->currency->currency_bt_id)->wa_address
            ]);
        }
        exportExcel([
            "ID",
            "邮箱",
            "用户ID",
            "昵称",
            "数量",
            "币种",
            "地址",
        ], $data, '待汇总纪录');
    }

    /**
     * 汇总页面
     * @param Request $request
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function summary(Request $request)
    {
        $us_id = $request->get('us_id');

        if (!empty($us_id)) {
            $summary = WalletUserSummary::with('currency')->where('us_id', $us_id)->find();
            if (!empty($summary) and !empty($summary->currency)) {
                $currency = $summary->currency;
                $addr = WalletAddress::get_user_address($summary->us_user_id, $summary->currency->currency_bt_id);
                if (!empty($addr)) {
                    $data['us_id'] = $us_id;
                    $data['us_wc_id'] = $summary->us_currency_id;
                    $data['us_user_id'] = $summary->us_user_id;
                    if ($summary->currency->currency_bt_id == BlockchainType::BTC_TOKEN_TYPE_ID) {
                        // USDT
                        $btc = new Btc();
                        $server['rpc_user'] = $summary->currency->currency_recharge_rpcuser;
                        $server['rpc_pwd'] = $summary->currency->currency_recharge_rpcpwd;
                        $server['rpc_url'] = $summary->currency->currency_recharge_url;
                        $server['port_number'] = $summary->currency->currency_recharge_prot;

                        // usdt帐号的余额
                        $usdt = $btc->omni_getbalance($addr->wa_address, $server);
                        // 待汇总的btc数量
                        $usdtBtc = $btc->get_balance_by_address($addr->wa_address, $server);
                        $data['usdtbtc'] = keepPoint($usdtBtc, 8);
                        //扣手续地址的btc的余额
                        $btcbalance = $btc->get_balance_by_address($summary->currency->currency_summary_fee_address, $server);
                        $data['usdt'] = $usdt;
                        $data['btc'] = keepPoint($btcbalance, 8);
                        $this->assign("data", $data);
                        return $this->fetch('USDTSummary');
                    } elseif ($summary->currency->currency_bt_id == BlockchainType::ETH_AND_TOKEN_TYPE_ID) {
                        // ETH 和 ETH代币
                        $e = new Eth($summary->currency->currency_recharge_url, $summary->currency->currency_recharge_prot);
                        if (!empty($summary->currency->currency_contract_address)) {
                            // ETH代币
                            // 获取ETH链上的数量
                            $token = $e->getBalance($addr['wa_address']);
                            if (!empty($token) and isset($token[$currency->currency_name])) {
                                $data['money'] = $token[$currency->currency_name];
                                $data['money1'] = substr(sprintf("%.7f", $data['money']), 0, -1);
                                $feesResult = $e->token_getTxUseFee($addr['wa_address'], $currency['currency_summary_address'], $currency['currency_contract_address'], $data['money']);
                                if (!empty($feesResult) && $feesResult['code'] == SUCCESS) {
                                    $data['fees'] = keepPoint($feesResult['result']['fee'], 8);//手续费
                                }
                            } else {
                                $data['money'] = 0;
                                $data['money1'] = 0;
                                $data['fees'] = 0; // 手续费
                            }
                            $data['eth'] = $token['eth'];
                            $this->assign('data', $data);
                            return $this->fetch('wallet/tokenSummary');
                        } else {
                            // ETH
                            $ETH = $e->getEthBalance($addr['wa_address']);
                            if ($ETH) {
                                $data['money'] = keepPoint($ETH, 8);
                                $data['money1'] = keepPoint($data['money'], 8);
                            }

                            $fee = $e->eth_fees();
                            if (!empty($fee) && $fee['code'] == SUCCESS) {
                                $data['fees'] = keepPoint($fee['result']['fees'], 8);
                            } else {
                                $data['fees'] = 0;
                            }

                            $this->assign("data", $data);
                        }
                    }
                }
            }
        }

        return $this->fetch();
    }

    /**
     * 待确认汇总列表
     * @param Request $request
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function waitSummaryList(Request $request)
    {
        $list = WalletSummary::with(['user', 'currency'])->where('ws_status', 1)->order('ws_id', 'desc')->paginate($this->pageRows, null, $request->get());
        $page = $list->render();
        $count = WalletSummary::where('ws_status', 1)->count('ws_id');
//        echo $count;die;
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 汇总成功
     * @param Request $request
     * @return mixed|string|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function successSummary(Request $request)
    {

        if ($request->isPost()) {
            $wsid = $request->post("wsid");
            $status = 2;
            $txhash = $request->post("txhash");
            $fees = $request->post("fees");

            if (empty($txhash) or empty($fees)) {
                return successJson("请填写必填表单!", null, ERROR2);
            }

            return successJson(WalletSummary::updateSummaryStatus($wsid, $status, $txhash, $fees));
        }
        $id = $request->get('id');
        if (empty($id)) {
            return "参数错误!";
        }
        $summary = WalletSummary::where('ws_id', $id)->find();

        return $this->fetch(null, compact('summary'));
    }

    /**
     * 修改汇总纪录状态
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function updateSummaryStatus(Request $request)
    {
        $id = $request->post('id');
        $status = $request->post('status');
        if (empty($id) or empty($status)) {
            return successJson("参数错误, 请重试!", null, ERROR1);
        }
        return successJson(WalletSummary::updateSummaryStatus($id, $status));
    }

    /**
     * 取消汇总
     * @param Request $request
     * @return \think\response\Json
     */
    public function cancelSummary(Request $request)
    {
        $usId = $request->post('us_id');
        if (empty($usId)) {
            return successJson("参数错误", null, ERROR1);
        }
        $res = UsersSummary::where('us_id', $usId)->update([
            "us_num" => 0
        ]);
        if (!$res) {
            return successJson("操作失败!", null, ERROR1);
        }

        return successJson('操作成功!');
    }


    /**
     * USTD汇总提交
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function USDTSummary(Request $request)
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];

        $money = $request->post("summary");//汇总数量
        $uid = $request->post("wuc_user_id"); // 用户ID
        $wuc_wc_id = $request->post("wuc_wc_id"); // 币种ID

        if (!empty($wuc_wc_id) && $money > 0 && !empty($uid)) {
            // 查询币种数据
            $wc = WalletCurrency::where('currency_id', $wuc_wc_id)->find();
            // 查询用户地址
            $wa = WalletAddress::get_user_address($uid, $wc['currency_bt_id']);
            if (!empty($wa)) {
                //汇总，用户余额转到汇总钱包地址
                //先添加一条汇总数据
                $wsResult = WalletSummary::addSummary($wa['wa_address'], $wc['currency_summary_address'], $money, $wuc_wc_id, 1);
                if (SUCCESS == $wsResult['code']) {
                    $btc = new Btc();
                    $currency['rpc_user'] = $wc['currency_recharge_rpcuser'];
                    $currency['rpc_pwd'] = $wc['currency_recharge_rpcpwd'];
                    $currency['rpc_url'] = $wc['currency_recharge_url'];
                    $currency['port_number'] = $wc['currency_recharge_prot'];
                    //汇总USDT时，手续费从指定的地址上扣除
                    $sendResult = $btc->omni_funded_send($wa['wa_address'], $wc['currency_recharge_unite_pwd'], $wc['currency_summary_address'], $money, $wc['currency_summary_fee_address'], $currency);
                    if ($sendResult['code'] == SUCCESS) {
                        $result = WalletSummary::where('ws_id', $wsResult['result'])->update([
                            'ws_txhash' => $sendResult['result']
                        ]);
                        if ($result) {
                            $r['code'] = SUCCESS;
                            $r['message'] = "已进入待确认汇总状态，请查看再确认";
                        } else {
                            $r['code'] = SUCCESS;
                            $r['message'] = "已进入待确认汇总状态，保存交易编号异常";
                        }
                    } else {
                        $r['code'] = SUCCESS;
                        $r['message'] = "已进入待确认汇总状态，但没有返回交易编号,原因是：" . $sendResult['message'];
                    }
                } else {
                    return json($wsResult);
                }
            }
        }
        return json($r);
    }

    /**
     * ETH汇总提交
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function ETHSummary(Request $request)
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['data'] = [];

        $money = $request->post('summary');
        $uid = input("wuc_user_id");
        $wuc_wc_id = input("wuc_wc_id");
        if (!empty($wuc_wc_id) && $money > 0 && !empty($uid)) {
            // 查询币种数据
            $wc = WalletCurrency::where('currency_id', $wuc_wc_id)->find();
            // 查询用户地址
            $wa = WalletAddress::get_user_address($uid, $wc['currency_bt_id']);
            if ($wc and $wa) {
                $waR = $wa;
                $e = new Eth($wc['currency_recharge_url'], $wc['currency_recharge_prot']);
                //以太坊代币有合约地址，合约地址不为空则为代币，否则为以太坊ETH币
                if (!empty($wc['currency_contract_address'])) {
                    // ETH代币
                    $totalResult = $e->getBalance($waR['wa_address']);
                    if (!empty($totalResult) and isset($totalResult[$wc['currency_name']])) {
                        // 用户钱包币数量要比汇总数量多
                        $_r = bccomp($totalResult[$wc['currency_name']], $money, 4);
                        if ($_r >= 0) {
                            if (isset($totalResult['eth'])) {
                                // 获取总钱包的ETH余额
                                $Ethtotal = $totalResult['eth'];//总钱包的ETH余额
                                // 获取转账需要的ETH手续费
                                $feesResult = $e->token_getTxUseFee($waR['wa_address'], $wc['currency_summary_address'], $wc['currency_contract_address'], $money);
                                if (!empty($feesResult) && $feesResult['code'] == SUCCESS) {
                                    $fees = $feesResult['result']['fee']; // 手续费
                                    $gas = $feesResult['result']['gas']['number'];
                                    $gasPrice = $feesResult['result']['gasPrice']['number'];

                                    if ($Ethtotal >= $fees) {
                                        //汇总，用户余额转到汇总钱包地址
                                        //先添加一条汇总数据
                                        $wsResult = WalletSummary::addSummary($waR['wa_address'], $wc['currency_summary_address'], $money, $wuc_wc_id, 1);

                                        if (SUCCESS == $wsResult['code']) {
                                            $send = $e->token_sendTransaction($waR['wa_address'], $wc['currency_summary_address'], $wc['currency_contract_address'], $money, $gasPrice, $gas, $wc['currency_recharge_unite_pwd']);
                                            if (!empty($send) and SUCCESS == $send['code']) {
                                                $result = WalletSummary::where('ws_id', $wsResult['result'])->update([
                                                    'ws_txhash' => $send['result']['result']
                                                ]);
                                                if ($result) {
                                                    $r['code'] = SUCCESS;
                                                    $r['message'] = "已进入待确认汇总状态，请查看再确认";
                                                } else {
                                                    $r['code'] = SUCCESS;
                                                    $r['message'] = "已进入待确认汇总状态，保存交易编号异常";
                                                }
                                            } else {
                                                $r['code'] = SUCCESS;
                                                $r['message'] = "已进入待确认汇总状态，请查看再确认--" . $send['message'];
                                            }
                                        } else {
                                            return json($wsResult);
                                        }
                                    } else {
                                        $r['message'] = "待waitforsummarylist地址的ETH不足";
                                    }
                                } else {
                                    $r['message'] = "获取提币的gasPrice失败";
                                }
                            } else {
                                $r['message'] = "获取待汇总的ETH失败";
                            }
                        } else {
                            $r['message'] = "汇总数量超额了";
                        }
                    } else {
                        $r['message'] = "获取余额失败";
                    }
                } else {
                    // ETH汇总
                    $fees = $e->eth_fees();
                    if (SUCCESS == $fees['code']) {
                        $finallMoney = $money - number_format($fees['result']['fees'], 18);//全总数量减掉手续费后
                        if ($finallMoney < 0) {
                            return json(['code' => ERROR2, "message" => "汇总数量不足以抵扣手续费"]);
                        }

                        //汇总，用户余额转到汇总钱包地址
                        //先添加一条汇总数据
                        $wsResult = WalletSummary::addSummary($waR['wa_address'], $wc['currency_summary_address'], $money, $wuc_wc_id, 1);
                        if (SUCCESS == $wsResult['code']) {
                            $send = $e->personal_sendTransaction($waR['wa_address'], $wc['currency_summary_address'], $finallMoney, $wc['currency_recharge_unite_pwd'], $fees["result"]["gasPrice"], $fees["result"]["gas"]);
                            if (!empty($send) and SUCCESS == $send['code']) {
                                Log::write('send--- ' . json_encode($send) . "\n");
                                Log::write('wsResult--- ' . json_encode($wsResult) . "\n");
                                if (!isset($send['result']) or !isset($send['result']['result'])) {
                                    $r['code'] = SUCCESS;
                                    $r['message'] = "交易编号未返回, 请查看后确认!";
                                    return json($r);
                                }
                                $result = WalletSummary::where('ws_id', $wsResult['result'])->update([
                                    'ws_txhash' => $send['result']['result']
                                ]);
                                if ($result) {
                                    $r['code'] = SUCCESS;
                                    $r['message'] = "已进入待确认汇总状态，请查看再确认";
                                } else {
                                    $r['code'] = SUCCESS;
                                    $r['message'] = "已进入待确认汇总状态，保存交易编号异常";
                                }
                            } else {
                                $r['code'] = SUCCESS;
                                $r['message'] = "已进入待确认汇总状态，请查看再确认--" . $send['message'];
                            }
                        } else {
                            return json($wsResult);
                        }
                    }
                }
            }

        }

        return json($r);
    }

    /**
     * 汇总纪录
     * @param Request $request
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function historySummary(Request $request)
    {
        $where = [];
        $userWhere = [];
        $userId = $request->get('user_id');
        $nickname = $request->get('nickname');
        $currencyId = $request->get('currency_id');
        $status = $request->get('status');
        $startTime = $request->get('start');
        $endTime = $request->get('end');
        $isExcel = $request->get('is_excel');

        if ($userId) {
            $where['ws_from_user_id'] = $userId;
        }

        if ($nickname) {
            $userWhere['user_nickname'] = $nickname;
        }

        if ($currencyId) {
            $where['ws_wc_id'] = $currencyId;
        }

        if ($status) {
            $where['ws_status'] = $status;
        }

        if ($startTime) {
            $endTime = $endTime ? $endTime : $startTime;
            $where['ws_starttime'] = ['between', [strtotime($startTime), strtotime($endTime) + 86400]];
        }

        $condition = "user.user_id = ws.ws_from_user_id";

        if ($nickname) {
            $condition .= " and user.user_nickname = \"{$nickname}\"";
        }

        if ($isExcel == 1) {
            // 导出Excel
            $list = WalletSummary::alias('ws')
                ->join('wallet_users user', $condition, 'left')
                ->with(['currency'])
                ->where($where)
                ->order('ws_id', 'desc')
                ->select();

            $data = [];
            $statusArray = [
                1 => "待确认",
                2 => "成功",
                3 => "失败",
            ];

            foreach ($list as $key => $value) {
                array_push($data, [
                    'id' => $value['ws_id'],
                    'currency_name' => $value['currency']['currency_name'],
                    'wt_money' => floattostr($value['ws_money']),
                    'user_id' => $value['user']['user_id'],
                    'user_nickname' => $value['user']['user_nickname'],
                    'ws_txhash' => $value['ws_txhash'],
                    'ws_to_address' => $value['ws_to_address'],
                    'ws_from_address' => $value['ws_from_address'],
                    'ws_fees' => floattostr($value['ws_fees']),
                    'ws_status' => $statusArray[$value['ws_status']],
                    'ws_starttime' => date("Y-m-d H:i:s", $value['ws_starttime']),
                    'ws_endtime' => date("Y-m-d H:i:s", $value['ws_endtime']),
                ]);
            }

            return exportExcel([
                "ID",
                "币种",
                "数量",
                "用户ID",
                "用户昵称",
                "交易编号",
                "汇总总地址",
                "转账地址",
                "手续费",
                "状态",
                "开始时间",
                "结束时间",
            ], $data, "汇总纪录");
        }


        $list = WalletSummary::alias('ws')
            ->join('wallet_users user', $condition, 'left')
            ->with(['currency'])
            ->where($where)
            ->order('ws_id', 'desc')
            ->paginate($this->pageRows, null, ['query' => $request->get()]);
        $page = $list->render();
        $count = WalletSummary::count();
        $currencys = WalletCurrency::all();
        return $this->fetch(null, compact('list', 'page', 'count', 'currencys'));
    }

    /**
     * BTC汇总页面
     * @param Request $request
     * @return mixed
     */
    public function BTCSummary(Request $request)
    {
        $amount = $request->get('amount');

        return $this->fetch('wallet/BTCSummary', ['amount' => $amount]);
    }

    /**
     * 处理BTC汇总
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function doBTCSummary(Request $request)
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];

        $amount = $request->post('summary');
        if (!empty($amount) and $amount > 0) {
            $wcR = WalletCurrency::where('currency_id', 'eq', WalletCurrency::BTC_ID)->find();
            $btc = new Btc();
            $server['rpc_user'] = $wcR['currency_recharge_rpcuser'];
            $server['rpc_pwd'] = $wcR['currency_recharge_rpcpwd'];
            $server['rpc_url'] = $wcR['currency_recharge_url'];
            $server['port_number'] = $wcR['currency_recharge_prot'];
            $btcmoney = $btc->get_qianbao_balance($server);
            if ($amount < $btcmoney) {
                $wsResult = WalletSummary::addSummary(null, $wcR['currency_summary_address'], $amount, $wcR['currency_id'], 1);
                if (SUCCESS == $wsResult['code']) {
                    //使用找零地址方式汇总，找零地址为充币服务上的
                    $txid = $btc->btc_transfer($wcR['currency_recharge_unite_pwd'], $wcR['currency_summary_address'], $wcR['currency_change_address'], $amount, $server);
                    if (!empty($txid)) {
                        $result = WalletSummary::where('ws_id', $wsResult['result'])->update([
                            'ws_txhash' => $txid
                        ]);
                        if ($result) {
                            $r['code'] = SUCCESS;
                            $r['message'] = "汇总已提交，请查询是否到汇总钱包";
                        } else {
                            $r['code'] = SUCCESS;
                            $r['message'] = "汇总已提交，但没有返回交易编号";
                        }
                    } else {
                        $r['message'] = "汇总失败,没有返回交易编号";
                    }
                } else {
                    $r['message'] = "添加汇总记录失败--" . $wsResult['message'];
                }
            } else {
                $r['message'] = "汇总数量超限了";
            }
        }

        return json($r);
    }

    /**
     * 提积分记录
     * @return mixed|string
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function tibi_index()
    {
        $daochu = input("daochu");
        $cuid = input('cuid');
        $phone = input('phone');
        $email = input('email');
        $member_id = input('member_id');
        $status = input('status');
        $url = input('url');
        $starttime = input("starttime");
        $endtime = input("endtime");
        $temp['starttime'] = $starttime;
        $temp['endtime'] = $endtime;
        if (!empty($starttime)) {
            if (empty($endtime)) {
                $endtime = date("Y-m-d", time());
            }
            $where['tb.add_time'] = array('between', array(strtotime($starttime), strtotime($endtime) + 86400));
        }
        if (!empty($cuid)) {
            $where['tb.currency_id'] = input("cuid");
            $this->assign("id", input("cuid"));
        }
        $where['tb.b_type'] = 0;
        $where['tb.transfer_type'] = "1";//区块链类型
        if (!empty($phone)) {
            $where['m.phone'] = array('like', '%' . $phone . '%');
        }
        if (!empty($email)) {
            $where['m.email'] = array('like', '%' . $email . '%');
        }
        if (!empty($member_id)) {
            $where['m.member_id'] = $member_id;
        }
        if (!empty($status)) {
            $where['tb.status'] = $status;
        } else {
            $where['tb.status'] = array("in", array(0, 1, -1, -2));
        }
        if (!empty($url)) {
            $where['tb.to_url'] = array('like', '%' . $url . '%');
        }

        $hash = input("hash");
        if ($hash) {
            $where['tb.ti_id'] = $hash;
        }

        $field = "tb.*,m.email,m.member_id,m.name,m.phone,c.currency_name,c.currency_type,m.remarks";
        //导出数据
        $statusList = ['0' => '等待确认中', '1' => '已完成', '-1' => '审核中', '-2' => '已撤销'];
        if ($daochu == 2) {
            $field = "tb.id,m.member_id,m.phone,c.currency_name,tb.from_url,tb.tag,tb.ti_id,tb.num,tb.actual,tb.add_time,tb.check_time,tb.status,tb.to_url,m.name,m.email";
            $list = Db::name("Tibi")->alias("tb")->field($field)->where($where)
                ->join(config("database.prefix") . "member m", "m.member_id=tb.from_member_id", "LEFT")
                ->join(config("database.prefix") . "currency c", "c.currency_id=tb.currency_id", "LEFT")
                ->order("check_time desc")->select();

            if (!empty($list)) {
                foreach ($list as &$value) {
                    $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
                    $value['check_time'] = date("Y-m-d H:i:s", $value['check_time']);
                    $value['status'] = $statusList[$value['status']];
                }
            }
            $xlsCell = array(
                '列表ID',
                '会员ID',
                '手机',
                '币类型名称',
                '转账地址',
                '接收标签',
                '转账编号',
                '转出数量',
                '到账数量',
                '申请时间',
                '操作时间',
                '状态',
                '接收钱包',
                '姓名',
                '会员邮箱',
            );
            return exportExcel($xlsCell, $list, "提币审核记录");

        }

        $today_begin = todayBeginTimestamp();
        //昨日提币总数量
        $tibi_total = Db::name('tibi')->field('currency_id,sum(num) as num,sum(actual) as actual')->where([
            'transfer_type' => '1',
            'status' => ['in', [-2, -1, 0, 1]],
            'add_time' => ['between', [$today_begin - 86400, $today_begin]],
        ])->group('currency_id')->select();

        //昨日审核提币总数量
        $audit_total = Db::name('tibi')->field('currency_id,sum(num) as num,sum(actual) as actual')->where([
            'transfer_type' => '1',
            'status' => 1,
            'add_time' => ['between', [$today_begin - 86400, $today_begin]],
        ])->group('currency_id')->select();
        $this->assign('tibi_total', $tibi_total);
        $this->assign('audit_total', $audit_total);

        $today_begin = todayBeginTimestamp();
        //今日提币总数量
        $tibi_total_today = Db::name('tibi')->field('currency_id,sum(num) as num,sum(actual) as actual')->where([
            'transfer_type' => '1',
            'status' => ['in', [-2, -1, 0, 1]],
            'add_time' => ['egt', $today_begin],
        ])->group('currency_id')->select();
        $this->assign('tibi_total_today', $tibi_total_today);

        //充币未到账
//        $xrp_currency_fail = Db::query("select * from (
//select substring_index(ato,'_',1) as ato1,sum(amount) as amount from yang_currency_log where status=4  GROUP BY ato1) a
//where ato1 in (
//	select waa_address from yang_wallet_admin_address where waa_type='recharge'
//)");
        $currency_fail = Db::name('currency_log')->where(['status' => 4, 'types' => 9])->select();
        $xrp_currency_fail = [];
        foreach ($currency_fail as $item) {
            if (!empty($item['trans'])) {
                $trans = json_decode($item['trans'], true);
                if ($trans['token'] == 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t') {
                    if (empty($xrp_currency_fail[0]['amount']))
                        $xrp_currency_fail[0]['amount'] = 0;
                    $xrp_currency_fail[0]['amount'] += $trans['amount'];
                    $xrp_currency_fail[0]['ato1'] = 'USDT';
                } else if ($trans['token'] == 'TXMdEqpiNrMXR5We8cfh2g73vU7uB6gRxR') {
                    if (empty($xrp_currency_fail[1]['amount']))
                        $xrp_currency_fail[1]['amount'] = 0;
                    $xrp_currency_fail[1]['amount'] += $trans['amount'];
                    $xrp_currency_fail[1]['ato1'] = 'MTK';
                }
            }
        }

        //充币到账
//        $xrp_currency_success = Db::query("select * from (
//select substring_index(ato,'_',1) as ato1,sum(amount) as amount from yang_currency_log where status=3  GROUP BY ato1) a
//where ato1 in (
//	select waa_address from yang_wallet_admin_address where waa_type='recharge'
//)");
        $currency_success = Db::name('currency_log')->where(['status' => 3, 'types' => 9])->select();
        $xrp_currency_success = [];
        foreach ($currency_success as $item) {
            if (!empty($item['trans'])) {
                $trans = json_decode($item['trans'], true);
                if (!empty($trans['token'])) {
                    if ($trans['token'] == 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t') {
                        if (empty($xrp_currency_success[0]['amount']))
                            $xrp_currency_success[0]['amount'] = 0;
                        $xrp_currency_success[0]['amount'] += $trans['amount'];
                        $xrp_currency_success[0]['ato1'] = 'USDT';
                    } else if ($trans['token'] == 'TXMdEqpiNrMXR5We8cfh2g73vU7uB6gRxR') {
                        if (empty($xrp_currency_success[1]['amount']))
                            $xrp_currency_success[1]['amount'] = 0;
                        $xrp_currency_success[1]['amount'] += $trans['amount'];
                        $xrp_currency_success[1]['ato1'] = 'MTK';
                    }
                }
            }
        }

        $this->assign('xrp_currency_fail', $xrp_currency_fail);
        $this->assign('xrp_currency_success', $xrp_currency_success);

        $list = Db::name("Tibi")->alias("tb")->field($field)->where($where)
            ->join(config("database.prefix") . "member m", "m.member_id=tb.from_member_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "c.currency_id=tb.currency_id", "LEFT")
            ->order("add_time desc")->paginate(20, null, ['query' => input()]);
        $show = $list->render();
        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出

        //读取积分类型表
        $curr = Db::name("Currency")->where(['status' => 1, /*'is_line' => 1*/])->select();
        $curr = array_column($curr, null, 'currency_id');
        $this->assign("curr", $curr);

        $temp['cuid'] = $cuid;
        $temp['member_id'] = $member_id;
        $temp['status'] = $status;
        $temp['phone'] = $phone;
        $temp['email'] = $email;
        $temp['url'] = $url;
        $temp['hash'] = $hash;
        $this->assign("status_list", $statusList);
        $this->assign("temp", $temp);
        return $this->fetch();
    }

    /**
     * 互转记录
     * @return mixed
     * @throws DbException
     */
    public function mutual_log()
    {
        $where = [];
        $whereOr = [];
        $member_id = $this->request->param('member_id', 0);
        if ($member_id) {
            $where['t1.cut_user_id'] = $member_id;
            $whereOr['t1.cut_target_user_id'] = $member_id;
        }
        $currency_id = $this->request->param('currency_id', 0);
        if ($currency_id) {
            $where['t1.cut_currency_id'] = $currency_id;
        }

        $list = CurrencyUserTransfer::alias('t1')
            ->join([config("database.prefix") . 'member' => 't2'], ['t1.cut_user_id = t2.member_id'], "LEFT")
            ->join([config("database.prefix") . 'member' => 't3'], ['t1.cut_target_user_id = t3.member_id'], "LEFT")
            ->join([config("database.prefix") . 'currency' => 't4'], ['t1.cut_currency_id = t4.currency_id'], "LEFT")
            ->field(['t1.*', 't2.ename', 't3.ename' => 'target_name', 't4.currency_name'])
            ->where($where)->whereOr($whereOr)->order(['t1.cut_add_time' => 'desc'])->paginate(null, null, ["query" => $this->request->get()]);
        $page = $list->render();
        $count = $list->total();
        $currencys = Currency::where(['status' => 1])->select();
        return $this->fetch(null, compact('list', 'page', 'count', 'currencys'));
    }

    /**
     * OTC - 广告
     * @param Request $request
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function otc_order(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $member_id = $request->param('member_id', 0, 'intval');
            if ($member_id > 0) $where['member_id'] = $member_id;

            $page = $request->param('page', 1, 'intval');
            $limit = $request->param('limit', 10, 'intval');
            $list = InsideOrder::where($where)
                ->order(['order_id' => 'desc'])->page($page, $limit)->select();
            if (empty($list))
                return ['code' => 0, 'message' => '找不到相关数据'];

            $status = ['1' => '进行中', '2' => '成交', '3' => '撤销'];
            foreach ($list as &$item) {
                $item['member_id'] = '<a href="javascript:void(0)" onClick="window.openUser(' . $item['member_id'] . ')">' . $item['member_id'] . '</a>';
                $item['type'] = $item['type'] == 1 ? '买' : '卖';//类型 1买 2卖
                $item['status'] = $status[$item['status']];
                $item['add_time'] = date('Y-m-d H:i:s', $item['add_time']);//创建时间
                $item['trade_time'] = date('Y-m-d H:i:s', $item['trade_time']);//成交时间
            }
            $count = InsideOrder::where($where)->count();
            return ['code' => 0, 'message' => '成功', 'data' => $list, 'count' => $count];
        }
        return $this->fetch();
    }

    // OTC - 广告撤销
    public function otc_order_revoke(Request $request)
    {
        if ($request->isAjax()) {
            $id = $request->param('id', 0, 'intval');
            $info = InsideOrder::where(['order_id' => $id])->find();
            if (empty($info)) {
                return ['code' => ERROR1, 'message' => '找不到相关数据'];
            }
            return InsideOrder::trade_revoke($info['member_id'], $info['order_id']);
        }
    }

    // OTC - 交易
    public function otc_trade(Request $request)
    {
        if ($request->isAjax()) {
            $where = [];
            $member_id = $request->param('member_id', 0, 'intval');
            if ($member_id > 0) $where['a.member_id'] = $member_id;

            $page = $request->param('page', 1, 'intval');
            $limit = $request->param('limit', 10, 'intval');
            $list = InsideTrade::where($where)
                ->alias('a')
                ->field('a.*')
                ->page($page, $limit)->order('a.trade_id desc')->select();
            if (empty($list))
                return ['code' => 0, 'message' => '找不到相关数据'];

            foreach ($list as &$item) {
                $item['pay_member_id'] = '<a href="javascript:void(0)" onClick="window.openUser(' . $item['pay_member_id'] . ')">' . $item['pay_member_id'] . '</a>';
                $item['type'] = $item['type'] == 1 ? '买' : '卖';//类型 1买 2卖
                $item['add_time'] = date('Y-m-d H:i:s', $item['add_time']);//创建时间
            }
            $count = InsideTrade::where($where)->count();
            return ['code' => 0, 'message' => '成功', 'data' => $list, 'count' => $count];
        }
        return view();
    }

    // 钱包地址管理
    public function query_wallet(Request $request) {
        $where = [];
        $currencyId = $request->get('currency_id');
        $ename = $request->get('ename', '', 'trim');
        $member_id = $request->get('member_id', '', 'trim');

        if ($ename) {
            $cid = \app\common\model\Member::where(['ename' => $ename])->value('member_id');
            $where['cae_member_id'] = $cid;
        }
        if ($member_id) {
            $where['cae_member_id'] = $member_id;
        }
        $where['cae_is_use'] = 2;
        if ($currencyId == 2) {
            $list = \app\common\model\CurrencyAddressBnb::with('users')->order('cae_id', 'desc')->where($where)
                ->paginate($this->pageRows, null, $request->get());
            if ($list) {
                foreach ($list as &$value) {
                    $value['name'] = 'Y令牌';
                }
            }
        } else {
            $list = \app\common\model\CurrencyAddressTrx::with('users')->order('cae_id', 'desc')->where($where)
                ->paginate($this->pageRows, null, $request->get());
            if ($list) {
                foreach ($list as &$value) {
                    $value['name'] = 'MTK';
                }
            }
        }

        $page = $list->render();
        $count = $list->total();
        $currencys = [['currency_id' => 1, 'name' => 'MTK'], ['currency_id' => 2, 'name' => 'Y令牌']];
        return $this->fetch(null, compact('list', 'page', 'count', 'currencys'));
    }

    /**
     * 方舟内部进仓
     * @return mixed
     * @throws DbException
     */
    public function ark_mutual_log()
    {
        $where = [];
        $whereOr = [];
        $member_id = $this->request->param('member_id', 0);
        if ($member_id) {
            $where['a.member_id'] = $member_id;
            $whereOr['b.to_member_id'] = $member_id;
        }
        $where2 = [];
        $currency_id = $this->request->param('currency_id', 0);
        if ($currency_id) {
            $where2['a.currency_id'] = $currency_id;
        } else {
            $where2['a.currency_id'] = ['in', [103, 104]];
        }
        $where2['a.type'] = 6;
        $where2['b.transfer_type'] = 1;
        $arr = Db::name("accountbook")->alias('a')
            ->join('tibi b', 'a.third_id=b.id')
            ->field('a.*,b.to_member_id as t_member_id')
            ->where(function ($query) use($where, $whereOr){
                $query->where($where)->whereOr($whereOr);
            })->where($where2)->order("a.id desc")->paginate(null, null, ["query" => $this->request->get()]);
        $list = [];
        if ($arr) {
            foreach ($arr as $key => $value) {
                $list[$key]['id'] = $value['id'];
                $list[$key]['member_id'] = $value['member_id'];
                $list[$key]['number'] = $value['number'];
                $list[$key]['to_member_id'] = $value['t_member_id'];
                $list[$key]['currency_name'] = Db::name('currency')->where(['currency_id' => $value['currency_id']])->value('currency_name');
                $list[$key]['ename'] = Db::name('member')->where(['member_id' => $value['member_id']])->value('ename');
                $list[$key]['target_name'] = Db::name('member')->where(['member_id' => $value['t_member_id']])->value('ename');
                $list[$key]['add_time'] = $value['add_time'];
            }
        }
        $page = $arr->render();
        $count = $arr->total();
        $currencys = Currency::whereIn('currency_id', [103, 104])->select();
        return $this->fetch(null, compact('list', 'page', 'count', 'currencys'));
    }
}