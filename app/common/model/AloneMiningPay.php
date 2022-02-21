<?php

namespace app\common\model;

use think\Db;
use think\Exception;
use think\Log;
use think\Model;

/**
 * 独享矿机 - 购买硬件
 * Class AloneMiningPay
 * @package app\common\model
 */
class AloneMiningPay extends Model
{
    /**
     * 购买硬件
     * @param int $member_id 用户ID
     * @param int $product_id 商品ID
     * @param int $amount 购买数量
     * @param int $pay_id 支付类型
     * @param int $start_time 时间
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    static function buy($member_id, $product_id, $amount, $pay_id, $start_time = 0)
    {
        $r = ['code' => ERROR1, 'message' => lang("operation_failed_try_again"), 'result' => null];
        if ($product_id <= 0 || $amount <= 0) return $r;

        // 查询矿机
        $product_info = AloneMiningProduct::get_product($product_id);
        if (empty($product_info)) {
            $r['message'] = lang('common_mining_amount_not_enough');
            return $r;
        }

        // 判断购买数量
        if($product_info['quota'] > $amount || $product_info['tnum'] < $amount) {
            $r['message'] = lang('common_mining_amount_not_enough');
            return $r;
        }

        //后台设置为USDT钱包账户支付
        $other_currency_num = 0;

        // 组合支付
        $pay_type_info = Db::name('fil_mining_pay_type')->where(['status' => 0, 'id' => $pay_id])->field(['id', 'currency_id', 'other_currency_id'])->find();
        if (empty($pay_type_info)) return $r;

        // 判断组合支付
        if ($pay_type_info['other_currency_id'] > 0) {
            $other_currency_user = CurrencyUser::getCurrencyUser($member_id, $pay_type_info['other_currency_id']);
            if (!empty($currency_user) || $other_currency_user['num'] > 0) {
                $other_currency_num = $other_currency_user['num'];
            }
        }

        // 获取资产
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $pay_type_info['currency_id']);
        if (empty($currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }
        $currency_user_num = bcadd($currency_user['num'], $other_currency_num, 6);
        $pay_num = $product_info['price_usdt'] * $amount;
        if ($currency_user_num < $pay_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        // 获取配置
        $alone_mining_config = AloneMiningConfig::get_key_value();

        // 方便测试
        if($start_time == 0) {
            $add_time = time();
            $today = todayBeginTimestamp() + 86400;
        }else {
            $add_time = $start_time + 10;
            $today = $start_time + 86400;
        }
        $start_day = $today + $alone_mining_config['output_time'] * 86400; // 产币时间
        $stop_day = $start_day + $alone_mining_config['return_pledge'] * 86400; // 退币时间
        $treaty_day = $start_day + $alone_mining_config['contract_period'] * 86400; // 合约时间

        try {
            self::startTrans();

            //插入订单
            $item_id = self::insertGetId([
                'member_id' => $currency_user['member_id'],
                'real_pay_num' => $pay_num,
                'real_pay_currency_id' => $currency_user['currency_id'],
                'tnum' => $amount,
                'mining_currency_id' => $product_info['mining_currency_id'],
                'product_id' => $product_info['id'],
                'stop_day' => $stop_day, // 退币时间
                'start_day' => $start_day, // 产币时间
                'treaty_day' => $treaty_day, // 合约时间
                'add_time' => $add_time,
                'mining_code' => self::GetRandOrderSn()
            ]);
            if (!$item_id) throw new Exception(lang('operation_failed_try_again'));

            // 扣除资产
            if ($pay_num > 0) {

                if ($other_currency_num > 0) {
                    $other_currency_pay_num = bcsub($pay_num, $other_currency_user['num'], 6);
                    if ($other_currency_pay_num >= 0) {
                        $flag = AccountBook::add_accountbook($other_currency_user['member_id'], $other_currency_user['currency_id'], 6620, 'buy_alone_mining', 'out', $other_currency_user['num'], $item_id, 0);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = CurrencyUser::where(['cu_id' => $other_currency_user['cu_id'], 'num' => $other_currency_user['num']])->setDec('num', $other_currency_user['num']);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6620, 'buy_alone_mining', 'out', $other_currency_pay_num, $item_id, 0);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $other_currency_pay_num);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                    } else {
                        $flag = AccountBook::add_accountbook($other_currency_user['member_id'], $other_currency_user['currency_id'], 6620, 'buy_alone_mining', 'out', $pay_num, $item_id, 0);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                        $flag = CurrencyUser::where(['cu_id' => $other_currency_user['cu_id'], 'num' => $other_currency_user['num']])->setDec('num', $pay_num);
                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                    }
                } else {
                    //增加账本 扣除资产
                    $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6620, 'buy_alone_mining', 'out', $pay_num, $item_id, 0);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $pay_num);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));
                }
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = $item_id;
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage() . $e->getLine();
        }
        return $r;
    }

    /**
     * 数据概况
     * @param int $member_id 用户ID
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function data_overview($member_id) {
        $check = self::where(['member_id' => $member_id])->find();
        if (empty($check)) {
            $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];
            return $r;
        }
        $result = [
            'total_num' => 0,//累计总产量
            'available_num' => 0,//累计可用
            'price' => 0,//今日币价
            'current_tnum' => 0,//当前算力
            'seal_tnum' => 0,//已存算力
            'num' => 0,//释放数量
            'lock_num' => 0,//锁仓数量
            'payment' => 0,//质押数量
            'pregas' => 0,//Gas数量
        ];
        $res = self::field('sum(total_release_num) as total_release_num,sum(total_lock_num) as total_lock_num,sum(total_lock_yu) as total_lock_yu,sum(total_lock_num + total_lock_yu) as lock_num,sum(max_tnum) as max_tnum,sum(archive) as archive')->where(['member_id' => $member_id])->find();
        if ($res) {
            $result['available_num'] = $res['total_release_num'] == 0 ? 0 : $res['total_release_num'];
            $available_num = keepPoint($res['total_release_num'] + $res['lock_num'], 6);
            $result['total_num'] = $available_num == 0 ? 0 : $available_num;
            $result['current_tnum'] = $res['max_tnum'] == 0 ? 0 : $res['max_tnum'];
            $result['seal_tnum'] = $res['archive'] == 0 ? 0 : $res['archive'];
            $result['num'] = $res['total_lock_num'] == 0 ? 0 : $res['total_lock_num'];
            $result['lock_num'] = $res['total_lock_yu'] == 0 ? 0 : $res['total_lock_yu'];
        }
        $price = Db::name('common_mining_currency_price')->where(['mining_currency_id' => 81, 'status' => 1, 'platform' => 'Huobi'])->value('usdt');
        $result['price'] = $price == 0 ? 0 : $price;
        $archive = Db::name('alone_mining_archive')->field('sum(pregas) as pregas, sum(payment) as payment')->where(['member_id' => $member_id,'thaw_time' => ['egt', time()]])->find();
        if ($archive) {
            $result['payment'] = $archive['payment'] == 0 ? 0 : $archive['payment'];
            $result['pregas'] = $archive['pregas'] == 0 ? 0 : $archive['pregas'];
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    /**
     * 购买记录
     * @param int $member_id 用户ID
     * @param int $page 页码
     * @param int $rows 条数
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function buy_list($member_id, $page = 1, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];

        $list = self::alias('a')->with(['product', 'miningcurrency', 'paycurrency'])->field('a.id,a.member_id,a.product_name,a.mining_currency_id,a.real_pay_currency_id,a.real_pay_num,a.add_time,a.total_release_num,a.treaty_day,a.last_release_day,a.last_release_num,a.start_day,a.stop_day,a.total_lock_num,a.total_lock_yu,a.tnum,a.archive,a.mining_code,a.total_lock_pledge,a.max_tnum,a.cycle_time')
            ->where(['a.member_id' => $member_id])
            ->page($page, $rows)->order("a.id asc")->select();
        if (!$list) return $r;

        $today = todayBeginTimestamp();
        $alone_mining_config = AloneMiningConfig::get_key_value();
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $alone_mining_config['archive_currency_id']);
        $currency_name = Currency::where(['currency_id' => $alone_mining_config['archive_currency_id']])->value('currency_name');
        $gas_fee = AloneMiningProduct::average_out();

        foreach ($list as &$item) {
            $item['release_percent'] = 100 - $alone_mining_config['release_manager_fee_percent'];
            $item['total_lock_release'] = keepPoint($item['total_lock_num'] - $item['total_lock_yu'], 6);
            $item['total_release_num'] = keepPoint($item['total_release_num'] + $item['total_lock_num'] + $item['total_lock_yu'], 6);

            if ($today < $item['start_day']) { // 0待封存
                $status = 1;
            } elseif ($today >= $item['start_day']
                && $today <= $item['stop_day']) { // 合约周期
                $status = 2;
            } else {
                $status = 3;
            }

            $item['status'] = $status; //0主网待挖 1主网在挖 2已挖完

            $item['add_time'] = date('Y-m-d', $item['add_time']);
            $item['start_day'] = date('Y-m-d', $item['start_day']); // 开挖时间
            $item['treaty_day'] = $item['treaty_day'] ? date('Y-m-d', $item['treaty_day']) : '---'; // 合期到期时间
            $item['surplus_archive'] = keepPoint($item['max_tnum'] - $item['archive']);//剩余需封存
            $service_rate = \app\common\model\AloneMiningTake::where(['member_id' => $item['member_id'], 'third_id' => $item['id']])->value('service_rate');
            $item['release_percent'] = keepPoint(100 - $service_rate, 0);//分币比例
            $start_time = $today;
            $end_time = $today + 86399;
            $res = \app\common\model\AloneMiningArchiveLog::where(['member_id' => $member_id, 'mining_pay_id' => $item['id'], 'add_time' => ['between', [$start_time, $end_time]]])->order('id DESC')->find();
            if ($res) {
                $pregas = $res['pregas'];
                $payment = $res['payment'];
                $real_pay_num = $res['real_pay_num'];
                $amount = $res['tnum'];
            } else {
                $amount = \app\common\model\AloneMiningArchive::where(['member_id' => $member_id, 'mining_pay_id' => $item['id']])->order('tnum DESC')->value('tnum');
                $amount = $amount ?: $alone_mining_config['archive_tnum'];
                $pregas = keepPoint($amount * $gas_fee['preGas_1T'], 6);
                $payment = keepPoint($amount * $gas_fee['payment_1T'], 6);
                $real_pay_num = keepPoint($pregas + $payment, 6);
            }
            $info = [
                'tnum' => $amount,
                'preGas_1T' => $pregas,
                'payment_1T' => $payment,
                'real_pay_num' => $real_pay_num,
                'currency_id' => $alone_mining_config['archive_currency_id'],
                'currency_name' => $currency_name,
                'currency_num' => $currency_user['num'],
            ];
            $item['info'] = $info;
            $release_time = '';
            if ($item['archive']) {
                $release_time = \app\common\model\AloneMiningArchive::where(['member_id' => $member_id, 'mining_pay_id' => $item['id']])->order('id ASC')->value('add_time');
            }
            $item['release_time'] = $release_time > 0 ? date('Y-m-d', $release_time) : '自行质押：交付即可开始产出';//产币时间
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 封存质押统计
     * @param int $member_id 用户ID
     * @param int $product_id 订单ID
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function archive_count($member_id,$product_id) {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];
        $result = self::where(['id'=>$product_id,'member_id'=>$member_id])->field(['total_lock_pledge','total_thaw_pledge','total_lock_num','total_lock_yu'])->find();
        if(empty($result)) return $r;
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        return $r;
    }

    /**
     * 生成随机订单号
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected static function GetRandOrderSn()
    {
        $string = 'abcdefghkmnprstuvwxyzABCDEFGHKMNPRSTUVWXYZ23456789';
        $cdkey = "";
        for ($i = 0; $i < 22; $i++) {
            $cdkey .= $string[rand(0, strlen($string) - 1)];
        }

        $out_trade_no = $cdkey . time();
        $is_out_trade_no = self::where('mining_code', $out_trade_no)->find();
        if (empty($is_out_trade_no)) {
            return $out_trade_no;
        }
        return self::GetRandOrderSn();
    }

    public function product()
    {
        return $this->belongsTo('app\\common\\model\\AloneMiningProduct', 'product_id', 'id')->field('id,name,node_name');
    }

    public function miningcurrency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'mining_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function paycurrency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'real_pay_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }

    public function takes() {
        return $this->belongsTo('app\\common\\model\\AloneMiningTake', 'id', 'third_id')->field('third_id,take_rate,service_rate');
    }

    /**
     * 新增/编辑记录
     *
     * @param int $data 数据
     * @return int
     * */
    static function addItem($data) {
        try {
            self::startTrans();
            Log::write($data);
            $data['start_day'] = strtotime($data['start_day']);//开挖时间
            $data['stop_day'] = $data['start_day'] + $data['return_time'] * 86400;//退币时间
            if (!empty($data['id'])) {
                $item_id = $data['id'];
                $take = [
                    'take_rate' => $data['take_rate'],
                    'service_rate' => $data['service_rate'],
                ];
                unset($data['id'],$data['take_rate'],$data['service_rate']);
                $flag = self::where(['id' => $item_id])->update($data);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

                $flag = \app\common\model\AloneMiningTake::where(['third_id' => $item_id, 'member_id' => $data['member_id']])->update($take);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
            } else {
                $data['mining_code'] = self::GetRandOrderSn();
                $data['add_time'] = time();
                $take = [
                    'member_id' => $data['member_id'],
                    'take_rate' => $data['take_rate'],
                    'service_rate' => $data['service_rate'],
                    'add_time' => time()
                ];
                unset($data['take_rate'],$data['service_rate']);
                //插入订单
                $item_id = self::insertGetId($data);
                if (!$item_id) throw new Exception(lang('operation_failed_try_again'));

                $take['third_id'] = $item_id;
                $flag = \app\common\model\AloneMiningTake::insertGetId($take);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
            }
            self::commit();

            //更新个人总有效空间
            $total = self::where(['member_id' => $data['member_id']])->sum('max_tnum');
            $flag = \app\common\model\AloneMiningMember::addItem($data['member_id'], $total);
            if ($flag === false) {
                return false;
            }

        } catch (Exception $e) {
            self::rollback();
            return false;
        }
        return $item_id;
    }
}
