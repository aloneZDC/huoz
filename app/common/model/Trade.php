<?php
namespace app\common\model;
use app\cli\controller\TradeRelease;
use think\Cache;
use think\Log;
use think\Model;
use think\Db;
use think\Exception;

//币币交易
class Trade extends Base {
    const TRADE_UNIX = 'CNY'; //币币交易 CNY USD  空字符串时和  NEW_PRICE_UNIT 一致
    const MOST_CURRENCY = [ //非主流币种交易对获取价格
        Currency::USDT_BB_ID,
    ];

    //获取最新一个成交记录
    static function getLastTrade($currency_id,$currency_trade_id){
        return self::where(['currency_id'=>$currency_id,'currency_trade_id'=>$currency_trade_id,'type'=>'buy'])->order('trade_id desc')->find();
    }

    //获取最新一个成交记录价格
    static function getLastTradePrice($currency_id,$currency_trade_id) {
        $trade = self::getLastTrade($currency_id,$currency_trade_id);
        if($trade) return $trade['price'];
        return 0;
    }

    //获取（币币对前币种）币种真实价值 CNY 或 USD
    static function getCurrencyRealMoney($currency_id,$currency_trade_id,$unit='USD') {
        $trade_price = self::getLastTradePrice($currency_id,$currency_trade_id);
        $currency_trade_real_money = self::getCurrencyTradeRealMoney($currency_trade_id,$unit);
        return keepPoint($trade_price*$currency_trade_real_money,2);
    }

    //获取（币币对后币种）币种真实价值 CNY 或 USD
    static function getCurrencyTradeRealMoney($currency_trade_id,$unit) {
        $real_money = CurrencyPriceTemp::get_price_currency_id($currency_trade_id,$unit);
        if($real_money==0) {
            /*
                A/B B/USDT 场景获取价格
            */
            $cur_currency_id = 0;
            $cur_price = 0;
            foreach (self::MOST_CURRENCY as $most_currency_id) {
                $most_price = self::getLastTradePrice($currency_trade_id,$most_currency_id);
                if($most_price) {
                    $cur_currency_id = $most_currency_id;
                    $cur_price = $most_price;
                    break;
                }
            }
            if(!$cur_price) return 0;

            $usdt_money = CurrencyPriceTemp::get_price_currency_id($cur_currency_id,$unit);
            return keepPoint($cur_price * $usdt_money,6);
        }
        return $real_money;
    }

    //币标签ID转成2个currency_ID
    static function currencyid_currencyid($currency_currency) {
        $data = explode('_',$currency_currency);
        return [
            'currency_id' => intval($data[0]),
            'currency_trade_id' => isset($data[1]) ? intval($data[1]) : 0
        ];
    }

    //获取最新的一条真实K线
    static function getNewKline($currency_id,$currency_trade_id,$time) {
        $res =  self::field("price as open_price,min(price) as low_price,max(price) as hign_price,sum(num) as num")->where([
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'type' => 'buy',
        ])->where('add_time>='.$time)->find();
        if(!$res) return null;

        $res['add_time'] = $time;
        $res['close_price'] = self::getLastTradePrice($currency_id,$currency_trade_id);
        return $res;
    }

    //获取比比对 24小时成交量
    static function get24HourTradeNum($currency_id,$currency_trade_id) {
        $cache_key = '24HourTradeNum_'.$currency_id.'_'.$currency_trade_id;
        $res = cache($cache_key);
        if(empty($res)) {
            $time = time()-86400;
            $res =  self::field("SUM(num) as num ,SUM(num * price) as numPrice")->where([
                'currency_id' => $currency_id,
                'currency_trade_id' => $currency_trade_id,
                'type' => 'buy',
            ])->where('add_time>'.$time)->find();
            if(!$res) $res = ['num'=>0,'numPrice'=>0];

            cache($cache_key,$res,10);
        }
        return $res;
    }

    //获取比比对 24小时【开盘价 最低价格 最高价格】
    static function get24HourOpenMinMaxPrice($currency_id,$currency_trade_id){
        $cache_key = '24HourOpenMinMaxPrice_'.$currency_id.'_'.$currency_trade_id;
        $res = cache($cache_key);
        if(empty($res)) {
            $time = time()-86400;
            $res =  self::field("price,min(price) as min_price,max(price) as max_price,trade_id,add_time")->where([
                'currency_id' => $currency_id,
                'currency_trade_id' => $currency_trade_id,
                'type' => 'buy',
            ])->where('add_time>'.$time)->find();
            if(!$res) $res = ['price'=>0,'min_price'=>0,'max_price'=>0,'trade_id'=>0,'add_time'=>0];

            cache($cache_key,$res,2);
        }
        return $res;
    }

    static function getCurrency($currency_id,$currency_trade_id=0) {
        $where = [
            'currency_id' => $currency_id,
            'is_line' => 1,
        ];
        if($currency_trade_id>0)  $where['trade_currency_id'] = ['like','%'.$currency_trade_id.'%'];

        $currency = Currency::where(['currency_id' => $currency_id,'is_line' => 1])->find();
        if(!$currency) return [];
        return $currency;
    }

