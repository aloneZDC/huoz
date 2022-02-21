<?php
/*
 * 自动审核
 */
namespace Admin\Controller;

class AuditController extends AdminController
{
    private $host;

    // 空操作
    public function __construct()
    {
        parent::__construct();
        $this->host = (isset($_SERVER['HTTP_X_CLIENT_SCHEME']) ? $_SERVER['HTTP_X_CLIENT_SCHEME'] : $_SERVER['REQUEST_SCHEME']) . '://' . $_SERVER['HTTP_HOST'];
    }

    //显示审核页面
    public function index()
    {
        $withdraw = M('Withdraw');
        $names = I('keyname');
        $keyid = I('keyid');
        $keynum = I('keynum');
        $tpl_id = I('tpl_id');
        $where = array();
        $where ['yang_withdraw.status'] = 3;
        $where ['yang_withdraw.firstaudit_term'] = 0;

        if (!empty($names)) {
            //模糊
            $where ['yang_bank.cardname'] = array('like', '%' . $names . '%');
        }
        if (!empty($keyid)) {
            $where ['yang_Withdraw.uid'] = array('like', '%' . $keyid . '%');
        }
        if (!empty($keynum)) {
            $where ['email'] = array('like', '%' . $keynum . '%');
        }
        // 查询满足要求的总记录数
        $count = $withdraw->join("yang_bank ON yang_withdraw.bank_id = yang_bank.id")->join("yang_member on yang_withdraw.uid=yang_member.member_id")->where($where)->count();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count, 20);
        //将分页（点击下一页）需要的条件保存住，带在分页中
        $Page->parameter = array(
            'yang_withdraw.status' => isset($where ['yang_withdraw.status']) ? $where ['yang_withdraw.status'] : 0,
            'yang_bank.cardname' => $names,
            'yang_Withdraw.uid' => $keyid,
            'yang_member.email' => $keynum
        );
        // 分页显示输出
        $show = $Page->show();
        //需要的数据
        $field = "yang_withdraw.*,yang_bank.cardname,yang_bank.cardnum,yang_bank.bankname,yang_bank.account_inname,b.area_name as barea_name,a.area_name as aarea_name,c.email,c.name username";
        $info = $withdraw->field($field)
            ->join("left join yang_bank ON yang_withdraw.bank_id = yang_bank.id")
            ->join("left join yang_areas as b ON b.area_id = yang_bank.address")
            ->join("left join yang_areas as a ON a.area_id = b.parent_id ")
            ->join("left join yang_member as c on yang_withdraw.uid=c.member_id")
            ->where($where)
            ->order('yang_withdraw.add_time desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        $this->assign('info', $info); // 赋值数据集
        $this->assign('page', $show); // 赋值分页输出

        //筛选规则--模版数据取值
        $condition = M('withdraw_condition')->select();
        $this->assign('condition', $condition);
        $condition_show = [
            'id' => '',
            'name' => '',
            'list' => []
        ];
        $condition_item = [];
        $currency_item = [];
        if (!empty($tpl_id)) {
            $condition_show = M('withdraw_condition')->where(['id' => $tpl_id])->find();
            $condition_show['list'] = explode(',', $condition_show['list']);
            $condition_item = M('withdraw_condition_item')->where(['pid' => $tpl_id])->select();

            if (!empty($condition_item)) {
                $_tmp = [];
                $in_array = ['con_6', 'con_7'];
                foreach ($condition_item as $value) {
                    $_tmp[$value['key']] = $value['value'];
                    if (in_array($value['key'], $in_array)) {
                        $currency_name = M("currency")->where(['currency_id' => ['in', $value['value']]])->field("currency_name")->select();
                        if (!empty($currency_name)) {
                            $__tmp = [];
                            foreach ($currency_name as $val) {
                                $__tmp[] = $val['currency_name'];
                            }
                            $currency_item[$value['key']] = implode(',', $__tmp);
                        }
                    }
                }
                $condition_item = $_tmp;
            }
            $this->assign('tpl_id', $tpl_id);
        }
        $this->assign('condition_show', $condition_show);
        $this->assign('condition_item', $condition_item);
        $this->assign('currency_item', $currency_item); //积分列表

        $this->assign('inquire', $names);
        $this->display();
    }

