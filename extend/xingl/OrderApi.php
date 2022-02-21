<?php
namespace xingl;

use think\Db;
use think\Exception;
use app\common\model\Config;

//对接星链Api
class OrderApi
{
    private $appKey = '1455471902867853313';
    private $appSecret = '5361b38338b494a3e997dd667e21638f';
    private $proUrl = 'https://sce-opz.380star.com';//正式
    private $client = null;

    public function __construct() {
        $cfg =array(
            'debug_mode'=>false, //是否输出日志
            'appKey' => $this->appKey,
            'appSecret' => $this->appSecret,
            'pro_url' => $this->proUrl,
        );

        $this->client = new RpcClient($cfg, time());
    }

    /**
     * 查询规格商品价格
     * @param array $params 参数
     * @return array
     */
    public function findSkuSalePrice($params=null) {
        $url = "/scce/cmc/cmc/spu/open/goods/findSkuSalePrice";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 查询SPU商品详情
     * @param array $params 参数
     * @return array
     */
    public function getSpuBySpuIds($params) {
        $url = "/scce/cmc/cmc/spu/open/getSpuBySpuIds";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 查询SKU规格信息
     * @param array $params 参数
     * @return array
     */
    public function listSkuBySpuId($params) {
        $url = "/scce/cmc/cmc/spu/open/listSkuBySpuId";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 查询商品库存
     * @param array $params 参数
     * @return array
     */
    public function findSkuStock($params) {
        $url = "/scce/cmc/cmc/spu/open/findSkuStock";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 下单
     * @param array $params 参数
     * @return array
     */
    public function submitOrder($params) {
        $url = "/scce/ctc/ctc/reseller/order/submitOrder";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 查询订单详情
     * @param array $params 参数
     * @return array
     */
    public function getOrderDetail($params) {
        $url = "/scce/ctc/ctc/reseller/order/getOrderDetail";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 查询物流信息
     * @param array $params 参数
     * @return array
     */
    public function getOrderExpressListByOs($params) {
        $url = "/scce/ctc/ctc/tOrderExpress/getOrderExpressListByOs";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 确认收货
     * @param array $params 参数
     * @return array
     */
    public function confirmReceive($params) {
        $url = "/scce/ctc/ctc/reseller/order/confirmReceive";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 取消订单
     * @param array $params 参数
     * @return array
     */
    public function cancelOrder($params) {
        $url = "/scce/ctc/ctc/reseller/order/cancelOrder";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 换货确认收货
     * @param array $params 参数
     * @return array
     */
    public function receiptRefund($params) {
        $url = "/scce/ctc/ctc/reseller/orderReturnGoods/receiptRefund";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 退货邮寄卖家
     * @param array $params 参数
     * @return array
     */
    public function sendReturnGoods($params) {
        $url = "/scce/ctc/ctc/reseller/orderReturnGoods/sendReturnGoods";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 查询商品库SPUID
     * @param array $params 参数
     * @return array
     */
    public function getSpuIdList($params) {
        $url = "/scce/cmc/cmc/spu/open/getSpuIdList";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 省市区地址查询
     * @param array $params 参数
     * @return array
     */
    public function getRegionByCodeOpen($params) {
        $url = "/scce/pbc/pbc/region/getRegionByCodeOpen";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 售后申请
     * @param array $params 参数
     * @return array
     */
    public function applyRefund($params) {
        $url = "/scce/ctc/ctc/reseller/orderReturnGoods/applyRefund";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 取消售后申请
     * @param array $params 参数
     * @return array
     */
    public function cancelRefund($params) {
        $url = "/scce/ctc/ctc/reseller/orderReturnGoods/cancelRefund";
        if($params == null) $params = (object)array();
        return $this->client->call($url, $params);
    }

    /**
     * 获取地址省市区
     * @param array $params 数据（省市区）
     * @param string $string 地址
     * @return string
     */
    public function get_address($data, $string) {
        foreach ($data as $key => $value) {
            if (strstr($string, $value)) {
                return $value;
            }
        }
        return false;
    }

    /**
     * 组合物流信息
     * @param array $data 数据（物流信息）
     * @return array
     */
    public function deliveryUpdateVOList($data) {
        $result = [];
        if ($data) {
            foreach ($data as $key => $value) {
                $result[$key]['time'] = $value['time'];
                $result[$key]['context'] = $value['context'];
                $result[$key]['ftime'] = '';
                $result[$key]['areaCode'] = '';
                $result[$key]['areaName'] = '';
                $result[$key]['status'] = $value['title'];
            }
        }
        return ['data' => $result];
    }
}