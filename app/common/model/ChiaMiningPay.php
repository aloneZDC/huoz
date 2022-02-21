<?php

namespace app\common\model;

use think\Exception;
use think\Model;
use think\Db;
use app\common\model\ChiaMiningReward;
use app\common\model\Member;
use app\common\model\ChiaMiningMember;

/**
 * CHIA(奇亚)云算力
 * Class AloneMiningPay
 * @package app\common\model
 */
class ChiaMiningPay extends Model
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
    static function product_buy($member_id, $product_id, $amount, $start_time = 0)
    {
        $r = ['code' => ERROR1, 'message' => lang("operation_failed_try_again"), 'result' => null];
        if ($product_id <= 0 || $amount <= 0) return $r;

        // 查询矿机
        $product_info = ChiaMiningProduct::get_product($product_id);
        if (empty($product_info)) {
            $r['message'] = lang('common_mining_amount_not_enough');
            return $r;
        }
        // 获取配置
        $mining_config = ChiaMiningConfig::get_key_value();

        // 判断购买数量
        if($mining_config['min_buy'] > $amount) {
            $r['message'] = lang('common_mining_amount_not_enough');
            return $r;
        }

        //后台设置为USDT钱包账户支付
//        $other_currency_num = 0;

        // 组合支付
//        $pay_type_info = Db::name('fil_mining_pay_type')->where(['status' => 0, 'id' => $pay_id])->field(['id', 'currency_id', 'other_currency_id'])->find();
//        if (empty($pay_type_info)) return $r;

        // 判断组合支付
//        if ($pay_type_info['other_currency_id'] > 0) {
//            $other_currency_user = CurrencyUser::getCurrencyUser($member_id, $pay_type_info['other_currency_id']);
//            if (!empty($currency_user) || $other_currency_user['num'] > 0) {
//                $other_currency_num = $other_currency_user['num'];
//            }
//        }

        // 获取资产
        $currency_user = CurrencyUser::getCurrencyUser($member_id, $product_info['price_usdt_currency_id']);
        if (empty($currency_user)) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }
//        $currency_user_num = bcadd($currency_user['num'], $other_currency_num, 6);
        $pay_num = bcmul($product_info['price_usdt'],$amount,6);
        if ($currency_user['num'] < $pay_num) {
            $r['message'] = lang('insufficient_balance');
            return $r;
        }

        // 获取配置
//        $alone_mining_config = AloneMiningConfig::get_key_value();

        // 方便测试
        if($start_time == 0) {
            $add_time = time();
            $today = todayBeginTimestamp() + 86400;

            $pre_sale_time = $product_info['add_time'] + $mining_config['pre_sale_time'] * 86400;
            $pre_sale_time = strtotime(date("Y-m-d", $pre_sale_time + 86400));
            $today = $pre_sale_time >= $today ? $pre_sale_time : $today;

        }else {
            $add_time = $start_time + 10;
            $today = $start_time + 86400;
        }
        $start_day = $today + $product_info['deliver_time'] * 86400; // 交付时间
        $treaty_day = $start_day + $product_info['cycle_time'] * 86400; // 合约时间

        try {
            self::startTrans();
            
            $flag = ChiaMiningMember::addItem($member_id, $amount, $pay_num);
            if(!$flag) {
                $r['message'] = lang('operation_failed_try_again');
                return $r;
            }

            //插入订单
            $item_id = self::insertGetId([
                'member_id' => $currency_user['member_id'],
                'real_pay_num' => $pay_num,
                'real_pay_currency_id' => $currency_user['currency_id'],
                'tnum' => $amount,
                'product_id' => $product_info['id'],
//                'stop_day' => $stop_day, // 退币时间
                'start_day' => $start_day, // 产币时间
                'treaty_day' => $treaty_day, // 合约时间
                'add_time' => $add_time,
                'mining_code' => self::GetRandOrderSn()
            ]);
            if (!$item_id) throw new Exception(lang('operation_failed_try_again'));

            // 扣除资产
            if ($pay_num > 0) {

//                if ($other_currency_num > 0) {
//                    $other_currency_pay_num = bcsub($pay_num, $other_currency_user['num'], 6);
//                    if ($other_currency_pay_num >= 0) {
//                        $flag = AccountBook::add_accountbook($other_currency_user['member_id'], $other_currency_user['currency_id'], 6620, 'buy_alone_mining', 'out', $other_currency_user['num'], $item_id, 0);
//                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));
//
//                        $flag = CurrencyUser::where(['cu_id' => $other_currency_user['cu_id'], 'num' => $other_currency_user['num']])->setDec('num', $other_currency_user['num']);
//                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));
//
//                        $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6620, 'buy_alone_mining', 'out', $other_currency_pay_num, $item_id, 0);
//                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));
//
//                        $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $other_currency_pay_num);
//                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));
//                    } else {
//                        $flag = AccountBook::add_accountbook($other_currency_user['member_id'], $other_currency_user['currency_id'], 6620, 'buy_alone_mining', 'out', $pay_num, $item_id, 0);
//                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));
//
//                        $flag = CurrencyUser::where(['cu_id' => $other_currency_user['cu_id'], 'num' => $other_currency_user['num']])->setDec('num', $pay_num);
//                        if (!$flag) throw new Exception(lang('operation_failed_try_again'));
//                    }
//                } else {
                    //增加账本 扣除资产
                    $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], 6640, 'buy_chia_mining', 'out', $pay_num, $item_id, 0);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));

                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setDec('num', $pay_num);
                    if (!$flag) throw new Exception(lang('operation_failed_try_again'));
