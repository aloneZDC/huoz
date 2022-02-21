<?php

namespace app\admin\controller;


use app\common\model\AccountBook;
use app\common\model\Currency as CurrencyModel;
use app\common\model\CurrencyLog;
use app\common\model\CurrencyTakeCoin;
use app\common\model\Recharge;
use app\common\model\Summary;
use app\common\model\TakePush;
use app\common\model\Tibi;
use Bitcoin;
use Kac;
use message\Btc;
use message\Eos;
use message\Eth;
use message\Xrp;
use QRcode;
use think\captcha\Captcha;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use Think\Page;
use think\Request;
use think\Response;
use think\response\Json;

class Currency extends Admin
{
    protected $currency;
    protected $currency2;

    public function _initialize()
    {
        parent::_initialize();
        $this->currency = Db::name('Currency');
//        $this->currency2 = M('Currency2');
    }

    //空操作
    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    public function repeal()
    {
        $where_repeal['limit_repeal'] = '1';
        $currency_repeal = Db::name("Currency")->where($where_repeal)->select();


        foreach ($currency_repeal as $value) {
            $repeal_id = $value['currency_id'];
            $where_orders['currency_id'] = $value['currency_id'];
            $where_orders['status'] = array('in', array(0, 1));

            $orders_repeal = Db::name("orders")->where($where_orders)->select();

            foreach ($orders_repeal as $rs) {

                $this->cancel($rs['orders_id'], $rs['member_id']);
            }

        }
    }

    public function repeal_one()
    {

        if (empty($_GET['currency_id'])) {
            $this->error('删除数据不存在');
        }


        $where_orders['currency_id'] = $_GET['currency_id'];
        $where_orders['status'] = array('in', array(0, 1));

        $orders_repeal = M("orders")->where($where_orders)->select();

        foreach ($orders_repeal as $rs) {

            $this->cancel($rs['orders_id'], $rs['member_id']);
        }


    }


    /**
     *  撤销方法
     */
    public function cancel($order_id, $member_id)
    {

        if (empty($order_id)) {
            $info['status'] = 0;
            $info['info'] = '撤销订单不正确';
            $this->ajaxReturn($info);
        }
        //获取人的一个订单
        $one_order = $this->getOneOrdersByMemberAndOrderId($member_id, $order_id, array(0, 1));
        if (empty($one_order)) {
            $info['status'] = -1;
            $info['info'] = '传入信息错误';
            $this->ajaxReturn($info);
        }
        $where['sell_orders'] = $order_id;
        $where['trade_status'] = '0';
        $have_order = M('Trade')->where($where)->find();

        if ($have_order) {
            $info['status'] = 8;
            $info['info'] = L('lan_and_the_exchange_memo');
            $this->ajaxReturn($info);

        }
        $info = $this->cancelOrdersByOrderId($one_order);
        //$this ->ajaxReturn($info);

    }

    /**
     * 返回指定用户挂单记录
     * @param int $member_id
     * @param int $order_id
     * @param array $status
     */
    protected function getOneOrdersByMemberAndOrderId($member_id, $order_id, $status = array(0, 1, 2, -1))
    {
        $where['member_id'] = $member_id;
        $where['orders_id'] = $order_id;
        $where['status'] = array('in', $status);
        $one_order = M('Orders')->where($where)->find();
        return $one_order;
    }

    /**
     * 设置订单状态
     * @param int $status 0 1 2 -1
     * @param int $orders_id 订单id
     * @return  boolean
     */
    protected function setOrdersStatusByOrdersId($status, $orders_id)
    {
        return M('Orders')->where(array('orders_id' => $orders_id))->setField('status', $status);
    }

    /**
     *撤销订单
     * @param int $list 订货单信息
     * @param int $member_id 用户id
     * @param int $order_id 订单号 id
     */
    protected function cancelOrdersByOrderId($one_order)
    {
        M()->startTrans();
        $r[] = $this->setOrdersStatusByOrdersId(-1, $one_order['orders_id']);

        //返还资金
        switch ($one_order['type']) {
            case 'buy':
                $money = ($one_order['num'] - $one_order['trade_num']) * $one_order['price'] * (1 + $one_order['fee']);
                $r[] = $this->setUserMoney($one_order['member_id'], $one_order['currency_trade_id'], $money, 'inc', 'num');
                $r[] = $this->setUserMoney($one_order['member_id'], $one_order['currency_trade_id'], $money, 'dec', 'forzen_num');
                break;
            case 'sell':
                $num = $one_order['num'] - $one_order['trade_num'];
                $r[] = $this->setUserMoney($one_order['member_id'], $one_order['currency_id'], $num, 'inc', 'num');

                $r[] = $this->setUserMoney($one_order['member_id'], $one_order['currency_id'], $num, 'dec', 'forzen_num');

                break;
        }

        //更新订单状态
        if (!in_array(false, $r)) {
            M()->commit();
            $info['status'] = 1;
            $info['info'] = L('lan_test_revocation_success');
            return $info;
        } else {
            M()->rollback();
            $info['status'] = -1;
            $info['info'] = L('lan_safe_image_upload_failure');

            return $info;
        }
    }


    /**
     * 生成地址
     */
    public function haveadress()
    {
        $curr = M("Currency")->select();
        $this->assign("curr", $curr);
        $this->display();
    }

    //获取个人账户指定积分类型金额
    public function getUserMoneyByCurrencyId($user, $currencyId)
    {
        return M('Currency_user')->field('num,forzen_num,chongzhi_url')->where("Member_id=$user and currency_id=$currencyId")->find();
    }

    /**
     * 获取新的一个钱包地址
     * @return unknown
     */
    private function qianbao_new_address($currency)
    {
        require_once 'App/Common/Common/easybitcoin.php';
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $user = $_SESSION['USER_KEY'];
        $address = $bitcoin->getnewaddress($user);

        return $address;
    }

    /**
     * 设置用户资金表 字段值
     * @param int $member_id 用户id
     * @param int $currenty_id 积分类型id
     * @param string $key 字段名称
     * @param string $value 字段值
     * @return  boolean 返回执行结果
     */
    protected function setCurrentyMemberByMemberId($member_id, $currenty_id, $key, $value)
    {
        return M("Currency_user")->where("member_id=$member_id and  currency_id=$currenty_id")->setField($key, $value);


    }

    /**
     * 自动生成地址
     */
    public function have_alladress()
    {

        $id = I('currency_id');//货币id
        if (empty($id)) {
            $this->error("请选择积分类型", U("Currency/haveadress"));
        }
        $currency = $this->getCurrencyByCurrencyId($id);

        if (empty($currency)) {
            $this->error("无效积分类型请联系管理员", U("Currency/haveadress"));
        }


        $currency_user = M("Currency_user")
            ->join(' yang_member  on yang_currency_user.member_id =yang_member.member_id')
            ->where("currency_id='$id' and chongzhi_url ='' and yang_member.status = 1 ")
            ->select();
        var_dump($currency_user);
        exit();
        foreach ($currency_user as $value) {
            $list = $this->getUserMoneyByCurrencyId($value['member_id'], $id);
            //设置充值地址
            if (empty($list['chongzhi_url'])) {
                $address = $this->qianbao_new_address($currency);
                $this->setCurrentyMemberByMemberId($value['member_id'], $id, 'chongzhi_url', $address);
                $list['chongzhi_url'] = $address;
            }
        }
        $this->display();

    }

    /**
     * 提积分记录
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
        if($hash) {
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
            'status' => ['in',[-2,-1,0,1] ],
            'add_time' => ['between',[$today_begin-86400,$today_begin]],
        ])->group('currency_id')->select();

        //昨日审核提币总数量
        $audit_total = Db::name('tibi')->field('currency_id,sum(num) as num,sum(actual) as actual')->where([
            'transfer_type' => '1',
            'status' => 1,
            'add_time' => ['between',[$today_begin-86400,$today_begin]],
        ])->group('currency_id')->select();
        $this->assign('tibi_total',$tibi_total);
        $this->assign('audit_total',$audit_total);

        $today_begin = todayBeginTimestamp();
        //今日提币总数量
        $tibi_total_today = Db::name('tibi')->field('currency_id,sum(num) as num,sum(actual) as actual')->where([
            'transfer_type' => '1',
            'status' => ['in',[-2,-1,0,1] ],
            'add_time' => ['egt',$today_begin],
        ])->group('currency_id')->select();
        $this->assign('tibi_total_today',$tibi_total_today);

        //充币未到账
        $xrp_currency_fail = Db::query("select * from (
select substring_index(ato,'_',1) as ato1,sum(amount) as amount from yang_currency_log where status=4  GROUP BY ato1) a 
where ato1 in (
	select waa_address from yang_wallet_admin_address where waa_type='recharge'
)");
        //充币到账
        $xrp_currency_success = Db::query("select * from (
select substring_index(ato,'_',1) as ato1,sum(amount) as amount from yang_currency_log where status=3  GROUP BY ato1) a 
where ato1 in (
	select waa_address from yang_wallet_admin_address where waa_type='recharge'
)");
        $this->assign('xrp_currency_fail',$xrp_currency_fail);
        $this->assign('xrp_currency_success',$xrp_currency_success);

        $list = Db::name("Tibi")->alias("tb")->field($field)->where($where)
            ->join(config("database.prefix") . "member m", "m.member_id=tb.from_member_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "c.currency_id=tb.currency_id", "LEFT")
            ->order("add_time desc")->paginate(20, null, ['query' => input()]);
        $show = $list->render();
        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出
        //读取积分类型表

        $curr = Db::name("Currency")->select();
        $curr = array_column($curr,null,'currency_id');
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

    //无忧宝提积分
    public function wyb_tibi($dizi, $num)
    {
        $where_currency['currency_id'] = '50';
        $where_currency['currency_mark'] = 'WYC';
        $Currency_message = M('Currency2')->$where($where_currency)->find();
        $zywybid = '7f48028c38117ac9e42c8e1f6f06ae027cdbb904eaf1a0bdc30c9d81694e045c';
        $url = $url = 'http://' . $Currency_message['rpc_url'] . ':' . $Currency_message['port_number'] . '';;
        // $url = 'http://'.$currency_message['rpc_url'].':'.$currency_message['port_number'].'';
        $ch = curl_init();
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; rv:12.0) Gecko/20100101 Firefox/12.0',
            CURLOPT_RETURNTRANSFER => 1,
        );
        curl_setopt_array($ch, $options);
        $postone = '{"jsonrpc":"2.0","method":"sendtoaddress","params":["' . $zywybid . '","' . $dizi . '",' . $num . '],"id":50}';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postone);
        $retary = json_decode(curl_exec($ch), true);

        if (isset($retary['result']) && $retary['id'] == 50) {
            return $retary['result']['txid'];
        } else {
            return false;
        }
    }

    /**
     * 充值重复列表
     */
    public function repeat_chongzhi()
    {
        $model = M('tibi');
        $result = $model->query("SELECT ti_id FROM `yang_tibi` GROUP BY ti_id,`add_time`,num  HAVING COUNT(ti_id) > 1");
        $show = [];

        $where = [];

        $state = I('get.state', '', 'trim');
        if (!empty($state) && in_array($state, [2, 1])) {
            $where['a_status'] = $state;
        }

        $keyid = I('get.keyid', '', 'trim');
        if (!empty($keyid)) {
            $where['user_id'] = $keyid;
        }

        $url = I('get.url', '', 'trim');
        if (!empty($url)) {
            $where['url'] = $url;
        }

        $ti_id = I('get.ti_id', '', 'trim');
        if (!empty($ti_id)) {
            $where['ti_id'] = $ti_id;
        }

        if (!empty($result)) {
            $order_id = [];
            foreach ($result as $value) {
                if (!empty($value['ti_id']) && !in_array($value['ti_id'], $order_id)) {
                    $order_id[] = $value['ti_id'];
                }
            }

            if (!empty($order_id)) {
                $order_id = implode(',', $order_id);

                $where['ti_id'] = ['in', $order_id];
                $where['status'] = 3;
                $where['fee'] = 0.0000;

                $count = $model->where($where)->count();
                $Page = new Page ($count, 20);
                // 分页显示输出
                $show = $Page->show();

                $result = $model->field("id,ti_id,num,user_id,url,add_time,a_status,a_remark")->where($where)->order("id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
            }
        }

        $this->assign('state', $state);
        $this->assign('keyid', $keyid);
        $this->assign('url', $url);
        $this->assign('ti_id', $ti_id);

        $this->assign('result', $result);
        $this->assign('page', $show);
        $this->display();
    }

    /**
     * 重复订单--修改备注信息
     */
    public function repeat_save_remark()
    {
        $id = I("post.id", '');
        $remark = I("post.text", '');

        if (empty($id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '订单不存在']);
        }

        if (!empty($remark)) {
            $db = M('tibi');
            $update = $db->where(['id' => $id])->save(['a_remark' => $remark]);
            if ($update === false) {
                $this->ajaxReturn(['Code' => 0, 'Msg' => '保存失败']);
            }
        }

        $this->ajaxReturn(['Code' => 1, 'Msg' => '保存成功']);
    }

