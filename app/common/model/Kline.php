<?php


namespace app\common\model;


use think\Db;
use think\Exception;
use think\Log;
use think\Model;

/**
 * Class Kline
 * @package app\common\model
 */
class Kline extends Model
{
    /**
     * @var string
     */
    protected $table = "yang_kline";

    static function getKline($currency_name,$range) {
        $r['code'] = ERROR1;
        $r['message'] = lang("b_stop");
        $r['result'] = null;
        if(empty($currency_name)) return $r;

        $step = substr($range, 0, -3); //默认1分钟
        $all_time=array('1'=>'60','5'=>'300','15'=>'900','30'=>'1800','60'=>'3600','1D'=>'86400','D'=>'86400','1W'=>'604800','1M'=>'2592000');
        $resolution = array_search($step,$all_time);
        if(!$resolution) return $r;

        $currency_name = explode("_",$currency_name);
        if(count($currency_name)!=2) return $r;

        $currency = Currency::where(['currency_mark'=>$currency_name[0]])->find();
        $other_currency = Currency::where(['currency_mark'=>$currency_name[1]])->find();
        if(empty($currency) || empty($other_currency)) return $r;

        $currency['currency_trade_id'] = $other_currency['currency_id'];

        $Sname = 'app_lists_pro' . $currency['currency_id'] . '_' . $currency['currency_trade_id'] . 't' . $step;
        $data = cache($Sname);
        if (empty($data)) {
            $data = [];
            $list = Db::name('kline')->where(['type'=>$step,'currency_id'=>$currency['currency_id'],'currency_trade_id'=>$currency['currency_trade_id']])
                ->limit(1000)->order('add_time desc')->select();
            if($list) {
                //添加最新的一条记录
                $kline_first = current($list);
                $newKline = Trade::getNewKline($currency['currency_id'],$currency['currency_trade_id'],$kline_first['add_time'] + $step);
                if($newKline && $newKline['open_price']>0) {
                    array_unshift($list,$newKline);
                }

                $timeArr = array_column($list, 'add_time');
                array_multisort($timeArr, SORT_ASC, $list);
                foreach ($list as $k => $v) {
                    $data[] = [
                        floatval($v['add_time'].''),
                        number_format($v['open_price'],6,'.',''),
                        number_format($v['hign_price'],6,'.',''),
                        number_format($v['low_price'],6,'.',''),
                        number_format($v['close_price'],6,'.',''),
                        floatval($v['num'])
                    ];
                }
                cache($Sname, $data, 2);
            } else {
                $data = [];
            }
        }

        if(empty($data)) {
            $r['message'] = lang('not_data');
            return $r;
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $data;
        return $r;
    }

    //跑1分钟的K线图
    static function runOne($type,$start_time,$stop_time,$exchange_currency) {
        try{
            foreach ($exchange_currency as $currency) {
                //是否已经跑过
                $kline = self::where(['type'=>$type,'currency_id'=>$currency['currency_id'],'currency_trade_id'=>$currency['trade_currency_id'],'add_time'=>$start_time])->find();
                if($kline) continue;

                $new_kline = Trade::getKlineByTime($currency['currency_id'],$currency['trade_currency_id'],$start_time,$stop_time);
                if($new_kline && $new_kline['open_price']>0) {
                    $data = [
                        'type' => $type,
                        'currency_id' => $currency['currency_id'],
                        'currency_trade_id' => $currency['trade_currency_id'],
                        'open_price' => $new_kline['open_price'],
                        'close_price' => $new_kline['close_price'],
                        'hign_price' => $new_kline['hign_price'],
                        'low_price' => $new_kline['low_price'],
                        'num' => $new_kline['num'],
                        'add_time' => $start_time,
                    ];
                    self::insertGetId($data);
                }
            }
        } catch (\Exception $e) {

        }
    }

    //跑
    static function runMany($type,$start_time,$stop_time,$exchange_currency) {
        try{
            foreach ($exchange_currency as $currency) {
                //是否已经跑过
                $kline = self::where(['type'=>$type,'currency_id'=>$currency['currency_id'],'currency_trade_id'=>$currency['trade_currency_id'],'add_time'=>$start_time])->find();
                if($kline) continue;

                $pre_info = self::field('open_price as open_price,close_price as close_price,max(hign_price) as hign_price,min(low_price) as low_price,sum(num) as num')
                    ->where('type',60)
                    ->where('currency_id',$currency['currency_id'])
                    ->where('currency_trade_id',$currency['trade_currency_id'])
                    ->where('add_time>='.$start_time.' and add_time<='.$stop_time)
                    ->find();
                if($pre_info && !empty($pre_info['hign_price'])) {
                    $data = [
                        'type' => $type,
                        'currency_id' => $currency['currency_id'],
                        'currency_trade_id' => $currency['trade_currency_id'],
                        'open_price' => $pre_info['open_price'],
                        'close_price' => $pre_info['close_price'],
                        'hign_price' => $pre_info['hign_price'],
                        'low_price' => $pre_info['low_price'],
                        'num' => $pre_info['num'],
                        'add_time' => $start_time,
                    ];
                    self::insertGetId($data);
                }
            }
        }catch (\Exception $e) {

        }
    }

    /**
     * 生成交易对K线图数据-火币
     * @param integer $currency_id
     * @param integer $currency_trade_id
     * @param integer $period
     * @param integer $type
     * @return string
     */
    public static function create_kline_huobi($currency_id, $currency_trade_id, $period, $type)
    {
        $where = [
            'currency_id'=>$currency_id,
            'currency_trade_id'=>$currency_trade_id,
        ];
        $currency = Currency::get($currency_id);
        $trade_currency = Currency::get($currency_trade_id);
        $symbol = strtolower($currency['currency_name'].$trade_currency['currency_name']);
        try {
            DB::startTrans();

            $log = "交易对K线图生成:currency:{$currency['currency_name']},trade_currency:{$trade_currency['currency_name']},symbol:{$symbol},type:{$period}-{$type}";
            $where['type'] = $type;
            $find = Db::name('kline')->where($where)->order('id', 'desc')->find();
            if (!$find) {
                //说明该交易对该type下没有任何记录，生成历史数据
                $log .= ",该交易对下type={$type}没有任何记录,生成历史数据";
                if (!self::create_kline_history_huobi($currency_id, $currency_trade_id, $symbol, $period, $type, $log)) {
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
                $now_time = strtotime(date('Y-m-d H:i:00'));
                foreach ($huobi as $key1 => $value1) {
                    $where1 = $where;
                    $where1['add_time'] = $value1['id'];
                    $find1 = Db::name('kline')->where($where1)->order('id', 'desc')->find();
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
                            'num'=>number_format($value1['amount'],6,".",""),
                            'amount'=>number_format($value1['amount'],6,".",""),
                            'count'=>number_format($value1['count'],6,".",""),
                            'vol'=>number_format($value1['vol'],6,".",""),
                            //'ch'=>$res['ch'],
                            //'add_time'=>$value1['id'],
                            'update_time'=>time(),
                        ];
                    }
                    else {
                        //if ($value1['id'] >= $now_time) continue;
                        $add_list[] = [
                            'type'=>$type,
                            'currency_id'=>$currency_id,
                            'currency_trade_id'=>$currency_trade_id,
                            'open_price'=>number_format($value1['open'],6,".",""),
                            'close_price'=>number_format($value1['close'],6,".",""),
                            'hign_price'=>number_format($value1['high'],6,".",""),
                            'low_price'=>number_format($value1['low'],6,".",""),
                            'num'=>number_format($value1['amount'],6,".",""),
                            'amount'=>number_format($value1['amount'],6,".",""),
                            'count'=>number_format($value1['count'],6,".",""),
                            'vol'=>number_format($value1['vol'],6,".",""),
                            //'ch'=>$res['ch'],
                            'add_time'=>$value1['id'],
                            'update_time'=>time(),
                        ];
                    }
                }
                if (count($add_list)) {
                    $kline = new \app\common\model\Kline;
                    $res1 = $kline->insertAll($add_list);
                    if (empty($res1)) {
                        throw new Exception(lang('插入记录失败').'-in line:'.__LINE__);
                        //return false;
                    }
                }
                if (count($update_list)) {
                    $kline = new \app\common\model\Kline;
                    $res2 = $kline->isUpdate()->saveAll($update_list);
                    if (empty($res2)) {
                        throw new Exception(lang('更新记录失败-2').'-in line:'.__LINE__);
                        //return false;
                    }
                }
            }

            Db::commit();
            $log .= ",成功";
            $r['code'] = SUCCESS;
            $r['message'] = lang('successful_operation');
        } catch (Exception $exception) {
            Db::rollback();
            $log .= ",异常:{$exception->getMessage()}";
            $r['code'] = ERROR5;
            $r['message'] = $exception->getMessage();
            Log::write($log, 'INFO');
        }
        //Log::write($log, 'INFO');
        return $r;
    }

