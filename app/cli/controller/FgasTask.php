<?php

namespace app\cli\controller;

use app\common\model\Config;
use think\console\Command;
use think\Db;
use think\console\Input;
use think\console\Output;

class FgasTask extends Command
{
    protected function configure()
    {
        $this->setName('FgasTask')->setDescription('FgasTask');
    }

    protected function execute(Input $input, Output $output)
    {
        $url = 'https://api.fgas.io/api/v1/fil?type=32G';
        $data = ['type' => '32G'];
        $result_gas = $this->_curl($url, $data, true, 'GET');
        $result = json_decode($result_gas, true);
        $result = !empty($result['result'][0]) ? $result['result'][0] : [];

        if (!empty($result)
            && $result['preGas_1T'] > 0
            && $result['payment_1T'] > 0
        ) {
            $gas_data = [
                'sector_type' => $result['sector_type'],
                'preGas_1T' => $result['preGas_1T'],
                'payment_1T' => $result['payment_1T'],
                'total_1T' => $result['total_1T'],
                'time' => time(),
            ];
            Db::name('fgas_task')->insert($gas_data);
        }
    }

    // 获取平均价格
    public static function average_out()
    {
        $total_fgas_average = Config::get_value('total_fgas_average', '');
        $fgas_average = explode(',', $total_fgas_average);
        $start_time = strtotime(date('Y-m-d ' . $fgas_average[0] . ':00:00')) - 86400;
        $start_end = strtotime(date('Y-m-d ' . $fgas_average[1] . ':00:00')) - 86400;

        $average = Db::name('fgas_task')
            ->whereBetween('time', [$start_time, $start_end])
            ->field(['avg(preGas_1T)' => 'preGas_1T', 'avg(payment_1T)' => 'payment_1T'])
            ->find();
        if (!$average || !$average['payment_1T']) {
            return 0;
        }

        return keepPoint($average['payment_1T'] + $average['preGas_1T'] / 2, 6);
    }

    private function _curl($url, $data = null, $json = false, $method = 'POST', $timeout = 30)
    {
        $ssl = substr($url, 0, 8) == "https://" ? TRUE : FALSE;
        $ch = curl_init();
        $fields = $data;
        $headers = array();
        if ($json && is_array($data)) {
            $fields = json_encode($data);
            $headers = array(
                "Content-Type: application/json;charset=utf-8",
                'Content-Length: ' . strlen($fields),
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36',
            );
        }

        $opt = array(
            CURLOPT_URL => $url,
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
        );

        if ($ssl) {
            $opt[CURLOPT_SSL_VERIFYHOST] = 2;
            $opt[CURLOPT_SSL_VERIFYPEER] = FALSE;
        }
        curl_setopt_array($ch, $opt);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}
