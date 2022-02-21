<?php
namespace app\cli\controller;
use app\common\model\Currency;
use app\common\model\CurrencyUserTransfer;
use app\common\model\Tibi;
use Workerman\Worker;
use think\Log;
use think\Db;
use think\Exception;

/**
 * 提币自动一审
 * 出金小于入金的自动审核通过
 */
class TibiAutoAudit
{
    public $name = "提币自动一审：";
    public $config=[];

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'TibiAutoAuditWorker';
        $this->worker->onWorkerStart = function($worker) {
            while (true){
                try{
                    $this->doRun($worker->id);
                } catch (Exception $exception) {
                    Log::write($this->name.$exception->getMessage());

                }
            }
        };
        Worker::runAll();
    }

    //异常监控
    protected function doRun($worker_id=0){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        Log::write($this->name."开始:".date('Y-m-d H:i:s'), 'INFO');
        $listen_currency_id = Currency::ERC20_ID;
        $last_id = 0;
        while (true) {
            $tibi_info = Tibi::where([
                'id' => ['gt',$last_id],
                'currency_id' => $listen_currency_id,
                'transfer_type' => '1',
                'status' => -1,
            ])->find();
            if(empty($tibi_info)) {
                break;
            }
            $last_id = $tibi_info['id'];

            if(intval($tibi_info['admin_id1'])>0) {
                continue;
            }

            //已经区块充币成功的数量
            $chongbi_sum = Tibi::where([
                'to_member_id' => $tibi_info['from_member_id'],
                'currency_id' => $listen_currency_id,
                'transfer_type' => '1',
                'status' => 3,
            ])->sum('num');

            //区块提币成功的数量
            $tibi_sum = Tibi::where([
                'from_member_id' => $tibi_info['from_member_id'],
                'currency_id' => $listen_currency_id,
                'transfer_type' => '1',
                'status' => 1,
            ])->sum('num');

            //内转转出数量
            $tibi_transfer_out_sum = Tibi::where([
                'from_member_id' => $tibi_info['from_member_id'],
                'currency_id' => $listen_currency_id,
                'transfer_type' => '2',
                'status' => 1,
            ])->sum('num');

            //内转转入数量
            $tibi_transfer_in_sum = Tibi::where([
                'to_member_id' => $tibi_info['from_member_id'],
                'currency_id' => $listen_currency_id,
                'transfer_type' => '2',
                'status' => 1,
            ])->sum('num');

            //互转转出
            $transfer_out_sum = CurrencyUserTransfer::where([
                'cut_user_id' => $tibi_info['from_member_id'],
                'cut_currency_id' => $listen_currency_id,
            ])->sum('cut_num');

            //互转转入
            $transfer_in_sum = CurrencyUserTransfer::where([
                'cut_target_user_id' => $tibi_info['from_member_id'],
                'cut_currency_id' => $listen_currency_id,
            ])->sum('cut_num');

            $chongzhi = $chongbi_sum + $tibi_transfer_in_sum + $transfer_in_sum;
            $tibi = $tibi_sum + $tibi_transfer_out_sum + $transfer_out_sum + $tibi_info['num'];
            if($tibi>$chongzhi) {
                $msg = '审核不通过: '.keepPoint($chongzhi-$tibi,6);
            } else {
                $msg = '审核通过: '.keepPoint($chongzhi-$tibi,6);
            }
            Tibi::where(['id'=>$tibi_info['id']])->update([
                'message1' => $msg,
                'admin_id1' => 1
            ]);
            sleep(2);
        }
        Log::write($this->name."结束:".date('Y-m-d H:i:s'), 'INFO');
        sleep(10);
    }
}