    //获取币币对描述信息
    static function getCurrencyDesc($currency_id,$language) {
        $introduce = Db::name('Currency_introduce')->where('currency_id='.$currency_id)->find();
        if(!$introduce) {
            return [
                'feature' => '',
                'short' => '',
                'advantage' => '',
            ];
        }

        switch($language) {
            case 'zh-tw':
                $introduce1 = Db::name('Currency_introduce_tc')->where('currency_id='.$currency_id)->find();
                break;
            case 'en-us':
                $introduce1 = Db::name('Currency_introduce_en')->where('currency_id='.$currency_id)->find();
                break;
            case 'th-th':
                $introduce1 = Db::name('Currency_introduce_th')->where('currency_id='.$currency_id)->find();
                break;
            default:
                $introduce1 = Db::name('Currency_introduce')->where('currency_id='.$currency_id)->find();
                break;
        }

        if($introduce1) {
            $introduce['feature'] = $introduce1['feature'];
            $introduce['short'] = $introduce1['short'];
            $introduce['advantage'] = $introduce1['advantage'];
        }

        $introduce['feature'] = html_entity_decode($introduce['feature']);
        $introduce['short'] = html_entity_decode($introduce['short']);
        $introduce['advantage'] = html_entity_decode($introduce['advantage']);
        return $introduce;
    }

    //获取币币对交易信息
    static function getCurrencyMessage($currency,$currency_trade_id) {
        $currency_id = $currency['currency_id'];
        $unit = self::TRADE_UNIX ?: NEW_PRICE_UNIT;

        //最新交易价格
        $data['new_price'] = self::getLastTradePrice($currency_id,$currency_trade_id);
        //是否开启机器人
        $is_huobi = false;
        $autoTrade = Db::name('currency_autotrade')->where(['currency_id'=>$currency_id,'trade_currency_id'=>$currency_trade_id,'is_autotrade'=>1,'kline_huobi'=>1])->find();
        if ($autoTrade) {
            //if (!$data['new_price']) {
                $where = [
                    'type'=>60,
                    'currency_id'=>$currency_id,
                    'currency_trade_id'=>$currency_trade_id,
                ];
                $kline = Kline::where($where)->order('add_time', 'DESC')->find();
                if ($kline) {
                    $is_huobi = true;
                    $data['new_price'] = $kline['close_price']; //floattostr($kline['close_price'])
                }
            //}
        }
        //交易对 后面币种人民币价格
        $currency_trade_real_money = self::getCurrencyTradeRealMoney($currency_trade_id,$unit);
//        $currency_trade_real_money = ShopConfig::get_value('hm_price',6.2);
        //交易对 前面币种人民币价格
        $data['new_trade_price_cny'] = $currency_trade_real_money;
        $data['new_price_usd'] = $data['new_price_cny'] = keepPoint($data['new_price']*$currency_trade_real_money,6);
        $data['new_price'] = keepPoint($data['new_price'],6);

        if($unit=='CNY') {
            $data['new_price_unix_icon'] = '¥';
        } else {
            $data['new_price_unix_icon'] = '$';
        }
        $data['new_price_unit'] = $unit;

        //24H成交量
        $tradeNum = self::get24HourTradeNum($currency_id,$currency_trade_id);
        if ($tradeNum['num'] == 0) {
            $data['24H_done_num'] = '0.00';
        }else {
            $data['24H_done_num'] =  keepPointV2($tradeNum['num'] * 2 + $currency['num_number'],2);
        }
        if ($tradeNum['numPrice'] == 0) {
            $data['24H_done_money'] = '0.00';
        } else {
            $data['24H_done_money'] =   round($tradeNum['numPrice'] * 2 + $currency['num_number'] * $data['new_price'],6);
        }
        $data['done_num_24H'] =keepPointV2($data['24H_done_num'], 2);
        $data['done_money_24H'] =keepPointV2($data['24H_done_money'], 6);


        //24H最低价
        $openMinMaxPrice = self::get24HourOpenMinMaxPrice($currency_id,$currency_trade_id);
        if ($is_huobi) {
            //一天前的时间
            $time = time();
            $sql_time = strtotime(date('Y-m-d', $time));
            $priceWhere = [
                'currency_id'=>$currency_id,
                'currency_trade_id'=>$currency_trade_id,
                'type'=>60,
                'add_time'=>['gt',$sql_time],
            ];
            //$rs = \app\common\model\Kline::where($priceWhere)->field('open_price as price,min(low_price) as minprice,max(hign_price) as maxprice,sum(num) as 24H_done_num')->find();
            $today = strtotime(date('Y-m-d 00:00:00', $time));
            $priceWhere['type'] = 86400;
            $priceWhere['add_time'] = $today;
            $rs1 = \app\common\model\Kline::where($priceWhere)->field('id,open_price,low_price,hign_price')->find();
            $openMinMaxPrice['price'] = $rs1['open_price'];
            $openMinMaxPrice['min_price'] = $rs1['low_price'];
            $openMinMaxPrice['max_price'] = $rs1['hign_price'];
        }
        $data['open_price'] = $openMinMaxPrice['price'];
        $data['open_trade_id'] = $openMinMaxPrice['trade_id'];
        $data['open_add_time'] = $openMinMaxPrice['add_time'];
        $data['min_price'] = keepPoint($openMinMaxPrice['min_price'],6);
        if ($data['min_price'] == 0) {
            $data['min_price'] = '0.00';
        }
        //24H最高价
        $data['max_price'] = keepPoint($openMinMaxPrice['max_price'],6);
        if ($data['max_price'] == 0) {
            $data['max_price'] = '0.00';
        }
        if ($openMinMaxPrice['price'] > $data['new_price'] ) {
            //说明价格下降
            $data['new_price_status'] = 0;
        } else {
            $data['new_price_status'] = 1;
        }

        //(最新价 - 开盘价) / 开盘价
        $data['24H_change'] = !empty( $openMinMaxPrice['price'])? sprintf("%.2f", ($data['new_price']  - $openMinMaxPrice['price']) / $openMinMaxPrice['price'] * 100):0;
        $data['24H_change_price'] = sprintf("%.2f", ($data['new_price']  - $openMinMaxPrice['price']));//24H价格变化值
        if ($data['24H_change'] == 0) {
            $data['24H_change'] = '0.00';
        }

        if($data['new_price_status']==1){
            $data['24H_change']= '+'. keepPointV2($data['24H_change'], 2);
        }elseif ($data['new_price_status']==0){
            $data['24H_change']= keepPointV2($data['24H_change'], 2);
        }
        $data['change_24'] = $data['24H_change']; //兼容

        return $data;
    }

