<?php
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;
//require_once '../../vendor/workerman/workerman/Autoloader.php';
use \Workerman\Autoloader;
use GatewayWorker\Gateway;
require_once __DIR__ . '/huobiredis.php';

$REAL_MYSQL_ADDRESS = 'ws://47.57.127.13:80/ws';

// 代理监听本地7272端口
$worker = new Worker('Websocket://0.0.0.0:8888');
$worker->count = 1;
$worker->name = 'Huobi_wss';

function saveLog($symbol, $msg){
	$dir =  __DIR__ ."/logs";
	if( !file_exists($dir) ) mkdir($dir, 0777);
	$file_path =$dir."/a-".$symbol.".log";
	$handle = fopen($file_path, "a+");
	@fwrite($handle, date("H:i:s"). $msg . "\r\n");
	@fclose($handle);
}

$worker->onWorkerStart = function($worker){
	
    global $REAL_MYSQL_ADDRESS;
    
    echo "onWorkerStart";

    // 异步建立一个到实际mysql服务器的连接
    $con = new AsyncTcpConnection($REAL_MYSQL_ADDRESS);
    //$con->transport = 'ssl';


 		$con->onError = function($con, $code, $msg)
    {
        echo "\r\n error $code $msg\n";
    };
    
    $con->onConnect = function($con) {

				echo "\r\n".date("H:i:s")." on connect";
				
        //行情图+盘口
        $make=["ethusdt"]; // "ethusdt","btcusdt","ethbtc"
        $klintime=["1min"];  // "1min","5min","15min","30min","60min","4hour","1day","1mon","1week","1year"

        foreach ($make as $value) {
            foreach ($klintime as $v) {
                $data = json_encode([ //行情
                    'sub' => "market." . $value . ".kline." . $v,
                    'id' => "id" . time(),
                    'freq-ms' => 5000 // 5000
                ]);
                $con->send($data);
            }
/*
            //盘口
             $handicap = json_encode([
                 'sub' => "market.".$value.".depth.step1",
                 'id' => $value."dep" . time()
             ]);
             $con->send($handicap);

             //成交记录
             $handicap = json_encode([
                 'sub' => "market.".$value.".trade.detail",
                 'id' => $value."trade" . time()
             ]);
             $con->send($handicap);

            //24H 头部
             $handicap = json_encode([
                 'sub' => "market.".$value.".detail",
                 'id' => $value."detail" . time()
             ]);
             $con->send($handicap);*/
        };
    };
    // mysql连接发来数据时，转发给对应客户端的连接
    $con->onMessage = function($con, $data)use($worker)
    {
        // $data = gzdecode($data);
        $data = json_decode($data, true);
        var_dump($data);
        
        saveLog("test", str_replace("\n", "", str_replace("\r\n", "",var_export($data, true))) );
        
        
        if(isset($data['ping'])) {
            $con->send(json_encode([
                "pong" => $data['ping']
            ]));
        }else{
        	
					/*$cmd = explode(".", $data["ch"]); // market.$value.kline.$v
    			$symbol = "";
    			if( sizeof($cmd)==4 ) $symbol = $cmd[1];
        	
        	if($symbol!="") saveLog($symbol, var_export($data, true) );
        	*/
        	
        	
        	/*foreach($worker->connections as $conn){
                $conn->send(json_encode($msg));
          }
          return;*/
            
			//echo "\r\n".date("H:i:s")." onMessage ";
			//var_dump($data);

            /*  tick 说明
             * "tick": {
                "id": K线id,
                "amount": 成交量,
                "count": 成交笔数,
                "open": 开盘价,
                "close": 收盘价,当K线为最晚的一根时，是最新成交价
                "low": 最低价,
                "high": 最高价,
                "vol": 成交额, 即 sum(每一笔成交价 * 该笔的成交量)
              }
             *
             *
             * */


/*
            $msg=[];
//                file_put_contents("./deta000.txt",var_export($data,true)."%%-----------\n",FILE_APPEND);

            //$hbrds= new HuobiRedis("127.0.0.1",6379);
            $hbrds= new HuobiRedis('r-j6cj3ol835g6l0rmj5.redis.rds.aliyuncs.com',6379,'aK4ZCt2rTWyThzK8M');


            if(isset($data['ch'])) {
                $pieces = explode(".", $data['ch']);
                switch ($pieces[2]) {
                    case "kline":              //行情图
                        $msg['type'] = "tradingvew";
                        $msg['market'] = $pieces[1];  //火币对
                        $msg['open'] = $data['tick']['open'];
                        $msg['close'] = $data['tick']['close'];
                        $msg['low'] = $data['tick']['low'];
                        $msg['vol'] = $data['tick']['vol'];
                        $msg['high'] = $data['tick']['high'];
                        $msg['count'] = $data['tick']['count'];
                        $msg['amount'] = $data['tick']['amount'];
                        $msg['time'] = $data['tick']['id'];

                        //把数据插入到redis

                        $table = $data['ch'];  //设置哈希表

                        $datarid = $msg;

                        $msg['period'] = $pieces[3];  //分期

                        $datarid['type'] = $pieces[3];


                        //先查询看
                        //$rs = $hbrds->SeachId($table, $data['tick']['id']);

                        //if ($rs == 1) { //如果相等就更新
                        //    $hbrds->write($table, $datarid);
                        //} else {
                        //    echo $table."\n";
                            //其他类型就更新或者插入
                            $insetinfo = $hbrds->read($table);     //先读取
                            $hbrds->write($table, $datarid);       //然后在更新覆盖原来的
                            //if(count($insetinfo)>1){               //有数据就插入数据库
                                $insertmysql = $hbrds->insertmysql("lara_kline_" . $pieces[1], $insetinfo);//读取的数据插入到数据表中
                            //}
                        //}

                        break;
                    case "depth" :   //盘口
                        $msg['type'] = "handicap";
                        $msg['market'] = $pieces[1];  //火币对
                        $msg['bid'] = [];  //买入
                        $msg['ask'] = [];  //买入
                        $bids = $data['tick']['bids'];
                        $asks = $data['tick']['asks'];
                        for ($i = 0; $i < count($bids); $i++) {  //出价  买入
                            $msg['bid'][$i]['id'] = $i;
                            $msg['bid'][$i]['price'] = $bids[$i][0];
                            $msg['bid'][$i]['quantity'] = $bids[$i][1];
                            if ($i == 0) {
                                $msg['bid'][$i]['total'] = $bids[$i][1];
                            } else {
                                $msg['bid'][$i]['total'] = $bids[$i][1] + $bids[$i - 1][1];
                            }
                        }

                        for ($i = 0; $i < count($asks); $i++) {  //出价  买入
                            $msg['ask'][$i]['id'] = $i;
                            $msg['ask'][$i]['price'] = $bids[$i][0];
                            $msg['ask'][$i]['quantity'] = $bids[$i][1];
                            if ($i == 0) {
                                $msg['ask'][$i]['total'] = $bids[$i][1];
                            } else {
                                $msg['ask'][$i]['total'] = $bids[$i][1] + $bids[$i - 1][1];
                            }
                        }
                        break;
                    case "trade":     //实时成交
                        $msg['type'] = "tradelog";
                        $msg['market'] = $pieces[1];  //货币对
                        $msg['id'] = $data['tick']['ts'];
                        $msg['price'] = $data['tick']['data'][0]['price'];
                        $msg['num'] = $data['tick']['data'][0]['amount'];
                        if ($data['tick']['data'][0]['direction'] == "sell") {
                            $msg['trade_type'] = 2;
                        } else {
                            $msg['trade_type'] = 1;
                        }
                        $msg['time'] = substr($data['tick']['data'][0]['ts'], 0, 10);
                        break;

                        case "detail":

                            $msg['type'] = "newprice";
                            $msg['market'] = $pieces[1];
                            $msg['new_price'] ='';
                            $msg['change'] =round(($data['tick']['open']-$data['tick']['close'])/$data['tick']['open']*1,2);
                            $msg['max_price'] =$data['tick']['high'];  //最高价
                            $msg['min_price'] =$data['tick']['low'];  //最低价
                            $msg['open'] =$data['tick']['open'];       //开盘价
                            $msg['close'] =$data['tick']['close'];     //收盘价
                            $msg['id'] =$data['tick']['id'];             //id号
                            $msg['count'] =$data['tick']['count'];      //成交笔数
                            $msg['amount'] =$data['tick']['amount'];     //成交量
                            $msg['version'] =$data['tick']['version'];   //
                            $msg['volume'] =$data['tick']['vol'];         //24H成交额
                            break;
                }
            }

            foreach($worker->connections as $conn)  //如果是websock协议的话 这里就可以这样发给客户端了
            {
                $conn->send(json_encode($msg));
            }*/
        }
        
        
    };
    $con->onClose = function($con) {
        // 如果连接断开，则在1秒后重连
        $con->reConnect(1);
    };

    // 执行异步连接
    $con->connect();
};

// 运行worker
//Worker::runAll();
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}