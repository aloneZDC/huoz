<?php


namespace app\admin\controller;

use OSS\Core\OssException;
use think\Controller;
use think\Db;

class CommonBase extends Controller
{
    protected $config;
    protected $trade;
    protected $member;
    protected $currency;
    protected $currency_id_mark;
    protected $currency_id_value;
    protected $currency_isqu;
    protected $currency_noqu;
    protected $cache_time = 3600; //缓存一小时
    protected $oss_config = [];
    protected $kac_config=[];
    protected  $coin_list =  [
                9 => 'KOK', //云数
                1 => 'BTC',
                14 => 'CNN',
                5 => 'USDT',
                ];

    public function _initialize()
    {

        $sel_oss_config = require_once(WEB_PATH.'/../app/extra/aliyun_oss.php');
        $this->oss_config = [
            'accessKeyId' => $sel_oss_config['accessKeyId'],
            'accessKeySecret' =>$sel_oss_config['accessKeySecret'],
            'endpoint' => $sel_oss_config['endpoint'],
            'bucket' =>$sel_oss_config['bucket'],
        ];
        $list =Db::name("Config")->select();
        foreach ($list as $k => $v) {
            $list[$v['key']] = $v['value'];
        }

        $this->config = $list;
        $this->assign('config', $this->config);
        $this->currency=Db::name("currency")->select();

        if(strtolower(CONTROLLER_NAME)=='upload') {
            $data = [];
        } else {
            $data = I('');
            $filter_param = ['pic1','pic2','pic3','img','image'];
            foreach ($filter_param as $filter) {
                if(isset($data[$filter])) $data[$filter] = '';
            }
        }
        $ip = get_client_ip();
        M('admin_access_log')->add([
            'admin_id' => intval($_SESSION['admin_userid']),
            'module' => CONTROLLER_NAME.'/'.ACTION_NAME,
            'param' => json_encode($data),
            'access_ip' => $ip,
            'access_time' => time(),
        ]);
    }
    
    /**
     * 输出json格式消息
     * @param int $code
     * @param string $message
     * @param array $result
     */
    protected static function output($code = 0, $message = "", $result = [])
    {
        parent::ajaxReturn(['code' => $code, 'message' => $message, 'result' => $result]);
    }
    /**输出json格式数据
     * @param array $data
     * Created by Red.
     * Date: 2018/7/9 10:45
     */
    protected static function output_new($data = []){
        if (func_num_args() > 2) {
            $args = func_get_args();
            array_shift($args);
            $info = array();
            $info['code'] = $data;
            $info['message'] = array_shift($args);
            $info['result'] = array_shift($args);
            parent::ajaxReturn($info);
        }
        parent::ajaxReturn($data);
    }

    /**
     * 是否已登陆
     * @return bool
     */
    protected function _checkLogin()
    {
        self::getUserInfo();
        if ($this->member_id !== false) {
            return true;
        }
        return false;
    }
    /**
     * 设置用户信息 redis存取
     */
    protected function getUserInfo()
    {
        $cache_name = $this->cache_name;
        $chche_token=$this->cache_token;
        $this->member = is_string($this->redis->$cache_name) ? @json_decode($this->redis->$cache_name) : $this->redis->$cache_name;
        $this->member_id = !empty($this->member['user_id']) ? $this->member['user_id'] : false;
    
        if($chche_token ==$this->member['token']){
             
        }else {
            $this->redirect(U('Entrust/index', '', false));
        }
        $_SESSION['USER_KEY_ID']=$this->member_id;
    
    }
    

    /**
     * 请求处理
     * @param string $type
     */
    protected function request_filter($type = '')
    {
        $message = false;
        $config = M('Config');
        $change_time = $config->where('id=83')->getField('value');

        //用不到的注释掉，不然程序还是会执行
        //$all_user = $config->where('id=59')->getField('value');
        //$transaction_false = $config->where('id=45')->getField('value');

        $time = time();
        $is_time = (($time - $change_time) - 720);

        if ($is_time > 0) {
            $rand_all_user = rand(1, 5);
            $rand_transaction_false = rand(10000, 100000) / 100;

            $config->where('id=45')->setInc('value', $rand_transaction_false);
            $config->where('id=59')->setInc('value', $rand_all_user);

            $data['id'] = '83';
            $data['value'] = $time;
            $config->save($data);
        }


        //整型过滤函数
        function get_int($number)
        {
            return intval($number);
        }

        //字符串型过滤函数
        function get_str($string)
        {
            if (!get_magic_quotes_gpc()) {
                return addslashes($string);
            }
            return $string;
        }

        /* 过滤所有GET过来变量 */
        foreach ($_GET as $get_key => $get_var) {
            if (is_numeric($get_var)) {
                $get[strtolower($get_key)] = get_int($get_var);
            } else {
                $get[strtolower($get_key)] = get_str($get_var);
            }
        }

        /* 过滤所有POST过来的变量 */
        foreach ($_POST as $post_key => $post_var) {
            if (is_numeric($post_var)) {
                $post[strtolower($post_key)] = get_int($post_var);
            } else {
                $post[strtolower($post_key)] = get_str($post_var);
            }
        }

        $ip = get_client_ip_extend();//获取当前访问者的ip
        $logFilePath = dirname(THINK_PATH) . '/ippath/';//日志记录文件保存目录
        $fileht = '.htaccess2';//被禁止的ip记录文件
        $allowtime = 60;//防刷新时间
        $allownum = 10;//防刷新次数
        $allowRefresh = 120;//在允许刷新次数之后加入禁止ip文件中

        if (!file_exists($fileht)) {
            file_put_contents($fileht, '');
        }
        $filehtarr = @file($fileht);
        if (in_array($ip . "\r\n", $filehtarr)) {
            $message = '警告：你的IP已经被禁止了！';
            if ($type === 'API') {
                return $message;
            }
            $this->error($message);
        }

        //加入禁止ip
        $time = time();
        $fileforbid = $logFilePath . 'forbidchk.dat';
        if (file_exists($fileforbid)) {
            if ($time - filemtime($fileforbid) > 30) {
                @unlink($fileforbid);
            } else {
                $fileforbidarr = @file($fileforbid);
                if ($ip == substr($fileforbidarr[0], 0, strlen($ip))) {
                    if ($time - substr($fileforbidarr[1], 0, strlen($time)) > 120) {
                        @unlink($fileforbid);
                    } else if ($fileforbidarr[2] > $allowRefresh) {
                        file_put_contents($fileht, $ip . "\r\n", FILE_APPEND);
                        @unlink($fileforbid);
                    } else {
                        $fileforbidarr[2]++;
                        file_put_contents($fileforbid, $fileforbidarr);
                    }
                }
            }
        }

        //防刷新
        $str = '';
        $file = $logFilePath . 'ipdate.dat';
        if (!file_exists($logFilePath) && !is_dir($logFilePath)) {
            mkdir($logFilePath, 0777);
        }

        if (!file_exists($file)) {
            file_put_contents($file, '');
        }

        $uri = $_SERVER['REQUEST_URI'];//获取当前访问的网页文件地址
        $checkip = md5($ip);
        $checkuri = md5($uri);
        $yesno = true;
        $ipdate = @file($file);
        foreach ($ipdate as $k => $v) {
            $iptem = substr($v, 0, 32);
            $uritem = substr($v, 32, 32);
            $timetem = substr($v, 64, 10);
            $numtem = substr($v, 74);
            if ($time - $timetem < $allowtime) {
                if ($iptem != $checkip) {
                    $str .= $v;
                } else {
                    $yesno = false;
                    if ($uritem != $checkuri) {
                        $str .= $iptem . $checkuri . $time . "\r\n";
                    } else if ($numtem < $allownum) {
                        $str .= $iptem . $uritem . $timetem . ($numtem + 1) . "\r\n";
                    } else {
                        if (!file_exists($fileforbid)) {
                            $addforbidarr = array($ip . "\r\n", time() . "\r\n", 1);
                            file_put_contents($fileforbid, $addforbidarr);
                        }
                        file_put_contents($logFilePath . 'forbided_ip.log', $ip . '--' . date('Y-m-d H:i:s', time()) . '--' . $uri . "\r\n", FILE_APPEND);
                        //$timepass = $timetem + $allowtime - $time;
                        $message = '警告：不要刷新的太频繁！';
                        if ($type === 'API') {
                            return $message;
                        }
                        $this->error($message);
                    }
                }
            }
        }

        if ($yesno) {
            $str .= $checkip . $checkuri . $time . "\r\n";
        }

        file_put_contents($file, $str);
    }

    /**
     * 获取首页积分类型涨跌信息
     * @return array
     */
    protected function get_currency_index()
    {
        $is_qu = [
            1 => 'isqu', //是区积分类型
            0 => 'noqu', //不是区积分类型
        ];

        $currency_data = [];
        foreach ($this->currency as $k => $v) {
            $list = $this->getCurrencyMessageById($v['currency_id']);
            $currency_data[$is_qu[$v['is_qu']]][$k] = array_merge($list, $v);
            $currency_data[$is_qu[$v['is_qu']]][$k]['currency_all_money'] = floatval($v['currency_all_money']);
        }
        return $currency_data;
    }

