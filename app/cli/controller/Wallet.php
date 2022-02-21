<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/14
 * Time: 14:06
 */

namespace app\cli\controller;




use app\common\model\CurrencyAddressEth;
use message\Eth;
use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\Log;

class Wallet extends Command
{
    protected function configure(){
        $this->setName('Wallet')->setDescription('This is a test');
    }

    protected function execute(Input $input, Output $output){
        \think\Request::instance()->module('cli');

        $this->createAddress();
    }

    /**
     * 创建ETH的地址
     * Created by Red.
     * Date: 2018/12/14 17:07
     */
    protected function createAddress(){
        $kk=100000;
        for ($i=0;$i<$kk;$i++){
            $eth=new Eth();
            $result= $eth->personal_newAccount();
            if($result['code']==SUCCESS){
                $address=isset($result['result']['result'])?$result['result']['result']:null;
                if(!empty($address)){
                    $cae=new CurrencyAddressEth();
                    $data['cae_address']=$address;
                    $data['cae_time']=time();
                    $data['cae_is_use']=1;
                    $r=$cae->data($data)->save();
                    if($r){
                        var_dump($cae->cae_id);
                    }else{
                        Log::write('时间：' . date("Y-m-d H:i:s")."  错误:保存失败", 'INFO');
                    }
                }else{
                    Log::write('时间：' . date("Y-m-d H:i:s")."  没有地址:".json_encode($result), 'INFO');
                }
            }else{
                Log::write('时间：' . date("Y-m-d H:i:s")."  错误:".json_encode($result), 'INFO');
            }
        }
    }
}