    /**
     * 批量付款页面
     */
    public function payment()
    {
        $withdraw = M('Withdraw');
        $names = I('keyname');
        $keyid = I('keyid');
        $keynum = I('keynum');
        $order_id = I('order_id');
        $order_num = I('order_num');
        $pay_type = I('pay_type');
        $date = I('date', '');
        $state = I('state', '');
        $do = I('get.do', '');
        $where = [];

        $order = 'yang_withdraw.add_time desc';
        $where ['yang_withdraw.status'] = 2;

        if (!empty($names)) {
            //模糊
            $where ['yang_bank.cardname'] = array('like', '%' . $names . '%');
            $this->assign('inquire', $names);
        }
        if (!empty($keyid)) {
            $where ['yang_Withdraw.uid'] = array('like', '%' . $keyid . '%');
            $this->assign('keyid', $keyid);
        }
        if (!empty($keynum)) {
            $where ['email'] = array('like', '%' . $keynum . '%');
            $this->assign('email', $keynum);
        }
        if (!empty($order_id)) {
            $where ['withdraw_id'] = $order_id;
            $this->assign('order_id', $order_id);
        }
        if (!empty($order_num)) {
            $where ['order_num'] = $order_num;
            $this->assign('order_num', $order_num);
        }

        $ed = 0;
        if (!empty($do) && $do == 'ed') {
            $ed = 1;
        }

        //已付款
        if ($ed == 1) {
            $where ['yang_withdraw.status'] = ['in', [-1, 4]];
            $order = 'yang_withdraw.pay_time desc';

            if (!empty($state) && intval($state) > 0) {
                $where ['yang_withdraw.pay_statue'] = intval($state);
            }

            if (!empty($date)) {
                $start = strtotime($date);
                $end = strtotime($date . " 23:59:59");
                $where ['yang_withdraw.pay_time'] = [['gt', $start], ['lt', $end], 'and'];

                $sql = "select * from (select ifnull(sum(money), '0.00') all_money from yang_withdraw where pay_time >= '{$start}' and pay_time <= '{$end}') _all join (select ifnull(sum(money), '0.00') a_money from yang_withdraw where pay_time >= '{$start}' and pay_time <= '{$end}' and pay_statue = 0) a join (select ifnull(sum(money), '0.00') b_money from yang_withdraw where pay_time >= '{$start}' and pay_time <= '{$end}' and pay_statue = 1) b join (select ifnull(sum(money), '0.00') c_money from yang_withdraw where pay_time >= '{$start}' and pay_time <= '{$end}' and pay_statue = 2) c";
                $count_money = $withdraw->query($sql);
                $this->assign('count_money', $count_money[0]);
            }

            $this->assign('do', $do);
            $this->assign('date', $date);
            $this->assign('state', $state);
        }

        // 查询满足要求的总记录数
        $count = $withdraw->join("yang_bank ON yang_withdraw.bank_id = yang_bank.id")->join("yang_member on yang_withdraw.uid=yang_member.member_id")->where($where)->count();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count, 20);
        //将分页（点击下一页）需要的条件保存住，带在分页中
        $Page->parameter = array(
            'do' => $do,
            'date' => $date,
            'state' => $state,
            'keyname' => $names,
            'keyid' => $keyid,
            'email' => $keynum
        );
        // 分页显示输出
        $show = $Page->show();
        //需要的数据
        $field = "yang_withdraw.*,yang_bank.cardname,yang_bank.cardnum,yang_bank.bankname,yang_bank.account_inname,b.area_name as barea_name,a.area_name as aarea_name,c.email,c.name username";
        $info = $withdraw->field($field)
            ->join("left join yang_bank ON yang_withdraw.bank_id = yang_bank.id")
            ->join("left join yang_areas as b ON b.area_id = yang_bank.address")
            ->join("left join yang_areas as a ON a.area_id = b.parent_id ")
            ->join("left join yang_member as c on yang_withdraw.uid=c.member_id")
            ->where($where)
            ->order($order)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        $this->assign('info', $info); // 赋值数据集
        $this->assign('page', $show); // 赋值分页输出

