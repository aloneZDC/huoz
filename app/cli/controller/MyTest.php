<?php
//定时任务
namespace app\cli\controller;

use app\common\model\AccountBook;
use app\common\model\AloneMiningArchive;
use app\common\model\CommonMiningMember;
use app\common\model\CommonMiningPay;
use app\common\model\CurrencyUser;
use app\common\model\InsideConfig;
use app\common\model\InsideOrder;
use app\common\model\Member;
use app\common\model\MemberBank;
use app\common\model\MemberBind;
use app\common\model\RocketOrder;
use app\common\model\YunMiningPay;
use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\Db;
use think\Exception;
use think\Log;
use think\Request;
use app\common\model\ChiaMiningMember;
use app\common\model\ChiaMiningPay;

class MyTest extends Command
{
    protected $name = '测试定时任务';

    protected function configure()
    {
        $this->setName('MyTest')->setDescription('This is a test');
    }

    protected function execute(Input $input, Output $output)
    {
        Request::instance()->module('cli');
        $this->doRun();
    }

    public function doRun()
    {
        //$this->commonTeamNum(); // 满存算力
        //$this->aloneTeamNum(); // 独享矿机
//        $this->chiaTeamNum();//chia矿机
        // 撤销所有进行中的订单
//        $this->otc_revoke_order();
        //生成方舟用户汇总
//        $this->settlement_reward();
//        $this->password();
//        (new MtkMiningTask())->doRun();// MTK矿机
        $this->export();
    }

    protected function export()
    {

        $log1 = '"原始id","新ID","上级id","密码","手机","新手机","邮箱","火米可用","火米冻结","预约余额","闯关未结算","金米可用","金米冻结","赠送可用","赠送冻结","M配额可用","M配额冻结","MTK可用","MTK冻结","MTK加速器","关系链"' ;
        $this->_Log_For_M("excel", $log1);

        $idbase = 100000;
        $_pwd = 'ABCDEF';

        // phone in ('13006947395','13014198738')
        $pre_phone = "";
        $cur_phone = "";
        //->where("phone in ('13001296167','13006947395','13014198738','13036844068','13002022828')")
        $index = 0;
        $Member = Member::field('member_id,pid,phone,email')->order('phone asc')
//            ->limit(10)
            ->select();
        foreach ($Member as $value) {
            $log = "";

            echo $index . ' - ' . $value['member_id'] . "\n";
            $index++;

            $cur_phone = trim($value['phone']);
            if ($pre_phone == $cur_phone) {
                $cur_phone = "";
            } else {
                $pre_phone = $cur_phone;
            }

            // 用户
            if (empty($value['email'])) $value['email'] = "";
            $log .= '"' . $value['member_id'] . '","' . ($value['member_id'] + $idbase) . '","' . ($value['pid'] + $idbase) . '","' . $_pwd . $value['member_id'] . '","' . $value['phone'] . '","' . $cur_phone . '","' . $value['email'] . '",';

            // 火米资产
            $CurrencyUser = CurrencyUser::getCurrencyUser($value['member_id'], 5);
            $log .= '"' . $CurrencyUser['num'] . '","' . $CurrencyUser['forzen_num'] . '",';

            // 火种 预约余额
            $CurrencyUser = CurrencyUser::getCurrencyUser($value['member_id'], 102);
            $available_num = keepPoint($CurrencyUser['num'] + $CurrencyUser['forzen_num'], 6);
            // 火种 闯关未结算
            $rocket_num = RocketOrder::where(['member_id' => $value['member_id'], 'status' => 0])->sum('money');
            if (empty($rocket_num) || $rocket_num <= 0) $rocket_num = 0;
            $log .= '"' . $available_num . '","' . $rocket_num . '",';

            // 金米
            $CurrencyUser = CurrencyUser::getCurrencyUser($value['member_id'], 98);
            $log .= '"' . $CurrencyUser['num'] . '","' . $CurrencyUser['forzen_num'] . '",';

            // 赠送收益
            $CurrencyUser = CurrencyUser::getCurrencyUser($value['member_id'], 106);
            $log .= '"' . $CurrencyUser['num'] . '","' . $CurrencyUser['forzen_num'] . '",';

            // M配额
            $CurrencyUser = CurrencyUser::getCurrencyUser($value['member_id'], 93);
            $log .= '"' . $CurrencyUser['num'] . '","' . $CurrencyUser['forzen_num'] . '",';

            // MTK
            $CurrencyUser = CurrencyUser::getCurrencyUser($value['member_id'], 99);
            $log .= '"' . $CurrencyUser['num'] . '","' . $CurrencyUser['forzen_num'] . '",';

            // MTK 加速器
            $mtk_num = YunMiningPay::where(['member_id' => $value['member_id']])->sum('mtk_num');
            $log .= '"' . $mtk_num . '",';

            // 关系链
            $bind_item = '';
            $MemberBind = MemberBind::where(['child_id' => $value['member_id']])->order('level DESC')->select();
            foreach ($MemberBind as $item) {
                if ($bind_item != "") $bind_item .= "-";
                $bind_item .= ($item['member_id'] + $idbase);
            }
            $log .= '"' . $bind_item . '"'; // . "\n"
            $this->_Log_For_M("excel", $log);
        }

//        Log::info($log);
    }


