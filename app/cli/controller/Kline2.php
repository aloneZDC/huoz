<?php
namespace app\cli\controller;
use app\common\model\Currency;
use think\Log;
use think\Db;
use think\Exception;

use think\console\Input;
use think\console\Output;
use think\console\Command;

/**
 *K线图刷单
 */
class Kline2 extends Command
{
    private $time_list = [
        1=>60, //1分钟
        2=>300,//5分钟
        3=>900,//15分钟
        4=>1800,//30分钟
        5=>3600,//1小时
        6=>86400,//1天
        7=>604800,//1周
        8=>2592000, //1月
    ];

    private $time_list_huobi = [
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

    private $kline_id = 2;

    protected function configure()
    {
        $this->setName('Kline'.$this->kline_id)->setDescription('This is a Kline '.$this->kline_id);
    }

    protected function execute(Input $input, Output $output){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);
        \think\Request::instance()->module('cli');

//        $this->every_minute_trade_history('2020-07-15','2020-08-05');

//        $this->history();
//        while (true) {
            //$this->every_minute();
            $this->every_minute_huobi();

            //$this->every_minute_trade('');
//        }
    }

    //每分钟跑一次 生成随机数据
    protected function every_minute() {
        $step = 60;
        $auto_trade_list = Db::name('currency_autotrade')->where(['is_autotrade'=>1,'kline_huobi'=>0,'kline_trade'=>0,'kline_id'=>$this->kline_id])->select();
        if(empty($auto_trade_list)) return;

        $start_day = strtotime(date('Y-m-d H:i'));
        //1分钟--1周处理 跑上一分钟的
        foreach ($this->time_list as $key=>$time) {
            if($key<8 && $start_day%$time==0) {
                $start_time = $start_day - $time;
                $stop_time = $start_day - 1;
                echo date('Y-m-d H:i:s',$start_time).' : '.date('Y-m-d H:i:s',$stop_time)."\r\n";
                if($key==1) {
                    $this->rule_one($time,$start_time,$stop_time,$auto_trade_list);
                } else {
                    $this->rule_many($time,$start_time,$stop_time,$auto_trade_list);
                }
            }
        }
        $start_month_time = strtotime(date('Y-m')); //本月
        if($start_day==$start_month_time) {
            $start_month_pre = strtotime('-1 months',$start_month_time); //上个月开始
            $start_time = $start_month_pre;
            $stop_time = $start_month_time - 1;
            $this->rule_many($this->time_list[8],$start_time,$stop_time,$auto_trade_list);
        }


        //提前跑 1小时 1天 1月的
        $pre_list = [5,6,8];
        foreach ($pre_list as $pre) {
            if($pre==5) {
                $start_month_time =  strtotime(date('Y-m-d H')); //开始
            } elseif ($pre==6) {
                $start_month_time =  strtotime(date('Y-m-d')); //开始
            } elseif ($pre==8) {
                $start_month_time =  strtotime(date('Y-m')); //开始
            }
            $start_time = $start_month_time;
            $stop_time = $start_month_time + $this->time_list[$pre] - 1;
            $this->rule_many($this->time_list[$pre],$start_time,$stop_time,$auto_trade_list,true);
        }
    }

