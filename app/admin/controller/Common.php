<?php


namespace app\admin\controller;


use OSS\Core\OssException;
use think\Controller;
use think\Db;

class Common extends Controller
{
    protected $config;
    protected $trade;
    protected $member;
    protected $currency;
    protected $currency_isqu;
    protected $currency_noqu;
    protected $cache_time = 3600; //缓存一小时
    protected $oss_config = [];

    public function _initialize()
    {
        //保存日志和过滤一些密码或上传图片数据
        if(strtolower($this->request->controller())=='upload') {
            $data = [];
        } else {
            $data = input();
            $filter_param = ['pic1','pic2','pic3','img','image'];
            foreach ($filter_param as $filter) {
                if(isset($data[$filter])) $data[$filter] = '';
            }
            if(isset($data["/".$this->request->pathinfo()])){
                unset($data["/".$this->request->pathinfo()]);
            }
            if(isset($data['pwd'])){
                unset($data['pwd']);
            }
            if(isset($data['password'])){
                unset($data['password']);
            }
        }
        $ip = get_client_ip();
        Db::name('admin_access_log')->insertGetId([
            'admin_id' => intval(session("admin_userid")),
            'module' => $this->request->pathinfo(),
            'param' => json_encode($data),
            'access_ip' => $ip,
            'access_time' => time(),
        ]);
        $oss_config = config('aliyun_oss');
        $this->oss_config = [
            'accessKeyId' => $oss_config['accessKeyId'],
            'accessKeySecret' =>$oss_config['accessKeySecret'],
            'endpoint' => $oss_config['endpoint'],
            'bucket' =>$oss_config['bucket'],
        ];
        $list =Db::name("Config")->select();
        foreach ($list as $k => $v) {
            $list[$v['key']] = $v['value'];
        }

        //网站配置信息
        $this->config = $list;
        $this->assign('config', $this->config);
        $this->login_limit_time();

        //帮助中心
//        $info_help = M('Article_category')->field('id, name')->where('parent_id = 6')->limit(4)->select();
//        $this->assign('help', $info_help);
//
//        //团队信息显示
//        $info_team = M('Article_category')->field('id, name')->where('parent_id = 7')->limit(4)->select();
//        $this->assign('team', $info_team);

//        $this->trade = M('Orders');

//        $_USER_KEY_ID = session('USER_KEY_ID');
//        if (session('?USER_KEY_ID')) {
//            $member = M("Member")->field('*,(rmb+forzen_rmb) as count')->where("member_id='{$_USER_KEY_ID}'")->find();
//            $member['xnb'] = $member['rmb'] / $list['bili'];
//            $this->member = $member;
//            $this->assign("member", $member);
//
//            $newMessageCount = $this->pullMessageCount($_USER_KEY_ID);
//        }

        //用户未读消息条数
//        $newMessageCount = isset($newMessageCount) ? $newMessageCount : 0;
//        $this->assign('newMessageCount', $newMessageCount);

        //积分类型信息
        $this->currency = self::currency();
        //是区块积分类型信息
        //$this->currency_isqu = self::currency_isqu();
        //不是区块积分类型信息
        //$this->currency_noqu = self::currency_noqu();
    }

