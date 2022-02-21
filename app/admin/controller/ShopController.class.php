<?php

namespace Admin\Controller;

class ShopController extends AdminController
{
    public function _initialize()
    {
        parent::_initialize();
        $this->currency = M('Currency');
    }

    //空操作
    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    //商品展示列表
    public function index()
    {
        $goods_id = I('goods_id');            //用于搜索
        if (!empty($goods_id)) {
            $where['goods_id'] = $goods_id;
        }
        $count = M('shop_goods')->where($where)->count();
        $Page = new \Think\Page($count, 12);
        $show = $Page->show();
        $list = M('shop_goods')->where($where)->order('add_time desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('list', $list);
        $this->assign('empty', '暂无数据');
        $this->assign('page', $show);
        $this->display();
    }

    //商品添加
    public function shop()
    {
        if (IS_POST) {
            foreach ($_POST as $k => $v) {
                $data[$k] = $v;
            }

            //获取筛选条件
            $data['screen'] = implode(',', $_POST['screen']);

            //获取图片 先把大图上传到 阿里云OSS 上传到本地 然后生成缩略图到本地
            $data['add_time'] = time();
            if ($_FILES['Filedata']['size']  > 0) {
                $data['goods_img'] = $this->oss_upload($_FILES, 'images/shop/goods')['Filedata'];

                $data['datu'] = $this->upload($_FILES["Filedata"]);
                $type = "big";
                $image = new \Think\Image();
                $image->open('.'. $data['datu']);
                $picname = substr($data['datu'], strrpos($data['datu'], "/") + 1);
                $filename = substr($data['datu'], 0, strrpos($data['datu'], "/") + 1);
                $thumb_pic = '.' . $filename . $type . "_" . $picname;
                $image->thumb(200, 200)->save($thumb_pic);
                $iden = true;
            }

            //把缩略图上传到 阿里云OSS
            if ($iden) {
                $thumbPic = $filename . $type . "_" . $picname;
                $_FILES['Filedata']['tmp_name'] = '.'.$thumbPic;

                $data['goods_thumb'] = $this->oss_upload($_FILES, 'images/shop/goods_thumb')['Filedata'];
                //$data['goods_thumb'] = $thumbPic;
            }

            $rs = M('shop_goods')->add($data);
            if ($rs) {
                $this->success("添加成功");
            } else {
                $this->error('添加失败');
            }
        }
        $catId = M('shop_category')->field('cat_id,cat_name')->select();
        $this->assign('catId', $catId);
        $this->display();
    }

    //商品修改
    public function shop_update()
    {
        $id = intval(I('id'));
        if (!empty($id)) {
            $this->assign("id", $id);
            $where['goods_id'] = $id;
            $list = M('shop_goods')->where($where)->find();
            $pic = M('shop_goods_pics')->where($where)->select();
            $screen = explode(',', $list['screen']);        //将筛选字段中的字符串用php方法转化为数组
            $this->assign('screen', $screen);
        }
        if (IS_POST) {
            $duotu_pic = $_POST['duotu_url'];//接收多图路径数组
            foreach ($_POST as $k => $v) {
                $data[$k] = $v;
            }

            //获取筛选条件
            $data['screen'] = implode(',', $_POST['screen']);
            $whereg['goods_id'] = $_POST['id'];

            //获取图片 先把大图上传到 阿里云OSS 上传到本地 然后生成缩略图到本地
            $data['add_time'] = time();
            if ($_FILES['Filedata']['size']  > 0) {
                $data['goods_img'] = $this->oss_upload($_FILES, 'images/shop/goods')['Filedata'];

                $data['datu'] = $this->upload($_FILES["Filedata"]);
                $type = "big";
                $image = new \Think\Image();
                $image->open('.'. $data['datu']);
                $picname = substr($data['datu'], strrpos($data['datu'], "/") + 1);
                $filename = substr($data['datu'], 0, strrpos($data['datu'], "/") + 1);
                $thumb_pic = '.' . $filename . $type . "_" . $picname;
                $image->thumb(200, 200)->save($thumb_pic);
                $iden = true;
            }

            //把缩略图上传到 阿里云OSS
            if ($iden) {
                $thumbPic = $filename . $type . "_" . $picname;
                $_FILES['Filedata']['tmp_name'] = '.'.$thumbPic;

                $data['goods_thumb'] = $this->oss_upload($_FILES, 'images/shop/goods_thumb')['Filedata'];
                //$data['goods_thumb'] = $thumbPic;
            }

            $rs = M('shop_goods')->where($whereg)->save($data);
            if ($rs) {
                //多图保存
                if ($duotu_pic) {
                    foreach ($duotu_pic as $value) {
                        $data['pic'] = $value;
                        $data['goods_id'] = $_POST['id'];
                        M('shop_goods_pics')->add($data);
                    }
                    //更新一张图为封面图
                    M('shop_goods_pics')->where('goods_id=' . $_POST['id'])->limit(1)->setField('fengmian', '1');
                }
                $this->success("修改成功", U('Shop/index'));
            } else {
                $this->error("修改失败", U('Shop/index'));
                if ($duotu_pic) {
                    foreach ($duotu_pic as $value) {
                        $lc_pic_url = $value;
                        if (file_exists($lc_pic_url)) {
                            unlink($lc_pic_url);
                        }
                    }
                }
            }
        }
        $catId = M('shop_category')->field('cat_id,cat_name')->select();//商品分类
        $this->assign('catId', $catId);
        $this->assign('list', $list);
        if (count($pic) > 0) {
            $this->assign('pic', $pic);
        }
        $this->assign('empty', '暂无图片');
        $this->display();
    }

    //商品删除
    public function shop_del()
    {
        $id = intval(I('id'));
        $rs = M('shop_goods')->delete($id);
        if ($rs) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
            return;
        }
    }