    //获取用户是否收藏该币币对
    static function isMemberCollect($member_id,$currency_id,$currency_trade_id) {
        if($member_id<=0) return 0;

        $currency_collect =Db::name('currency_collect')->where(['member_id' => $member_id, 'currency_id' => $currency_id,'trade_currency_id'=>$currency_trade_id])->find();
        if(!$currency_collect) return 0;

        return 1;
    }

    //K线图页面接口
    static function icon_info($member_id,$currency_currency,$language) {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_close");
        $r['result'] = null;

        $currency_currency = self::currencyid_currencyid($currency_currency);
        $currency_id = $currency_currency['currency_id'];
        $currency_trade_id= $currency_currency['currency_trade_id'];
        $trade_currency = self::getCurrency($currency_trade_id);
        if(!$trade_currency) return $r;

        $currency = self::getCurrency($currency_id,$currency_trade_id);
        if(!$currency) return $r;


        $special_area = CurrencyArea::area_info($currency['currency_id']);
        $special_area = $special_area['code']==SUCCESS ? $special_area['result'] : [];
        $result = [
            'is_defaule' => self::isMemberCollect($member_id,$currency_id,$currency_trade_id), //是否收藏
            'currency_message' => self::getCurrencyMessage($currency,$currency_trade_id),
            'currency' => [
                'currency_id' => $currency['currency_id'],
                'currency_name' => $currency['currency_name'],
                'currency_mark' => $currency['currency_mark'],
                'trade_currency_id' => $trade_currency['currency_id'],
                'trade_currency_name' => $trade_currency['currency_name'],
                'trade_currency_mark' => $trade_currency['currency_mark'],
            ],
            'cid_value' => self::getCurrencyDesc($currency_id,$language),
            'special_area' => $special_area
        ];

        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_user_request_successful');
        $r['result'] = $result;
        return $r;
    }

    //获取买盘
    static function getBuyList($currency_id,$currency_trade_id,$count) {
        $list = Db::name('Orders')->where([
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'type' => 'buy',
            'status' => ['in',[0,1]]
        ])->field("sum(num) as num,sum(trade_num) as trade_num,price,type,status")
            ->group('price')->order("price desc, add_time asc")->limit($count)->select();
        if(!$list) return [];

        //随机一部分假数据
//        $rand_num = $count-count($list);
//        if($rand_num>0) {
//            $first = current($list);
//            $last_price = $first['price'];
//            for ($i=0;$i<$rand_num;$i++) {
//                $last_price -= self::randTradePrice();
//                array_unshift($list, [
//                    'num' => self::randTradeNum(),
//                    'trade_num' => 0,
//                    'price' => keepPoint($last_price,6),
//                    'type' => 'sell',
//                    'status' => 0,
//                ]);
//            }
//        }

        foreach ($list as $k => &$val) {
//            $rand = self::randTradeNum();
//            $val['num'] += $rand;

            $val['bili'] = 100 - ($val['trade_num'] / $val['num'] * 100);
            $val['cardinal_number'] = 0;
            $val['price_usd'] = format_price_usd($val['price'] / usd2cny());//对美元的价格
            self::formatNumber($val, $currency_id);
            if($val['trade_num']<=0) $val['trade_num'] = $val['num'];
        }
        return $list;
    }

