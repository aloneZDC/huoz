<?php

namespace app\cli\controller;

use app\common\model\ChiaMiningConfig;
use app\common\model\Config;
use app\common\model\Currency;
use app\common\model\CurrencyPriceTemp;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

/**
 * 传统矿机价格更新
 */
class CommonMiningCurrencyPrice
{
    public function index()
    {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'CommonMiningCurrencyPrice';
        $this->worker->onWorkerStart = function ($worker) {
            while (true) {
                $this->doRun($worker->id);
                sleep(60);
            }
        };
        Worker::runAll();
    }

    protected function doRun($worker_id = 0)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);
        Log::write("CommonMiningCurrencyPrice start");

        $usdt_price = CurrencyPriceTemp::get_price_currency_id(Currency::USDT_ID, 'CNY');

        $list = Db::name('common_mining_currency_price')->where(['status' => 1])->select();
        foreach ($list as $item) {
            $price = 0;
            if ($item['from'] == 'huobi') {
                $price = $this->getFromHuobi($item['mining_currency_name']);
                if ($price <= 0) {
                    $price = $this->getFromHuobi3($item['mining_currency_name']);
                }
            } elseif ($item['from'] == 'ok') {
                $price = $this->getFromOk($item['mining_currency_name']);
            } elseif ($item['from'] == 'zb') {
                $price = $this->getFromZb($item['mining_currency_name']);
            } elseif ($item['from'] == 'binance') {
                $price = $this->getFromBinance($item['mining_currency_name']);
            }

            if ($price > 0) {
                $cny = keepPoint($usdt_price * $price, 2);
                $price = keepPoint($price, 2);
                Db::name('common_mining_currency_price')->where('id', $item['id'])->update([
                    'usdt' => $price,
                    'cny' => $cny,
                    'update_time' => time(),
                ]);
            }
        }

//        $this->getEthBlock();
//        $this->getBtcBlock();
//        $this->getChiaBlock();
        $this->getFilBlock();
