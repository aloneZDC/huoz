<?php
namespace app\admin\controller;

use think\Db;

class Manage extends Admin
{
    public function _initialize()
    {
        parent::_initialize();
    }

    //判断权限
    private function auth()
    {
        if ($this->admin['admin_id'] != 1) {
            $this->error('只有超级管理员可以进入此页面');
            exit();
        }
    }

    /**
     * 管理员列表
     */
    public function index()
    {
        $this->auth();
        $username=input("username");
        $admin = Db::name('Admin');
        if(!empty($username)){
            $admin->where(['username'=>['like','%'.$username.'%']]);
        }
        $list = $admin->paginate(25,null,['query'=>input()]);
        $show=$list->render();
        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch(); // 输出模板
    }

    /**
     * 黑名单管理
     */
    public function blacklist()
    {
        $this->auth();
        $db = M('blacklist');
        $uid = trim(I("uid"));
        $where = [];
        if(!empty($uid)){
            $where['a.uid'] = $uid;
        }

        $count = $db->alias('a')->where($where)->count();// 查询满足要求的总记录数
        $Page = new \Think\Page($count, 25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        $show = $Page->show();// 分页显示输出

        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = $db->alias('a')->join("inner join ".C("DB_PREFIX")."member b on a.uid = b.member_id")->field("a.*,b.name username")->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出

        $this->assign('uid' ,$uid);
        $this->display(); // 输出模板
    }

    public function saveBlacklist()
    {
        if($this->admin['admin_id'] != 1){
            $this->ajaxReturn(['Code' => 0, 'Msg' => "您没有权限进行此操作"]);
        }

        $id = trim(I("id", ''));
        $ac = trim(I("ac", ''));
        $uid = trim(I("post.uid"));
        $type = trim(I("post.type", 1));
        $active = trim(I("post.active", 1));

        $db = M("blacklist");

        if(!empty($id) && !empty($ac) && $ac == 'del'){
            $del = $db->delete($id);

            if(!$del){
                $this->error("删除失败");
            }

            $this->success("删除成功");
            exit;
        }

        $user = $db->table(C("DB_PREFIX")."member")->where(['member_id' => $uid])->field("name")->find();
        if(empty($user)){
            $this->ajaxReturn(['Code' => 0, 'Msg' => "此用户不存在"]);
        }

        if(empty($id)){
            $black = $db->where(['uid' => $uid])->find();
            if(!empty($black)){
                $this->ajaxReturn(['Code' => 0, 'Msg' => "黑名单已存在此用户"]);
            }
        }

        $data = [
            'uid' => $uid,
            'active' => $active,
            'type' => $type
        ];
        $act = "添加";

        if(empty($id)){
            $save = $db->add($data);
        }else{
            $act = "修改";
            $save = $db->where(['id' => $id])->save($data);
        }

        if(!$save){
            $this->ajaxReturn(['Code' => 0, 'Msg' => $act."操作失败"]);
        }

        $this->ajaxReturn(['Code' => 1, 'Msg' => $user['name']]);
    }

    /**
     * 后台登录记录
     */
    public function record()
    {
        $field="al.*,a.username as admin_name,DATE_FORMAT(FROM_UNIXTIME(addtime),'%Y-%m-%d %H:%i') as addtime";
        $list=Db::name("Admin_log")->alias("al")->field($field)
            ->join(config("database.prefix")."admin a","a.admin_id=al.admin_id","LEFT")
            ->order("al.addtime desc")->paginate(25,null);
        $show=$list->render();
        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出
       return $this->fetch(); // 输出模板
    }

    /**
     * 用户记录
     */
    public function recordUser()
    {
        $model = Db::name('member');

        $member_id = I("member_id", '', 'intval');
        $email = I("email", '', 'trim,strval');
        $date = I("date", '', 'trim,strval');
        $where=null;
        if(!empty($date)){
//            $where[] = ["login_time >= '".strtotime($date)."' and login_time <= '".strtotime($date." 23:59:59")."'"];
            $where['login_time']=['between',[strtotime($date),strtotime($date." 23:59:59")]];
        }

        if($member_id > 0){
            $where['member_id'] = $member_id;
        }

        if(!empty($email)){
            $where['email'] = $email;
        }
        $list = $model->field("member_id,email,nick,name,login_ip,DATE_FORMAT(FROM_UNIXTIME(login_time), '%Y-%m-%d %H:%i') as login_time")
            ->where($where)->order("login_time desc")->paginate(25,null,['query'=>input()]);
       $show=$list->render();
        $this->assign('list', $list);// 赋值数据集
        $this->assign('page', $show);// 赋值分页输出
       return $this->fetch(); // 输出模板
    }

    /**
     * 取地区信息
     * @param string $ip
     * @return string
     */
    private function getAddress($ip = '')
    {
        $err = '未知';
        if(empty($ip)){
            return $err;
        }

        $sina = "http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip=".$ip; //json
        $taobao = "http://ip.taobao.com/service/getIpInfo.php?ip=".$ip; //json
        $youdao = "http://www.youdao.com/smartresult-xml/search.s?type=ip&q=".$ip; //xml
        $addr = file_contents_post($taobao);
        if(!$addr){
            return $err;
        }
        $addr = json_decode($addr);
        $address = $addr['data']['region'].'-'.$addr['data']['city'].'-'.$addr['data']['county']."（{$addr['data']['isp']}）";

        return $address;
    }

    /**
     * 添加管理员
     */
    public function addAdmin()
    {
        $admin = Db::name('Admin');

        if ($_POST){
            $username = I('post.username');
            $password = I('post.password');
            $admin_id = I('post.admin_id');
            $email = I('post.email');
            if (empty($admin_id)&&(empty($username) || empty($password))) {
               return $this->error('请补全信息');
            }
            $pwd = md5(I('post.password'));
            $data['email'] = $email;
            $data['username'] = $username;
            $data['password'] = $pwd;
            $data['pwd_show'] = ''; //I('post.password');
            $data['nickname'] = I('post.nickname', '', 'strval');
            $data['remarks'] = I('post.remarks', '', 'strval');
            $data['rule'] = I('post.rule', 'admin', 'strval');
            if(!checkEmail($email)){
              return  $this->error('邮箱格式不正确');
            }
            if (empty($_POST['admin_id'])) {
                $rs = $admin->where("username='$username'")->find();
                if ($rs) {
                    $this->error('用户名称已存在');
                }
                $rs = $admin->insertGetId($data);
            } else {
                $data['admin_id'] = intval(I('post.admin_id'));
                unset($data['username']);//不修改用户名

                if (empty(I('post.password'))) {
                    unset($data['password']);//传空密码则不修改
                }
                $rs = $admin->where(['admin_id'=>$data['admin_id']])->update($data);
            }

            if ($rs === false) {
                $this->error('操作失败');
            }

           return $this->success('操作成功', U("Admin/Manage/index"));
        }

        $list = null;
        $admin_id=input("admin_id");
        if (!empty($admin_id)) {
            $list = $admin->where('admin_id=' . $admin_id)->find();
        }
        $this->assign('list', $list);

        if (!empty($_GET['admin_id'])) {
           return $this->fetch("editAdmin");
        } else {
           return $this->fetch();
        }
    }

    //修改权限页面
    public function showNav()
    {
        $this->auth();
        $where['admin_id'] = input("admin_id");
        $admin = Db::name('Admin')->where($where)->find();
        $list = explode(',', $admin['nav']);
        $nav = Db::name('Nav')->where("cat_id","<>","test")->select();
        foreach ($nav as $k => $v) {
            if (in_array($v['nav_id'], $list)) {
                $nav[$k]['status'] = 1;
            }else{
                $nav[$k]['status'] = 0;
            }
        }
        $this->assign('nav', $nav);
        $this->assign('id', $where['admin_id']);
       return $this->fetch();
    }

    //修改权限程序
    public function saveNav()
    {
        $this->auth();
        if (I('post.admin_id')) {
            $nav=isset($_POST['nav'])?$_POST['nav']:"";
            $data['nav'] = !empty($nav)?implode(',',$nav):"";
            $data['admin_id'] = I('post.admin_id');
            if($data['admin_id']==1){
               //主帐号只有41这个权限
                $data['nav']=41;
            }
            $rs = Db::name('Admin')->update($data);
            if ($rs) {
                $this->success('修改成功');
            } else {
                $this->error('修改失败');
            }
        } else {
            $this->error('操作有误');
        }
    }

    /**
     * 修改本账号密码
     */
    public function pwdUpdate()
    {
        header("Content-type:text/html;charset=utf-8");
        $admin_id = session('admin_userid');
        $list = Db::name('Admin')->where('admin_id=' . $admin_id)->find();
        if (empty($admin_id)) {
            $this->error('操作有误');
            return;
        }
        if ($_POST) {
            if (empty($_POST['old_pwd'])) {
                $this->error('请输入原始密码');
                return;
            }
            if (empty($_POST['password'])) {
                $this->error('请输入新密码');
                return;
            }
            $old_pwd = md5($_POST['old_pwd']);
            if ($list['password'] != $old_pwd) {
                $this->error('您输入的原始密码错误');
                return;
            }
            if (!checkPwd($_POST['password'])) {
                $this->error('验证密码长度在6-20个字符之间');
                return;
            }
            if(!checkEmail($_POST['email'])){
                $this->error('邮箱格式不正确');
                return;
            }
            $data['password']=md5($_POST['password']);
            $data['email']=$_POST['email'];
            $r = Db::name('Admin')->where('admin_id=' . $admin_id)->update($data);
            if ($r === true) {
                $this->error('修改失败');
                return;
            } else {
                $this->redirect('Login/loginout', '', 1, "<script>alert('修改成功请重新登录')</script>");
                return;
            }
        } else {
            $this->assign('list', $list);
            return $this->fetch();
        }
    }

    /**
     * 删除管理员
     */
    public function delMember()
    {
        $this->auth();
        $admin_id=input("admin_id");
        if (empty($admin_id)) {
            $this->error('要删除的ID不存在');
        }
        if ($admin_id == 1) {
            $this->error('此ID不可删除');
        }
        $admin = Db::name('Admin');
        $r = $admin->where(['admin_id'=>$admin_id])->delete();
        if ($r) {
            $this->success('删除成功', U('Manage/index'));
            return;
        } else {
            $this->error('删除失败');
            return;
        }
    }
}