    /**
     * 检测是否需要进行信息填写(补全)
     */
    protected function User_status()
    {
        header("Content-type:text/html;charset=utf-8");
//        $list = M('Member')->where(array('member_id' => session('USER_KEY_ID')))->find();
        $list = M('Member')->where(array('member_id' => $this->member_id))->find();
        if ($list['status'] == 0) {
            $msg = L('lan_input_personal_info');
            $this->redirect('ModifyMember/simple_verify', '', 1, "<meta name='viewport' content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' /><script type='text/javascript' src='/Public/js/jquery-2.1.1.min.js'></script> <body><script type='text/javascript' src='/Public/js/layer/layer.js'></script><script>layer.msg('".$msg."')</script></body>");
            exit();
        }
    }
    /**
     * 检测是否需要进行信息填写-手机端(补全)
     */
    protected function User_mobile_status()
    {
        header("Content-type:text/html;charset=utf-8");
//        $list = M('Member')->where(array('member_id' => session('USER_KEY_ID')))->find();
        $list = M('Member')->where(array('member_id' => $this->member_id))->find();
        if ($list['status'] == 0) {
            
            $msg = L('lan_input_personal_info');
            $this->redirect('AccountManage/bank', '',1, "<meta name='viewport' content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' /><script type='text/javascript' src='/Public/js/jquery-2.1.1.min.js'></script> <body><script type='text/javascript' src='/Public/js/layer/layer.js'></script><script>layer.msg('".$msg."')</script></body>");
            exit();
        }
    }
    
    
    /**
     * 手机端是否登录
     */
    public function mobile_is_Login(){
        if (!$this->checkLogin()) {
            $platform = cookie('platform');
            //echo '<script> window.webkit.messageHandlers.iosAction.postMessage("login");</script>';
            //var_dump($platform);exit();
            //$platform='ios';
            if($platform=='android'){
                echo '<script> history.go(-1);</script>';
    
               // echo '<script> apps.gologin(1);</script>';
                
    
            }elseif($platform=='ios'){
                //var_dump($platform);exit();
    
                echo '<script> window.webkit.messageHandlers.iosAction.postMessage("login");history.go(-1);</script>';
    
                 
            }else {
    
                $this->redirect('account/index');
                 
            }
    
        }
    
    }
    public function mobile_is_Login_ios(){
        if (!$this->checkLogin()) {
            $platform = cookie('platform');
            if ($platform=='ios'){
    
            }else {
    
                echo '<script> window.webkit.messageHandlers.iosAction.postMessage("login");</script>';
            }
    
    
        }
    
    }
    /**
     * 查询钱包余额
     * @param unknown $port_number 端口号
     * @return Ambigous <number, unknown> 剩余的余额
     */
    protected function get_qianbao_balance($currency)
    {
        require_once 'App/Common/Common/easybitcoin.php';
        $bitcoin = new \Bitcoin($currency['rpc_user'], $currency['rpc_pwd'], $currency['rpc_url'], $currency['port_number']);
        $money = $bitcoin->getinfo();
        $num = empty($money['balance']) ? 0 : $money['balance'];
        return $num;
    }

    /**
     * 根据ID返回具体分类
     * @param string $id
     * @return boolean|array $list;
     */

    public function getCatById($id = null)
    {
        if (empty($id)) {
            return false;
        }
        return M('Article_category')->where(array('id' => $id))->find();
    }

    /**
     * 返回指定父类下面的二级分类
     * @param string $parentId
     * @return boolean|array $list;
     */
    public function getChildCatByParentCat($parentId = null)
    {
        if (empty($parentId)) {
            return false;
        }
        return M('Article_category')->where(array('parent_id' => $parentId))->select();
    }

    /**
     * 获取积分最新价格
     * @param int $currency_id
     * @return int
     */
    protected static function getCurrencyNewPrice($currency_id = 0)
    {
        if ($currency_id == 0) {
            return 0;
        }

        $where['currency_id'] = $currency_id;
        $where['type'] = 'buy';
        $trade_model = M('trade');
        $order = 'add_time desc';
        $result = $trade_model->field("price")->where($where)->order($order)->find();

        return !empty($result['price']) ? $result['price'] : 0;
    }


    /***
     * 获取BB兑换信息
     * @param $type all 全部
     * @from 0-api, 1-cron
     */
        public  function  bbExchange($type='all', $from=0){
            
            $coin_list = $this->coin_list ;
            if ($type != 'all' && in_array($type,$coin_list)){
                $coin_type = [];
                foreach ($coin_list as $coin_k=>$coin_v){
                        if(strpos($type,$coin_v) !== false){
                            $coin_type[$coin_k] =$coin_v;
                        }
                }
            }else{
                $coin_type = $coin_list;
            }

            $cahe_name = 'cahe_'.$type;

            $currency_data =  ($from==1) ? [] :  S($cahe_name);

            if(empty($currency_data)){
                $currency=$this->currency;
                $currency_data = [];
            }else{
                $currency = [];
            }
            //获取BTC_KOK价格，ETH_KOK价格
//             $BTC_KOK_price=self::getCurrencyMessageById(1,9,$from)['new_price'];
//             $ETH_KOK_price=self::getCurrencyMessageById(3,9,$from)['new_price'];
            foreach ($currency as $k => $v) {
                    if (!empty($v['trade_currency_id'])){
                        $trade_currency_id = explode(',',$v['trade_currency_id']);
                        foreach ($trade_currency_id as $vtId){
                            if($coin_type[$vtId]){
                                $list = self::getCurrencyMessageById($v['currency_id'],$vtId,$from);
                               $list2=[];//self::getCurrencyMessageById2($v['currency_id'],$vtId);
                               $trends = self::getTrends($v['currency_id'],$vtId);//获取走势图
                                unset($v['qianbao_key'],$v['qianbao_key1'],$v['rpc_user'],$v['rpc_user1'],$v['rpc_pwd'],$v['rpc_pwd1'],$v['rpc_url'],$v['rpc_url1'],$v['port_number'],$v['port_number1'],$v['summary_fee_address'],$v['summary_fee_pwd'],$v['qianbao_address'],$v['tibi_address'],$v['token_address']);
                                $currency_data[$coin_type[$vtId]][$k] = array_merge($list,$list2, $v);
                                $currency_data[$coin_type[$vtId]][$k]['currency_all_money'] = floatval($v['currency_all_money']);
                                $currency_data[$coin_type[$vtId]][$k]['currency_buy_fee'] = floatval($v['currency_buy_fee']);
                                $currency_data[$coin_type[$vtId]][$k]['currency_sell_fee'] = floatval($v['currency_sell_fee']);
                                $currency_data[$coin_type[$vtId]][$k]['trade_currency_mark'] = $this->currency_id_mark[$vtId];//M('Currency')->where(array('currency_id' => $vtId))->find()['currency_mark'];//交易的币英文名
                               $currency_data[$coin_type[$vtId]][$k]['trends'] = $trends;
                                $currency_data[$coin_type[$vtId]][$k]['trade_currency_id'] = $vtId;
                                $currency_data[$coin_type[$vtId]][$k]['new_price'] = format_price( $currency_data[$coin_type[$vtId]][$k]['new_price']);
                                if($this->currency_id_mark[$vtId]=='KOK'){
                                    $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']/usd2cny()) ;
                                }elseif($this->currency_id_mark[$vtId]=='USDT' ) {
                                    $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']) ;
                                }elseif($this->currency_id_mark[$vtId]=='BTC' ) {
                                    $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd(($BTC_KOK_price*$currency_data[$coin_type[$vtId]][$k]['new_price'])/usd2cny());
                                }elseif ($this->currency_id_mark[$vtId]=='ETH' ){
                                    $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd(($ETH_KOK_price*$currency_data[$coin_type[$vtId]][$k]['new_price'])/usd2cny());
                                }else {
                                    $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']/usd2cny()) ;
                                }
                               //添加交易兑取值
//                                 $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price'] = format_price( $currency_data[$coin_type[$vtId]][$k]['new_price']);
//                                 $currency_data[$v['currency_id'].'_'.$vtId][$k]['price'] = format_price( $currency_data[$coin_type[$vtId]][$k]['new_price']);
                                
//                                 if($this->currency_id_mark[$vtId]=='KOK'){
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']/usd2cny()) ;
//                                 }elseif($this->currency_id_mark[$vtId]=='USDT' ) {
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']) ;
//                                 }elseif($this->currency_id_mark[$vtId]=='BTC' ) {
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd(($BTC_KOK_price*$currency_data[$coin_type[$vtId]][$k]['new_price'])/usd2cny());
//                                 }elseif ($this->currency_id_mark[$vtId]=='ETH' ){
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd(($ETH_KOK_price*$currency_data[$coin_type[$vtId]][$k]['new_price'])/usd2cny());
//                                 }else {
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']/usd2cny()) ;
//                                 }
//                                 $currency_data[$v['currency_id'].'_'.$vtId][$k]['max_price'] =  $currency_data[$coin_type[$vtId]][$k]['max_price'];
//                                 $currency_data[$v['currency_id'].'_'.$vtId][$k]['min_price'] =  $currency_data[$coin_type[$vtId]][$k]['min_price'];

//                                 $currency_data[$v['currency_id'].'_'.$vtId][$k]['24H_done_num'] =  $currency_data[$coin_type[$vtId]][$k]['24H_done_num'];
//                                 $currency_data[$v['currency_id'].'_'.$vtId][$k]['24H_done_money'] =  $currency_data[$coin_type[$vtId]][$k]['24H_done_money'];
//                                 $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_status'] =  $currency_data[$coin_type[$vtId]][$k]['new_price_status'];
//                                 if($currency_data[$coin_type[$vtId]][$k]['new_price_status']==1){
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['change_24']= '+'.$currency_data[$coin_type[$vtId]][$k]['24H_change'];
//                                 }elseif ($currency_data[$coin_type[$vtId]][$k]['new_price_status']==2){
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['change_24']= '-'.$currency_data[$coin_type[$vtId]][$k]['24H_change'];
//                                 }else {
//                                     $currency_data[$v['currency_id'].'_'.$vtId][$k]['change_24']= $currency_data[$coin_type[$vtId]][$k]['24H_change'];
//                                 }

                            }
                        }
                    }
            }
            if(!empty($currency)) S($cahe_name,$currency_data,20);
            return $currency_data;
        }
        