    //获取卖盘
    static function getSellList($currency_id,$currency_trade_id,$count) {
        $list = Db::name('Orders')->where([
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'type' => 'sell',
            'status' => ['in',[0,1]]
        ])->field("sum(num) as num,sum(trade_num) as trade_num,price,type,status")
            ->group('price')->order("price asc, add_time asc")->limit($count)->select();
        if(!$list) return [];

        //随机一部分假数据
//        $rand_num = $count-count($list);
//        if($rand_num>0) {
//            $first = current($list);
//            $last_price = $first['price'];
//            for ($i=0;$i<$rand_num;$i++) {
//                $last_price += self::randTradePrice();
//                array_unshift($list, [
//                    'num' => self::randTradeNum(),
//                    'trade_num' => 0,
//                    'price' => keepPoint($last_price,6),
//                    'type' => 'sell',
//                    'status' => 0,
//                ]);
//            }
//        }

        $list = array_reverse($list);
        foreach ($list as $k => &$val) {
//            $rand = self::randTradeNum();
////            $val['num'] += $rand;

            $val['bili'] = 100 - ($val['trade_num'] / $val['num'] * 100);
            $val['cardinal_number'] = 0;
            $val['price_usd'] = format_price_usd($val['price'] / usd2cny());//对美元的价格
            self::formatNumber($val, $currency_id);
            if($val['trade_num']<=0) $val['trade_num'] = $val['num'];
        }
        return $list;
    }

    //获取成交记录
    static function getTradeList($currency_id,$currency_trade_id,$count) {
        $list = Db::name('Orders')->where([
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'status' => ['in',[2,5]],
            'type' => ['neq','onebuy'],
            'trade_time' => ['elt',time()],
        ])->limit($count)->order('trade_time desc')->select();
        if(!$list) return [];

        foreach ($list as &$val) {
            $val['num']= format_num($val['num'] );
            //防止买卖盘显示0
            if($val['trade_num']<=0) {
                $val['trade_num'] = $val['num'];
            } else {
                $val['trade_num']= format_num($val['trade_num']);
            }
            $val['price']=keepPoint($val['price'], 4);
            $val['price'] = strval($val['price']);
            $val['trade_num'] = strval($val['trade_num']);
            $val['num'] = strval($val['num']);
        }
        return $list;
    }

    static function getOpenTime($currency_id) {
        $res = [
            'is_open' => 0,
            'is_time' => 0,
            'min_time' => 0,
            'max_time' => 0,
        ];
        $currency = Currency::field('is_time,min_time,max_time')->where('currency_id',$currency_id)->find();
        if(!$currency) return $res;

        $res = $currency;
        $res['is_open'] = 1;
        $is_open = 0;
        if($res['is_time']) {
            $newtime = date("H", time());
            $min_time = $currency['min_time'];
            if ($newtime < $min_time) {
                $is_open = 1;
            }
            $max_time = $currency['max_time'] - 1;
            if ($newtime > $max_time) {
                $is_open = 1;
            }
        }
        $res['is_time'] = $is_open;
        return $res;
    }