    //跑历史数据 生成随机数据
    protected function history() {
        $start_day = '2019-07-31';
        $stop_day = '2019-09-31';

        $start_day = strtotime($start_day);
        $stop_day = strtotime($stop_day);
        $step = 60;

        $last_trade = Db::name('kline')->where(['add_time'=>['lt',$stop_day]])->order('add_time desc')->find();
        if($last_trade && $last_trade['add_time']>$start_day) {
            $start_day = $last_trade['add_time'] + $step * 2 ;
        }

        $auto_trade_list = Db::name('currency_autotrade')->where(['is_autotrade'=>1,'kline_huobi'=>0,'kline_id'=>$this->kline_id])->select();
        if(empty($auto_trade_list)) return;

        while ($start_day<$stop_day && $start_day<time()) {
            echo date('Y-m-d H:i',$start_day)."\r\n";

            //1分钟--1周处理 跑上一分钟的
            foreach ($this->time_list as $key=>$time) {
                if($key<8 && $start_day%$time==0) {
                    $start_time = $start_day - $time;
                    $stop_time = $start_day - 1;
                    if($stop_time<time()) {
                        echo date('Y-m-d H:i:s',$start_time).' : '.date('Y-m-d H:i:s',$stop_time)."\r\n";
                        if($key==1) {
                            $this->rule_one($time,$start_time,$stop_time,$auto_trade_list);
                        } else {
                            $this->rule_many($time,$start_time,$stop_time,$auto_trade_list);
                        }
                    }
                }
            }

            //一月的
            $start_month_time = strtotime(date('Y-m')); //本月开始
            if($start_day==$start_month_time) {
                $start_month_pre = strtotime('-1 months',$start_month_time); //上个月开始
                $start_time = $start_month_pre;
                $stop_time = $start_month_time - 1;
                $this->rule_many($this->time_list[8],$start_time,$stop_time,$auto_trade_list);
            }

            $start_day += $step;
        }
    }

    protected function rule_many($type,$start_time,$stop_time,$auto_trade_list,$is_repeat=false){
        $type_index = array_search($type,$this->time_list);
        if(empty($type_index)) return;

        $type_pre = $type_index -1 ;
        if($type_pre<=0) return;

        foreach ($auto_trade_list as $autotrade) {
            $pre_info = Db::name('kline')->where(['type'=>$this->time_list[$type_pre],'currency_id'=>$autotrade['currency_id'],'currency_trade_id'=>$autotrade['trade_currency_id'],'add_time'=>['between',[$start_time,$stop_time]]])
                ->field('max(hign_price) as hign_price,min(low_price) as low_price,sum(num) as num')->find();
            if($pre_info && !empty($pre_info['hign_price'])) {
                //改为数量随机
                $num = ($type / $this->time_list[1]) * $autotrade['num'] * rand(0,$autotrade['num_limits'])/100; //5n * (1%-50%)
                if($num<=0) $num=1;
                $data = [
                    'type' => $type,
                    'currency_id' => $autotrade['currency_id'],
                    'currency_trade_id' => $autotrade['trade_currency_id'],
                    'hign_price' => $pre_info['hign_price'],
                    'low_price' => $pre_info['low_price'],
                    'num' => $num,
                    'open_price' => 0,
                    'close_price' => 0,
                    'add_time' => $start_time,
                ];
                $open_info = Db::name('kline')->where(['type'=>$this->time_list[$type_pre],'currency_id'=>$autotrade['currency_id'],'currency_trade_id'=>$autotrade['trade_currency_id'],'add_time'=>['between',[$start_time,$stop_time]]])->order('add_time asc')->find();
                $close_info = Db::name('kline')->where(['type'=>$this->time_list[$type_pre],'currency_id'=>$autotrade['currency_id'],'currency_trade_id'=>$autotrade['trade_currency_id'],'add_time'=>['between',[$start_time,$stop_time]]])->order('add_time desc')->find();
                if($open_info) {
                    $data['open_price'] = $open_info['open_price'];
                    $data['close_price'] = $close_info['close_price'];
                }
                if($is_repeat) {
                    $kline = Db::name('kline')->where(['type'=>$type,'currency_id'=>$autotrade['currency_id'],'currency_trade_id'=>$autotrade['trade_currency_id'],'add_time'=>$start_time])->find();
                    if(!empty($kline)) {
                        Db::name('kline')->where(['type'=>$type,'currency_id'=>$autotrade['currency_id'],'currency_trade_id'=>$autotrade['trade_currency_id'],'add_time'=>$start_time])->update($data);
                    } else {
                        Db::name('kline')->insertGetId($data);
                    }
                } else {
                    Db::name('kline')->insertGetId($data);
                }
            }
        }
    }

