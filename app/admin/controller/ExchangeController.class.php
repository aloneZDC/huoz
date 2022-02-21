<?php
namespace Admin\Controller;
use Admin\Controller\AdminController;
use Think\Verify;
use Think\Exception;
use Think\Page;

class ExchangeController extends AdminController {
    //空操作
    public function _empty(){
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    //GAC兑换记录
    public function index() {
        $phone=I('phone');
        $member_id = I('member_id');
        $type = I('type','exchange');
        if($type=='internal_buy') {
            $model = M('gac_internal_buy');
        } elseif($type=='xrp_exchange_gac') {
            $model = M('xrp_exchange_gac'); //剩余本金 福利兑换
        } else {
            $model = M('currency_exchange_log');
        }
        
        if(!empty($phone)) {
            if(checkEmail($phone)) {
                $where['b.email'] = $phone;
            } else {
                $where['b.phone'] = $phone;
            }
        }
        if(!empty($member_id)) $where['a.member_id'] = $member_id;

        $count      = $model->alias('a')->join('LEFT JOIN yang_member as b on a.member_id = b.member_id ')->where($where)->count();
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('phone'=>$phone,'member_id'=>$member_id));
         
        $show       = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = $model->alias('a')->field('a.*,b.phone as attend_phone,b.name as attend_name,b.email as attend_email,c.currency_mark as from_mark,d.currency_mark as to_mark')
            ->where($where)
            ->join('LEFT JOIN yang_member as b on a.member_id = b.member_id ')
            ->join('LEFT JOIN yang_currency as c on a.currency_id = c.currency_id ')
            ->join('LEFT JOIN yang_currency as d on a.to_currency_id = d.currency_id ')
            ->order("a.id desc")
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        //尚未兑换的剩余本金
        $query = M()->query('select sum(a.num-b.num1) as num from yang_boss_plan_info a left join (select sum(num) as num1,member_id from yang_boss_bouns_log WHERE receive_status=1 GROUP BY member_id) b on a.member_id=b.member_id left join yang_boss_plan c on a.member_id=c.member_id where c.confirm_time < 1555430400 and a.num>b.num1 and a.member_id not in (select DISTINCT member_id from yang_currency_gac_log WHERE type=20)');
        $num = 0;
        if($query && isset($query[0]) ) $num = $query[0]['num'];

        $sum = [];
        $sum['internal_buy'] = M('gac_internal_buy')->field('sum(from_num) as from_num,sum(num) as num')->find();
        $sum['exchange'] = M('currency_exchange_log')->field('sum(from_num) as from_num,sum(num) as num')->find();
        $sum['xrp_exchange_gac'] = M('xrp_exchange_gac')->field('sum(from_num) as from_num,sum(num) as num')->find();
        $sum['pay'] = M('pay')->where(['type'=>8])->sum('money');
        $sum['yu'] = $num;
        $this->assign('sum',$sum);
        $this->assign('type',$type);
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }

    //GAC记录
    public function index_list() {
        $phone=I('phone');
        $member_id = I('member_id');
        $type = I('type','exchange');
        if($type=='internal_buy') {
            $model = M('currency_gac_internal_buy');
        } elseif($type=='xrp_exchange_gac') {
            $model = M('currency_gac_log'); //剩余本金 福利兑换
        } else {
            $model = M('currency_gac_forzen');
        }
        
        if(!empty($phone)) {
            if(checkEmail($phone)) {
                $where['b.email'] = $phone;
            } else {
                $where['b.phone'] = $phone;
            }
        }
        if(!empty($member_id)) $where['a.member_id'] = $member_id;

        $count      = $model->alias('a')->join('LEFT JOIN yang_member as b on a.member_id = b.member_id ')->where($where)->count();
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('phone'=>$phone,'member_id'=>$member_id));
         
