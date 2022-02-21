<?php


namespace app\admin\controller;

use app\common\model\Currency;
use app\common\model\CurrencyAreaOrders;
use app\common\model\GoldMainOrders;
use app\common\model\Goods;
use app\common\model\ShopPayType;
use think\Db;
use think\Request;

class  CurrencyArea extends Admin
{
    /**
     * 商品列表
     * @param Request $request
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function index(Request $request)
    {
        $count = \app\common\model\CurrencyArea::count('currency_id');
        $list = \app\common\model\CurrencyArea::with(['currency'])->order('currency_id desc')->paginate(null, $count, ['query' => $request->get()]);
        foreach ($list as &$item) {

        }
        $page = $list->render();

        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    /**
     * 添加商品
     * @param Request $request
     * @return mixed|\think\response\Json|void
     * @throws \think\exception\DbException
     */
    public function add(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();
            $path = 'currency_area';
            if ($_FILES['img']['size'] > 0) {
                $art_pic = ['img' => $_FILES['img']];
                unset($_FILES['img']);
                $upload = $this->oss_upload($art_pic, $path);
                if (empty($upload)) {
                    $this->error("上传图片失败");
                    return;
                }
                $data['img'] = trim($upload['img']);
            } else {
                $this->error("请上传图片");
                return;
            }

            // 上传轮播图
            if ($_FILES['banners']['size'] < 0){
                $this->error('请上传轮播图');
                return;
            }
            $goodsBanners = ['art_pic' => $_FILES['banners']];
            unset($_FILES['banners']);
            $up = $this->multiple_upload($goodsBanners, $path);
            if (empty($up)) {
                $this->error('图片上传失败');
                return false;
            }
            $data['postage_currency_id'] = intval($data['postage_currency_id']);
            $data['banners'] = json_encode($up);
            $data['status'] = 1;
            $data['add_time'] = time();
            $data['price'] = 1;
            $res =  \app\common\model\CurrencyArea::insertGetId($data);
            if ($res===false) {
                $this->error("系统错误添加失败");
                return;
            }

            $this->success("添加成功");
        }
        $currency = Currency::field('currency_id, currency_mark, currency_name')->select();
        return $this->fetch('add', ['currency' => $currency]);
    }

    /**
     * 修改商品
     * @param Request $request
     * @return mixed|\think\response\Json|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request)
    {
        if ($request->isPost()) {
            $currency_id = intval(input('currency_id'));

            $data = $request->post();
            $path = 'currency_area';
            if ($_FILES['img']['size'] > 0) {
                $art_pic = ['img' => $_FILES['img']];
                unset($_FILES['img']);
                $upload = $this->oss_upload($art_pic, $path);
                if (empty($upload)) {
                    $this->error("上传图片失败");
                    return;
                }
                $data['img'] = trim($upload['img']);
            }

            // 上传轮播图
            if ($_FILES['banners']['size'][0] > 0){
                $goodsBanners = ['art_pic' => $_FILES['banners']];
                unset($_FILES['banners']);
                $up = $this->multiple_upload($goodsBanners, $path);
                if (empty($up)) {
                    $this->error('图片上传失败');
                    return false;
                }
                $data['banners'] = json_encode($up);
            }
            $data['postage_currency_id'] = intval($data['postage_currency_id']);
            $data['price'] = 1;
            $res = \app\common\model\CurrencyArea::where(['currency_id'=>$currency_id])->update($data);
            if (!$res) {
                $this->error("系统错误更新失败");
                return;
            }
            $this->success("修改成功");
        }

        $goods = \app\common\model\CurrencyArea::where('currency_id', $request->get('currency_id'))->find();
        $currency = Currency::field('currency_id, currency_mark, currency_name')->select();
        return $this->fetch(null, ['goods' => $goods, 'currency' => $currency]);
    }

    /**
     * 订单列表
     * @param Request $request
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\DbException
     * Create by: Red
     * Date: 2019/10/19 15:17
     */
    function orders_list(Request $request)
    {
        $where = null;
        $get = $request->get();

        //订单状态
        if (isset($get['currency_id']) && $get['currency_id'] > 0) {
            $where['cao_currency_id'] = $get['currency_id'];
        }

        //订单状态
        if (isset($get['status']) && $get['status'] > 0) {
            $where['cao_status'] = $get['status'];
        }

        if (isset($get['user_id']) && $get['user_id'] > 0) {
            $where['cao_status'] = $get['user_id'];
        }

        if (isset($get['cao_code']) && !empty($get['cao_code'])) {
            $where['cao_code'] = $get['cao_code'];
        }

        if(isset($get['start'])&&!empty($get['start'])){
            $endtime=date("Y-m-d", time());
            if (isset($get['end'])&&!empty($get['end'])) {
                $endtime = $get['end'];
            }
            $where['cao_add_time']=array('between', array(strtotime($get['start']), strtotime($endtime) + 86400));
        }
        $list = CurrencyAreaOrders::with(['currency', 'postagecurrency'])
            ->where($where)
            ->alias('g')
            ->order('cao_id desc')
            ->join(config("database.prefix") . "member u", "u.member_id=g.cao_user_id", "LEFT")
            ->paginate(null, false, ['query' => $get]);
        $page = $list->render();
        $count = $list->total();
        $currency_area = \app\common\model\CurrencyArea::with('currency')->select();
        return $this->fetch(null, compact('list', 'page', 'count', 'get','currency_area'));
    }

    /**
     * 发货操作
     * @param Request $request
     * @return \think\response\Json
     * Create by: Red
     * Date: 2019/10/19 15:19
     */
    function deliver_goods(Request $request)
    {

        if ($request->isPost()) {
            $data = $request->post();
            $find=CurrencyAreaOrders::where(['cao_id'=>$data['cao_id']])->find();
            if($find->cao_status==2) return successJson(ERROR1,'该订单还未付款', null);
            if($find->cao_status==1) $find->cao_status=3;
            $find->cao_express_name=$data['cao_express_name'];
            $find->cao_express_code=$data['cao_express_code'];
            if (!$find->save()) {
                return successJson(ERROR1,'发货失败!', null);
            }
            return successJson(SUCCESS,'发货成功!', null);
        }
        return $this->fetch(null,['cao_id'=>$request->get('cao_id')]);
    }

    /**
     * 确认自提
     * @param Request $request
     * @return \think\response\Json
     * Create by: Red
     * Date: 2019/10/19 15:19
     */
    function success_goods(Request $request)
    {

        if ($request->isPost()) {
            $data = $request->post();
            $find=CurrencyAreaOrders::where(['cao_id'=>$data['cao_id']])->find();
            if($find->cao_status!=3 || $find->cao_self_mention!=1) return successJson(ERROR1,'该订单不能自提', null);

            $find->cao_status=4;
            $find->cao_sure_time = time();
            if (!$find->save()) {
                return successJson(ERROR1,'自提失败!', null);
            }
            return successJson(SUCCESS,'自提成功!', null);
        }
    }

    /**
     * 富文本上传图片
     * Create by: Red
     * Date: 2019/10/23 14:01
     */
    function  oss_file_upload(){
        $oss = new OssObject();
        $upload = $oss->oss_upload($file = [], $path = 'currency_area');
        if(!empty($upload['imgFile'])){
            echo json_encode(['error'=>0,'url'=>$upload['imgFile']]);
        }else{
            echo json_encode(['error'=>0,'message'=>'上传失败']);
        }
        exit;
    }
}