    //获取买卖盘及成交记录 接口调用
    static function getBuySellTrade($currency_currency,$count) {
        $currency_currency = self::currencyid_currencyid($currency_currency);
        $currency_id = $currency_currency['currency_id'];
        $currency_trade_id= $currency_currency['currency_trade_id'];

        $new_trade_key = 'new_trade'.$currency_id.'_'.$currency_trade_id;
        $new_trade = cache($new_trade_key);
        if(empty($new_trade)) {
            $new_trade = self::getCurrencyMessage(['currency_id' => $currency_id, 'num_number' => 0],$currency_trade_id);
            cache($new_trade_key,$new_trade,2);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_user_request_successful');
        $r['result'] = [
            'trade_open' => self::getOpenTime($currency_id),
            'new_trade' => $new_trade,
            'buy_list' => self::getBuyList($currency_id,$currency_trade_id,$count),
            'sell_list' => self::getSellList($currency_id,$currency_trade_id,$count),
            'trade_list' => self::getTradeList($currency_id,$currency_trade_id,50)
        ];
        return $r;
    }

    //获取行情数据
    static function getQuotation1() {
        return self::getQuatitionByMark('USDTBB');
    }

    //单列的行情接口
    static function getAllQuotation() {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;

        $cache_key = 'quatition_all';
        $quatition = cache($cache_key);
        if(empty($data)) {
            $currency_list = Currency::where(['is_line' => 1,'is_trade_currency'=>1])
                ->field('currency_id,trade_currency_id,currency_name,currency_mark,currency_logo,num_number,is_lock,currency_buy_fee,currency_sell_fee,trade_min_num')
                ->select();
            if(!$currency_list || $currency_list->isEmpty()) return $r;

            $quatition = [];
            $currency_list = array_column($currency_list->toArray(),null,'currency_id');
            foreach ($currency_list as $currency) {
                $trade_currency_ids = explode(',',$currency['trade_currency_id']);
                foreach ($trade_currency_ids as $trade_currency_id){
                    if(!isset($currency_list[$trade_currency_id])) continue;

                    $data = self::getCurrencyMessage($currency,$trade_currency_id);

                    $data['is_open'] = $currency['is_lock'] ? 0 : 1; //锁定币种提示不开放
                    $data['currency_id'] = $currency['currency_id'];
                    $data['currency_name'] = $currency['currency_name'];
                    $data['currency_mark'] = $currency['currency_mark'];
                    $data['currency_buy_fee'] = floatval($currency['currency_buy_fee']);
                    $data['currency_sell_fee'] = floatval($currency['currency_sell_fee']);
                    $data['trade_currency_name'] = $currency_list[$trade_currency_id]['currency_name'];
                    $data['trade_currency_mark'] = $currency_list[$trade_currency_id]['currency_mark'];
                    $data['trade_currency_id'] = $currency_list[$trade_currency_id]['currency_id'];
                    $data['trade_min_num'] = floattostr($currency['trade_min_num']);
                    $quatition[] = $data;
                }
            }
            cache($cache_key,$quatition,2); //缓存2秒
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = $quatition;
        return $r;
    }

    //多列的行情接口
    static function getQuatitionByMark($currency_mark) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;

        $cache_key = 'quatition1_'.$currency_mark;
        $quatition = cache($cache_key);
        if(empty($data)) {
            $trade_currency = Currency::where([
                'is_line' => 1,
                'currency_mark' => $currency_mark,
            ])->field('currency_id,currency_name,currency_mark,currency_logo,trade_currency_id')->find();
            if(!$trade_currency) return $r;

            $currency_list =  Currency::where([
                'is_line' => 1,
                'trade_currency_id' => ['like','%'.$trade_currency['currency_id'].'%'],
            ])->field('currency_id,currency_name,currency_mark,currency_logo,num_number,is_lock,currency_buy_fee,currency_sell_fee')->select();
            if(!$trade_currency || $currency_list->isEmpty()) return $r;

            $quatition = [];
            foreach ($currency_list as $currency) {
                $data = self::getCurrencyMessage($currency,$trade_currency['currency_id']);

                $data['is_open'] = $currency['is_lock'] ? 0 : 1; //锁定币种提示不开放
                $data['currency_id'] = $currency['currency_id'];
                $data['currency_name'] = $currency['currency_name'];
                $data['currency_mark'] = $currency['currency_mark'];
                $data['currency_buy_fee'] = floatval($currency['currency_buy_fee']);
                $data['currency_sell_fee'] = floatval($currency['currency_sell_fee']);
                $data['trade_currency_name'] = $trade_currency['currency_name'];
                $data['trade_currency_mark'] = $trade_currency['currency_mark'];
                $data['trade_currency_id'] = $trade_currency['currency_id'];
                $quatition[] = $data;
            }
            cache($cache_key,$quatition,2); //缓存2秒
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = $quatition;
        return $r;
    }

    //限制购买
    static function purchase_limit($type,$member_id,$currency_id,$currency_trade_id,$price,$num) {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = null;

        $time = time();
        $config = Db::name('trade_day_config')
            ->where('currency_id',$currency_id)->where('currency_trade_id',$currency_trade_id)
            ->where('start_time','elt',$time)->where('stop_time','gt',$time)->find();
        if($config && strpos($config['robot_id'],strval($member_id))===false) {
            if( ($type=='sell' && $config['is_sell']!=1) ||  ($type=='buy' && $config['is_buy']!=1) ) {
                $r['message'] = lang('lan_close');
                return $r;
            }

            //涨跌幅限制
            if($config['price_percent']>0) {
                //获取昨日收盘价 没有则使用发行价
                $trade = self::where([
                    'currency_id'=>$currency_id,
                    'currency_trade_id'=>$currency_trade_id,
                    'type'=>'buy',
                    'add_time' => ['lt',todayBeginTimestamp()],
                ])->field('price')->order('trade_id desc')->find();
                if($trade) {
                    $limit_price = $trade['price'];
                } else {
                    $limit_price = $config['open_price'];
                }

                $min_limit_price = keepPoint($limit_price*(1-$config['price_percent']/100),6);
                $max_limit_price = keepPoint($limit_price*(1+$config['price_percent']/100),6);

                if( $price< $min_limit_price || $price>$max_limit_price) {
                    $r['message'] = lang('price_between',['num1'=>$min_limit_price,'num2'=>$max_limit_price]);
                    return $r;
                }
            }

            if($type=='buy') {
                if($config['min_price']>0 && $price<$config['min_price']) {
                    $r['message'] = lang('purchase_limit_min_price');
                    return $r;
                }
                if($config['max_price']>0 && $price>$config['max_price']) {
                    $r['message'] = lang('purchase_limit_max_price');
                    return $r;
                }

                if($config['min_num']>0 && $num<$config['min_num']){
                    $r['message'] = lang('purchase_limit_min_num');
                    return $r;
                }
                if($config['max_num']>0 && $num>$config['max_num']){
                    $r['message'] = lang('purchase_limit_max_num');
                    return $r;
                }
            } else {
                if($config['sell_min_price']>0 && $price<$config['sell_min_price']) {
                    $r['message'] = lang('purchase_limit_min_price');
                    return $r;
                }
                if($config['sell_max_price']>0 && $price>$config['sell_max_price']) {
                    $r['message'] = lang('purchase_limit_max_price');
                    return $r;
                }

                if($config['sell_min_num']>0 && $num<$config['sell_min_num']){
                    $r['message'] = lang('purchase_limit_min_num');
                    return $r;
                }
                if($config['sell_max_num']>0 && $num>$config['sell_max_num']){
                    $r['message'] = lang('purchase_limit_max_num');
                    return $r;
                }
            }


            //个人限制交易总量
            if($type=='buy' && $config['max_trade_num']>0) {
                //已成交部分
                $has_num = Db::name('orders')->where([
                    'member_id' => $member_id,
                    'currency_id' => $currency_id,
                    'currency_trade_id' => $currency_trade_id,
                    'type' => 'buy',
                    'status' => ['in',[-1,2]],
                    'add_time' => ['between',[$config['start_time'],$config['stop_time']]],
                ])->sum('trade_num');

                //挂单数量
                $has_num_trade = Db::name('orders')->where([
                    'member_id' => $member_id,
                    'currency_id' => $currency_id,
                    'currency_trade_id' => $currency_trade_id,
                    'type' => 'buy',
                    'status' => ['in',[0,1]],
                    'add_time' => ['between',[$config['start_time'],$config['stop_time']]],
                ])->sum('num');

                $has_num += $has_num_trade;
                if( ($num+$has_num) > $config['max_trade_num'] ) {
                    $r['message'] = lang('lan_order_purchase_limit',['num1'=>$config['max_trade_num'],'num2'=>keepPoint($config['max_trade_num']-$has_num)]);
                    return $r;
                }
            }

            //平台限制总量
            if($type=='buy' && $config['all_trade_num']>0) {
                //已成交部分
                $has_num = Db::name('orders')->where([
                    'currency_id' => $currency_id,
                    'currency_trade_id' => $currency_trade_id,
                    'type' => 'buy',
                    'status' => ['in',[-1,2]],
                    'add_time' => ['between',[$config['start_time'],$config['stop_time']]],
                ])->sum('trade_num');

                //挂单数量
                $has_num_trade = Db::name('orders')->where([
                    'currency_id' => $currency_id,
                    'currency_trade_id' => $currency_trade_id,
                    'type' => 'buy',
                    'status' => ['in',[0,1]],
                    'add_time' => ['between',[$config['start_time'],$config['stop_time']]],
                ])->sum('num');
                $has_num += $has_num_trade;

                if( $has_num>=$config['all_trade_num'] ) {
                    $r['message'] = lang('lan_order_purchase_limit',['num1'=>$config['all_trade_num'],'num2'=>0]);
                    return $r;
                }

                if( ($num+$has_num) > $config['all_trade_num'] ) {
                    $r['message'] = lang('lan_order_purchase_limit',['num1'=>$config['all_trade_num'],'num2'=>keepPoint($config['all_trade_num']-$has_num)]);
                    return $r;
                }
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        return $r;
    }


    static function formatNumber(&$val, $currency_id)
    {
        $num = $val['num'] - $val['trade_num'];
        switch ($currency_id) {
            case Currency::BTC_ID:
                $val['num'] = keepPoint($num, 6);
                $val['price']= keepPoint($val['price'], 2);
                break;
            case Currency::ETH_ID:
                $val['num'] = keepPoint($num, 4);
                $val['price']= keepPoint($val['price'], 2);
                break;
            case Currency::XRP_ID:
            case Currency::IWC_ID:
                $val['num'] = keepPoint($num, 2);
                $val['price'] = keepPoint($val['price'], 5);
                break;
            default:
                $val['num'] = keepPoint($num, 4);
                $val['price'] = keepPoint($val['price'], 6);
        }
    }

    //随机交易数量
    static function randTradeNum() {
        return rand(10000,30000);
    }

    static function randTradePrice() {
        return  rand(1, 10) / 1000;
    }

    static function orders_info($member_id,$orders_id) {
        $info=Db::name("Orders")->alias("a")
            ->field("a.orders_id,a.num,a.trade_num,a.price,a.add_time,a.trade_time,b.currency_name as b_name,d.currency_name as b_trade_name,c.email,c.name,c.phone")->where([
            'member_id' => $member_id,
            'orders_id' =>$orders_id,
        ])->join(config("database.prefix")."currency b","b.currency_id=a.currency_id","LEFT")
            ->join(config("database.prefix")."currency d","d.currency_id=a.currency_trade_id","LEFT")
            ->order('orders_id desc')->find();
    }

    static function orders_trade_list($member_id,$orders_id,$page=1) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;

        $orders = Db::name('orders')->where(['member_id'=>$member_id,'orders_id'=>$orders_id])->find();
        if(!$orders) return $r;

        $type = $orders['type'] == 'buy' ? 'sell' : 'buy';
        $list = self::alias('a')->where([
            'orders_id' => $orders['orders_id'],
            'type' => $type,
        ])->field('a.trade_id,a.type,a.add_time,a.price,a.num,a.money,a.fee,b.currency_name as b_name,d.currency_name as b_trade_name')
            ->join(config("database.prefix")."currency b","b.currency_id=a.currency_id","LEFT")
            ->join(config("database.prefix")."currency d","d.currency_id=a.currency_trade_id","LEFT")
            ->page($page, 10)->order("a.trade_id desc")->select();
        if(!$list) {
            $r['message'] = lang("lan_No_data");
            return $r;
        }

        foreach ($list as &$value){
            $value['add_time'] = date('m-d H:i',$value['add_time']);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = $list;
        return $r;
    }

    static function getKlineByTime($currency_id,$currency_trade_id,$start_time,$stop_time) {
        $res =  self::field("price as open_price,min(price) as low_price,max(price) as hign_price,sum(num) as num")->where([
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'type' => 'buy',
        ])->where('add_time>='.$start_time.' and add_time<='.$stop_time)->find();
        if(!$res) return null;
        if($res['open_price']<=0) return null;

        $res['add_time'] = $start_time;

        $last = self::where([
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'type' => 'buy',
        ])->where('add_time>='.$start_time.' and add_time<='.$stop_time)->order('trade_id desc')->find();

        $res['close_price'] = $last ? $last['price'] : 0;
        return $res;
    }

    //非正式代码
    static function getYestodayMaxPrice($currency_id,$currency_trade_id) {
        $where = [
            'add_time' => ['lt',todayBeginTimestamp()],
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'type' => 'buy',
        ];
        $lastTrade = self::field('price,add_time')->where($where)->order('add_time desc')->find();
        if(!$lastTrade) return 0;

        $start_time = strtotime(date('Y-m-d',$lastTrade['add_time']));
        $stop_time = $start_time + 86399;

        $where['add_time'] = ['between',[$start_time,$stop_time] ];
        $lastTrade = self::where($where)->order('price desc')->limit(3)->select();
        $lowLastTrade = self::where($where)->order('price asc')->limit(3)->select();
        if(!$lastTrade || !$lowLastTrade) return 0;


        $totalNum = count($lastTrade) + count($lowLastTrade);
        $totalPrice = array_map(function ($item) {
            return $item['price'];
        }, $lastTrade);

        $totalLowPrice = array_map(function ($item) {
            return $item['price'];
        }, $lowLastTrade);

        $totalAllPrice = array_sum($totalPrice) + array_sum($totalLowPrice);

        return keepPoint($totalAllPrice/$totalNum,6);
    }

    static function getYestodayMaxMinPrice($currency_id,$currency_trade_id) {
        $where = [
            'add_time' => ['lt',todayBeginTimestamp()],
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'type' => 'buy',
        ];
        $lastTrade = self::field('price,add_time')->where($where)->order('add_time desc')->find();
        $start_time = strtotime(date('Y-m-d',$lastTrade['add_time']));
        $stop_time = $start_time + 86399;

        $where['add_time'] = ['between',[$start_time,$stop_time] ];
        $lastTrade = self::field('min(price) as min_price,max(price) as max_price')->where($where)->find();
        if($lastTrade) return $lastTrade;
        return ['min_price'=>0,'max_price'=>0];
    }

    //获取昨天最后一笔交易价格
    static function getYestodayLastTradePrice($currency_id,$currency_trade_id) {
        $where = [
            'add_time' => ['lt',todayBeginTimestamp()],
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'type' => 'buy',
        ];
        $lastTrade = self::field('price')->where($where)->order('add_time desc')->find();
        if($lastTrade) return $lastTrade['price'];
        return 0;
    }

    //币币交易购买 挂单
    static function buy($member_id,$buyprice,$buynum,$buypwd,$currency_currency) {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_close");
        $r['result'] = null;

        $currency_currency = self::currencyid_currencyid($currency_currency);
        $currency_id = $currency_currency['currency_id'];
        $currency_trade_id= $currency_currency['currency_trade_id'];
        if($currency_id<=0 || $currency_trade_id<=0) return $r;

        //币种
        $currency = self::getCurrency($currency_id,$currency_trade_id);
        if(empty($currency)) return $r;


        $is_free_fee = Db::name('orders_free_fee')->where(['member_id'=>$member_id])->find();
        if($is_free_fee) $currency['currency_buy_fee'] = 0;

        $currency['trade_currency_id'] = $currency_trade_id;
        if ($currency['is_lock']) return $r;


        if (!is_numeric($buyprice) || !is_numeric($buynum)) {
            $r['message'] = lang('lan_trade_wrong');
            return $r;
        }
        if ($buyprice * $buynum < 0) {
            $r['message'] = lang('lan_trade_entrust_lowest');
            return $r;
        }

        if ($buyprice<=0 || $buynum <= 0) {
            $r['message'] = lang('lan_trade_change_quantity');
            return $r;
        }

        //时间限制
        if ($currency['is_time']) {
            $newtime = date("H", time());
            $min_time = $currency['min_time'];
            if ($newtime < $min_time) {
                $r['message'] = lang('lan_trade_no_time_to');
                return $r;
            }
            $max_time = $currency['max_time'] - 1;
            if ($newtime > $max_time) {
                $r['message'] = lang('lan_trade_over_time');
                return $r;
            }
        }

        //价格限制
        if ($currency['is_limit']) {
            $getlastmessage = self::getYestodayLastTradePrice($currency_id,$currency_trade_id);
            $newprice = $getlastmessage + ($getlastmessage * $currency['max_limit']) / 100;
            $newprice2 = $getlastmessage - ($getlastmessage * $currency['min_limit']) / 100;
            if ($getlastmessage) {
                if ($buyprice > $newprice) {
                    $r['message'] = lang('lan_Buy_price_bad_failure');
                    return $r;
                }
                if ($buyprice < $newprice2) {
                    $r['message'] = lang('lan_Buy_price_bad_failure1');
                    return $r;
                }
            }
        }

        $trade_money = keepPoint($buynum * $buyprice * (1 + $currency['currency_buy_fee'] / 100),6);
        $currency_trade_user = CurrencyUser::getCurrencyUser($member_id,$currency_trade_id);
        if ($currency_trade_user['num'] < $trade_money) {
            $r['message'] = lang('lan_trade_underbalance');
            return $r;
        }

        //限制星期6天交易
        $da = date("w");
        if ($da == '6') {
            if ($currency['trade_day6']) {
                $r['message'] = lang('lan_trade_six_today');
                return $r;
            }

        }
        if ($da == '0') {
            if ($currency['trade_day7']) {
                $r['message'] = lang('lan_trade_Sunday');
                return $r;
            }
        }

        //挂单
        try{
            self::startTrans();
            $fee = $currency['currency_buy_fee'] / 100;
            $data = array(
                'type' => 'buy',
                'member_id' => $member_id,
                'currency_id' => $currency_id,
                'currency_trade_id' => $currency_trade_id,
                'price' => $buyprice,
                'num' => $buynum,
                'trade_num' => 0,
                'fee' => $fee,
                'add_time' => time(),
            );
            $orders_id = Db::name('Orders')->insertGetId($data);
            if(!$orders_id) throw new Exception(lang('lan_network_busy_try_again'));

            //添加账本
            $flag = AccountBook::add_accountbook($currency_trade_user['member_id'],$currency_trade_user['currency_id'],11,'lan_trade','out',$trade_money,$orders_id,0);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            //减少资产 添加冻结
            $flag = CurrencyUser::where(['cu_id'=>$currency_trade_user['cu_id'],'num'=>$currency_trade_user['num']])->update([
                'num' => ['dec',$trade_money],
                'forzen_num' => ['inc',$trade_money],
            ]);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_operation_success');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    //撮合交易
    static function trade() {

    }
    //非正式代码结束


    //根据币币交易总量 释放DNC
    static function trade_release($member_id,$trade_num,$config) {
//        $trade_num = min($trade_num,$config['trade_release_max_num']);
        $percent = keepPoint(randomFloat($config['trade_release_min_percent'],$config['trade_release_max_percent']),6);
        $release_num = keepPoint($trade_num * $percent /100,6);
        if($release_num<=0) return true;

        $currency_user = CurrencyUser::getCurrencyUser($member_id,TradeRelease::RELEASE_CURRENCY_ID);
        if(!$currency_user || $currency_user['dnc_lock']<=0) return true;

        //封顶比例
        $currency_user_top_percent = keepPoint(randomFloat($config['trade_release_limit_min_percent'],$config['trade_release_limit_max_percent']),2);
        $currency_user_top = keepPoint($currency_user['dnc_lock']*$currency_user_top_percent/100,6);

        $release_num = min($release_num,$currency_user_top);
        if($release_num<0.000001) return true;

        try{
            self::startTrans();

            //增加DNC锁仓释放记录
            $log_id = DcLockLog::add_log(DcLockLog::TRADE_RELEASE,$currency_user['member_id'],$currency_user['currency_id'],$release_num,intval($trade_num));
            if(!$log_id) throw new Exception("增加DNC锁仓释放记录失败");

            //增加账本
            $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],2300,'trade_release','in',$release_num,$log_id,0);
            if(!$flag) throw new Exception("添加账本失败");

            //增加资产
            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'dnc_lock'=>$currency_user['dnc_lock']])->update([
                'num' => ['inc',$release_num],
                'dnc_lock'=> ['dec',$release_num],
            ]);
            if(!$flag) throw new Exception("增加资产失败");


            self::commit();
        } catch (Exception $e) {
            self::rollback();
            Log::write("币币交易释放失败：".$e->getMessage());
        }
    }
}