        $show       = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = $model->alias('a')->field('a.*,b.phone as attend_phone,b.name as attend_name,b.email as attend_email')
            ->where($where)
            ->join('LEFT JOIN yang_member as b on a.member_id = b.member_id ')
            ->order("a.id desc")
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        foreach ($list as $key => &$value) {
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
            $value['title'] = L($value['title']);
            if($value['type']==1){
                $value['title'] = '轉賬給'.$value['title'];
                $value['num_type'] = '-';
            } elseif($value['type']==2) {
                $value['title'] = '從'.$value['title'].'轉入';
                $value['num_type'] = '+';
            } elseif($value['type']<20) {
                $value['num_type'] = '+';
            } elseif($value['type']<30) { //20
                $value['num_type'] = '+';
            } elseif($value['type']>=30) { //30
                $value['num_type'] = '-';
            }
            $value['num'] = rtrim(rtrim($value['num'],'0'),'.');
            $value['currency_name'] = 'GAC';
        }
        
        $this->assign('type',$type);
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }

    //释放记录
    public function freed_list() {
        $model = M('currency_lock_freed');

        $where = [];
        $member_id = I('member_id');
        if($member_id) $where['a.member_id'] = $member_id;

        $phone = I('phone');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['d.email'] = $phone;
            } else {
                $where['d.phone'] = $phone;
            }
        }

        // 查询满足要求的总记录数
        $count = $model->alias('a')->where($where)->join('left join __MEMBER__ d on a.member_id=d.member_id')->count();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count, 20);
        //将分页（点击下一页）需要的条件保存住，带在分页中
        $Page->parameter = $where;
        // 分页显示输出
        $show = $Page->show();
        //需要的数据
        $list = $model->alias('a')->field("a.*,b.currency_name,d.email,d.phone,d.name")->join("left join __CURRENCY__ b ON a.currency_id = b.currency_id")->join('left join __MEMBER__ d on a.member_id=d.member_id')->where($where)->order('a.clf_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $this->assign('page', $show); // 赋值分页输出
        $this->assign('list', $list);
        $this->display();
    }

    //互转记录
    public function mutual_transfer_log() {
        $model = M('currency_mutual_transfer');

        $where = [];

        $member_id = I('member_id');
        if(!empty($member_id)) $where['a.from_member_id'] = $member_id;

        $to_member_id = I('to_member_id');
        if(!empty($to_member_id)) $where['a.to_member_id'] = $to_member_id;

        $phone = I('phone');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['d.email'] = $phone;
            } else {
                $where['d.phone'] = $phone;
            }
        }

        // 查询满足要求的总记录数
        $count = $model->alias('a')->where($where)->join('left join __MEMBER__ d on a.from_member_id=d.member_id')->count();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count, 20);
        //将分页（点击下一页）需要的条件保存住，带在分页中
        $Page->parameter = $where;
        // 分页显示输出
        $show = $Page->show();
        //需要的数据
        $list = $model->alias('a')->field("a.*,d.email,d.phone,d.name,c.email as to_email,c.phone as to_phone,c.name as to_name")->join('left join __MEMBER__ d on a.from_member_id=d.member_id')->join('left join __MEMBER__ c on a.to_member_id=c.member_id')->where($where)->order('a.mt_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $this->assign('page', $show); // 赋值分页输出
        $this->assign('list', $list);
        $this->display();
    }
    
    //转锁仓记录
    public function tolock_log() {
        $model = M('currency_tolock_log');

        $where = [];
        $member_id = I('member_id');
        if($member_id) $where['a.member_id'] = $member_id;

        $phone = I('phone');
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['d.email'] = $phone;
            } else {
                $where['d.phone'] = $phone;
            }
        }

        // 查询满足要求的总记录数
        $count = $model->alias('a')->where($where)->join('__MEMBER__ d on a.member_id=d.member_id')->count();
        // 实例化分页类 传入总记录数和每页显示的记录数
        $Page = new \Think\Page ($count, 20);
        //将分页（点击下一页）需要的条件保存住，带在分页中
        $Page->parameter = $where;
        // 分页显示输出
        $show = $Page->show();
        //需要的数据
        $list = $model->alias('a')->field("a.*,b.currency_name,d.email,d.phone,d.name")->join("left join __CURRENCY__ b ON a.currency_id = b.currency_id")->join('__MEMBER__ d on a.member_id=d.member_id')->where($where)->order('a.id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $this->assign('page', $show); // 赋值分页输出
        $this->assign('list', $list);
        $this->display();
    }
}