    protected function rule_one($type,$start_time,$stop_time,$auto_trade_list) {
        foreach ($auto_trade_list as $autotrade) {
            //是否已经跑过
            $kline = Db::name('kline')->where(['type'=>$type,'currency_id'=>$autotrade['currency_id'],'currency_trade_id'=>$autotrade['trade_currency_id'],'add_time'=>$start_time])->find();
            if($kline) continue;

            $autotrade['price'] = $autotrade['last_price'];

            //如果有真实交易,则按照最新的价格来进行涨跌限制
            $where['currency_id'] = $autotrade['currency_id'];
            $where['currency_trade_id'] = $autotrade['trade_currency_id'];
            $where['add_time'] = ['between',[$start_time,$stop_time]];
            $trade_info = Db::name('trade')->where($where)->order('add_time desc')->find();
            if(!empty($trade_info)) {
                $autotrade['price'] = $trade_info['price'];
                Db::name('currency_autotrade')->where(['currency_id'=>$autotrade['currency_id'],'trade_currency_id'=>$autotrade['trade_currency_id']])->setField('last_price',$autotrade['price']);
            }

            //限制价格区间
            $price_limit = $autotrade['price'] * $autotrade['price_limits']/100;

            //查找上一次记录
            $last_auto = Db::name('kline')->where(['type'=>$type,'currency_id'=>$autotrade['currency_id'],'currency_trade_id'=>$autotrade['trade_currency_id'],'add_time'=>['lt',$start_time]])->order('add_time desc')->find();
            if($last_auto) {
                $last_max_price = $last_auto['hign_price'];
                $last_min_price = $last_auto['low_price'];
            } else {
                $last_max_price = $last_min_price = $autotrade['price'];
            }

            $max_price_limit = $autotrade['price'] + $price_limit; //最高点
            $min_price_limit = $autotrade['price'] - $price_limit; //最低点
            $up_down = $this->rule_1_up_or_down($max_price_limit, $min_price_limit, $last_max_price);

            $rand = rand(0,10); //单次最高涨跌幅0-30%
            if($up_down=='up') {
                //最低价 上次价格最低价
                $low_price = $last_min_price + ($last_max_price-$last_min_price) * (rand(0,10)/10);
                $hign_price = $last_max_price + ($max_price_limit-$last_max_price) * $rand/100;
            } elseif($up_down=='down'){
                //最高价 上次价格最高价
                $hign_price = $last_min_price + ($last_max_price-$last_min_price) * (rand(0,10)/10);
                $low_price = $last_min_price - ($last_min_price-$min_price_limit) * $rand/100;
            }

            if($up_down=='up') {
                //低开高收
                $close_price = $low_price + ($hign_price-$low_price) * rand(7,10)/10; //7-10防止高线过长
                $open_price = $low_price + ($hign_price-$low_price) * rand(0,3)/10; //0-3防止低线过长
            } elseif ($up_down=='down') {
                //高开低收
                $open_price = $low_price + ($hign_price-$low_price) * rand(7,10)/10;
                $close_price = $low_price + ($hign_price-$low_price) * rand(0,3)/10;
            }

            $num = $autotrade['num'] * rand(0,$autotrade['num_limits'])/100; //5n * (1%-50%)
            if($num<=0) $num = 1;

            $data = [
                'type' => $type,
                'currency_id' => $autotrade['currency_id'],
                'currency_trade_id' => $autotrade['trade_currency_id'],
                'open_price' => $open_price,
                'close_price' => $close_price,
                'hign_price' => $hign_price,
                'low_price' => $low_price,
                'num' => $num,
                'add_time' => $start_time,
                'real_price' => !empty($trade_info['price']) ? $trade_info['price'] : 0,
                'direction' => $up_down,
            ];
            Db::name('kline')->insertGetId($data);
        }
    }

    //上次价格距离最高点越近 下降概率越高
    private function rule_1_up_or_down($max_price,$min_price,$price) {
        if($price<$min_price) {
            return 'up'; //小于最低价上升
        } elseif($price>$max_price) {
            return 'down'; //大于最高价下降
        }

        $count_1 = 0;
        $step = ($max_price - $min_price)/10; //价格区间分为10分 离最高价越近上升概率越低
        $m_price = $max_price;
        while ($m_price>$min_price) {
            $m_price -= $step;
            if($price>$m_price) {
                break;
            }
            $count_1++;
        }

        if($count_1==0)  return 'down';
        if($count_1>=10) return 'up';

        $arr = array_fill(0,$count_1,1) + array_fill($count_1,10-$count_1,0);
        $val = $arr[array_rand($arr)];
        return $val==1 ? 'up' : 'down';
    }