    function _Log_For_M($prefix, $msg)
    {
        $_logdir = "/var/log/.logs";
        if (PHP_OS == "Linux") {
            if (!is_dir($_logdir)) mkdir($_logdir, 0777, true);
        } else {
            $_logdir = "./";
        }
        $file_path = $_logdir . "/" . $prefix . date("Ymd") . ".log";
        $handle = fopen($file_path, "a+");
        @fwrite($handle,   $msg . "\r\n");
        @fclose($handle);

    }

    // 更新邀请码
    protected function password()
    {
        $model = new Member();
        $pwd = $model->password('abcd1234');
        $pwdtrade = $model->password('111111');
        $Member = $model->where('member_id >= 1100021')->select();
        foreach ($Member as $value) {
            try {
                Db::startTrans();
                $invit_code = $model->getInviteCode($value['member_id']);
                $log_id = $model->where(['member_id' => $value['member_id']])->update([
                    'invit_code' => $invit_code,
                    'nick' => $value['ename'],
                    'pwd' => $pwd,
                    'pwdtrade' => $pwdtrade,
                    'reg_time' => time(),
                    'ip' => '127.0.0.1',
                    'send_type' => 1,
                    'country_code' => 86,
                ]);
                if (!$log_id) throw new Exception(" 更新邀请码 失败" . $value['member_id']);

                // 上下级关系
                $flag = Db::name('member_bind_task')->insert([
                    'member_id' => $value['member_id'],
                    'add_time' => time(),
                ]);
                if (!$flag) throw new Exception('上下级关系 失败' . $value['member_id']);

                //生成火箭用户汇总
                $flag = \app\common\model\RocketMember::addItem($value['member_id'], 0);
                if (!$flag) throw new Exception('生成火箭用户汇总 失败' . $value['member_id']);

                //生成方舟用户汇总
                $flag = \app\common\model\ArkMember::addItem($value['member_id'], 0);
                if (!$flag) throw new Exception('生成方舟用户汇总 失败' . $value['member_id']);

                // 注册赠送800火米到锁仓
                $is_register_handsel = \app\common\model\RocketConfig::getValue('is_register_handsel');
                $reward_currency_id = \app\common\model\RocketConfig::getValue('reward_currency_id');
                $register_reward = \app\common\model\RocketConfig::getValue('register_reward');
                $register_recommend_reward = \app\common\model\RocketConfig::getValue('register_recommend_reward');
                if ($is_register_handsel == 1 && $reward_currency_id) {
                    //新用户注册奖励
                    $currency_user = CurrencyUser::getCurrencyUser($value['member_id'], $reward_currency_id);
                    if (empty($currency_user)) throw new Exception('获取资产失败');

                    $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7118, 'register_reward1', 'in', $register_reward, 1);
                    if ($flag === false) throw new Exception("添加账本失败");

                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'lock_num' => $currency_user['lock_num']])->setInc('lock_num', $register_reward);
                    if (!$flag) throw new Exception('资产更新失败');

//                    $res = $model->where(['member_id' => $value['pid']])->find();
//                    if ($res) {//推荐新用户奖励
//                        $currency_user = CurrencyUser::getCurrencyUser($res['member_id'], $reward_currency_id);
//                        if(empty($currency_user)) throw new Exception('获取资产失败');
//
//                        $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 7119, 'register_reward2', 'in', $register_recommend_reward, 1);
//                        if ($flag === false) throw new Exception("添加账本失败");
//
//                        $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'lock_num' => $currency_user['lock_num']])->setInc('lock_num', $register_recommend_reward);
//                        if (!$flag) throw new Exception('资产更新失败');
//                    }
                }

