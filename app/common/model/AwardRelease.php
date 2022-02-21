<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
use think\Db;
use think\Exception;

/**
 *赠送释放
 */
class AwardRelease extends Base {
    public function getList($member_id,$page,$page_size,$platform=''){
        $list = Db::name('currency_award_freed')->alias('a')
                ->field('a.clf_id,a.rate,a.money,a.total,a.time,b.currency_mark')
                ->where(['a.member_id'=>$member_id])
                ->join('__CURRENCY__ b','a.currency_id=b.currency_id','LEFT')
                ->limit(($page - 1) * $page_size, $page_size)->order("a.clf_id desc")->select();
        if(!$list) return [];

        foreach ($list as $key => $value) {
            $value['rate'] = $value['rate'] * 10;
            $value['time'] = date('Y-m-d H:i',$value['time']);
            $list[$key] = $value;
        }
        return $list;
    }

    public function getUserNum($member_id) {
        $currency = Db::name('currency')->field('currency_id')->where(['currency_mark'=>'XRP'])->find();
        if(!$currency) return ['num_award'=>0,'sum_award'=>0];

        $info = Db::name('currency_user')->field('num_award,sum_award')->where(['member_id'=>$member_id,'currency_id'=>$currency['currency_id']])->find();
        if(!$info) return ['num_award'=>0,'sum_award'=>0];

        return $info;
    }

    //所有资产转换成XRP
    public function toXrp($member_id) {
        $xrpCurrency = Db::name('currency')->field('currency_id')->where(['currency_mark'=> 'XRP'])->find();
        if(!$xrpCurrency) return 0;

        $price = $this->getPrice();
        if(empty($price[$xrpCurrency['currency_id']])) return 0;

        $total = 0;
        $currency_user_list = Db::name('currency_user')->field('currency_id,num,forzen_num,num_award,lock_num,exchange_num')->where(['member_id'=>$member_id])->select();
        if($currency_user_list) {
            foreach ($currency_user_list as $c_user) {
                if(!empty($price[$c_user['currency_id']])) {
                    $total += $price[$c_user['currency_id']] * ($c_user['num']+$c_user['forzen_num']);
                }
            }
        }

        //持币生息
        $money_interest_list = Db::name('money_interest')->field('member_id,currency_id,num')->where(['member_id'=>$member_id,'status'=>0])->select();
        if($money_interest_list) {
            foreach ($money_interest_list as $c_user) {
                if(!empty($price[$c_user['currency_id']])) {
                    $total += $price[$c_user['currency_id']] * ($c_user['num']);
                }
            }
        }

        //瑞波钻 入金+瑞波钻
        //2019.04.15 取消入金
        $xrpz = Db::name('boss_plan_info')->field('num,xrpz_num,xrpz_forzen')->where(['member_id'=>$member_id])->find();
        if($xrpz) {
            $total += $price[$xrpCurrency['currency_id']] * ($xrpz['xrpz_num'] + $xrpz['xrpz_forzen']);
        }

        return keepPoint($total/$price[$xrpCurrency['currency_id']],6);
    }

    //所有资产转换成BTC
    public function toBtc($member_id) {
        $btcCurrency = Db::name('currency')->field('currency_id')->where(['currency_mark'=> 'BTC'])->find();
        if(!$btcCurrency) return 0;

        $price = $this->getPrice();
        if(empty($price[$btcCurrency['currency_id']])) return 0;

        $total = 0;
        $currency_user_list = Db::name('currency_user')->field('currency_id,num,forzen_num,num_award,lock_num,exchange_num')->where(['member_id'=>$member_id])->select();
        if($currency_user_list) {
            foreach ($currency_user_list as $c_user) {
                if(!empty($price[$c_user['currency_id']])) {
                    $total += $price[$c_user['currency_id']] * ($c_user['num']+$c_user['forzen_num']);
                }
            }
        }

        //持币生息
        $money_interest_list = Db::name('money_interest')->field('member_id,currency_id,num')->where(['member_id'=>$member_id,'status'=>0])->select();
        if($money_interest_list) {
            foreach ($money_interest_list as $c_user) {
                if(!empty($price[$c_user['currency_id']])) {
                    $total += $price[$c_user['currency_id']] * ($c_user['num']);
                }
            }
        }

        //获取XRP
        $xrpCurrency = Db::name('currency')->field('currency_id')->where(['currency_mark'=> 'XRP'])->find();
        if($xrpCurrency) {
            //瑞波钻
            $xrpz = Db::name('boss_plan_info')->field('num,xrpz_num,xrpz_forzen')->where(['member_id'=>$member_id])->find();
            if($xrpz) {
                $total += $price[$xrpCurrency['currency_id']] * ($xrpz['xrpz_num'] + $xrpz['xrpz_forzen'] + $xrpz['num']);
            }
        }

        return keepPoint($total/$price[$btcCurrency['currency_id']],6);
    }

    //XRP转换成BTC
    public function xrpToBtc($num) {
        if($num<=0) return 0;

        $price = $this->getPrice();
        return keepPoint(($price['xrp'] * $num)/$price['btc'],6);
    }

    public function getPrice() {
        $return = [];

        $cache_key = 'award_release_list';
        $data = cache($cache_key);
        if(empty($data)) {
            $currency = Db::name('currency')->where(['is_line' => 1])->field("currency_id,currency_mark")->select();
            foreach ($currency as $cu) {
                $price = [];
                if($cu['currency_mark']=='USDT'){
                    $price['close'] = getUsdtCny();
                } else {
                    $price = getCnyPrice($cu['currency_mark'].'USDT');
                }

                if(!empty($price)) $return[$cu['currency_id']] = $price['close'];
            }
            cache($cache_key,$return,300); //缓存5分钟
        } else {
            $return = $data;
        }
        return $return;
    }
}
