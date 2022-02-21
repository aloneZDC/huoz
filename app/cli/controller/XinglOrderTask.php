<?php


namespace app\cli\controller;

use think\Log;
use think\Db;
use Workerman\Worker;

class XinglOrderTask
{
    protected $name = '星链下单定时任务';
    protected $mining_config = [];

    public function index() {
        $this->worker = new Worker();
        $this->worker->count = 1;// 设置进程数
        $this->worker->name = 'XinglOrderTask';
        $this->worker->onWorkerStart = function($worker) {
            while (true){
                $this->doRun($worker->id);
            }
        };
        Worker::runAll();
    }

    /**
     * 每分钟执行一次
     */
    protected function doRun($worker_id=0){
        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);

        //自动下单
        $this->automatic_order();
    }

    //自动下单
    public function automatic_order() {
        $res = \app\common\model\GoodsMainOrders::where(['is_upload' => 1, 'gmo_status' => ['in', [1,3,4]]])->group('gmo_id')->select();
        if (!$res) {
            return;
        }

        $OrderApi = new \xingl\OrderApi();
        foreach ($res as $key => $value) {
            $username = \app\common\model\Member::where(['member_id' => $value['gmo_user_id']])->value('ename');
            $str = $value['gmo_address'];//收货地址
            $state_arr = Db::name('areas')->where(['area_type' => 1])->column('area_name');//省数组
            $city_arr = Db::name('areas')->where(['area_type' => 2])->column('area_name');//市数组
            $district_arr = Db::name('areas')->where(['area_type' => 3])->column('area_name');//区数组
            $receiver_state = $OrderApi->get_address($state_arr, $str);//省
            $receiver_city = $OrderApi->get_address($city_arr, $str);//市
            $receiver_district = $OrderApi->get_address($district_arr, $str);//区、城镇
            $address_name = mb_substr($str, mb_strlen($receiver_state . $receiver_city . $receiver_district));//详细地址
            $state_code = Db::name('areas')->where(['area_type' => 1, 'area_name' => $receiver_state])->value('area_id');//省code
            $city_code = Db::name('areas')->where(['area_type' => 2, 'area_name' => $receiver_city])->value('area_id');//市code
            $district_code = Db::name('areas')->where(['area_type' => 3, 'area_name' => $receiver_district])->value('area_id');//区code
            $order_arr = \app\common\model\GoodsOrders::alias('a')->join('goods_format b', 'a.go_format_id=b.id')->where(['a.go_main_id' => $value['gmo_id']])->field('a.go_num as goodsQty,b.sku_id as skuId')->select();

            $params = [
                'orderSource' => 1,
                'goodsList' => $order_arr,
                'buyerName' => $username,
                'shipArea' => $receiver_state . ',' . $receiver_city . ',' . $receiver_district,
                'shipName' => $value['gmo_receive_name'],
                'shipAddress' => $address_name,
                'shipMobile' => $value['gmo_mobile'],
                'outOrderSn' => $value['gmo_code'],
                'shipAreaCode' => $state_code . ',' . $city_code . ',' . $district_code,
            ];
            //Log::write(json_encode($params));
            $result = $OrderApi->submitOrder($params);
            if (empty($result)) {
                Log::write('星链自动下单失败');
            }
            if (!empty($result) && $result['status'] != 200) {
                Log::write('星链自动下单失败：' . $result['msg']);
            }
            if (!empty($result['data']['orderList'])) {
                $orderList = $result['data']['orderList'];
                foreach ($orderList as $k => $v) {
                    $data = [
                        'member_id' => $value['gmo_user_id'],
                        'gmo_id' => $value['gmo_id'],
                        'totalAmount' => $v['totalAmount'],
                        'payAmount' => $v['payAmount'],
                        'orderSn' => $v['orderSn'],
                        'batchOrderSn' => $v['batchOrderSn'],
                        'orderStatus' => $v['orderStatus'],
                        'add_time' => time()
                    ];

                    $flag = \app\common\model\GoodsXingl::insertGetId($data);
                    if ($flag === false) {
                        Log::write('添加星链下单记录失败：' . $value['gmo_id']);
                    }
                }
            }

            $flag = \app\common\model\GoodsMainOrders::where(['gmo_id' => $value['gmo_id'], 'is_upload' => 1])->update(['is_upload' => 2]);
            if ($flag === false) {
                Log::write('更新星链上传状态失败：' . $value['gmo_id']);
            }

            sleep(3);
        }
    }
}