        /***
         * 获取BB兑换信息
         * @param $type all 全部
         * @from 0-api, 1-cron
         */
        public  function  bbExchange_index($type='all', $from=0){
        
            $coin_list = $this->coin_list ;
            if ($type != 'all' && in_array($type,$coin_list)){
                $coin_type = [];
                foreach ($coin_list as $coin_k=>$coin_v){
                    if(strpos($type,$coin_v) !== false){
                        $coin_type[$coin_k] =$coin_v;
                    }
                }
            }else{
                $coin_type = $coin_list;
            }
        
            $cahe_name = 'index_cahe_'.$type;
            if(empty(S($cahe_name))){
                $from=1;
            }
        
            $currency_data =  ($from==1) ? [] :  S($cahe_name);
        
            if(empty($currency_data)){
                $currency=$this->currency;
                $currency_data = [];
            }else{
                $currency = [];
            }
            //获取BTC_KOK价格，ETH_KOK价格
            $BTC_KOK_price=self::getCurrencyMessageById(1,9,$from)['new_price'];
            $ETH_KOK_price=self::getCurrencyMessageById(3,9,$from)['new_price'];
            foreach ($currency as $k => $v) {
                if (!empty($v['trade_currency_id'])){
                    $trade_currency_id = explode(',',$v['trade_currency_id']);
                    foreach ($trade_currency_id as $vtId){
                        if($coin_type[$vtId]){
                            $list = self::getCurrencyMessageById($v['currency_id'],$vtId,$from);
                            $list2=[];//self::getCurrencyMessageById2($v['currency_id'],$vtId);
                            //$trends = self::getTrends($v['currency_id'],$vtId);//获取走势图
                            unset($v['qianbao_key'],$v['qianbao_key1'],$v['rpc_user'],$v['rpc_user1'],$v['rpc_pwd'],$v['rpc_pwd1'],$v['rpc_url'],$v['rpc_url1'],$v['port_number'],$v['port_number1'],$v['summary_fee_address'],$v['summary_fee_pwd'],$v['qianbao_address'],$v['tibi_address'],$v['token_address']);
                            $currency_data[$coin_type[$vtId]][$k] = array_merge($list,$list2, $v);
                            $currency_data[$coin_type[$vtId]][$k]['currency_all_money'] = floatval($v['currency_all_money']);
                            $currency_data[$coin_type[$vtId]][$k]['currency_buy_fee'] = floatval($v['currency_buy_fee']);
                            $currency_data[$coin_type[$vtId]][$k]['currency_sell_fee'] = floatval($v['currency_sell_fee']);
                            $currency_data[$coin_type[$vtId]][$k]['trade_currency_mark'] = $this->currency_id_mark[$vtId];//M('Currency')->where(array('currency_id' => $vtId))->find()['currency_mark'];//交易的币英文名
                           // $currency_data[$coin_type[$vtId]][$k]['trends'] = $trends;
                            $currency_data[$coin_type[$vtId]][$k]['trade_currency_id'] = $vtId;
                            $currency_data[$coin_type[$vtId]][$k]['new_price'] = format_price( $currency_data[$coin_type[$vtId]][$k]['new_price']);
                            if($this->currency_id_mark[$vtId]=='KOK'){
                                $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']/usd2cny()) ;
                            }elseif($this->currency_id_mark[$vtId]=='USDT' ) {
                                $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']) ;
                            }elseif($this->currency_id_mark[$vtId]=='BTC' ) {
                                $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd(($BTC_KOK_price*$currency_data[$coin_type[$vtId]][$k]['new_price'])/usd2cny());
                            }elseif ($this->currency_id_mark[$vtId]=='ETH' ){
                                $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd(($ETH_KOK_price*$currency_data[$coin_type[$vtId]][$k]['new_price'])/usd2cny());
                            }else {
                                $currency_data[$coin_type[$vtId]][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']/usd2cny()) ;
                            }
                            //添加交易兑取值
                            $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price'] = format_price( $currency_data[$coin_type[$vtId]][$k]['new_price']);
                            $currency_data[$v['currency_id'].'_'.$vtId][$k]['price'] = format_price( $currency_data[$coin_type[$vtId]][$k]['new_price']);
        
                            if($this->currency_id_mark[$vtId]=='KOK'){
                                $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']/usd2cny()) ;
                            }elseif($this->currency_id_mark[$vtId]=='USDT' ) {
                                $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']) ;
                            }elseif($this->currency_id_mark[$vtId]=='BTC' ) {
                                $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd(($BTC_KOK_price*$currency_data[$coin_type[$vtId]][$k]['new_price'])/usd2cny());
                            }elseif ($this->currency_id_mark[$vtId]=='ETH' ){
                                $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd(($ETH_KOK_price*$currency_data[$coin_type[$vtId]][$k]['new_price'])/usd2cny());
                            }else {
                                $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_usd'] = format_price_usd($currency_data[$coin_type[$vtId]][$k]['new_price']/usd2cny()) ;
                            }
                            $currency_data[$v['currency_id'].'_'.$vtId][$k]['max_price'] =  $currency_data[$coin_type[$vtId]][$k]['max_price'];
                            $currency_data[$v['currency_id'].'_'.$vtId][$k]['min_price'] =  $currency_data[$coin_type[$vtId]][$k]['min_price'];
                            $currency_data[$v['currency_id'].'_'.$vtId][$k]['24H_done_num'] =  $currency_data[$coin_type[$vtId]][$k]['24H_done_num'];
                            $currency_data[$v['currency_id'].'_'.$vtId][$k]['24H_done_money'] =  $currency_data[$coin_type[$vtId]][$k]['24H_done_money'];
                            $currency_data[$v['currency_id'].'_'.$vtId][$k]['new_price_status'] =  $currency_data[$coin_type[$vtId]][$k]['new_price_status'];
                            if($currency_data[$coin_type[$vtId]][$k]['new_price_status']==1){
                                $currency_data[$v['currency_id'].'_'.$vtId][$k]['change_24']= '+'.$currency_data[$coin_type[$vtId]][$k]['24H_change'];
                            }elseif ($currency_data[$coin_type[$vtId]][$k]['new_price_status']==2){
                                $currency_data[$v['currency_id'].'_'.$vtId][$k]['change_24']= '-'.$currency_data[$coin_type[$vtId]][$k]['24H_change'];
                            }else {
                                $currency_data[$v['currency_id'].'_'.$vtId][$k]['change_24']= $currency_data[$coin_type[$vtId]][$k]['24H_change'];
                            }
        
                        }
                    }
                }
            }
            if(!empty($currency)) S($cahe_name,$currency_data,60);
            return $currency_data;
        }
    /**
     * 获取走势图
     * @param $currency_id
     * @return string
     *    * 新增加  $toId 目标兑换币
     */
    public function getTrends($currency_id,$toId='',$form=0)
    {
        //$trade = M()->query("SELECT price from yang_trade WHERE add_time>UNIX_TIMESTAMP(DATE_SUB(NOW(),INTERVAL  6 HOUR)) and currency_id = $currency_id and type = 'buy' order by add_time limit 100");
        //$trade = M()->query("SELECT price from yang_trade WHERE currency_id = $currency_id and type = 'sell' order by add_time limit 100");
        /*$where['currency_id'] = $currency_id;
        $where['type'] = 'buy';
        $time = time();
        $trade = M('Trade')->field('price as max_price')->where($where)->where("add_time>$time-60*60*24")->order('add_time desc')->limit(35)->select();*/
        $s_name =  'ceche_getTrends_'.$currency_id.'_'.$toId;
        $return =  $retult = ($form ==1) ? [] :S($s_name);
        if(empty($retult)){

            $model = M('Trade');
            $step = 3600;
            $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
            $where['currency_id'] = $currency_id;
            empty($toId)?:$where['currency_trade_id'] = $toId;
            $where['type'] = 'buy';
            $max_price = $model->field('max(price) as max_price,' . "$areaKey" . ' min')->where($where)->group($areaKey)->order('min asc')->limit(35)->select();

            $model = M('Trade');
            $step = 3600;
            $areaKey = "floor(yang_trade.add_time - yang_trade.add_time % " . $step . ")";
            $where['currency_id'] = $currency_id;
            $where['type'] = 'buy';
            $min_price = $model->field('min(price) as min_price,' . "$areaKey" . ' min')->where($where)->group($areaKey)->order('min asc')->limit(35)->select();

            foreach ($max_price as $k => $v) {
                $trade[$k] = array_merge($max_price[$k], $min_price[$k]);
            }

            $list = array();
            if ($trade) {
                for ($i = 0; $i < 35; $i++) {
                    if ($trade[$i]['max_price'] != null) {
                        $list[$i] = floatval(($trade[$i]['max_price']+$trade[$i]['min_price'])/2);
                        //$list[$i] = floatval($trade[$i]['max_price']);
                    }
                }
                //$max = max($list);
                $min = min($list);
                for ($i = 0; $i < count($list); $i++) {
                    //$list[$i] = (100 / count($list)) * $i . "," . (30 - (30 / (max($list)+min($list)) * $list[$i]));
                    //$list[$i] = (100 / count($list)) * $i . "," . (30-30*$list[$i]/$max);
                    $list[$i] =  ($list[$i]-$min)/33;
                }
                //$return = implode(" ", $list);
                $return = json_encode($list);
            } else {
                //$return = "0,29 100,29";
                $return = "0";
            }
            S($s_name,$return,210);
        }
        return $return;
    }


    /**
     * 获取当前积分类型的信息
     * @param int $id 积分类型id
     * @return 24H成交量 24H_done_num  24H成交额 24H_done_money 24H涨跌 24H_change 7D涨跌  7D_change
     * @return 最新价格 new_price 买一价 buy_one_price 卖一价 sell_one_price 最高价 max_price 最低价 min_price
     *
     * 新增加  $toId 目标兑换币
     */
    public  function getCurrencyMessageById($id,$toId='',$from=0)
    {
        static  $usd2cny  = 0;
        $usd2cny =  $usd2cny ?:  usd2cny();
        $all_name = 'rs_all' . $id.'_'.$toId;
        $data = ($from==1) ? [] : S($all_name);
        if (empty($data)) {
            $where['currency_id'] = $id;
            empty($toId)?: $where['currency_trade_id'] = $toId;
            $where['type'] = 'buy';
            $Currency_model = M('Currency');
            $trade_model = M('trade');
            $list = ($this->currency_id_value[$id])? :($Currency_model->where(array('currency_id' => $id,'is_line' => 1))->cache()->find());

            $time = time();
            //一天前的时间
            $old_time = strtotime(date('Y-m-d', $time));

            //最新价格
            $order = 'add_time desc';
            $rs = $trade_model->where($where)->order($order)->cache(true,60)->find();

            $data['new_price'] = sprintf('%.8f',$rs['price']);
            $data['new_price_usd'] = sprintf('%.4f',round($rs['price']/$usd2cny,2));//对应美元价格
            $data['new_price_kok'] = sprintf('%.4f',round($rs['price']*$usd2cny/1,2));//对应美元价格

            //判断价格是升是降
            //$re = $trade_model->where($where)->where("add_time<$old_time")->order($order)->find();
           // $lastdate = $trade_model->field('price')->where("add_time <= 60*60*24 and currency_id={$id} ".($toId?"and currency_trade_id='{$toId}'":"")." and type='sell' ")->order('add_time desc')->limit(1)->find();
//            if ($lastdate['price'] > $rs['price']) {
//                //说明价格下降
//                $data['new_price_status'] = 0;
//            } else {
//                $data['new_price_status'] = 1;
//            }

            //24H涨跌
//            $lastdate = $trade_model->field('price')->where("add_time <= UNIX_TIMESTAMP(CAST(CAST(SYSDATE()AS DATE)AS DATETIME)) and currency_id={$id} ".($toId?"and currency_trade_id='{$toId}'":"")." and type='sell' ")->order('add_time desc')->limit(1)->find();

            $where2['currency_id'] = $id;
            empty($toId)?: $where2['currency_trade_id'] = $toId;
            $where2['type'] = 'sell';
            //3D涨跌
//             $re = $trade_model->where($where2)->where("add_time<$time-60*60*24*3")->order($order)->find();
//             $data['3D_change'] = sprintf("%.2f", ($rs['price'] - $re['price']) / $re['price'] * 100);
//             if ($data['3D_change'] == 0) {
//                 $data['3D_change'] = '0.00';
//             }

//             //7D涨跌
//             $re = $trade_model->where($where2)->where("add_time<$time-60*60*24*7")->order($order)->field('price')->find();
//             $data['7D_change'] = sprintf("%.2f", ($rs['price'] - $re['price']) / $re['price'] * 100);
//             if ($data['7D_change'] == 0) {
//                 $data['7D_change'] = '0.00';
//             }

//             //买一价
//             $where['type'] = 'buy';
//             $rs = $trade_model->field('price,add_time')->where($where)->order($order)->find();
//             $data['buy_one_price'] = $rs['price'];

//             //卖一价
//             $where['type'] = 'sell';
//             $rs = $trade_model->field('price,add_time')->where($where)->order("price")->find();
//             $data['sell_one_price'] = $rs['price'];


            //24H成交量
            $rs = $trade_model->field("SUM(num) as num ,SUM(num * price) as numPrice")->where($where)->where("add_time>$time-60*60*24")->find();

            if ($rs['num'] == 0) {
                $data['24H_done_num'] = '0.00';
            }else {
            $data['24H_done_num'] = $rs['num'] * 2 + $list['num_number'];
            }

            //24H成交额
//            $rs = $trade_model->field('num*price')->where($where)->where("add_time>$time-60*60*24")->sum('num*price');
            if ($rs['numPrice'] == 0) {
                $data['24H_done_money'] = '0.00';
            } else {
            $data['24H_done_money'] = $rs['numPrice'] * 2 + $list['num_number'] * $data['new_price'];
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
            $data['24H_change'] = sprintf("%.2f", ($data['new_price']  - $rs['price']) / $rs['price'] * 100);
            $data['24H_change_price'] = sprintf("%.2f", ($data['new_price']  - $rs['price']));//24H价格变化值
            if ($data['24H_change'] == 0) {
                $data['24H_change'] = '0.00';
            }

            s($all_name, $data, 15);
        }

        //返回
        return $data;
    }
    /*
     *
     * 新增加  $toId 目标兑换币
     */
    public static function getCurrencyMessageById2($id,$toId='')
    {
        static  $usd2cny  = 0;
        $usd2cny =  $usd2cny ?:  usd2cny();
        $all_name = 'rs_all2' . $id.'_'.$toId;
        $data = S($all_name);
        if (empty($data)) {
            $where['currency_id'] = $id;
            empty($toId)?: $where['currency_trade_id'] = $toId;
            $where['type'] = 'buy';
            $Currency_model = M('Currency');
            $trade_model = M('trade');

            $list = $Currency_model->where(array('currency_id' => $id,'is_line' => 1))->find();
            $time = time();
            //一天前的时间
            $old_time = strtotime(date('Y-m-d', $time));

            //最新价格
            $order = 'add_time desc';
            $rs = $trade_model->where($where)->order($order)->find();
            $data['new_price'] = sprintf('%.6f',$rs['price']);
            $data['new_price_usd'] = sprintf('%.4f',round($rs['price']/$usd2cny,2));//对应美元价格

            /*
                    //判断价格是升是降
                    $re = $trade_model->where($where)->where("add_time<$old_time")->order($order)->find();
                    if ($re['price'] > $rs['price']) {
                        //说明价格下降
                        $data['new_price_status'] = 0;
                    } else {
                        $data['new_price_status'] = 1;
                    }
                    */
            //24H涨跌
            $where2['currency_id'] = $id;
            empty($toId)?: $where2['currency_trade_id'] = $toId;
            $where2['type'] = 'sell';
//             $lastdate = $trade_model->field('price')->where("add_time <= UNIX_TIMESTAMP(CAST(CAST(SYSDATE()AS DATE)AS DATETIME)) and currency_id={$id} ".($toId?"and currency_trade_id='{$toId}'":"")." and type='sell' ")->order('add_time desc')->limit(1)->find();
//             $data['24H_change'] = sprintf("%.2f", ($rs['price'] - $lastdate['price']) / $lastdate['price'] * 100);
//             $data['24H_change_price'] = sprintf("%.2f", ($rs['price'] - $lastdate['price']));//24H价格变化值
//             if ($data['24H_change'] == 0) {
//                 $data['24H_change'] = '0.00';
//             }

//             //3D涨跌
//             $re = $trade_model->where($where2)->where("add_time<$time-60*60*24*3")->order($order)->find();
//             $data['3D_change'] = sprintf("%.2f", ($rs['price'] - $re['price']) / $re['price'] * 100);
//             if ($data['3D_change'] == 0) {
//                 $data['3D_change'] = '0.00';
//             }

//             //7D涨跌
//             $re = $trade_model->where($where2)->where("add_time<$time-60*60*24*7")->order($order)->find();
//             $data['7D_change'] = sprintf("%.2f", ($rs['price'] - $re['price']) / $re['price'] * 100);
//             if ($data['7D_change'] == 0) {
//                 $data['7D_change'] = '0.00';
//             }

            //24H成交量
//             $rs = $trade_model->field('num')->where($where)->where("add_time>$time-60*60*24")->sum('num');
//             $data['24H_done_num']=$rs['num'];
//             if ($data['24H_done_num'] == 0) {
//                 $data['24H_done_num'] = '0.00';
//             }else {
//                 $data['24H_done_num'] = $rs * 2 + $list['num_number'];
//             }

            //24H成交额
//             $rs = $trade_model->field('num*price')->where($where)->where("add_time>$time-60*60*24")->sum('num*price');
//             if ($rs == 0) {
//                 $data['24H_done_money'] = '0.00';
//             } else {
//                 $data['24H_done_money'] = $rs * 2 + $list['num_number'] * $data['new_price'];
//             }

//             //最低价
//             $sql_time = $time - 60 * 60 * 24 * 7;
//             $rs = $trade_model->field('min(price) as price')->where($where)->where("add_time>$sql_time")->find();
//             $data['min_price'] = $rs['price'];

//             //最高价

//             $rs = $trade_model->field('max(price) as price')->where($where)->where("add_time>$sql_time")->find();
//             $data['max_price'] = $rs['price'];
            /*
                     //买一价
                     $where['type'] = 'buy';
                     $rs = $trade_model->field('price,add_time')->where($where)->order($order)->find();
                     $data['buy_one_price'] = $rs['price'];

                     //卖一价
                     $where['type'] = 'sell';
                     $rs = $trade_model->field('price,add_time')->where($where)->order("price")->find();
                     $data['sell_one_price'] = $rs['price'];
                     */
            s($all_name, $data, 15);
        }

        //返回
        return $data;
    }

    //获取对应积分类型，该会员的资产
    public function getCurrencyUser($uid, $cid)
    {
        $where['member_id'] = $uid;
        $where['currency_id'] = $cid;
        $rs = M('Currency_user')->field('*,(num+forzen_num) as count')->where($where)->find();
        return $rs;
    }

    //获取全部积分类型信息
    public static function currency()
    {
        return M('Currency')->where('is_line=1 ')->order('sort ASC')->select();
        //return M('Currency')->order('sort ASC')->select();
    }

    //获取区块全部积分类型信息
    public static function currency_isqu()
    {
        return M('Currency')->where('is_line=1 and is_qu=1 ')->order('sort ASC')->select();
    }

    //获取没区块全部积分类型信息
    public static function currency_noqu()
    {
        return M('Currency')->where('is_line=1 and is_qu<1 ')->order('sort ASC')->select();
    }

    //获取单独积分类型信息
    public function currency_one($id)
    {
        $list = M('Currency')->where(array('currency_id' => $id))->find();
        return $list;
    }

    //验证前台登录
    public function checkLogin()
    {
        if ( !$_SESSION['USER_KEY_ID']) {
            return false;
        }
        return true;
    }

    //空操作
    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }


    /**
     * 根据ID返回具体分类
     * @param string $id
     * @return boolean|array $list;
     */

    /**
     * @param $id 用户ID
     * @return bool 返回用户未读消息条数
     */
    public function pullMessageCount($id)
    {
        if (empty($id)) {
            return false;
        }
        if (!$count = M('message')->where(array('member_id' => $id, 'status' => 0))->count()) {
            return false;
        }
        return $count;
    }

    /**
     * 添加委托表
     * @param  $member_id   用户id
     * @param  $currency_id 积分类型id
     * @param  $all_num  全部数量
     * @param  $price
     * @param  $type   卖出单1 还是买入单2
     * @param  $fee  手续费
     * @return
     */
    public function addEntrust($member_id, $currency_id, $all_num, $price, $type, $fee)
    {
        $data['member_id'] = $member_id;
        $data['currency_id'] = $currency_id;
        $data['all_num'] = $all_num;
        $data['surplus_num'] = $all_num;
        $data['price'] = $price;
        $data['add_time'] = time();
        $data['type'] = $type;
        $data['fee'] = $fee;
        $data['status'] = 0;
        $list = M('Entrust')->add($data);
        if ($list) {
            return $list;
        } else {
            return false;
        }
    }

    /**
     *  /**
     * 添加消息库
     * @param int $member_id 用户ID -1 为群发
     * @param int $type 分类  4=系统  -1=文章表系统公告 -2 个人信息
     * @param String $title 标题
     * @param String $content 内容
     * @return bool|mixed  成功返回增加Id 否则 false
     */

    public function addMessage_all($member_id, $type, $title, $content)
    {
        $data['u_id'] = $member_id;
        $data['type'] = $type;
        $data['title'] = $title;
        $data['content'] = $content;
        $data['add_time'] = time();
        $id = M('Message_all')->add($data);
        if ($id) {
            return $id;
        } else {
            return false;
        }
    }

    /**
     * 添加财务日志方法
     * @param unknown $member_id
     * @param unknown $type
     * @param unknown $content
     * @param unknown $money
     * @param unknown $money_type 收入=1/支出=2
     * @param unknown $currency_id 积分类型id 0是rmb
     * @return
     */
    public function addFinance($member_id, $type, $content, $money, $money_type, $currency_id, $trade_id=0)
    {
        $data['member_id'] = $member_id;
        $data['trade_id'] = $trade_id;
        $data['type'] = $type;
        $data['content'] = $content;
        $data['money_type'] = $money_type;
        $data['money'] = $money;
        $data['add_time'] = time();
        $data['currency_id'] = $currency_id;
        $data['ip'] = get_client_ip_extend();
        $list = M('Finance')->add($data);
        if ($list) {
            return $list;
        } else {
            return false;
        }
    }

    //修正众筹表 计算剩余数量  修改状态
    public function checkZhongchou()
    {
        $list = M('Issue')->field('id,add_time,end_time,num,num_nosell,zhongchou_success_bili,status')->select();
        foreach ($list as $k => $v) {
            $where['id'] = $v['id'];
            if ($v['status'] == 3) {
                M('Issue')->where($where)->setField('end_time', time());
                continue;
            }
            if ($v['add_time'] > time()) {
                M('Issue')->where($where)->setField('status', 0);
            }
            if ($v['add_time'] < time() && $v['end_time'] > time()) {
                M('Issue')->where($where)->setField('status', 1);
            }
            if ($v['end_time'] < time()) {
                M('Issue')->where($where)->setField('status', 2);
                M('Issue')->where($where)->setField('end_time', time());
            }
            $num = M('Issue_log')->where('iid=' . $v['id'])->sum('num');
            M('Issue')->where($where)->setField('deal', $v['num'] - $num - $v['num_nosell']);
            $limit_num = $v['num'] * $v['zhongchou_success_bili'] - $v['num_nosell'];
            if ($num >= $limit_num) {
                M('Issue')->where($where)->setField('status', 2);
            }
        }
    }
    
    public function checkZhongchou_one($id)
    {
        $where['id'] = $id;
        $v = M('Issue')->field('id,add_time,end_time,num,num_nosell,zhongchou_success_bili,status')->where($where)->find();
         
        $where['id'] = $v['id'];
        if ($v['status'] == 3) {
            M('Issue')->where($where)->setField('end_time', time());
        }
        if ($v['add_time'] > time()) {
            M('Issue')->where($where)->setField('status', 0);
        }
        if ($v['add_time'] < time() && $v['end_time'] > time()) {
            M('Issue')->where($where)->setField('status', 1);
        }
        if ($v['end_time'] < time()) {
            M('Issue')->where($where)->setField('status', 2);
            M('Issue')->where($where)->setField('end_time', time());
        }
        $num = M('Issue_log')->where('iid=' . $v['id'])->sum('num');
    
        if(empty($num)){
            $num=0;
        }
        M('Issue')->where($where)->setField('deal', $v['num'] - $num - $v['num_nosell']);
        $limit_num = $v['num'] * $v['zhongchou_success_bili'] - $v['num_nosell'];
        if ($num >= $limit_num) {
            M('Issue')->where($where)->setField('status', 2);
        }
    }

    //修正众筹表 计算后台操作剩余数量
    public function checkZhongchouAdmin()
    {
        $list = M('Issue')->field('id,num_nosell,admin_num')->select();
        foreach ($list as $k => $v) {
            $where['id'] = $v['id'];
            $num = M('Issue_log')->where('iid=' . $v['id'])->sum('num');
            M('Issue')->where($where)->setField('admin_deal', $v['admin_num'] - $num - $v['num_nosell']);
        }
    }

    //获取会员一次众筹有几次记录
    public function getIssuecountById($uid, $iid)
    {
        if (empty($uid)) {
            return 0;
        }
        $list = M('Issue_log')->where(array('uid' => $uid, 'iid' => $iid))->count();
        if ($list) {
            return $list;
        } else {
            return false;
        }
    }
    //超过时限退出登录方法
    //登录时间存在session里，每次判断当前时间比较，时间过了就清掉SESSION记录
    protected function login_limit_time()
    {
        if (!empty($_SESSION['login_time'])) {
            if (!empty($this->config['time_limit'])) {
                if ($_SESSION['login_time'] < time() - $this->config['time_limit'] * 60) {
                    $_SESSION['login_time'] = null;
                    $_SESSION['USER_KEY_ID'] = null;
                    $_SESSION['USER_KEY'] = null;
                    $_SESSION['STATUS'] = null;
                    $this->redirect('Index/index');
                }
            }
        }
        $time = time();
        $_SESSION['login_time'] = $time;
    }
    //设置交易时间，超时不开交易方法

    /**
     * 实例化积分类型
     * @param unknown $currency_id 积分类型id
     * @return unknown
     */
    public function getCurrencynameById($currency_id)
    {
        if ($currency_id == 0) {
            return array('currency_name' => '人民币', 'currency_mark' => 'CNY', 'currency_buy_fee' => 0, 'currency_sell_fee' => 0);
        }
        $where['currency_id'] = $currency_id;

        $list = M('Currency')->field('currency_name,currency_mark,currency_buy_fee,currency_sell_fee')->where($where)->find();
        return $list;
    }

    /**
     *
     * @param int $currency_id 积分类型id
     * @return array 积分类型结果集
     */
    protected function getCurrencyByCurrencyId($currency_id = 0)
    {
        if (empty($currency_id)) {
            $where['currency_id'] = array('gt', $currency_id);
        } else {
            $where['currency_id'] = array('eq', $currency_id);
        }
        //获取交易积分类型信息
        $list = M('Currency')->field("currency_id,currency_name,currency_mark,currency_buy_fee,currency_sell_fee,trade_currency_id,is_lock,rpc_url,rpc_pwd,rpc_user,port_number,currency_all_tibi,is_limit,max_limit,min_limit,is_time,max_time,min_time,sort,limit_in,tcoin_fee,trade_day6,trade_day7,currency_type,token_address,tibi_address,currency_min_tibi")->where($where)->select();
        if (!empty($currency_id)) {
            return $list[0];
        } else {
            return $list;
        }
    }

    /**
     *
     * @param int $currency_id 积分类型id
     * @return array 积分类型结果集
     */
    protected function getCurrencyByCurrencyId2($currency_id = 0)
    {
        if (empty($currency_id)) {
            $where['currency_id'] = array('gt', $currency_id);
        } else {
            $where['currency_id'] = array('eq', $currency_id);
        }
        //获取交易积分类型信息
        $list = M('Currency2')->field("currency_id,currency_name,currency_mark,currency_buy_fee,currency_sell_fee,trade_currency_id,is_lock,rpc_url,rpc_pwd,rpc_user,port_number,currency_all_tibi,is_limit,max_limit,min_limit,is_time,max_time,min_time,sort,limit_in")->where($where)->select();
        if (!empty($currency_id)) {
            return $list[0];
        } else {
            return $list;
        }
    }

    /**
     * 获取用户名
     * @param unknown $member_id 用户id
     * @return unknown
     */
    public function setUnameById($member_id)
    {

        $where['member_id'] = $member_id;
        $list = M('Member')->field('name')->where($where)->find();
        if (!empty($list)) {
            return $list['name'];
        }
    }

    /**
     * 设置用户资金表 字段值
     * @param int $member_id 用户id
     * @param int $currenty_id 积分类型id
     * @param string $key 字段名称
     * @param string $value 字段值
     * @return  boolean 返回执行结果
     */
    protected function setCurrentyMemberByMemberId($member_id, $currenty_id, $key, $value)
    {
        return M("Currency_user")->where(array('member_id' => $member_id, 'currency_id' => $currenty_id))->setField($key, $value);


    }

    /**
     * 获取指定数量个人挂单记录
     * @param int $num 数量
     */
    protected function getOrdersByUser($num, $currency_id,$toId='')
    {
        $where2['currency_id'] = $currency_id;
        $cardinal_number2 = M('Currency')->field("cardinal_number")->where($where2)->select();
        foreach ($cardinal_number2 as $k => $v) {

        }
        $cardinal_number = $cardinal_number2[$k]['cardinal_number'];
        $where['member_id'] = $_SESSION['USER_KEY_ID'] ? $_SESSION['USER_KEY_ID'] : $this->member_id;
        $where['status'] = array('in', array(0, 1));
        $where['currency_id'] = $currency_id;
        empty($toId)?:$where['currency_trade_id'] = $toId;
        $list = M('Orders')->where($where)->order("add_time desc")->limit($num)->select();
        foreach ($list as $k => $v) {
            $list[$k]['bili'] = 100 - ($v['trade_num'] / $v['num'] * 100);
            $list[$k]['cardinal_number'] = $cardinal_number;
            $list[$k]['type_name'] = fomatOrdersType($v['type']);
            $list[$k]['price'] = sprintf('%.4f',round($v['price'],4));
            $list[$k]['price_usd'] = sprintf('%.2f',round($v['price']/usd2cny(),2));
        }
        return $list;

    }
    /**
     * 获取指定数量个人历史挂单记录
     * @param int $num 数量
     */
    protected function getOrdersByUser_history($num, $currency_id,$toId='')
    {
        $where2['currency_id'] = $currency_id;
        $cardinal_number2 = M('Currency')->field("cardinal_number")->where($where2)->select();
        foreach ($cardinal_number2 as $k => $v) {
    
        }
        $cardinal_number = $cardinal_number2[$k]['cardinal_number'];
        $where['member_id'] = $_SESSION['USER_KEY_ID'] ? $_SESSION['USER_KEY_ID'] : $this->member_id;
        $where['status'] = array('in', array(-1, 2));
        //一周时间限制
        $where['add_time']=array('egt',time()-60*60*24*7);
        $where['currency_id'] = $currency_id;
        empty($toId)?:$where['currency_trade_id'] = $toId;
        $list = M('Orders')->where($where)->order("add_time desc")->limit($num)->select();
        foreach ($list as $k => $v) {
            $list[$k]['bili'] = 100 - ($v['trade_num'] / $v['num'] * 100);
            $list[$k]['cardinal_number'] = $cardinal_number;
            $list[$k]['type_name'] = fomatOrdersType($v['type']);
            $list[$k]['price'] = sprintf('%.4f',round($v['price'],4));
            $list[$k]['price_usd'] = sprintf('%.2f',round($v['price']/usd2cny(),2));
        }
        return $list;
    
    }

    /**
     * 设置账户资金
     * @param int $currency_id 积分类型ID
     * @param int $num 交易数量
     * @param char $inc_dec setDec setInc 是加钱还是减去
     * @param char forzen_num num
     */
    protected function setUserMoney($member_id, $currency_id, $num, $inc_dec, $field)
    {
        $inc_dec = strtolower($inc_dec);
        $field = strtolower($field);
        //允许传入的字段
        if (!in_array($field, array('num', 'forzen_num'))) {
            return false;
        }
        //如果是RMB
        if ($currency_id == 0) {
            //修正字段
            switch ($field) {
                case 'forzen_num':
                    $field = 'forzen_rmb';
                    break;
                case 'num':
                    $field = 'rmb';
                    break;
            }
            switch ($inc_dec) {
                case 'inc':
                    $msg = M('Member')->where(array('member_id' => $member_id))->setInc($field, $num);
                    break;
                case 'dec':
                    $msg = M('Member')->where(array('member_id' => $member_id))->setDec($field, $num);
                    break;
                default:
                    return false;
            }
            return $msg;
        } else {
            switch ($inc_dec) {
                case 'inc':
                    $msg = M('Currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setInc($field, $num);
                    break;
                case 'dec':
                    $msg = M('Currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setDec($field, $num);
                    break;
                default:
                    return false;
            }
            return $msg;
        }
    }

    /**
     * 返回指定状态的挂单记录
     * @param int $status -1 0 1 2
     * @param int $num 数量
     * @param int $currency_id 积分类型id
     */
    protected function getOrdersByStatus($status, $num, $currency_id,$toId='')
    {
        $where['currency_id'] = $currency_id;
        $where['status'] = $status;
        empty($toId)?:$where['currency_trade_id'] = $toId;
        $where['type'] = array('neq', 'onebuy');
        return M('Orders')->where($where)->limit($num)->order('trade_time desc')->select();
    }
    
    /**
     * 返回指定状态的挂单记录
     * @param int $status -1 0 1 2
     * @param int $num 数量
     * @param int $currency_id 积分类型id
     */
    protected function getOrdersByStatus_all($status, $num, $currency_id,$toId)
    {
        $where['currency_id'] = $currency_id;
        empty($toId)?:$where['currency_trade_id'] = $toId;
        $where['status'] = array('in','2,5');;
        $where['type'] = array('neq', 'onebuy');
        $where['trade_time']=array('elt',time());
        return M('Orders')->where($where)->limit($num)->order('trade_time desc')->select();
    }

    /**
     * 获取指定数量的成交记录
     * @param int $num
     */
    protected function getTradesByNum($currency_id)
    {
        $where['currency_id'] = $currency_id;
        return M('Trade')->where($where)->order('add_time desc')->select();
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

            $member = M('member')->where(array('member_id' => $this->member_id))->find();
            $num = $member[$field];
        } else {
            $currency_user = M('Currency_user')->where(array('member_id' => $this->member_id, 'currency_id' => $currency_id))->find();
            $num = $currency_user[$field];
        }

        return number_format($num, 4, '.', '');
    }

    /**
     * 返回指定数量排序的挂单记录
     * @param char $type buy sell
     * @param int $num 数量
     * @param char $order 排序 desc asc
     * @param  int $toId 对币ID
     */
    protected function getOrdersByType($currencyid, $type, $num, $order,$toId='')
    {
        $where2['currency_id'] = $currencyid;
        $cardinal_number2 = M('Currency')->field("cardinal_number")->where($where2)->select();
        foreach ($cardinal_number2 as $k => $v) {

        }
        $cardinal_number = $cardinal_number2[$k]['cardinal_number'];
        $where['type'] = array('eq', $type);
        $where['status'] = array('in', array(0, 1));
        $where['currency_id'] = $currencyid;
        empty($toId)?:$where['currency_trade_id'] = $toId;
        $list = M('Orders')->field("sum(num) as num,sum(trade_num) as trade_num,price,type,status")
            ->where($where)->group('price')->order("price $order, add_time asc")->limit($num)->select();
        foreach ($list as $k => $v) {
            $list[$k]['bili'] = 100 - ($v['trade_num'] / $v['num'] * 100);
            $list[$k]['cardinal_number'] = $cardinal_number;
//             $list[$k]['price'] = sprintf('%.4f',round($v['price'],4));
        }
        return $list;
    }
    
    /**
     * 返回指定数量排序的挂单记录
     * @param char $type buy sell
     * @param int $num 数量
     * @param char $order 排序 desc asc
     */
    protected function getOrdersByType_new($currencyid, $type, $num, $order,$toId='')
    {
        // $where2['currency_id'] = $currencyid;
        // $cardinal_number = M('Currency')->field("cardinal_number")->where($where2)->find()['cardinal_number'];
        $where['type'] = array('eq', $type);
        $where['status'] = array('in', array(0, 1));
        $where['currency_id'] = $currencyid;
        empty($toId)?:$where['currency_trade_id'] = $toId;
        $list = M('Orders')->field("sum(num) as num,sum(trade_num) as trade_num,price,type,status")
        ->where($where)->group('price')->order("price $order, add_time asc")->limit($num)->select();
    
        return $list;
    }

    /**
     * 返回昨天最后价格
     * @param int $currency_id 数量
     * @param char $order 排序 desc asc
     * @param int $toId 对币ID
     */
    protected function getlastmessage($currency_id,$toId='')
    {
        $lastdate = M('Trade')->field('price')->where("add_time <= UNIX_TIMESTAMP(CAST(CAST(SYSDATE()AS DATE)AS DATETIME)) and currency_id={$currency_id} ".($toId? " and currency_trade_id = '{$toId}'" : "")." and type='buy' ")->order('add_time desc')->limit(1)->find();
        $lastdate = $lastdate['price'];
        if(empty($lastdate)){
           if($toId==9){
            
            $lastdate=M('Currency')->field('first_price')->where("currency_id={$currency_id}")->find();
            $lastdate = $lastdate['first_price'];
            if(empty($lastdate)){
                $lastdate='0';
            }
           }
        
        }
        return $lastdate;
    }

    /**
     * HTTP转HTTPS
     * @param $string
     * @return mixed
     */
    protected static function http_to_https($string)
    {
        return str_replace("http://", "http://", $string);
    }

    /**
     * 阿里云OSS文件上传
     * @param array $file
     * @param string $path
     * @return array
     */
    protected function oss_upload($file = [], $path = 'file')
    {
        $accessKeyId = $this->oss_config['accessKeyId'];
        $accessKeySecret = $this->oss_config['accessKeySecret'];
        $endpoint = $this->oss_config['endpoint'];
        $bucket = $this->oss_config['bucket'];
        $isCName = false;

        $arr = array();

        require_once(THINK_PATH . 'Extend/Vendor/aliyun-oss/autoload.php');
        $ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint, $isCName);

        $file = !empty($file) ? $file : $_FILES;
        if (empty($file)) {
            $this->error("没有可上传文件");
        }

        $date_path = date("Y-m-d");
        foreach ($file as $key => $value) {
            $file_raw = file_get_contents($value['tmp_name']);
            $name = substr(md5($value['name'] . time() . mt_rand(33, 126)), 8, 16) . '.' . strtolower(pathinfo($value['name'])['extension']);
            $object = $path . "/{$date_path}/" . $name;
            $arr[] = [
                'file_name' => $key,
                'file_raw' => $file_raw,
                'object' => $object,
            ];
        }
        if (!isset($_SERVER['HTTPS']) ||
            $_SERVER['HTTPS'] == 'off'  ||
            $_SERVER['HTTPS'] == '') {
            $scheme = 'http';
        }
        else {
            $scheme = 'https';
        }
        $photo_list = [];
        try {
            if (!empty($arr)) {
                foreach ($arr as $value) {

                    $getOssInfo = $ossClient->putObject($bucket, $value['object'], $value['file_raw']);
                    $getOssPdfUrl =  $getOssInfo['info']['url']?:$scheme . '://' .$bucket.'.'.$endpoint.'/'.$value['object'];
                    if ($getOssPdfUrl) {
                        $photo_list[$value['file_name']] = self::http_to_https($getOssPdfUrl);
                    }
                }
            }
        } catch (OssException $e) {
            $this->error($e->getMessage());
        }

        return $photo_list;
    }

    /**
     * 实名认证文件上传
     * @param $member_id
     * @return array
     */
    protected function upload_auth($member_id)
    {
        $accessKeyId = $this->oss_config['accessKeyId'];
        $accessKeySecret = $this->oss_config['accessKeySecret'];
        $endpoint = $this->oss_config['endpoint'];
        $bucket = $this->oss_config['bucket'];
        $isCName = false;

        $file1_raw = null;
        $file2_raw = null;
        $file3_raw = null;

        if (empty($member_id) || !intval($member_id) > 0) {
            return ['Code' => 0, 'Msg' => "请传入用户ID"];
        }

        require_once(THINK_PATH . 'Extend/Vendor/aliyun-oss/autoload.php');
        $ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint, $isCName);
        $image_type = ['pjpeg', 'jpeg', 'jpg', 'gif', 'bmp', 'png'];

        //debug($ossClient);
        $file_raw = [];
        //debug($_FILES);
        if ($_FILES['auth_1']['size'] > 0) {
            $file1_raw = file_get_contents($_FILES['auth_1']['tmp_name']);
            $name = substr(md5(base64_encode(time() . mt_rand(33, 126))), 8, 16);
            $extension = strtolower(pathinfo($_FILES['auth_1']['name'])['extension']);

            if (!in_array($extension, $image_type)) {
                return ['Code' => 0, 'Msg' => "身份证正面照片格式不正确"];
            }

            $object = "auth_photo/{$member_id}/{$name}." . $extension;
            $file_raw[] = [
                'file_name' => "auth_1",
                'file_raw' => $file1_raw,
                'object' => $object,
            ];
        } else {
            return ['Code' => 0, 'Msg' => "请选择身份证正面照片"];
        }

        if ($_FILES['auth_2']['size'] > 0) {
            $file2_raw = file_get_contents($_FILES['auth_2']['tmp_name']);
            $name = substr(md5(base64_encode(time() . mt_rand(33, 126))), 8, 16);
            $extension = strtolower(pathinfo($_FILES['auth_2']['name'])['extension']);

            if (!in_array($extension, $image_type)) {
                return ['Code' => 0, 'Msg' => "身份证背面照片格式不正确"];
            }

            $object = "auth_photo/{$member_id}/{$name}." . $extension;
            $file_raw[] = [
                'file_name' => "auth_2",
                'file_raw' => $file2_raw,
                'object' => $object,
            ];
        } else {
            return ['Code' => 0, 'Msg' => "请选择身份证背面照片"];
        }

        if ($_FILES['auth_3']['size'] > 0) {
            $file3_raw = file_get_contents($_FILES['auth_3']['tmp_name']);
            $name = substr(md5(base64_encode(time() . mt_rand(33, 126))), 8, 16);
            $extension = strtolower(pathinfo($_FILES['auth_3']['name'])['extension']);

            if (!in_array($extension, $image_type)) {
                return ['Code' => 0, 'Msg' => "身份证手持照片格式不正确"];
            }

            $object = "auth_photo/{$member_id}/{$name}." . $extension;
            $file_raw[] = [
                'file_name' => "auth_3",
                'file_raw' => $file3_raw,
                'object' => $object,
            ];
        } else {
            return ['Code' => 0, 'Msg' => "请选择身份证手持照片"];
        }

        if (isset($_FILES['auth_4']['size']) && $_FILES['auth_4']['size'] > 0) {
            $file3_raw = file_get_contents($_FILES['auth_4']['tmp_name']);
            $name = substr(md5(base64_encode(time() . mt_rand(33, 126))), 8, 16);
            $extension = strtolower(pathinfo($_FILES['auth_4']['name'])['extension']);

            if (!in_array($extension, $image_type)) {
                return ['Code' => 0, 'Msg' => "其他证件照片格式不正确"];
            }

            $object = "auth_photo/{$member_id}/other_{$name}." . $extension;
            $file_raw[] = [
                'file_name' => "auth_4",
                'file_raw' => $file3_raw,
                'object' => $object,
            ];
        }
        //debug($file_raw);
        if (!isset($_SERVER['HTTPS']) ||
            $_SERVER['HTTPS'] == 'off'  ||
            $_SERVER['HTTPS'] == '') {
                $scheme = 'http';
            }
            else {
                $scheme = 'https';
            }

        $photo_list = [];
        try {
            if (!empty($file_raw)) {
                foreach ($file_raw as $value) {
                    $getOssInfo = $ossClient->putObject($bucket, $value['object'], $value['file_raw']);
                    $getOssPdfUrl = $getOssInfo['info']['url']?:$scheme . '://' .$bucket.'.'.$endpoint.'/'.$value['object'];
                    if ($getOssPdfUrl) {
                        $photo_list[$value['file_name']] = self::http_to_https($getOssPdfUrl);
                    }
                }
            }
        } catch (OssException $e) {
            return ['Code' => 0, 'Msg' => $e->getMessage()];
        }

        return ['Code' => 1, 'Msg' => $photo_list];
    }

