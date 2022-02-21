<?php
namespace app\admin\controller;
use think\Db;
use think\paginator\driver\Bootstrap;
use think\Request;

//币币交易新功能限制功能
class TradeDayConfig extends Admin
{
    public function index(Request $request) {
        $list = Db::name('trade_day_config')->field('a.*,b.currency_name,c.currency_name as currency_trade_name')->alias('a')
            ->join(config("database.prefix").'currency b','a.currency_id = b.currency_id',"LEFT")
            ->join(config("database.prefix").'currency c','a.currency_trade_id = c.currency_id',"LEFT")
            ->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        return $this->fetch(null, compact('list', 'page'));
    }

    public function currency_add(){
        if($this->request->isPost()){
            $form = input('post.');
            $form['start_time'] = $form['start_time'] ? strtotime($form['start_time']) : 0;
            $form['stop_time'] = $form['stop_time'] ? strtotime($form['stop_time']) : 0;
            $form['is_buy'] = intval($form['is_buy']);
            $form['is_sell'] = intval($form['is_sell']);
            $form['robot_id'] = str_replace('，',',',$form['robot_id']);

            $flag = Db::name('trade_day_config')->insertGetID($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $currency = \app\common\model\Currency::field('currency_id,currency_name,is_trade_currency')->select();
            return $this->fetch(null,compact('currency'));
        }
    }

    public function currency_edit() {
        if($this->request->isPost()){
            $id = input('id');
            $form = input('post.');
            $form['start_time'] = $form['start_time'] ? strtotime($form['start_time']) : 0;
            $form['stop_time'] = $form['stop_time'] ? strtotime($form['stop_time']) : 0;
            $form['is_buy'] = intval($form['is_buy']);
            $form['is_sell'] = intval($form['is_sell']);
            $form['robot_id'] = str_replace('，',',',$form['robot_id']);

            $flag = Db::name('trade_day_config')->where(['id'=>$id])->update($form);
            if($flag===false) {
                $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'失败']);
            } else {
                $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'成功']);
            }
        } else {
            $id = input('id');
            $info = Db::name('trade_day_config')->where(['id'=>$id])->find();

            $currency = \app\common\model\Currency::field('currency_id,currency_name,is_trade_currency')->select();
            return $this->fetch(null,compact('currency','info'));
        }
    }

    public function currency_delete() {
        $id = intval(input('id'));
        $flag = Db::name('trade_day_config')->where(['id'=>$id])->delete();
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }
}
