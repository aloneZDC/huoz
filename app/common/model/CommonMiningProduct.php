<?php
//传统矿机商品列表
namespace app\common\model;

use app\cli\controller\FgasTask;
use think\Db;
use think\Model;

class CommonMiningProduct extends Model
{
    static function getProduct($product_id)
    {
        return self::where('id', $product_id)->where('status', 1)->where('amount', 'gt', 0)->find();
    }

    // 计算1T的价格
    public static function mining_price()
    {
        $today_start = strtotime(date('Y-m-d'));
        $mining_price = cache('common_mining_price_' . $today_start);
        if (empty($mining_price)) {
            // 获取成本价格
            $cost_num = FgasTask::average_out();
            if ($cost_num <= 0) {
                return false;
            }

            // 获取FIL价格
//            $fil_close_price = Kline::where(['type' => 3600, 'currency_id' => 81, 'currency_trade_id' => 5, 'add_time' => ['between', [$today_start - 86400, $today_start - 1]]])->avg('close_price');
            $fil_close_price = Db::name('common_mining_currency_price')->where('id', 1)->value('usdt', 0);

            // 获取USDT价格
//            $usdt_price = CurrencyPriceTemp::get_price_currency_id(Currency::USDT_ID, 'CNY');
            $usdt_price = 6.45;

            // 获取矿机价格基数
            $mining_price_base = CommonMiningConfig::get_value('mining_price_base', 0);

            // 计算价格
            $mining_usdt_price = intval(($cost_num * $fil_close_price) + ($mining_price_base / $usdt_price));
            $mining_cny_price = intval($mining_usdt_price * $usdt_price);
            $result = ['mining_usdt_price' => $mining_usdt_price, 'mining_cny_price' => $mining_cny_price];
            cache('common_mining_price_' . $today_start, $result, 86400 + 3600);
            return $result;
        }
        return $mining_price;
    }

