<?php
namespace app\index\controller;
use think\captcha\Captcha;
use think\Db;
use think\Exception;

class Base  extends \app\common\controller\Common {
    protected $public_action = []; //无需登录即可访问
    protected $member_id = false;
    protected $member = [];
    

    public function _initialize() {
        parent::_initialize();

//        die('welcome');

        $this->assign(['config'=>$this->config]);

        $this->checkLogin(true);
        //登录验证
        $this->public_action = array_flip(array_change_key_case(array_flip($this->public_action),CASE_LOWER ));
        $action = strtolower($this->request->action());
        if(!$this->checkLogin() && !in_array($action, $this->public_action)) $this->output(10100,lang('lan_modifymember_please_login_first'));

        if(ACCESS_LOG_OPEN) {
            $data=input();
            $cur_url = strtolower($this->request->controller().'/'.$this->request->action());
            member_access_log($this->member_id,'/index/'.$cur_url,$data);
        }

        if($this->member_id) {
            $this->member = model('member')->getUserName($this->member_id);
            $this->assign('member',$this->member);
        }

        $this->assign('footer',[
            'xrp_overview' => $this->config['xrp_white'],
            'disclaimer' => url('index/help/index',['id'=>59]),
            'press_center' => url('news/newsList'),
            'ripple_home' => $this->config['xrp_url'],

            'faq' => url('Help/index',['id'=>184]),
            'contact_us' => url('index/help/index',['id'=>56]),
            'user_agree' => url('index/help/index',['id'=>60]),
            'xrp_block' => $this->config['xrp_block'],
            'about_us' => url('index/help/index',['id'=>58]),
        ]);
    }

    protected function getPages($count,$page,$page_size) {
        $count = intval($count);
        $page = intval($page);
        $page_size = intval($page_size);

        if($page<1) $page = 1;
        if($page_size<1) $page_size = 1;
        
        $param = $this->request->param();
        if(isset($param['page'])) unset($param['page']); 
        //$pages = new \think\paginator\driver\Bootstrap(null,$page_size,$page,$count,false,['path'=>url('',$param)]);
        $pages = new \page\Rbz(null,$page_size,$page,$count,false,['path'=>url('',$param)]);
        return $pages->render();
    }

    protected function output($code, $msg = '', $data = []) {
        if($this->request->isAjax()) {
            header('Content-type: application/json;charset=utf-8');
            $return = ['code' => $code, 'message' => $msg, 'result' => $data];
            exit(json_encode($return));
        } else {
            if($code==10000){
                $this->assign($data);
                $this->fetch();
            } elseif($code==10100){ //请先登录
                $this->redirect(url('Login/index'));
            } elseif($code==20100) {
                $this->redirect(url('User/safe'));
            } elseif ($code==30100) { 
                $this->redirect(url('User/senior_verify'));
            } elseif ($code==40100) {
                $this->redirect(url('User/senior_verify'));
            } else {
                $this->error($msg);
            }
        }
        exit;
    }

    //检测是否登录
    protected function checkLogin($reset=false) {
        if($this->member_id) return true;
        
        $this->member_id = intval(session('USER_KEY_ID'));
        if(empty($this->member_id)) return false;

        $key = session('USER_KEY_TOKEN');
        if(empty($key)) return false;

        $token_id = $this->member_id;
        $token = cache('pc_auto_login_'.$token_id,'',$this->login_keep_time);
        if($token===$key) {
            //重置过期时间
            if($reset) cache('pc_auto_login_'.$token_id,$token,$this->login_keep_time);
            return true;
        }
        
        session('USER_KEY_ID',null);
        session('USER_KEY_TOKEN',null);
        return false;
    }

	/**
     * 请求来源判断
     * @param $method
     */
    protected  function method_filter($method = 'post')
    {
        $_method = $this->request->method(true);
        if (strtolower($method)!==strtolower($_method)) $this->output(10400, lang('lan_orders_illegal_request'));
    }

    /**
     *  获取当前登陆账号指定积分类型的金额
     * @param int $currency_id 积分类型ID
     * @param char $field num  forzen_num
     * @return array 当前登陆人账号信息
     */
    protected function getUserMoney($currency_id, $field)
    {
        if (!isset($currency_id) || $currency_id == 0) {
            switch ($field) {
                case 'num':
                    $field = 'rmb';
                    break;
                case 'forzen_num':
                    $field = 'forzen_rmb';
                    break;
                default:
                    $field = 'rmb';
            }

            $num = db('member')->where(array('member_id' => $this->member_id))->value($field);
        } else {
            $num = db('Currency_user')->where(array('member_id' => $this->member_id, 'currency_id' => $currency_id))->value($field);
        }

        return number_format($num, 4, '.', '');
    }

    //验证网易验证码
    protected function checkNECaptchaValidate($NECaptchaValidate){
        //验证码验证
        $valudate= new \message\NECaptchaVerifier();
        if(!$valudate->verify($NECaptchaValidate)){
            return false;
        }else{
            return true;
        }
    }