    /**
     * 生成交易对K线图数据-生成历史数据-火币
     * @param integer $currency_id
     * @param integer $currency_trade_id
     * @param integer $symbol
     * @param integer $period
     * @param integer $type
     * @param integer $log
     * @return string
     */
    protected static function create_kline_history_huobi($currency_id, $currency_trade_id, $symbol, $period, $type,  &$log)
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
        $now_time = strtotime(date('Y-m-d H:i:00'));
        foreach ($huobi as $key1 => $value1) {
            //if ($value1['id'] >= $now_time) continue;
            $data_list[] = [
                'type'=>$type,
                'currency_id'=>$currency_id,
                'currency_trade_id'=>$currency_trade_id,
                'open_price'=>number_format($value1['open'],6,".",""),
                'close_price'=>number_format($value1['close'],6,".",""),
                'hign_price'=>number_format($value1['high'],6,".",""),
                'low_price'=>number_format($value1['low'],6,".",""),
                'num'=>number_format($value1['amount'],6,".",""),
                'amount'=>number_format($value1['amount'],6,".",""),
                'count'=>number_format($value1['count'],6,".",""),
                'vol'=>number_format($value1['vol'],6,".",""),
                //'ch'=>$res['ch'],
                'add_time'=>$value1['id'],
                'update_time'=>time(),
            ];
        }
        $kline = new \app\common\model\Kline;
        $res1 = $kline->saveAll($data_list);
        if (empty($res1)) {
            $log .= lang('插入记录失败').'-in line:'.__LINE__;//throw new Exception(lang('插入记录失败').'-in line:'.__LINE__);
            return false;
        }
        $log .= ",生成历史数据成功";
        return true;
    }

    private static function curl_get($url, $timeout = 30)
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