    /**
     * 请求处理
     */
    protected function request_filter()
    {
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

        /* 过滤函数 */
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
            $this->error('警告：你的IP已经被禁止了！');
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
                        $this->error('警告：不要刷新的太频繁！');
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
        $list = M('Member')->where(array('member_id' => session('USER_KEY_ID')))->find();
        if ($list['status'] == 0) {
            $this->redirect('ModifyMember/modify', '', 1, "<script>alert('请填写个人信aaaaaa')</script>");
            exit();
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
        $result = $trade_model->where($where)->getField("price")->order($order)->find();

        return !empty($result['price']) ? $result['price'] : 0;
    }

    /**
     * 获取当前积分类型的信息
     * @param int $id 积分类型id
     * @return 24H成交量 24H_done_num  24H成交额 24H_done_money 24H涨跌 24H_change 7D涨跌  7D_change
     * @return 最新价格 new_price 买一价 buy_one_price 卖一价 sell_one_price 最高价 max_price 最低价 min_price
     */
    public static function getCurrencyMessageById($id)
    {
        $all_name = 'rs_all' . $id;
        $data = S($all_name);
        if (empty($data)) {
            $where['currency_id'] = $id;
            $where['type'] = 'buy';
            $Currency_model = M('Currency');
            $trade_model = M('trade');

            $list = $Currency_model->where(array('currency_id' => $id))->find();

            $time = time();
            //一天前的时间
            $old_time = strtotime(date('Y-m-d', $time));

            //最新价格
            $order = 'add_time desc';
            $rs = $trade_model->where($where)->order($order)->find();

            $data['new_price'] = $rs['price'];

            //判断价格是升是降
            $re = $trade_model->where($where)->where("add_time<$old_time")->order($order)->find();
            if ($re['price'] > $rs['price']) {
                //说明价格下降
                $data['new_price_status'] = 0;
            } else {
                $data['new_price_status'] = 1;
            }
/*
            //24H涨跌
            $re = $trade_model->where($where)->where("add_time<$time-60*60*24")->order($order)->find();
            $data['24H_change'] = sprintf("%.2f", ($rs['price'] - $re['price']) / $re['price'] * 100);
            if ($data['24H_change'] == 0) {
                $data['24H_change'] = 0;
            }

            //3D涨跌
            $re = $trade_model->where($where)->where("add_time<$time-60*60*24*3")->order($order)->find();
            $data['3D_change'] = sprintf("%.2f", ($rs['price'] - $re['price']) / $re['price'] * 100);
            if ($data['3D_change'] == 0) {
                $data['3D_change'] = 0;
            }

            //7D涨跌
            $re = $trade_model->where($where)->where("add_time<$time-60*60*24*7")->order($order)->find();
            $data['7D_change'] = sprintf("%.2f", ($rs['price'] - $re['price']) / $re['price'] * 100);
            if ($data['7D_change'] == 0) {
                $data['7D_change'] = 0;
            }

            //24H成交量
            $rs = $trade_model->field('num')->where($where)->where("add_time>$time-60*60*24")->sum('num');
            $data['24H_done_num'] = $rs * 2 + $list['num_number'];

            //24H成交额
            $rs = $trade_model->field('num*price')->where($where)->where("add_time>$time-60*60*24")->sum('num*price');
            $data['24H_done_money'] = $rs * 2 + $list['num_number'] * $data['new_price'];

            //最低价
            $order = "price";
            $sql_time = $time - 60 * 60 * 24 * 7;
            // $name2 = 'rs_down' . $id;
            // $rs = S($name2);
            // if (empty($rs)) {
            $rs = $trade_model->field('price,add_time')->where($where)->where("add_time>$sql_time")->order($order)->find();
            //    S($name2, $rs, 60);
            //  }
            $data['min_price'] = $rs['price'];

            //最高价
            $order = "price desc";
            // $name1 = 'rs_up' . $id;
            //$rs = S($name1);
            // if (empty($rs)) {
            $rs = $trade_model->field('price,add_time')->where($where)->where("add_time>$sql_time")->order($order)->find();
            //  S($name1, $rs, 60);
            // }
            $data['max_price'] = $rs['price'];
*/
            //买一价
            $where['type'] = 'buy';
            $rs = $trade_model->field('price,add_time')->where($where)->order($order)->find();
            $data['buy_one_price'] = $rs['price'];

            //卖一价
            $where['type'] = 'sell';
            $rs = $trade_model->field('price,add_time')->where($where)->order("price")->find();
            $data['sell_one_price'] = $rs['price'];

            s($all_name, $data, 360);
        }

        //返回
        return $data;
    }
    
    public static function getCurrencyMessageById2($id)
    {
        $all_name = 'rs_all2' . $id;
        $data = S($all_name);
        if (empty($data)) {
            $where['currency_id'] = $id;
            $where['type'] = 'buy';
            $Currency_model = M('Currency');
            $trade_model = M('trade');
    
            $list = $Currency_model->where(array('currency_id' => $id))->find();
    
            $time = time();
            //一天前的时间
            $old_time = strtotime(date('Y-m-d', $time));
    
            //最新价格
            $order = 'add_time desc';
            $rs = $trade_model->where($where)->order($order)->find();
    
            $data['new_price'] = $rs['price'];
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
            $where2['type'] = 'sell';
             $re = $trade_model->where($where2)->where("add_time<$time-60*60*24")->order($order)->find();
             $data['24H_change'] = sprintf("%.2f", ($rs['price'] - $re['price']) / $re['price'] * 100);
             if ($data['24H_change'] == 0) {
             $data['24H_change'] = 0;
             }
    
             //3D涨跌
             $re = $trade_model->where($where2)->where("add_time<$time-60*60*24*3")->order($order)->find();
             $data['3D_change'] = sprintf("%.2f", ($rs['price'] - $re['price']) / $re['price'] * 100);
             if ($data['3D_change'] == 0) {
             $data['3D_change'] = 0;
             }
    
             //7D涨跌
             $re = $trade_model->where($where2)->where("add_time<$time-60*60*24*7")->order($order)->find();
             $data['7D_change'] = sprintf("%.2f", ($rs['price'] - $re['price']) / $re['price'] * 100);
             if ($data['7D_change'] == 0) {
             $data['7D_change'] = 0;
             }
    
             //24H成交量
             $rs = $trade_model->field('num')->where($where)->where("add_time>$time-60*60*24")->sum('num');
             $data['24H_done_num'] = $rs * 2 + $list['num_number'];
    
             //24H成交额
             $rs = $trade_model->field('num*price')->where($where)->where("add_time>$time-60*60*24")->sum('num*price');
             $data['24H_done_money'] = $rs * 2 + $list['num_number'] * $data['new_price'];
    
             //最低价
             $sql_time = $time - 60 * 60 * 24 * 7;
             $min_price = $trade_model->field("min(price) as price")->where($where)->where("add_time>$sql_time")->find();
             $data['min_price'] = $min_price['price'];

             //最高价

             $max_price = $trade_model->field("max(price) as price")->where($where)->where("add_time>$sql_time")->find();
             $data['max_price'] = $max_price['price'];
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
            s($all_name, $data, 13600);
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
        return Db::name("Currency")->where('is_line=1 ')->order('sort ASC')->select();
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
        if (!$_SESSION['USER_KEY'] || !$_SESSION['USER_KEY_ID']) {
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
        $id = Db::name('Message_all')->insertGetId($data);
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
    public function addFinance($member_id, $type, $content, $money, $money_type, $currency_id)
    {
        $data['member_id'] = $member_id;
        $data['type'] = $type;
        $data['content'] = $content;
        $data['money_type'] = $money_type;
        $data['money'] = $money;
        $data['add_time'] = time();
        $data['currency_id'] = $currency_id;
        $data['ip'] = get_client_ip_extend();
        $list = Db::name('Finance')->insertGetId($data);
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
        $list = M('Currency')->field("currency_id,currency_name,currency_buy_fee,currency_sell_fee,trade_currency_id,is_lock,rpc_url,rpc_pwd,rpc_user,port_number,currency_all_tibi,is_limit,max_limit,min_limit,is_time,max_time,min_time,sort,limit_in,tcoin_fee")->where($where)->select();
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
        $list = M('Currency2')->field("currency_id,currency_name,currency_buy_fee,currency_sell_fee,trade_currency_id,is_lock,rpc_url,rpc_pwd,rpc_user,port_number,currency_all_tibi,is_limit,max_limit,min_limit,is_time,max_time,min_time,sort,limit_in")->where($where)->select();
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
    protected function getOrdersByUser($num, $currency_id)
    {
        $where2['currency_id'] = $currency_id;
        $cardinal_number2 = M('Currency')->field("cardinal_number")->where($where2)->select();
        foreach ($cardinal_number2 as $k => $v) {

        }
        $cardinal_number = $cardinal_number2[$k]['cardinal_number'];
        $where['member_id'] = $_SESSION['USER_KEY_ID'];
        $where['status'] = array('in', array(0, 1));
        $where['currency_id'] = $currency_id;
        $list = M('Orders')->where($where)->order("add_time desc")->limit($num)->select();
        foreach ($list as $k => $v) {
            $list[$k]['bili'] = 100 - ($v['trade_num'] / $v['num'] * 100);
            $list[$k]['cardinal_number'] = $cardinal_number;
            $list[$k]['type_name'] = fomatOrdersType($v['type']);
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
                    $msg = Db::name('Member')->where(array('member_id' => $member_id))->setInc($field, $num);
                    break;
                case 'dec':
                    $msg = Db::name('Member')->where(array('member_id' => $member_id))->setDec($field, $num);
                    break;
                default:
                    return false;
            }
            return $msg;
        } else {
            switch ($inc_dec) {
                case 'inc':
                    $msg = Db::name('Currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setInc($field, $num);
                    break;
                case 'dec':
                    $msg = Db::name('Currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setDec($field, $num);
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
    protected function getOrdersByStatus($status, $num, $currency_id)
    {
        $where['currency_id'] = $currency_id;
        $where['status'] = $status;
        $where['type'] = array('neq', 'onebuy');
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
     * @param int $user_id 用户ID
     * @param int $currency_id 积分类型ID
     * @param char $field num  forzen_num
     * @return array 当前登陆人账号信息
     */
    protected function getUserMoney($user_id, $currency_id, $field)
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

            $num = db('member')->where(array('member_id' => $user_id))->value($field);
        } else {
            $num = db('Currency_user')->where(array('member_id' => $user_id, 'currency_id' => $currency_id))->value($field);
        }

        return number_format($num, 4, '.', '');
    }

    /**
     * 返回指定数量排序的挂单记录
     * @param char $type buy sell
     * @param int $num 数量
     * @param char $order 排序 desc asc
     */
    protected function getOrdersByType($currencyid, $type, $num, $order)
    {
        $where2['currency_id'] = $currencyid;
        $cardinal_number2 = M('Currency')->field("cardinal_number")->where($where2)->select();
        foreach ($cardinal_number2 as $k => $v) {

        }
        $cardinal_number = $cardinal_number2[$k]['cardinal_number'];
        $where['type'] = array('eq', $type);
        $where['status'] = array('in', array(0, 1));
        $where['currency_id'] = $currencyid;
        $list = M('Orders')->field("sum(num) as num,sum(trade_num) as trade_num,price,type,status")
            ->where($where)->group('price')->order("price $order, add_time asc")->limit($num)->select();
        foreach ($list as $k => $v) {
            $list[$k]['bili'] = 100 - ($v['trade_num'] / $v['num'] * 100);
            $list[$k]['cardinal_number'] = $cardinal_number;
        }
        return $list;
    }

    /**
     * 返回昨天最后价格
     * @param int $currency_id 数量
     * @param char $order 排序 desc asc
     */
    protected function getlastmessage($currency_id)
    {
        $currency_id = $currency_id;
        $lastdate = M('Trade')->field('price')->where("add_time <= UNIX_TIMESTAMP(CAST(CAST(SYSDATE()AS DATE)AS DATETIME)) and currency_id={$currency_id} and type='buy' ")->order('add_time desc')->limit(1)->find();
        $lastdate = $lastdate['price'];
        return $lastdate;
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
     * 多文件上传
     * @param array $file
     * @param string $path
     */
    public function multiple_upload($file = [], $path = 'file')
    {
        $file = !empty($file) ? $file : $_FILES;
        $response = [];
        foreach ($file as $key => $item) {
            foreach ($item['name'] as $k => $v) {
                $file = ['art_pic' => [
                    'name' => $v,
                    'type' => $item['type'][$k],
                    'tmp_name' => $item['tmp_name'][$k],
                    'error' => $item['error'][$k],
                    'size' => $item['size'][$k]
                ]];
                $res = $this->oss_upload($file, $path);
                array_push($response, trim($res[$key]));
            }
        }
        return $response;
    }
    /**
     * HTTP转HTTPS
     * @param $string
     * @return mixed
     */
    protected static function http_to_https($string)
    {
        return str_replace("http://", "https://", $string);
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

}