    /**
     * Base64上传文件
     * @param $images
     * @param string $model_path
     * @param string $model_type
     * @param string $upload_path
     * @param bool $autoName
     * @return array
     */
    protected function oss_base64_upload($images, $model_path = '', $model_type = 'images', $upload_path = '', $autoName = true)
    {
        $accessKeyId = $this->oss_config['accessKeyId'];
        $accessKeySecret = $this->oss_config['accessKeySecret'];
        $endpoint = $this->oss_config['endpoint'];
        $bucket = $this->oss_config['bucket'];
        $isCName = false;

        if (empty($images)) {
            return ['Code' => 0, 'Msg' => '文件列表不能为空'];
        }

        $file_raw = [];
        $file_type = ['pjpeg', 'jpeg', 'jpg', 'gif', 'bmp', 'png'];
        $preg_type = "image";
        $model_type = strtolower($model_type);
        if ($model_type == 'video') {
            $preg_type = $model_type;
            $file_type = ['mov', '3gp', 'mp4', 'avi'];
        }

        if (is_array($images) && count($images) > 0) {
            /*
             * $images 批量上传示例(值为一维单列或多列数组)
             * $images = [
             *      "base64/image1..........."
             *      "base64/image2..........."
             * ]
             */

            foreach ($images as $key => $value) {
                $value = trim($value);
                if (preg_match("/^(data:\s*$preg_type\/(\w+);base64,)/", $value, $result)) {
                    $type = strtolower($result[2]);
                    if (in_array($type, $file_type)) {
                        $file_raw[] = [
                            'raw' => base64_decode(str_replace($result[1], '', $value)), //文件流
                            'extension' => $type, //文件后缀
                            'index' => $key,
                        ];
                    } else {
                        return ['Code' => 0, 'Msg' => '文件类型错误'];
                    }
                } else {
                    return ['Code' => 0, 'Msg' => '文件base64格式不合法'];
                }
            }
        }

        if (is_string($images)) {
            /*
             * $images 上传单个示例，字符串
             * $images = "base64/image..........."
             */

            $images = trim($images);
            if (preg_match("/^(data:\s*$preg_type\/(\w+);base64,)/", $images, $result)) {
                $type = strtolower($result[2]);
                if (in_array($type, $file_type)) {
                    $file_raw[] = [
                        'raw' => base64_decode(str_replace($result[1], '', $images)), //文件流
                        'extension' => $type, //文件后缀
                        'index' => 0,
                    ];
                } else {
                    return ['Code' => 0, 'Msg' => '文件类型错误'];
                }
            } else {
                return ['Code' => 0, 'Msg' => '文件base64格式不合法'];
            }
        }

        if (empty($upload_path)) {
            $model_path = strstr('/', $model_path) ? $model_path : $model_path . '/';
            $upload_path = "{$model_type}/{$model_path}" . date('Y-m-d') . '/';
        }

        require_once(THINK_PATH . 'Extend/Vendor/aliyun-oss/autoload.php');
        $ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint, $isCName);