    /**
     * 重复订单--确认处理
     */
    public function repeat_save_status()
    {
        $id = I("post.id", '');

        if (empty($id)) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '订单不存在']);
        }

        $db = M('tibi');
        $update = $db->where(['id' => $id])->save(['a_status' => 1]);
        if ($update === false) {
            $this->ajaxReturn(['Code' => 0, 'Msg' => '修改失败']);
        }

        $this->ajaxReturn(['Code' => 1, 'Msg' => '修改成功']);
    }

    /**
     * 充值记录
     */
    public function chongzhi_index()
    {

        if (input('mit')) {
            $message1 = input('message1');
            $id = input('id');
            $condition['id'] = $id;
            $data['message1'] = $message1;
            $data['admin_id1'] = session("admin_userid");
            //判断是否有数据
            $find_data = Db::name('tibi')->where($condition)->find();
            //判断是否已在审核2提交
            if (session("admin_userid") > 1) {
                if ($find_data['admin_id2'] == session("admin_userid")) {
                    return $this->error('已在审核2提交，不可重复提交');
                }
            }
            //判断是否已经有数据
            if ($find_data['message1']) {
                if (session("admin_userid") == 1) {
                    $rs = Db::name('tibi')->where($condition)->update($data);
                } else {
                    return $this->error('没有权限更改数据');
                }
            } else {
                $rs = Db::name('tibi')->where($condition)->update($data);
            }
            if ($rs != false) {
                return $this->success('提交成功');
            } else {
                return $this->error('提交失败');
            }
        }

        if (input('mit2')) {
            $message2 = input('message2');
            $id = input('id');
            $condition['id'] = $id;
            $data2['message2'] = $message2;
            $data2['admin_id2'] = session("admin_userid");
            //判断是否有数据
            $find_data = Db::name('tibi')->where($condition)->find();
            //判断是否已在审核2提交
            if (session("admin_userid") > 1) {
                if ($find_data['admin_id1'] == session("admin_userid")) {
                    return $this->error('已在审核1提交，不可重复提交');
                }
            }
            //判断是否已经有数据
            if ($find_data['message2']) {
                if (session("admin_userid") == 1) {
                    $rs = Db::name('tibi')->where($condition)->update($data2);
                } else {
                    return $this->error('没有权限更改数据');
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

        $cuid = input('cuid');

        $phone = input('phone');
        $email = input('email');
        $member_id = input('member_id');
        $status = input('status');
        $url = input('url');

        if (!empty($cuid)) {
//            if ($cuid == 1) {
//
//            } else {
            $where['tb.currency_id'] = input("cuid");
//            }
            $this->assign("id", input("cuid"));
        }
//        if (!empty($cuid)) {
//            $name = M("Member")->where("email='{$email}'")->find();
//            //$where['yang_tibi.user_id']=$name['member_id'];
//        }
//        $where['yang_tibi.status'] = array("in", array(2, 3, 4, 5));
        $where['tb.status'] = 3;
        $where['tb.b_type'] = 0;
        $where['tb.transfer_type'] = "1";//区块链充币类型
//        $where['tb.to_member_id'] = array("exp", "is not null");
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
        $field = "tb.*,m.email,m.member_id,m.name,m.phone,c.currency_name,c.currency_type,m.remarks";
        $list = Db::name("Tibi")->alias("tb")->field($field)->where($where)->where("tb.to_member_id", "exp", "is not null")
            ->join(config("database.prefix") . "member m", "m.member_id=tb.to_member_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "c.currency_id=tb.currency_id", "LEFT")
            ->order("add_time desc")->paginate(20, null, ['query' => input()]);
        $show = $list->render();
        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出


        //读取积分类型表

        $curr = Db::name("Currency")->select();
        $curr = array_column($curr,null,'currency_id');
        $this->assign("curr", $curr);
        $temp['cuid'] = $cuid;
        $temp['member_id'] = $member_id;
        $temp['status'] = $status;
        $temp['phone'] = $phone;
        $temp['email'] = $email;
        $temp['url'] = $url;
        $this->assign("temp", $temp);

        //XRP昨日充值总数量
        $today = todayBeginTimestamp();
        $chong_sum = Tibi::where([
            'status' => 3,
            'add_time' =>['between',[$today-86400,$today-1]]
        ])->field('currency_id,sum(actual) as actual')->group('currency_id')->select();
        $this->assign("chong_sum", $chong_sum);

        $today_chong_sum = Tibi::where([
            'status' => 3,
            'add_time' =>['egt',$today]
        ])->field('currency_id,sum(actual) as actual')->group('currency_id')->select();
        $this->assign("today_chong_sum", $today_chong_sum);
        return $this->fetch();
    }

    Public function add()
    {
        if ($_POST) {
            if (!empty($_POST['currency_id'])) {
                $_POST['currency_id'];
                $cu = Db::name("Currency")->where("currency_name='{$_POST['currency_name']}' and currency_id <> '{$_POST['currency_id']}'")->find();
            } else {
                $cu = Db::name("Currency")->where("currency_name='{$_POST['currency_name']}'")->find();
            }
            /*if (!empty($cu)) {
                return $this->error("积分类型名称已经存在");
            }*/

            foreach ($_POST as $k => $v) {
                $data[$k] = $v;
            }
            $data['add_time'] = time();
            if ($_FILES["Filedata"]["tmp_name"]) {
                $upload = $this->oss_upload($file = [], $path = 'currency_logo');
                if (empty($upload)) {
                    $this->error('图片上传失败');
                }
                $data['currency_logo'] = trim($upload['Filedata']);  //保存路径到数据库
            }
            if (isset($data['trade_currency_id'])) {
                $data['trade_currency_id'] = implode(',', $data['trade_currency_id']);
            }
            if (!empty($_POST['currency_id'])) {
                $rs = Db::name("Currency")->where(['currency_id' => $_POST['currency_id']])->update($data);
                $currency_id = $data['currency_id'];
            } else {
                $rs = Db::name("Currency")->insertGetId($data);
                $currency_id = $rs;
            }
            if ($rs) {
                return $this->success("操作成功");
            } else {
                return $this->error('操作失败');
            }
        } else {
            $currency_id = input("currency_id");
            $list = null;
            if (!empty($currency_id)) {
                $list = $this->currency->where('currency_id=' . $currency_id)->find();

            }
            $this->assign('list', $list);
            $currency_currency = Db::name("Currency")->where("currency_id <> '{$currency_id}'")->select();
            $this->assign("currency_currency", $currency_currency);
            $this->assign('public_chain',[
                'currency_id' => \app\common\model\Currency::PUBLIC_CHAIN_ID,
                'currency_name' => \app\common\model\Currency::PUBLIC_CHAIN_NAME,
            ]);
            return $this->fetch();
        }
    }

    public function link()
    {
        $currency_id = I('currency_id');
        if (empty($currency_id)) {
            $this->error("请在 操作->修改 按钮中添加链接", U('Currency/index'));
        } else {
            $Link = M('Currency_introduce_url'); // 实例化User对象
            $count = $Link->where('currency_id=' . $currency_id)->count();// 查询满足要求的总记录数
            $Page = new Page($count, 25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
            $show = $Page->show();// 分页显示输出
            // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
            $list = $Link->where('currency_id=' . $currency_id)->order('currency_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
            $this->assign('list', $list);// 赋值数据集
            $this->assign('page', $show);// 赋值分页输出
            $this->display();
        }

    }

    public function link_add()
    {
        $currency_id = I('currency_id');
        $url_id = I('url_id');
        if (!empty($url_id)) {
            $list = M("Currency_introduce_url")->where("url_id='$url_id'")->find();
            $this->assign("list", $list);
        }
        $this->assign('currency_id', $currency_id);
        $this->display();
    }

    public function link_save()
    {
        if (empty($_POST['url_name'])) {
            $this->error("请输入链接名称");
        }
        if (empty($_POST['url_path'])) {
            $this->error("请输入链接地址");
        }
        $url_id = I('url_id');

        if (!empty(I('currencyId'))) {
            $data['currency_id'] = I('currencyId');
        } else {
            $data['currency_id'] = I('currency_id');
        }
        $data['url_name'] = I('url_name');
        $data['url_path'] = I('url_path');
        if (empty($url_id)) {
            $re = M("Currency_introduce_url")->add($data);
            if ($re) {
                $this->success("添加成功", U('Currency/link', array('currency_id' => $_POST['currency_id'])));
            } else {
                $this->error("添加失败");
            }
        } else {
            $re = M("Currency_introduce_url")->where("url_id='$url_id'")->save($data);
            if ($re) {
                $this->success("修改成功", U('Currency/link', array('currency_id' => $_POST['currencyId'])));
            } else {
                $this->error("修改失败");
            }
        }

    }

    public function link_del()
    {
        $url_id = I('url_id');
        if (empty($url_id)) {
            $this->error("无效参数，无法删除");
        }
        $re = M("currency_introduce_url")->where("url_id='$url_id'")->delete();
        if ($re) {
            $this->success("删除成功", U("currency/index"));
        } else {
            $this->error("删除失败");
        }

    }

    public function detail_para()
    {
        $id = input('currency_id');
        $lang = input('lang', 'zh');
        $Currency_introduce = Db::name('Currency_introduce');

        $Currency_introduce_en = Db::name('Currency_introduce_en');
        $Currency_introduce_tc = Db::name('Currency_introduce_tc');
        $Currency_introduce_th = Db::name('Currency_introduce_th');
        $pd = Db::name('Currency_introduce')->where("currency_id='$id'")->count();
        if ($_POST) {
            $currencyId = $_POST['currency_id'];
            foreach ($_POST as $k => $v) {
                $data[$k] = $v;
                unset($data['lang']);
            }

            if ($lang === 'zh') {
                $data['feature'] = input('feature');//主要特色
                $data['short'] = input('short');//不足之处
                $data['advantage'] = input('advantage');//综合优势
            }
            if ($lang === 'tc') {
                $data1['feature'] = input('tc_feature');//主要特色
                $data1['short'] = input('tc_short');//不足之处
                $data1['advantage'] = input('tc_advantage');//综合优势
                $tc_find = $Currency_introduce_tc->where("currency_id='$currencyId'")->find();
                $data1['currency_id'] = $currencyId;//ID
                if ($currencyId) {
                    if (empty($tc_find)) {
                        $Currency_introduce_tc->insertGetId($data1);
                    } else {
                        $Currency_introduce_tc->update($data1);
                    }
                }
            }
            if ($lang === 'en') {
                $data2['feature'] = input('en_feature');//主要特色
                $data2['short'] = input('en_short');//不足之处
                $data2['advantage'] = input('en_advantage');//综合优势
                $en_find = $Currency_introduce_en->where("currency_id='$currencyId'")->find();
                $data2['currency_id'] = $currencyId;//ID
                if ($currencyId) {
                    if (empty($en_find)) {
                        $Currency_introduce_en->insertGetId($data2);
                    } else {
                        $Currency_introduce_en->update($data2);
                    }
                }
            }
            if ($lang === 'th') {
                $data2['feature'] = input('en_feature');//主要特色
                $data2['short'] = input('en_short');//不足之处
                $data2['advantage'] = input('en_advantage');//综合优势
                $th_find = $Currency_introduce_th->where("currency_id='$currencyId'")->find();
                $data2['currency_id'] = $currencyId;//ID
                if ($currencyId) {
                    if (empty($th_find)) {
                        $Currency_introduce_th->insertGetId($data2);
                    } else {
                        $Currency_introduce_th->update($data2);
                    }
                }
            }
            $Currency_inuce = $Currency_introduce->where("currency_id='$currencyId'")->find();
//            if (!empty($currencyId)) {
            if (!empty($Currency_inuce)) {
                $rs = $Currency_introduce->where("currency_id='{$currencyId}'")->update($data);
            } else {
                $rs = Db::name('Currency_introduce')->insertGetId($data);
            }
            if ($rs === false) {
                $this->error('操作失败');
            } else {
                $this->success("操作成功", url("currency/detail_index"));
            }
        } else {
            $currency_id = input('currency_id');
            if (!empty($currency_id)) {
                $list = Db::name('Currency_introduce')->alias("ci")->where('ci.currency_id=' . $currency_id)
                    ->field("ci.*,tc.feature as tc_feature,tc.short as tc_short,tc.advantage as tc_advantage,
                    en.feature as en_feature,en.short as en_short,en.advantage as en_advantage,
                    th.feature as th_feature,th.short as th_short,th.advantage as th_advantage")
                    ->join(config("database.prefix") . "currency_introduce_tc tc", "tc.currency_id=ci.currency_id", "LEFT")
                    ->join(config("database.prefix") . "currency_introduce_th th", "th.currency_id=ci.currency_id", "LEFT")
                    ->join(config("database.prefix") . "currency_introduce_en en", "en.currency_id=ci.currency_id", "LEFT")
                    ->find();
                $this->assign('currency_id', $currency_id);
                $this->assign('list', $list);
            }
        }
        $this->assign("pd", $pd);
        return $this->fetch();
    }


    public function index()
    {
        $list = $this->currency->select();
        $this->assign('list', $list);
        $this->assign('empty', '暂无数据');
        return $this->fetch();
    }

    public function detail_index()
    {
        $list = $this->currency->select();
        $this->assign('list', $list);
        $this->assign('empty', '暂无数据');
        return $this->fetch();
    }

    public function shangxian()
    {
        if (!empty($_GET['currency_id'])) {
            $rs = M('Currency')->where('currency_id=' . $_GET['currency_id'])->setField('is_lock', 0);
        }
        if ($rs) {
            $this->success('操作成功');
        } else {
            $this->error('操作失败');
        }
    }

    //删除的时候，要判断个人账户下有没有钱
    public function del()
    {
        $currency_id = input("currency_id");
        if (empty($currency_id)) {
            $this->error('删除数据不存在');
        }
        $rs = Db::name('Currency_user')->where('currency_id=' . $currency_id)->find();

        if ($rs) {
            return $this->error('该积分类型尚有用户持有，不能删除');
        }

        $list = Db::name("Currency")->where(['currency_id' => $currency_id])->delete();
        if ($list) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    //删除对应的积分类型详情图片
    public function delCurrencyPic()
    {
        $id = I('get.id');
        $list = M('Currency_pic')->where('id=' . $id)->delete();
        if ($list) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }


    /**
     * 给某个用户钱包转账
     */
    public function set_member_currencyForQianbao()
    {
        $cuid = intval(I("cuid"));
        if (empty($cuid)) {
            $this->error("无效货币参数", U("Currency/index"));
            exit();
        }
        $currency = M("Currency")->where("currency_id='$cuid'")->find();

        $currency['balance'] = $this->get_qianbao_balance($currency);

        if (empty($currency)) {
            $this->error("无效货币", U("Currency/index"));
            exit();
        }
        if (IS_POST) {

            $username = I("name");//用户名
            $num = I('num');//数量
            if (empty($username)) {
                $this->error("请输入用户名");
                exit();
            }
            if (empty($num) || !is_numeric($num)) {
                $this->error("数量请输入数字类型");
                exit();
            }
            $member = M("Member")->where("email='$username'")->find();
            if (empty($member)) {
                $this->error("查无此人，请核实");
                exit();
            }

            $qa = M("Qianbao_address")->where("user_id='{$member['member_id']}' and currency_id='{$cuid}'")->find();
            if (empty($qa['qianbao_url'])) {
                $this->error("此用户没有绑定提积分地址，无法转账");
                exit();
            }
            //判断看这个钱包地址是否是真实地址
            if (!$this->check_qianbao_address($qa['qianbao_url'], $currency)) {
                $this->error("提积分地址不是一个有效地址");
                exit();
            }
            $num = floatval($num);
            $data['fee'] = 0;//手续费
            $data['currency_id'] = $cuid;
            $data['user_id'] = $qa['user_id'];
            $data['url'] = $qa['qianbao_url'];
            $data['name'] = $qa['name'];
            $data['num'] = $num;
            $data['actual'] = $num;//实际到账价格
            $data['status'] = 0;
            $data['add_time'] = time();

            $tibi = $this->qianbao_tibi($qa['qianbao_url'], $num, $currency);//提积分程序

            if ($tibi) {//成功写入数据库
                $data['ti_id'] = $tibi;
                $re = M("Tibi")->add($data);
                //减钱操作
//     			M("Currency_user")->where("member_id='{$_SESSION['USER_KEY_ID']}' and currency_id='$cuid'")->setDec("num",$num);
                $this->success("转账成功，请耐心等待", U('Currency/index'));
                exit();

            } else {//失败提示
                $this->error("转账失败");
                exit();
            }
        }

        $this->assign("currency", $currency);
        $this->display();
    }

    /**
     * 检测地址是否是有效地址
     *
     * @param unknown $url
     * @param $port_number 端口号 来区分不同的钱包
     * @return boolean 如果成功返回个true
     * @return boolean 如果失败返回个false；
     */

    private function check_qianbao_address($url, $currency)
    {
        require_once 'App/Common/Common/easybitcoin.php';
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $address = $bitcoin->validateaddress($url);
        if ($address['isvalid']) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 提积分引用的方法
     * @param unknown $url 钱包地址
     * @param unknown $money 提积分数量
     */
    private function qianbao_tibi($url, $money, $currency)
    {
        require_once 'App/Common/Common/easybitcoin.php';
        $bitcoin = new Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $bitcoin->walletlock();//强制上锁
        $bitcoin->walletpassphrase($currency['qianbao_key'], 20);
        $id = $bitcoin->sendtoaddress($url, $money);
        $bitcoin->walletlock();
        return $id;
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
        $where['yang_tibi.status'] = array("in", array(0, 1));
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
     * 通过审核提币
     * Created by Red.
     * Date: 2018/10/18 11:56
     */
    Public function successByid()
    {
        if ($_POST) {
            $r['code'] = ERROR1;
            $param = input('post.');
            if (empty($param['id'])) {
                $this->error("数据错误");
            }
            if (isset($param['captcha']) and !captcha_check($param['captcha'])) {
                $r['message'] = "亲，验证码输错了哦！";
                return $this->ajaxReturn($r);
            }
            $condition['id'] = $param['id'];
            $find_data = Db::name('tibi')->where($condition)->find();

            if ((empty($find_data['message1'])) and 1 == $find_data['transfer_type']) {
                return $this->ajaxReturn(['code' => ERROR1, 'message' => "请先审核后提交"]);
            }
            $result = Tibi::applyTransfer($param['id'], session("admin_userid"));
            return $this->ajaxReturn($result);
        }

    }

    Public function falseByid()
    {
        $id = $_POST['id'];
        if (empty($id)) {
            return $this->ajaxReturn(['code' => ERROR1, 'message' => '参数错误']);
        }
        $result = Tibi::rebut($id, session("admin_userid"));
        return $this->ajaxReturn($result);
    }
//
//    Public function chongByid()
//    {
//        $id = $_POST['id'];
//        if (empty($id)) {
//            $this->error("数据错误");
//        }
//        $tibi = D('Tibi');
//        if (!$res = $tibi->create()) {
//            $arr['status'] = 9;
//            $arr['info'] = $tibi->getError();
//        }
//        $result = $tibi->suRecharge($id);
//        $this->ajaxReturn($result);
//    }

    /**
     * 以太坊和Token人工汇总
     */
    public function eth_collect()
    {
        //取出以太坊和Token英文缩写
        $currency = $this->currency->field("currency_id,currency_name,currency_mark,qianbao_address as chongbi_address,tibi_address,token_address")->where(['currency_type' => ['in', 'eth,eth_token']])->select();

        $balance_list = [];
        $mark_list = [];
        $cid = "";
        if (!empty($currency)) {
            foreach ($currency as $value) {
                $mark_list[] = $value['currency_mark']; //缩写加入集合
                $cid[] = $value['currency_id']; //币ID加入集合
            }
        }

        if (!empty($mark_list)) {
            $cid = implode(',', $cid);
            $fields = "currency_user.member_id,currency_user.currency_id,currency_user.num,currency_user.chongzhi_url";
            $result = M('currency_user')->alias('currency_user')->field($fields)->where(['currency_user.num' => ['gt', 0], 'currency_id' => ['in', $cid]])->select(); //取出以太坊或者Token在平台余额大于0的用户

            $list = [];
            if (!empty($result)) {
                foreach ($result as &$value) {
                    $list[$value['member_id']] = $value['chongzhi_url'];
                }
            }

            foreach ($list as $member_id => $address) {
                //获取钱包地址网络余额信息
                $balance = $this->get_eth_balance($address, $mark_list);

                if (!empty($balance['is'])) { //如果币种余额有大于0的，就是最终需要汇总的信息
                    $balance_list[] = [
                        'member_id' => $member_id,
                        'address' => $address,
                        'currency' => $balance['num'],
                    ];
                }
            }
            //debug($balance_list);
        }

        $this->assign('list', $balance_list);
        $this->assign('cur_count', count($mark_list));
        $this->display();
    }

    /**
     * 取用户以太坊和代币余额
     * @param $address
     * @param array $currency_mark
     * @return array
     */
    private function get_eth_balance($address, $currency_mark = [])
    {
        $balance = [];
        if (parent::isValidAddress($address)) {
            $address_info = @_curl("https://api.ethplorer.io/getAddressInfo/{$address}?apiKey=freekey", [], false, 'get');
            $address_info = @json_decode($address_info, true);

            if (!empty($address_info) && !empty($currency_mark)) {
                $_balance = [];

                $_balance['ETH'] = $address_info['ETH']['balance']; //ETH余额
                foreach ($address_info['tokens'] as $tokens) {
                    $_balance[$tokens['tokenInfo']['symbol']] = $tokens['balance'] / pow(10, intval($tokens['tokenInfo']['decimals'])); //Token余额
                }

                foreach ($currency_mark as $value) {
                    $balance['num'][$value] = isset($_balance[$value]) ? $_balance[$value] : 0; //ETH和Token的余额

                    if (isset($_balance[$value]) && $_balance[$value] > 0) { //如果有用户ETH或者Token真实余额大于0，则加入这个集合
                        $balance['is'][$value] = $_balance[$value];
                    }
                }
            }
        }

        return $balance;
    }

    /**
     * 查询以太坊和代币手续费
     */
    public function get_transactions_fee()
    {
        $currency = $this->currency->field("currency_id,currency_name,currency_mark,token_address,qianbao_address")->where(['currency_type' => ['in', 'eth,eth_token']])->select();

        $mark_list = [];
        $token_list = [];
        $chongbi_list = [];
        if (!empty($currency)) {
            foreach ($currency as $value) {
                if (strtolower($value['currency_mark']) !== 'eth') {
                    $mark_list[$value['currency_mark']] = $value['currency_name']; //Token代号和名称
                    $token_list[$value['currency_mark']] = $value['token_address']; //Token代号和合约地址
                }
                $chongbi_list[$value['currency_mark']] = $value['qianbao_address']; //Token代号和提币地址
            }
        }

        $this->assign('mark_list', $mark_list);
        $this->assign('token_list', json_encode($token_list));
        $this->assign('chongbi_list', json_encode($chongbi_list));
        $this->display('eth_transactions_fee');
    }

    /**
     * ajax取以太坊和代币手续费
     */
    public function ajax_transactions_fee()
    {
        try {
            $currency = $this->currency->field("rpc_url,port_number,qianbao_address")->where(['currency_type' => 'eth'])->find();

            $data = I('post.data', []);
            $gas = 21000;
            $gasPrice = 0;

            $data['to'] = $currency['qianbao_address'];

            $mark = ['ETH'];
            if ($data['agreement'] == 'token') {
                $mark = ['ETH', $data['mark']];
            }
            unset($data['mark']);

            $balance = $this->get_eth_balance($data['from'], $mark); //取账地地址的余额
            $eth_balance = @$balance['num'][$mark[0]]; //转账地址内的ETH余额

            //取当前转账所需的 gasPrice
            $gasPrice_data = ['method' => 'eth_gasPrice'];
            $result = @self::_ethereum($currency, $gasPrice_data);
            if (isset($result['result']['result']['number']) && $result['result']['result']['number'] > 0) $gasPrice = $result['result']['result']['number'];

            if (!($gasPrice > 0)) {
                $this->ajaxReturn(['code' => 0, 'msg' => "没有获取到gasPrice，请重试"]);
            }

            //为了转账稳定性，gasPrice不小于10 Gwei
            if ($gasPrice < 10000000000) {
                $gasPrice = 10000000000;
            }

            //token 转账需要的预估 gas
            if ($data['agreement'] == 'token') {
                $data['method'] = 'token_estimateGas';
                $_gas = 0;

                $result = @self::_ethereum($currency, $data);

                if (isset($result['code']) && $result['code'] == '-32000') $this->ajaxReturn(['code' => 0, 'msg' => "发送的地址内代币余额不足，请换个有余额的地址再预估"]);

                if (isset($result['result']['number']) && $result['result']['number'] > 0) $_gas = $result['result']['number'];

                if (!($_gas > 0)) {
                    $this->ajaxReturn(['code' => 0, 'msg' => "没有获取到代币Gas，请重试"]);
                }
                $gas = $_gas;
            }

            //转账需要的手续费
            $fee = floatval(($gas * $gasPrice) / 1000000000000000000);

            //当前eth余额是否足够支付手续费
            $x = (($fee - $eth_balance) <= 0) ? 0 : ($fee - $eth_balance);
            //$text = "手续费：" . $fee . " 个ETH / 当前余额：" . $eth_balance . " 个ETH";

            $this->ajaxReturn(['code' => 1, 'msg' => [$fee, $eth_balance, $x]]); //手续费、eth余额、如果手续费不足，需要充值的eth
        } catch (\Exception $exception) {
            $this->ajaxReturn(['code' => 0, 'msg' => "获取失败 " . $exception->getMessage()]);
        }
    }

    /**
     * ajax发送转账（充币汇总）
     */
    public function ajax_transactions_send()
    {
        try {
            $currency = $this->currency->field("rpc_url,port_number")->where(['currency_type' => 'eth'])->find();

            $data = I('post.data', []);
            $gas = 21000;
            $gasPrice = 0;

            //从数据库取出来的汇总接收地址
            if (!self::isValidAddress($data['to'])) $this->ajaxReturn(['code' => 0, 'msg' => "收币钱包地址不正确"]);

            $mark = ['ETH'];
            $_mark = $data['mark'];
            if ($data['agreement'] == 'token') {
                $mark = ['ETH', $data['mark']];
            }
            unset($data['mark']);

            //取当前转账所需的 gasPrice（ETH和Token是一样的）
            $gasPrice_data = ['method' => 'eth_gasPrice'];
            $result = @self::_ethereum($currency, $gasPrice_data);
            if (isset($result['result']['result']['number']) && $result['result']['result']['number'] > 0) $gasPrice = $result['result']['result']['number'];

            if (!($gasPrice > 0)) {
                $this->ajaxReturn(['code' => 0, 'msg' => "没有获取到gasPrice，请重试"]);
            }

            //为了转账稳定性，gasPrice不小于10 Gwei
            if ($gasPrice < 10000000000) {
                $gasPrice = 10000000000;
            }

            //token 转账需要的预估 gas
            if ($data['agreement'] == 'token') {
                $data['method'] = 'token_estimateGas';
                $_gas = 0;

                $result = @self::_ethereum($currency, $data);

                if (isset($result['code']) && $result['code'] == '-32000') $this->ajaxReturn(['code' => 0, 'msg' => "发送的地址的代币余额不足，请检查"]);

                if (isset($result['result']['number']) && $result['result']['number'] > 0) $_gas = $result['result']['number'];

                if (!($_gas > 0)) {
                    $this->ajaxReturn(['code' => 0, 'msg' => "没有获取到代币Gas，请重试"]);
                }
                $gas = $_gas;
            }

            //转账需要的手续费
            $fee = floatval(($gas * $gasPrice) / 1000000000000000000);

            $balance = $this->get_eth_balance($data['from'], $mark); //取转账地址的余额
            $eth_balance = @$balance['num'][$mark[0]]; //转账地址内的ETH余额

            if (!($eth_balance >= $fee)) $this->ajaxReturn(['code' => 0, 'msg' => "以太坊手续费不足，请先充值"]);

            $actual = $data['value'] - $fee;

            //Token转账
            if ($data['agreement'] == 'token') {
                $token_balance = @$balance['num'][$mark[1]]; //转账地址内的Token余额
                if (!($token_balance >= $data['value'])) $this->ajaxReturn(['code' => 0, 'msg' => $_mark . "余额不足，请先充值"]);
                $actual = $data['value'];
            }

            $pass = "QIXS9EvR5h2L"; //普通地址的密码
            $transaction_hash = false;
            if ($data['agreement'] == 'eth') {
                //ETH提币
                $transaction_hash = self::eth_transaction($data['to'], (float)$actual, $currency, $pass, $data['from'], $gasPrice, $gas);
            } elseif ($data['agreement'] == 'token') {
                //Token提币
                $transaction_hash = self::eth_transaction($data['to'], (float)$actual, $currency, $pass, $data['from'], $gasPrice, $gas, "token_sendTransaction", null, null, "token", $data['token_address']);
            }

            //转账成功返回交易hash，失败返回false
            if ($transaction_hash !== false) {
                $callback = ['code' => 1, 'msg' => "转账已提交，等待节点确认<br>查看地址：https://cn.etherscan.com/tx/" . $transaction_hash];
            } else {
                $callback = ['code' => 0, 'msg' => '转账失败，请重试'];
            }
            $this->ajaxReturn($callback);
        } catch (\Exception $exception) {
            $this->ajaxReturn(['code' => 0, 'msg' => "转账失败 " . $exception->getMessage()]);
        }
    }

    /**
     * 验证码
     * @return Response
     */
    public function showVerify()
    {
        $config = [
            'fontSize' => 13,              // 验证码字体大小(px)
            'useCurve' => true,            // 是否画混淆曲线
            'useNoise' => false,            // 是否添加杂点
            'imageH' => 35,               // 验证码图片高度
            'imageW' => 80,               // 验证码图片宽度
            'length' => 3,               // 验证码位数
            'fontttf' => '4.ttf',              // 验证码字体，不设置随机获取
        ];

        return (new Captcha($config))->entry();
    }


    //出积分相关

    Public function add2()
    {
        if (IS_POST) {
            if (!empty($_POST['currency_id'])) {
                $_POST['currency_id'];
                $cu = M("Currency2")->where("currency_name='{$_POST['currency_name']}' and currency_id <> '{$_POST['currency_id']}'")->find();
            } else {
                $cu = M("Currency2")->where("currency_name='{$_POST['currency_name']}'")->find();
            }
            /*if (!empty($cu)) {
                $this->error("积分类型名称已经存在");
            }*/

            foreach ($_POST as $k => $v) {
                $data[$k] = $v;
            }

            $data['add_time'] = time();
            if ($_FILES["Filedata"]["tmp_name"]) {
                $data['currency_logo'] = $this->upload($_FILES["Filedata"]);
            }

            if (!empty($_POST['currency_id'])) {
                $rs = $this->currency2->save($data);
                $currency_id = $data['currency_id'];
            } else {
                $rs = $this->currency2->add($data);
                $currency_id = $rs;
            }
            if ($_FILES["pic"]["tmp_name"]) {
                $te['pic'] = $this->upload($_FILES["pic"]);
                $te['currency_id'] = $currency_id;
                $te['add_time'] = time();
                $te['status'] = 0;
                M('Currency_pic')->add($te);
            }
            if ($rs) {
                $this->success("操作成功");
            } else {
                $this->error('操作失败');
            }
        } else {
            if (!empty($_GET['currency_id'])) {
                $list = $this->currency2->where('currency_id=' . $_GET['currency_id'])->find();
                $this->assign('list', $list);
                $currency_pic = M('Currency_pic')->where('currency_id=' . $_GET['currency_id'])->order('add_time')->select();
                $this->assign('pic', $currency_pic);
            }

            $currency_currency = M("Currency2")->where("currency_id <> '{$_GET['currency_id']}'")->select();
            $this->assign("currency_currency", $currency_currency);

            $this->display();
        }
    }

    public function index2()
    {

        $list = $this->currency2->select();
        foreach ($list as $k => $v) {
            $list[$k]['qianbao_balance'] = $this->get_qianbao_balance($v);
        }
        $this->assign('list', $list);
        $this->assign('empty', '暂无数据');
        $this->display();
    }

    /**
     * 取用户吉链余额
     * @param $address
     * @param array $currency_mark
     * @return array
     */
    private function get_kac_balance($address, $currency_type, $issuer, $currency_mark)
    {
        if (parent::isValidAddressKac($address)) {
            $address_info = @_curl("https://app.buysent.com/api/log/balances?account={$address}", [], false, 'get');
            $address_info = @json_decode($address_info, true);
            if (!empty($address_info) && !empty($currency_type)) {
                if ($address_info['state'] == "SUCCESS") {
                    foreach ($address_info['list'] as $key => $value) {
                        if ($currency_mark == $value['assetcode']) {
                            if ($issuer == $value['issuer']) {
                                $balance = floatval($value['balance']);
                                if (isset($balance) && $balance > 0) {
                                    return $balance;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @desc 兑换
     */
    public function exchange()
    {

        if (I('mit')) {
            $remarks = I('remarks');
            $m_id = I('m_id');
            $condition['member_id'] = $m_id;
            $data['remarks'] = $remarks;


            $rs = M('Member')->where($condition)->save($data);

            if ($rs != false) {
                $this->success('提交成功');
            } else {
                $this->error('提交失败');
            }
        }
        $cuid = I('cuid');
        $email = I('email');
        $member_id = I('member_id');
        $status = I('status');
        $url = I('url');
        if (!empty($cuid)) {
            if ($cuid == 5) {

            } else {
                $where['yang_tibi.currency_id'] = I("cuid");
            }
            $this->assign("id", I("cuid"));
        }
        if (!empty($email)) {
            $name = M("Member")->where("email='{$email}'")->find();
            //$where['yang_tibi.user_id']=$name['member_id'];
        }
        $where['yang_tibi.status'] = array("in", array(8, -1, -2));
        $where['yang_tibi.b_type'] = 1;
        if (!empty($email)) {
            $where['yang_member.email'] = array('like', '%' . $email . '%');
        }
        if (!empty($member_id)) {
            $where['yang_member.member_id'] = $member_id;
        }
        if (!empty($status)) {
            $where['yang_tibi.status'] = $status;
        }
        if (!empty($url)) {
            $where['yang_tibi.url'] = array('like', '%' . $url . '%');
        }
        $field = "yang_tibi.*,yang_member.email,yang_member.member_id,yang_member.name,yang_member.phone,tos.currency_name as to_currency_name,yang_currency.currency_name,yang_member.remarks";
        $count = M("Tibi")->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->count();// 查询满足要求的总记录数
        $Page = new Page($count, 20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('cuid' => I("cuid"), 'email' => I('email'), 'member_id' => I('member_id'), 'status' => I('status'), 'url' => I('url')));
        $show = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M("Tibi")->field($field)->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")
            ->join("yang_currency  tos on yang_tibi.to_currency_id=tos.currency_id")
            ->order("add_time desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出
        //读取积分类型表

        $curr = M("Currency")->select();
        $this->assign("curr", $curr);

        $this->display();
    }

    /*
     * @desc 转账
     */
    public function exchange_transfer()
    {
        $verify = new Verify();
        if (!$verify->check($_POST['captcha'])) {
            $this->error("亲，验证码输错了哦！", $this->site_url, 9);
        }
        $id = $_POST['id'];
        if (empty($id)) {
            $this->error(L('lan_safe_temporarily_no_data'));//暂无数据
        }
        $tibi = M("Tibi")->where("id='{$id}'")->find();
        if (empty($tibi))
            $this->error(L('lan_safe_temporarily_no_data'));//暂无数据
        $model = M();
        $model->startTrans();
        $currency_id = $tibi['currency_id'];
        $to_currency_id = $tibi['to_currency_id'];
        $user_id = $tibi['user_id'];
        $num = $tibi['actual'];
        $re[] = M("Tibi")->where("id='{$id}'")->save(['status' => 8]);
        $re[] = M('Currency_user')->where(array('member_id' => $user_id, 'currency_id' => $to_currency_id))->setInc('num', $num);
        if (!in_array(false, $re)) {
            $model->commit();
            $this->error(L('lan_operation_success'));//操作成功
        } else {
            $model->rollback();
            $this->error(L('lan_operation_failure'));//操作失败
        }

    }

    /**
     * 汇总列表
     * Created by Red.
     * Date: 2018/10/27 14:06
     */
    public function summarylist()
    {
        $uid = input("uid");
        $search = input("search");
        $currencyid = input("currencyid");
        $data['uid'] = $uid;
        $data['search'] = $search;
        $data['currencyid'] = $currencyid;
        if (!empty($uid)) {
            $where['u.member_id'] = $uid;
        } elseif (!empty($search)) {
            $where['_string'] = ' (u.nick = "' . $search . '")  OR ( u.phone = "' . $search . '")';
        }
        if (!empty($currencyid)) {
            $where['w.currency_id'] = $currencyid;
        }
        $currency_user = Db::name("currency_user");
        $where['real_num'] = array("GT", 0);
//        $where['currency_mark'] = ['neq', array("BTC","XRP")];
        $where['wc.currency_mark'] = array("not in", array("XRP", "EOS"));
        $join = "LEFT JOIN yang_member u on u.member_id=w.member_id";
        $join1 = "LEFT JOIN yang_currency wc on wc.currency_id=w.currency_id";

        $field = "w.cu_id,u.member_id,u.phone,u.member_id,u.name,u.nick,w.real_num,wc.currency_mark,w.chongzhi_url";
        $list = $currency_user->alias("w")->field($field)->where($where)
            ->join(config("database.prefix") . "member u", "u.member_id=w.member_id", "LEFT")
            ->join(config("database.prefix") . "currency wc", "wc.currency_id=w.currency_id", "LEFT")
            ->paginate(20, null, ['query' => input()]);
        $show = $list->render();
        //btc的充币服务器数据
        $wc = Db::name("currency");
        $wcR = $wc->where(['currency_mark' => "BTC"])->find();
        $btc = new Btc();
        $server['rpc_user'] = $wcR['rpc_user'];
        $server['rpc_pwd'] = $wcR['rpc_pwd'];
        $server['rpc_url'] = $wcR['rpc_url'];
        $server['port_number'] = $wcR['port_number'];
        $btcmoney = $btc->get_qianbao_balance($server);
        $this->assign("btcmoney", $btcmoney);
        $this->assign("btcsummaryaddress", $wcR['qianbao_address']);
        //瑞波币的币数量
//        $xrp = $wc->where(['currency_mark' => "XRP"])->field("recharge_address,qianbao_address,qianbao_address_tag")->find();
//        $xrpmoney = 0;
//        if (!empty($xrp)) {
//            $xrpClass = new Xrp();
//            $xrpmoney = $xrpClass->getBalance($xrp['recharge_address']);
//        }
//        $eos = $wc->where(['currency_mark' => "EOS"])->field("rpc_url,port_number,recharge_address,qianbao_address,qianbao_address_tag")->find();
//        $eosmoney = 0;
//        if (!empty($eos)) {
//            $eosClass = new Eos($eos['rpc_url'], $eos['port_number']);
//            $eosResult = $eosClass->getbalance($eos['recharge_address']);
//            if ($eosResult['code'] == SUCCESS) {
//                $eosmoney = $eosResult['result'];
//            }
//        }
        //xrp的
//        $this->assign("xrpmoney", $xrpmoney);
//        $this->assign("xrpsummaryaddress", $xrp['qianbao_address']);
//        $this->assign("qianbao_address_tag", $xrp['qianbao_address_tag']);
        //eos的
//        $this->assign("eosmoney", $eosmoney);
//        $this->assign("eossummaryaddress", $eos['qianbao_address']);
//        $this->assign("eosmemo", $eos['qianbao_address_tag']);

        $currency = Db::name('currency')->field('currency_id,currency_mark')->select();
        $this->assign("currency", $currency);
        $this->assign("list", $list);
        $this->assign("page", $show);
        $this->assign("data", $data);

        return $this->fetch();
    }

    /**
     * @desc 取消汇总
     */
    public function cancelsummary()
    {
        $cu_id = input('cu_id', '', 'intval');
        if (Db::name("Currency_user")->where(['cu_id' => $cu_id])->setField('real_num', 0)) {
            $this->ajaxReturn(['code' => 10000, 'message' => '执行成功']);
        } else {
            $this->ajaxReturn(['code' => 10001, 'message' => '执行失败']);
        }
    }

    /**
     * 弹出汇总页面
     * Created by Red.
     * Date: 2018/10/27 14:06
     */
    public function summary()
    {
        if($_POST){
            $wsResult =  Summary::addSummary($_POST['chongzhi_url'], $_POST['qianbao_address'], $_POST['summary'], $_POST['wuc_wc_id'], 1, $_POST['wuc_user_id']);
            return $this->ajaxReturn($wsResult);
        }
        $cu_id = input('cu_id', '', 'intval');
        if (!empty($cu_id)) {
            $wucD = Db::name("currency_user");
            $field = "c.qianbao_address,c.currency_id,c.currency_mark,c.currency_type,c.rpc_user,c.rpc_pwd,c.rpc_url,c.port_number,c.token_address,cu.chongzhi_url,cu.member_id,cu.real_num";
            $wucR = $wucD->alias("cu")->field($field)->where(['cu.cu_id' => $cu_id])
                ->join(config("database.prefix") . "currency c", "c.currency_id=cu.currency_id", "LEFT")->find();
            $this->assign("data", $wucR);
            return  $this->fetch("summary_new");
//            $address = $wucR['chongzhi_url'];
//            if (!empty($wucR) && !empty($address)) {
//                $data['wuc_id'] = $cu_id;
//                $data['wuc_wc_id'] = $wucR['currency_id'];
//                $data['wuc_user_id'] = $wucR['member_id'];
//                if (in_array($wucR['currency_type'], ['eth', 'eth_token'])) {
//                    $e = new Eth($wucR['rpc_url'], $wucR['port_number']);
//                    //wc_contract为空则是ETH，否则为它的代币
//                    if (!empty($wucR['token_address'])) {
//                        //ETH 代币
//                        //获取ETH代币链上的数量
//                        $ETH = $e->eth_getBalance($address) ?: 0;
//                        $ETH_T = $e->token_getBalance($address, $wucR['token_address']);
//                        $data['fee'] = 0;
//                        if ($ETH_T['result'] > 0) {
//                            $feeResult = $e->token_getTxUseFee($address, $wucR['qianbao_address'], $wucR['token_address'], $ETH_T['result']);
//                            if ($feeResult['code'] == SUCCESS) {
//                                $data['fee'] = $feeResult['result']['fee'];
//                            }
//                        }
//                        $data['name'] = $wucR['currency_mark'];
//                        $this->assign("data", array_merge_recursive($data, ['eth' => keepPoint($ETH['result']['result']['number'], 6), 'AT' => keepPoint($ETH_T['result'], 6)]));
//                        return $this->display("ethtokensummary");
//                    } else {
//                        //ETH
//                        //获取ETH链上的数量
//                        $ETH = $e->eth_getBalance($address);
//                        $data['money'] = 0;
//                        $data['money1'] = 0;
//                        $data['fees'] = 0;
//                        if ($ETH['code'] == SUCCESS) {
//                            $data['money'] = keepPoint($ETH["result"]['result']['number'], 10);
//                            $data['money1'] = keepPoint($data['money'], 6);
//                        }
//                        $fee = $e->eth_fees();
//                        if (!empty($fee) && $fee['code'] == SUCCESS) {
//                            $data['fees'] = keepPoint($fee['result']['fees'], 10);
//                        }
//                        $this->assign("data", $data);
//                    }
//                } else if (in_array($wucR['currency_type'], ['kac'])) {
//                    //超级链KAC的
//                    require_once 'App/Common/Common/Kac.php';
//                    $kac = new \Kac();
//                    $token_address = $wucR['token_address'];
//                    $token_address OR die('缺少资产发行方');
//                    $kacR = $kac->balancesByIssuer($address, $token_address);
//                    if ($kacR['code'] == SUCCESS) {
//                        $data['money'] = keepPoint($kacR['result'], 10);
//                    }
//                    $this->assign("data", $data);
//                    return $this->display("kacsummary");
//                } elseif (in_array($wucR['currency_type'], ['btc', 'usdt'])) {
//                    //USDT的
//                    $btc = new Btc();
//                    $server['rpc_user'] = $wucR['rpc_user'];
//                    $server['rpc_pwd'] = $wucR['rpc_pwd'];
//                    $server['rpc_url'] = $wucR['rpc_url'];
//                    $server['port_number'] = $wucR['port_number'];
//                    //usdt帐号的余额
//                    $usdt = $btc->omni_getbalance($address, $server);
//                    //btc的余额
//                    $btcbalance = $btc->get_balance_by_address($wucR['chongzhi_url'], $server);
//                    $data['usdt'] = $usdt;
//                    $data['btc'] = number_format($btcbalance, 10);
//                    $this->assign("data", $data);
//                    return $this->fetch("usdtsummary");
//                }
//            }
        }
        return $this->fetch();

    }

    /**
     * kac提交汇总
     * Created by Red.
     * Date: 2018/7/27 20:37
     */
    function kacsummary()
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        $money = I("summary");
        $uid = I("wuc_user_id");
        $currency_id = I("wuc_wc_id");
        if (!empty($currency_id) && $money > 0 && !empty($uid)) {
            $currency = D("currency");
            //查询币种数据
            $currency_info = $currency->where(['currency_id' => $currency_id])->find();

            //查询用户地址信息
            $user_info_address = M("Currency_user")->where(array('member_id' => $uid, 'currency_id' => $currency_id))->field("chongzhi_url,secret")->find();
            if ($currency_info && $user_info_address['chongzhi_url']) {
                //汇总，用户余额转到汇总钱包地址
                //先添加一条汇总数据
                $wsD = D("Summary");
                $wsResult = $wsD->addSummary($user_info_address['chongzhi_url'], $currency_info['qianbao_address'], $money, $currency_id, 1, $uid);
                if ($wsResult['code'] == SUCCESS) {
                    require_once 'App/Common/Common/Kac.php';
                    $kac = new Kac();
                    $kacR = $kac->summary($user_info_address['secret'], $user_info_address['chongzhi_url'], $money, $currency_info['currency_mark'], $currency_info['token_address']);
                    if (!empty($kacR) && $kacR['code'] == SUCCESS) {
                        $result = $wsD->where(['id' => $wsResult['result']])->save(['txhash' => $kacR['result']['opid']]);
                        M("Currency_user")->where(array('member_id' => $uid, 'currency_id' => $currency_id))->setField('real_num', 0);
                        if ($result) {
                            $r['code'] = SUCCESS;
                            $r['message'] = "已进入待确认汇总状态，请查看再确认";
                        } else {
                            $r['code'] = SUCCESS;
                            $r['message'] = "已进入待确认汇总状态，保存交易编号异常";
                        }
                    } else {
                        $r['code'] = SUCCESS;
                        $r['message'] = "已进入待确认汇总状态，请查看再确认--" . $kacR['message'];
                    }
                } else {
                    return $this->ajaxReturn($wsResult);
                }
            }
        }
        return $this->ajaxReturn($r);
    }

    /**
     * 以太坊提交汇总
     * Created by Red.
     * Date: 2018/7/27 20:37
     */
    function ethsummary()
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        $money = input("summary");
        $uid = input("wuc_user_id");
        $currency_id = input("wuc_wc_id");
        if (!empty($currency_id) && $money > 0 && !empty($uid)) {
            $currency = Db::name("currency");
            $currency_info = $currency->where(['currency_id' => $currency_id])->find();
            //查询用户地址信息
            $user_info_address = Db::name("Currency_user")->where(array('member_id' => $uid, 'currency_id' => $currency_id))->field("chongzhi_url,secret")->find();
            if ($currency_info && $user_info_address['chongzhi_url']) {
                if (in_array($currency_info['currency_type'], ['eth'])) {
                    $e = new Eth($currency_info['rpc_url'], $currency_info['port_number']);
                    $fees = $e->eth_fees();
                    if ($fees['code'] == SUCCESS) {
                        $finallMoney = $money - number_format($fees['result']['fees'], 18);//全总数量减掉手续费后
                        if ($finallMoney < 0) {
                            return $this->ajaxReturn(ERROR2, "汇总数量不足以抵扣手续费", []);
                        }
                        //汇总，用户余额转到汇总钱包地址
                        //先添加一条汇总数据
                        $wsD = Db::name("Summary");
                        $wsResult = Summary::addSummary($user_info_address['chongzhi_url'], $currency_info['qianbao_address'], $money, $currency_id, 1, $uid);
                        if ($wsResult['code'] == SUCCESS) {
                            $send = $e->personal_sendTransaction($user_info_address['chongzhi_url'], $currency_info['qianbao_address'], $finallMoney, $e->pwd, $fees["result"]["gasPrice"], $fees["result"]["gas"]);
                            if (!empty($send) && $send['code'] == SUCCESS) {
                                $result = $wsD->where(['id' => $wsResult['result']])->update(['txhash' => $send['result']['result']]);
                                Db::name("Currency_user")->where(array('member_id' => $uid, 'currency_id' => $currency_id))->setField('real_num', 0);
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
                            return $this->ajaxReturn($wsResult);
                        }
                    } else {
                        $r['message'] = "获取转账手续费异常";
                    }
                } else if (in_array($currency_info['currency_type'], ['eth_token'])) {
                    $e = new Eth($currency_info['rpc_url'], $currency_info['port_number']);
                    $fees = $e->token_getTxUseFee($user_info_address['chongzhi_url'], $currency_info['qianbao_address'], $currency_info['token_address'], $money);
                    if ($fees['code'] == SUCCESS) {
                        //汇总，用户余额转到汇总钱包地址
                        //先添加一条汇总数据
                        $wsD = Db::name("Summary");
                        $wsResult = Summary::addSummary($user_info_address['chongzhi_url'], $currency_info['qianbao_address'], $money, $currency_id, 1, $uid);
                        if ($wsResult['code'] == SUCCESS) {
                            $send = $e->token_sendTransaction($user_info_address['chongzhi_url'], $currency_info['qianbao_address'], $currency_info['token_address'], $money, $fees["result"]["gasPrice"]['number'], $fees["result"]["gas"]['number'], $e->pwd);
                            if (!empty($send) && $send['code'] == SUCCESS) {
                                $result = $wsD->where(['id' => $wsResult['result']])->update(['txhash' => $send['result']['result']]);
                                Db::name("Currency_user")->where(array('member_id' => $uid, 'currency_id' => $currency_id))->setField('real_num', 0);
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
                            return $this->ajaxReturn($wsResult);
                        }
                    } else {
                        $r['message'] = "获取转账手续费异常";
                    }
                }
            }
        }
        return $this->ajaxReturn($r);
    }

    /**
     * 手续费弹出页面
     * Created by Red.
     * Date: 2018/7/27 11:41
     */
    function fees()
    {
        $cu_id = input("wuc_id");
        if (!empty($cu_id)) {
            $wucD = Db::name("currency_user");
            $field = "cu.member_id,c.currency_id,cu.chongzhi_url,c.currency_mark,c.currency_type,c.rpc_user,c.rpc_pwd,c.rpc_url,c.port_number";
            $wucR = $wucD->alias("cu")->field($field)->where(['cu.cu_id' => $cu_id])
                ->join(config("database.prefix") . "currency c", "c.currency_id=cu.currency_id", "LEFT")->find();
            $address = $wucR['chongzhi_url'];
            if (!empty($wucR) && $address) {
                $data['wuc_id'] = $cu_id;
                $data['wuc_wc_id'] = $wucR['currency_id'];
                $data['wuc_user_id'] = $wucR['member_id'];

                //USDT充手续费
                if (in_array($wucR['currency_type'], ['usdt'])) {
                    echo "USDT不需要充手续费";
                    die();
                    //USDT的
                    require_once 'App/Common/Common/class.btc.php';
                    $btc = new \Btc();
                    $server['rpc_user'] = $wucR['rpc_user'];
                    $server['rpc_pwd'] = $wucR['rpc_pwd'];
                    $server['rpc_url'] = $wucR['rpc_url'];
                    $server['port_number'] = $wucR['port_number'];
                    //btc的余额
                    $btcbalance = $btc->get_balance_by_address($wucR['chongzhi_url'], $server);
                    $data['btc'] = number_format($btcbalance, 10);
                    $data['address'] = $address;
                    $this->assign("data", $data);
                    $param['name'] = "USDT";
                    $param['dname'] = "btc";
                    $this->assign("param", $param);
                    return $this->display("usdtfees");
                } else if (in_array($wucR['currency_type'], ['eth_token'])) {
                    $e = new Eth($wucR['rpc_url1'], $wucR['port_number1']);
                    //获取ETH代币链上的数量
                    $result = $e->eth_getBalance($address);
                    $data['btc'] = $result['result']['result']['number'];
                    $data['address'] = $address;
                    $param['name'] = $wucR['currency_mark'];
                    $param['dname'] = "eth";
                    $this->assign("param", $param);
                    $this->assign("data", $data);
                    return $this->display("usdtfees");
                } else {
                    echo "该币种暂不需要转手续费";
                    die();
                }
            }
        }
        return $this->display();
    }

    /**
     * 给汇总地址充值手续费(ETH代币和USDT)
     * Created by Red.
     * Date: 2018/8/4 16:58
     */
    function rechargeusdtfees()
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        $address = I("address");
        // $c_id = I("wuc_wc_id");
        // $c_keys = ['29' => 56, '68' => 52];
        $currency_id = I("wuc_wc_id");
        $money = I("summary");
        if (!empty($address) && !empty($currency_id)) {
            $wcD = D("currency");
            $wcR = $wcD->where(['currency_id' => $currency_id])->find();
            //为USDT地址充值BTC手续费
            //充币地址给usdt地址充值手续费
            if (in_array($wcR['currency_type'], ['usdt'])) {
                require_once 'App/Common/Common/class.btc.php';
                $btc = new \Btc();
                $btcServer = $wcD->where(['currency_mark' => "BTC"])->find();
                if (!empty($btcServer) && !empty($btcServer['rpc_user1']) && !empty($btcServer['rpc_pwd1']) &&
                    !empty($btcServer['rpc_url1']) && !empty($btcServer['port_number1']) && !empty($btcServer['qianbao_key1'])) {
                    $currency['rpc_user'] = $btcServer['rpc_user1'];
                    $currency['rpc_pwd'] = $btcServer['rpc_pwd1'];
                    $currency['rpc_url'] = $btcServer['rpc_url1'];
                    $currency['port_number'] = $btcServer['port_number1'];
                    $btcBalance = $btc->get_qianbao_balance($currency);
                    if ($btcBalance > $money) {
                        $txid = $btc->qianbao_tibi($btcServer['qianbao_key1'], $address, $money, $currency);
                        if ($txid) {
                            $r['code'] = SUCCESS;
                            $r['message'] = "已转入手续费，等待节点确认再来查询";
                        } else {
                            $r['message'] = "转入异常";
                        }
                    } else {
                        $r['message'] = "提币地址btc不足";
                    }
                } else {
                    $r['message'] = "提币BTC服务器参数配置错误";
                }

            } else if (in_array($wcR['currency_type'], ['eth_token'])) {
                require_once 'App/Common/Common/Eth.php';
                $eth = new \Eth($wcR['rpc_url1'], $wcR['port_number1']);
                // 预估手续费
                $fees = $eth->eth_fees();
                $gas = $fees['result']['gas'];
                $gasPrice = $fees['result']['gasPrice'];
//                $fee = sctonum($fees['result']['fees'], 8);
                $fee = $fees['result']['fees'];
                // 账户余额
                $balance = $eth->eth_getBalance($wcR['summary_fee_address']);
                $balance = $balance['result']['result']['number'];
                if ($balance < ($fee + $money)) {
                    $r['message'] = "充币地址eth不足";
                } else {
                    $result = $eth->personal_sendTransaction($wcR['summary_fee_address'], $address, $money, $wcR['summary_fee_pwd'], $gasPrice, $gas);
                    if (!empty($result) && $result['code'] == SUCCESS) {
                        $r['message'] = "已转入手续费，等待节点确认再来查询";
                    } else {
                        $r['message'] = "转入异常";
                    }
                }
            }
        }
        return $this->ajaxReturn($r);
    }

    /**
     * usdt提交汇总
     * Created by Red.
     * Date: 2018/7/27 20:37
     */
    function usdtsummary()
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        $money = input("summary");//汇总数量
        $uid = input("wuc_user_id");
        $currency_id = input("wuc_wc_id");
        if (!empty($currency_id) && $money > 0 && !empty($uid)) {
            //查询币种数据
            $currency_info = Db::name("currency")->where(['currency_id' => $currency_id])->find();
//            $waD = D("WalletAddress", "Logic");
            //查询用户地址信息
//            $wa = $waD->findForUidAndWcid($uid, $currency_id);
            $user_info_address = Db::name("Currency_user")->where(array('member_id' => $uid, 'currency_id' => $currency_id))->field("chongzhi_url,secret")->find();
            if ($currency_info && $user_info_address['chongzhi_url']) {
//                $waR = $wa['result'];
                //汇总，用户余额转到汇总钱包地址
                //先添加一条汇总数据
                $wsD = Db::name("Summary");
                $wsResult = Summary::addSummary($user_info_address['chongzhi_url'], $currency_info['qianbao_address'], $money, $currency_id, 1, $uid);

                if ($wsResult['code'] == SUCCESS) {
                    $btc = new Btc();
                    $currency['rpc_user'] = $currency_info['rpc_user'];
                    $currency['rpc_pwd'] = $currency_info['rpc_pwd'];
                    $currency['rpc_url'] = $currency_info['rpc_url'];
                    $currency['port_number'] = $currency_info['port_number'];
//                    $txid = $btc->usdt_qianbao_tibi($user_info_address['chongzhi_url'], $currency_info['qianbao_key'], $currency_info['qianbao_address'], $money, $currency);
                    //汇总手续费从指定的地址上扣除
                    $sendResult = $btc->omni_funded_send($user_info_address['chongzhi_url'], $currency_info['qianbao_key'], $currency_info['qianbao_address'], $money, $currency_info['summary_fee_address'], $currency);
                    if ($sendResult['code'] == SUCCESS) {
                        $result = $wsD->where(['id' => $wsResult['result']])->update(['txhash' => $sendResult['result']]);
                        if ($result) {
                            $r['code'] = SUCCESS;
                            $r['message'] = "已进入待确认汇总状态，请查看再确认";
                        } else {
                            $r['code'] = SUCCESS;
                            $r['message'] = "已进入待确认汇总状态，保存交易编号异常";
                        }
                    } else {
                        $r['code'] = SUCCESS;
                        $r['message'] = "已进入待确认汇总状态，但没有返回交易编号,原因:" . $sendResult['message'];
                    }
                } else {
                    return $this->ajaxReturn($wsResult);
                }
            }
        }
        return $this->ajaxReturn($r);
    }

    /**
     * 弹出btc汇总页面
     * @return mixed
     * Created by Red.
     * Date: 2018/8/15 15:19
     */
    function btcsummary()
    {
        $amount = input("amount");
        $address = input("btcaddress");
        $this->assign("amount", $amount);
        $this->assign("address", $address);
        return $this->fetch();
    }

    /**
     * 弹出xrp汇总页面
     * @return mixed
     * Created by Red.
     * Date: 2018/8/15 15:19
     */
    function xrpsummary()
    {
        $currency = Db::name("currency")->field("currency_id")->where(['currency_mark' => "XRP"])->find();
        if (!empty($currency)) {
            $addressList = Db::name("summary_address")->where(['sa_currency_id' => $currency['currency_id']])->order("sa_is_default asc ")->select();
            $data[0]['sa_name'] = "币种配置默认";
            $data[0]['sa_tag'] = input("xrptag");
            $data[0]['sa_address'] = input("xrpaddress");
            if (!empty($addressList)) {
                //判断一下是否有默认设置的
                if ($addressList[0]['sa_is_default'] == 1) {
                    $addressList = array_merge($addressList, $data);
                } else {
                    $addressList = array_merge($data, $addressList);
                }

            } else {
                $addressList = $data;
            }

        }
        $list = [];
        if (isset($addressList[0])) $list = $addressList[0];
        $this->assign("list", $list);
        $this->assign("data", $addressList);
        $this->assign("data_json", json_encode($addressList));
        $amount = input("amount");
        $this->assign("amount", $amount);
        return $this->fetch();
    }

    /**
     * 弹出EOS汇总页面
     * @return mixed
     * Created by Red.
     * Date: 2018/8/15 15:19
     */
    function eossummary()
    {
        $amount = input("amount");
        $eostag = input("eostag");
        $address = input("eosaddress");
        $this->assign("amount", $amount);
        $this->assign("eostag", $eostag);
        $this->assign("address", $address);
        return $this->fetch();
    }

    /**
     * btc提交汇总
     * Created by Red.
     * Date: 2018/10/22 20:19
     */
    function submitbtcsummary()
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        $amount = input("summary");
        if (!empty($amount) && $amount > 0) {
            $wc = Db::name("currency");
            $wcR = $wc->where(['currency_mark' => "BTC"])->find();
            $btc = new Btc();
            $server['rpc_user'] = $wcR['rpc_user'];
            $server['rpc_pwd'] = $wcR['rpc_pwd'];
            $server['rpc_url'] = $wcR['rpc_url'];
            $server['port_number'] = $wcR['port_number'];
            $btcmoney = $btc->get_qianbao_balance($server);
            if ($amount <= $btcmoney) {
                $summary['from_address'] = $wcR['qianbao_address'];
                $summary['to_address'] = $wcR['qianbao_address'];
                $summary['money'] = $amount;
                $summary['status'] = 1;
                $summary['starttime'] = time();
                $summary['currency_id'] = $wcR['currency_id'];
                $id = Db::name("summary")->insertGetId($summary);
                if ($id) {
                    $txid = $btc->qianbao_tibi($wcR['qianbao_key'], $wcR['qianbao_address'], $amount, $server);
                    if (!empty($txid)) {
                        Db::name("summary")->where(['id' => $id])->update(['txhash' => $txid]);
                        $r['code'] = SUCCESS;
                        $r['message'] = "汇总已提交，请查询是否到汇总钱包";
                    } else {
                        $r['message'] = "汇总失败,没有返回交易编号";
                    }
                }
            } else {
                $r['message'] = "汇总数量超限了";
            }
        }
        return $this->ajaxReturn($r);
    }

    /**
     * xrp提交汇总
     * Created by Red.
     * Date: 2018/10/22 20:19
     */
    function submitxrpsummary()
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        $amount = input("summary");
        $xrpaddress = input("xrpaddress");
        $xrptag = input("xrptag");
        if (!empty($amount) && $amount > 0 && !empty($xrpaddress) && !empty($xrptag)) {
            $wc = Db::name("currency");
            $wcR = $wc->where(['currency_mark' => "XRP"])->find();
            $summary['from_address'] = $wcR['recharge_address'];
//            $summary['to_address'] = $wcR['qianbao_address'];
            $summary['to_address'] = $xrpaddress;
            $summary['money'] = $amount;
            $summary['status'] = 1;
            $summary['starttime'] = time();
            $summary['currency_id'] = $wcR['currency_id'];
//            $summary['tags'] = $wcR['qianbao_address_tag'];
            $summary['tags'] = $xrptag;
            $id = Db::name("summary")->insertGetId($summary);
            if ($id) {
                $url = "http://" . $wcR['rpc_url'] . ":" . $wcR['port_number'];
                $xrp = new Xrp();
                $result = $xrp->sendTrans($url, $amount, $wcR['recharge_address'], $wcR['qianbao_key'], $xrpaddress, $xrptag);
                if ($result['code'] == SUCCESS) {
                    $r['code'] = SUCCESS;
                    $r['message'] = "汇总已提交，请查询是否到汇总钱包";
                } else {
                    $r['message'] = "汇总失败,没有返回交易编号:" . $result['message'];
                }
            }
        }
        return $this->ajaxReturn($r);
    }

    /**
     * EOS提交汇总
     * Created by Red.
     * Date: 2018/10/22 20:19
     */
    function submiteossummary()
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        $amount = input("summary");
        if (!empty($amount) && $amount > 0) {
            $wc = Db::name("currency");
            $wcR = $wc->where(['currency_mark' => "EOS"])->find();
            $summary['from_address'] = $wcR['recharge_address'];
            $summary['to_address'] = $wcR['qianbao_address'];
            $summary['money'] = $amount;
            $summary['status'] = 1;
            $summary['starttime'] = time();
            $summary['currency_id'] = $wcR['currency_id'];
            $summary['tags'] = $wcR['qianbao_address_tag'];
            $id = Db::name("summary")->insertGetId($summary);
            if ($id) {
                $eos = new Eos($wcR['rpc_url'], $wcR['port_number']);
                $result = $eos->transfer($wcR['recharge_address'], $wcR['qianbao_address'], $amount, $wcR['qianbao_address_tag']);
                if ($result['code'] == SUCCESS) {
                    if (!empty($result['result'])) {
                        Db::name("summary")->where(['id' => $id])->update(['txhash' => $result['result']]);
                        $r['code'] = SUCCESS;
                        $r['message'] = "汇总已提交，请查询是否到汇总钱包";
                    } else {
                        $r['code'] = SUCCESS;
                        $r['message'] = "汇总已提交，但没有返回交易编号";
                    }

                } else {
                    $r['message'] = "汇总失败,没有返回交易编号:" . $result['message'];
                }
            }
        }
        return $this->ajaxReturn($r);
    }

    /**
     * btc 充值明细
     */
    public function btclog()
    {
        $cuid = I('cuid') ?: 52;
        $member_id = I('uid');
        $search = I('search');

        if (!empty($cuid)) {
            if ($cuid == 1) {

            } else {
                $where['yang_tibi.currency_id'] = $cuid;
            }
            $this->assign("id", $cuid);
        }
        if (!empty($email)) {
            $name = M("Member")->where("email='{$email}'")->find();
            //$where['yang_tibi.user_id']=$name['member_id'];
        }
        $where['yang_tibi.status'] = array("in", array(2, 3, 4, 5));
        $where['yang_tibi.b_type'] = 0;
        if (!empty($email)) {
            $where['yang_member.email'] = array('like', '%' . $email . '%');
        }
        if (!empty($member_id)) {
            $where['yang_member.member_id'] = $member_id;
        }
        if (!empty($status)) {
            $where['yang_tibi.status'] = $status;
        }
        if (!empty($url)) {
            $where['yang_tibi.url'] = array('like', '%' . $url . '%');
        }
        if (!empty($search)) {
            $where['yang_member.name|yang_member.email|yang_member.phone'] = array('like', '%' . $search . '%');
        }
        $field = "yang_tibi.*,yang_member.email,yang_member.member_id,yang_member.name,yang_member.phone,yang_currency.currency_name,yang_member.remarks";
        $count = M("Tibi")->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->count();// 查询满足要求的总记录数
        $Page = new Page($count, 10);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('cuid' => $cuid, 'search' => $search, 'uid' => $member_id));
        $show = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M("Tibi")->field($field)->where($where)->join("yang_member on yang_tibi.user_id=yang_member.member_id")
            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")->order("add_time desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出
        return $this->display();
    }


    /**
     * 待汇总页面
     * @return mixed
     * Created by Red.
     * Date: 2018/7/27 21:35
     */
    function waitforsummarylist()
    {
        $uid = input("uid");
        $search = input("search");
        $currency_id = input("currency_id");
        $starttime = input("starttime");
        $endtime = input("endtime");
        $data = null;
        $wsD = Db::name("summary")->alias("ws");
        if (!empty($uid)) {
            $where['ws.from_user_id'] = $uid;

        } elseif (!empty($search)) {
            $where['ws.from_address|ws.to_address|u.phone'] = ['like', '%' . $search . '%'];
        }
        $data['uid'] = $uid;
        $data['search'] = $search;
        if (!empty($currency_id)) {
            $where['ws.currency_id'] = $currency_id;
        }
        $data['currency_id'] = $currency_id;
        $data['starttime'] = $starttime;
        $data['endtime'] = $endtime;
        if (!empty($starttime)) {
            if (empty($endtime)) {
                $endtime = date("Y-m-d", time());
            }
            $where['ws.starttime'] = array('between', array(strtotime($starttime), strtotime($endtime) + 86400));
        }

        $status = input("status", 1);
//        状态:1待确认,2成功,3失败
        $status_all = ['1' => '待确认', '2' => '汇总成功', '3' => '汇总失败',];
        $this->assign("status_all", $status_all);
        $data['status'] = $status;
        $where['ws.status'] = $status;
        $field = "ws.*,u.phone,u.email,c.currency_mark,c.currency_type";

        if (input("daochu") == 1) {
            $list = $wsD->where($where)->field($field)
                ->join(config("database.prefix") . "currency c", "c.currency_id=ws.currency_id", 'LEFT')
                ->join(config("database.prefix") . "member u", "u.member_id=ws.from_user_id", 'LEFT')
                ->order("id desc")->select();
            if (!empty($list)) {
                foreach ($list as &$value) {
                    $value['status'] = $status_all[$value['status']];
                    $value['starttime'] = date("Y-m-d H:i:s", $value['starttime']);
                    if (!empty($value['endtime'])) {
                        $value['endtime'] = date("Y-m-d H:i:s", $value['endtime']);
                    }
                }
            }
            $xlsCell = array(
                array('id', '列表ID'),
                array('from_user_id', '用户ID'),
                array('phone', '手机号'),
                array('email', '邮箱'),
                array('from_address', '转账地址'),
                array('to_address', '汇总地址'),
                array('txhash', '交易编号'),
                array('money', '数量'),
                array('fees', '手续费'),
                array('currency_mark', '币种'),
                array('status', '状态'),
                array('starttime', '开始时间'),
                array('endtime', '结束时间'),
            );
            $this->exportExcel("汇总记录", $xlsCell, $list);
            die();
        }
        $list = $wsD->where($where)->field($field)
            ->join(config("database.prefix") . "currency c", "c.currency_id=ws.currency_id", 'LEFT')
            ->join(config("database.prefix") . "member u", "u.member_id=ws.from_user_id", 'LEFT')
            ->order("id desc")->paginate(20, null, ['query' => input()]);
        $show = $list->render();
        $currency = Db::name('currency')->field('currency_id,currency_mark')->select();
        $this->assign("currency", $currency);
        $this->assign("page", $show);
        $this->assign("list", $list);
        $this->assign("data", $data);
        return $this->fetch();
    }

    /**
     * 不汇总页面
     * @return mixed
     * Created by Red.
     * Date: 2018/7/27 21:35
     */
    public function nosummarylist()
    {
        $uid = input("uid");
        $search = input("search");
        $currencyid = input("currencyid");
        $currencyFiled = input("currencyname");
        $orderBy = input("order");
        $where = null;
        if (!empty($uid)) {
            $where['u.member_id'] = $uid;

        } elseif (!empty($search)) {
            $where["u.nick|u.phone|u.email"] = ['like', '%' . $search . '%'];
        }
        $data['uid'] = $uid;
        $data['search'] = $search;
        if (!empty($currencyid)) {
            $where['w.currency_id'] = $currencyid;
        }
        $data['currencyid'] = $currencyid;
        $currency_user = Db::name("currency_user");
        $field = "w.cu_id,u.member_id,u.phone,u.email,u.member_id,u.name,u.nick,w.num,w.real_num,wc.currency_mark,w.chongzhi_url,w.forzen_num,w.num_award,w.xrpj,w.xrpg,u.remarks";
        $select = $currency_user->alias("w")->field($field)->where($where)
            ->join(config("database.prefix") . "member u", "u.member_id=w.member_id", "LEFT")
            ->join(config("database.prefix") . "currency wc", "wc.currency_id=w.currency_id", "LEFT");
        if (!empty($currencyFiled) || $currencyFiled != 0) {
            if ($orderBy == '0') $orderBy = "desc";
            $select->order("w." . $currencyFiled . " " . $orderBy);
        }
        $list = $select->paginate(20, null, ['query' => input()]);
        $show = $list->render();
        $currency = Db::name('currency')->field('currency_id,currency_mark')->select();
        $data['currencyFiled'] = $currencyFiled;
        $data['orderBy'] = $orderBy;
        $this->assign("currency", $currency);
        $this->assign("list", $list);
        $this->assign("page", $show);
        $this->assign("data", $data);
        $currencyName = ["num" => "可用", "forzen_num" => "冻结", "num_award" => "赠送", "xrpj" => "瑞波钻", "xrpg" => "瑞波金"];
        $order = ["asc" => "从低到高", "desc" => "从高到低"];

        $this->assign("currencyName", $currencyName);
        $this->assign("order", $order);


        return $this->fetch();
    }

    /**
     * 弹窗页面
     * @return mixed
     * Created by Red.
     * Date: 2018/7/27 22:57
     */
    function successsummary()
    {
        $id = input("id");
        if (!empty($id)) {
            $wsD = Db::name("Summary");
            $result = $wsD->where(['id' => $id])->find();
            $this->assign("data", $result);
        }
        return $this->fetch();
    }

    /**
     * 审核汇总操作
     * 状态:status=2表示成功；status=3拒绝，不进行其它操作
     * Created by Red.
     * Date: 2018/7/27 22:45
     */
    function updateSummaryStatus()
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        $wsid = input("post.wsid");
        $status = input("post.status");
        $txhash = input("post.txhash");
        $fees = input("post.fees");
        $to_address = input("post.to_address");
        if (!empty($wsid) && !empty($status)) {
            $result = Summary::updateSummaryStatus($wsid, $status, $txhash, $fees,$to_address);
            return $this->ajaxReturn($result);
        }
        return $this->ajaxReturn($r);
    }

    public function daystrade()
    {
        $trade_count_day = M('trade_count_day');
        $time_start = I('date1');
        $time_end = I('date2');
        $currency_id = I('currency_id');
        if (!empty($time_start) && !empty($time_end)) {
            $w['time'] = ['between', [strtotime($time_start), strtotime($time_end)]];
        }
        if (!empty($currency_id)) {
            $w['currency_id'] = $currency_id;
        }
        $count = $trade_count_day->where($w)->count();
        $page = new Page($count, 20);
        $show = $page->show();
        $list = $trade_count_day->where($w)->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign("list", $list);
        $this->assign("page", $show);
        $currency = M('currency')->field('currency_id,currency_mark')->select();
        $this->assign("currency", $currency);
        $this->display();
    }

    /**
     * 提积分记录
     */
    public function tibilist()
    {
        if (input('mit')) {
            $message1 = input('message1');
            $id = input('id');
            $condition['id'] = $id;
            $data['message1'] = $message1;
            $data['admin_id1'] = session("admin_userid");
            //判断是否有数据
            $find_data = Db::name('tibi')->where($condition)->find();
            //判断是否已在审核2提交
            if (session("admin_userid") > 1) {
                if ($find_data['admin_id2'] == session("admin_userid")) {
                    $this->error('已在审核2提交，不可重复提交');
                }
            }
            //判断是否已经有数据
            if ($find_data['message1']) {
                if (session("admin_userid") == 1) {
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

        if (input('mit2')) {
            $message2 = input('message2');
            $id = input('id');
            $condition['id'] = $id;
            $data2['message2'] = $message2;
            $data2['admin_id2'] = session("admin_userid");
            //判断是否有数据
            $find_data = Db::name('tibi')->where($condition)->find();
            //判断是否已在审核2提交
            if (session("admin_userid") > 1) {
                if ($find_data['admin_id1'] == session("admin_userid")) {
                    $this->error('已在审核1提交，不可重复提交');
                }
            }
            //判断是否已经有数据
            if ($find_data['message2']) {
                if (session("admin_userid") == 1) {
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
        $cuid = input('cuid');
        $phone = input('phone');
        $member_id = input('member_id');
        $url = input('url');
        $tag = input('tag');
        if (!empty($cuid)) {
            $where['tb.currency_id'] = input("cuid");
        }
        $this->assign("id", input("cuid"));

        $where['tb.status'] = array("in", array(0, 1, -1, -2));
        $where['tb.b_type'] = 0;
        if (!empty($phone)) {
            $where['m.phone'] = array('like', '%' . $phone . '%');
        }
        if (!empty($member_id)) {
            $where['m.member_id'] = $member_id;
        }

        $where['tb.status'] = -1;
        $where['tb.transfer_type'] = '1';
        if (!empty($url)) {
            $where['tb.to_url'] = array('like', '%' . $url . '%');
        }
        if(!empty($tag)) {
            $where['tb.tag'] = $tag;
        }
        $field = "tb.*,m.email,m.member_id,m.name,m.phone,c.currency_name,m.remarks";


        $list = Db::name("Tibi")->alias("tb")->field($field)->where($where)
            ->join(config("database.prefix") . "member m", "m.member_id=tb.from_member_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "c.currency_id=tb.currency_id", "LEFT")
            ->order("add_time asc")
            ->paginate(20, null, ['query' => input()]);
        $show = $list->render();
        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出
        //读取积分类型表

        $curr = Db::name("Currency")->select();
        $this->assign("curr", $curr);

        $temp['cuid'] = $cuid;
        $temp['member_id'] = $member_id;
        $temp['phone'] = $phone;
        $temp['url'] = $url;
        $temp['tag'] = $tag;
        $this->assign("temp", $temp);
        return $this->fetch();
    }

    /**
     * 提币待确认列表
     * Created by Red.
     * Date: 2018/10/18 20:04
     */
    function waitfortakecoinlist()
    {
        $uid = input("uid");
        $search = input("search");
        $currency_id = input("currency_id");
        $starttime = input("starttime");
        $endtime = input("endtime");
        $data = null;
        if (!empty($uid)) {
            $where['t.from_member_id'] = $uid;
        } elseif (!empty($search)) {
            $where["ctc.from_address|ctc.to_address|t.from_url"]=['like', '%' . $search . '%'];
        }
        $data['search'] = $search;
        $data['uid'] = $uid;
        if (!empty($currency_id)) {
            $where['ctc.currency_id'] = $currency_id;
        }
        $data['currency_id'] = $currency_id;

        $data['starttime'] = $starttime;
        $data['endtime'] = $endtime;
        if (!empty($starttime)) {
            if (empty($endtime)) {
                $endtime = date("Y-m-d", time());
            }
            $where['ctc.starttime'] = array('between', array(strtotime($starttime), strtotime($endtime) + 86400));
        }

        $tag = input("tag",'');
        if(!empty($tag)) {
            $where['t.tag'] = $tag;
        }
        $data['tag'] = $tag;

        $WalletTakeCoinD = Db::name("CurrencyTakeCoin");
        $where['ctc.status'] = 1;
        $list = $WalletTakeCoinD->alias("ctc")->field("*,ctc.id as cid,ad.username as transfer_admin_name")->where($where)
            ->join(config("database,prefix")."currency c","c.currency_id=ctc.currency_id","LEFT")
            ->join(config("database,prefix")."tibi t","t.id=ctc.tibi_id","LEFT")
            ->join(config("database,prefix")."admin ad","ad.admin_id=ctc.transfer_admin_id","LEFT")
            ->paginate(20,null,['query'=>input()]);
        $show=$list->render();
        $currency = Db::name('Currency')->field('currency_id,currency_mark')->select();
        $this->assign("currency", $currency);
        $this->assign("list", $list);
        $this->assign("page", $show);
        $this->assign("data", $data);
        return $this->fetch();
    }

    //提币检测 - hash获取不到原因
    function takecoincheck()
    {
        $address = input("address");
        $num = input("num");
        /**
        #status 0未处理 1正在处理 2失败 3成功,4处理(地址不存在系统内) 5异常数据
        select * from yang_currency_log where ato='0xaf14c9a574cc9442ec7e66f8e3299d49c12c60a4' and amount=260;
        #status 0为提币中 1为提币成功  2为充值中 3位充值成功 8兑换 -1 审核中 -2 撤销
        select * from yang_tibi where to_url='0xaf14c9a574cc9442ec7e66f8e3299d49c12c60a4' and actual=260;

        #take_coin 状态:1待确认中,2提币成功,3失败,4重新审核
        select * from yang_currency_take_coin where to_address='0xaf14c9a574cc9442ec7e66f8e3299d49c12c60a4' and money=260;
        select * from yang_take_push where ato='0xaf14c9a574cc9442ec7e66f8e3299d49c12c60a4' and amount=260;
         */
        $time = time()-86400*7;
        //获取7天内 提币列表
        $wait_list = Db::name('currency_take_coin')->where('to_address',$address)->where('starttime>='.$time)->where('money',$num)->select();
        //获取7天内 推送到服务器提币申请列表
        $pull_list = Db::name('take_push')->where('ato',$address)->where('push_time>='.$time)->where('amount',$num)->select();
        //获取7天内 服务器 提币成功hash推送列表
        $push_list = Db::name('currency_log')->where('ato',$address)->where('add_time>='.$time)->where('amount',$num)->select();

        return $this->fetch(null,compact('wait_list','pull_list','push_list'));
    }

    //分配转账管理员ID
    function transfer_admin() {
        $id = intval(input("id"));
        $info = Db::name("CurrencyTakeCoin")->where(['id' => $id])->find();
        if(empty($info)) $this->error('记录不存在');

        if($this->request->isPost()) {
            $admin_id = intval(input('admin_id'));
            $flag = Db::name("CurrencyTakeCoin")->where(['id' => $id])->setField('transfer_admin_id',$admin_id);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $admins = Db::name('admin')->where(['rule'=>'caiwu'])->select();
            return $this->fetch(null,compact('admins','info'));
        }
    }

    //我的转账任务
    function my_transfer_task() {
        $uid = input("uid");
        $search = input("search");
        $currency_id = input("currency_id");
        $starttime = input("starttime");
        $endtime = input("endtime");
        $data = null;
        if (!empty($uid)) {
            $where['t.from_member_id'] = $uid;
        } elseif (!empty($search)) {
            $where["ctc.from_address|ctc.to_address|t.from_url"]=['like', '%' . $search . '%'];
        }
        $data['search'] = $search;
        $data['uid'] = $uid;
        if (!empty($currency_id)) {
            $where['ctc.currency_id'] = $currency_id;
        }
        $data['currency_id'] = $currency_id;

        $data['starttime'] = $starttime;
        $data['endtime'] = $endtime;
        if (!empty($starttime)) {
            if (empty($endtime)) {
                $endtime = date("Y-m-d", time());
            }
            $where['ctc.starttime'] = array('between', array(strtotime($starttime), strtotime($endtime) + 86400));
        }


        $WalletTakeCoinD = Db::name("CurrencyTakeCoin");
        $where['ctc.status'] = 1;
        $where['transfer_admin_id'] = $this->admin['admin_id'];
        $list = $WalletTakeCoinD->alias("ctc")->field("*,ctc.id as cid,ad.username as transfer_admin_name")->where($where)
            ->join(config("database,prefix")."currency c","c.currency_id=ctc.currency_id","LEFT")
            ->join(config("database,prefix")."tibi t","t.id=ctc.tibi_id","LEFT")
            ->join(config("database,prefix")."admin ad","ad.admin_id=ctc.transfer_admin_id","LEFT")
            ->paginate(20,null,['query'=>input()]);
        $show=$list->render();
        $currency = Db::name('Currency')->field('currency_id,currency_mark')->select();
        $this->assign("currency", $currency);
        $this->assign("list", $list);
        $this->assign("page", $show);
        $this->assign("data", $data);
        return $this->fetch();
    }

    /**
     * 提币成功弹出页面
     * Created by Red.
     * Date: 2018/7/30 21:21
     */
    function successtakecoin()
    {
        $id = input("id");
        $wtcD = Db::name("CurrencyTakeCoin");
        $wtcR = $wtcD->where(['id' => $id])->find();
        $currency = CurrencyModel::where('currency_id', $wtcR['currency_id'])->find();

        if (empty($wtcR['txhash'])) {
            $tibi = Tibi::where('id', $wtcR['tibi_id'])->field('id, tag, to_url, to_member_id, from_url, from_member_id')->find();
            $logWhere = [];
            if ($currency['currency_type'] == 'xrp') {
                $logWhere['ato'] = ['like', "%{$wtcR['to_address']}_{$tibi['tag']}%"];
//                $logWhere['afrom'] = ['like', "%{$currency['recharge_address']}_{$tibi['from_member_id']}%"];
                $logWhere['types'] = 4;
            } else {
                $logWhere['ato'] = ['like', "%{$wtcR['to_address']}%"];
//                $logWhere['afrom'] = ['like', "%{$currency['recharge_address']}%"];
            }
            $logWhere['amount'] = (double)$wtcR['money'];
            $log = CurrencyLog::where($logWhere)->find();

            if (!is_null($log)) {
                $wtcR['txhash'] = $log['tx'];
            }
        }
        $this->assign('currency_type', $currency['currency_type']);
        $this->assign("data", $wtcR);
        return $this->fetch();
    }

    /**
     * 提币审核操作
     * status:1待确认中,2提币成功,3失败,4重新审核
     * @return \json
     */
    function updatetakecoin()
    {
        $r['code'] = ERROR1;
        $r['message'] = "参数错误";
        $r['result'] = [];
        $id = input("post.id");
        $status = input("post.status");
        $hash = input("post.hash",'');
        $fee = input("post.fee");

        if (is_numeric($id) && is_numeric($status) && in_array($status, [2, 3, 4])) {
            if (in_array($status, [3, 4])) {
                if($this->admin['admin_id']!=1 && $this->admin['rule']!='shenhe') {
                    $r['message'] = "你没有权限操作，请联系高级管理员";
                    return $this->ajaxReturn($r);
                }
            }
            $takecoin = Db::name("CurrencyTakeCoin")->where(['id' => $id])->find();
            if (!empty($takecoin)) {
                if ($status == 4) {
                    $result =CurrencyTakeCoin::updateTakeCoinStatus($id, 4, null, null, null, session("admin_userid"));
                    return $this->ajaxReturn($result);
                } else {
//                  $hashResult = empty($takecoin['txhash']) ? $hash : $takecoin['txhash']; //txhash 填写错误无法更改 暂时隐藏
                    $hashResult = $hash;
                    if ($status == 2) {
                        if(empty($hashResult)) {
                            $r['message'] = "请填写hash";
                            return $this->ajaxReturn($r);
                        }
                        $result = CurrencyTakeCoin::updateTakeCoinStatus($id, 2, $hashResult, time(), $fee, session("admin_userid"));
                        return $this->ajaxReturn($result);
                    }
                    if ($status == 3) {
                        $result = CurrencyTakeCoin::updateTakeCoinStatus($id, 3, $hashResult, time(), null, session("admin_userid"));
                        return $this->ajaxReturn($result);
                    }
                }
            } else {
                $r['message'] = "数据有误";
            }
        }
        return $this->ajaxReturn($r);
    }

    /**每日充币统计
     * Created by Red.
     * Date: 2018/9/7 11:04
     */
    function everydayRecharge()
    {
        $startTime = input("startTime");
        $endTime = input("endTime");
        $daochu = input("daochu");
        if (empty($startTime)) {
            $startTime = date("Y-m-d", time());
        }
        if (empty($endTime)) {
            $endTime = date("Y-m-d", time());
        }
        $where['wer_time'] = array('between', array($startTime, $endTime));
        $list = Db::name("WalletEverydayRecharge")->where($where)->order("wer_time desc")->select();
        $array = [];
        $total = 0;
        if (!empty($list)) {
            $wcD = Db::name("Currency");
            foreach ($list as &$value) {
                $wcResult = $wcD->field("currency_mark")->where(['currency_id' => $value['wer_currency_id']])->find();
                $value['currency_mark'] = isset($wcResult['currency_mark']) ? $wcResult['currency_mark'] : "未知币种";
                $array[$value['wer_time']][] = $value;
                $total += $value['wer_total'];
            }
        }
        if ($daochu == 1) {
            $xlsCell = array(
                array('currency_mark', '币种名称'),
                array('wer_time', '日期'),
                array('wer_total', '数量'),
            );
            $this->exportExcel("充币统计", $xlsCell, $list);
            die();
        }
        $this->assign("list", $array);
        $this->assign("startTime", $startTime);
        $this->assign("endTime", $endTime);
        $this->assign("total", $total);
        return $this->fetch();
    }

    /**
     * 每日提币统计
     * Created by Red.
     * Date: 2018/9/7 12:12
     */
    function everydayTake()
    {
        $startTime = input("startTime");
        $endTime = input("endTime");
        $daochu = input('daochu');
        if (empty($startTime)) {
            $startTime = date("Y-m-d", time());
        }
        if (empty($endTime)) {
            $endTime = date("Y-m-d", time());
        }
        $where['wet_time'] = array('between', array($startTime, $endTime));
        $list = Db::name("WalletEverydayTake")->where($where)->order("wet_time desc")->select();
        $array = [];
        $total = 0;
        if (!empty($list)) {
            $wcD = Db::name("Currency");
            foreach ($list as &$value) {
                $wcResult = $wcD->field("currency_mark")->where(['currency_id' => $value['wet_currency_id']])->find();
                $value['currency_mark'] = isset($wcResult['currency_mark']) ? $wcResult['currency_mark'] : "未知币种";
                $array[$value['wet_time']][] = $value;
                $total += $value['wet_total'];
            }
        }
        if ($daochu == 1) {
            $xlsCell = array(
                array('currency_mark', '币种名称'),
                array('wet_time', '日期'),
                array('wet_total', '数量'),
            );
            $this->exportExcel("提币统计", $xlsCell, $list);
            die();
        }
        $this->assign("list", $array);
        $this->assign("startTime", $startTime);
        $this->assign("endTime", $endTime);
        $this->assign('total',$total);
        return $this->fetch();
    }

    /**
     * 每日汇总统计
     * Created by Red.
     * Date: 2018/9/7 14:37
     */
    function everydaySummary()
    {
        $startTime = input("startTime");
        $endTime = input("endTime");
        $daochu = input('daochu');
        if (empty($startTime)) {
            $startTime = date("Y-m-d", time());
        }
        if (empty($endTime)) {
            $endTime = date("Y-m-d", time());
        }
        $where['wes_time'] = array('between', array($startTime, $endTime));
        $list = Db::name("WalletEverydaySummary")->where($where)->order("wes_time desc")->select();
        $array = [];
        if (!empty($list)) {
            $wcD = Db::name("Currency");
            foreach ($list as &$value) {
                $wcResult = $wcD->field("currency_mark")->where(['currency_id' => $value['wes_currency_id']])->find();
                $value['currency_mark'] = isset($wcResult['currency_mark']) ? $wcResult['currency_mark'] : "未知币种";
                $array[$value['wes_time']][] = $value;
            }
        }
        if ($daochu == 1) {
            $xlsCell = array(
                array('currency_mark', '币种名称'),
                array('wes_time', '日期'),
                array('wes_total', '数量'),
            );
            $this->exportExcel("汇总统计", $xlsCell, $list);
            die();
        }
        $this->assign("list", $array);
        $this->assign("startTime", $startTime);
        $this->assign("endTime", $endTime);
        return $this->fetch();
    }

    /**
     * 充提币统计数据
     * @return mixed
     * Created by Red.
     * Date: 2018/9/15 14:50
     */
    function everydayRechargeAndTake()
    {
        $startTime = input("startTime");
        $endTime = input("endTime");
        if (empty($startTime)) {
            $startTime = date("Y-m-d", time());
        }
        if (empty($endTime)) {
            $endTime = date("Y-m-d", time());
        }
        //充币统计
        $where['wer_time'] = array('between', array($startTime, $endTime));
        $list = Db::name("WalletEverydayRecharge")->where($where)->order("wer_time desc")->select();
        $array = [];
        if (!empty($list)) {
            $wcD = Db::name("Currency");
            foreach ($list as &$value) {
                $value['currency_id'] = $value['wer_currency_id'];
                $value['wet_total'] = $value['wer_total'];
                $wcResult = $wcD->field("currency_mark")->where(['currency_id' => $value['wer_currency_id']])->find();
                $value['currency_mark'] = isset($wcResult['currency_mark']) ? $wcResult['currency_mark'] : "未知币种";
                $array[$value['wer_time']][] = $value;
            }
        }
        //提币统计
        $where1['wet_time'] = array('between', array($startTime, $endTime));
        $list1 = Db::name("WalletEverydayTake")->where($where1)->order("wet_time desc")->select();
        $array1 = [];
        if (!empty($list1)) {
            $wcD = Db::name("Currency");
            foreach ($list1 as &$value1) {
                $value1['currency_id'] = $value1['wet_currency_id'];
                $wcResult = $wcD->field("currency_mark")->where(['currency_id' => $value1['wet_currency_id']])->find();
                $value1['currency_mark'] = isset($wcResult['currency_mark']) ? $wcResult['currency_mark'] : "未知币种";
                $array1[$value1['wet_time']][] = $value1;
            }
        }
        $allList = array_merge_recursive($array, $array1);
        $abc = array();
        foreach ($allList as $k => $v) {
            foreach ($v as $kk => $vv) {
                if (isset($vv['wer_id'])) $abc[$k][$vv['currency_id']]['con'] = $vv;
                else $abc[$k][$vv['currency_id']]['ti'] = $vv;
            }
        }
        $this->assign("startTime", $startTime);
        $this->assign("endTime", $endTime);
        $this->assign("list", $abc);

        return $this->fetch();

    }

    /**
     * 内部互转记录
     * @throws Exception
     * Created by Red.
     * Date: 2019/1/14 16:28
     */
    function mutualTransfer()
    {
        $cuid = I('cuid');
        $phone = I('phone');
        $email = I('email');
        $member_id = I('member_id');
        $url = I('url');

        $status = I('status', null);
        if ('' == $status) {
            $status = null;
        }
        $tphone = I('tphone');
        $temail = I('temail');
        $tmember_id = I('tmember_id');
        $turl = I('turl');
        if (!empty($cuid)) {
            $where['tb.currency_id'] = I("cuid");
            $this->assign("id", I("cuid"));
        }
        $where['tb.b_type'] = 0;
        $where['tb.transfer_type'] = "2";//内部互转类型
        if (!empty($phone)) {
            $where['m.phone'] = array('like', '%' . $phone . '%');
        }
        if (!empty($email)) {
            $where['m.email'] = array('like', '%' . $email . '%');
        }
        if (!empty($member_id)) {
            $where['tb.from_member_id'] = $member_id;
        }
        if (!empty($url)) {
            $where['tb.from_url'] = array('like', '%' . $url . '%');
        }

        if (!empty($tphone)) {
            $where['tm.phone'] = array('like', '%' . $tphone . '%');
        }
        if (!empty($temail)) {
            $where['tm.email'] = array('like', '%' . $temail . '%');
        }
        if (!empty($tmember_id)) {
            $where['tb.to_member_id'] = $tmember_id;
        }

        if (!empty($turl)) {
            $where['tb.to_url'] = array('like', '%' . $turl . '%');
        }

        if (!is_null($status) and in_array($status, Tibi::ALL_STATUS_ARRAY)) {
            $where['tb.status'] = $status;
        }

        $field = "tb.*,m.email,m.name,m.phone,c.currency_name,
        c.currency_type,m.remarks,tm.name as tname,tm.phone as tphone, tm.email as temail";

        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = Db::name("Tibi")->alias("tb")->field($field)->where($where)
            ->join(config("database.prefix")."member m","m.member_id=tb.from_member_id","LEFT")
            ->join(config("database.prefix")."member tm","tm.member_id=tb.to_member_id","LEFT")
            ->join(config("database.prefix")."currency c","c.currency_id=tb.currency_id","LEFT")

//            ->join("yang_member on yang_tibi.from_member_id=yang_member.member_id")
//            ->join("yang_member tm on yang_tibi.to_member_id=tm.member_id")
//            ->join("yang_currency on yang_tibi.currency_id=yang_currency.currency_id")
            ->order("add_time desc")->paginate(20,null,['query'=>input()]);
        $show=$list->render();
        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出
        //读取积分类型表

        $curr = Db::name("Currency")->select();
        $this->assign("curr", $curr);

        $temp['cuid'] = $cuid;
        $temp['member_id'] = $member_id;
        $temp['phone'] = $phone;
        $temp['email'] = $email;
        $temp['url'] = $url;
        $temp['tmember_id'] = $tmember_id;
        $temp['tphone'] = $tphone;
        $temp['temail'] = $temail;
        $temp['turl'] = $turl;
        $this->assign('status_enum', Tibi::STATUS_ENUM);
        $this->assign("temp", $temp);

        //昨日互转总数量
        $today_begin = todayBeginTimestamp();
        $tibi_total = Db::name('tibi')->field('sum(num) as num,sum(actual) as actual')->where([
            'transfer_type' => '2',
            'b_type' => 0,
            'status' => ['in',[-2,-1,0,1] ],
            'add_time' => ['between',[$today_begin-86400,$today_begin]],
        ])->find();
        $this->assign('tibi_total', $tibi_total);

        //今日互转总数量
        $tibi_total_today = Db::name('tibi')->field('sum(num) as num,sum(actual) as actual')->where([
            'transfer_type' => '2',
            'b_type' => 0,
            'status' => ['in',[-2,-1,0,1] ],
            'add_time' => ['gt',$today_begin],
        ])->find();
        $this->assign('tibi_total_today', $tibi_total_today);

        return $this->fetch();
    }

    /**
     * 新会员列表
     * @throws Exception
     * Created by Red.
     * Date: 2019/3/14 11:03
     */
    public function member_index()
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

        $count = $model->alias('member')->join("left join yang_boss_plan_info as boss on boss.member_id = member.member_id ")->where($where)->count();// 查询满足要求的总记录数
        $Page = new Page($count, 15);// 实例化分页类 传入总记录数和每页显示的记录数(25)

        //给分页传参数
        setPageParameter($Page, array('email' => $email, 'member_id' => $member_id, 'name' => $name, 'phone' => $phone, 'status' => $status, 'is_award' => $is_award, 'pid' => $pid));

        $show = $Page->show();// 分页显示输出

        $list = $model->alias('member')->where($where)->join("left join yang_boss_plan_info as boss on boss.member_id = member.member_id ")->field("member.*,boss.pid as boss_pid,boss.member_id as boss_member_id")->order("member.member_id desc ")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        // $field = "member.*,ifnull(verify_file.verify_state, 0) as verify_state";
        // $list = $model->alias('member')->join("left join " . C("DB_PREFIX") . "verify_file as verify_file on member.member_id = verify_file.member_id")->field($field)->where($where)->order("member.member_id desc ")->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出
        $this->display(); // 输出模板
    }

    /**
     * 每天提币排行榜
     * @return mixed
     * @throws Exception
     * Created by Red.
     * Date: 2019/3/19 18:48
     */
    function everydayOutCoin()
    {
        $data['type'] = input('type');//转账类型：1为区块链转账，2为系统内互转,不传则查询全部的
        $data['currency_id'] = input("currencyid");
        $data['order'] = input("order", "desc");
        $where['t.status'] = 1;//提币成功的
        $data['startTime'] = input("startTime");
        $data['endTime'] = input("endTime");
        $data['rows'] = input('rows', 20);
        $page = input('page', 1);
        $data['sort'] = ($page - 1) * $data['rows'];
        if (empty($data['startTime'])) {
            $data['startTime'] = date("Y-m-d", time());
            $startTime = todayBeginTimestamp();
        } else {
            $startTime = strtotime($data['startTime']);
        }
        if (empty($data['endTime'])) {
            $data['endTime'] = date("Y-m-d", time());
            $endTime = todayEndTimestamp();
        } else {
            $endTime = strtotime($data['endTime']) + 86399;
        }
        $where['t.add_time'] = array('between', array($startTime, $endTime));
        $currency = Db::name("currency")->field("currency_id,currency_name")->order("sort asc")->select();
        $currency = array_column($currency, null, 'currency_id');
        if (!empty($data['currency_id'])) {
            $where['t.currency_id'] = $data['currency_id'];
        } else {
            reset($currency);//获取第一个键
            $where['t.currency_id'] = key($currency);
            $data['currency_id'] = key($currency);
        }
        if ($data['type'] == 1) {
            $where['transfer_type'] = "1";
        } elseif ($data['type'] == 2) {
            $where['transfer_type'] = "2";
        }
        $model = Db::name("tibi");
        $field = "t.from_member_id,t.currency_id,sum(t.num) as sum,m.email,m.phone,m.nick,m.name,m.remarks,c.currency_name";
        $list = $model->alias('t')->where("(t.from_member_id is not null or t.from_member_id !='' )")->where($where)
            ->join(config("database.prefix") . "member m", "m.member_id=t.from_member_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "c.currency_id=t.currency_id", "LEFT")
            ->field($field)->order("sum " . $data['order'])->group("t.from_member_id")
            ->paginate($data['rows'], null, ['query' => input()])->each(function ($value, $key) {
                $value['transfer_sum'] = Db::name("accountbook")->where(['member_id' => $value['from_member_id'], 'type' => 18, 'number_type' => 2, 'currency_id' => $value['currency_id']])->sum("number");
                return $value;
            });
        $show = $list->render();
        $data['orderList'] = ["asc" => "从低到高", "desc" => "从高到低"];
        $data['typeList'] = ["1" => "区块链提币", "2" => "平台内互转"];
        $this->assign("data", $data);
        $this->assign("currency_id", $data['currency_id']);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('list', $list);// 赋值数据集
        $this->assign('currency', $currency);
        return $this->fetch();
    }


    /**
     * 提币总排行榜
     * @return mixed
     * @throws Exception
     * Created by Red.
     * Date: 2019/3/19 18:55
     */
    function allOutCoin()
    {
        $data['type'] = input('type');//转账类型：1为区块链转账，2为系统内互转,不传则查询全部的
        $data['currency_id'] = input("currencyid");
        $data['order'] = input("order", "desc");
        $where['t.status'] = 1;//提币成功的
        $data['p'] = input("page", 1);
        $data['rows'] = 20;
        $data['sort'] = ($data['p'] - 1) * $data['rows'];
        $currency = Db::name("currency")->field("currency_id,currency_name")->order("sort asc")->select();
        $currency = array_column($currency, null, 'currency_id');
        if (!empty($data['currency_id'])) {
            $where['t.currency_id'] = $data['currency_id'];
        } else {
            reset($currency);//获取第一个键
            $where['t.currency_id'] = key($currency);
            $data['currency_id'] = key($currency);
        }
        if ($data['type'] == 1) {
            $where['transfer_type'] = "1";
        } elseif ($data['type'] == 2) {
            $where['transfer_type'] = "2";
        }
        $model = Db::name("tibi");
        $field = "t.from_member_id,t.currency_id,sum(t.num) as sum,m.email,m.phone,m.nick,m.name,m.remarks,c.currency_name";
        $list = $model->alias('t')->where("(t.from_member_id is not null or t.from_member_id !='' )")->field($field)->where($where)
            ->join(config("database.prefix") . "member m", "m.member_id=t.from_member_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "c.currency_id=t.currency_id", "LEFT")
            ->order("sum " . $data['order'])->group("t.from_member_id")
            ->paginate($data['rows'], null, ['query' => input()])->each(function ($value, $key) {
                $value['transfer_sum'] = Db::name("accountbook")->where(['member_id' => $value['from_member_id'], 'type' => 18, 'number_type' => 2, 'currency_id' => $value['currency_id']])->sum("number");
                return $value;
            });
        $show = $list->render();
        $data['orderList'] = ["asc" => "从低到高", "desc" => "从高到低"];
        $data['typeList'] = ["1" => "区块链提币", "2" => "平台内互转"];
        $this->assign("data", $data);
        $this->assign("currency_id", $data['currency_id']);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('list', $list);// 赋值数据集
        $this->assign('currency', $currency);
        return $this->fetch();
    }

    /**
     * 汇总地址管理
     * @return mixed
     * Created by Red.
     * Date: 2019/4/29 13:58
     */
    function summary_address_list()
    {
        $list = Db::name("summary_address")->order("sa_is_default asc")->select();
        $currencyList = Db::name("currency")->field("currency_id,currency_mark")->select();
        $arrayList = array_column($currencyList, null, 'currency_id');
        if (!empty($list)) {
            foreach ($list as &$value) {
                $value['is_default'] = $value['sa_is_default'] == 1 ? "是" : "否";
                $value['sa_currency_id'] = $arrayList[$value['sa_currency_id']]['currency_mark'];
            }
        }
        $this->assign("list", $list);
        return $this->fetch();
    }

    /**
     * 添加汇总地址页面
     * Created by Red.
     * Date: 2019/4/29 14:19
     */
    function add_summary_address()
    {
        if ($_POST) {
            $data = $_POST;
            if (!in_array($data['sa_is_default'], [1, 2])) {
                $data['sa_is_default'] = 2;
            }
            $find = Db::name("summary_address")->where(['sa_address' => $data['sa_address'], 'sa_tag' => $data['sa_tag'], 'sa_currency_id' => $data['sa_currency_id']])->find();
            if (!empty($find)) {
                return $this->error("该汇总地址已存在");
            }
            $add = Db::name("summary_address")->insertGetId($data);
            if ($add) {
                Db::name("summary_address")->where('sa_id!=' . $add . ' and sa_currency_id=' . $data['sa_currency_id'])->update(['sa_is_default' => 2]);
                return $this->success("添加成功");
            } else {
                return $this->success("添加失败");
            }
        }
        $select = "'XRP'";
        $list = Db::name("currency")->field('currency_id,currency_mark')->where('currency_mark in (' . $select . ')')->select();
        $this->assign("list", $list);
        return $this->fetch();
    }

    /**
     * 删除汇总地址
     * Created by Red.
     * Date: 2019/4/29 15:38
     */
    function delete_summary_address()
    {
        $sa_id = I("post.sa_id");
        if (!empty($sa_id)) {
            $delete = Db::name("summary_address")->where(['sa_id' => $sa_id])->delete();
            if ($delete) {
                $this->ajaxReturn(['code' => SUCCESS, 'msg' => '删除成功']);
            } else {
                $this->ajaxReturn(['code' => ERROR1, 'msg' => '删除失败']);
            }
        }
        return $this->ajaxReturn(['code' => ERROR1, 'msg' => '参数错误']);
    }

    /**
     * 设置默认汇总地址
     * Created by Red.
     * Date: 2019/4/29 15:41
     */
    function set_default_summary()
    {
        $sa_id = I("post.sa_id");
        if (!empty($sa_id)) {
            $find = Db::name("summary_address")->where(['sa_id' => $sa_id])->find();
            $delete = Db::name("summary_address")->where(['sa_id' => $sa_id])->update(['sa_is_default' => 1]);;
            if ($delete) {
                Db::name("summary_address")->where('sa_id!=' . $sa_id . ' and sa_currency_id=' . $find['sa_currency_id'])->update(['sa_is_default' => 2]);
                $this->ajaxReturn(['code' => SUCCESS, 'msg' => '设置成功']);
            } else {
                $this->ajaxReturn(['code' => ERROR1, 'msg' => '设置失败']);
            }
        }
        return $this->ajaxReturn(['code' => ERROR1, 'msg' => '参数错误']);
    }
    function qrcode()
    {
        $id = I("id");
        $field="t.*,c.currency_name,c.currency_type";
        $find = Db::name("tibi")->alias("t")->field($field)->where(['t.id' => $id, 't.status' => -1])->join(config("database.prefix")."currency c","c.currency_id=t.currency_id","LEFT")->find();
        if (is_null($find['admin_id1']) or is_null($find['admin_id2'])) {
            return "请先完成1审和2审";
        }

        if (!empty($find)) {
            $this->assign("data",$find);
            return $this->fetch();
        } else {
            echo "不是审核的数据，请刷新页面";
        }
    }
    /**
     * 生成二维码
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * Create by: Red
     * Date: 2019/12/18 14:46
     */
    function qrcode_img(){
        $to_url=input("to_url");
        $currency_type=input("currency_type");
        $money=input("money");
        if(!empty($to_url)){
                if(in_array($currency_type,['btc','usdt'])){
                    $qrcode="bitcoin:".$to_url."?amount=".$money;
                }else{
                    $qrcode=$to_url;
                }
        }
        require_once EXTEND_PATH.'phpqrcode'.DS.'phpqrcode.php';
        QRcode::png($qrcode, false,  'Q', '6', '2');
        header("Content-type: image/png");
        die();
    }
//    function test(){
//        $list= M()->query('select from_member_id,sum(num) as total from yang_tibi WHERE from_member_id in(select child_id from yang_member_bind WHERE member_id=88941 and level<=5)  and `status`=1 AND currency_id=8 GROUP BY from_member_id ORDER BY total desc limit 50');
//        if($list){
//            foreach ($list as &$value){
//                $find=M("member_bind")->where(['child_id'=>$value['from_member_id'],'member_id'=>88941])->find();
//                $value= array_merge($value,$find);
//                $member=M("member")->where(['member_id'=>$value['from_member_id']])->field("phone,name,email")->find();
//                $value= array_merge($value,$member);
//            }
////            var_dump($list);
//        }
//        $xlsCell = array(
//            array('from_member_id', '用户id'),
//            array('total', '提币总数'),
//            array('phone', '手机'),
//            array('name', '真实姓名'),
//            array('level', '所在层级'),
//            array('child_level', '社区等级'),
//            array('email', '会员邮箱'),
//        );
//        $this->exportExcel("前50提币排名", $xlsCell, $list);
//        die();
//    }

//        function test(){
//        $list= M()->query('SELECT member_id,SUM(number) as sum FROM yang_accountbook WHERE type=18 AND number_type=2 AND currency_id=8 GROUP BY member_id ORDER BY sum DESC LIMIT 100');
//            if($list){
//                foreach ($list as &$value){
//                    $member=M("member")->where(['member_id'=>$value['member_id']])->field("phone,name,email,remarks")->find();
//                    $sum=M()->query("SELECT SUM(num) as sum FROM yang_tibi WHERE from_member_id=".$value['member_id']." AND `status`=1 AND currency_id=8");
//                    $value['tibi_sum']=$sum[0]['sum'];
//                    $value= array_merge($value,$member);
//                }
//            }
//            $xlsCell = array(
//            array('member_id', '用户id'),
//            array('phone', '手机'),
//            array('name', '真实姓名'),
//            array('email', '邮箱'),
//            array('remarks', '备注'),
//            array('sum', '内转总数'),
//            array('tibi_sum', '提币总数'),
//        );
//        $this->exportExcel("内转100名排名", $xlsCell, $list);
//        die();
//        }

    /**
     * @param Request $request
     * @return array|mixed|Json
     * @throws DbException
     */
    public function modifyTag(Request $request)
    {
        if ($request->isPost()) {
            $tag = $request->post('tag');
            $tx = $request->post('tx');
            if (empty($tx) or empty($tag) or $tag <= 0) {
                return successJson(ERROR1, "参数错误");
            }

            return successJson(CurrencyLog::modifyTag($tx, $tag));
        }


        $recharge_wallet = \app\common\model\WalletAdminAddress::where(['waa_type'=>'recharge'])->select();
        $where = [
            'status' => ['in',[4, 2]],
        ];
        $where['ato'] = [];
        foreach ($recharge_wallet as $wallet) {
            $where['ato'][] = ['like','%'.$wallet['waa_address'].'%'];
        }
        $where['ato'][] = 'or';

        $list = CurrencyLog::field('*')
            ->where($where)
            ->order('add_time', 'desc')
            ->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, ['list' => $list, 'count' => $count, 'page' => $page]);
    }

    /**
     * 充币错误处理
     * 两种方式
     * 1. 查询不是【充提币地址】的区块记录 带标签的币种异常，不能使用该种方式
     * 2. 查询本平台分配充值地址的区块记录 有大量汇总充手续费的记录
     * @param Request $request
     * @return array|mixed|Json
     * @throws DbException
     */
    public function chargeError(Request $request)
    {
        if ($request->isPost()) {
            $tx = $request->post('tx');
            if (empty($tx)) {
                return successJson(ERROR1, "参数错误");
            }
            return successJson(CurrencyLog::chargeError($tx));
        }

        $system_address_total = [];
        $system_address = \app\common\model\Currency::field('qianbao_address,tibi_address,summary_fee_address')->select();
        if($system_address) {
            array_map(function ($value) use (&$system_address_total) {
                array_push($system_address_total, $value['qianbao_address'], $value['tibi_address'], $value['summary_fee_address']);
            }, $system_address->toArray());

            $system_address_total = array_unique($system_address_total);
        }

        $where = [
            't1.status' => ['in', [4, 2]],
        ];
        if(!empty($system_address_total)) {
            $where['t1.afrom'] = ['not in',$system_address_total];
            $where['t1.ato'] = ['not in',$system_address_total];
        }
        $list = CurrencyLog::alias('t1')->where($where)
            ->order('add_time', 'desc')
            ->paginate(null, false, ['query' => $request->get()]);


//        $where = [
//            't1.status' => ['in', [4, 2]],
//        ];
//        $list = CurrencyLog::alias('t1')
//            ->join([config("database.prefix") . 'currency_user' => 't2'], 't1.ato = t2.chongzhi_url')
//            ->field(['t1.*', 't2.chongzhi_url'])
//            ->where($where)
//            ->order('add_time', 'desc')
//            ->paginate(null, false, ['query' => $request->get()]);

        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, ['list' => $list, 'count' => $count, 'page' => $page]);
    }

    public function rechargeList(Request $request)
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
        return $this->fetch(null, ['list' => $list, 'count' => $count, 'page' => $page, 'enum' => $enum]);
    }


    public function rechargeSuccess(Request $request)
    {
        $id = $request->post('id', 0, 'intval');
        $tx = $request->post('tx', null);
        $from = $request->post('from_address', null);
        $fee = $request->post('fee', 0);
        $verifyNumber = $request->post('verify_number', 0);

        $r = [
            'code' => ERROR1,
            'message' => "参数错误",
            'result' => null
        ];
        if (empty($id) or empty($tx) or empty($from) or $fee <= 0 or $verifyNumber <= 0) {
            return mobileAjaxReturn($r);
        }

        try {
            Db::startTrans();
            // 查询充币数据
            $data = Recharge::where('id', $id)->find();
            if ($data['status'] != Recharge::STATUS_VERIFY) {
                throw new Exception("该数据已审核!");
            }

            $flag = AccountBook::add_accountbook($data['user_id'], $data['currency_id'], 5, 'lan_chongbi', 'in', $verifyNumber, $data['id']);
            if (empty($flag)) {
                throw new Exception("系统错误，请稍后再试!");
            }
            $userCurrency = \app\common\model\CurrencyUser::getCurrencyUser($data['user_id'], $data['currency_id']);
            $userCurrency['num'] += $verifyNumber;
            if (!$userCurrency->save()) {
                throw new Exception("系统错误，请稍后再试!");
            }

            $data['fee'] = $fee;
            $data['from'] = $from;
            $data['tx'] = $tx;
            $data['verify_number'] = $verifyNumber;
            $data['status'] = Recharge::STATUS_SUCCESS;
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


    public function rechargeFail(Request $request)
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

    public function takePush(Request $request)
    {
        $where = [];
        $userId = $request->post('user_id', 0, 'intval');
        if ($userId) {
            $where['user_id'] = $userId;
        }

        // 发送地址
        $afrom = $request->get('afrom', '');
        if($afrom) $where['afrom'] = $afrom;
        // 接收地址
        $ato = $request->get('ato', '');
        if($ato) $where['ato'] = $ato;
        // Token/Tag
        $token = $request->get('token', '');
        if($token) $where['token'] = $token;

        $list = (new TakePush())->where($where)->with('currency')->paginate(null, false, ["query" => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        return $this->fetch(null, compact('list', 'page', 'count'));
    }
}