    //多图ajax标题修改
    public function duotu_edit_title()
    {
        $title = $_POST['title'];//获取标题
        $pic_id = $_POST['id'];//获取修改的编号
        $sql = M('shop_goods_pics')->where('pic_id=' . $pic_id)->setField('title', $title);
        if (count($sql) > 0) {
            $value = "yes";//成功了
        } else {
            $value = "no";//失败了
        }
        $this->ajaxReturn($value);
    }

    //多图ajax排序修改
    public function duotu_edit_paixu()
    {
        $paixu = $_POST['paixu'];//获取排序编号
        $lc_id = $_POST['id'];//获取修改的编号
        $sql = M('shop_goods_pics')->where('pic_id=' . $lc_id)->setField('sort_id', $paixu);
        if (count($sql) > 0) {
            $value = "yes";//成功了
        } else {
            $value = "no";//失败了
        }
        $this->ajaxReturn($value);
    }

    //多图ajax封面图片修改
    public function duotu_xuan_fengmian()
    {
        $product_id = $_POST['product_id'];//获取所属图文id
        $lc_id = $_POST['id'];//获取修改的编号
        //先设置所有图片不是封面图
        $sql = M('shop_goods_pics')->where('goods_id=' . $product_id)->setField('fengmian', '0');
        //再设置某张图片为封面图
        $sqlf = M('shop_goods_pics')->where('pic_id=' . $lc_id)->setField('fengmian', '1');
        if (count($sqlf) > 0) {
            $value = "yes";//成功了
        } else {
            $value = "no";//失败了
        }
        $this->ajaxReturn($value);
    }

    //多图ajax删除
    public function duotu_del()
    {
        $type = $_POST['type'];//获取操作类型(one and all)

        if ($type == "one") {
            $lc_id = $_POST['id'];//接收要删除的图片的编号
            $select_rows = M('shop_goods_pics')->field('pic')->where('pic_id=' . $lc_id)->find();
            $rs = M('shop_goods_pics')->delete($lc_id);
            if ($rs && count($rs) > 0) {
                $value = "yes";
                //判断图片是否存在并删除
                if (file_exists($select_rows['pic'])) {
                    unlink($select_rows['pic']);
                }
            } else {
                $value = "no";
            }
            $this->ajaxReturn($value);
        }
        if ($type == "all") {
            $lc_id = $_POST['id'];//接收要删除的图片的编号数组
            $picid = explode(",", $lc_id);//分割数组
            $shu = count($picid);//获取数组元素个数
            foreach ($picid as $value) {
                /*查询删除的内容*/
                $select_rows = M('shop_goods_pics')->field('pic')->where('pic_id=' . $value)->find();
                /*查询删除的内容end*/
                /*删除内容*/
                $rs = M('shop_goods_pics')->delete($value);
                if ($rs && count($rs) > 0) {
                    //判断图片是否存在并删除
                    if (file_exists($select_rows['pic'])) {
                        unlink($select_rows['pic']);
                    }
                }
                /*删除内容End*/
            }
            $value = "yes";
            $this->ajaxReturn($value);
        }
    }
}