//        $this->getTop10Avg();
    }

    // 获取区块信息（ETH）
    public function getEthBlock()
    {
        $url = 'https://explorer-web.api.btc.com/v1/eth/stats/summary';
        $result = $this->getFromUrl($url);
        if ($result === false) return;

        $result = json_decode($result, true);
        if (empty($result['data'])) return;
        $allow_field = [
            'eth_whole_num' => [
                'value' => sprintf("%.2f", $result['data']['hashrate'] / 1000),
                'unit' => ' TH/s',
            ],
            'eth_hour24_num' => [
                'value' => sprintf("%.6f", $result['data']['earning_eth']),
                'unit' => ' ETH',
            ],
        ];
        $block = [
            "url" => 'https://eth.tokenview.com/',
            'fields' => [],
        ];
        foreach ($allow_field as $field => $value) {
            $block["fields"][$field] = [
                'name' => $field,
                'value' => $value['value'] . $value['unit'],
                'number' => $value['value'],
            ];
        }
        cache('eth_block_info', $block);
    }

    // 获取区块信息（BTC）
    public function getBtcBlock()
    {
        $url = 'https://btc.com/';
        $result = $this->getFromUrl($url);
        if ($result === false) return;

        $result = strip_tags($result);
        $allow_field = [
            'btc_whole_num' => [
                'regular' => '/Hashrate\s+([0-9,.]+) EH/',
                'unit' => ' EH/s',
            ],
            'btc_hour24_num' => [
                'regular' => '/Mining Earnings\s+PPS\s+[1T * 24H = ]+([0-9,.]+) BTC/',
                'unit' => '',
            ],
        ];

        $block = [
            "url" => 'https://btc.tokenview.com/',
            'fields' => [],
        ];

        foreach ($allow_field as $field => $value) {
            $block["fields"][$field] = [
                'name' => $field,
                'value' => '',
                'number' => 0,
            ];
            $matches = [];
            preg_match_all($value['regular'], $result, $matches);
            if (isset($matches[1]) && is_array($matches[1]) && isset($matches[1][0])) {
                $block["fields"][$field]['value'] = $matches[1][0] . $value['unit'];
                $block["fields"][$field]['number'] = $matches[1][0];
            }
        }
        cache('btc_block_info', $block);
    }

    // 获取区块信息（Chia）
    protected function getChiaBlock()
    {
        $chia_config = ChiaMiningConfig::get_key_value();
        if ($chia_config['chia_open'] == 1) {
            $chia_total_power = $chia_config['chia_total_power'];
            $res = [
                'addressCount' => $chia_config['chia_total_miner'],
                'xchPerDay' => $chia_config['chia_hour24_avg'],
                'uniqueCoins' => $chia_config['chia_hour24_num'],
            ];
        } else {
            $url = 'http://testhxys.hengxunyun.com/tbr/getChiaData';
            $result = $this->getFromUrl($url);
            if ($result === false) return;
            $result = json_decode($result, true);
            if (empty($result['data'])) return;
            $res = [
                'addressCount' => !empty($result['data']['addressCount']) ? $result['data']['addressCount'] : 0,
                'xchPerDay' => !empty($result['data']['coffOfDay']) ? sprintf('%.6f', $result['data']['coffOfDay']) : 0,
                'uniqueCoins' => !empty($result['data']['uniqueCoins']) ? $result['data']['uniqueCoins'] : 0,
            ];
            $netspace = !empty($result['data']['netspace']) ? $result['data']['netspace'] : 0;
            $chia_total_power = sprintf('%.2f', $netspace / 1125899906842624 / 1000);//除以1024的五次方，再除以1000，把PiB转换成EiB
        }

        $allow_field = [
            'chia_total_power' => [
                'value' => $chia_total_power,
                'unit' => ' EiB',
            ],
            'chia_total_miner' => [
                'value' => $res['addressCount'],
                'unit' => '',
            ],
            'chia_hour24_avg' => [
                'value' => $res['xchPerDay'],
                'unit' => ' XCH/TiB',
            ],
            'chia_hour24_num' => [
                'value' => $res['uniqueCoins'],
                'unit' => '',
            ],
        ];
        $block = [
            "url" => 'https://www.chiaexplorer.com/',
            'fields' => [],
        ];
        foreach ($allow_field as $field => $value) {
            $block["fields"][$field] = [
                'name' => $field,
                'value' => $value['value'] . $value['unit'],
                'number' => $value['value'],
            ];
        }
        cache('chia_block_info', $block);
    }

    // 获取区块信息（FIL）
    protected function getFilBlock()
    {
        $url = 'https://filfox.info/en';
        $result = $this->getFromUrl($url);
        if ($result === false) return;

        $result = strip_tags($result);
        $allow_field = [
            'total_power' => [
                'regular' => '/the network.\s+([0-9,.]+)\s+EiB/',
                'unit' => ' EiB',
            ],
            'total_miner' => [
                'regular' => '/positive storage power.\s+([0-9,.]+)/',
                'unit' => '',
            ],
            'hour24_avg' => [
                'regular' => '/adjusted storage power.\s+([0-9,.]+)\sFIL\/TiB/',
                'unit' => ' FIL/TiB',
            ],
            'hour24_num' => [
                'regular' => '/minted in last 24h.\s+([0-9,.]+)\s+FIL/',
                'unit' => ' FIL',
            ],
        ];

        $block = [
            "url" => $url,
            'fields' => [],
        ];
        foreach ($allow_field as $field => $value) {
            $block["fields"][$field] = [
                'name' => $field,
                'value' => '',
                'number' => 0,
            ];
            $matches = [];
            preg_match_all($value['regular'], $result, $matches);
            if (isset($matches[1]) && is_array($matches[1]) && isset($matches[1][0])) {
                $number = sprintf('%.6f', $matches[1][0]);
                if ($field == 'hour24_avg') {
                    $hour24_avg_ratio = Config::get_value('ipfs_hour24_avg_ratio', 1);
                    $number = sprintf('%.6f', $number * $hour24_avg_ratio);
                }

                $block["fields"][$field]['value'] = $number . $value['unit'];
                $block["fields"][$field]['number'] = $number;
            }
        }
        cache('fil_block_info', $block);
    }

    // 获取前10名均值
    protected function getTop10Avg()
    {
        $url = 'https://filfox.info/zh/ranks/power';
        $result = $this->getFromUrl($url);
        if ($result === false) return;

        $matches = [];
        preg_match_all('/24h挖矿效率:\s+([0-9.]*)\s+FIL\/TiB/', $result, $matches);
        if (isset($matches[1]) && is_array($matches[1]) && count($matches[1]) > 10) {
            $total_num = 0;
            foreach ($matches[1] as $key => $item) {
                if (!is_numeric($item)) {
                    return 0;
                }

                if ($key < 10) {
                    $total_num += $item;
                }
            }
            $top10_avg = keepPoint($total_num / 10, 6);

            cache('fil_top10_avg', $top10_avg);
            return $top10_avg;
        }
    }

    // 从火币获取价格
    protected function getFromHuobi($ticker)
    {
        $url = "https://api.huobi.pro/market/detail?symbol=" . strtoupper($ticker) . "USDT";
        $result = $this->getFromUrl($url);
        if ($result === false) return 0;

        $res = json_decode($result, true);
        if (is_array($res) && isset($res['tick']) && isset($res['tick']['close']) && is_numeric($res['tick']['close'])) {
            return $res['tick']['close'];
        }
        return 0;
    }

    // 从火币获取价格
    protected function getFromHuobi2($ticker)
    {
        $url = "http://api.coindog.com/api/v1/tick/HUOBIPRO:" . strtoupper($ticker) . "USDT" . "?unit=base";
        $result = $this->getFromUrl($url);
        if ($result === false) return 0;

        $res = json_decode($result, true);
        if (is_array($res) && isset($res['close']) && is_numeric($res['close'])) {
            return $res['close'];
        }
        return 0;
    }

    // 从火币获取价格
    protected function getFromHuobi3($ticker)
    {
        $url = "https://api.huobi.bs/market/history/kline?period=1min&size=1&symbol=" . strtolower($ticker) . "usdt";
        $result = $this->getFromUrl($url);
        if ($result === false) return 0;

        $res = json_decode($result, true);
        if (is_array($res) && isset($res['data'][0]) && isset($res['data'][0]['close']) && is_numeric($res['data'][0]['close'])) {
            return $res['data'][0]['close'];
        }
        return 0;
    }

    // 从OK获取价格
    protected function getFromOk($ticker)
    {
        $url = 'https://www.okexcn.com/v2/spot/markets/ticker?symbol=' . $ticker . '_usdt&t=' . (time() * 1000);
        $result = $this->getFromUrl($url);
        if ($result === false) return 0;

        $json = json_decode($result, true);
        if (is_array($json) && isset($json['data']) && isset($json['data']['close']) && is_numeric($json['data']['close'])) {
            return $json['data']['close'];
        }
        return 0;
    }

    // 从中币获取价格
    protected function getFromZb($ticker)
    {
        $url = "https://trans.zb.com/api/web/market/V1_0_0/getGroupTicker?market=" . strtolower($ticker) . "usdt";
        $result = $this->getFromUrl($url);
        if ($result === false) return 0;

        $json = json_decode($result, true);
        if (is_array($json) && isset($json['datas']) && isset($json['datas']['tickers']) && isset($json['datas']['tickers'][2])) {
            if (is_numeric($json['datas']['tickers'][2])) {
                return $json['datas']['tickers'][2];
            }
        }
        return 0;
    }

    // 从币安获取价格
    protected function getFromBinance($ticker)
    {
        $url = "https://www.binance.com/api/v3/ticker/price?symbol=" . strtoupper($ticker) . "USDT";
        $result = $this->getFromUrl($url);
        if ($result === false) return 0;

        $json = json_decode($result, true);
        if (is_array($json) && isset($json['price'])) {
            if (is_numeric($json['price'])) {
                return $json['price'];
            }
        }
        return 0;
    }

    protected function getFromUrl($url, $method = 'GET')
    {
        try {
            $opts = array(
                'http' => array(
                    'method' => $method,
                    'timeout' => 5,//单位秒
                )
            );
            $result = file_get_contents($url, false, stream_context_create($opts));
            if (empty($result)) return false;

            return $result;
        } catch (Exception $e) {
            Log::write("CommonMiningCurrencyPrice error:" . $e->getMessage());
        }
        return false;
    }
}
