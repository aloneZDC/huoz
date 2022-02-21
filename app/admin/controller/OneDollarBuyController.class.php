<?php

namespace Admin\Controller;

use Admin\Controller\AdminController;

class OneDollarBuyController extends AdminController
{
    public function _initialize()
    {
        parent::_initialize();
        $this->currency = M('Currency');
        $this->activity = M('yy_oneshop_activity');
    }

    //空操作
    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    //活动展示页
    public function index()
    {
        $one_id = intval(I('one_id'));            //用于搜索
        if (!empty($one_id)) {
            $where['one_id'] = $one_id;
        }
        $count = $this->activity->where($where)->count();
        $Page = new \Think\Page($count, 12);
        $show = $Page->show();
        $list = $this->activity->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->order('add_time desc')->select();
        $this->assign('list', $list);
        $this->assign('empty', '暂无数据');
        $this->assign('page', $show);
        $this->display();
    }

    //活动添加
    public function add()
    {
        $currency = $this->currency->field('currency_id,currency_name')->where(' currency_id<>25 and currency_id<>26 and currency_id<>28 ')->select();    //积分类型不选择比特积分、莱特积分、狗狗积分
        if (IS_POST) {
            $screen = implode(',', $_POST['screen']);        //将传过来的复选框数组用php方法转化成字符串
            if (!empty($_POST['sub'])) {
                $keyname = $_POST['keyname'];
                if (!empty($keyname)) {
                    $where['goods_name'] = array('like', '%' . $keyname . '%');
                    $where['oneupdown'] = 0;
                }
                $key = M('yy_goods')->field('goods_id,goods_name')->where($where)->select();
                $this->assign("ware", $key);
            } else {
                //$goods_name=$_POST['goods_name'];
                foreach ($_POST as $k => $v) {
                    $data[$k] = $v;
                }
                $data['add_time'] = time();
                $data['screen'] = $screen;                //筛选
                array_shift($data);
//                debug($data);
                /*if(!empty($goods_name)){
                   $where['goods_id']=$goods_name;
                   $data['goods_name']=M('yy_goods')->field('goods_name')->where($where)->select();
               } */
                $goodsId = $data['goods_id'];    //post传过来的商品ID
                $goods_name = M('yy_goods')->field('goods_name')->where("goods_id='" . $goodsId . "'")->find();   //通过商品ID找到商品名称
                $data['goods_name'] = $goods_name['goods_name'];
                $rs = $this->activity->add($data);
                if ($rs) {
                    $this->success("添加成功");
                    if (!empty($goodsId)) {
                        $ones = $this->activity->field('one_id,price,oneupdown,currency_downprice,currency_num')->where('one_id=' . $rs)->find();
                        $datao['one_id'] = $ones['one_id'];
                        $datao['shop_price'] = $ones['price'];
                        $frequency_result = $ones['price'] / $ones['currency_downprice'] / $ones['currency_num'];        //每人次需购买次数
                        $datao['goods_gdnumber'] = $frequency_result;
                        $datao['goods_number'] = $frequency_result;
                        $datao['oneupdown'] = $ones['oneupdown'];
                        $re = M('yy_goods')->where('goods_id=' . $goodsId)->save($datao);
                    }
                } else {
                    $this->error('添加失败');
                }
            }
        }
        $this->assign('currency', $currency);
        $this->display();
    }

    //活动修改
    public function update()
    {
        $currency = $this->currency->field('currency_id,currency_name')->where(' currency_id<>25 and currency_id<>26 and currency_id<>28 ')->select();    //积分类型不选择比特积分、莱特积分、狗狗积分
        $id = intval(I('id'));
        if (!empty($id)) {
            $this->assign("id", $id);
            $where['one_id'] = $id;
            $goodsName = $this->activity->where($where)->find();
            $screen = explode(',', $goodsName['screen']);        //将筛选字段中的字符串用php方法转化为数组
        }
        if (IS_POST) {
            foreach ($_POST as $k => $v) {
                $data[$k] = $v;
            }
            $where['one_id'] = $_POST['id'];
            $data['add_time'] = time();
            $screenp = implode(',', $_POST['screen']);        //将传过来的复选框数组用php方法转化成字符串
            $data['screen'] = $screenp;                //筛选
            $rs = $this->activity->where($where)->save($data);
            if ($rs) {
                $this->success("修改成功");
                if (!empty($_POST['id'])) {
                    $ones = $this->activity->field('price,oneupdown,currency_downprice,currency_num')->where('one_id=' . $_POST['id'])->find();
                    $whereo['one_id'] = $_POST['id'];
                    $whereo['oneupdonw'] = 1;
                    $frequency_result = $ones['price'] / $ones['currency_downprice'] / $ones['currency_num'];        //每人次需购买次数
                    $datao['shop_price'] = $ones['price'];
                    $datao['goods_gdnumber'] = $frequency_result;
                    $datao['oneupdown'] = $ones['oneupdown'];
                    $datao['goods_number'] = $frequency_result;
                    $re = M('yy_goods')->where($whereo)->save($datao);
                }
            } else {
                $this->error("修改失败");
            }
        }
        $this->assign('screen', $screen);
        $this->assign('goodsName', $goodsName);
        $this->assign('currency', $currency);
        $this->display();
    }

    //活动删除
    public function delete()
    {
        $id = intval(I('id'));
        $re = $this->activity->delete($id);
        if ($re) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
            return;
        }
        $this->display();
    }

    //活动详情页
    public function act_history()
    {
        $id = intval(I('id'));
        $rid = intval(I('rid'));//移除id
        $where['one_id'] = $id;
        $where['is_new'] = 1;
        $count = M('yy_goods')->where($where)->count();
        $Page = new \Think\Page($count, 12);
        $show = $Page->show();
        $list = M('yy_goods')->where($where)->order('qishu asc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        if (!empty($rid)) {
            $wherer['goods_id'] = $rid;
            $data['is_new'] = 0;
            $rs = M('yy_goods')->where('goods_id=' . $rid)->save($data);
            if ($rs) {
                $this->success("移除成功", U('OneDollarBuy/index'));
            } else {
                $this->error("移除失败", U('OneDollarBuy/index'));
            }
        }
        $this->assign('list', $list);
        $this->assign('empty', '暂无数据');
        $this->assign('page', $show);
        $this->display();
    }

    //活动订单详情
    public function act_order()
    {
        $id = intval(I('id'));
        $wherep['goods_id'] = $id;
        $wherep['win_order'] = 1;                //win_order=1表示是中奖用户
        $prize = M('yy_order_goods')->where($wherep)->find();            //取活动获奖的幸运号码和订单编号
        $peoplenum = M('yy_goods')->field('renshu')->where('goods_id=' . $id)->find();        //取商品表的参与活动的人数

        $count = M('yy_order_goods')->where('goods_id=' . $id)->count();
        $Page = new \Think\Page($count, 12);
        $show = $Page->show();
        $order = M('yy_order_goods')->where('goods_id=' . $id)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('order', $order);
        $this->assign('empty', '暂无数据');
        $this->assign('page', $show);
        $this->assign('prize', $prize);
        $this->assign('peoplenum', $peoplenum);
        $this->display();
    }
}