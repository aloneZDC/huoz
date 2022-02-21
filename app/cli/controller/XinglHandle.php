<?php


namespace app\cli\controller;

use think\Log;
use think\Db;
use think\console\Input;
use think\console\Output;
use think\console\Command;

class XinglHandle extends Command
{
    protected $name = '处理星链定时任务';
    protected $today_config = [];
    protected $mining_config = [];

    protected $all_levels = [];

    protected function configure()
    {
        $this->setName('XinglHandle')->setDescription('This is a XinglHandle');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);
        \think\Request::instance()->module('cli');

        $this->doRun();
    }

    public function doRun($today = '')
    {
        if (empty($today)) $today = date('Y-m-d');
        $today_start = strtotime($today);
        $this->today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'today_end' => $today_start + 86399,
            'yestday_start' => $today_start - 86400,
            'yestday_stop' => $today_start - 1,
        ];

        //更新订单信息和物流信息
        $this->update_order();

        //更新商品规格库存
        $this->update_goods();

        //更新商品状态
        $this->getGoodsStatus();
    }

    //更新订单信息和物流信息
    public function update_order() {
        Log::write('更新订单信息和物流信息 start');

        $res = \app\common\model\GoodsMainOrders::where(['is_upload' => 2, 'gmo_status' => ['in', [1,3]]])->select();
        if (!$res) {
            Log::write('无需要更新订单信息和物流信息');
            return;
        }

        $OrderApi = new \xingl\OrderApi();
        foreach ($res as $key => $value) {
            $orderSn = \app\common\model\GoodsXingl::where(['member_id' => $value['gmo_user_id'], 'gmo_id' => $value['gmo_id']])->value('orderSn');
            if (!$orderSn) {
                continue;
            }
            $status = 1;
            if ($value['gmo_status'] == 1) {//待发货
                $params = ['orderSn' => $orderSn];
                $result = $OrderApi->getOrderDetail($params);
                if (empty($result)) {
                    Log::write('星链获取订单信息失败');
                    continue;
                }
                if (!empty($result) && $result['status'] != 200) {
                    Log::write('星链获取订单信息失败' . $result['msg']);
                    continue;
                }
                $data = $result['data'];
                $status = 1;
                //订单状态,10为已提交待付款,20为已付款待发,30为已发货待收货,40为确认收货交易成功,50取消订单,100已完成可结算
                if (!empty($data['orderStatus']) && in_array($data['orderStatus'], [20, 30, 40, 100])) {
                    $auto_sure_time = \app\common\model\ShopConfig::get_value('auto_sure_time');
                    if (empty($data['deliverList'][0])) {
                        continue;
                    }
                    $deliverList = $data['deliverList'][0];
                    if ($deliverList) {
                        $sendTime = strtotime($deliverList['sendTime']);
                        $u_data = [
                            'gmo_status' => 3,
                            'gmo_express_name' => $deliverList['expressName'],
                            'gmo_express_company' => $deliverList['deliveryCorpSn'],
                            'gmo_express_code' => $deliverList['deliverySn'],
                            'gmo_ship_time' => $sendTime,
                            'gmo_auto_sure_time' => $sendTime + ($auto_sure_time * 86400),//自动确认收货时间
                        ];
                        $flag = \app\common\model\GoodsMainOrders::where(['gmo_id' => $value['gmo_id']])->update($u_data);
                        if ($flag === false) {
                            Log::write('更新发货信息失败' . $value['gmo_id']);
                        }
                        $status = 2;
                    }
                    //更新星链订单详情
                    $flag = \app\common\model\GoodsXingl::where(['member_id' => $value['gmo_user_id'], 'gmo_id' => $value['gmo_id']])->update(['orderStatus' => $data['orderStatus']]);
                    if ($flag === false) {
                        Log::write('更新订单状态失败');
                    }
                }
            } else {//已发货
                $status = 2;
            }

            if ($status == 2) {//查询物流信息
                $deliverySn = \app\common\model\GoodsMainOrders::where(['gmo_id' => $value['gmo_id']])->value('gmo_express_code');
                $params = ['orderSn' => $orderSn, 'deliverySn' => $deliverySn];
                $e_result = $OrderApi->getOrderExpressListByOs($params);
                if (empty($e_result)) {
                    Log::write('星链获取物流信息失败');
                    continue;
                }
                if (!empty($e_result) && $e_result['status'] != 200) {
                    Log::write('星链获取物流信息失败' . $e_result['msg']);
                    continue;
                }
                if (empty($e_result['data'][0])) {
                    continue;
                }
                $list = $e_result['data'][0];
                $last_result = $OrderApi->deliveryUpdateVOList($list['deliveryUpdateVOList']);
                $e_data = [
                    'company' => $list['deliveryCorpSn'],
                    'company_name' => $list['expressName'],
                    'number' => $list['deliverySn'],
                    'status' => 'polling',//监控状态:polling:监控中，shutdown:结束
                    'last_result' => json_encode($last_result),
                    'update_time' => time()
                ];
                $check = \app\common\model\ShopLogisticsList::where(['number' => $list['deliverySn']])->find();
                if ($check) {
                    $flag = \app\common\model\ShopLogisticsList::where(['id' => $check['id']])->update($e_data);
                    if ($flag === false) {
                        Log::write('更新物流信息失败');
                    }
                } else {
                    $e_data['add_time'] = time();
                    $flag = \app\common\model\ShopLogisticsList::insertGetId($e_data);
                    if ($flag === false) {
                        Log::write('添加物流信息失败');
                    }
                }
            }
            sleep(1);
        }
        Log::write('更新订单信息和物流信息 end');
    }

    //更新规格库存
    public function update_goods() {
        Log::write('更新商品规格库存 start');

        $res = \app\common\model\GoodsFormat::whereNotNull('sku_id')->column('sku_id');
        if (!$res) {
            Log::write('无需要更新商品规格库存');
            return;
        }

        $OrderApi = new \xingl\OrderApi();
        $params = ['skuIdList' => $res];
        $result = $OrderApi->findSkuStock($params);
        if (empty($result)) {
            Log::write('星链获取查询商品库存失败');
            return;
        }
        if (!empty($result) && $result['status'] != 200) {
            Log::write('星链获取查询商品库存失败' . $result['msg']);
            return;
        }
        $data = $result['data'];
        if ($data) {
            foreach ($data as $key => $value) {
                $check = \app\common\model\GoodsFormat::where(['sku_id' => $value['skuId']])->find();
                if ($check) {
                    $flag = \app\common\model\GoodsFormat::where(['id' => $check['id']])->update(['goods_stock' => $value['inventory']]);
                    if ($flag === false) {
                        Log::write('更新商品规格库存失败：' . $value['skuId']);
                    }
                }
            }
        }

        Log::write('更新商品规格库存 end');
    }

    public function getGoodsStatus() {
        $list = \app\common\model\Goods::where(['goods_type' => 2, 'goods_status' => 1])->column('spu_id');
        if (!$list) {
            Log::write('无需要更新商品状态');
            return;
        }
        $OrderApi = new \xingl\OrderApi();
        foreach ($list as $v) {
            $sp_params = ['spuIdList' => [$v]];
            $sp_result = $OrderApi->getSpuBySpuIds($sp_params);
            if (empty($sp_result)) {
                Log::write('星链获取商品信息失败');
                continue;
            }
            if (!empty($sp_result) && $sp_result['status'] != 200) {
                Log::write('星链获取商品信息失败' . $sp_result['msg']);
                continue;
            }
            $data = $sp_result['data'];
            if ($data) {
                foreach ($data as $key => $value) {
                    $goods= \app\common\model\Goods::where(['spu_id' => $value['spuId']])->find();
                    if ($goods && $value['status'] == 1) {//goods_status
                        $flag = \app\common\model\Goods::where(['goods_id' => $goods['goods_id']])->update(['goods_status' => 2]);
                        if ($flag === false) {
                            Log::write('更新商品规格库存失败：' . $value['spuId']);
                        }
                    }
                }
            }
        }
    }
}