        if (!isset($_SERVER['HTTPS']) ||
            $_SERVER['HTTPS'] == 'off'  ||
            $_SERVER['HTTPS'] == '') {
                $scheme = 'http';
            }
            else {
                $scheme = 'https';
            }
        
        $photo_list = [];
        try {
            if (!empty($file_raw)) {
                foreach ($file_raw as $value) {
                    $name = substr(md5(base64_encode($value['raw']) . base64_encode(time() . mt_rand(33, 126))), 8, 16);
                    if ($autoName === true) {
                        $file_name = $upload_path . $name . "." . strtolower($value['extension']);
                    } else {
                        $file_name = $upload_path;
                    }
                    $getOssInfo = $ossClient->putObject($bucket, $file_name, $value['raw']);
                    $getOssPdfUrl = $getOssInfo['info']['url']?:$scheme . '://' .$bucket.'.'.$endpoint.'/'.$file_name;
                    if ($getOssPdfUrl) {
                        $photo_list[$value['index']] = self::http_to_https($getOssPdfUrl);
                    }
                }
            }
        } catch (OssException $e) {
            return ['Code' => 0, 'Msg' => $e->getMessage()];
        }

        return ['Code' => 1, 'Msg' => $photo_list];
    }

    /**
     * OSS内文件是否存在
     * @param string $object @文件路径
     * @return bool
     */
    protected function doesObjectExist($object = '')
    {
        $exist = false;
        if (empty($object)) {
            return $exist;
        }

        $accessKeyId = $this->oss_config['accessKeyId'];
        $accessKeySecret = $this->oss_config['accessKeySecret'];
        $endpoint = $this->oss_config['endpoint'];
        $bucket = $this->oss_config['bucket'];
        $isCName = true;

        require_once(THINK_PATH . 'Extend/Vendor/aliyun-oss/autoload.php');
        $ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint, $isCName);

        try {
            $exist = $ossClient->doesObjectExist($bucket, $object);
        } catch (OssException $e) {
            $exist = false;
            //printf($e->getMessage() . "\n");
        }

        return $exist;
    }

    /**
     * 登陆日志记录到数据库
     * @param $member_id
     */
    public function login_record($member_id)
    {
        if (!empty($member_id)) {
            $member = M("member");
            $data['login_ip'] = get_client_ip_extend();
            $data['login_time'] = time();
            $where['member_id'] = $member_id;

            $member->where($where)->save($data);
        }
    }

    /**
     * 获取会员的父级
     * @param $member_id 会员id
     * @param $level 级别 
     * @return bool
     */
    public function getParentUid($member_id, $level)
    {
        if ($level == 1) {
            $members = M('member')->field('pid')->where(array('member_id' => $member_id))->find();
        } elseif ($level == 2) {
            $m1 = M('member')->field('pid')->where(array('member_id' => $member_id))->find();
            $members = M('member')->field('pid')->where(array('member_id' => $m1['pid']))->find();
        } elseif ($level == 3) {
            $m1 = M('member')->field('pid')->where(array('member_id' => $member_id))->find();
            $m2 = M('member')->field('pid')->where(array('member_id' => $m1['pid']))->find();
            $members = M('member')->field('pid')->where(array('member_id' => $m2['pid']))->find();
        }
        if ($members) {
            return $members['pid'];
        } else {
            return false;
        }
    }

    /**
     * 以太坊接口通讯
     * @param array $currency
     * @param array $push_data
     * @param bool $json
     * @param string $req_type
     * @return mixed
     */
    protected function _ethereum($currency = [], $push_data = [], $json = false, $req_type = 'post')
    {
        $keycode = "QIXS9EvR5h2L";
        $push_data['sign'] = self::createSign($push_data, $keycode);
        $api_url = sprintf("%s:%d", $currency['rpc_url'], $currency['port_number']);

        $result = @_curl("http://{$api_url}", $push_data, $json, $req_type);
        return @json_decode($result, true);

    }

    /**
     * 以太坊转币交易
     * @param string $to_address #接收地址
     * @param int $actual #实际转账金额
     * @param array $currency #币种信息，ip和端口，用来连接php接口
     * @param string $pass #发送转账的地址的密码
     * @param string $from_address #转账地址，默认为提币地址
     * @param int $gasPrice
     * @param int $gas #燃气价格   eth:21000  代币需要通过接口获取
     * @param string $method #接口方法名
     * @param int $nonce
     * @param string $data
     * @param string $agreement #以太坊转账默认为eth，代币转账必须为token
     * @param string $token_address #代币转账时，必须传入代币的合约地址
     * @return bool|array
     */
    protected function eth_transaction($to_address = '', $actual = 0, $currency = [], $pass = null, $from_address = null, $gasPrice = null, $gas = null, $method = null, $nonce = null, $data = null, $agreement = null, $token_address = null)
    {
        $tx_hash = false;

        if (!self::isValidAddress($to_address)) return $tx_hash;
        if (!empty($from_address) && !self::isValidAddress($from_address)) return $tx_hash;
        if (!($actual > 0)) return $tx_hash;
        if (empty($currency)) return $tx_hash;

        $tibi_address = $currency['tibi_address'];
        $tibi_pass = $currency['qianbao_key'];

        $push_data = [
            'method' => !empty($method) ? $method : 'personal_sendTransaction',
            'from' => !empty($from_address) ? $from_address : $tibi_address,
            'to' => $to_address,
            'value' => $actual,
            'gas' => isset($gas) ? $gas : 21000,
            'passphrase' => !empty($pass) ? $pass : $tibi_pass,
        ];

        //获取转账手续费单价
        if (empty($gasPrice)) {
            $gasPrice_push_data = [
                'method' => 'eth_gasPrice',
            ];

            $result = self::_ethereum($currency, $gasPrice_push_data);

            if (isset($result['result']['result']['number'])) {
                $push_data['gasPrice'] = $result['result']['result']['number'];
            }
        } else {
            $push_data['gasPrice'] = $gasPrice;
        }

        if (!empty($agreement)) $push_data['agreement'] = $agreement;
        if (!empty($token_address)) $push_data['token_address'] = $token_address;
        if (!empty($nonce)) $push_data['nonce'] = $nonce;
        if (!empty($data)) $push_data['data'] = $data;

        $result = self::_ethereum($currency, $push_data);

        if (empty($result['result']['error']) && self::isValidTransactionHash($result['result']['result'])) {
            $tx_hash = $result['result']['result'];
        }

        return $tx_hash;
    }

    /**
     * 获取以太坊Token充值记录 [递归]
     * https://github.com/EverexIO/Ethplorer/wiki/Ethplorer-API
     * @param string $address
     * @param string $token_address
     * @param string $timestamp
     * @param array $result
     * @param int $index
     * @param array $tx_array
     * @return array
     */
    protected function getTokenHistory($address = '', $token_address = '', $timestamp = '', $result = [], $index = 0, $tx_array = [])
    {
        if (!self::isValidAddress($address) || !self::isValidAddress($token_address)) return $result;

        //每页最多取10条，这里取3页，通过每页最后一个数组元素的时间戳取下一页数据。
        $page = 3;
        if ($index < $page) {
            $url = "https://api.ethplorer.io/getAddressHistory/{$address}?apiKey=freekey&token={$token_address}&type=transfer";
            if (!empty($timestamp)) $url .= "&timestamp=" . $timestamp;
            $data = @_curl($url, [], false, 'get');
            $data = @json_decode($data, true);

            if (!empty($data['operations'])) {
                $length = count($data['operations']);
                foreach ($data['operations'] as $key => $val) {
                    if (strtolower($val['to']) == strtolower($address) && !in_array($val['transactionHash'], $tx_array)) {
                        $tx_info = self::getTxInfo($val['transactionHash']);
                        $val['success'] = $tx_info['success'];
                        $tx_array[] = $val['transactionHash'];

                        $result[] = [
                            'hash' => $val['transactionHash'],
                            'timestamp' => $val['timestamp'],
                            'value' => $val['value'] / pow(10, intval($val['tokenInfo']['decimals'])),
                            'success' => $val['success'],
                        ];
                    }
                }

                $index++;

                if ($length === 10) {
                    $timestamp = @$data['operations'][9]['timestamp'];
                    return self::getTokenHistory($address, $token_address, $timestamp, $result, $index, $tx_array);
                }
            }
        }

        return $result;
    }

    /**
     * 获取以太坊和Token交易信息
     * @param $tx_hash
     * @return mixed
     */
    protected function getTxInfo($tx_hash)
    {
        $url = "https://api.ethplorer.io/getTxInfo/{$tx_hash}?apiKey=freekey";
        $data = @_curl($url, [], false, 'get');
        return @json_decode($data, true);
    }

    /**
     * 以太坊钱包地址是否合法
     * Returns true if provided string is a valid ethereum address.
     *
     * @param string $address Address to check
     * @return bool
     */
    protected function isValidAddress($address)
    {
        return (is_string($address)) ? preg_match("/^0x[0-9a-fA-F]{40}$/", $address) : false;
    }

    /**
     * 以太坊交易hash是否合法
     * Returns true if provided string is a valid ethereum tx hash.
     *
     * @param string $hash Hash to check
     * @return bool
     */
    protected function isValidTransactionHash($hash)
    {
        return (is_string($hash)) ? preg_match("/^0x[0-9a-fA-F]{64}$/", $hash) : false;
    }

    /**
     * 生成签名
     * @param $args //要发送的参数
     * @param $key //keycode
     * @return string
     */
    private function createSign($args, $key = '')
    {
        $signPars = ""; //初始化
        ksort($args); //键名升序排序
        //$key = empty($key) ? self::$keycode : $key;
        foreach ($args as $k => $v) {
            if (!isset($v) || strtolower($k) == "sign" || strtolower($k) == "uuid") {
                continue;
            }
            $signPars .= $k . "=" . $v . "&";
        }
        $signPars .= "key=" . $key; //签名的参数和key秘钥连接
        $sign = md5($signPars); //md5加密
        $sign = strtoupper($sign); //转为大写
        return $sign; //最终的签名
    }

    /**
     * PHP正负数互转（支持小数点）
     * 正数转负数 / 负数转正数
     * @param int $number
     * @return float|int
     */
    protected static function plus_minus_conversion($number = 0)
    {
        return $number > 0 ? (-1 * $number) : abs($number);
    }

    /**
     * 二维数组按指定的键值排序
     * @param $arr #原始数组
     * @param $keys #指定排序的键名
     * @param string $type #排序方式
     * @return array
     */
    protected static function array_sort($arr, $keys, $type = 'desc')
    {
        $keysvalue = $new_array = [];
        foreach ($arr as $k => $v) {
            $keysvalue[$k] = $v[$keys];
        }

        if ($type == 'asc') {
            asort($keysvalue);
        } else {
            arsort($keysvalue);
        }

        reset($keysvalue);
        foreach ($keysvalue as $k => $v) {
            $new_array[$k] = $arr[$k];
        }
        return $new_array;
    }

    /**
     * 吉链签名函数
     * @param $params 参数
     */
    protected function getSign($params){
        $fix = $this->kac_config['NODE_USER']."and".$this->kac_config['NODE_PWD'];//config('NODE_USER')=  sbcapp_HJ&*Dsdk23   config('NODE_PWD') = HJ6534*(*(df_dsf
        $str = http_build_query($params);
        $str = preg_replace("/[=&]+/", "", $str);
        $str = $fix . $str . $fix;
        return md5($str);
    }
    /**
     * 吉链访问接口方法（非批量）
     * @param $url
     * @param $postdata 参数
     * @return mixed
     */
    protected function postUrl($url, $postdata, $headers)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        if(!empty($headers)){
            curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
        }
        $data = curl_exec($ch);
        return $data;
    }
    /**
     * 吉链钱包地址是否合法
     * Returns true if provided string is a valid kac address.
     *
     * @param string $address Address to check
     * @return bool
     */
    protected function isValidAddressKac($address)
    {
        return (is_string($address)) ? preg_match("/^G[a-zA-Z0-9]{55}$/", $address) : false;
    }

    /**
     * @desc AB区的总会员的AT数
     * @return mixed
     */
    public  function  user_ab(){
        $member_id = $this->member_id?:$_SESSION['USER_KEY_ID'];
        $ab = M('role_ab')->where(['member_id'=>$member_id])->find();
        if($ab['a']){
            $sql  = "select  IFNULL(SUM(num) + sum(forzen_num) + sum(lock_num) ,0) as sum from  yang_currency_user where currency_id=29 and member_id in({$ab['a']})";
            $money['a'] = M()->query($sql);
        }
        if($ab['b']){
            $sql  = "select  IFNULL(SUM(num) + sum(forzen_num) + sum(lock_num) ,0)as sum from  yang_currency_user where currency_id=29 and member_id in({$ab['b']})";
            $money['b'] = M()->query($sql);
        }
        $money['a']['m'] =  number_format($money['a'][0]['sum']?:0,6);
        $money['a']['l']    = substr_count($ab['a'],',')?substr_count($ab['a'],',')+1:(!empty($ab['a'])?1:0);
        $money['b']['m'] = number_format($money['b'][0]['sum']?:0,6);
        $money['b']['l']    = substr_count($ab['b'],',')?substr_count($ab['b'],',')+1:(!empty($ab['b'])?1:0);
        $this->assign('money',$money);
      return  $money;
    }

    /*
    * 获取语音包
    */
    protected function getLangNamePc() {
        $lang = cookie('think_language');
        $lang=strtolower($lang);
        if(empty($lang) || strpos(C('LANG_LIST'), $lang)===false) $lang = C('DEFAULT_LANG');

        $name = '';
        switch ($lang) {
            case 'en-us':
                $name = 'en';
                break;
            case 'zh-tw';
                $name = 'tc';
                break;
            default:
                break;
        }
        return $name;
    }

    //验证网易验证码
    protected function checkNECaptchaValidate($NECaptchaValidate){
        //验证码验证
        $valudate=new NECaptchaVerifier();
        if(!$valudate->verify($NECaptchaValidate)){
            return false;
        }else{
            return true;
        }
    }

    protected function checkFileSize($img) {
        $start = strpos($img, 'base64,');
        if($start) $img = substr($img, $start+7);
        $img = base64_decode($img);
        if(strlen($img)>5*1024*1024) return false; 

        return true;
    }
    
  /**
     * 币标签转成2个currency_ID
     * @param string $buysell_name
     * @return bool|array
     */
    public function geteachothertrade_mark($buysell_name){
        $buysell_name=explode('_', $buysell_name);
        if($buysell_name['1']==$buysell_name['0']){
            return false;
        }
        if(empty($buysell_name['1'])||empty($buysell_name['0'])){
            return false;
        }
        $where_currency_trade_id['currency_mark']=$buysell_name['1'];
        $currency_trade_id=M('Currency')->where($where_currency_trade_id)->find();
        $where_currency_id['trade_currency_id']= array('like','%'.$currency_trade_id['currency_id'].'%');
        $where_currency_id['currency_mark']=$buysell_name['0'];
        $currency_id=M('Currency')->where($where_currency_id)->find();
        if(empty($currency_id)||empty($currency_trade_id)){
            return false;
    
        }else {
            $data['currency_id']=$currency_id['currency_id'];
            $data['currency_trade_id']=$currency_trade_id['currency_id'];
            return $data;
        }
    }
    /**
     * 币标签ID转成2个currency_ID
     * @param string $buysell_id
     * @return bool|array
     */
    public function geteachothertrade_id($buysell_id){
        $buysell_id=explode('_', $buysell_id);
        if($buysell_id['1']==$buysell_id['0']){
            return false;
        }
        if(empty($buysell_id['1'])||empty($buysell_id['0'])){
            return false;
        }
        $where_currency_id['currency_id']=$buysell_id['0'];
        $currency_id=M('Currency')->where($where_currency_id)->find()['currency_id'];
        $where_currency_trade_id['currency_id']= $buysell_id['0'];
        $where_currency_trade_id['trade_currency_id']= array('like','%'.$buysell_id['1'].'%');
        $currency_trade_id=M('Currency')->where($where_currency_trade_id)->find();
        if(empty($currency_id)||empty($currency_trade_id)){
            return false;
    
        }else {
            $data['currency_id']=$currency_id;
            $data['currency_trade_id']=$buysell_id['1'];
            return $data;
        }
    }

    /**
     * 敏感信息处理
     * @param $arr 二维数组
     * @param $filed 要处理的键值
     * @param $start 前位数
     * @param $end   后位数
     * @param $num   *固定4个
     */
    public function hideStr($arr, $key, $start, $end)
    {
        $data = $arr;
        if(is_array($arr)){
            foreach($arr as $_key=>$val){
                if(is_array($key)){
                    foreach($key as $key_key){
                        $_str_r = $val[$key_key];
                        $data[$_key][$key_key] = mb_substr($_str_r,0,$start).'****'.mb_substr($_str_r,-$end);
                    }
                }else{
                    $_str_r = $val[$key];
                    $data[$_key][$key] = mb_substr($_str_r,0,$start).'****'.mb_substr($_str_r,-$end);
                }
            }
        }
        return $data;
    }
}