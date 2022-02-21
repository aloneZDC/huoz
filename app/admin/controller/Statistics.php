<?php
namespace app\admin\controller;
use think\Db;

class Statistics extends Admin {
    //空操作
    public function _empty(){
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    //会员积分统计
    public function currency_user() {
        $order = 'a.cu_id';
        $order_by = 'desc';
        $where = [];
        $data['member_id']=$member_id = input('member_id');
        if(!empty($member_id)) $where['a.member_id'] = $member_id;

        $data['currency_id']= $currency_id = intval(input('currency_id'));
        if(!empty($currency_id)) $where['a.currency_id'] = $currency_id;

        $data['name']=$name = input('name');
        if(!empty($name)) $where['d.name'] = $name;

        $data['phone']=$phone = input('phone');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['d.email'] = $phone;
            } else {
                $where['d.phone'] = $phone;
            }
        }

        $order = input('order');
        if(!empty($order) && in_array($order,['total','a.cu_id','a.num','a.forzen_num','a.num_award','a.lock_num','a.exchange_num'])) {
            $order = $order;
        } else {
            $order = 'a.cu_id';
        }

        $order_by = input('order_by');
        if(empty($order_by) || $order_by=='desc') {
            $order_by = 'desc';
        } else {
            $order_by = 'asc';
        }

        $model = Db::name('currency_user');
        $list = $model->alias('a')->field("a.*,(a.num+a.forzen_num+a.num_award+a.lock_num+a.exchange_num) as total,b.currency_name,d.email,d.phone,d.name")
           ->join(config("database.prefix")."currency b","b.currency_id=a.currency_id","LEFT")
            ->join(config("database.prefix")."member d","d.member_id=a.member_id","LEFT")
            ->where($where)->order($order.' '.$order_by)->paginate(20,null,['query'=>input()]);
        $show=$list->render();
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('list', $list);

        $currency = Db::name('Currency')->field('currency_name,currency_id')->select();
        $this->assign('currency',$currency);

        $where['order'] = $order;
        $where['order_by'] = $order_by;
        $this->assign('where',$where);
        $this->assign('data',$data);
       return $this->fetch();
    }

    //积分汇总统计
    public function currencys() {
        $list = M('currency_user')->alias('a')->field("a.currency_id,sum(num) as num,sum(forzen_num) as forzen_num,sum(num_award) as num_award,sum(lock_num) as lock_num,sum(exchange_num) as exchange_num,b.currency_name")->join("left join __CURRENCY__ b ON a.currency_id = b.currency_id")->where($where)->group('a.currency_id')->select();
        $this->assign('list', $list);
        $this->display();
    }

    function everydayTotalList(){
        $startTime = I("startTime");
        $endTime = I("endTime");
        if (empty($startTime)) {
            $t=60*60*24*7;//七天时间秒数
            $startTime = date("Y-m-d", time()-$t);
        }
        if (empty($endTime)) {
            $endTime = date("Y-m-d", time());
        }
        $types=['num'=>"可用资产",'num_award'=>"赠送资产","forzen_num"=>"冻结资产"];
        $where['wet_time'] = array('between', array($startTime, $endTime));
        $list = M("wallet_everyday_total")->where($where)->order("wet_time desc")->select();
        $array = [];
        if (!empty($list)) {
            $currencyList=M("Currency")->field("currency_id,currency_mark")->select();
            $currencyList=array_column($currencyList,null,"currency_id");
            $typeList=[];
            foreach ($list as &$value) {
                $value['currency_mark'] = isset($currencyList[$value['wet_currency_id']]['currency_mark']) ? $currencyList[$value['wet_currency_id']]['currency_mark']: "未知币种";
                $value['types']=$types[$value['wet_type']];
                $typeList[$value['wet_type']]=$value;
                $array[$value['wet_time']][$value['wet_currency_id']] = $typeList;
            }
        }
        krsort($allList);
        $this->assign("startTime", $startTime);
        $this->assign("endTime", $endTime);
        $this->assign("list",$array);
        $this->display();
    }


}