        $this->assign('pay_type', $pay_type);
        $this->display();
    }

    /**
     * 付款失败订单修改备注信息
     */
    public function save_pay_remark()
    {
        $id = I("post.id", '');
        $remark = I("post.text", '');

        if (empty($id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '订单不存在']);
        }

        if (!empty($remark)) {
            $db = M('withdraw');
            $update = $db->where(['withdraw_id' => $id])->save(['pay_remark' => $remark]);
            if ($update === false) {
                $this->ajaxReturn(['Code' => 0, 'Msg' => '保存失败']);
            }
        }

        $this->ajaxReturn(['Code' => 1, 'Msg' => '保存成功']);
    }

    /**
     * 付款失败--确认手工处理
     */
    public function save_pay_action()
    {
        $id = I("post.id", '');

        if (empty($id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '订单不存在']);
        }

        $db = M('withdraw');

        //推送到APP
        $uid = $db->field('uid')->where(['withdraw_id' => $id])->find()['uid'];
        if(empty($uid)){
            $this->ajaxReturn(['Code' => 0, 'Msg' => '订单不存在']);
        }

        $jpush = A("Api/Jpush");
        $jpush->index('withdraw', 'suc', $uid);

        $update = $db->where(['withdraw_id' => $id])->save(['pay_statue' => 1, 'pay_action' => 2]);
        if ($update === false) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '修改失败']);
        }

        $this->ajaxReturn(['Code' => 1, 'Msg' => '修改成功']);
    }

    /**
     * ajax批量付款请求
     */
    public function pay()
    {
        $list = I("post.list", "");
        $type = I("post.type", '');

        if (empty($list)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '请选择要付款的订单']);
        }

        if (empty($type)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '请选择付款平台']);
        }

        $db = M('withdraw');
        $field = "a.withdraw_id order_id,round(a.money, 2) money,round(a.all_money, 2) all_money,round(a.withdraw_fee, 2) withdraw_fee,a.uid,a.add_time,a.order_num,b.cardname,b.cardnum,b.bankname,c.area_name as area1,d.area_name as area2,e.email,e.name username,e.phone";

        $_result = [];
        foreach ($list as $value) {
            $where = [
                //'b.is_alipay' => 0,
                'a.status' => 2,
                'a.withdraw_id' => ['eq', $value]
            ];
            $_result[] = $db->alias('a')->field($field)
                ->join("left join yang_bank b on a.bank_id = b.id")
                ->join("left join yang_areas d on d.area_id = b.address")
                ->join("left join yang_areas c on c.area_id = d.parent_id")
                ->join("left join yang_member as e on a.uid = e.member_id")
                ->where($where)
                ->find();
        }

        if (empty($_result)) {
            $this->ajaxReturn(['Code' => 1, 'Msg' => '付款完成，没有可操作订单']);
        }

        $payName = 'pay_' . $type;

        if (!method_exists($this, $payName)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '付款未完成，付款平台方法不存在']);
        }

        $this->$payName($_result, $type);
    }

    /**
     * 通联代付
     * @param $_result
     */
    private function pay_allinpay($_result, $typeName)
    {
        $now = time();
        $order_id = 'T'.$now.rand(10, 99);
        $post_data = [
            'time' => date("YmdHis", $now),
            'totalAmount' => 0,
            'num' => 0,
            'batchNo' => $order_id,
            'content' => "",
        ];

        //正式
        $merno_info = [
            'daifu_pid' => $this->config['allinpay_daifu_pid'],
            'daifu_user' => $this->config['allinpay_daifu_user'],
            'daifu_pass' => $this->config['allinpay_daifu_pass'],
        ];

        //开发
        $merno_info = [
            'daifu_pid' => "200604000000445",
            'daifu_user' => "20060400000044502",
            'daifu_pass' => "`12qwe",
        ];

        $all_money = 0;
        $withdraw_fee = 0;
        $withdraw_log_content = [];

        require_once THINK_PATH . 'Extend/Vendor/allinpayInter/libs/ArrayXml.class.php';
        require_once THINK_PATH . 'Extend/Vendor/allinpayInter/libs/cURL.class.php';
        require_once THINK_PATH . 'Extend/Vendor/allinpayInter/libs/PhpTools.class.php';
        $tools = new \PhpTools();

        /**
         * 批量代收付接口
         * TRX_CODE:100002--批量代付
         * TRX_CODE:100001--批量代收
         * 文档地址：http://113.108.182.3:8282/techsp/helper/filedetail/tlt/filedetail131.html
         * http://113.108.182.3:8282/techsp/helper/basicdef/tlt/basicdef.html
         * http://112.95.232.217:8888/allinpayapi
         */

        // 源数组
        $params = [
            'INFO' => [
                'TRX_CODE' => '100002', //批量代付
                'VERSION' => '03', //版本
                'DATA_TYPE' => '2', //数据格式
                'LEVEL' => '5', //处理级别
                'USER_NAME' => $merno_info['daifu_user'], //用户名
                'USER_PASS' => $merno_info['daifu_pass'], //用户密码
                'REQ_SN' => $merno_info['daifu_pid']."_".$post_data['time']."_".$post_data['batchNo'], //交易批次号：商户号+时间+固定位数顺序流水号
            ],
            'BODY' => [
                'TRANS_SUM' => [
                    'BUSINESS_CODE' => '09900', //业务代码
                    'MERCHANT_ID' => $merno_info['daifu_pid'], //商户代码
                    'SUBMIT_TIME' => $post_data['time'], //提交时间
                    'TOTAL_ITEM' => '0', //总记录数
                    'TOTAL_SUM' => '0', //总金额
                ],
                'TRANS_DETAILS'=> []
            ],
        ];

        $_pay_result = [];
        $fail_user = "";
        $index = 1;
        foreach ($_result as $value) {
            $username = !empty(trim($value['username'])) ? trim($value['username']) : trim($value['cardname']);
            if (empty($username)) {
                //账户名为空，跳过
                continue;
            }

            $bank_code = $tools->get_bank_code($value['bankname']);

            if (empty($bank_code)) {
                //没获取到银行卡号，跳过
                $fail_user .= " 用户：{$username} 银行名称({$value['bankname']})有误，跳过本次付款；<br>";
                continue;
            }
            $_pay_result[] = $value;

            $post_data['num']++;
            $post_data['totalAmount'] += number_format($value['money'], 2, '.', '');

            $params['BODY']['TRANS_DETAILS'][] = [
                'SN' => '0000'.$index,
                'BANK_CODE'=> $bank_code['bank_code'],
                'ACCOUNT_NO'=> trim($value['cardnum']),
                'ACCOUNT_NAME'=> $username,
                'BANK_NAME'=> $bank_code['bank_name'],
                'ACCOUNT_PROP'=> '0',
                'AMOUNT'=> intval(number_format($value['money'], 2, '.', '') * 100)
            ];
            $index++;

            $all_money += $value['all_money'];
            $withdraw_fee += $value['withdraw_fee'];
            $withdraw_log_content[] = [
                'accountName' => $value['cardname'],
                'amount' => $value['money'],
                'bankCode' => $bank_code,
                'cardNumber' => $value['cardnum'],
                'orderNumber' => $value['order_num'],
                'state' => 1,
            ];
        }

        $params['BODY']['TRANS_SUM']['TOTAL_ITEM'] = $post_data['num'];
        $params['BODY']['TRANS_SUM']['TOTAL_SUM'] = intval(number_format($post_data['totalAmount'], 2, '.', '') * 100);

        if(empty($params['BODY']['TRANS_DETAILS'])){
            $this->ajaxReturn(['Code' => 0, 'Msg' => "付款失败：没有可付款的订单号<br><br>{$fail_user}"]);
        }

        //发起请求
        $result = $tools->send($params);
        debug($result);

        $action = "https://tlt.allinpay.com/aipg/ProcessServlet"; //生产
        #$action = "https://113.108.182.3/aipg/ProcessServlet"; //开发
        $ret = _curl($action, $post_data);
        //debug(json_decode($ret, true));
        $responseData = json_decode($ret);

        //存日志
        logs($ret, 'pay_moneymoremore_end', '/payment/');
        $response_joinMap = array(
            "merno" => $responseData->merno,
            "time" => $responseData->time,
            "totalAmount" => $responseData->totalAmount,
            "num" => $responseData->num,
            "batchNo" => $responseData->batchNo,
            "content" => $responseData->content,
            "status" => $responseData->status
        );

        $responseBeforeSignedData = $this->joinMapValue($response_joinMap);

        $verifySignature = $rsa->verify($responseBeforeSignedData, $responseData->signature);

        $Member = isset($Member) ? $Member : M('Member');
        $Withdraw = isset($Withdraw) ? $Withdraw : M('Withdraw');
        $Withdraw_log = isset($Withdraw_log) ? $Withdraw_log : M('withdraw_log');
        $Withdraw_log_con = isset($Withdraw_log_con) ? $Withdraw_log_con : M('withdraw_log_content');

        //保存付款记录
        $pay_log = [
            'batchNo' => $responseData->batchNo,
            'orderNo' => '',
            'state' => 1,
            'num' => $responseData->num,
            'totalAmount' => $all_money,
            'amount' => $responseData->totalAmount,
            'poundage' => $withdraw_fee,
        ];
        $insert_log = $Withdraw_log->add($pay_log);
        if ($insert_log) {
            $id = $Withdraw_log->getLastInsID();
            foreach ($withdraw_log_content as &$value) {
                $value['log_id'] = $id;
                $Withdraw_log_con->add($value);
            }
        }

        //付款失败
        if ($verifySignature == false || $responseData->status != 'success') {
            //给用户退钱
            foreach ($_pay_result as $value) {
                //退钱
                //$Member->where(['member_id' => $value['uid']])->setInc('rmb', $value['all_money'], 30);
                //修改状态为失败
                $Withdraw->where(['withdraw_id' => $value['order_id']])->save(['status' => '4', 'admin_uid' => session('admin_userid'), 'pay_time' => $now, 'pay_statue' => 2, 'pay_type' => $typeName, 'pay_order_id' => $order_id]);
            }

            //付款未完成
            if ($verifySignature == false) { //非法请求
                $this->ajaxReturn(['Code' => 0, 'Msg' => '付款失败 (' . $responseData->remark . ')']);
            }

            $this->ajaxReturn(['Code' => 0, 'Msg' => '付款失败 [' . $responseData->remark . ']']);
        }

        $jpush = A("Api/Jpush");
        //付款成功
        foreach ($_pay_result as $value) {

            //推送到APP
            $jpush->index('withdraw', 'suc', $value['uid']);

            //修改状态为成功
            $Withdraw->where(['withdraw_id' => $value['order_id']])->save(['status' => '4', 'admin_uid' => session('admin_userid'), 'pay_time' => $now, 'pay_statue' => 1, 'pay_type' => $typeName, 'pay_order_id' => $order_id]);
        }
        $this->ajaxReturn(['Code' => 1, 'Msg' => '付款成功'.$fail_user]);
    }

    /**
     * 钱多多批量付款[代付]
     * @param $_result
     */
    private function pay_moneymoremore($_result, $typeName)
    {
        $now = time();
        $order_id = 'T'.$now.rand(10, 99);
        $post_data = [
            'merno' => $this->config['sqpay_pid'],
            'time' => date("YmdHis", $now),
            'totalAmount' => 0,
            'num' => 0,
            'batchNo' => $order_id,
            'content' => "",
        ];

        $all_money = 0;
        $withdraw_fee = 0;
        $withdraw_log_content = [];

        require_once(THINK_PATH . 'Extend/Vendor/moneymoremore/RSA.php');
        $rsa = new \Payment\sdk\RSA();

        $_pay_result = [];
        $fail_user = "";
        foreach ($_result as $value) {
            $username = !empty(trim($value['username'])) ? trim($value['username']) : trim($value['cardname']);
            if (empty($username)) {
                //账户名为空，跳过
                continue;
            }

            $bank_code = $rsa->get_bank_code($value['bankname']);

            if ($bank_code == 'NULL') {
                //没获取到银行卡号，跳过
                $fail_user .= " 用户：{$username} 银行名称({$value['bankname']})有误，跳过本次付款；<br>";
                continue;
            }
            $_pay_result[] = $value;

            $post_data['num']++;
            $post_data['content'] .= $username . "|" . $bank_code . "|" . trim($value['cardnum']) . "|1|" . $value['money'] . "|" . trim($value['order_num']) . "|303#";
            $post_data['totalAmount'] += number_format($value['money'], 2, '.', '');

            $all_money += $value['all_money'];
            $withdraw_fee += $value['withdraw_fee'];
            $withdraw_log_content[] = [
                'accountName' => $value['cardname'],
                'amount' => $value['money'],
                'bankCode' => $bank_code,
                'cardNumber' => $value['cardnum'],
                'orderNumber' => $value['order_num'],
                'state' => 1,
            ];
        }
        $post_data['content'] = substr($post_data['content'], 0, -1);

        if(empty($post_data['content'])){
            $this->ajaxReturn(['Code' => 0, 'Msg' => "付款失败：没有可付款的订单号<br><br>{$fail_user}"]);
        }

        //重新排序
        $post_data = [
            'merno' => $post_data['merno'],
            'time' => $post_data['time'],
            'totalAmount' => number_format($post_data['totalAmount'], 2, '.', ''),
            'num' => $post_data['num'],
            'batchNo' => $post_data['batchNo'],
            'content' => $post_data['content']
        ];

        $beforeSignedData = $this->joinMapValue($post_data);
        $post_data["signature"] = $rsa->sign($beforeSignedData);
        //发送日志
        logs($post_data, 'pay_moneymoremore_start', '/payment/');

        $action = "https://df.95epay.cn/merchant/numberPaid.action"; //生产
        #$action = "http://218.4.234.150:9600/merchant/numberPaid.action"; //开发  测试商户号：168893
        $ret = _curl($action, $post_data);
        //debug(json_decode($ret, true));
        $responseData = json_decode($ret);

        //回调日志
        logs($ret, 'pay_moneymoremore_end', '/payment/');
        $response_joinMap = array(
            "merno" => $responseData->merno,
            "time" => $responseData->time,
            "totalAmount" => $responseData->totalAmount,
            "num" => $responseData->num,
            "batchNo" => $responseData->batchNo,
            "content" => $responseData->content,
            "status" => $responseData->status
        );

        $responseBeforeSignedData = $this->joinMapValue($response_joinMap);

        $verifySignature = $rsa->verify($responseBeforeSignedData, $responseData->signature);

        $Member = isset($Member) ? $Member : M('Member');
        $Withdraw = isset($Withdraw) ? $Withdraw : M('Withdraw');
        $Withdraw_log = isset($Withdraw_log) ? $Withdraw_log : M('withdraw_log');
        $Withdraw_log_con = isset($Withdraw_log_con) ? $Withdraw_log_con : M('withdraw_log_content');

        //保存付款记录
        $pay_log = [
            'batchNo' => $responseData->batchNo,
            'orderNo' => '',
            'state' => 1,
            'num' => $responseData->num,
            'totalAmount' => $all_money,
            'amount' => $responseData->totalAmount,
            'poundage' => $withdraw_fee,
        ];
        $insert_log = $Withdraw_log->add($pay_log);
        if ($insert_log) {
            $id = $Withdraw_log->getLastInsID();
            foreach ($withdraw_log_content as &$value) {
                $value['log_id'] = $id;
                $Withdraw_log_con->add($value);
            }
        }

        //付款失败
        if ($verifySignature == false || $responseData->status != 'success') {
            //给用户退钱
            foreach ($_pay_result as $value) {
                //退钱
                //$Member->where(['member_id' => $value['uid']])->setInc('rmb', $value['all_money'], 30);
                //修改状态为失败
                $Withdraw->where(['withdraw_id' => $value['order_id']])->save(['status' => '4', 'admin_uid' => session('admin_userid'), 'pay_time' => $now, 'pay_statue' => 2, 'pay_type' => $typeName, 'pay_order_id' => $order_id]);
            }

            //付款未完成
            if ($verifySignature == false) { //非法请求
                $this->ajaxReturn(['Code' => 0, 'Msg' => '付款失败 (' . $responseData->remark . ')']);
            }

            $this->ajaxReturn(['Code' => 0, 'Msg' => '付款失败 [' . $responseData->remark . ']']);
        }

        $jpush = A("Api/Jpush");
        //付款成功
        foreach ($_pay_result as $value) {

            //推送到APP
            $jpush->index('withdraw', 'suc', $value['uid']);

            //修改状态为成功
            $Withdraw->where(['withdraw_id' => $value['order_id']])->save(['status' => '4', 'admin_uid' => session('admin_userid'), 'pay_time' => $now, 'pay_statue' => 1, 'pay_type' => $typeName, 'pay_order_id' => $order_id]);
        }
        $this->ajaxReturn(['Code' => 1, 'Msg' => '付款成功'.$fail_user]);
    }

    /**
     * 爱农代付
     * @param $_result
     * @param $typeName
     */
    private function pay_chinagpay($_result, $typeName)
    {
        $_result = $_result[0];

        require_once(THINK_PATH . 'Extend/Vendor/chinagpay/HttpClient.class.php');
        $HttpClient = new \Payment\sdk\HttpClient();

        $username = trim($_result['cardname']) ? $_result['cardname'] : $_result['username'];
        $bankname = trim($_result['bankname']);
        $bank_code = $HttpClient->get_bank_code($bankname);
        if($bank_code == 'NULL'){
            logs("订单号：{$_result['order_num']}，用户名：{$username} 付款失败，没有获取到【{$bankname}】银行编号。", 'pay_chinagpay_err', '/payment/');
            $this->ajaxReturn(['Code' => 2, 'Msg' => "订单号：{$_result['order_num']}，用户名：{$username} 付款失败，没有获取到【{$bankname}】银行编号。"]);
        }

        $now = time();
        $order_id = $_result['order_num'];
        $post_data = [
            "version" => "1.0.0", //*版本号
            "signMethod" => "MD5",//*签名类型
            "txnType" => "12", //*查询交易码，代付
            "txnSubType" => "01", //*交易子类型
            "bizType" => "000401", //产品类型，代付
            "accessType" => "0", //接入类型
            "accessMode" => "01", //接入方式
            "ppFlag" => "01", //对公对私标志
            'merId' => $this->config['anpay_pid'], //商户号-测试环境统一商户号
            'merOrderId' => $order_id, //商户订单号
            'txnTime' => date("YmdHis", $now), //订单发送时间：yyyyMMddHHmmss
            "txnAmt" => number_format($_result['money'], 2, '', ''),//交易金额(分)
            "currency" => "CNY",//交易积分类型
            "backUrl" => $this->host . U('PayCallBack/chinagpay'), //回调地址
            "bankId" => $bank_code,//银行编号
            "payType" => "0401",//支付方式
            "accNo" => trim($_result['cardnum']),//账号
            //银行卡验证信息及身份信息
            "customerInfo" => json_encode(['customerNm' => $username, 'iss_ins_name' => $bankname]),
            "subject" => "合约网提现代付",//商品标题
            "body" => "见标题",//商品描述
            // "merResv1"=>"一般不填",//请求保留域
        ];
        // 给array里面的值按照首字母排序,如果首字母一样看第二个字母   以此类推...
        ksort($post_data);

        // 加签key值
        $md5Key = $this->config['anpay_key'];
        $msg = $HttpClient->signMsg($post_data, $md5Key);
        //debug("组装字符串:" . $msg);
        //debug("MD5签名:".md5($msg,TRUE));

        // 获得签名值
        $signature = base64_encode(md5($msg, TRUE));
        $post_data["signature"] = $signature;
        //debug("BASE64签名值:" . $post_data["signature"]);
        $post_data = $HttpClient->reqBase64Keys_encode($post_data);

        $_url = 'http://remit.chinagpay.com/bas/BgTrans'; //生产
        //$_url = 'http://180.169.129.78:38280/bas/BgTrans'; //开发

        //发起代付请求
        $pageContents = $HttpClient->quickPost($_url, $post_data);
        //debug("返回信息:" . $pageContents);
        $respArray = $HttpClient->strToArr($pageContents);
        // 解码base64_decode（）
        $respArray = $HttpClient->reqBase64Keys_decode($respArray);
        // 给array里面的值按照首字母排序,如果首字母一样看第二个字母   以此类推...
        ksort($respArray);

        //存日志
        logs($post_data, 'pay_chinagpay_$post_data '.$order_id, '/payment/');
        logs($respArray, 'pay_chinagpay_end '.$order_id, '/payment/');

        $resMsg = $HttpClient->signMsg($respArray, $md5Key);
        //debug("获得返回签名值:".$resMsg);
        // 返回数据做签名  然后验签
        $resSing = base64_encode(md5($resMsg, TRUE));
        //debug("返回数据签名值:" . $resSing);
        //debug("支付平台签名值:" . $respArray["signature"]);

        $Member = isset($Member) ? $Member : M('member');
        $Withdraw = isset($Withdraw) ? $Withdraw : M('withdraw');
        $Withdraw_log = isset($Withdraw_log) ? $Withdraw_log : M('withdraw_log');
        $Withdraw_log_con = isset($Withdraw_log_con) ? $Withdraw_log_con : M('withdraw_log_content');

//        if(!in_array($respArray['respCode'], [0000, 1111, 1001, 1003, 1002])){
//            logs("订单号：{$_result['order_num']}，用户名：{$username} 付款失败，{$respArray['respMsg']}。", 'pay_chinagpay_api_err', '/payment/');
//            $this->ajaxReturn(['Code' => $respArray['respCode'], 'Msg' => $respArray['respMsg']]);
//        }

        //验签成功
        //if (strval($resSing) === strval($respArray["signature"])) {
            $pay_statue = 0;
            $state = 1;
            if(in_array($respArray['respCode'], [1001])){
                $pay_statue = 1;
                $state = 3;
            }
//            elseif(in_array($respArray['respCode'], [1003, 1002])){
//                $pay_statue = 2;
//                $state = 5;
//            }elseif(in_array($respArray['respCode'], [1111])){
//                $pay_statue = 0;
//                $state = 1;
//            }

            //保存付款记录
            $pay_log = [
                'batchNo' => $post_data['merOrderId'],
                'orderNo' => $post_data['merOrderId'],
                'state' => $state,
                'num' => 1,
                'totalAmount' => number_format($_result['all_money'], 2, '.', ''),
                'amount' => $post_data['txnAmt'],
                'poundage' => number_format($_result['withdraw_fee'], 2, '.', ''),
                'uptime' => $now,
            ];
            $insert_log = $Withdraw_log->add($pay_log);
            if ($insert_log) {
                $id = $Withdraw_log->getLastInsID();
                $pay_log_con = [
                    'log_id' => $id,
                    'accountName' => trim($_result['cardname']) ? $_result['cardname'] : $_result['username'],
                    'amount' => $post_data['txnAmt'],
                    'bankCode' => '',
                    'cardNumber' => $post_data['accNo'],
                    'orderNumber' => $post_data['merOrderId'],
                    'state' => $state,
                ];
                $Withdraw_log_con->add($pay_log_con);
            }

            //更新付款类型和付款状态
            //先把第三方付款状态 pay_statue 改为未付款状态   --***等待回调URL回馈  再更新为成功或失败***--
            $Withdraw->where(['withdraw_id' => $_result['order_id']])->save(['status' => '4', 'admin_uid' => session('admin_userid'), 'pay_time' => $now, 'pay_statue' => $pay_statue, 'pay_type' => $typeName, 'pay_order_id' => $order_id]);
        //}

        //推送到APP
        $jpush = A("Api/Jpush");
        $jpush->index('withdraw', 'suc', $_result['uid']);

        $this->ajaxReturn(['Code' => 1, 'Msg' => "付款提交成功，待第三方接口处理"]);
    }

    /**
     * 已付款订单状态同步
     */
    public function pay_sync()
    {
        $this->pay_type = I('pay_type', '');

        if (IS_AJAX && IS_POST) {
            $db = M('Withdraw');
            $where = [
                'a.status' => 4,
                'a.pay_statue' => ['gt', 0],
                'b.state' => ['in', [1, 2]],
            ];
            $field = "b.batchNo,b.num,b.amount,b.totalAmount,b.poundage";

            $_result = $db->alias('a')->join('inner join yang_withdraw_log_content c on a.order_num = c.orderNumber')->join('inner join yang_withdraw_log b on c.log_id = b.id')->where($where)->field($field)->group('batchNo')->select();
            debug($_result);

            if (empty($_result)) {
                $this->ajaxReturn(['Code' => 1, 'Msg' => '同步完成，没有可同步订单']);
            }

            $syncName = 'sync_' . $this->pay_type;

            if (!method_exists($this, $syncName)) {
                $this->ajaxReturn(['Code' => 0, 'Msg' => '同步完成，同步平台方法不存在']);
            }

            $this->$syncName($_result);
        } else {
            $this->display();
        }
    }

    private function sync_moneymoremore($_result)
    {
        //
    }

    /**
     * 参数拼接
     * @param array $sign_params
     * @return string
     */
    private function joinMapValue($sign_params = [])
    {
        $sign_str = "";
        //ksort($sign_params);
        foreach ($sign_params as $key => $val) {
            $sign_str .= sprintf("%s=%s&", $key, $val);
        }
        return substr($sign_str, 0, -1);
    }

    /**
     * 获取所有积分
     */
    public function get_currency()
    {
        $currency = M("currency")->where(['is_line' => 1])->field("currency_id,currency_name")->select();

        if (empty($currency)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '获取失败']);
        }

        $this->ajaxReturn(['Code' => 1, 'Msg' => json_encode($currency)]);
    }

    /**
     * 删除模板
     */
    public function delete_template()
    {
        $tpl_id = I("post.tpl_id");
        if (empty($tpl_id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '删除失败，请选择模板']);
        }

        M('withdraw_condition')->where(['id' => $tpl_id])->delete();
        M('withdraw_condition_item')->where(['pid' => $tpl_id])->delete();

        $this->ajaxReturn(['Code' => 1, 'Msg' => '删除成功']);
    }

    /**
     * 保存模板
     */
    public function save_template()
    {
        $data = I("post.", []);
        $db = M("withdraw_condition");

        $id = $data['id'];
        $name = $data['name'];
        $condition = $data['condition'];
        $condition_item = $data['condition_item'];

        $condition = implode(',', $condition);

        $withdraw_condition = [
            'name' => $name ? $name : '',
            'list' => $condition ? $condition : []
        ];

        $db->startTrans();
        if (empty($id)) {
            $save = $db->add($withdraw_condition);
            if ($save) {
                $id = $db->getLastInsID();
            }
        } else {
            $save = $db->where(['id' => $id])->save($withdraw_condition);
        }

        $save_item = true;
        if (!empty($condition_item)) {
            M('withdraw_condition_item')->where(['pid' => $id])->delete();
            foreach ($condition_item as $value) {
                $_withdraw_condition_item = [
                    'pid' => $id,
                    'key' => $value['key'],
                    'value' => $value['value'] ? $value['value'] : ''
                ];
                $save_item = M('withdraw_condition_item')->add($_withdraw_condition_item);
                if (!$save_item) {
                    $save_item = false;
                    break;
                }
            }
        }

        if ($save === false || !$save_item) {
            $db->rollback();
            $this->ajaxReturn(['Code' => 0, 'Msg' => '保存失败']);
        }

        $db->commit();
        $this->ajaxReturn(['Code' => 1, 'Msg' => '保存成功']);
    }

    /**
     * 一，判断提现金额不能大于充值总额（非管理员充值）的1.2倍
     * 二，判断是否黑名单
     * 三，充值到李俊成民生银行卡的不提
     * 四，只有原始钱，提现总额不能大于购积分总金额的2倍
     * 五，只有钱包，提现总额不大5万，（即不大于购积分总金额的2.5倍）
     * 六，有原始钱和钱包的，提现总额不大于购积分总金额的2倍
     * 七，赏金积分如无现金充值的不提
     * 八，G积分和乡野积分可任意提
     */
    public function review()
    {
        //获取页面提交的条件
        $post = I("post.", []);
        $condition = $post['tpl']['condition'];             //条件
        $condition_item = $post['tpl']['condition_item'];  //条件数据
        $list = $post['list'];                            //提现数据

        if (!empty($condition_item)) {
            $_tmp = [];
            foreach ($condition_item as $value) {
                $_tmp[$value['key']] = $value['value'];
            }
            $condition_item = $_tmp;
        }

        if (empty ($list)) {
            $datas['status'] = 0;
            $datas['info'] = "请选择提现记录";
            $this->ajaxReturn($datas);
        }

        //1，提现金额不能大于充值总额几倍
        if (in_array('condition_a', $condition)){
            if (empty($condition_item['con_1']) || !$condition_item['con_1'] > 0) {
                $datas['status'] = 0;
                $datas['info'] = "提现总额不大于现金充值总额的倍数";
                $this->ajaxReturn($datas);
            }
        }

        //4，只有原始钱，提现金额小于用户金额，提现金额小于购积分总金额的几倍
        if (in_array('condition_d', $condition)){
            if (empty($condition_item['con_2']) || !$condition_item['con_2'] > 0) {
                $datas['status'] = 0;
                $datas['info'] = "只有原始钱，提现金额不大于购积分总金额的倍数不能为空";
                $this->ajaxReturn($datas);
            }
        }

        //5，只有钱包积分，提现金额不大于五万，提积分金额不大于购积分总金额的几倍
        if (in_array('condition_e', $condition)) {
            if (empty($condition_item['con_3']) || !$condition_item['con_3'] > 0 || empty($condition_item['con_4']) || !$condition_item['con_4'] > 0) {
                $datas['status'] = 0;
                $datas['info'] = "只有钱包条件下的参数不完整";
                $this->ajaxReturn($datas);
            }
        }

        //6，有原始钱也有钱包，提现金额不大于购积分总金额的几倍
        if (in_array('condition_f', $condition)) {
            if (empty($condition_item['con_5']) || !$condition_item['con_5'] > 0) {
                $datas['status'] = 0;
                $datas['info'] = "有原始钱和钱包的，提现总额不大于购积分总金额的倍数不能为空";
                $this->ajaxReturn($datas);
            }
        }

        //7，特殊积分类型无现金充值任意提
        if (in_array('condition_g', $condition)) {
            $con_6 = explode(',', $condition_item['con_6']);
            if (empty($con_6)) {
                $datas['status'] = 0;
                $datas['info'] = "无现金充值的不提条件请选择积分类型";
                $this->ajaxReturn($datas);
            }
        }

        //对条件进行判断
        foreach ($list as $key => $withdraw_id) {
            //查询用户可用金额等信息
            $info = $this->getMoneyByid($withdraw_id);
            if ($info['status'] != 3) {                    //不是审核中状态的提现，中断继续下次循环
                continue;
            }

            $status = 2;                                    //定义默认审核状态

            if ($status === 2) {
                $arr = M('currency_user')->field('num, forzen_num')->where(array('member_id' => $info['member_id']))->select();
                foreach ($arr as $key => $val) {                            //积分的资产不能为负数
                    if ($val['num'] < 0 || $val['forzen_num'] < 0) {
                        $status = 1;
                        break;
                    }
                }
            }

            if($status === 2) {
                if ($info['rmb'] < 0 || $info['forzen_rmb'] < 0) {            //金额不能为负数
                    $status = 1;
                }
            }

            //只有钱包时的总金额（用户充值的钱）
            $where = ['yang_pay.member_id' => $info['member_id'], 'yang_pay.status' => 1];
            $totalcz = M('Pay')
                ->field('sum(count) as totalcount')
                ->join('left join yang_member on yang_member.member_id=yang_pay.member_id')
                ->where($where)
                ->where('yang_pay.currency_id=0')
                ->find();
            $qianbao_total = $totalcz['totalcount'];

            //只有原始钱的总金额（管理员充值的钱）
            $where = ['member_id' => $info['member_id'], 'type' => ['eq', 3], 'currency_id' => ['eq', 0]];
            $yuanshibi_total = M('pay')->field('sum(money) as total')->where($where)->find()['total'];

            //获取购积分总金额
            $total = 0;             //定义购积分总金额
            $where = ['uid' => $info['member_id']];
            $arr = M('issue_log')->field('num, original_price')->where($where)->select();
            foreach ($arr as $key => $val) {
                $total += $val['num'] * $val['original_price'];
            }

            if (in_array('condition_a', $condition) && $status === 2) {                  //1，提现金额不能大于充值总额几倍
                if(!empty($qianbao_total) && $qianbao_total > 0) {
                    // 获取提现总金额
                    $where = ['yang_bank.uid' => $info['member_id'], 'yang_withdraw.status' => ['in','2,4']];
                    $totaltx = M('bank')
                        ->field('sum(money) as totalmoney')
                        ->join("yang_withdraw ON yang_withdraw.bank_id = yang_bank.id")
                        ->join("yang_areas as b ON b.area_id = yang_bank.address")
                        ->join("yang_areas as a ON a.area_id = b.parent_id ")
                        ->join("yang_member as c on yang_withdraw.uid=c.member_id")
                        ->where($where)
                        ->find();
                    $tixian_total = $totaltx['totalmoney'];

                    // 获取倍数
                    $multiple = ($info['forzen_rmb'] + $tixian_total) / $qianbao_total;

                    //1，判断提现金额不能大于充值总额倍数
                    if ($multiple > $condition_item['con_1']) {
                        $status = 1;
                    }
                }
            }

            if (in_array('condition_b', $condition) && $status === 2) {                  //2，黑名单不提
                $re = M('blacklist')->field('id')->where(array('uid' => $info['member_id'], 'active' => 1, 'type' => 1))->find()['id'];
                if ($re) {
                    $status = 1;
                }
            }

            if (in_array('condition_c', $condition) && $status === 2) {                //3，充值到李俊成民生银行卡的不提
                $re = M('pay')->field('pay_id')->where(array('member_id' => $info['member_id'], 'account' => '6226220623106548'))->find()['pay_id'];
                if ($re) {
                    $status = 1;
                }
            }

            if (in_array('condition_d', $condition) && $status === 2) {                  //4，只有原始钱，提现金额小于购积分总金额的几倍
                if (empty($qianbao_total)) {
                    if ($info['all_money'] > $total * $condition_item['con_2']) {
                        $status = 1;
                    }
                }
            }

            if (in_array('condition_e', $condition) && $status === 2) {                  //5，只有钱包积分，提现金额不大于五万，提积分金额不大于购积分总金额的几倍
                if (empty($yuanshibi_total)) {
                    if ($info['all_money'] > $condition_item['con_3'] * 10000 || $info['all_money'] > $total * $condition_item['con_4']) {
                        $status = 1;
                    }
                }
            }

            if (in_array('condition_f', $condition) && $status === 2) {                  //6，有原始钱也有钱包，提现金额不大于购积分总金额的几倍
                if (!empty($yuanshibi_total) && !empty($qianbao_total)) {
                    if ($info['all_money'] > $total * $condition_item['con_5']) {
                        $status = 1;
                    }
                }
            }

            if (in_array('condition_g', $condition) && $status === 2) {                  //7，特殊积分类型无现金充值不提
                $con_6 = explode(',', $condition_item['con_6']);
                foreach ($con_6 as $key => $val) {
                    $sum_num = M('tibi')->field('sum(num) as sum_num')->where(array('user_id' => $info['member_id'], 'status' => 3, 'currency_id' => $val))->find()['sum_num'];
                    if ($sum_num > 0) {
                        if ($qianbao_total < 0) {
                            $status = 1;
                            break;
                        }
                    }
                }
            }

            if (in_array('condition_h', $condition) && $status === 2) {                  //8, 特殊积分类型任意提（非特殊积分无现金充值不提）
                $con_7 = explode(',', $condition_item['con_7']);

                $arr = M('currency')->field('currency_id')->select();
                foreach ($arr as $key => $val){
                    for($i = 0;$i < count($con_7);$i++) {
                        if ($val['currency_id'] == $con_7[$i]){
                            unset($arr[$key]);
                        }
                    }
                }

                foreach ($arr as $key => $val) {
                    $sum_num = M('tibi')->field('sum(num) as sum_num')->where(array('user_id' => $info['member_id'], 'status' => 3, 'currency_id' => $val['currency_id']))->find()['sum_num'];
                    if ($sum_num > 0) {
                        if ($qianbao_total < 0) {
                            $status = 1;
                            break;
                        }
                    }
                }
            }

            //所有条件都满足后通过
            $data ['withdraw_id'] = $withdraw_id;
            $data ['firstaudit_username'] = session('admin_userid');
            $data ['firstaudit_time'] = time();
            $data ['firstaudit_term'] = $status;
            //更新数据库
            M('Withdraw')->save($data);
        }
        $datas['status'] = 1;
        $datas['info'] = "自动审核完成";
        $this->ajaxReturn($datas);
    }

    /**
     * 获取提现金额信息
     * @param unknown $id
     * @return boolean|unknown $rmb 会员号，可用金额，冻结金额，手续费，提现金额
     */
    private function getMoneyByid($id)
    {

        $field = "yang_member.member_id,yang_member.rmb,yang_member.forzen_rmb,yang_withdraw.status,yang_withdraw.all_money,yang_withdraw.withdraw_fee,yang_withdraw.add_time";
        $rmb = M('Withdraw')
            ->field($field)
            ->join('yang_member ON yang_withdraw.uid = yang_member.member_id')
            ->where("withdraw_id = '{$id}'")
            ->find();
        if (empty($rmb)) {
            return false;
        }
        return $rmb;
    }
}