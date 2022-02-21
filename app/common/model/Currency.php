<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
use think\Exception;
use think\Db;
class Currency extends Base {

    const BTC_ID = 1;
    const ETH_ID = 3;
    const USDT_ID = 5;
    const USDT_BB_ID = 65; //币币交易USDT ID

    const XRP_ID = 8;
    const IWC_ID = 16;
    const ERC20_ID = 20;
    const TRC20_ID = 40;
    const KOI_ID = 35;
    const XRP_PLUS_ID = 35;
    const DNC_ID = 38;

    const IDUN_ID = 25;
    const ODUN_ID = 26;
    const IOSCORE_ID = 23;
    const TFT_ID = 63;
    const ABF_BB_ID = 70; // ABF BB账户ID

    //公链名称
    const PUBLIC_CHAIN_NAME = 'AC';
    //公链对应币种ID
    const PUBLIC_CHAIN_ID = 63;

    const TRANSFER_FIELD_TYPE = [
        self::XRP_PLUS_ID => 'num',
        self::DNC_ID => 'dnc_lock'
    ];


    /**
     * 购物组合币种
     * @var array
     */
    const SHOP_COMPOSE_CURRENCY = [
        self::IDUN_ID, self::ODUN_ID, self::IOSCORE_ID
    ];

    /**
     * 组合币种扣除资产类型 game_lock|uc_card_lock|uc_card
     * @var array
     * @deprecated
     */
    const SHOP_COMPOSE_CURRENCY_USER_TYPE_ENUM = [
        self::IDUN_ID => 'uc_card',
        self::ODUN_ID => 'uc_card_lock',
        self::IOSCORE_ID => 'game_lock'
    ];

    protected $resultSetType = 'collection';

    //上线币种列表
    public function online_list() {
        $currency = Db::name('currency')->field('currency_id,currency_name')->where(['is_line' => 1])->order('sort asc')->select();
        if($currency) return $currency;

        return [];
    }

    //获取全部积分类型信息
    public static function currency()
    {
        return self::where('is_line=1 ')->order('sort ASC')->select();
    }

    /**
     * 获取购物组合币种信息
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getShopComposeCurrency()
    {
        return self::where('currency_id', 'in', self::SHOP_COMPOSE_CURRENCY)->field('currency_id, currency_name, currency_mark')->select();
    }


    //常规验证
    public function common_check($currency_id,$type='trade') {
        //获取广告资产类型信息
        $currency=Db::name('currency')->where(['currency_id'=>$currency_id])->find();
        if(!$currency) return lang('lan_Invalid_currency');

        if($type=='trade') {
            //时间限制
            if($currency['is_time']){
                $newtime=date("H",time());
                $min_time=$currency['min_time'];
                if($newtime<$min_time) return lang('lan_trade_no_time_to');

                $max_time=$currency['max_time']-1;
                if($newtime>$max_time) return lang('lan_trade_over_time');
            }
        } elseif($type=='otc') {
            //时间限制
            if($currency['is_time_otc']){
                $newtime=date("H",time());
                $min_time=$currency['min_time_otc'];
                if($newtime<$min_time) return lang('lan_trade_no_time_to');

                $max_time=$currency['max_time_otc']-1;
                if($newtime>$max_time) return lang('lan_trade_over_time');
            }
        }

        //限制星期6天兑换
        $da = date("w");
        if ($da == '6' && $currency['trade_day6']) return lang('lan_trade_six_today');
        if ($da == '0' && $currency['trade_day7']) return lang('lan_trade_Sunday');

        return $currency;
    }
}