    //每分钟跑一次-火币
    protected function every_minute_huobi()
    {
        $step = 60;
        $auto_trade_list = Db::name('currency_autotrade')->where(['is_autotrade' => 1, 'kline_huobi' => 1,'kline_id'=>$this->kline_id])->select();
        if (empty($auto_trade_list)) return;

        foreach ($auto_trade_list as $key => $value) {
            foreach ($this->time_list_huobi as $key1 => $value1) {
                \app\common\model\Kline::create_kline_huobi($value['currency_id'], $value['trade_currency_id'], $key1, $value1);
            }
        }
    }

    //根据真实交易记录生成缓存数据
    protected function every_minute_trade($start_day='') {
        if(empty($start_day)) $start_day = strtotime(date('Y-m-d H:i'));

        $auto_trade_list = Db::name('currency_autotrade')->where(['is_autotrade'=>1,'kline_huobi'=>0,'kline_trade'=>1,'kline_id'=>$this->kline_id])->select();
        if(empty($auto_trade_list)) return;

        //1分钟--1周处理 跑上一分钟的
        foreach ($this->time_list as $key=>$kline_step) {
            if($key<8 && $start_day%$kline_step==0) {
                $start_time = $start_day - $kline_step;
                $stop_time = $start_day - 1;
                if($key==1) {
                    \app\common\model\Kline::runOne($kline_step,$start_time,$stop_time,$auto_trade_list);
                } else {
                    \app\common\model\Kline::runMany($kline_step,$start_time,$stop_time,$auto_trade_list);
                }
            }
        }

        //跑一个月的
        $start_month_time = strtotime(date('Y-m',$start_day)); //本月
        if($start_day==$start_month_time) {
            $start_month_pre = strtotime('-1 months',$start_month_time); //上个月开始
            $start_time = $start_month_pre;
            $stop_time = $start_month_time - 1;
            \app\common\model\Kline::runMany($this->time_list[8],$start_time,$stop_time,$auto_trade_list);
        }
    }

    protected function every_minute_trade_history($start_day,$stop_day) {
        $start_day = strtotime($start_day);
        $stop_day = strtotime($stop_day);
        $step = 60;
        while ($start_day<$stop_day && $start_day<time()) {
            echo date('Y-m-d H:i',$start_day)."\r\n";
            $this->every_minute_trade($start_day);
            $start_day+=$step;
        }
    }

    private function test() {
        //            $trade_info = [];
//            //随机真实数据 概率10%
//            $max_p = 0;
//            $rand_p = rand(1,30);
//            if($rand_p==1) {
//                $rand_p = rand(1,4);
//                switch ($rand_p) {
//                    case 1://在上区间
//                        $max_p = $autotrade['price'] + ($max_price_limit-$autotrade['price']) * rand(0,10)/10;
//                        $min_p = $max_p - ($max_p - $min_price_limit)*rand(0,100)/100;
//                        break;
//                    case 2: //在下区间
//                        $max_p = $autotrade['price'] + ($autotrade['price']-$min_price_limit) * rand(0,10)/10;
//                        $min_p = $max_p - ($max_p - $min_price_limit)*rand(0,100)/100;
//                        break;
//                    case 3: //在最高值之上
//                        $min_p = $max_price_limit + $price_limit* rand(0,30)/100;
//                        $max_p = $min_p + $price_limit* rand(0,30)/100;
//                        break;
//                    case 4: //在最低值之下
//                        $max_p = $min_price_limit - $price_limit* rand(0,30)/100;
//                        $min_p = $max_p - $price_limit* rand(0,30)/100;
//                        break;
//                }
//                if(!empty($max_p)) {
//                    $trade_info['max_price'] =$max_p;
//                    $trade_info['min_price'] = $min_p;
//                }
//            }
    }
}
