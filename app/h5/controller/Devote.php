<?php

namespace app\h5\controller;

use app\common\model\Config;
use app\common\model\Currency;
use app\common\model\CurrencyUser;
use app\common\model\GoodsMainOrders;
use app\common\model\Member;
use app\common\model\MemberBind;
use app\common\model\Recharge;
use app\common\model\ShopConfig;
use app\common\model\WechatBind;
use app\common\model\WechatTransfer;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\exception\PDOException;

class Devote extends Base
{
    /**
     * 贡献值页面
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function DevotePage()
    {
        // 累计贡献值
        $grand_total = Db::name('accountbook')
            ->where(['member_id' => $this->member_id, 'number_type' => 1])
            ->whereIn('type', [119, 120])->sum('number');

        // 本周预估到账贡献值
        $estimate_result = $this->Estimate();
        if ($estimate_result['code'] == ERROR1) {
            $this->output_new($estimate_result);
        }
        $estimate = $estimate_result['result']['total_sum'];

        // 保持有效账户，每个月需自动扣除10 个贡献值。
        $effective_fee = Db::name('yn_config')->where(['key' => 'month_reduce_num'])->value('value', 0);
        // 可提现的贡献值余额
        $CurrencyUser = CurrencyUser::getCurrencyUser($this->member_id, Currency::GXZ_ID);

        // 微信提现开关
        $wechat_withdraw_switch = Config::get_value('wechat_withdraw_switch', 0);
        $result = [
            'grand_total' => $grand_total,
            'estimate' => $estimate,
            'effective_fee' => $effective_fee,
            'balance' => $CurrencyUser->num,
            'wechat_withdraw_switch' => $wechat_withdraw_switch,
        ];
        $this->output_new(10000, lang('data_success'), $result);
    }

    /**
     * 贡献值 - 提现页面
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function WithdrawPage()
    {
        $currency_id = ShopConfig::get_value('reward_currency_id');
        // 可提现的贡献值余额
        $CurrencyUser = CurrencyUser::getCurrencyUser($this->member_id, $currency_id);
        // 提现手续费
//        $wechat_withdraw_fee = Config::get_value('wechat_withdraw_fee', 0);

        $currency = Currency::where(['currency_id' => Currency::TRC20_ID])->find();

        // 微信信息
        $WechatBind = WechatBind::where(['member_id' => $this->member_id, 'status' => 1])
            ->field(['id' => 'wxid', 'actual_name', 'wechat_account'])->find();
        // 银行卡信息
        $BankBind = \app\common\model\MemberBank::where(['member_id' => $this->member_id, 'status' => 1])
            ->field('id,truename as actual_name,bankcard as bank_card,bankadd as open_bank,bankname as bank_id')->find();
        if ($BankBind) {
            $BankBind['bank_name'] = '';
            $banklist = Db::name('banklist')->where(['id' => $BankBind['bank_id']])->find();
            if ($banklist) {
                $BankBind['bank_name'] = $banklist['name'];
            }
        }

        // 保持有效账户，每个月需自动扣除10 个贡献值。
//        $month_reduce_num = Db::name('yn_config')->where(['key' => 'month_reduce_num'])->value('value', 0);
//        $balance = bcsub($CurrencyUser->num, $month_reduce_num, 6);

        // 火米价格比例
        $hm_price = ShopConfig::get_value('hm_price', 6.1);
        $result = [
//            'balance' => $balance > 0 ? $balance : 0.000000,
//            'effective_fee' => $month_reduce_num,
            'balance' => $CurrencyUser['num'],
            'hm_price' => $hm_price,
            'withdraw_fee' => $currency['fee_greater'],
            'currency_min_tibi' => $currency['currency_min_tibi'],
            'wechat_info' => $WechatBind,
            'bank_info' => $BankBind
        ];
        $this->output_new(10000, lang('data_success'), $result);
    }

    /**
     * 贡献值提现 - 绑定微信
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function WeChatBind()
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        // 判断前端数据
        $actual_name = input('actual_name', '');
        $wechat_account = input('wechat_account', '');
        $phone_code = input('phone_code', 0);
        $openid = input('openid', '');
        if (empty($actual_name)
            || empty($wechat_account) || empty($phone_code)
        ) {
            $this->output_new($r);
        }
        $userInfo = Db::name('member')->where(['member_id' => $this->member_id])->find();
        $phone = $userInfo['send_type'] == 1 ? $userInfo['phone'] : $userInfo['email'];
        $senderLog = model('Sender')->check_log($userInfo['send_type'], $phone, 'modifypwd', $phone_code);
        if (is_string($senderLog)) $this->output(ERROR1, $senderLog);

        // 判断是否绑定
        $WechatBind = WechatBind::where(['member_id' => $this->member_id])->find();
        if (!empty($WechatBind)) {
            $r['message'] = lang('wechat_already_bind');
            $this->output_new($r);
        }

        // 保存数据
        $data = [
            'member_id' => $this->member_id,
            'actual_name' => $actual_name,
            'wechat_account' => $wechat_account,
            'add_time' => time(),
            'status' => 1,
        ];

        // 判断是否有openid
        if (!empty($openid)) $data['openid'] = $openid;

        $result = WechatBind::insert($data);
        if (empty($result)) {
            $r['message'] = lang('lan_reg_the_network_busy');
            $this->output_new($r);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_operation_success');
        $this->output_new($r);
    }

    /**
     * 贡献值提现 - 绑定银行卡
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function BankBind()
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        // 判断前端数据
        $actual_name = input('actual_name', '');
        $bank_card = input('bank_card', '');
        $open_bank = input('open_bank', '');
        $bank_name = input('bank_name', '');
        $phone_code = input('phone_code', 0);
        if (empty($actual_name) || empty($bank_card) || empty($open_bank) || empty($phone_code)) {
            $this->output_new($r);
        }
        $userInfo = Db::name('member')->where(['member_id' => $this->member_id])->find();
        $phone = $userInfo['send_type'] == 1 ? $userInfo['phone'] : $userInfo['email'];
        $senderLog = model('Sender')->check_log($userInfo['send_type'], $phone, 'modifypwd', $phone_code);
        if (empty($phone_code) || is_string($senderLog)) {
            $this->output(ERROR1, $senderLog);
        }

        // 判断是否绑定
        $BankBind = \app\common\model\MemberBank::where(['member_id' => $this->member_id])->find();
        // 保存数据
        $data = [
            'member_id' => $this->member_id,
            'truename' => $actual_name,
            'bankcard' => $bank_card,
            'bankname' => $bank_name,
            'bankadd' => $open_bank,
            'add_time' => time(),
            'status' => 1,
        ];
        if (!empty($BankBind)) {
            $result = \app\common\model\MemberBank::where(['id' => $BankBind['id']])->update($data);
            if ($result === false) {
                $r['message'] = lang('lan_reg_the_network_busy');
                $this->output_new($r);
            }
        } else {
            $result = \app\common\model\MemberBank::insert($data);
            if (empty($result)) {
                $r['message'] = lang('lan_reg_the_network_busy');
                $this->output_new($r);
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_operation_success');
        $this->output_new($r);
    }

    public function banklist()
    {
        $list = model('Bank')->banklist($this->lang);
        $this->output(10000, lang('lan_operation_success'), $list);
    }

    /**
     * 微信绑定 - 发验证码
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function SendSms()
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];
        $userInfo = Db::name('member')->where(['member_id' => $this->member_id])->find();
        if ((empty($userInfo['country_code']) && empty($userInfo['phone'])) && empty($userInfo['email'])) {
            $r['message'] = '对应的手机或邮箱不正确';
            $this->output_new($r);
        }

        if (!empty($userInfo['phone']) && $userInfo['send_type'] == 1) {//手机
            $send_result = model('Sender')->send_phone($userInfo['country_code'], $userInfo['phone'], 'modifypwd');
        } elseif (!empty($userInfo['email'])) {//邮箱
            $send_result = model('Sender')->send_email($userInfo['email'], 'modifypwd');
        }

        if (is_string($send_result)) $this->output(ERROR1, $send_result);
        $this->output(SUCCESS, lang('lan_user_send_success'));
    }

    /**
     * 贡献值提现 - 提交申请
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws PDOException
     */
    public function WithdrawSubmit()
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];
        $number = input('number', 0);
        $wxid = input('wxid', 0);
        $type = input('type', 1);
        $phone_code = input('phone_code');
        $paypwd = input('paypwd');
        if (empty($number) || empty($wxid)) {
            $this->output_new($r);
        }

        $password = Member::verifyPaypwd($this->member_id, $paypwd);
        if (empty($paypwd) || $password['code'] != SUCCESS) {
            $this->output(ERROR1, lang("lan_verification_not_pass"));
        }

        // 敏感用户禁止提币
        $userInfo = Db::name('member')->where(['member_id' => $this->member_id])->find();
        if (1 == $userInfo['is_sensitive']) {
            $r['message'] = lang('operation_deny');
            $this->output_new($r);
        }

        $phone = $userInfo['send_type'] == 1 ? $userInfo['phone'] : $userInfo['email'];
        $senderLog = model('Sender')->check_log($userInfo['send_type'], $phone, 'modifypwd', $phone_code);
        if (empty($phone_code) || is_string($senderLog)) {
            $this->output(ERROR1, $senderLog);
        }

        $result = WechatTransfer::WithdrawSubmit($this->member_id, $wxid, $number, $type);
        $this->output_new($result);
    }

    // 贡献值提现记录
    public function WithdrawLog()
    {
        $page = input('page', 1);
//        $currency_id = input('currency_id', 0);
        $currency_id = ShopConfig::get_value('reward_currency_id');
        $result = WechatTransfer::WithdrawLog($this->member_id, $currency_id, $page);
        $this->output_new($result);
    }

    // 取消提现
    public function WithdrawCancel()
    {
        $id = input('id', 0);
        $result = WechatTransfer::WithdrawCancel($this->member_id, $id);
        $this->output_new($result);
    }

    /**
     * 本周预估 - 记录明细
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function EstimateLog()
    {
        $result = $this->Estimate();
        $this->output_new($result);
    }

    /**
     * 本周预估 - 计算
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    private function Estimate()
    {
        $currency_id = Currency::GXZ_ID;
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];
        $rebate_config = Db::name('yn_config')->select();
        if (empty($rebate_config)) {
            $r['message'] = lang('lan_reg_the_network_busy');
            return $r;
        }
        $rebate_config = array_column($rebate_config, 'value', 'key');

        $Member = Member::where(['active_status' => 1, 'member_id' => $this->member_id])->find();
        if (!$Member) {
            $r['code'] = SUCCESS;
            $r['message'] = lang('data_success');
            $r['result'] = ['total_sum' => 0, 'loglist' => null];
            return $r;
        }

        // 三代的用户
        $child_data = MemberBind::where(['member_id' => $this->member_id, 'level' => ['elt', 3]])->column('level', 'child_id');
        $gmo_user_id = array_keys($child_data);

        $giftGoodsId = ShopConfig::get_value('new_gift_goods_id', 1);

        $OrdersData = GoodsMainOrders::alias('o')
            ->join(config("database.prefix") . "member m", "m.member_id = o.gmo_user_id", "LEFT")
            ->join(config('database.prefix') . 'goods_orders b', 'b.go_id IN (o.gmo_go_id)', 'LEFT')
            ->where(function ($query) use ($currency_id, $gmo_user_id) {
                $query->where(function ($query) use ($currency_id) {
                    $query->where(['o.gmo_user_id' => $this->member_id, 'o.gmo_rebate_self_id' => $currency_id]);
                });
                $query->whereOr(function ($query) use ($gmo_user_id, $currency_id) {
                    $query->where(['o.gmo_user_id' => ['in', $gmo_user_id], 'o.gmo_rebate_parent_id' => $currency_id]);
                });
            })
            ->where('b.go_goods_id', 'gt', $giftGoodsId)
            ->whereIn('o.gmo_status', [1, 3])
            ->whereTime('o.gmo_add_time', 'w')
            ->field(['o.gmo_id', 'o.gmo_user_id', 'o.gmo_add_time', 'o.gmo_status', 'o.gmo_rebate_self', 'o.gmo_rebate_parent', 'm.ename' => 'username'])
            ->order('o.gmo_id', 'desc')->select();

        $total_sum = 0;
        foreach ($OrdersData as &$value) {
            if ($value['gmo_user_id'] == $this->member_id) {
                $value['gmo_rebate'] = $value['gmo_rebate_self'];
            } else {
                $value['gmo_rebate'] = $value['gmo_rebate_parent'] * $rebate_config['rebeat_percent' . $child_data[$value['gmo_user_id']]] / 100;
            }
            $value['gmo_status'] = ($value['gmo_status'] == 1) ? lang('to_be_delivered') : lang('to_be_received');
            $value['gmo_add_time'] = date('Y-m-d H:i:s', $value['gmo_add_time']);
            $total_sum += $value['gmo_rebate'];
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = ['total_sum' => floattostr($total_sum), 'loglist' => $OrdersData];
        return $r;
    }

    /**
     * 贡献值充值
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function recharge()
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        // 判断参数
        $number = input('number', 0);
        $currency_id = input('currency_id', 0);
        $img = input('img', '');
        if (empty($number) || empty($currency_id) || empty($img)) {
            $this->output_new($r);
        }

        $Recharge = Recharge::where(['user_id' => $this->member_id, 'currency_id' => $currency_id, 'status' => Recharge::STATUS_VERIFY])->find();
        if ($Recharge) {
            $r['message'] = lang('please_wait_while_reviewing');
            $this->output_new($r);
        }

        // base64文件大小
        $checkFileSize = $this->checkFileSize($img);
        if (!$checkFileSize) {
            $r['message'] = lang('lan_picture_to_big');
            $this->output_new($r);
        }

        // 上传凭证
        $attachments_list = $this->oss_base64_upload($img, 'recharge');
        if (empty($attachments_list) || $attachments_list['Code'] === 0 || count($attachments_list['Msg']) == 0) {
            $r['message'] = lang('lan_network_busy_try_again');
            $this->output_new($r);
        }

        // 保存数据
        $img = $attachments_list['Msg'][0];
        $result = Recharge::insert([
            'user_id' => $this->member_id,
            'currency_id' => $currency_id,
            'number' => $number,
            'img' => $img,
            'status' => Recharge::STATUS_VERIFY,
            'add_time' => time()
        ]);

        if (!$result) {
            $r['message'] = lang('lan_reg_the_network_busy');
            $this->output_new($r);
        }
        $this->output(SUCCESS, lang('lan_operation_success'));
    }

    /**
     * 贡献值充值记录
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function rechargeLog()
    {
        $r = ['code' => ERROR1, 'message' => lang('parameter_error'), 'result' => null];

        // 判断参数
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $currency_id = input('currency_id', 0);
        if (empty($currency_id)) {
            $this->output_new($r);
        }

        $Recharge = Recharge::where(['user_id' => $this->member_id, 'currency_id' => $currency_id])
            ->field(['number', 'status', 'add_time', 'message2'])
            ->limit($page - 1, $page_size)->select();
        if (!$Recharge) {
            $r['message'] = lang('not_data');
            $this->output_new($r);
        }

        foreach ($Recharge as &$value) {
            $value['add_time'] = date('Y-m-d H:i:s', $value['add_time']);
        }

        $this->output(SUCCESS, lang('data_success'), $Recharge);
    }
}
