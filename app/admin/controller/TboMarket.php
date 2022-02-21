<?php
namespace app\admin\controller;
use think\Request;

//矿源市场
class TboMarket extends Admin
{
    public function index(Request $request) {
        $list = \app\common\model\TboMarket::with('cat')->order('cat_id asc')->select();
        $cat_list = \app\common\model\TboMarketCat::select();
        return $this->fetch(null, compact('list','cat_list'));
    }

    public function add() {
        if($this->request->isPost()){
            $form = input('post.');

            if(empty($form['pic1'])) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'请上传图片']);
//                return $this->error("请上传图片");
            }

            $attachments_list = $this->oss_base64_upload($form['pic1'], 'tbo_market');
            if (empty($attachments_list) || $attachments_list['Code'] === 0 || count($attachments_list['Msg']) == 0) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'上传图片失败']);
//                return $this->error("上传图片失败");
            }

            unset($form['pic1'],$form['file']);
            $form['image'] = $attachments_list['Msg'][0];
            $flag = \app\common\model\TboMarket::insertGetID($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $cat_list = \app\common\model\TboMarketCat::all();
            return $this->fetch(null,compact('cat_list'));
        }
    }

    public function edit() {
        if($this->request->isPost()){
            $id = input('id');
            $form = input('post.');

            if(!empty($form['pic1'])) {
                $attachments_list = $this->oss_base64_upload($form['pic1'], 'tbo_market');
                if (empty($attachments_list) || $attachments_list['Code'] === 0 || count($attachments_list['Msg']) == 0) {
                    return $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'上传图片失败']);
                }
                $form['image'] = $attachments_list['Msg'][0];
            }
            unset($form['pic1'],$form['file']);
            $flag = \app\common\model\TboMarket::where(['id'=>$id])->update($form);
            if($flag===false) {
                return $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                return $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = \app\common\model\TboMarket::where(['id'=>$id])->find();

            $cat_list = \app\common\model\TboMarketCat::all();
            return $this->fetch(null,compact('cat_list','info'));
        }
    }

    public function delete()
    {
        $id = intval(input('id'));
        $flag = \app\common\model\TboMarket::where(['id' => $id])->delete();
        if ($flag === false) {
            $this->ajaxReturn(['result' => null, 'code' => ERROR1, 'message' => '修改失败']);
        } else {
            $this->ajaxReturn(['result' => null, 'code' => SUCCESS, 'message' => '修改成功']);
        }
    }
}
