<?php
namespace app\admin\controller;
use think\captcha\Captcha;
use think\Db;

class Login extends Common
{
    public function _initialize(){
        parent::_initialize();

        $vpn_config_file =  ROOT_PATH.'admin_ip_allow.php';
        if(file_exists($vpn_config_file)) {
            $ip = get_client_ip();
            $allow_ip = require_once $vpn_config_file;
            $ip_check = true;
            if(!empty($allow_ip) && is_array($allow_ip)){
                $ip_check = false;
                foreach ($allow_ip as $a_ip) {
                    if(!empty($a_ip) && strpos($ip,$a_ip)!==false) $ip_check = true;
                }
            }
            if(!$ip_check) die($ip.' not allow');
        }
    }

    //空操作
    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    public function login()
    {
        return $this->fetch("login/login");
    }

    /**
     * 登录发送邮箱验证码
     * @return \json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Create by: Red
     * Date: 2019/9/9 16:42
     */
    public function email_send() {
        $username=input("post.username");
        $pwd=input("post.pwd");
        $r['code']=ERROR1;
        $r['message']="参数错误";
        $r['result']=[];
        if(!empty($username)&&!empty($pwd)){
            $where['username']=$username;
            $admin =Db::name("admin")->where($where)->find();
            if ($admin['password'] != md5($pwd)) {
                $r['message']="帐号或密码不正确";
                return mobileAjaxReturn($r);
            }
            if($admin['status']!=0){
                $r['message']="帐号已被禁用";
                return mobileAjaxReturn($r);
            }
            $result = model('Sender')->send_email($admin['email'],"validate");
            if(is_string($result)) mobileAjaxReturn([],$result,ERROR1);
            mobileAjaxReturn(hideStar($admin['email']),"发送成功",SUCCESS);
        }
      return mobileAjaxReturn($r);
    }

    //登录验证
    public function checkLogin()
    {
        $username = trim(input('post.username'));
        $pwd = trim(input('post.pwd'));
        $captcha=input("post.captcha");
        if (empty($captcha)) {
            $this->error("邮箱验证码不能为空！", "login/login", 9);
        }
        if (empty($username) || empty($pwd)) {
            $this->error('请填写完整信息');
        }
        $where['username']=$username;

        $admin =Db::name("admin")->where($where)->find();

        if(empty($admin)){
            $this->error('管理员帐号不存在');
        }

        if ($admin['password'] != md5($pwd)) {
            $this->error('登录密码不正确');
        }
        $senderLog = model('Sender')->check_log(2,$admin['email'],'validate',$captcha,true);
        if(is_string($senderLog)) return $this->error($senderLog);
        if($admin['status']!=0){
            return $this->error('已禁用');
        }

        //插入登录日志
        $log['admin_id'] = $admin['admin_id'];
        $log['info'] = "管理后台登录";
        $log['ip_addresses'] = get_client_ip_extend();
        $log['addtime'] = time();
        Db::name("Admin_log")->insertGetId($log);
        session("admin_userid",$log['admin_id']);
        return redirect(url('Index/index'));
    }

    //登出
    public function loginout()
    {
        session("admin_userid",null);
        $this->redirect('Login/login');
    }

    //显示验证码
    public function verifyTest()
    {
        $this->display();
    }

    //检验验证码是否正确
    public function verifyCheck()
    {
        //防止页面乱码
        header('Content-type:text/html;charset=utf-8');

        if (md5($_POST['verifyTest']) != Session::get('verify')) {
            echo '验证码错误';
        } else {
            echo '验证码正确';
        }
    }

    // 生成验证码
    public function verify2()
    {
        import("ORG.Util.Image");
        Image::buildImageVerify();
    }

    //生成验证码
    public function verify_c()
    {
        $Verify = new \Think\Verify;
        $Verify->fontSize = 18;
        $Verify->length = 5;
        $Verify->useNoise = false;
        $Verify->codeSet = '0123456789';
        $Verify->imageW = 160;
        $Verify->imageH = 40;
        $Verify->entry();
    }



    /**
     * 显示验证码
     */
//    public function showVerify()
//    {
//        $config = array(
//            'fontSize' => 13,              // 验证码字体大小(px)
//            'useCurve' => true,            // 是否画混淆曲线
//            'useNoise' => false,            // 是否添加杂点
//            'imageH' => 35,               // 验证码图片高度
//            'imageW' => 80,               // 验证码图片宽度
//            'length' => 3,               // 验证码位数
//            'fontttf' => '4.ttf',              // 验证码字体，不设置随机获取
//        );
//        ob_clean();
//        $Verify = new Verify($config);
//        $Verify->entry();
//    }

    /**
     * 验证码
     * @return \think\Response
     */
    public function showVerify()
    {
        $config =  [
            'fontSize' => 13,              // 验证码字体大小(px)
            'useCurve' => true,            // 是否画混淆曲线
            'useNoise' => false,            // 是否添加杂点
            'imageH' => 35,               // 验证码图片高度
            'imageW' => 80,               // 验证码图片宽度
            'length' => 3,               // 验证码位数
            'fontttf' => '4.ttf',              // 验证码字体，不设置随机获取
        ];

        return (new Captcha($config))->entry();
    }
}
