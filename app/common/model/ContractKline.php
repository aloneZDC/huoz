<?php
//合约K线图数据
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class ContractKline extends Base
{
    private static $time_list = [
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
     * 获取K线价格
     * @param integer $trade_id
     * @param integer $type 类型 60-1分钟 300-5分钟 900-15分钟 1800-30分钟  3600一小时  86400一天 604800一周 2592000‬一月
     * @param string $field
     * @param integer $add_time
     * @return string
     */
    public static function get_price($trade_id, $type, $field, $add_time = 0, $length = 2)
    {
        $price = 0;
        if (!$add_time) $add_time = strtotime(date('Y-m-d H:i:00'));
        $where = [
            'trade_id'=>$trade_id,
            'type'=>$type,
            'add_time'=>$add_time,
        ];
        $value = (new self)->where($where)->order('id', 'desc')->value($field);
        if ($value) {
            $price = floattostr($value);
            /*if ($length > 0) {
                $price = number_format($value,$length,".","");
            }
            else {
                $price = $value;
            }*/
        };
        return $price;
    }

    /**
     * 获取交易对日涨幅
     * @param $trade_id
     * @return int
     */
    public static function get_daily_gain($trade_id) {

        $daily_gain = 0;
        $today = strtotime(date('Y-m-d'));
        //$yestday = $today - 86400;
        $today_close = self::get_price($trade_id, 86400, 'close_price', $today, 0);
        $today_open = self::get_price($trade_id, 86400, 'open_price', $today, 0);
        if ($today_close > 0 && $today_open > 0) {
            $daily_gain = keepPoint(($today_close - $today_open) / $today_open * 100 ,2);
        }
        return $daily_gain;
    }

    /**
     * 生成合约K线图数据
     * @param integer $trade_id
     * @param integer $currency_id
     * @param integer $currency_trade_id
     * @param integer $period
     * @param integer $type
     * @return string
     */
    public static function create_kline($trade_id, $currency_id, $currency_trade_id, $period, $type)
    {
        $where = [
            'trade_id'=>$trade_id,
            'currency_id'=>$currency_id,
            'currency_trade_id'=>$currency_trade_id,
        ];
        $currency = Currency::get($currency_id);
        $trade_currency = Currency::get($currency_trade_id);
        $symbol = strtolower($currency['currency_name'].$trade_currency['currency_name']);
        //Log::write("合约交易对K线图生成:trade:".$trade_id.',currency:'.$currency.',trade_currency:'.$trade_currency.',symbol:'.$symbol, 'INFO');
        try {
            self::startTrans();

            $log = "合约交易对K线图生成:trade:{$trade_id},currency:{$currency['currency_name']},trade_currency:{$trade_currency['currency_name']},symbol:{$symbol},type:{$period}-{$type}";
            $where['type'] = $type;
            $find = (new self)->where($where)->order('id', 'desc')->find();
            if (!$find) {
                //说明该交易对该type下没有任何记录，生成历史数据
                $log .= ",该交易对下type={$type}没有任何记录,生成历史数据";
                if (!self::create_kline_history($trade_id, $currency_id, $currency_trade_id, $symbol, $period, $type, $log)) {
                    throw new Exception('生成历史数据异常');
                }
            }
            else {
                $now = time();
                $diff = intval((strtotime(date('Y-m-d H:i:00'), $now) - $find['add_time']) / $type);
                $size = $diff + 1 > 2000 ? 2000 : $diff + 1;
                $url = "https://api.huobipro.com/market/history/kline?period={$period}&size={$size}&symbol={$symbol}";
                $log .= ",url:{$url}";
                $post_url = 'http://HBapi.cexe.io/post.php?url='.urlencode($url);
                $log .= ",post_url:{$post_url}";
                //$res = _curl($url, '', '', 'GET', 5);
                $res = self::curl_get($post_url, 5);
                if (!$res) throw new Exception(lang('火币请求失败'));
                $res = json_decode($res, true);
                if ($res['status'] != 'ok') throw new Exception("火币网返回错误,err-code:{$res['err-code']},err-msg:{$res['err-msg']}");
                if (empty($res['data'])) throw new Exception("火币网返回数据为空");
                $huobi = $res['data'];
                $ids = array_column($huobi,'id');
                array_multisort($ids,SORT_ASC,$huobi);
                $add_list = [];
                $update_list = [];
                foreach ($huobi as $key1 => $value1) {
                    $where1 = $where;
                    $where1['add_time'] = $value1['id'];
                    $find1 = (new self)->where($where1)->order('id', 'desc')->find();
                    if ($find1) {//记录已存在，更新已有记录
                        $update_list[] = [
                            'id'=>$find1['id'],
                            //'type'=>$value,
                            //'trade_id'=>$trade_id,
                            //'currency_id'=>$currency_id,
                            //'currency_trade_id'=>$currency_trade_id,
                            'open_price'=>number_format($value1['open'],6,".",""),
                            'close_price'=>number_format($value1['close'],6,".",""),
                            'hign_price'=>number_format($value1['high'],6,".",""),
                            'low_price'=>number_format($value1['low'],6,".",""),
                            'amount'=>number_format($value1['amount'],6,".",""),
                            'count'=>number_format($value1['count'],6,".",""),
                            'vol'=>number_format($value1['vol'],6,".",""),
                            'ch'=>$res['ch'],
                            //'add_time'=>$value1['id'],
                            'update_time'=>time(),
                        ];
                    }
                    else {
                        $add_list[] = [
                            'type'=>$type,
                            'trade_id'=>$trade_id,
                            'currency_id'=>$currency_id,
                            'currency_trade_id'=>$currency_trade_id,
                            'open_price'=>number_format($value1['open'],6,".",""),
                            'close_price'=>number_format($value1['close'],6,".",""),
                            'hign_price'=>number_format($value1['high'],6,".",""),
                            'low_price'=>number_format($value1['low'],6,".",""),
                            'amount'=>number_format($value1['amount'],6,".",""),
                            'count'=>number_format($value1['count'],6,".",""),
                            'vol'=>number_format($value1['vol'],6,".",""),
                            'ch'=>$res['ch'],
                            'add_time'=>$value1['id'],
                            'update_time'=>time(),
                        ];
                    }
                }
                if (count($add_list)) {
                    $kline = new self;
                    $res1 = $kline->saveAll($add_list);
                    if (empty($res1)) {
                        throw new Exception(lang('插入记录失败').'-in line:'.__LINE__);
                        //return false;
                    }
                }
                if (count($update_list)) {
                    $kline = new self;
                    $res2 = $kline->isUpdate()->saveAll($update_list);
                    if (empty($res2)) {
                        throw new Exception(lang('更新记录失败-2').'-in line:'.__LINE__);
                        //return false;
                    }
                }
            }

            self::commit();
            $log .= ",成功";
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
        } catch (Exception $exception) {
            self::rollback();
            $log .= ",异常:{$exception->getMessage()}";
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
            Log::write($log, 'INFO');
        }
        //Log::write($log, 'INFO');
        return $r;
    }

    /**
     * 生成合约K线图数据-旧接口保留
     * @param integer $trade_id
     * @param integer $currency_id
     * @param integer $currency_trade_id
     * @return string
     */
    public static function create_kline_old($trade_id, $currency_id, $currency_trade_id, $time_list)
    {
        $where = [
            'trade_id'=>$trade_id,
            'currency_id'=>$currency_id,
            'currency_trade_id'=>$currency_trade_id,
        ];
        $currency = Currency::get($currency_id);
        $trade_currency = Currency::get($currency_trade_id);
        $symbol = strtolower($currency['currency_name'].$trade_currency['currency_name']);
        //Log::write("合约交易对K线图生成:trade:".$trade_id.',currency:'.$currency.',trade_currency:'.$trade_currency.',symbol:'.$symbol, 'INFO');
        foreach ($time_list as $key => $value) {
            try {
                self::startTrans();

                $log = "合约交易对K线图生成:trade:{$trade_id},currency:{$currency['currency_name']},trade_currency:{$trade_currency['currency_name']},symbol:{$symbol},type:{$key}-{$value}";
                $where['type'] = $value;
                $find = (new self)->where($where)->order('id', 'desc')->find();
                if (!$find) {
                    //说明该交易对该type下没有任何记录，生成历史数据
                    $log .= ",该交易对下type={$value}没有任何记录,生成历史数据";
                    if (!self::create_kline_history_old($trade_id, $currency_id, $currency_trade_id, $symbol, $key, $time_list, $log)) {
                        throw new Exception('生成历史数据异常');
                    }
                }
                else {
                    $now = time();
                    $diff = intval((strtotime(date('Y-m-d H:i:00'), $now) - $find['add_time']) / $value);
                    $size = $diff + 1 > 2000 ? 2000 : $diff + 1;
                    $url = "https://api.huobipro.com/market/history/kline?period={$key}&size={$size}&symbol={$symbol}";
                    $log .= ",url:{$url}";
                    $post_url = 'http://HBapi.cexe.io/post.php?url='.urlencode($url);
                    $log .= ",post_url:{$post_url}";
                    //$res = _curl($url, '', '', 'GET', 5);
                    $res = self::curl_get($post_url, 5);
                    if (!$res) throw new Exception(lang('火币请求失败'));
                    $res = json_decode($res, true);
                    if ($res['status'] != 'ok') throw new Exception("火币网返回错误,err-code:{$res['err-code']},err-msg:{$res['err-msg']}");
                    if (empty($res['data'])) throw new Exception("火币网返回数据为空");
                    $huobi = $res['data'];
                    $ids = array_column($huobi,'id');
                    array_multisort($ids,SORT_ASC,$huobi);
                    $add_list = [];
                    $update_list = [];
                    foreach ($huobi as $key1 => $value1) {
                        $where1 = $where;
                        $where1['add_time'] = $value1['id'];
                        $find1 = (new self)->where($where1)->order('id', 'desc')->find();
                        if ($find1) {//记录已存在，更新已有记录
                            $update_list[] = [
                                'id'=>$find1['id'],
                                //'type'=>$value,
                                //'trade_id'=>$trade_id,
                                //'currency_id'=>$currency_id,
                                //'currency_trade_id'=>$currency_trade_id,
                                'open_price'=>number_format($value1['open'],6,".",""),
                                'close_price'=>number_format($value1['close'],6,".",""),
                                'hign_price'=>number_format($value1['high'],6,".",""),
                                'low_price'=>number_format($value1['low'],6,".",""),
                                'amount'=>number_format($value1['amount'],6,".",""),
                                'count'=>number_format($value1['count'],6,".",""),
                                'vol'=>number_format($value1['vol'],6,".",""),
                                'ch'=>$res['ch'],
                                //'add_time'=>$value1['id'],
                                'update_time'=>time(),
                            ];
                        }
                        else {
                            $add_list[] = [
                                'type'=>$value,
                                'trade_id'=>$trade_id,
                                'currency_id'=>$currency_id,
                                'currency_trade_id'=>$currency_trade_id,
                                'open_price'=>number_format($value1['open'],6,".",""),
                                'close_price'=>number_format($value1['close'],6,".",""),
                                'hign_price'=>number_format($value1['high'],6,".",""),
                                'low_price'=>number_format($value1['low'],6,".",""),
                                'amount'=>number_format($value1['amount'],6,".",""),
                                'count'=>number_format($value1['count'],6,".",""),
                                'vol'=>number_format($value1['vol'],6,".",""),
                                'ch'=>$res['ch'],
                                'add_time'=>$value1['id'],
                                'update_time'=>time(),
                            ];
                        }
                    }
                    if (count($add_list)) {
                        $kline = new self;
                        $res1 = $kline->saveAll($add_list);
                        if (empty($res1)) {
                            throw new Exception(lang('插入记录失败').'-in line:'.__LINE__);
                            //return false;
                        }
                    }
                    if (count($update_list)) {
                        $kline = new self;
                        $res2 = $kline->isUpdate()->saveAll($update_list);
                        if (empty($res2)) {
                            throw new Exception(lang('更新记录失败-2').'-in line:'.__LINE__);
                            //return false;
                        }
                    }
                }

                self::commit();
                $log .= ",成功";
                $r['code'] = SUCCESS;
                $r['message'] = lang('successful_operation');
            } catch (Exception $exception) {
                self::rollback();
                $log .= ",异常:{$exception->getMessage()}";
                $r['code'] = ERROR5;
                $r['message'] = $exception->getMessage();
            }
            Log::write($log, 'INFO');
        }
        return $r;
    }

    /**
     * 生成合约K线图数据-生成历史数据
     * @param integer $trade_id
     * @param integer $currency_id
     * @param integer $currency_trade_id
     * @param integer $symbol
     * @param integer $period
     * @param integer $type
     * @param integer $log
     * @return string
     */
    public static function create_kline_history($trade_id, $currency_id, $currency_trade_id, $symbol, $period, $type,  &$log)
    {
        //$file_name = 'E:\WWW\hongbao/huobi_json/'.$symbol.'_'.$period.'.json';
        //$url = "https://api.huobi.pro/market/history/kline?period={$period}&size=2000&symbol={$symbol}";
        $url = "https://api.huobipro.com/market/history/kline?period={$period}&size=2000&symbol={$symbol}";
        $log .= ",create_kline_history,url:{$url}";
        $post_url = 'http://HBapi.cexe.io/post.php?url='.urlencode($url);
        $log .= ",post_url:{$post_url}";
        //$res = _curl($url, '', '', 'GET', 5);
        $res = self::curl_get($post_url, 5);
        if (!$res) {
            $log .= lang('火币请求失败');//throw new Exception(lang('火币请求失败'));
            return false;
        }
        $res = json_decode($res, true);
        if ($res['status'] != 'ok') {
            $log .= "火币网返回错误,err-code:{$res['err-code']},err-msg:{$res['err-msg']}";//throw new Exception("火币网返回错误,err-code:{$res['err-code']},err-msg:{$res['err-msg']}");
            return false;
        }
        if (empty($res['data'])) {
            $log .= "火币网返回数据为空";//throw new Exception("火币网返回数据为空");
            return false;
        }
        $huobi = $res['data'];
        $ids = array_column($huobi,'id');
        array_multisort($ids,SORT_ASC,$huobi);
        $data_list = [];
        foreach ($huobi as $key1 => $value1) {
            $data_list[] = [
                'type'=>$type,
                'trade_id'=>$trade_id,
                'currency_id'=>$currency_id,
                'currency_trade_id'=>$currency_trade_id,
                'open_price'=>number_format($value1['open'],6,".",""),
                'close_price'=>number_format($value1['close'],6,".",""),
                'hign_price'=>number_format($value1['high'],6,".",""),
                'low_price'=>number_format($value1['low'],6,".",""),
                'amount'=>number_format($value1['amount'],6,".",""),
                'count'=>number_format($value1['count'],6,".",""),
                'vol'=>number_format($value1['vol'],6,".",""),
                'ch'=>$res['ch'],
                'add_time'=>$value1['id'],
                'update_time'=>time(),
            ];
        }
        $kline = new self;
        $res1 = $kline->saveAll($data_list);
        if (empty($res1)) {
            $log .= lang('插入记录失败').'-in line:'.__LINE__;//throw new Exception(lang('插入记录失败').'-in line:'.__LINE__);
            return false;
        }
        $log .= ",生成历史数据成功";
        return true;
    }

    /**
     * 生成合约K线图数据-生成历史数据-旧接口保留
     * @param integer $trade_id
     * @param integer $currency_id
     * @param integer $currency_trade_id
     * @param integer $symbol
     * @param integer $period
     * @param integer $log
     * @return string
     */
    public static function create_kline_history_old($trade_id, $currency_id, $currency_trade_id, $symbol, $period, $time_list,  &$log)
    {
        //$file_name = 'E:\WWW\hongbao/huobi_json/'.$symbol.'_'.$period.'.json';
        //$url = "https://api.huobi.pro/market/history/kline?period={$period}&size=2000&symbol={$symbol}";
        $url = "https://api.huobipro.com/market/history/kline?period={$period}&size=2000&symbol={$symbol}";
        $log .= ",create_kline_history,url:{$url}";
        $post_url = 'http://HBapi.cexe.io/post.php?url='.urlencode($url);
        $log .= ",post_url:{$post_url}";
        //$res = _curl($url, '', '', 'GET', 5);
        $res = self::curl_get($post_url, 5);
        if (!$res) {
            $log .= lang('火币请求失败');//throw new Exception(lang('火币请求失败'));
            return false;
        }
        $res = json_decode($res, true);
        if ($res['status'] != 'ok') {
            $log .= "火币网返回错误,err-code:{$res['err-code']},err-msg:{$res['err-msg']}";//throw new Exception("火币网返回错误,err-code:{$res['err-code']},err-msg:{$res['err-msg']}");
            return false;
        }
        if (empty($res['data'])) {
            $log .= "火币网返回数据为空";//throw new Exception("火币网返回数据为空");
            return false;
        }
        $huobi = $res['data'];
        $ids = array_column($huobi,'id');
        array_multisort($ids,SORT_ASC,$huobi);
        $data_list = [];
        foreach ($huobi as $key1 => $value1) {
            $data_list[] = [
                'type'=>self::$time_list[$period],
                'trade_id'=>$trade_id,
                'currency_id'=>$currency_id,
                'currency_trade_id'=>$currency_trade_id,
                'open_price'=>number_format($value1['open'],6,".",""),
                'close_price'=>number_format($value1['close'],6,".",""),
                'hign_price'=>number_format($value1['high'],6,".",""),
                'low_price'=>number_format($value1['low'],6,".",""),
                'amount'=>number_format($value1['amount'],6,".",""),
                'count'=>number_format($value1['count'],6,".",""),
                'vol'=>number_format($value1['vol'],6,".",""),
                'ch'=>$res['ch'],
                'add_time'=>$value1['id'],
                'update_time'=>time(),
            ];
        }
        $kline = new self;
        $res1 = $kline->saveAll($data_list);
        if (empty($res1)) {
            $log .= lang('插入记录失败').'-in line:'.__LINE__;//throw new Exception(lang('插入记录失败').'-in line:'.__LINE__);
            return false;
        }
        $log .= ",生成历史数据成功";
        return true;
    }

    static function curl_get($url, $timeout = 30)
    {
        $ssl = substr($url, 0, 8) == "https://" ? TRUE : FALSE;
        $ch = curl_init();
        $headers = array(
            "Content-Type: application/json charset=utf-8",
            //'Content-Length: ' . strlen($fields),
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.122 Safari/537.36',
        );

        $opt = array(
            CURLOPT_URL => $url,
            //CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_CUSTOMREQUEST => strtoupper('GET'),
            //CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            //CURLOPT_PROXY => '47.56.188.100',
            //CURLOPT_PROXY => '127.0.0.1',
            //CURLOPT_PROXYPORT => '30999',
            //CURLOPT_PROXYPORT => '1080',
            //CURLOPT_PROXYAUTH => CURLAUTH_BASIC,
            //CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5,
            //CURLOPT_PROXY => 'socks5://127.0.0.1:1080',
            //CURLOPT_PROXY => 'localhost:1080',
            //CURLOPT_HTTPPROXYTUNNEL => true,
            //CURLOPT_FOLLOWLOCATION => 1,
        );

        if ($ssl) {
            //$opt[CURLOPT_SSL_VERIFYHOST] = 2;
            $opt[CURLOPT_SSL_VERIFYHOST] = false;
            $opt[CURLOPT_SSL_VERIFYPEER] = FALSE;
        }
        curl_setopt_array($ch, $opt);
        $result = curl_exec($ch);
        if (!$result) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            Log::write("curl_get,url:{$url},error:{$error},error:{$errno}", 'INFO');
        }
        curl_close($ch);
        return $result;
    }
}