    /**
     * 获取用户资的资产折算值
     * 返回数组如：
     *  'allmoneys' => string '1,390.3094' (length=10)
        'allmoneys_usd' => string '495.59' (length=6)
        'allmoneys_cny' => string '1,390.31' (length=8)
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Created by Red.
     * Date: 2019/2/26 11:47
     */
    protected function getUsersAssetConversion()
    {
        //个人账户资产
        $kok_price = empty($this->config['kok_price']) ? 1 : $this->config['kok_price'];

        $data['allmoneys']=number_format(0, 4);
        $data['allmoneys_usd']=number_format(0, 2);
        $data['allmoneys_cny']=number_format(0, 2);
        if (!empty($this->member_id)) {

            //获取所有账号总额
            $where_user['member_id'] = $this->member_id;

            $currency_user = db('currency_user')->where($where_user)->select();
            $allmoneys = 0;
            $all_name = 'rs_all_currency_user';
            $rs1 = cache($all_name);
            if (empty($rs1)) {
                foreach ($currency_user as $k => $v) {
                    $Currency_message[$v['currency_id']] = $this->getCurrencyMessageById($v['currency_id'], 8);
                }
                $Currency_message = empty($Currency_message) ? [] : $Currency_message;
                cache($all_name, $Currency_message, 300);
            }
            $Currency_message = cache($all_name);

            foreach ($currency_user as $k => $v) {
                if ($v['currency_id'] == "8") {
                    //查询手续费奖励和红包利息
//                    $allmoney = ($v['num'] + $v['lock_num'] + $v['exchange_num'] + $v['forzen_num'] + $v['num_award']);
                    $allmoney = $v['num'] ;
                } else {
//                    $allmoney = ($v['num'] + $v['lock_num'] + $v['exchange_num'] + $v['forzen_num'] + $v['num_award']) * $Currency_message[$v['currency_id']]['new_price'];
                    $allmoney = $v['num']* $Currency_message[$v['currency_id']]['new_price'];

                }

                $currency_user[$k]['now_price'] = number_format($allmoney, 2);
                $allmoneys += $allmoney;
            }

            $usd2cny = $this->getCurrencyMessageById(8, 5)['new_price'];

            $data['allmoneys']=number_format($allmoneys, 4);
            $data['allmoneys_usd']=number_format($allmoneys * $kok_price * $usd2cny, 2);
            $data['allmoneys_cny']=number_format($allmoneys * $kok_price, 2);

        }
        return$data;
    }

    /**
     * 获取用户资的资产折算值
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Created by Red.
     * Date: 2019/4/9 12:08
     */
    protected function getUsersAssetConversion1(){
        $where_user['member_id'] = $this->member_id;
        $currency_user = db('currency_user')->where($where_user)->select();
        $currency=db('currency')->field("currency_id,currency_mark")->select();
        $currency=array_column($currency,null,"currency_id");
        $allCNY=0;
        $allUSD=0;
        foreach ($currency_user as $k => $v) {
            $priceCNY=$this->getPriceByCurrencyId($v['currency_id'])['cny'];
            $priceUSD=$this->getPriceByCurrencyId($v['currency_id'])['usd'];
            $allmoneyCNY = $v['num']  * $priceCNY;
            $allmoneyUSD = $v['num']  * $priceUSD;
            $data[$currency[$v['currency_id']]['currency_mark']]['cny']=number_format($allmoneyCNY , 2,'.', '');
            $data[$currency[$v['currency_id']]['currency_mark']]['usd']=number_format($allmoneyUSD, 2,'.', '');
            $allCNY+=$allmoneyCNY;
            $allUSD+=$allmoneyUSD;
        }
        $data['allmoneys_usd']=$allUSD;
        $data['allmoneys_cny']=$allCNY;
        return $data;
    }
    /**
     * 根据币种id获取币等价的人民币和美元
     * @param $currency_id
     * @return
     * 'usd' => string '0.71' (length=4)
    'cny' => string '2.00' (length=4)
     * Created by Red.
     * Date: 2019/4/8 16:59
     */
    function getPriceByCurrencyId($currency_id){
//        $kok_price = empty($this->config['kok_price']) ? 1 : $this->config['kok_price'];
        $data['usd']=number_format(0, 2);
        $data['cny']=number_format(0, 2);
        $all_name = 'getPriceByCurrencyId_'.$currency_id;
        $rs1 = cache($all_name);
        if (empty($rs1)) {
            if($currency_id!=8){
                $Currency_message = $this->getCurrencyMessageById($currency_id, 8);
            }else{
                $Currency_message = $this->getCurrencyMessageById($currency_id, 5);
            }
            $Currency_message = empty($Currency_message) ? [] : $Currency_message;
            cache($all_name, $Currency_message, 300);
        }
        $Currency_message = cache($all_name);
        $usd2cny = $this->getCurrencyMessageById(8, 5)['new_price'];
//            $allmoney = NEW_PRICE_UNIT=="CNY"?$Currency_message['new_price_cny']:$Currency_message['new_price_usd'];
        if($currency_id==5){
            $data['cny']=usd2cny();
            $data['usd']=1;
        }else{
            $data['cny']=number_format($Currency_message['new_price_cny'], 2,'.', '');
            $data['usd']=number_format($Currency_message['new_price_usd'] * $usd2cny, 2,'.', '');
        }
        return$data;
    }