//                }
            }

            //一次性每买10T, 就送 1 T
            //前200名账户，是指不同账户的前200名！
            //同一个账户，可以反复购买多次，只要满足一次性买10T,就送1T
            $res = self::where(['tnum' => ['>=', 10]])->order('add_time ASC')->group('member_id')->limit(200)->column('member_id');
            $result = [];
            $result['item_id'] = $item_id;
            $result['status'] = false;//是否弹窗 false不弹窗 true弹窗
            $result['tnum'] = 0;
            if (in_array($member_id, $res) && $amount >= 10) {
                $result['status'] = true;
                $result['tnum'] = intval($amount / 10);
            }
            self::commit();

            //更新矿工身份
            $ChiaMiningMember = new ChiaMiningMember();
            $ChiaMiningMember->updateLevel($member_id);

            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = $result;
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage() . $e->getLine();
        }
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
        $result = [
            'info' =>  ['pay_tnum' => 0, 'release_num' => 0, 'today_release_num' => 0],
            'list' => null
        ];
        $r = ['code' => ERROR1, 'message' => lang('no_data'), 'result' => $result, 'total' => 0];

        $list = self::alias('a')->with(['paycurrency'])
            ->field('a.id,a.member_id,a.product_name,a.add_time,a.tnum,a.start_day,a.treaty_day,a.mining_code,a.last_release_num,a.total_release_num,a.tnum,a.valid_tnum,a.max_tnum,a.cycle_time')
            ->where(['a.member_id' => $member_id])
            ->page($page, $rows)
            ->order("a.id desc")
            ->select();
        if (!$list) return $r;

        $today = todayBeginTimestamp();
        foreach ($list as &$item) {
            $item['add_time'] = date('Y-m-d', $item['add_time']);
            $item['deliver_time'] = date('Y-m-d', $item['start_day']);
            if ($today < $item['start_day']) { // 开挖时间
                $status = 1;
            } elseif ($today >= $item['start_day'] && $today <= $item['treaty_day']) { // 合约周期
                $status = 2;
            } else {
                $status = 3;
            }
            $item['status'] = $status; //1主网待挖 2主网在挖 3已挖完
            $item['start_day'] = date('Y-m-d', $item['start_day']);
            $item['treaty_day'] = date('Y-m-d', $item['treaty_day']);;
            $item['xch_name'] = 'XCH';
            $service_rate = \app\common\model\ChiaMiningTake::where(['member_id' => $item['member_id'], 'third_id' => $item['id']])->value('service_rate');
            $item['release_percent'] = keepPoint(100 - $service_rate, 0);//分币比例
        }
        $start_time = strtotime(date('Y-m-d'));
        $end_time = $start_time + 86399;
        $info = \app\common\model\ChiaMiningMember::field('release_num,pay_tnum')->where(['member_id' => $member_id])->find();
        $info['release_num'] = $info['release_num'] == 0 ? 0 : $info['release_num'];
        $today_release_num = \app\common\model\ChiaMiningRelease::where(['member_id' => $member_id,'release_time' => ['between', [$start_time, $end_time]]])->sum('num');//昨日产币
        $info['today_release_num'] = $today_release_num == 0 ? 0 : sprintf('%.6f', $today_release_num);
        $price = Db::name('common_mining_currency_price')->where(['mining_currency_id' => 95, 'status' => 1, 'platform' => 'Huobi'])->value('usdt');
        $info['price'] = $price == 0 ? 0 : $price;
        $result['info'] = $info;
        $result['list'] = $list;

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
        return $this->belongsTo('app\\common\\model\\ChiaMiningProduct', 'product_id', 'id')->field('id,name');
    }

    public function paycurrency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'real_pay_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    
    public function miningcurrency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'mining_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function takes() {
        return $this->belongsTo('app\\common\\model\\ChiaMiningTake', 'id', 'third_id')->field('third_id,take_rate,service_rate');
    }

    /**
     * 推荐奖励1代2代发放
     * @param array $mining_pay 封存信息
     * @param array $mining_config 配置信息
     * @param int $today_start 时间
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function recommand_award($mining_pay, $mining_config, $today_start)
    {
        $base_member_id = $mining_pay['member_id'];

        //直推 间推奖励
        for ($count = 1; $count <= 2; $count++) {
            $award_level = $count; //奖励类型

            $member = Member::where(['member_id' => $base_member_id])->field('pid')->find();
            if (empty($member) || $member['pid'] == 0) {
                return;
            }

            $base_member_id = $member['pid'];

            // 购买独享矿机或满存算力
            $ChiaMiningPay = ChiaMiningPay::where(['member_id' => $base_member_id])->find(); // Chia矿机
            if (empty($ChiaMiningPay)) {
                continue;
            }

            // 固定奖励 /T
            $award_num = sprintf('%.6f', $mining_pay['real_pay_num'] * ($mining_config['fixed_recommand'. $count] / 100));
            if ($award_num > 0.000001) {
                ChiaMiningIncome::recommand_award($award_level, $base_member_id, $mining_config['recommand_currency_id'], $award_num, $mining_pay, $today_start);
            }
        }
    }

    /**
     * 我的团队
     * @param int $member_id 用户ID
     * @param int $page 页
     * @param int $page_size 条数
     * @return array
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function myTeam($member_id, $page = 1, $page_size = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang('not_data'), 'result' => null,'total'=>0];
        
        $result = Member::alias('t1')
            //->join(config("database.prefix") . "chia_mining_pay t3", "t1.member_id=t3.member_id", "LEFT")
            ->join(config("database.prefix") . "chia_mining_member t4", "t1.member_id=t4.member_id", "LEFT")
            ->where('t1.member_id = ' . $member_id)
            ->field('t1.member_id,t1.ename,t1.phone,t1.email,t4.pay_tnum as tnum,t4.level,t4.valid_num as valid_people,t4.team_tnum as total_tnum')->find();
        if (empty($result)) return $r;

        $result['direct_people'] =  MemberBind::where(['member_id'=>$member_id,'level'=>1])->count();
        $result['level'] = $result['level'] ?: 0;//等级
        $result['info'] = [];

        $res = Member::alias('t1')->join(config("database.prefix") . "member_bind t2", 't1.member_id = t2.child_id')
            ->join(config("database.prefix") . "chia_mining_member t4", "t1.member_id=t4.member_id", "LEFT")
            ->where('t2.level = 1 and t2.member_id = ' . $member_id)
            ->field('t1.member_id,t1.ename,t1.phone,t1.email,t1.reg_time,t4.level,t4.team_tnum as total_tnum,t4.pay_tnum as tnum,t4.is_edit_level')->group('t1.member_id')
            ->page($page, $page_size)->select();
        if (!empty($res)) {
            foreach($res as &$value) {
                $value['level'] = $value['level'] ?: 0;//等级
                $value['reg_time'] = date('Y-m-d', $value['reg_time']);
                $value['tnum'] = $value['tnum'] ?: '0.00';
                $value['total_tnum'] = $value['total_tnum'] ?: '0.00';
            }
            $result['info'] = $res;
            $r['total'] = count($res);
        }
        $result['tnum'] = $result['tnum'] ?: '0.00';
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $result;
        
        return $r;
    }

    /**
     * 新增/编辑记录
     * @param int $data 数据
     * @return int
     * */
    static function addItem($data) {
        try {
            self::startTrans();

            $data['start_day'] = strtotime($data['start_day']);
            $data['treaty_day'] = $data['start_day'] + $data['cycle_time'] * 86400;
            if (!empty($data['id'])) {
                $item_id = $data['id'];
                $take = [
                    'take_rate' => $data['take_rate'],
                    'service_rate' => $data['service_rate'],
                ];
                unset($data['id'],$data['take_rate'],$data['service_rate']);
                $flag = self::where(['id' => $item_id])->update($data);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));

                $flag = \app\common\model\ChiaMiningTake::where(['third_id' => $item_id, 'member_id' => $data['member_id']])->update($take);
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
                $flag = \app\common\model\ChiaMiningTake::insertGetId($take);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
            }
            self::commit();

            //更新个人总有效空间
            $total = self::where(['member_id' => $data['member_id']])->sum('max_tnum');
            $flag = \app\common\model\ChiaMiningMember::where(['member_id' => $data['member_id']])->update(['pay_tnum' => $total]);
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