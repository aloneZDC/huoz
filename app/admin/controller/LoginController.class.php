<?php
namespace Admin\Controller;

use Common\Controller\CommonController;
use Think\Verify;

class LoginController extends CommonController
{
    //空操作
    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    public function login()
    {
        $this->display();
    }

    //登录验证
    public function checkLogin()
    {
        $username = trim(I('post.username'));
        $pwd = trim(I('post.pwd'));
        //$verify = I('post.verify','');
        //if(!check_verify($verify)){
        //    $this->error("亲，验证码输错了哦！",$this->site_url,9);
        //}
        $verify = new Verify();
        if (!$verify->check($_POST['captcha'])) {
            $this->error("亲，验证码输错了哦！", $this->site_url, 9);
        }
        if (empty($username) || empty($pwd)) {
            $this->error('请填写完整信息');
        }
        $where['username']=$username;

        $admin = M('Admin')->where($where)->find();

        if(empty($admin)){
            $this->error('管理员帐号不存在');
        }

        if ($admin['password'] != md5($pwd)) {
            $this->error('登录密码不正确');
        }
        if($admin['status']!=0){
            return $this->error('已禁用');
        }

        //插入登录日志
        $log['admin_id'] = $admin['admin_id'];
        $log['info'] = "管理后台登录";
        $log['ip_addresses'] = get_client_ip_extend();
        $log['addtime'] = time();
        M('Admin_log')->add($log);

        $_SESSION['admin_userid'] = $log['admin_id'];
        $this->redirect('Index/index');
    }

    //登出
    public function loginout()
    {
        $_SESSION['admin_userid'] = null;
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

    public function check_verify($code, $id = '')
    {
        $verify = new \Think\Verify;
        return $verify->check($code, $id);
    }

    /**
     * 显示验证码
     */
    public function showVerify()
    {
        $config = array(
            'fontSize' => 13,              // 验证码字体大小(px)
            'useCurve' => true,            // 是否画混淆曲线
            'useNoise' => false,            // 是否添加杂点
            'imageH' => 35,               // 验证码图片高度
            'imageW' => 80,               // 验证码图片宽度
            'length' => 3,               // 验证码位数
            'fontttf' => '4.ttf',              // 验证码字体，不设置随机获取
        );
        ob_clean();
        $Verify = new Verify($config);
        $Verify->entry();
    }
}