<?php
namespace Admin\Controller;
use Common\Controller\CommonController;
use Think\Page;
use Think\Exception;

class MoneyInterestController extends AdminController {
    //空操作
    public function _empty(){
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    /**
     * 持币生息列表
     */
    public function index(){
        $member_id=I('member_id');
        $currency_id=I('currency_id');

        $phone=I('phone');
        $daochu=I('daochu');

        if(!empty($member_id)) $where['c.member_id'] = $member_id;
        if(!empty($currency_id)) $where['a.currency_id'] = $currency_id;
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['c.email'] = $phone;
            } else {
                $where['c.phone'] = $phone;
            }
        }

        $status = I('status','');
        if($status!='') {
            $status = intval($status);
            $where['a.status'] = $status;
        }

        $field = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        if($daochu==2){
            $list = M('money_interest')
                ->alias('a')
                ->field($field)
                ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
                ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
                ->where($where)
                ->order(" a.id desc ")
                ->select();
            $statusList = ['0' => '生息中', '1' => '已生息',  '2' => '已撤销'];
            if (!empty($list)) {
                foreach ($list as &$value) {
                    $value['phone']=!empty($value['phone'])?$value['phone']:$value['email'];
                    $value['rate']=$value['rate']."%";
                    $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
                    $value['end_time'] = date("Y-m-d H:i:s", $value['end_time']);
                    $value['status'] = $statusList[$value['status']];
                }
            }
            $xlsCell = array(
                array('id', '列表ID'),
                array('member_id', '会员ID'),
                array('name', '姓名'),
                array('phone', '账户'),
                array('currency_name', '币种'),
                array('months', '月份'),
                array('num', '数量'),
                array('rate', '年收益率'),
                array('day_num', '每日生息量'),
                array('add_time', '添加时间'),
                array('end_time', '到期时间'),
                array('status', '状态')

            );
            $this->exportExcel("持币生息记录", $xlsCell, $list);
            die();
        }
        $count      = M('money_interest')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->count();// 查询满足要求的总记录数
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('currency_id'=>$currency_id,'phone'=>$phone,'member_id'=>$member_id,'status'=>$status));
         
        $show       = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M('money_interest')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->order(" a.id desc ")
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        //积分类型
        $currency = M('Currency')->field('currency_name,currency_id')->select();
        $this->assign('currency',$currency);
        $this->assign('list',$list);

        $this->assign('member_id',$member_id);
        $this->assign('currency_id',$currency_id);
        $this->assign('phone',$phone);
        $this->assign('status',$status);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }

    //生息记录
    public function interest() {
        $member_id=I('member_id');
        $currency_id=I('currency_id');

        $phone=I('phone');
        $interest_id = I('interest_id');
        if(!empty($interest_id)) $where['a.interest_id'] = $interest_id;
        if(!empty($member_id)) $where['c.member_id'] = $member_id;
        if(!empty($currency_id)) $where['a.currency_id'] = $currency_id;
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['c.email'] = $phone;
            } else {
                $where['c.phone'] = $phone;
            }
        }

        $status = I('status','');
        if($status!='') {
            $status = intval($status);
            $where['d.status'] = $status;
        }

        $field = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $count      = M('money_interest_daily')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->join('LEFT JOIN yang_money_interest as d on a.interest_id = d.id ')
            ->where($where)
            ->count();// 查询满足要求的总记录数
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('currency_id'=>$currency_id,'phone'=>$phone,'member_id'=>$member_id,'status'=>$status));
         
        $show       = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M('money_interest_daily')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->join('LEFT JOIN yang_money_interest as d on a.interest_id = d.id ')
            ->where($where)
            ->order(" a.id desc ")
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        //积分类型
        $currency = M('Currency')->field('currency_name,currency_id')->select();
        $this->assign('currency',$currency);
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }

    //分红记录
    public function dividend() {
        $member_id=I('member_id');
        $currency_id=I('currency_id');

        $phone=I('phone');
        $interest_id = I('interest_id');
        if(!empty($interest_id)) $where['a.interest_id'] = $interest_id;
        if(!empty($member_id)) $where['c.member_id'] = $member_id;
        if(!empty($currency_id)) $where['a.currency_id'] = $currency_id;
        if(!empty($phone)){
            if(checkEmail($phone)) {
                $where['c.email'] = $phone;
            } else {
                $where['c.phone'] = $phone;
            }
        }

        $status = I('status','');
        if($status!='') {
            $status = intval($status);
            $where['a.status'] = $status;
        }

        $field = "a.*,b.currency_name,c.email as email,c.member_id as member_id,c.name as name,c.phone as phone";
        $count      = M('money_interest_dividend')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->count();// 查询满足要求的总记录数
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('currency_id'=>$currency_id,'phone'=>$phone,'member_id'=>$member_id,'status'=>$status));
         
        $show       = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M('money_interest_dividend')
            ->alias('a')
            ->field($field)
            ->join('LEFT JOIN yang_currency AS b ON a.currency_id = b.currency_id')
            ->join('LEFT JOIN yang_member as c on a.member_id = c.member_id ')
            ->where($where)
            ->order(" a.id desc ")
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        //积分类型
        $currency = M('Currency')->field('currency_name,currency_id')->select();
        $this->assign('currency',$currency);
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }

    //分红记录
    public function dividend_day() {
        $count      = M('money_interest_dividend_day')->count();// 查询满足要求的总记录数
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        setPageParameter($Page, array());
         
        $show       = $Page->show();// 分页显示输出
        $list = M('money_interest_dividend_day')->order("id desc ")->limit($Page->firstRow.','.$Page->listRows)->select();

        $currency = M('Currency')->field('currency_name,currency_id')->select();
        $currency_list = [];
        foreach ($currency as $key => $value) {
            $currency_list[$value['currency_id']] = $value['currency_name'];
        }

        foreach ($list as $key => $value) {
            $price_list = json_decode($value['price_list'],true);
            $value['price_list'] = '';
            foreach ($price_list as $currency_id => $price) {
                if(isset($currency_list[$currency_id])) {
                    $value['price_list']  .= $currency_list[$currency_id].' = '.$price.' KOK<br>';
                }
            }
            $list[$key] = $value;
        }

        $kok_total = 0;
        $kok_money = keepPoint($this->config['kok_interest_dividend'],6);
        $price_ll = '';
        //获取持币生息中的数量,计算分红
        $total_interest = M('money_interest')->field('sum(num) as sum_num,currency_id')->where('status=0')->group('currency_id')->select();
        if($total_interest){
            foreach ($total_interest as $value) {
                //获取对应的KOK价格
                $price = $this->getBk($value['currency_id']);
                if(!$price) $price = 0;

                $kok_total += keepPoint($price * $value['sum_num'],6);
                $price_ll .= $currency_list[$value['currency_id']].' = '.$price." KOK<br>"; 
            }
            //每份可得的分红
            $one_dividend = keepPoint($kok_money/$kok_total,6);
        }
        $this->assign('dividend_day',['kok_total'=>$kok_total,'kok_money'=>$kok_money,'one_dividend'=>$one_dividend,'price_ll'=>$price_ll]);
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }

    //配置列表
    public function setting() {
        $where = [];

        $currency_id= intval(I('currency_id'));
        if(!empty($currency_id)) $where['a.currency_id'] = $currency_id;

        $count      = M('money_interest_config')->alias('a')->where($where)->count();// 查询满足要求的总记录数
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        setPageParameter($Page, array('currency_id'=>$currency_id));

        $list = M('money_interest_config')->alias('a')->field('a.*,b.currency_name')->where($where)->join('left join __CURRENCY__ b on a.currency_id=b.currency_id')->order('a.id desc')->limit($Page->firstRow.','.$Page->listRows)->select();

        $currency = M('Currency')->field('currency_name,currency_id')->select();
        $this->assign('currency',$currency);
        $this->assign('list',$list);
        $show       = $Page->show();
        $this->assign('page',$show);
        $this->display();
    }

    //添加配置
    public function add_setting() {
        $id = I('id',0,'intval');        
        if(IS_POST){
            $model = M('money_interest_config');
            $data = [
                'currency_id' => I('currency_id',0,'intval'),
                'months' => I('months','','intval'),
                'min_num' => I('min_num','','floatval'),
                'max_num' => I('max_num','','floatval'),
                'rate'=> I('rate','','floatval'),
                'add_time' => time(),
                'cn_title' => I("cn_title"),
                'en_title' => I("en_title"),
                'cn_characteristic' => I("cn_characteristic"),
                'en_characteristic' => I("en_characteristic"),
                'cn_details' => I("cn_details"),
                'en_details' => I("en_details"),
            ];

            if(strlen($data['cn_title'])<=0) $this->error('中文标题不能为空');
            if(strlen($data['en_title'])<=0) $this->error('英文标题不能为空');
            if($data['months']<=0) $this->error('月份不能为空');
            if($data['rate']<=0) $this->error('年收益不能为空');
            if($data['min_num']<0) $this->error('最低转入不能为空');
            if($data['max_num']<0) $this->error('最高转入不能为空');
            if(strlen($data['cn_characteristic'])<=0) $this->error('中文产品特点介绍不能为空');
            if(strlen($data['en_characteristic'])<=0) $this->error('英文产品特点介绍不能为空');
            if($data['cn_details']<=0) $this->error('中文详情文章ID能为空');
            if($data['en_details']<=0) $this->error('英文详情文章ID不能为空');

            if(empty($id)) {
                $log = $model->where(['currency_id'=>$data['currency_id'],'months'=>$data['months']])->find();
                if($log) $this->error('该币种-月份已存在');
                
                $result = $model->add($data);
            } else {
                $result = $model->where(['id'=>$id])->save($data); 
            }
            
            if($result!==false){
                $this->success(L('lan_operation_success'));
            } else {
                $this->error(L('lan_network_busy_try_again'));
            }
        } else {
            $info = [];
            if(!empty($id)) $info = M('money_interest_config')->where(['id'=>$id])->find();
            $this->assign('list',$info);
            $this->assign('currency',M('currency')->select());
            $this->display();
        }
    }

    public function del_setting() {
        $id = I('id',0,'intval');
        if(empty($id)) self::output(0, L('lan_Illegal_operation'));

        $model = M('money_interest_config');
        $info = $model->where(['id'=>$id])->find();
        if(empty($info)) self::output(0, L('lan_Illegal_operation'));

        $result = $model->where(['id'=>$id])->delete();
        if(!$result) {
            self::output(0, L('lan_network_busy_try_again'));
        } else {
            self::output(1, L('lan_operation_success'));
        }
    }

    //根据币种获取对应KOK价格
    private function getBk($currency_id) {
        if($currency_id == 9){
            return 1;
        }else{
            return M('trade')->where(['currency_id'=>$currency_id, 'currencty_trade_id'=>9])->order('add_time desc')->getField('price');
        }
    }

    /**
     * 取消生息
     * Created by Red.
     * Date: 2019/1/17 16:34
     */
    function cancel(){
        $r['code']=ERROR1;
        $r['message']="参数错误";
        $r['result']=[];
        $id=I("id");
        if(!empty($id)){
            M()->startTrans();
            try{
                $find=M("money_interest")->where(['id'=>$id,'status'=>0])->find();
                if(!empty($find)){
                    $update=M("money_interest")->where(['id'=>$id])->save(['status'=>2]);
                    if($update){
                        //退回可用的帐本记录
                        $accountbookD=D("Accountbook");
                        $da['member_id']=$find['member_id'];
                        $da['currency_id']=$find['currency_id'];
                        $da['type']=23;
                        $da['content']="lan_withdrawal_currency";
                        $da['number_type']=1;
                        $da['number']=$find['num'];
                        $da['third_id']=$find['id'];
                        $accountbook= $accountbookD->addLog($da);
                        if(!$accountbook) {
                            throw new Exception(L("lan_operation_failure"));
                        }
                        $currencyUser=M("currency_user")->where(['member_id'=>$find['member_id'],'currency_id'=>$find['currency_id']])->find();
                        if($currencyUser){
                            $currencyUser['num']+=$find['num'];
                            $save=M("currency_user")->where(['member_id'=>$find['member_id'],'currency_id'=>$find['currency_id']])->save(['num'=>$currencyUser['num']]);
                            if($save){
                                $r['code']=SUCCESS;
                                $r['message']="撤消成功";
                            }else{
                                throw new \Exception("保存数据异常");
                            }
                        }else{
                            throw new \Exception("查询数据异常");
                        }
                    }

                }else{
                    $r['message']="该记录已不可再操作";
                }
                M()->commit();
            }catch (\Exception $exception){
                M()->rollback();
                $r['message']=$exception->getMessage();
            }

        }
        self::output_new($r);
    }
}