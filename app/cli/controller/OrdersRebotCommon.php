<?php
namespace app\cli\controller;

use app\common\model\Currency;
use app\common\model\Member;
use app\common\model\AccountBook;
use app\common\model\CurrencyUser;
use app\common\model\OrdersRebotConfig;
use app\common\model\Trade;
use think\Log;
use think\Db;
use think\Exception;

use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 * 挂单机器人公共
 */
class OrdersRebotCommon/* extends Command*/
{
    protected $configs = [];

    protected $currencyList = [];

    protected $today_config = [];

    protected $priceRate = 0;

    protected $lastTradePrice = 0;

    protected $currency;

    protected $currency_trade;

    protected $tradeName;

    protected $sleep = true;

    protected $sleepTime = 500000;//usleep 0.2s

    protected $sleepTime1 = 30;//30s，平台币交易对机器人挂单间隔

    private $time_list = [
        '1min'=>60, //1分钟
        '5min'=>300,//5分钟
        '15min'=>900,//15分钟
        '30min'=>1800,//30分钟
        '60min'=>3600,//1小时
        '1day'=>86400,//1天
        '1week'=>604800,//1周
        '1mon'=>2592000, //1月
        //'1year'=>31536000, //1年
    ];

    /**
     * 挂单机器人，每分钟执行一次
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function doRun()
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);

        $today = date('Y-m-d');

        $today_start = strtotime($today);
        $this->today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'yestday_start' => $today_start - 86400,
            'yestday_stop' => $today_start - 1,
        ];

        Log::write("挂单机器人:定时任务:" . date('Y-m-d H:i:s'), 'INFO');
        $select = Db::name('OrdersRebotTrade')->order('id asc')->select();
        foreach ($select as $key => $value) {
            $this->rebot($value);
        }
        Log::write("挂单机器人:定时任务结束:" . date('Y-m-d H:i:s'), 'INFO');
    }

    protected function init_configs($trade)
    {
        $this->configs = null;
        $this->configs = $trade;
        $this->currency = null;
        $this->currency = $this->currencyList[$trade['currency_id']];
        $this->currency_trade = null;
        $this->currency_trade = $this->currencyList[$trade['trade_currency_id']];
        $this->tradeName = $this->currency['currency_name'].'/'.$this->currency_trade['currency_name'];
    }

    protected function rebot($trade)
    {
        $this->init_configs($trade);
        var_dump($this->tradeName);
        var_dump(time());
        if(empty($this->configs) || $this->configs['rebot_switch'] != 1) {
            Log::write("挂单机器人,交易对:{$this->tradeName},配置为空或者机器人开关未开启");
            return;
        }
        $operateType = $this->configs['rebot_operate_type'];//机器人操作类型 0-横盘 1-拉盘 2-砸盘
        $hengTrend = $this->configs['rebot_heng_trend'];//机器人横盘趋势 0-正常 1-上升 2-下降
        $buyUserId = $this->configs['buy_rebot_user_id'];//卖单机器人用户id 0-代表未设置
        $sellUserId = $this->configs['sell_rebot_user_id'];//卖单机器人用户id 0-代表未设置
        $buyOrderNum = $this->configs['buy_rebot_order_num'];//买单机器人挂单次数
        $sellOrderNum = $this->configs['sell_rebot_order_num'];//卖单机器人挂单次数
        $buyTradeNumMin = $this->configs['buy_rebot_trade_num_min'];//买单机器人成交次数最小值 用于生成随机数
        $buyTradeNumMax = $this->configs['buy_rebot_trade_num_max'];//买单机器人成交次数最大值 用于生成随机数
        $sellTradeNumMin = $this->configs['sell_rebot_trade_num_min'];//卖单机器人成交次数最小值 用于生成随机数
        $sellTradeNumMax = $this->configs['sell_rebot_trade_num_max'];//卖单机器人成交次数最大值 用于生成随机数
        $tradeLimit = $this->configs['rebot_trade_limit'];//机器人当日成交总额上限 0-不限制
        $buyTradeLimit = $this->configs['buy_rebot_trade_limit'];//买单机器人当日成交总额上限 0-不限制
        $sellTradeLimit = $this->configs['sell_rebot_trade_limit'];//卖单机器人当日成交总额上限 0-不限制
        $cancelOrderType = $this->configs['rebot_cancel_order_type'];//机器人撤单类型 1-尾部 2-全部
        $type = $operateType;//成交优先级 1-拉盘，卖单优先成交 2-砸盘，买单优先成交
        $buyTradeNum= 0;
        $sellTradeNum = 0;
        if ($operateType == 0) {//0-横盘
            $rand = mt_rand(1, 2);//随机数 1-拉盘 2-砸盘
            $type = $rand;
            if ($rand == 1) {//拉盘，买单机器人成交次数>=卖单机器人成交次数
                if ($buyTradeNumMin == $buyTradeNumMax) {
                    $buyTradeNum = $buyTradeNumMin;
                }
                else {
                    $buyTradeNum = mt_rand(min($buyTradeNumMin, $buyTradeNumMax), max($buyTradeNumMin, $buyTradeNumMax));
                }
                if ($buyTradeNum > 0) {
                    $sellTradeNum = mt_rand($sellTradeNumMin, $buyTradeNum);
                }
                else {
                    $sellTradeNum = mt_rand(min($sellTradeNumMin, $sellTradeNumMax), max($sellTradeNumMin, $sellTradeNumMax));
                }
            }
            else {//砸盘，卖单机器人成交次数>=买单机器人成交次数
                if ($sellTradeNumMin == $sellTradeNumMax) {
                    $sellTradeNum = $sellTradeNumMin;
                }
                else {
                    $sellTradeNum = mt_rand(min($sellTradeNumMin, $sellTradeNumMax), max($sellTradeNumMin, $sellTradeNumMax));
                }
                if ($sellTradeNum > 0) {
                    $buyTradeNum = mt_rand($buyTradeNumMin, $sellTradeNum);
                }
                else {
                    $buyTradeNum = mt_rand(min($buyTradeNumMin, $buyTradeNumMax), max($buyTradeNumMin, $buyTradeNumMax));
                }
            }
            //机器人横盘趋势 0-正常 1-上升 2-下降
            if ($hengTrend == 1) {//1-上升
                $this->configs['sell_rebot_order_max'] = $this->configs['rebot_heng_order_max'];
                $this->configs['sell_rebot_order_min'] = $this->configs['rebot_heng_order_min'];
            }
            else if ($hengTrend == 2) {//2-下降
                $this->configs['buy_rebot_order_max'] = $this->configs['rebot_heng_order_max'];
                $this->configs['buy_rebot_order_min'] = $this->configs['rebot_heng_order_min'];
            }
        }
        else if ($operateType == 1) {//1-拉盘  买单机器人成交次数随机，卖单机器人成交次数=0
            if ($buyTradeNumMin == $buyTradeNumMax) {
                $buyTradeNum = $buyTradeNumMin;
            }
            else {
                $buyTradeNum = mt_rand(min($buyTradeNumMin, $buyTradeNumMax), max($buyTradeNumMin, $buyTradeNumMax));
            }
            //$sellTradeNum = 0;
            //2020-07-18，改为：买单机器人成交次数>=卖单机器人成交次数
            if ($buyTradeNum > 0) {
                $sellTradeNum = mt_rand($sellTradeNumMin, $buyTradeNum);
            }
            else {
                $sellTradeNum = mt_rand(min($sellTradeNumMin, $sellTradeNumMax), max($sellTradeNumMin, $sellTradeNumMax));
            }
            $rand = mt_rand(1, 10);
            if ($rand <= 3) {//拉盘中有30%概率出现砸盘
                $type = 2;
                list($buyTradeNum, $sellTradeNum) = [$sellTradeNum, $buyTradeNum];
            }
        }
        else if ($operateType == 2) {//2-砸盘  卖单机器人成交次数随机，买单机器人成交次数=0
            if ($sellTradeNumMin == $sellTradeNumMax) {
                $sellTradeNum = $sellTradeNumMin;
            }
            else {
                $sellTradeNum = mt_rand(min($sellTradeNumMin, $sellTradeNumMax), max($sellTradeNumMin, $sellTradeNumMax));
            }
            //$buyTradeNum = 0;
            //2020-07-18，改为：卖单机器人成交次数>=买单机器人成交次数
            if ($sellTradeNum > 0) {
                $buyTradeNum = mt_rand($buyTradeNumMin, $sellTradeNum);
            }
            else {
                $buyTradeNum = mt_rand(min($buyTradeNumMin, $buyTradeNumMax), max($buyTradeNumMin, $buyTradeNumMax));
            }
            $rand = mt_rand(1, 10);
            if ($rand <= 3) {//砸盘中有30%概率出现拉盘
                $type = 1;
                list($buyTradeNum, $sellTradeNum) = [$sellTradeNum, $buyTradeNum];
            }
        }
        $this->priceRate = $this->configs['rebot_price_rate'];//机器人挂单价格与成交价浮动比率(万分之‱)
        $lastTradePrice = \app\common\model\Trade::getLastTradePrice($this->currency['currency_id'],$this->currency_trade['currency_id']);
        $this->lastTradePrice = $lastTradePrice > 0 ? $lastTradePrice : $this->configs['rebot_initial_price'];
        $now = time();
        $res =  db('Trade')->field("SUM(IF(`type`='buy', num, 0)) as buy_num,SUM(IF(`type`='sell', num, 0)) as sell_num")->where([
            'currency_id' => $this->currency['currency_id'],
            'currency_trade_id' => $this->currency_trade['currency_id'],
            'add_time' => ['between', [$this->today_config['today_start'], $this->today_config['today_start'] + 86400]]
        ])->find();
        if($res) {
            $todayBuyNum = $res['buy_num'];
            $todaySellNum = $res['sell_num'];
            $todayTotal = $todayBuyNum + $todaySellNum;
        }
        else {
            $todayBuyNum = 0;
            $todaySellNum = 0;
            $todayTotal = $todayBuyNum + $todaySellNum;
        }
        $cancelAll = false;
        //撤销之前的机器人挂单
        $buyUser = null;
        $buyUserStatus = 0;//买单机器人状态 1-运行 0-停止
        if (!$buyUserId) {
            Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人用户id未设置");
        }
        else {
            $buyUser = Member::get($buyUserId);
            if (!$buyUser) {
                Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人用户不存在");
            }
            else {
                $buyUserStatus = 1;
                //撤销买单机器人的买单
                $buyPriceMin = keepPoint($this->lastTradePrice * (1 - $buyOrderNum * $this->priceRate / 10000), 6);
                $where = [
                    'member_id'=>$buyUserId,
                    'type'=>'buy',
                    'currency_id' => $this->currency['currency_id'],
                    'currency_trade_id' => $this->currency_trade['currency_id'],
                    'add_time'=>['lt', $now],
                    'price'=>['lt', $buyPriceMin],//只撤销价格小于最近成交价挂单最小值的买单
                    'status'=>['in', [0, 1]],
                ];
                $desc = '尾部撤销,';
                if ($cancelOrderType == 2) {//机器人撤单类型 1-尾部 2-全部
                    unset($where['price']);
                    $desc = '全部撤销,';
                }
                else {//尾部撤销时，每隔1个小时全部撤销一次
//                    $minute = date('i', $now);
//                    if ($minute == '00') {//每小时整点
//                        unset($where['price']);
//                        $desc = '每小时整点,全部撤销,';
//                    }
//                    else {
                        if ($this->configs['next_cancel_order_all']) {
                            $cancelAll = true;
                            unset($where['price']);
                            $desc = '机器人配置更新,全部撤销,';
                        }
//                    }
                }
                $orderSelect = db('Orders')->where($where)->select();
                if (count($orderSelect) > 0) {
                    Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,{$desc}撤销买单机器人之前的买单:".count($orderSelect));
                    $result = $this->cancelOrdersByOrders($orderSelect);
                    if ($result['code'] != SUCCESS) Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,{$desc}撤销买单机器人的买单,".$result['message']);
                }
            }
        }
        $sellUser = null;
        $sellUserStatus = 0;//卖单机器人状态 1-运行 0-停止
        if (!$sellUserId) {
            Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人用户id未设置");
        }
        else {
            $sellUser = Member::get($sellUserId);
            if (!$sellUser) {
                Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人用户不存在");
            }
            else {
                $sellUserStatus = 1;
                //撤销卖单机器人的卖单
                $sellPriceMax = keepPoint($this->lastTradePrice * (1 + $sellOrderNum * $this->priceRate / 10000), 6);
                $where = [
                    'member_id'=>$sellUserId,
                    'type'=>'sell',
                    'currency_id' => $this->currency['currency_id'],
                    'currency_trade_id' => $this->currency_trade['currency_id'],
                    'add_time'=>['lt', $now],
                    'price'=>['gt', $sellPriceMax],//只撤销价格大于于最近成交价挂单最大值的卖单
                    'status'=>['in', [0, 1]],
                ];
                $desc = '尾部撤销,';
                if ($cancelOrderType == 2) {//机器人撤单类型 1-尾部 2-全部
                    unset($where['price']);
                    $desc = '全部撤销,';
                }
                else {//尾部撤销时，每隔1个小时全部撤销一次
//                    $minute = date('i', $now);
//                    if ($minute == '00') {//每小时整点
//                        unset($where['price']);
//                        $desc = '每小时整点,全部撤销,';
//                    }
//                    else {
                        if ($this->configs['next_cancel_order_all']) {
                            $cancelAll = true;
                            unset($where['price']);
                            $desc = '机器人配置更新,全部撤销,';
                        }
//                    }
                }
                $orderSelect = db('Orders')->where($where)->select();
                if (count($orderSelect) > 0) {
                    Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,{$desc}撤销卖单机器人之前的卖单:".count($orderSelect));
                    $result = $this->cancelOrdersByOrders($orderSelect);
                    if ($result['code'] != SUCCESS) Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,{$desc}撤销卖单机器人的卖单,".$result['message']);
                }
            }
        }
        if ($cancelAll && $this->configs['next_cancel_order_all']) {
            $update = Db::name('OrdersRebotTrade')->where('id', $trade['id'])->setField('next_cancel_order_all', 0);
            if ($update === false) Log::write("挂单机器人,交易对:{$this->tradeName},更新机器人配置失败-in line:".__LINE__);
        }
        if ($tradeLimit > 0) {
            if ($todayTotal > $tradeLimit) {
                Log::write("挂单机器人,交易对:{$this->tradeName},机器人当日成交总额已达上限:{$tradeLimit},今日:{$todayTotal},买单总额:{$todayBuyNum},卖单总额:{$todaySellNum}");
                $buyUserStatus = 0;
                $sellUserStatus = 0;
            }
        }
        if ($buyTradeLimit > 0 && $buyUserStatus) {
            if ($todayBuyNum > $buyTradeLimit) {
                Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人当日成交总额已达上限:{$buyTradeLimit},今日:{$todayBuyNum}");
                $buyUserStatus = 0;
            }
        }
        if ($sellTradeLimit > 0 && $sellUserStatus) {
            if ($todaySellNum > $sellTradeLimit) {
                Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人当日成交总额已达上限:{$sellTradeLimit},今日:{$todaySellNum}");
                $sellUserStatus = 0;
            }
        }
        if ($this->configs['buy_rebot_switch'] != 1) {
            Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人开关未开启");
            $buyUserStatus = 0;
        }
        if ($this->configs['sell_rebot_switch'] != 1) {
            Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人开关未开启");
            $sellUserStatus = 0;
        }
        /*//机器人自动充值
        if ($this->configs['rebot_auto_recharge_switch'] == 1) {
            $autoRechargeCondition = $this->configs['rebot_auto_recharge_condition'];//机器人自动充值条件 余额<=X时自动充值
            $autoRechargeMoney = $this->configs['rebot_auto_recharge_money'];//机器人自动充值金额
            if ($buyUserStatus) {
                //获取账户信息
                $money = $this->getUserMoney($buyUserId, $this->currency_trade['currency_id'], 'num');
                if ($money <= $autoRechargeCondition) {
                    Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人".$this->currency_trade['currency_name']."余额:{$money},小于等于:{$autoRechargeCondition},自动充值,充值金额:{$autoRechargeMoney}-in line:".__LINE__);
                    $result = $this->admRecharge($buyUserId, $this->currency_trade['currency_id'], $autoRechargeMoney, "买单机器人:{$buyUserId}自动充值");
                    if ($result['code'] != SUCCESS) {
                        Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,自动充值,".$result['message']);
                        $buyUserStatus = 0;
                    }
                }
            }
            if ($sellUserStatus) {
                //获取账户信息
                $money = $this->getUserMoney($sellUserId, $this->currency['currency_id'], 'num');
                if ($money <= $autoRechargeCondition) {
                    Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人".$this->currency['currency_name']."余额:{$money},小于等于:{$autoRechargeCondition},自动充值,充值金额:{$autoRechargeMoney}-in line:".__LINE__);
                    $result = $this->admRecharge($sellUserId, $this->currency['currency_id'], $autoRechargeMoney, "卖单机器人:{$sellUserId}自动充值");
                    if ($result['code'] != SUCCESS) {
                        Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,自动充值,".$result['message']);
                        $sellUserStatus = 0;
                    }
                }
            }
        }*/
        //买单机器人自动充值
        if ($this->configs['buy_rebot_auto_recharge_switch'] == 1) {
            $autoRechargeCondition = $this->configs['buy_rebot_auto_recharge_condition'];//买单机器人自动充值条件 余额<=X时自动充值
            $autoRechargeMoney = $this->configs['buy_rebot_auto_recharge_money'];//买单机器人自动充值金额
            if ($buyUserStatus) {
                //获取账户信息
                $money = $this->getUserMoney($buyUserId, $this->currency_trade['currency_id'], 'num');
                if ($money <= $autoRechargeCondition) {
                    Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人".$this->currency_trade['currency_name']."余额:{$money},小于等于:{$autoRechargeCondition},自动充值,充值金额:{$autoRechargeMoney}-in line:".__LINE__);
                    $result = $this->admRecharge($buyUserId, $this->currency_trade['currency_id'], $autoRechargeMoney, "买单机器人:{$buyUserId}自动充值");
                    if ($result['code'] != SUCCESS) {
                        Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,自动充值,".$result['message']);
                        $buyUserStatus = 0;
                    }
                }
            }
        }
        //卖单机器人自动充值
        if ($this->configs['sell_rebot_auto_recharge_switch'] == 1) {
            $autoRechargeCondition = $this->configs['sell_rebot_auto_recharge_condition'];//卖单机器人自动充值条件 余额<=X时自动充值
            $autoRechargeMoney = $this->configs['sell_rebot_auto_recharge_money'];//卖单机器人自动充值金额
            if ($sellUserStatus) {
                //获取账户信息
                $money = $this->getUserMoney($sellUserId, $this->currency['currency_id'], 'num');
                if ($money <= $autoRechargeCondition) {
                    Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人".$this->currency['currency_name']."余额:{$money},小于等于:{$autoRechargeCondition},自动充值,充值金额:{$autoRechargeMoney}-in line:".__LINE__);
                    $result = $this->admRecharge($sellUserId, $this->currency['currency_id'], $autoRechargeMoney, "卖单机器人:{$sellUserId}自动充值");
                    if ($result['code'] != SUCCESS) {
                        Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,自动充值,".$result['message']);
                        $sellUserStatus = 0;
                    }
                }
            }
        }
        if ($buyUserStatus) {
            //获取账户信息
            $money = $this->getUserMoney($buyUserId, $this->currency_trade['currency_id'], 'num');
            if ($money <= 0) {
                Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人".$this->currency_trade['currency_name']."余额为0-in line:".__LINE__);
                $buyUserStatus = 0;
            }
        }
        if ($sellUserStatus) {
            //获取账户信息
            $money = $this->getUserMoney($sellUserId, $this->currency['currency_id'], 'num');
            if ($money <= 0) {
                Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人".$this->currency['currency_name']."余额为0-in line:".__LINE__);
                $sellUserStatus = 0;
            }
        }
        $typeList = ['0'=>'横盘','1'=>'拉盘','2'=>'砸盘',];
        $typeName = $type == 2 ? '先买单机器人成交' : '先卖单机器人成交';
        Log::write("挂单机器人,交易对:{$this->tradeName},operateType:{$typeList[$operateType]},优先级:{$typeName},buyUserId:{$buyUserId},sellUserId:{$sellUserId},priceRate:{$this->priceRate},lastTradePrice:{$this->lastTradePrice},buyTradeNum:{$buyTradeNum},sellTradeNum:{$sellTradeNum}");
        //挂单，买单卖单一起，挂完单之后再进行成交操作
        if ($buyUserStatus) {
            $result = $this->buy_rebot($buyUserId);
            if ($result['code'] != SUCCESS) Log::write("挂单机器人,交易对:{$this->tradeName},操作失败,".$result['message']);
        }
        if ($sellUserStatus) {
            $result = $this->sell_rebot($sellUserId);
            if ($result['code'] != SUCCESS) Log::write("卖单机器人,操作失败,".$result['message']);
        }
        //成交，按照优先级
        if ($type == 2) {//先买单机器人成交
            if ($buyUserStatus) {
                $result = $this->buy_trade_rebot($buyUserId, $buyTradeNum);
                if ($result['code'] != SUCCESS) Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,操作失败,".$result['message']);
            }
            if ($sellUserStatus) {
                $result = $this->sell_trade_rebot($sellUserId, $sellTradeNum);
                if ($result['code'] != SUCCESS) Log::write("卖单机器人,卖单机器人,操作失败,".$result['message']);
            }
        }
        else {//先卖单机器人成交
            if ($sellUserStatus) {
                $result = $this->sell_trade_rebot($sellUserId, $sellTradeNum);
                if ($result['code'] != SUCCESS) Log::write("卖单机器人,卖单机器人,操作失败,".$result['message']);
            }
            if ($buyUserStatus) {
                $result = $this->buy_trade_rebot($buyUserId, $buyTradeNum);
                if ($result['code'] != SUCCESS) Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,操作失败,".$result['message']);
            }
        }
    }

    /**
     * 火币主流币机器人
     * @param $trade
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function rebot_huobi($trade)
    {
        $this->init_configs($trade);
        var_dump($this->tradeName);
        var_dump(time());
        $time1 = microtime(true);
        if(empty($this->configs) || $this->configs['rebot_switch'] != 1) {
            Log::write("挂单机器人,交易对:{$this->tradeName},配置为空或者机器人开关未开启");
            return;
        }
        $operateType = $this->configs['rebot_operate_type'];//机器人操作类型 0-横盘 1-拉盘 2-砸盘
        $hengTrend = $this->configs['rebot_heng_trend'];//机器人横盘趋势 0-正常 1-上升 2-下降
        $buyUserId = $this->configs['buy_rebot_user_id'];//卖单机器人用户id 0-代表未设置
        $sellUserId = $this->configs['sell_rebot_user_id'];//卖单机器人用户id 0-代表未设置
        $buyOrderNum = $this->configs['buy_rebot_order_num'];//买单机器人挂单次数
        $sellOrderNum = $this->configs['sell_rebot_order_num'];//卖单机器人挂单次数
        $buyTradeNumMin = $this->configs['buy_rebot_trade_num_min'];//买单机器人成交次数最小值 用于生成随机数
        $buyTradeNumMax = $this->configs['buy_rebot_trade_num_max'];//买单机器人成交次数最大值 用于生成随机数
        $sellTradeNumMin = $this->configs['sell_rebot_trade_num_min'];//卖单机器人成交次数最小值 用于生成随机数
        $sellTradeNumMax = $this->configs['sell_rebot_trade_num_max'];//卖单机器人成交次数最大值 用于生成随机数
        $tradeLimit = $this->configs['rebot_trade_limit'];//机器人当日成交总额上限 0-不限制
        $buyTradeLimit = $this->configs['buy_rebot_trade_limit'];//买单机器人当日成交总额上限 0-不限制
        $sellTradeLimit = $this->configs['sell_rebot_trade_limit'];//卖单机器人当日成交总额上限 0-不限制
        $cancelOrderType = $this->configs['rebot_cancel_order_type'];//机器人撤单类型 1-尾部 2-全部
        $type = $operateType;//成交优先级 1-拉盘，卖单优先成交 2-砸盘，买单优先成交
        $buyTradeNum= 0;
        $sellTradeNum = 0;
        $this->priceRate = $this->configs['rebot_price_rate'];//机器人挂单价格与成交价浮动比率(万分之‱)
        //$lastTradePrice = \app\common\model\Trade::getLastTradePrice($this->currency['currency_id'],$this->currency_trade['currency_id']);
        //$this->lastTradePrice = $lastTradePrice > 0 ? $lastTradePrice : $this->configs['rebot_initial_price'];
        $price_time = strtotime(date('Y-m-d H:i:00')) - 60;
        $priceWhere = [
            'currency_id'=>$trade['currency_id'],
            'currency_trade_id'=>$trade['trade_currency_id'],
            'type'=>60,
            'status'=>0,
            'time_id'=>['egt', $price_time],
        ];
        //$priceFind = Db::name('kline_history')->where($priceWhere)->order('add_time', 'ASC')->find();
        $priceFind = Db::name('kline_history')->where($priceWhere)->order('add_time', 'DESC')->find();
        $priceId = 0;
        if ($priceFind) {
            var_dump(__LINE__);
            $flag = Db::name('kline_history')->where('id', $priceFind['id'])->update([
                'status'=>1,
                'deal_start'=>time(),
            ]);
            $priceId = $priceFind['id'];
            $closePrice = $priceFind['close'];
            $highPrice = $priceFind['high'];
            $lowPrice = $priceFind['low'];
            $this->sleep = $this->sleep && false;
        }
        else {
            Log::write("挂单机器人,交易对:{$this->tradeName},未获取到最新的火币价格");
            return;
            //$price_time = strtotime(date('Y-m-d H:i:00')) - 60;
            /*$priceWhere = [
                'currency_id'=>$trade['currency_id'],
                'currency_trade_id'=>$trade['trade_currency_id'],
                'type'=>60,
                //'add_time'=>$price_time,
            ];
            $priceFind = \app\common\model\Kline::where($priceWhere)->order('add_time', 'DESC')->find();
            if (!$priceFind) {
                $n = 10;
                while ($n > 0) {
                    sleep(1);
                    $priceFind = \app\common\model\Kline::where($priceWhere)->order('add_time', 'DESC')->find();
                    if (!$priceFind) {
                        $n++;continue;
                    }
                    else {
                        break;
                    }
                }
            }
            if (!$priceFind) {
                Log::write("挂单机器人,交易对:{$this->tradeName},未获取到最新的火币价格");
                return;
            }
            $closePrice = $priceFind['close_price'];
            $highPrice = $priceFind['hign_price'];
            $lowPrice = $priceFind['low_price'];*/
        }
        $time2 = microtime(true);
        echo "\r\n  获取价格消耗时间:".($time2 - $time1);
        $this->lastTradePrice = $closePrice > 0 ? $closePrice : $this->configs['rebot_initial_price'];
        $now = time();
        $res =  db('Trade')->field("SUM(IF(`type`='buy', num, 0)) as buy_num,SUM(IF(`type`='sell', num, 0)) as sell_num")->where([
            'currency_id' => $this->currency['currency_id'],
            'currency_trade_id' => $this->currency_trade['currency_id'],
            'add_time' => ['between', [$this->today_config['today_start'], $this->today_config['today_start'] + 86400]]
        ])->find();
        if($res) {
            $todayBuyNum = $res['buy_num'];
            $todaySellNum = $res['sell_num'];
            $todayTotal = $todayBuyNum + $todaySellNum;
        }
        else {
            $todayBuyNum = 0;
            $todaySellNum = 0;
            $todayTotal = $todayBuyNum + $todaySellNum;
        }
        $cancelAll = false;
        //撤销之前的机器人挂单
        $buyUser = null;
        $buyUserStatus = 0;//买单机器人状态 1-运行 0-停止
        $time3 = microtime(true);
        echo "\r\n  撤单前消耗时间:".($time3 - $time2);
        if (!$buyUserId) {
            Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人用户id未设置");
        }
        else {
            $buyUser = Member::get($buyUserId);
            if (!$buyUser) {
                Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人用户不存在");
            }
            else {
                $buyUserStatus = 1;
                //撤销买单机器人的买单
                $buyPriceMin = keepPoint($this->lastTradePrice * (1 - $buyOrderNum * $this->priceRate / 10000), 6);
                $where = [
                    'member_id'=>$buyUserId,
                    'type'=>'buy',
                    'currency_id' => $this->currency['currency_id'],
                    'currency_trade_id' => $this->currency_trade['currency_id'],
                    'add_time'=>['elt', $now],
                    //'price'=>['lt', $buyPriceMin],//只撤销价格小于最近成交价挂单最小值的买单
                    //'price'=>[['elt', $this->lastTradePrice], ['egt', $priceFind['low_price']]],//买单：K线最低价<=挂单价格<=K线实时价格
                    //'price'=>[[['elt', $priceFind['hign_price']], ['egt', $priceFind['low_price']],'and'],['egt', $priceFind['hign_price']],['lt', $buyPriceMin],'or'],
                    'price'=>[[['elt', $highPrice], ['egt', $lowPrice],'and'],['egt', $highPrice],['lt', $buyPriceMin],'or'],
                    'status'=>['in', [0, 1]],
                ];
                $desc = '尾部撤销,';
                if ($cancelOrderType == 2) {//机器人撤单类型 1-尾部 2-全部
                    unset($where['price']);
                    $desc = '全部撤销,';
                }
                else {//尾部撤销时，每隔1个小时全部撤销一次
//                    $minute = date('i', $now);
//                    if ($minute == '00') {//每小时整点
//                        unset($where['price']);
//                        $desc = '每小时整点,全部撤销,';
//                    }
//                    else {
                        if ($this->configs['next_cancel_order_all']) {
                            $cancelAll = true;
                            unset($where['price']);
                            $desc = '机器人配置更新,全部撤销,';
                        }
//                    }
                }
                $orderSelect = db('Orders')->where($where)->select();
                //var_dump(db('Orders')->getLastSql());
                if (count($orderSelect) > 0) {
                    echo "\r\n  买单撤单数量:".count($orderSelect);
                    Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,{$desc}撤销买单机器人之前的买单:".count($orderSelect));
                    $result = $this->cancelOrdersByOrders($orderSelect);
                    if ($result['code'] != SUCCESS) Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,{$desc}撤销买单机器人的买单,".$result['message']);
                }
            }
        }
        $time41 = microtime(true);
        echo "\r\n  买单撤单消耗时间:".($time41 - $time3);
        $sellUser = null;
        $sellUserStatus = 0;//卖单机器人状态 1-运行 0-停止
        if (!$sellUserId) {
            Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人用户id未设置");
        }
        else {
            $sellUser = Member::get($sellUserId);
            if (!$sellUser) {
                Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人用户不存在");
            }
            else {
                $sellUserStatus = 1;
                //撤销卖单机器人的卖单
                $sellPriceMax = keepPoint($this->lastTradePrice * (1 + $sellOrderNum * $this->priceRate / 10000), 6);
                $where = [
                    'member_id'=>$sellUserId,
                    'type'=>'sell',
                    'currency_id' => $this->currency['currency_id'],
                    'currency_trade_id' => $this->currency_trade['currency_id'],
                    'add_time'=>['elt', $now],
                    //'price'=>['gt', $sellPriceMax],//只撤销价格大于于最近成交价挂单最大值的卖单
                    //'price'=>[['egt', $this->lastTradePrice], ['elt', $priceFind['hign_price']]],//卖单：K线实时价格<=挂单价格<=K线最高价
                    //'price'=>[[['elt', $priceFind['hign_price']], ['egt', $priceFind['low_price']],'and'],['elt', $priceFind['low_price']],['gt', $sellPriceMax],'or'],
                    'price'=>[[['elt', $highPrice], ['egt', $lowPrice],'and'],['elt', $lowPrice],['gt', $sellPriceMax],'or'],
                    'status'=>['in', [0, 1]],
                ];
                $desc = '尾部撤销,';
                if ($cancelOrderType == 2) {//机器人撤单类型 1-尾部 2-全部
                    unset($where['price']);
                    $desc = '全部撤销,';
                }
                else {//尾部撤销时，每隔1个小时全部撤销一次
//                    $minute = date('i', $now);
//                    if ($minute == '00') {//每小时整点
//                        unset($where['price']);
//                        $desc = '每小时整点,全部撤销,';
//                    }
//                    else {
                        if ($this->configs['next_cancel_order_all']) {
                            $cancelAll = true;
                            unset($where['price']);
                            $desc = '机器人配置更新,全部撤销,';
                        }
//                    }
                }
                $orderSelect = db('Orders')->where($where)->select();
                //var_dump(db('Orders')->getLastSql());
                if (count($orderSelect) > 0) {
                    echo "\r\n  卖单撤单数量:".count($orderSelect);
                    Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,{$desc}撤销卖单机器人之前的卖单:".count($orderSelect));
                    $result = $this->cancelOrdersByOrders($orderSelect);
                    if ($result['code'] != SUCCESS) Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,{$desc}撤销卖单机器人的卖单,".$result['message']);
                }
            }
        }
        $time42 = microtime(true);
        echo "\r\n  卖单撤单消耗时间:".($time42 - $time41);
        if ($cancelAll && $this->configs['next_cancel_order_all']) {
            $update = Db::name('OrdersRebotTrade')->where('id', $trade['id'])->setField('next_cancel_order_all', 0);
            if ($update === false) Log::write("挂单机器人,交易对:{$this->tradeName},更新机器人配置失败-in line:".__LINE__);
        }
        $time4 = microtime(true);
        echo "\r\n  撤单消耗时间:".($time4 - $time3);
        //更新K线数据
        $flag = $this->updateKline($priceFind);
        if ($tradeLimit > 0) {
            if ($todayTotal > $tradeLimit) {
                Log::write("挂单机器人,交易对:{$this->tradeName},机器人当日成交总额已达上限:{$tradeLimit},今日:{$todayTotal},买单总额:{$todayBuyNum},卖单总额:{$todaySellNum}");
                $buyUserStatus = 0;
                $sellUserStatus = 0;
            }
        }
        if ($buyTradeLimit > 0 && $buyUserStatus) {
            if ($todayBuyNum > $buyTradeLimit) {
                Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人当日成交总额已达上限:{$buyTradeLimit},今日:{$todayBuyNum}");
                $buyUserStatus = 0;
            }
        }
        if ($sellTradeLimit > 0 && $sellUserStatus) {
            if ($todaySellNum > $sellTradeLimit) {
                Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人当日成交总额已达上限:{$sellTradeLimit},今日:{$todaySellNum}");
                $sellUserStatus = 0;
            }
        }
        if ($this->configs['buy_rebot_switch'] != 1) {
            Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人开关未开启");
            $buyUserStatus = 0;
        }
        if ($this->configs['sell_rebot_switch'] != 1) {
            Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人开关未开启");
            $sellUserStatus = 0;
        }
        /*//机器人自动充值
        if ($this->configs['rebot_auto_recharge_switch'] == 1) {
            $autoRechargeCondition = $this->configs['rebot_auto_recharge_condition'];//机器人自动充值条件 余额<=X时自动充值
            $autoRechargeMoney = $this->configs['rebot_auto_recharge_money'];//机器人自动充值金额
            if ($buyUserStatus) {
                //获取账户信息
                $money = $this->getUserMoney($buyUserId, $this->currency_trade['currency_id'], 'num');
                if ($money <= $autoRechargeCondition) {
                    Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人".$this->currency_trade['currency_name']."余额:{$money},小于等于:{$autoRechargeCondition},自动充值,充值金额:{$autoRechargeMoney}-in line:".__LINE__);
                    $result = $this->admRecharge($buyUserId, $this->currency_trade['currency_id'], $autoRechargeMoney, "买单机器人:{$buyUserId}自动充值");
                    if ($result['code'] != SUCCESS) {
                        Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,自动充值,".$result['message']);
                        $buyUserStatus = 0;
                    }
                }
            }
            if ($sellUserStatus) {
                //获取账户信息
                $money = $this->getUserMoney($sellUserId, $this->currency['currency_id'], 'num');
                if ($money <= $autoRechargeCondition) {
                    Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人".$this->currency['currency_name']."余额:{$money},小于等于:{$autoRechargeCondition},自动充值,充值金额:{$autoRechargeMoney}-in line:".__LINE__);
                    $result = $this->admRecharge($sellUserId, $this->currency['currency_id'], $autoRechargeMoney, "卖单机器人:{$sellUserId}自动充值");
                    if ($result['code'] != SUCCESS) {
                        Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,自动充值,".$result['message']);
                        $sellUserStatus = 0;
                    }
                }
            }
        }*/
        //买单机器人自动充值
        if ($this->configs['buy_rebot_auto_recharge_switch'] == 1) {
            $autoRechargeCondition = $this->configs['buy_rebot_auto_recharge_condition'];//买单机器人自动充值条件 余额<=X时自动充值
            $autoRechargeMoney = $this->configs['buy_rebot_auto_recharge_money'];//买单机器人自动充值金额
            if ($buyUserStatus) {
                //获取账户信息
                $money = $this->getUserMoney($buyUserId, $this->currency_trade['currency_id'], 'num');
                if ($money <= $autoRechargeCondition) {
                    Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人".$this->currency_trade['currency_name']."余额:{$money},小于等于:{$autoRechargeCondition},自动充值,充值金额:{$autoRechargeMoney}-in line:".__LINE__);
                    $result = $this->admRecharge($buyUserId, $this->currency_trade['currency_id'], $autoRechargeMoney, "买单机器人:{$buyUserId}自动充值");
                    if ($result['code'] != SUCCESS) {
                        Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,自动充值,".$result['message']);
                        $buyUserStatus = 0;
                    }
                }
            }
        }
        //卖单机器人自动充值
        if ($this->configs['sell_rebot_auto_recharge_switch'] == 1) {
            $autoRechargeCondition = $this->configs['sell_rebot_auto_recharge_condition'];//卖单机器人自动充值条件 余额<=X时自动充值
            $autoRechargeMoney = $this->configs['sell_rebot_auto_recharge_money'];//卖单机器人自动充值金额
            if ($sellUserStatus) {
                //获取账户信息
                $money = $this->getUserMoney($sellUserId, $this->currency['currency_id'], 'num');
                if ($money <= $autoRechargeCondition) {
                    Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人".$this->currency['currency_name']."余额:{$money},小于等于:{$autoRechargeCondition},自动充值,充值金额:{$autoRechargeMoney}-in line:".__LINE__);
                    $result = $this->admRecharge($sellUserId, $this->currency['currency_id'], $autoRechargeMoney, "卖单机器人:{$sellUserId}自动充值");
                    if ($result['code'] != SUCCESS) {
                        Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,自动充值,".$result['message']);
                        $sellUserStatus = 0;
                    }
                }
            }
        }
        if ($buyUserStatus) {
            //获取账户信息
            $money = $this->getUserMoney($buyUserId, $this->currency_trade['currency_id'], 'num');
            if ($money <= 0) {
                Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人".$this->currency_trade['currency_name']."余额为0-in line:".__LINE__);
                $buyUserStatus = 0;
            }
        }
        if ($sellUserStatus) {
            //获取账户信息
            $money = $this->getUserMoney($sellUserId, $this->currency['currency_id'], 'num');
            if ($money <= 0) {
                Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人".$this->currency['currency_name']."余额为0-in line:".__LINE__);
                $sellUserStatus = 0;
            }
        }
        $time5 = microtime(true);
        echo "\r\n  自动充值消耗时间:".($time5 - $time4);
        //$typeList = ['0'=>'横盘','1'=>'拉盘','2'=>'砸盘',];
        //$typeName = $type == 2 ? '先买单机器人成交' : '先卖单机器人成交';
        //Log::write("挂单机器人,交易对:{$this->tradeName},operateType:{$typeList[$operateType]},优先级:{$typeName},buyUserId:{$buyUserId},sellUserId:{$sellUserId},priceRate:{$this->priceRate},lastTradePrice:{$this->lastTradePrice},buyTradeNum:{$buyTradeNum},sellTradeNum:{$sellTradeNum}");
        //Log::write("挂单机器人,交易对:{$this->tradeName},buyUserId:{$buyUserId},sellUserId:{$sellUserId},priceRate:{$this->priceRate},lastTradePrice:{$this->lastTradePrice},hign_price:{$priceFind['hign_price']},low_price:{$priceFind['low_price']}");
        Log::write("挂单机器人,交易对:{$this->tradeName},buyUserId:{$buyUserId},sellUserId:{$sellUserId},priceRate:{$this->priceRate},lastTradePrice:{$this->lastTradePrice},hign_price:{$highPrice},low_price:{$lowPrice}");
        //成交，查找真实玩家符合成交条件的挂单
        //买单
        if ($buyUserStatus) {
            //$result = $this->buy_trade_rebot_huobi($buyUserId, $priceFind['hign_price']);
            $result = $this->buy_trade_rebot_huobi($buyUserId, $highPrice);
            if ($result['code'] != SUCCESS) Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,操作失败,".$result['message']);
        }
        $time6 = microtime(true);
        echo "\r\n  买单成交消耗时间:".($time6 - $time5);
        //卖单
        if ($sellUserStatus) {
            //$result = $this->sell_trade_rebot_huobi($sellUserId, $priceFind['low_price']);
            $result = $this->sell_trade_rebot_huobi($sellUserId, $lowPrice);
            if ($result['code'] != SUCCESS) Log::write("卖单机器人,卖单机器人,操作失败,".$result['message']);
        }
        $time7 = microtime(true);
        echo "\r\n  卖单成交消耗时间:".($time7 - $time6);
        //挂单，买单卖单一起，挂完单之后再进行成交操作
        if ($buyUserStatus) {
            $result = $this->buy_rebot($buyUserId);
            if ($result['code'] != SUCCESS) Log::write("挂单机器人,交易对:{$this->tradeName},操作失败,".$result['message']);
        }
        $time8 = microtime(true);
        echo "\r\n  挂买单消耗时间:".($time8 - $time7);
        if ($sellUserStatus) {
            $result = $this->sell_rebot($sellUserId);
            if ($result['code'] != SUCCESS) Log::write("卖单机器人,操作失败,".$result['message']);
        }
        $time9 = microtime(true);
        echo "\r\n  挂卖单消耗时间:".($time9 - $time8);
        if ($priceId) {
            $flag = Db::name('kline_history')->where('id', $priceFind['id'])->update([
                'deal_end'=>time(),
            ]);
        }
        echo "\r\n  总共消耗时间:".($time9 - $time1);
    }

    protected function buy_rebot($user_id)
    {
        $buyOrderNumMin = $this->configs['buy_rebot_order_min'];//买单机器人挂单数量最小值 用于生成随机数
        $buyOrderNumMax = $this->configs['buy_rebot_order_max'];//买单机器人挂单数量最大值 用于生成随机数
        $buyOrderNumScale = $this->configs['buy_rebot_order_scale'];//买单机器人挂单精度 小数点位数
        $buyOrderNum = $this->configs['buy_rebot_order_num'];//买单机器人挂单次数
        $currency_id = $this->currency['currency_id'];
        $currency_trade_id = $this->currency_trade['currency_id'];
        $where = [
            'member_id'=>$user_id,
            'type'=>'buy',
            'currency_id' => $this->currency['currency_id'],
            'currency_trade_id' => $this->currency_trade['currency_id'],
            'status'=>['in', [0, 1]],
        ];
        $buyOrderCount = db('Orders')->where($where)->count();
        $buyPriceMax = db('Orders')->where($where)->max('price');
        $buyPriceMin = db('Orders')->where($where)->min('price');
        $basePrice = $this->lastTradePrice;
        $num = $buyOrderNum - $buyOrderCount;
        if ($buyPriceMax) {
            //$a = abs($basePrice - $buyPriceMax) / $basePrice;
            //$b = $this->priceRate / 10000;
            //$c = $a / $b;
            $diff = round(abs($basePrice - $buyPriceMax) / $basePrice / ($this->priceRate / 10000));
            //$diff = min($diff, $num);
            //Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,a:{$a},b:{$b},c:{$c},diff:{$diff}");
            $num1 = 0;
            if ($diff > 1) $num1 = $diff - 1;
            $num1 = min($num1, $num);
            $num2 = $num - $num1;
        }
        else {
            $num1 = $num;
            $num2 = 0;
        }
        Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,向下挂单次数:{$num},补全:{$num1},末尾追加:{$num2},预定挂单次数:{$buyOrderNum},当前挂单数量:{$buyOrderCount},basePrice:{$basePrice},buyPriceMax:{$buyPriceMax},buyPriceMin:{$buyPriceMin}");
        //向下挂单
        for ($i = 0; $i < $num1; $i++) {
            Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,向下挂单,补全");
            if ($buyOrderNumScale > 0) {
                $buyNum = keepPoint(mt_rand($buyOrderNumMin * pow(10, $buyOrderNumScale), $buyOrderNumMax * pow(10, $buyOrderNumScale)) / pow(10, $buyOrderNumScale), $buyOrderNumScale);
            }
            else {
                $buyNum = mt_rand($buyOrderNumMin, $buyOrderNumMax);
            }
            $buyPrice = keepPoint($basePrice * (1 - ($i + 1) * $this->priceRate / 10000), 6);
            $result = $this->buy($user_id, $buyNum, $buyPrice);
            if ($result['code'] != SUCCESS) {
                Log::write("买单机器人挂单失败,".$result['message']);
                return $result;
            }
        }
        for ($i = 0; $i < $num2; $i++) {
            Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,向下挂单,末尾追加");
            if ($buyOrderNumScale > 0) {
                $buyNum = keepPoint(mt_rand($buyOrderNumMin * pow(10, $buyOrderNumScale), $buyOrderNumMax * pow(10, $buyOrderNumScale)) / pow(10, $buyOrderNumScale), $buyOrderNumScale);
            }
            else {
                $buyNum = mt_rand($buyOrderNumMin, $buyOrderNumMax);
            }
            $buyPrice = keepPoint($buyPriceMin * (1 - ($i + 1) * $this->priceRate / 10000), 6);
            $result = $this->buy($user_id, $buyNum, $buyPrice);
            if ($result['code'] != SUCCESS) {
                Log::write("买单机器人挂单失败,".$result['message']);
                return $result;
            }
        }
        $r['code'] = SUCCESS;
        $r['message'] = '成功';
        return $r;
    }

    protected function buy_trade_rebot($user_id, $trade_num)
    {
        $buyOrderNumMin = $this->configs['buy_rebot_order_min'];//买单机器人挂单数量最小值 用于生成随机数
        $buyOrderNumMax = $this->configs['buy_rebot_order_max'];//买单机器人挂单数量最大值 用于生成随机数
        $currency_id = $this->currency['currency_id'];
        $currency_trade_id = $this->currency_trade['currency_id'];
        Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,向上挂单促成成交次数:{$trade_num}");
        if ($trade_num > 0) {
            //向上以最新卖1的价格挂买单促成成交
            for ($i = 0; $i < $trade_num; $i++) {
                //获取对应交易的一个订单
                //$trade_order=$this->getOneOrders('sell', $currency_id, $this->lastTradePrice, $currency_trade_id);
                $trade_order=$this->getOneOrders('sell', $currency_id, 0, $currency_trade_id);
                //如果没有相匹配的订单，直接返回
                if (empty($trade_order) ) {
                    $r['code'] = ERROR1;
                    $r['message'] = '买单机器人向上挂单促成成交失败,没有相匹配的订单:';
                    return $r;
                    //Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,向上挂单促成成交,没有相匹配的订单,用最新成交价向上挂单");
                    //$buyPrice = keepPoint($this->lastTradePrice * (1 + ($i + 1) * $this->priceRate / 10000 / 10), 6);
                }
                else {
                    $buyPrice = $trade_order['price'];
                }

                $buyNum = mt_rand($buyOrderNumMin, $buyOrderNumMax);
                //$buyNum = min($buyNum, $trade_order['num'] - $trade_order['trade_num']);
                $result = $this->buy($user_id, $buyNum, $buyPrice, 2);
                if ($result['code'] != SUCCESS) {
                    Log::write("买单机器人向上挂单促成成交失败,".$result['message']);
                    return $result;
                }
                sleep(1);
                $orders_id = $result['result'];
                $order = db('Orders')->where('orders_id', $orders_id)->find();
                if (!in_array($order['status'], [1, 2])) {
                    sleep(1);
                }
            }
        }
        $r['code'] = SUCCESS;
        $r['message'] = '成功';
        return $r;
    }

    protected function buy_trade_rebot_huobi($user_id, $high_price)
    {
        $currency_id = $this->currency['currency_id'];
        $currency_trade_id = $this->currency_trade['currency_id'];
        Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,挂单促成与真实用户挂单成交");
        $now = time();
        $where = [
            'type'=>'sell',
            'currency_id' => $this->currency['currency_id'],
            'currency_trade_id' => $this->currency_trade['currency_id'],
            'member_id' => ['neq', $user_id],
            'add_time'=>['lt', $now],
            //'price'=>['lt', $buyPriceMin],//只撤销价格小于最近成交价挂单最小值的买单
            'price'=>[['egt', $this->lastTradePrice], ['elt', $high_price]],//卖单：K线实时价格<=挂单价格<=K线最高价
            'status'=>['in', [0, 1]],
        ];
        $orders = db('Orders')->where($where)->select();
        $trade_num = count($orders);
        if ($trade_num > 0) {
            Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,挂单促成与真实用户挂单成交,次数:{$trade_num}");
            //Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,挂单促成与真实用户挂单成交,sql:".db('Orders')->getLastSql());
            foreach ($orders as $key => $trade_order) {
                $buyPrice = $trade_order['price'];
                $buyNum = floatval(bcsub($trade_order['num'], $trade_order['trade_num'], 6));

                $result = $this->buy($user_id, $buyNum, $buyPrice, 2);
                if ($result['code'] != SUCCESS) {
                    Log::write("买单机器人挂单促成与真实用户挂单成交失败,".$result['message']);
                    return $result;
                }
                sleep(1);
                $orders_id = $result['result'];
                $order = db('Orders')->where('orders_id', $orders_id)->find();
                if (!in_array($order['status'], [1, 2])) {
                    sleep(1);
                }
            }
        }
        $r['code'] = SUCCESS;
        $r['message'] = '成功';
        return $r;
    }

    protected function buy($user_id, $buyNum, $buyPrice, $type = 1)
    {
        $des = $type == 1 ? '向下挂单,' : '向上挂单促成成交,';
        try {
            Db::startTrans();

            $time1 = microtime(true);
            if ($this->checkUserMoney($user_id, $buyNum, $buyPrice, 'buy')) {
                throw new Exception($this->currency_trade['currency_name'].'余额不足-in line:'.__LINE__);
            }

            //计算买入需要的金额
            //$trade_money = round($buyNum * $buyPrice * (1 + ($this->currency['currency_buy_fee'] / 100)), 6);
            //$trade_money = round($buyNum * $buyPrice, 6);
            $trade_money = keepPoint($buyNum * $buyPrice, 6);

            //挂单流程
            $flag = $orders_id = $this->guadan($user_id, $buyNum, $buyPrice, 'buy');
            if ($flag === false) throw new Exception('挂单失败-in line:'.__LINE__);

            $flag = model("AccountBook")->addLog([
                'member_id'=>$user_id,
                'currency_id'=> $this->currency_trade['currency_id'],
                'number_type'=>2,
                'number'=>$trade_money,
                'type'=>11,
                'content'=>"lan_trade",
                //'fee'=> round($buyNum * $buyPrice * ($this->currency['currency_buy_fee'] / 100),6),
                'fee'=> 0,
                'to_member_id'=>0,
                'to_currency_id'=>0,
                'third_id'=> $orders_id,
            ]);
            if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);

            //操作账户
            $flag = $this->setUserMoney($user_id, $this->currency_trade['currency_id'], $trade_money, 'dec', 'num');
            if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
            $flag = $this->setUserMoney($user_id, $this->currency_trade['currency_id'], $trade_money, 'inc', 'forzen_num');
            if ($flag === false) throw new Exception('更新用户资产-冻结失败-in line:'.__LINE__);

            $time2 = microtime(true);
            $cost = $time2 - $time1;
            Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,{$des}挂单:{$orders_id},buyNum:{$buyNum},buyPrice:{$buyPrice},cost:{$cost}");

            //交易流程
            /*$flag = $this->trade($user_id, $orders_id, $this->currency['currency_id'], 'buy', $buyNum, $buyPrice,$this->currency_trade['currency_id']);
            if ($flag === false) throw new Exception('交易流程失败-in line:'.__LINE__);*/

            Db::commit();
            $r['result'] = $orders_id;
        }
        catch (Exception $e) {
            Db::rollback();

            $r['code'] = ERROR1;
            $r['message'] = '异常信息:'.$e->getMessage();
            return $r;
        }
        $r['code'] = SUCCESS;
        $r['message'] = '成功';
        return $r;
    }

    protected function sell_rebot($user_id)
    {
        $sellOrderNumMin = $this->configs['sell_rebot_order_min'];//卖单机器人挂单数量最小值 用于生成随机数
        $sellOrderNumMax = $this->configs['sell_rebot_order_max'];//卖单机器人挂单数量最大值 用于生成随机数
        $sellOrderNumScale = $this->configs['sell_rebot_order_scale'];//卖单机器人挂单精度 小数点位数
        $sellOrderNum = $this->configs['sell_rebot_order_num'];//卖单机器人挂单次数
        $currency_id = $this->currency['currency_id'];
        $currency_trade_id = $this->currency_trade['currency_id'];
        $where = [
            'member_id'=>$user_id,
            'type'=>'sell',
            'currency_id' => $this->currency['currency_id'],
            'currency_trade_id' => $this->currency_trade['currency_id'],
            'status'=>['in', [0, 1]],
        ];
        $sellOrderCount = db('Orders')->where($where)->count();
        $sellPriceMax = db('Orders')->where($where)->max('price');
        $sellPriceMin = db('Orders')->where($where)->min('price');
        $basePrice = $this->lastTradePrice;
        $num = $sellOrderNum - $sellOrderCount;
        if ($sellPriceMin) {
            //$a = abs($basePrice - $sellPriceMin) / $basePrice;
            //$b = $this->priceRate / 10000;
            //$c = $a / $b;
            $diff = round(abs($basePrice - $sellPriceMin) / $basePrice / ($this->priceRate / 10000));
            //$diff = min($diff, $num);
            //Log::write("挂单机器人,交易对:{$this->tradeName},买单机器人,a:{$a},b:{$b},c:{$c},diff:{$diff}");
            $num1 = 0;
            if ($diff > 1) $num1 = $diff - 1;
            $num1 = min($num1, $num);
            $num2 = $num - $num1;
        }
        else {
            $num1 = $num;
            $num2 = 0;
        }
        Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,向上挂单次数:{$num},补全:{$num1},末尾追加:{$num2},预定挂单次数:{$sellOrderNum},当前挂单数量:{$sellOrderCount},basePrice:{$basePrice},sellPriceMax:{$sellPriceMax},sellPriceMin:{$sellPriceMin}");
        //向上挂单
        for ($i = 0; $i < $num1; $i++) {
            Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,向上挂单,补全");
            if ($sellOrderNumScale > 0) {
                $sellNum = keepPoint(mt_rand($sellOrderNumMin * pow(10, $sellOrderNumScale), $sellOrderNumMax * pow(10, $sellOrderNumScale)) / pow(10, $sellOrderNumScale), $sellOrderNumScale);
            }
            else {
                $sellNum = mt_rand($sellOrderNumMin, $sellOrderNumMax);
            }
            $sellPrice = keepPoint($basePrice * (1 + ($i + 1) * $this->priceRate / 10000), 6);
            $result = $this->sell($user_id, $sellNum, $sellPrice);
            if ($result['code'] != SUCCESS) {
                Log::write("卖单机器人挂单失败,".$result['message']);
                return $result;
            }
        }
        for ($i = 0; $i < $num2; $i++) {
            Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,向上挂单,末尾追加");
            if ($sellOrderNumScale > 0) {
                $sellNum = keepPoint(mt_rand($sellOrderNumMin * pow(10, $sellOrderNumScale), $sellOrderNumMax * pow(10, $sellOrderNumScale)) / pow(10, $sellOrderNumScale), $sellOrderNumScale);
            }
            else {
                $sellNum = mt_rand($sellOrderNumMin, $sellOrderNumMax);
            }
            $sellPrice = keepPoint($sellPriceMax * (1 + ($i + 1) * $this->priceRate / 10000), 6);
            $result = $this->sell($user_id, $sellNum, $sellPrice);
            if ($result['code'] != SUCCESS) {
                Log::write("卖单机器人挂单失败,".$result['message']);
                return $result;
            }
        }
        $r['code'] = SUCCESS;
        $r['message'] = '成功';
        return $r;
    }

    protected function sell_trade_rebot($user_id, $trade_num)
    {
        $sellOrderNumMin = $this->configs['sell_rebot_order_min'];//卖单机器人挂单数量最小值 用于生成随机数
        $sellOrderNumMax = $this->configs['sell_rebot_order_max'];//卖单机器人挂单数量最大值 用于生成随机数
        $currency_id = $this->currency['currency_id'];
        $currency_trade_id = $this->currency_trade['currency_id'];
        Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,向下挂单促成成交次数:{$trade_num}");
        if ($trade_num > 0) {
            //向下以最新买1的价格挂卖单促成成交
            for ($i = 0; $i < $trade_num; $i++) {
                //获取对应交易的一个订单
                //$trade_order=$this->getOneOrders('buy', $currency_id, $this->lastTradePrice, $currency_trade_id);
                $trade_order=$this->getOneOrders('buy', $currency_id, 0, $currency_trade_id);
                //如果没有相匹配的订单，直接返回
                if (empty($trade_order) ) {
                    $r['code'] = ERROR1;
                    $r['message'] = '卖单机器人向下挂单促成成交失败,没有相匹配的订单:';
                    return $r;
                    //Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,向下挂单促成成交,没有相匹配的订单,用最新成交价向下挂单");
                    //$sellPrice = keepPoint($this->lastTradePrice * (1 - ($i + 1) * $this->priceRate / 10000 / 10), 6);
                }
                else {
                    $sellPrice = $trade_order['price'];
                }

                $sellNum = mt_rand($sellOrderNumMin, $sellOrderNumMax);
                //$sellNum = min($sellNum, $trade_order['num'] - $trade_order['trade_num']);
                $result = $this->sell($user_id, $sellNum, $sellPrice, 2);
                if ($result['code'] != SUCCESS) {
                    Log::write("卖单机器人向下挂单促成成交失败,".$result['message']);
                    return $result;
                }
                sleep(1);
                $orders_id = $result['result'];
                $order = db('Orders')->where('orders_id', $orders_id)->find();
                if (!in_array($order['status'], [1, 2])) {
                    sleep(1);
                }
            }
        }
        $r['code'] = SUCCESS;
        $r['message'] = '成功';
        return $r;
    }

    protected function sell_trade_rebot_huobi($user_id, $low_price)
    {
        $currency_id = $this->currency['currency_id'];
        $currency_trade_id = $this->currency_trade['currency_id'];
        Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,挂单促成与真实用户挂单成交");
        $now = time();
        $where = [
            'type'=>'buy',
            'currency_id' => $this->currency['currency_id'],
            'currency_trade_id' => $this->currency_trade['currency_id'],
            'member_id' => ['neq', $user_id],
            'add_time'=>['lt', $now],
            //'price'=>['lt', $buyPriceMin],//只撤销价格小于最近成交价挂单最小值的买单
            'price'=>[['elt', $this->lastTradePrice], ['egt', $low_price]],//买单：K线最低价<=挂单价格<=K线实时价格
            'status'=>['in', [0, 1]],
        ];
        $orders = db('Orders')->where($where)->select();
        $trade_num = count($orders);
        if ($trade_num > 0) {
            Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,挂单促成与真实用户挂单成交,次数:{$trade_num}");
            //Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,挂单促成与真实用户挂单成交,sql:".db('Orders')->getLastSql());
            foreach ($orders as $key => $trade_order) {
                $sellPrice = $trade_order['price'];
                $sellNum = floatval(bcsub($trade_order['num'], $trade_order['trade_num'], 6));

                $result = $this->sell($user_id, $sellNum, $sellPrice, 2);
                if ($result['code'] != SUCCESS) {
                    Log::write("卖单机器人向下挂单促成成交失败,".$result['message']);
                    return $result;
                }
                sleep(1);
                $orders_id = $result['result'];
                $order = db('Orders')->where('orders_id', $orders_id)->find();
                if (!in_array($order['status'], [1, 2])) {
                    sleep(1);
                }
            }
        }
        $r['code'] = SUCCESS;
        $r['message'] = '成功';
        return $r;
    }

    protected function sell($user_id, $sellNum, $sellPrice, $type = 1)
    {
        $des = $type == 1 ? '向上挂单' : '向下挂单促成成交,';
        try {
            Db::startTrans();

            $time1 = microtime(true);
            if ($this->checkUserMoney($user_id, $sellNum, $sellPrice, 'sell')) {
                throw new Exception($this->currency['currency_name'].'余额不足-in line:'.__LINE__);
            }

            //挂单流程
            $flag = $orders_id = $this->guadan($user_id, $sellNum, $sellPrice, 'sell');
            if ($flag === false) throw new Exception('挂单失败-in line:'.__LINE__);

            $flag = model('AccountBook')->addLog([
                'member_id' => $user_id,
                'currency_id' => $this->currency['currency_id'],
                'type'=> 11,
                'content' => 'lan_trade',
                'number_type' => 2,
                'number' => $sellNum,
                'fee' => 0,
                'to_member_id' => 0,
                'to_currency_id' => 0,
                'third_id' => $orders_id,
            ]);
            if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);

            //操作账户
            $flag = $this->setUserMoney($user_id, $this->currency['currency_id'], $sellNum, 'dec', 'num');
            if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
            $flag = $this->setUserMoney($user_id, $this->currency['currency_id'], $sellNum, 'inc', 'forzen_num');
            if ($flag === false) throw new Exception('更新用户资产-冻结失败-in line:'.__LINE__);

            $time2 = microtime(true);
            $cost = $time2 - $time1;
            Log::write("挂单机器人,交易对:{$this->tradeName},卖单机器人,{$des}挂单:{$orders_id},sellNum:{$sellNum},sellPrice:{$sellPrice},cost:{$cost}");

            //交易流程
            /*$flag = $this->trade($user_id, $orders_id, $this->currency['currency_id'], 'sell', $sellNum, $sellPrice,$this->currency_trade['currency_id']);
            if ($flag === false) throw new Exception('交易流程失败-in line:'.__LINE__);*/

            Db::commit();
            $r['result'] = $orders_id;
        }
        catch (Exception $e) {
            Db::rollback();

            $r['code'] = ERROR1;
            $r['message'] = '异常信息:'.$e->getMessage();
            return $r;
        }
        $r['code'] = SUCCESS;
        $r['message'] = '成功';
        return $r;
    }

    /**
     * 撤销订单
     * @param array $orders 订单列表
     * @return mixed
     */
    protected function cancelOrdersByOrders($orders)
    {
        try {
            Db::startTrans();

            foreach ($orders as $one_order) {
                //更新订单状态
                $flag = db('Orders')->where('orders_id',$one_order['orders_id'])->setField('status', -1);
                if ($flag === false) throw new Exception('更新订单状态失败-in line:'.__LINE__);
                //返还资金
                switch ($one_order['type']) {
                    case 'buy':
                        //$money = ($one_order['num'] - $one_order['trade_num']) * $one_order['price'] * (1 + $one_order['fee']);
                        $money = keepPoint(($one_order['num'] - $one_order['trade_num']) * $one_order['price'] * (1 + $one_order['fee']), 6);
                        if ($money > 0) {
                            $flag =model("AccountBook")->addLog([
                                'member_id'=>$one_order['member_id'],
                                'currency_id'=>$one_order['currency_trade_id'],
                                'number_type'=>1,
                                'number'=>$money,
                                'type'=>17,
                                'content'=>"lan_Return_funds",
                                //'fee'=> ($one_order['num'] - $one_order['trade_num']) * $one_order['price'] * $one_order['fee'],
                                'fee'=> keepPoint(($one_order['num'] - $one_order['trade_num']) * $one_order['price'] * $one_order['fee'], 6),
                                'to_member_id'=>0,
                                'to_currency_id'=>$one_order['currency_id'],
                                'third_id'=>$one_order['orders_id'],
                            ]);
                            if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                            $flag = $this->setUserMoney($one_order['member_id'], $one_order['currency_trade_id'], $money, 'inc', 'num');
                            if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
                            $flag = $this->setUserMoney($one_order['member_id'], $one_order['currency_trade_id'], $money, 'dec', 'forzen_num');
                            if ($flag === false) throw new Exception('更新用户资产-冻结失败-in line:'.__LINE__);
                        }
                        break;
                    case 'sell':
                        //$num = $one_order['num'] - $one_order['trade_num'];
                        $num = keepPoint($one_order['num'] - $one_order['trade_num'], 6);
                        if ($num > 0) {
                            $flag =model("AccountBook")->addLog([
                                'member_id'=>$one_order['member_id'],
                                'currency_id'=>$one_order['currency_id'],
                                'number_type'=>1,
                                'number'=>$num,
                                'type'=>17,
                                'content'=>"lan_Return_funds",
                                'fee'=>0,
                                'to_member_id'=>0,
                                'to_currency_id'=>$one_order['currency_trade_id'],
                                'third_id'=>$one_order['orders_id'],
                            ]);
                            if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);

                            $flag = $this->setUserMoney($one_order['member_id'], $one_order['currency_id'], $num, 'inc', 'num');
                            if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
                            $flag = $this->setUserMoney($one_order['member_id'], $one_order['currency_id'], $num, 'dec', 'forzen_num');
                            if ($flag === false) throw new Exception('更新用户资产-冻结失败-in line:'.__LINE__);
                        }
                        break;
                }
            }

            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = '撤销成功';
            return $r;
        }
        catch (Exception $e) {
            Db::rollback();

            $r['code'] = ERROR1;
            $r['message'] = '撤销失败,异常信息:'.$e->getMessage();
            return $r;
        }
    }

    /**
     *
     * @param int $user_id 用户id
     * @param int $num 数量
     * @param float $price 价格
     * @param char $type 买buy 卖sell
     * @param $currency_id 交易积分类型
     */
    private function checkUserMoney($user_id, $num, $price, $type)
    {

        //获取交易积分类型信息
        if ($type == 'buy') {
            //$trade_money = $num * $price;
            $trade_money = keepPoint($num * $price, 6);
            $currency_id = $this->currency_trade['currency_id'];
        } else {
            $trade_money = $num;
            $currency_id = $this->currency['currency_id'];
        }
        //和自己的账户做对比 获取账户信息
        $money = $this->getUserMoney($user_id, $currency_id, 'num');
        if ($money < $trade_money) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *  获取当前登陆账号指定积分类型的金额
     * @param int $user_id 用户ID
     * @param int $currency_id 积分类型ID
     * @param char $field num  forzen_num
     * @return array 当前登陆人账号信息
     */
    protected function getUserMoney($user_id, $currency_id, $field)
    {
        if (!isset($currency_id) || $currency_id == 0) {
            switch ($field) {
                case 'num':
                    $field = 'rmb';
                    break;
                case 'forzen_num':
                    $field = 'forzen_rmb';
                    break;
                default:
                    $field = 'rmb';
            }

            $num = db('member')->where(array('member_id' => $user_id))->value($field);
        } else {
            $num = db('Currency_user')->where(array('member_id' => $user_id, 'currency_id' => $currency_id))->value($field);
        }

        return number_format($num, 4, '.', '');
    }

    /**
     * 设置账户资金
     * @param int $currency_id 积分类型ID
     * @param int $num 交易数量
     * @param char $inc_dec setDec setInc 是加钱还是减去
     * @param char forzen_num num
     */
    protected function setUserMoney($member_id, $currency_id, $num, $inc_dec, $field)
    {
        $inc_dec = strtolower($inc_dec);
        $field = strtolower($field);
        //允许传入的字段
        if (!in_array($field, array('num', 'forzen_num'))) {
            return false;
        }
        //如果是RMB
        if ($currency_id == 0) {
            //修正字段
            switch ($field) {
                case 'forzen_num':
                    $field = 'forzen_rmb';
                    break;
                case 'num':
                    $field = 'rmb';
                    break;
            }
            switch ($inc_dec) {
                case 'inc':
                    $msg = db('Member')->where(array('member_id' => $member_id))->setInc($field, $num);
                    break;
                case 'dec':
                    $msg = db('Member')->where(array('member_id' => $member_id))->setDec($field, $num);
                    break;
                default:
                    return false;
            }
            return $msg;
        } else {
            switch ($inc_dec) {
                case 'inc':
                    $msg = db('Currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setInc($field, $num);
                    break;
                case 'dec':
                    $msg = db('Currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setDec($field, $num);
                    break;
                default:
                    return false;
            }
            return $msg;
        }
    }

    /**
     * 挂单
     * @param int $user_id 用户id
     * @param int $num 数量
     * @param float $price 价格
     * @param char $type 买buy 卖sell
     */
    private function guadan($user_id, $num, $price, $type)
    {
        //throw new Exception('抛出异常测试-in line:'.__LINE__);
        //获取交易积分类型信息
        switch ($type) {
            case 'buy':
                //$fee = $currency['currency_buy_fee'] / 100;
                $fee = 0;
                break;
            case 'sell':
                //$fee = $currency['currency_sell_fee'] / 100;
                $fee = 0;
                break;
        }

        $data = array(
            'member_id' => $user_id,
            'currency_id' => $this->currency['currency_id'],
            'currency_trade_id' => $this->currency_trade['currency_id'],
            'price' => $price,
            'num' => $num,
            'trade_num' => 0,
            'fee' => $fee,
            'add_time' => time(),
            'type' => $type,
        );
        $msg = db('Orders')->insertGetId($data);
        if (empty($msg)) {
            $msg = 0;
        }

        return $msg;

    }

    private function trade($user_id, $orders_id, $currencyId, $type, $num, $price,$trade_currency_id)
    {
        if ($type == 'buy') {
            $trade_type = 'sell';
            $rebotName = '买单机器人';
        } else {
            $trade_type = 'buy';
            $rebotName = '卖单机器人';
        }
        $memberId = $user_id;
        //获取操作人一个订单
        //$order=$this->getFirstOrdersByMember($memberId,$type ,$currencyId,$trade_currency_id);
        $order = db('Orders')->where('orders_id', $orders_id)->find();
        //获取对应交易的一个订单
        $trade_order=$this->getOneOrders($trade_type, $currencyId,$price,$trade_currency_id);
        //如果没有相匹配的订单，直接返回
        if (empty($trade_order) ) {
            return true;
        }

        //如果有就处理订单
        $trade_num = min($num, $trade_order['num'] - $trade_order['trade_num']);

        //增加本订单的已经交易的数量
        $flag = db('Orders')->where("orders_id",$order['orders_id'])->update([
            'trade_num'=>['inc', $trade_num],'trade_time'=>time(),
        ]);
        if ($flag === false) throw new Exception('更新本订单的已经交易的数量失败-in line:'.__LINE__);

        //增加trade订单的已经交易的数量
        $flag = db('Orders')->where("orders_id",$trade_order['orders_id'])->update([
            'trade_num'=>['inc', $trade_num],'trade_time'=>time(),
        ]);
        if ($flag === false) throw new Exception('更新trade订单的已经交易的数量失败-in line:'.__LINE__);

        //更新一下订单状态
        $flag = db('Orders')->where("trade_num>0 and status=0")->setField('status', 1);
        if ($flag === false) throw new Exception('更新订单状态失败-in line:'.__LINE__);
        $flag = db('Orders')->where("num=trade_num")->setField('status', 2);
        if ($flag === false) throw new Exception('更新订单状态失败-in line:'.__LINE__);

        //处理资金
        $trade_price = 0;
        switch ($type) {
            case 'buy':
                $order_money = sprintf('%.6f', $trade_num * $order['price'] * (1 + $order['fee']));
                $trade_order_money = $trade_num * $trade_order['price'] * (1 - $trade_order['fee']);
                $trade_price = min($order['price'], $trade_order['price']);
                //$trade_price=$order['price'];
                $flag = model("AccountBook")->addLog([
                    'member_id'=>$memberId,
                    'currency_id'=>$order['currency_id'],
                    'number_type'=>1,
                    'number'=>$trade_num,
                    'type'=>11,
                    'content'=>"lan_Increased_availability_of_currency_transactions",
                    'fee'=>0,
                    'to_member_id'=>$trade_order['member_id'],
                    'to_currency_id'=>$order['currency_trade_id'],
                    'third_id'=>$order['orders_id'],
                ]);
                if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                $flag = $this->setUserMoney($memberId, $order['currency_id'], $trade_num, 'inc', 'num');
                if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

                $flag = $this->setUserMoney($memberId, $order['currency_trade_id'], $order_money, 'dec', 'forzen_num');
                if ($flag === false) throw new Exception('更新用户资产-冻结失败-in line:'.__LINE__);

                $flag = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_id'], $trade_num, 'dec', 'forzen_num');
                if ($flag === false) throw new Exception('更新用户资产-冻结失败-in line:'.__LINE__);

                $flag = model("AccountBook")->addLog([
                    'member_id'=>$trade_order['member_id'],
                    'currency_id'=>$trade_order['currency_trade_id'],
                    'number_type'=>1,
                    'number'=>$trade_order_money,
                    'type'=>11,
                    'content'=>"lan_Increased_availability_of_currency_transactions",
                    'fee'=>$trade_num * $trade_order['price'] * ($trade_order['fee']),
                    'to_member_id'=>$order['member_id'],
                    'to_currency_id'=>$trade_order['currency_id'],
                    'third_id'=>$trade_order['orders_id'],
                ]);
                if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                $flag = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_trade_id'], $trade_order_money, 'inc', 'num');
                if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

                $back_price = $order['price'] - $trade_price;
                if ($back_price > 0) {
                    if ($order['fee'] > 0) {
                        //返还未成交部分手续费
                        $flag = model("AccountBook")->addLog([
                            'member_id'=>$memberId,
                            'currency_id'=>$order['currency_trade_id'],
                            'number_type'=>1,
                            'number'=> $trade_num * $back_price * $order['fee'],
                            'type'=>11,
                            'content'=>"lan_Return_charges",
                            'fee'=>0,
                            'to_member_id'=>$trade_order['member_id'],
                            'to_currency_id'=>$order['currency_id'],
                            'third_id'=>$order['orders_id'],
                        ]);
                        if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                        //$r[] = $this->setUserMoney($memberId, $order['currency_trade_id'], $trade_num * $back_price * $order['fee'], 'inc', 'num');
                        $flag = $this->setUserMoney($memberId, $order['currency_trade_id'], $trade_num * $back_price * $order['fee'], 'inc', 'num');
                        if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
                    }

                    //返还多扣除的挂单金额
                    $flag = model("AccountBook")->addLog([
                        'member_id'=>$memberId,
                        'currency_id'=>$order['currency_trade_id'],
                        'number_type'=>1,
                        'number'=> $trade_num * $back_price,
                        'type'=>11,
                        'content'=>"Return_the_amount_of_overdeducted_bill_of_lading",
                        'fee'=>0,
                        'to_member_id'=>$trade_order['member_id'],
                        'to_currency_id'=>$order['currency_id'],
                        'third_id'=>$order['orders_id'],
                    ]);
                    if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                    //$r[] = $this->setUserMoney($memberId, $order['currency_trade_id'], $trade_num * $back_price, 'inc', 'num');
                    $flag = $this->setUserMoney($memberId, $order['currency_trade_id'], $trade_num * $back_price, 'inc', 'num');
                    if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
                }
                break;
            case 'sell':
                $order_money = $trade_num * $order['price'] * (1 - $order['fee']);
                $trade_order_money = sprintf('%.6f', $trade_num * $trade_order['price'] * (1 + $trade_order['fee']));
                $trade_price = max($order['price'], $trade_order['price']);

                //$r[] = $this->setUserMoney($memberId, $order['currency_id'], $trade_num, 'dec', 'forzen_num');
                $flag = $this->setUserMoney($memberId, $order['currency_id'], $trade_num, 'dec', 'forzen_num');
                if ($flag === false) throw new Exception('更新用户资产-冻结失败-in line:'.__LINE__);

                $flag = model("AccountBook")->addLog([
                    'member_id'=>$memberId,
                    'currency_id'=>$order['currency_trade_id'],
                    'number_type'=>1,
                    'number'=> $order_money,
                    'type'=>11,
                    'content'=>"lan_Increased_availability_of_currency_transactions",
                    'fee'=> $trade_num * $order['price'] * ($order['fee']),
                    'to_member_id'=>$trade_order['member_id'],
                    'to_currency_id'=>$order['currency_id'],
                    'third_id'=>$order['orders_id'],
                ]);
                if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                //$r[] = $this->setUserMoney($memberId, $order['currency_trade_id'], $order_money, 'inc', 'num');
                $flag = $this->setUserMoney($memberId, $order['currency_trade_id'], $order_money, 'inc', 'num');
                if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

                $flag = model("AccountBook")->addLog([
                    'member_id'=>$trade_order['member_id'],
                    'currency_id'=>$trade_order['currency_id'],
                    'number_type'=>1,
                    'number'=> $trade_num,
                    'type'=>11,
                    'content'=>"lan_Increased_availability_of_currency_transactions",
                    'fee'=> 0,
                    'to_member_id'=>$memberId,
                    'to_currency_id'=>$trade_order['currency_trade_id'],
                    'third_id'=>$trade_order['orders_id'],
                ]);
                if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                //$r[] = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_id'], $trade_num, 'inc', 'num');
                $flag = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_id'], $trade_num, 'inc', 'num');
                if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

                //$r[] = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_trade_id'], $trade_order_money, 'dec', 'forzen_num');
                $flag = $this->setUserMoney($trade_order['member_id'], $trade_order['currency_trade_id'], $trade_order_money, 'dec', 'forzen_num');
                if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
                $back_price=$trade_price-$order['price'];

                if ($back_price > 0) {

                    if ($order['fee'] > 0) {
                        //返还未成交部分手续费
                        $flag = model("AccountBook")->addLog([
                            'member_id'=>$memberId,
                            'currency_id'=>$order['currency_trade_id'],
                            'number_type'=>1,
                            'number'=> $trade_num * $back_price * $order['fee'],
                            'type'=>11,
                            'content'=>"lan_Return_charges",
                            'fee'=>0,
                            'to_member_id'=>$trade_order['member_id'],
                            'to_currency_id'=>$order['currency_id'],
                            'third_id'=>$order['orders_id'],
                        ]);
                        if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                        //$r[] = $this->setUserMoney($memberId, $order['currency_trade_id'], $trade_num * $back_price * $order['fee'], 'inc', 'num');
                        $flag = $this->setUserMoney($memberId, $order['currency_trade_id'], $trade_num * $back_price * $order['fee'], 'inc', 'num');
                        if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
                    }

                    //返还多扣除的挂单金额
                    $flag = model("AccountBook")->addLog([
                        'member_id'=>$memberId,
                        'currency_id'=>$order['currency_trade_id'],
                        'number_type'=>1,
                        'number'=> $trade_num * $back_price,
                        'type'=>11,
                        'content'=>"Return_the_amount_of_overdeducted_bill_of_lading",
                        'fee'=>0,
                        'to_member_id'=>$trade_order['member_id'],
                        'to_currency_id'=>$order['currency_id'],
                        'third_id'=>$order['orders_id'],
                    ]);
                    if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);
                    //$r[] = $this->setUserMoney($memberId, $order['currency_trade_id'], $trade_num * $back_price, 'inc', 'num');
                    $flag = $this->setUserMoney($memberId, $order['currency_trade_id'], $trade_num * $back_price, 'inc', 'num');
                    if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
                }
                break;
        }

        //写入成交表
        //$r[] = $trade_id = $this->addTrade($order['member_id'], $order['currency_id'], $order['currency_trade_id'], $trade_price, $trade_num, $order['type'], $order['fee'],$trade_order['orders_id'],$trade_order['member_id']);
        $flag = $trade_id = $this->addTrade($order['member_id'], $order['currency_id'], $order['currency_trade_id'], $trade_price, $trade_num, $order['type'], $order['fee'],$trade_order['orders_id'],$trade_order['member_id']);
        if ($flag === false) throw new Exception('新增交易记录失败-in line:'.__LINE__);
        //$r[] = $trade_id2 = $this->addTrade($trade_order['member_id'], $trade_order['currency_id'], $trade_order['currency_trade_id'], $trade_price, $trade_num, $trade_order['type'], $trade_order['fee'],$order['orders_id'],$order['member_id']);
        $flag = $trade_id2 = $this->addTrade($trade_order['member_id'], $trade_order['currency_id'], $trade_order['currency_trade_id'], $trade_price, $trade_num, $trade_order['type'], $trade_order['fee'],$order['orders_id'],$order['member_id']);
        if ($flag === false) throw new Exception('新增交易记录失败-in line:'.__LINE__);

        //手续费
        $time = time();
        $order_fee = ($trade_num * $trade_price) * $order['fee'];
        $trade_order_fee = ($trade_num * $trade_price) * $trade_order['fee'];

        if ($order_fee > 0) {

            //$r[] = $this->addFinance($order['member_id'], 11, lang('lan_trade_exchange_charge'), $order_fee, 2, $order['currency_id'],$trade_id);
            $flag = $this->addFinance($order['member_id'], 11, lang('lan_trade_exchange_charge'), $order_fee, 2, $order['currency_id'],$trade_id);
            if ($flag === false) throw new Exception('新增财务日志失败-in line:'.__LINE__);
            //写入手续费表
            $add = [
                'member_id' => $order['member_id'],
                'fee' => $order_fee,
                'currency_id' => $order['currency_id'],
                'currency_trade_id' => $order['currency_trade_id'],
                'type' => $order['type'],
                'add_time' => $time
            ];
            //$r[] = db('mining_fee')->insert($add);
            $flag = db('mining_fee')->insert($add);
            if ($flag === false) throw new Exception('新增挖矿分红手续费记录失败-in line:'.__LINE__);

        }

        if ($trade_order_fee > 0) {
            //$r[] = $this->addFinance($trade_order['member_id'], 11, lang('lan_trade_exchange_charge'), $trade_order_fee, 2, $trade_order['currency_id'],$trade_id2);
            $flag = $this->addFinance($trade_order['member_id'], 11, lang('lan_trade_exchange_charge'), $trade_order_fee, 2, $trade_order['currency_id'],$trade_id2);
            if ($flag === false) throw new Exception('新增财务日志失败-in line:'.__LINE__);
            //写入手续费表
            $add2 = [
                'member_id' => $trade_order['member_id'],
                'fee' => $trade_order_fee,
                'currency_id' => $trade_order['currency_id'],
                'currency_trade_id' => $trade_order['currency_trade_id'],
                'type' => $trade_order['type'],
                'add_time' => $time
            ];
            //$r[] = db('mining_fee')->insertGetId($add2);
            $flag = db('mining_fee')->insertGetId($add2);
            if ($flag === false) throw new Exception('新增挖矿分红手续费记录失败-in line:'.__LINE__);
        }
        Log::write("挂单机器人,交易对:{$this->tradeName},{$rebotName},交易,订单:{$order['orders_id']},num:{$num},交易订单:{$trade_order['orders_id']},trade_num:{$trade_num}");

        $num = $num - $trade_num;
        if ($num > 0) {
            //递归
            return $this->trade($memberId, $orders_id, $currencyId, $type, $num, $price,$trade_currency_id);
        }
        return true;

    }

    /**
     * 返回用户第一条未成交的挂单
     * @param int $memberId 用户id
     * @param int $currencyId 积分类型id
     * @return array 挂单记录
     */
    private function getFirstOrdersByMember($memberId,$type,$currencyId,$trade_currency_id){
        $where['member_id']=$memberId;
        $where['currency_id']=$currencyId;
        $where['currency_trade_id']=$trade_currency_id;
        $where['type']=$type;
        $where['status']=array('in',array(0,1));
        return db('Orders')->where($where)->order('add_time desc,orders_id desc')->find();
    }

    /**
     * 返回一条挂单记录
     * @param int $currencyId 积分类型id
     * @param float $price 交易价格
     * @return array 挂单记录
     */
    private function getOneOrders($type,$currencyId,$price,$trade_currency_id){
        switch ($type){
            case 'buy':$gl='egt';$order='price desc'; break;
            case 'sell':$gl='elt'; $order='price asc';break;
        }
        $where['currency_id']=$currencyId;
        $where['currency_trade_id']=$trade_currency_id;
        $where['type']=$type;
        if ($price > 0) $where['price']=array($gl,$price);
        $where['status']=array('in',array(0,1));
        return db('Orders')->where($where)->order($order.',add_time asc')->find();
    }

    /**
     * 增加交易记录
     * @param unknown $member_id
     * @param unknown $currency_id
     * @param unknown $currency_trade_id
     * @param unknown $price
     * @param unknown $num
     * @param unknown $type
     * @return boolean
     */
    private function addTrade($member_id, $currency_id, $currency_trade_id, $price, $num, $type, $fee,$orders_id,$other_member_id=0)
    {
        $fee = $price * $num * $fee;
        $data = array(
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'currency_trade_id' => $currency_trade_id,
            'other_member_id' => $other_member_id,
            'price' => $price,
            'num' => $num,
            'fee' => $fee,
            'money' => $price * $num,
            'type' => $type,
            'orders_id' => $orders_id,
            'add_time' => time(),
            'trade_no' => 'T'.time()
        );
        if ($res = db('Trade')->insertGetId($data)) {
            return $res;
        } else {
            return false;
        }
    }

    /**
     * 添加财务日志方法
     * @param unknown $member_id
     * @param unknown $type
     * @param unknown $content
     * @param unknown $money
     * @param unknown $money_type 收入=1/支出=2
     * @param unknown $currency_id 积分类型id 0是rmb
     * @return
     */
    public function addFinance($member_id, $type, $content, $money, $money_type, $currency_id, $trade_id=0)
    {
        $data = [
            'member_id' => $member_id,
            'trade_id' => $trade_id,
            'type' => $type,
            'content' => $content,
            'money_type' => $money_type,
            'money' => $money,
            'add_time' => time(),
            'currency_id' => $currency_id,
            'ip' => get_client_ip_extend(),
        ];

        $list = Db::name('finance')->insertGetId($data);
        if ($list) {
            return $list;
        } else {
            return false;
        }
    }

    /**
     * 管理员充值
     */
    public function admRecharge($user_id, $currency_id, $money, $message)
    {
        try {
            Db::startTrans();

            $data['message'] = $message;
            $data['admin_id'] = 0;
            $data['member_id'] = $user_id;
            $data['currency_id'] = $currency_id;
            $data['money'] = $money;
            $data['status'] = 1;
            $data['add_time'] = time();
            $data['type'] = 3;//管理员充值类型
            $flag = $pay_id = Db::name('pay')->insertGetId($data);
            if ($flag === false) throw new Exception('添加充值记录失败-in line:'.__LINE__);

            //添加账本信息
            $flag =model('AccountBook')->addLog([
                'member_id' => $user_id,
                'currency_id' => $currency_id,
                'type' => 13,
                'content' => 'lan_admin_recharge',
                'number_type' => 1,
                'number' => $money,
                'add_time' => time(),
                'third_id' => $pay_id,
            ]);
            if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);

            $info = Db::name('currency_user')->lock(true)->where(['member_id' => $user_id, 'currency_id' => $currency_id])->find();
            if ($info) {
                $flag = $this->setUserMoney($user_id, $currency_id, $money, 'inc', 'num');
                if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);
            } else {
                $flag = Db::name('Currency_user')->insertGetId([
                    'member_id' => $user_id,
                    'currency_id' => $currency_id,
                    'num' => $money,
                ]);
                if ($flag === false) throw new Exception('新增用户资产-可用失败-in line:'.__LINE__);
            }

            $flag = $this->addFinance($user_id, 3, "管理员充值", $money, 1, $currency_id);
            if ($flag === false) throw new Exception('新增财务日志失败-in line:'.__LINE__);
            $flag = $this->addMessage_all($user_id, -2, "管理员充值", "管理员充值" . getCurrencynameByCurrency($currency_id) . ":" . $money);
            if ($flag === false) throw new Exception('新增消息库失败-in line:'.__LINE__);

            //获取账户信息
            $orzen = $this->getUserMoney($user_id, $currency_id, 'forzen_num');
            if ($orzen < 10000) {//为了防止冻结不够扣的情况，一定条件下补充机器人一定的冻结金额
                $flag = $this->setUserMoney($user_id, $currency_id, 10000, 'inc', 'forzen_num');
                if ($flag === false) throw new Exception('更新用户资产-冻结失败-in line:'.__LINE__);
            }

            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = '成功';
            return $r;
        }
        catch (Exception $e) {
            Db::rollback();

            $r['code'] = ERROR1;
            $r['message'] = '失败,异常信息:'.$e->getMessage();
            return $r;
        }
    }

    /**
     *  /**
     * 添加消息库
     * @param int $member_id 用户ID -1 为群发
     * @param int $type 分类  4=系统  -1=文章表系统公告 -2 个人信息
     * @param String $title 标题
     * @param String $content 内容
     * @return bool|mixed  成功返回增加Id 否则 false
     */
    public function addMessage_all($member_id, $type, $title, $content)
    {
        $data['u_id'] = $member_id;
        $data['type'] = $type;
        $data['title'] = $title;
        $data['content'] = $content;
        $data['add_time'] = time();
        $id = Db::name('Message_all')->insertGetId($data);
        if ($id) {
            return $id;
        } else {
            return false;
        }
    }

    /**
     * 更新K线数据
     * @param $price
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function updateKline($price)
    {
        $where = [
            'currency_id'=>$price['currency_id'],
            'currency_trade_id'=>$price['currency_trade_id'],
            'type'=>$price['type'],
            'add_time'=>$price['time_id'],
        ];
        $find1 = \app\common\model\Kline::where($where)->order('id', 'desc')->find();
        if ($find1) {//记录已存在，更新已有记录
            if ($find1['open_price'] != $price['open'] ||
                $find1['close_price'] != $price['close'] ||
                $find1['hign_price'] != $price['high'] ||
                $find1['low_price'] != $price['low'] ||
                $find1['amount'] != $price['amount'] ||
                $find1['count'] != $price['count'] ||
                $find1['vol'] != $price['vol']) {//没有数据变化不做更新
                $update_list = [];
                $update_list[] = [
                    'id'=>$find1['id'],
                    'open_price'=>number_format($price['open'],6,".",""),
                    'close_price'=>number_format($price['close'],6,".",""),
                    'hign_price'=>number_format($price['high'],6,".",""),
                    'low_price'=>number_format($price['low'],6,".",""),
                    'num'=>number_format($price['amount'],6,".",""),
                    'amount'=>number_format($price['amount'],6,".",""),
                    'count'=>number_format($price['count'],6,".",""),
                    'vol'=>number_format($price['vol'],6,".",""),
                    'update_time'=>time(),
                ];
                $kline = new \app\common\model\Kline;
                $res2 = $kline->isUpdate()->saveAll($update_list);
                if (empty($res2)) {
                    return false;
                    var_dump(lang('更新记录失败-2').'-in line:'.__LINE__);
                    //throw new Exception(lang('更新记录失败-2').'-in line:'.__LINE__);
                }
            }
        }
        else {
            $add_list = [];
            $add_list[] = [
                'type'=>$price['type'],
                'currency_id'=>$price['currency_id'],
                'currency_trade_id'=>$price['currency_trade_id'],
                'open_price'=>number_format($price['open'],6,".",""),
                'close_price'=>number_format($price['close'],6,".",""),
                'hign_price'=>number_format($price['high'],6,".",""),
                'low_price'=>number_format($price['low'],6,".",""),
                'num'=>number_format($price['amount'],6,".",""),
                'amount'=>number_format($price['amount'],6,".",""),
                'count'=>number_format($price['count'],6,".",""),
                'vol'=>number_format($price['vol'],6,".",""),
                'add_time'=>$price['time_id'],
                'update_time'=>time(),
            ];
            $kline = new \app\common\model\Kline;
            $res1 = $kline->saveAll($add_list);
            if (empty($res1)) {
                return false;
                var_dump(lang('插入记录失败').'-in line:'.__LINE__);
                //throw new Exception(lang('插入记录失败').'-in line:'.__LINE__);
            }
        }
        foreach ($this->time_list as $key => $value) {
            $type = $value;
            if ($type == $price['type']) continue;
            //$time = floor($price['time_id'] - $price['time_id'] % $type);
            //if ($type >= 86400) $time = $time - 8 * 3600;
            $priceWhere = [
                'currency_id'=>$price['currency_id'],
                'currency_trade_id'=>$price['currency_trade_id'],
                'type'=>$type,
                'status'=>0,
                //'time_id'=>$time,
                'add_time'=>['between', [$price['add_time']-1, $price['add_time']+1]]
            ];
            $price1 = Db::name('kline_history')->where($priceWhere)->order('add_time', 'DESC')->find();
            $where = [
                'currency_id'=>$price['currency_id'],
                'currency_trade_id'=>$price['currency_trade_id'],
                'type'=>$type,
                'add_time'=>$price1['time_id'],
            ];
            $find2 = \app\common\model\Kline::where($where)->order('id', 'desc')->find();
            if ($find2) {//记录已存在，更新已有记录
                if ($find2['open_price'] != $price1['open'] ||
                    $find2['close_price'] != $price1['close'] ||
                    $find2['hign_price'] != $price1['high'] ||
                    $find2['low_price'] != $price1['low'] ||
                    $find2['amount'] != $price1['amount'] ||
                    $find2['count'] != $price1['count'] ||
                    $find2['vol'] != $price1['vol']) {//没有数据变化不做更新
                    $update_list = [];
                    $update_list[] = [
                        'id'=>$find2['id'],
                        'open_price'=>number_format($price1['open'],6,".",""),
                        'close_price'=>number_format($price1['close'],6,".",""),
                        'hign_price'=>number_format($price1['high'],6,".",""),
                        'low_price'=>number_format($price1['low'],6,".",""),
                        'num'=>number_format($price1['amount'],6,".",""),
                        'amount'=>number_format($price1['amount'],6,".",""),
                        'count'=>number_format($price1['count'],6,".",""),
                        'vol'=>number_format($price1['vol'],6,".",""),
                        'update_time'=>time(),
                    ];
                    $kline = new \app\common\model\Kline;
                    $res2 = $kline->isUpdate()->saveAll($update_list);
                    if (empty($res2)) {
                        return false;
                        var_dump(lang('更新记录失败-2').'-in line:'.__LINE__);
                        //throw new Exception(lang('更新记录失败-2').'-in line:'.__LINE__);
                    }
                }
            }
            else {
                $add_list =[];
                $add_list[] = [
                    'type'=>$type,
                    'currency_id'=>$price['currency_id'],
                    'currency_trade_id'=>$price['currency_trade_id'],
                    'open_price'=>number_format($price1['open'],6,".",""),
                    'close_price'=>number_format($price1['close'],6,".",""),
                    'hign_price'=>number_format($price1['high'],6,".",""),
                    'low_price'=>number_format($price1['low'],6,".",""),
                    'num'=>number_format($price1['amount'],6,".",""),
                    'amount'=>number_format($price1['amount'],6,".",""),
                    'count'=>number_format($price1['count'],6,".",""),
                    'vol'=>number_format($price1['vol'],6,".",""),
                    'add_time'=>$price1['time_id'],
                    'update_time'=>time(),
                ];
                $kline = new \app\common\model\Kline;
                $res1 = $kline->saveAll($add_list);
                if (empty($res1)) {
                    return false;
                    var_dump(lang('插入记录失败').'-in line:'.__LINE__);
                    //throw new Exception(lang('插入记录失败').'-in line:'.__LINE__);
                }
            }
        }

        return true;
    }
}