    /**
     * 商品列表
     * @param int $page 页码
     * @param int $rows 条数
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function product_list($page = 1, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];
        $list = self::alias('a')->where(['a.status' => 1])
            ->field("a.id,a.name,a.tnum,
            a.price_usdt,a.third_price_usdt,a.payment,a.pre_gas,
            b.currency_name as price_usdt_currency_name,
            a.price_cny,c.currency_name as price_cny_currency_name,
            a.deliver_time,a.cycle_time,a.amount,a.price_type")
            ->join("currency b", "a.price_usdt_currency_id = b.currency_id", "left")
            ->join("currency c", "a.price_cny_currency_id = c.currency_id", "left")
            ->page($page, $rows)->order('a.sort asc')->select();
        if (empty($list)) return $r;

        // 满存24H平均产币
        $fil_block_info = cache('fil_block_info');
        $ipfs_hour24_avg = !empty($fil_block_info) ? $fil_block_info['fields']['hour24_avg']['number'] : 0;

        // 获取1T的价格
        $mining_price = self::mining_price();
        if ($mining_price === false) {
            $r['message'] = lang('price_error');
            return $r;
        }

        // 获取gas费
//        $average_out = AloneMiningProduct::average_out();

        foreach ($list as &$item) {
//            $item['price_usdt'] = keepPoint(($item['price_usdt'] + $item['third_price_usdt']) + ($average_out['payment_1T'] + $item['payment']) + ($average_out['preGas_1T'] + $item['pre_gas']), 6);
//            $item['price_usdt'] = keepPoint($item['price_usdt'] + $item['payment'] + $item['pre_gas'], 6);
            if ($item['price_type']) {
                $item['price_usdt'] = $mining_price['mining_usdt_price'];
                $item['price_cny'] = $mining_price['mining_cny_price'];
            } else {
                $item['price_usdt'] = keepPoint($item['price_usdt'] + $item['payment'] + $item['pre_gas'], 6);
            }

            $item['hour24_avg'] = $ipfs_hour24_avg;
            unset($item['price_type'], $item['third_price_usdt'], $item['payment'], $item['pre_gas']);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 商品详情
     * @param int $member_id 用户id
     * @param int $product_id 商品id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function product_info($member_id, $product_id)
    {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];
        $list = self::alias('a')->where([
            'a.id' => $product_id,
            'a.amount' => ['gt', 0],
            'a.status' => 1,
        ])
            ->field("a.id,a.name,a.tnum,
            a.price_usdt,a.third_price_usdt,a.payment,a.pre_gas,
            a.price_usdt_currency_id,b.currency_name as price_usdt_currency_name,
            a.price_cny,c.currency_name as price_cny_currency_name,a.deliver_time,a.cycle_time,a.amount,
            a.price_type")
            ->join("currency b", "a.price_usdt_currency_id = b.currency_id", "left")
            ->join("currency c", "a.price_cny_currency_id = c.currency_id", "left")
            ->find();
        if (empty($list)) return $r;

        // 满存24H平均产币
        $fil_block_info = cache('fil_block_info');
        $ipfs_hour24_avg = !empty($fil_block_info) ? $fil_block_info['fields']['hour24_avg']['number'] : 0;
        $list['hour24_avg'] = $ipfs_hour24_avg;

        // 获取gas费
//        $average_out = AloneMiningProduct::average_out();
//        $list['price_usdt'] = keepPoint(($list['price_usdt'] + $list['third_price_usdt']) + ($average_out['payment_1T'] + $list['payment']) + ($average_out['preGas_1T'] + $list['pre_gas']), 6);
//        $list['price_usdt'] = keepPoint($list['price_usdt'] + $list['payment'] + $list['pre_gas'], 6);

        // 获取1T的价格
        $mining_price = self::mining_price();
        if ($mining_price === false) {
            $r['message'] = lang('price_error');
            return $r;
        }

        if ($list['price_type']) {
            $list['price_usdt'] = $mining_price['mining_usdt_price'];
            $list['price_cny'] = $mining_price['mining_cny_price'];
        } else {
            $list['price_usdt'] = keepPoint($list['price_usdt'] + $list['payment'] + $list['pre_gas'], 6);
        }

        // 服务费
        $list['service_fee'] = CommonMiningConfig::get_value('release_service_fee', 0);

        // 资产
//        $currency_user_usdt = CurrencyUser::getCurrencyUser($member_id, $list['price_usdt_currency_id']);
//        $currency_user_integral = CurrencyUser::getCurrencyUser($member_id, 98);

        // 获取配置
//        $mining_config = FuseMiningConfig::get_key_value();

        unset($list['price_usdt_currency_id'], $list['price_type'],$list['third_price_usdt'],$list['payment'],$list['pre_gas']);

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;

//        $r['result'] = [
//            'product' => $list,
//            'currency_num' => [ // 资产
//                'usdt' => $currency_user_usdt ? $currency_user_usdt['num'] : 0,
//                'integral' => $currency_user_integral ? $currency_user_integral['num'] : 0,
//            ],
//            'voucher' => FuseVoucherMember::get_my_quan($member_id), // 优惠券
//            'discount' => [// 满减
//                'price_full' => $mining_config['price_full'],
//                'price_reduce' => $mining_config['price_reduce'],
//                'price_capping' => $mining_config['price_capping'],
//            ],
//            'level_reward' => FuseMiningMember::level_reward($member_id), // 代理优惠折扣
//        ];
        return $r;
    }

    static function getProductList($lang, $page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        if ($lang == 'en') {
            $field = 'a.name_en as name';
        } else {
            $field = 'a.name';
        }

        $where = [
            'a.status' => 1,
        ];
        $list = self::alias('a')->where($where)->
        field($field . ",a.id,a.node_name,a.price_type,a.tnum,a.price_usdt,a.price_usdt_currency_id,a.price_cny,a.price_cny_currency_id,a.days,a.amount,a.total_amount,a.quota,b.currency_name as price_usdt_currency_name,c.currency_name as price_cny_currency_name,d.currency_name as mining_currency_name")
            ->join(config("database.prefix") . "currency b", "a.price_usdt_currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.price_cny_currency_id=c.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency d", "a.mining_currency_id=d.currency_id", "LEFT")
            ->page($page, $rows)->order(['a.sort' => 'asc'])->select();
        if (empty($list)) return $r;

        $mining_price = self::mining_price(); // 获取1T的价格
        if ($mining_price === false) {
            $r['message'] = lang('price_error');
            return $r;
        }
        $mining_discount_price = CommonMiningConfig::getValue('mining_discount_price', 0); // 获取优惠价格
        foreach ($list as &$item) {
            $item['process'] = $item['total_amount'] > 0 ? keepPoint(($item['total_amount'] - $item['amount']) / $item['total_amount'] * 100, 2) : 0;
            if ($item['price_type']) {
                if ($item['tnum'] == 10) {
                    $item['price_usdt'] = intval($mining_price['mining_usdt_price'] * $item['tnum'] * $mining_discount_price / 100);
                    $item['price_cny'] = intval($mining_price['mining_cny_price'] * $item['tnum'] * $mining_discount_price / 100);
                } else {
                    $item['price_usdt'] = $mining_price['mining_usdt_price'] * $item['tnum'];
                    $item['price_cny'] = $mining_price['mining_cny_price'] * $item['tnum'];
                }
                $item['show540'] = $item['tnum'] >= 1 ? 1 : 0; // 1显示，0隐藏
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    static function getProductInfo($member_id, $product_id, $lang)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        if ($lang == 'en') {
            $field = 'a.name_en as name';
        } else {
            $field = 'a.name';
        }

        $where = [
            'a.id' => $product_id,
            'a.amount' => ['gt', 0],
            'a.status' => 1,
        ];
        $list = self::alias('a')->where($where)->
        field($field . ",a.id,a.node_name,a.price_type,a.tnum,a.price_usdt,a.price_usdt_currency_id,a.price_cny,a.price_cny_currency_id,a.days,a.quota,a.amount,b.currency_name as price_usdt_currency_name,c.currency_name as price_cny_currency_name,d.currency_name as mining_currency_name")
            ->join(config("database.prefix") . "currency b", "a.price_usdt_currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency c", "a.price_cny_currency_id=c.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency d", "a.mining_currency_id=d.currency_id", "LEFT")
            ->find();
        if (empty($list)) return $r;

        // 计算价格
        if ($list['price_type']) {
            $mining_price = self::mining_price(); // 获取1T的价格
            if ($mining_price === false) {
                $r['message'] = lang('price_error');
                return $r;
            }
            $mining_discount_price = CommonMiningConfig::getValue('mining_discount_price', 0); // 获取优惠价格
            if ($list['tnum'] == 10) {
                $list['price_usdt'] = intval($mining_price['mining_usdt_price'] * $list['tnum'] * $mining_discount_price / 100);
                $list['price_cny'] = intval($mining_price['mining_cny_price'] * $list['tnum'] * $mining_discount_price / 100);
            } else {
                $list['price_usdt'] = $mining_price['mining_usdt_price'] * $list['tnum'];
                $list['price_cny'] = $mining_price['mining_cny_price'] * $list['tnum'];
            }
        }

        $list['show540'] = $list['tnum'] >= 1 ? 1 : 0; // 1显示，0隐藏

        $percent = CommonMiningConfig::getValue('release_manager_fee_percent', 0);
        $list['percent'] = 100 - $percent;

        $member_id = intval($member_id);
        $currency_user_usdt = CurrencyUser::getCurrencyUser($member_id, $list['price_usdt_currency_id']);
        $currency_user_cny = CurrencyUser::getCurrencyUser($member_id, $list['price_cny_currency_id']);

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'product' => $list,
            'currency_num' => [
                'usdt' => $currency_user_usdt ? $currency_user_usdt['num'] : 0,
                'cny' => $currency_user_cny ? $currency_user_cny['num'] : 0,
            ],
            'quan' => self::getMyQuan($member_id, $list['price_usdt_currency_id']),
        ];
        return $r;
    }

    // 获取我的代金券
    static function getMyQuan($member_id, $currency_id)
    {
        $quan_list = Db::name('voucher_member')->alias('a')->field('a.id,a.cny,a.status,a.expire_time,b.name')->where('a.member_id', $member_id)
            ->where('a.status', 0)->where('a.expire_time', 'gt', time())
            ->join(config("database.prefix") . "voucher_config b", "a.voucher_id=b.id", "LEFT")
            ->order('a.expire_time asc')->limit(10)->select();
        if (empty($quan_list)) return [];

        $usdt_price = CurrencyPriceTemp::get_price_currency_id($currency_id, 'CNY');
        foreach ($quan_list as &$item) {
            $item['expire_time'] = date('m-d H:i', $item['expire_time']);
            $item['usdt'] = keepPoint($item['cny'] / $usdt_price, 6);
        }
        return $quan_list;
    }

    public function currency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'mining_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function usdtcurrency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'price_usdt_currency_id', 'currency_id')->field('currency_id,currency_name');
    }

    public function cnycurrency()
    {
        return $this->belongsTo('app\\common\\model\\Currency', 'price_cny_currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