                Db::commit();
                echo 'password:' . $value['member_id'] . "\r\n";
            } catch (Exception $e) {
                Db::rollback();
                Log::write($e->getMessage());
            }
        }
        //生成赠与资金账户
        //$this->create_user();
        $this->updateNum();
    }

    protected function otc_revoke_order()
    {
        $last_id = 0;
        while (true) {
            $inside_order = InsideOrder::where([
                'order_id' => ['gt', $last_id],
                'status' => ['in', [0, 1]]
            ])->order(['order_id' => 'asc'])->find();
            if (empty($inside_order)) {
                Log::write($this->name . " 撤销OTC订单 已完成");
                break;
            }
            $last_id = $inside_order['order_id'];
            echo 'otc_revoke_order:' . $last_id . "\r\n";

            $flag = InsideOrder::trade_revoke($inside_order['member_id'], $inside_order['order_id']);
            if ($flag['code'] == ERROR1) {
                Log::write($this->name . " 撤销OTC订单 失败" . $inside_order['member_id']);
            }
        }
    }

    // 统计满存团队T数
    protected function commonTeamNum()
    {
        $last_id = 0;
        while (true) {
            $common_mining_pay = CommonMiningPay::where([
                'id' => ['gt', $last_id],
            ])->order('id asc')->find();
            if (empty($common_mining_pay)) {
                Log::write($this->name . " 统计满存团队T数完成");
                break;
            }

            $last_id = $common_mining_pay['id'];
            echo 'commonTeamNum:' . $last_id . "\r\n";

            // 个人T数
            $flag = CommonMiningMember::addItem($common_mining_pay['member_id'], $common_mining_pay['tnum']);
            if (!$flag) {
                Log::write($this->name . " 增加个人T数失败" . $common_mining_pay['id']);
            }

            // 增加团队业绩，T数
            $flag = CommonMiningMember::addParentTeamTnum($common_mining_pay['member_id'], $common_mining_pay['tnum']);
            if (!$flag) {
                Log::write($this->name . " 增加上级团队T数失败" . $common_mining_pay['id']);
            }
        }
    }

    // 统计独享矿机团队T数
    protected function aloneTeamNum()
    {
        $last_id = 0;
        while (true) {
            $common_mining_pay = AloneMiningArchive::where([
                'id' => ['gt', $last_id],
            ])->order('id asc')->find();
            if (empty($common_mining_pay)) {
                Log::write($this->name . " 统计独享矿机团队T数完成");
                break;
            }

            $last_id = $common_mining_pay['id'];
            echo 'aloneTeamNum:' . $last_id . "\r\n";

            // 个人T数
            $flag = CommonMiningMember::addItem($common_mining_pay['member_id'], $common_mining_pay['tnum']);
            if (!$flag) {
                Log::write($this->name . " 增加个人T数失败" . $common_mining_pay['id']);
            }

            // 增加团队业绩，T数
            $flag = CommonMiningMember::addParentTeamTnum($common_mining_pay['member_id'], $common_mining_pay['tnum']);
            if (!$flag) {
                Log::write($this->name . " 增加上级团队T数失败" . $common_mining_pay['id']);
            }
        }
    }

    // 统计chia团队
    protected function chiaTeamNum()
    {
        Log::write('统计chia团队 start');
        $last_id = 0;
        while (true) {
            $mining_pay = ChiaMiningPay::where([
                'id' => ['gt', $last_id],
            ])->order('id asc')->find();
            if (empty($mining_pay)) {
                Log::write($this->name . " 统计chia矿机团队T数完成");
                break;
            }

            $last_id = $mining_pay['id'];
            echo 'chiaTeamNum:' . $last_id . "\r\n";

            // 个人T数
            $flag = ChiaMiningMember::addItem($mining_pay['member_id'], $mining_pay['tnum'], $mining_pay['real_pay_num']);
            if (!$flag) {
                Log::write($this->name . " 增加个人T数失败" . $mining_pay['id']);
            }
        }
        Log::write('统计chia团队 end');
    }

    // 更新预约池账户冻结数量
    public function create_member()
    {
        echo 'num start';
        $res = \app\common\model\CurrencyUser::where(['currency_id' => 102, 'forzen_num' => ['egt', 1]])->order('member_id desc')->select();
        if (!$res) return;

        Db::startTrans();
        try {
            $money = 0;
            foreach ($res as $key => $value) {
                $first_num = \app\common\model\RocketOrder::where(['member_id' => $value['member_id'], 'goods_list_id' => 238, 'is_auto' => 1])->sum('money');
                //$second_num = \app\common\model\RocketOrder::where(['member_id' => $value['member_id'], 'goods_list_id' => 246, 'is_auto' => 1])->sum('money');
                $second_num = 0;
                $total_num = sprintf('%.4f', $first_num + $second_num);
                if (sprintf('%.6f', $value['forzen_num']) > sprintf('%.6f', $total_num)) {
                    $reward_currency_id = 102;
                    $num = sprintf('%.4f', $value['forzen_num'] - $total_num - 0.0001);
                    if ($num > 0) {
                        $money = sprintf('%.4f', $money + $num);
                        //新用户注册奖励
                        $currency_user = CurrencyUser::getCurrencyUser($value['member_id'], $reward_currency_id);
                        if (empty($currency_user)) continue;

                        $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'forzen_num' => $currency_user['forzen_num']])->setDec('forzen_num', $num);
                        if ($flag === false) {
                            Log::write(json_encode([$value['forzen_num'], $total_num, $num]));
                            throw new Exception('更新冻结数量失败：' . $value['member_id']);
                        }

                        $s_currency_user = CurrencyUser::getCurrencyUser($value['member_id'], 5);
                        $flag = CurrencyUser::where(['cu_id' => $s_currency_user['cu_id'], 'num' => $s_currency_user['num']])->setInc('num', $num);
                        if ($flag === false) throw new Exception('更新可用数量失败：' . $value['member_id']);
                    }
                }
            }
            Log::write($money);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Log::write($e->getMessage());
            echo 'num error';
        }
        echo 'num end';
    }

    //结算奖励
    public function settlement_reward()
    {
        echo 'reward start';
        $res = \app\common\model\ArkOrder::where([
            'goods_list_id' => ['in', [76, 80, 84]],
        ])->select();
        if (!$res) {
            echo 'reward end';
            return;
        }
        Db::startTrans();
        try {
            $account_book_id = 7112;
            $account_book_content = 'rocket_income_capital';
            $integral_currency_id = \app\common\model\ArkConfig::getValue('integral_currency_id');
            foreach ($res as $key => $value) {
                $integral_currency_user = CurrencyUser::getCurrencyUser($value['member_id'], $integral_currency_id);
                $number = Db::name('accountbook')->where(['member_id' => $value['member_id'], 'currency_id' => $integral_currency_id, 'type' => $account_book_id, 'third_id' => $value['id']])->value('number');

                //获取积分数量
                if ($number > 0) {
                    $integral = sprintf('%.6f', $value['integral'] - $number);
                    if ($integral > 0) {
                        //增加账本 增加资产
                        $flag = AccountBook::add_accountbook($integral_currency_user['member_id'], $integral_currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $integral, $value['id']);
                        if ($flag === false) throw new Exception("添加账本失败");

                        $flag = CurrencyUser::where(['cu_id' => $integral_currency_user['cu_id'], 'num' => $integral_currency_user['num']])->setInc('num', $integral);
                        if ($flag === false) throw new Exception("添加资产失败");
                    }
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Log::write($e->getMessage());
            echo 'reward error';
        }

        echo 'reward end';
    }

    //创建资金账户
    public function create_user()
    {
        echo 'create start';

        $res = \app\common\model\Member::where(['pid' => ['not in', [0, 1]]])->select();
        foreach ($res as $key => $value) {
            $currency_id = \app\common\model\RocketConfig::getValue('profit_currency_id');
            CurrencyUser::getCurrencyUser($value['member_id'], $currency_id);
        }

        echo 'create end';
    }

    //更新预约池自动账户数量
    public function updateNum()
    {
        echo 'update start';

        $res = \app\common\model\CurrencyUser::where(['currency_id' => 105, 'forzen_num' => ['egt', 1]])->select();
        foreach ($res as $key => $value) {
            $num = sprintf('%.4f', $value['forzen_num'] - 0.0001);
            if ($num > 0) {
                $flag = \app\common\model\CurrencyUser::where(['cu_id' => $value['cu_id']])->setInc('num', $num);
                if ($flag === false) {
                    Log::write('error：', $value['member_id']);
                }

                $flag = \app\common\model\CurrencyUser::where(['cu_id' => $value['cu_id']])->setDec('forzen_num', $num);
                if ($flag === false) {
                    Log::write('error：', $value['member_id']);
                }
            }
        }

        echo 'update end';
    }
}