    /**
     * 获取当前积分类型的信息
     * @param int $id 积分类型id
     * @return 24H成交量 24H_done_num  24H成交额 24H_done_money 24H涨跌 24H_change 7D涨跌  7D_change
     * @return 最新价格 new_price 买一价 buy_one_price 卖一价 sell_one_price 最高价 max_price 最低价 min_price
     *
     * 新增加  $toId 目标兑换币
     */
    private function getCurrencyMessageById($id,$toId='',$from=0)
    {
        $currency = db('Currency')->where('is_line=1 ')->order('sort ASC')->select();
        $currency_id_mark = array_column($currency, 'currency_mark', 'currency_id');
        static  $usd2cny  = 0;
        static $static_new_price = [];
        $usd2cny =  $usd2cny ?:  usd2cny();
        $all_name = 'rs_all' . $id.'_'.$toId;
        $data = ($from==1) ? [] : cache($all_name);
        $currency_mark_id = array_flip($currency_id_mark);
        $usdt_id = $currency_mark_id["USDT"];
        $currency_id_value=[];
        foreach ($currency as $k => $value) {
            $currency_id_value[$value['currency_id']] = $value;
        }
        if (empty($data)) {
            $where['currency_id'] = $id;
            empty($toId)?: $where['currency_trade_id'] = $toId;
            $where['type'] = 'buy';
            $Currency_model = db('Currency');
            $trade_model = db('trade');

            $list = !empty($currency_id_value[$id])?$currency_id_value[$id] :($Currency_model->where(array('currency_id' => $id,'is_line' => 1))->cache()->find());
            $time = time();
            //一天前的时间
            $old_time = strtotime(date('Y-m-d', $time));

            //最新价格
            $order = 'add_time desc';
            $rs = $trade_model->where($where)->order($order)->cache(true,60)->find();


            if($toId == $usdt_id){
                $data['new_price'] = sprintf('%.8f',$rs['price']);
                $new_price = 1;
            }else{
                if( isset($static_new_price[$toId."_".$usdt_id]) && !empty($static_new_price[$toId."_".$usdt_id])){
                    $new_price = $static_new_price[$toId."_".$usdt_id];
                }else{
                    $usdt_new_price =  self::getCurrencyMessageById($toId,$usdt_id);
                    $new_price = $static_new_price[$toId."_".$usdt_id] = $usdt_new_price['new_price'];
                }
                $new_price = empty($new_price)?1:$new_price;
                $data['new_price'] = sprintf('%.8f',$rs['price']);
            }
            $data['new_price_usd'] = sprintf('%.4f',round($rs['price']*$new_price,2));//对应美元价格
            $data['new_price_cny'] = sprintf('%.4f',round($rs['price']*$new_price*$usd2cny/1,2));//对应美元价格


            $where2['currency_id'] = $id;
            empty($toId)?: $where2['currency_trade_id'] = $toId;
            $where2['type'] = 'sell';

            //24H成交量
            $rs = $trade_model->field("SUM(num) as num ,SUM(num * price) as numPrice")->where($where)->where("add_time>$time-60*60*24")->find();

            if ($rs['num'] == 0) {
                $data['24H_done_num'] = '0.00';
            }else {

                $data['24H_done_num'] =  round($rs['num'] * 2 + $list['num_number'],6);
            }

            //24H成交额
            if ($rs['numPrice'] == 0) {
                $data['24H_done_money'] = '0.00';
            } else {

                $data['24H_done_money'] =   round($rs['numPrice'] * 2 + $list['num_number'] * $data['new_price'],6);
            }

            //24H最低价
            $sql_time = $time - 60 * 60 * 24 ;
            $rs = $trade_model->field('price,min(price) as minprice,max(price) as maxprice')->where($where)->where("add_time>$sql_time")->find();
            $data['min_price'] = $rs['minprice'];
            if ($data['min_price'] == 0) {
                $data['min_price'] = '0.00';
            }

            //24H最高价
            $data['max_price'] = $rs['maxprice'];
            if ($data['max_price'] == 0) {
                $data['max_price'] = '0.00';
            }

            if ($rs['price'] > $data['new_price'] ) {
                //说明价格下降
                $data['new_price_status'] = 0;
            } else {
                $data['new_price_status'] = 1;
            }
            $data['24H_change'] = !empty( $rs['price'])? sprintf("%.2f", ($data['new_price']  - $rs['price']) / $rs['price'] * 100):0;
            $data['24H_change_price'] = abs(sprintf("%.2f", ($data['new_price']  - $rs['price'])));//24H价格变化值
            if ($data['24H_change'] == 0) {
                $data['24H_change'] = '0.00';
            }

            cache($all_name, $data, 15);
        }

        //返回
        return $data;
    }

    /**
     * 验证码
     * @param string $captcha 验证码
     * @return bool
     */
    public function verifyCaptcha($captcha = '')
    {
        if ((new Captcha())->check($captcha)) {
            return true;
        }

        return false;
    }
}
