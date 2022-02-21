<?php
namespace app\index\controller;

use Think\Db;
use app\common\model\Member;

class User extends Base
{
    //个人信息
    public function safe()
    {
        $member_info=Db::name('member')->field("member_id,email,idcard,phone,name,nick,reg_time,head")->where(['member_id'=>$this->member_id])->find();
        $member_info['phone']=!empty($member_info['phone']) ? substr($member_info['phone'],0,3).'****'.substr($member_info['phone'],9,2) : '';
        $member_info['email']=!empty($member_info['email']) ? substr($member_info['email'],0,3).'****'.substr($member_info['email'],-7) : '';
        $member_info['idcard']=!empty($member_info['idcard']) ? substr( $member_info['idcard'],0,3).'****'.substr($member_info['idcard'],-3) : '';
        $member_info['reg_time']=!empty($member_info['reg_time']) ? date('Y-m-d H:i:s',$member_info['reg_time']) : '';

        $verify_state = 0;
        $info = Db::name('verify_file')->where(['member_id'=>$this->member_id])->find();
        if($info) $verify_state = $info['verify_state'];

        return $this->fetch('user/safe',['member_info'=>$member_info,'verify_state'=>$verify_state]);
    }

    //增加昵称
    public function modifynick()
    {
        $nick = input('post.nick','strval');
        if(empty($nick)) mobileAjaxReturn(['status'=>0, 'info' =>lang('lan_The_nickname_empty')]);

        $nick = str_replace(' ', '', $nick);
        if(strlen($nick)<4 || strlen($nick)>30) mobileAjaxReturn(['status'=>0, 'info' =>lang('lan_nickname_format_error')]);
        $flag = Db::name('member')->field('member_id')->where(['nick'=>$nick])->find();
        if($flag) mobileAjaxReturn(['status'=>0, 'info' =>lang('lan_modifymember_nicknames_to_take_up')]);
        $flag = Db::name('member')->where(['member_id'=>$this->member_id])->setField('nick',$nick);
        if($flag){
            mobileAjaxReturn(['status'=>1, 'info' =>lang('lan_operation_success')]);
        } else {
            mobileAjaxReturn(['status'=>0, 'info' =>lang('lan_operation_failure')]);
        }

    }
    /**
     * ajax头像上传
     */
    public function addPicForAjax()
    {


        // 上传成功
        $pic = $this->oss_upload($_FILES, "Member/Head/");

        $pic = $pic['Filedata'];
        $where['member_id'] = $this->member_id;
        Db::name('member')->where($where)->update(array('head' => $pic));
        session('USER_HEAD', $pic);//用户名
        $r['status'] = 1;
        $r['info'] = $pic;
        mobileAjaxReturn($r);

    }
    //安全设置
    public function simple_verify()
    {
        $member_info=Db::name('member')->where(['member_id'=>$this->member_id])->find();
        $member_info['email']=empty($member_info['email'])?"":substr($member_info['email'],0,3).'****'.substr($member_info['email'],-7);
        return $this->fetch('user/simple_verify',['member_info'=>$member_info]);
    }
    //实名验证
    public function senior_verify()
    {
        $model_member= new Member();
        if ($_POST) {
            $post=input('post.');
            $post['member_id']=$this->member_id;
            $post['nation_id']=input('post.nation_id', 0, 'intval');
            $max = 5 * 1024 * 1024;
            if(!$_FILES['auth_1']['size'] > 0){

                $r['status'] = 0;
                $r['message'] =lang('lan_upload_front_photo');
                mobileAjaxReturn($r);
            } elseif ($_FILES['auth_1']['size'] > $max) {
                $r['status'] = 0;
                $r['message'] =lang('lan_upload_front_photo').' '.lang('lan_picture_to_big');
                mobileAjaxReturn($r);
            }

            if(!$_FILES['auth_2']['size'] > 0){
                $r['status'] = 0;
                $r['message'] =lang('lan_upload_reverse_photo');
                mobileAjaxReturn($r);
            } elseif ($_FILES['auth_2']['size'] > $max) {
                $r['status'] = 0;
                $r['message'] =lang('lan_upload_reverse_photo').' '.lang('lan_picture_to_big');
                mobileAjaxReturn($r);
            }

            if(!$_FILES['auth_3']['size'] > 0){
                $r['status'] = 0;
                $r['message'] =lang('lan_upload_handheld_photo');
                mobileAjaxReturn($r);
            } elseif ($_FILES['auth_2']['size'] > $max) {
                $r['status'] = 0;
                $r['message'] =lang('lan_upload_handheld_photo').' '.lang('lan_picture_to_big');
                mobileAjaxReturn($r);
            }
            $upload = $this->upload_auth($post['member_id']);
            $post['pic1'] = $upload['Msg']['auth_1'];
            $post['pic2'] = $upload['Msg']['auth_2'];
            $post['pic3'] = $upload['Msg']['auth_3'];
            $post['country_code'] =$post['countries'];
            $post['member_id'] =$this->member_id;
            if ($upload['Code'] == 0) {
                $r['status'] = 0;
                $r['message'] =$upload['Msg'];
                mobileAjaxReturn($r);
            }
            $r=$model_member->add_verify($post);
            if($r['code']!=10000){
                $r['status'] = 0;
                $r['message'] =$r['message'];
                mobileAjaxReturn($r);
            }else{
                $r['status'] = 1;
                $r['message'] =lang('lan_realname_certification_submitted_successfully');
                mobileAjaxReturn($r);
            }
        }else{
            $lang = $this->getLang();
            $countries_field="cn";
            if($lang=="en")$countries_field="en";
            $countries_list=$model_member->countries_code($countries_field."_name as name,phone_code");
            if($lang=="tc")$lang="nation";
            $nation_list=$model_member->nation($lang."_name as nation_name,nation_id");
            $info=Db::name('verify_file')->where(['member_id'=>$this->member_id])->find();
            if(empty($info)||$info['verify_state']==0){
                $info['verify_state']=0;
                $info['name']="";
                $info['cardtype']="";
                $info['idcard']="";
                $info['nation_id']="";
                $info['nation_name']="";
                $info['sex']="";
                $info['pic1']="";
                $info['pic2']="";
                $info['pic3']="";
                $info['country_code']="";
            }
            $info['idcard']=!empty($info['idcard']) ? substr( $info['idcard'],0,3).'****'.substr($info['idcard'],-3) : '';
            $info['verify_state'] = intval($info['verify_state']);
            if($info['verify_state']==1){
                return $this->fetch("user/senior_verify_view",['nation'=>$nation_list,'countries'=>$countries_list,'info'=>$info]);
            } else {
                return $this->fetch("user/senior_verify",['nation'=>$nation_list,'countries'=>$countries_list,'info'=>$info]);
            }
        }
    }
    //收支方式跳转链接验证
     public function idcard_verify()
    {
        $info=Db::name('verify_file')->where(['member_id'=>$this->member_id])->find();
         $r['status'] = 1;
        if(empty($info)||$info['verify_state']==0||$info['verify_state']==2){
            $r['status'] = 0;
        }
        mobileAjaxReturn($r);
    }
    public function getList($flag=false){
        if($flag) $where['b1.status'] = 1;

        $where['b1.member_id'] = $this->member_id;

        $return = [];
        foreach (['bank','wechat','alipay'] as $type) {
            $field = $this->getField($type);

            $model = Db::name('member_'.$type)->alias('b1');
            if($type=='bank') $model = $model->join("left join __BANKLIST__ as b2","b2.id = b1.bankname");


            $info = $model->field($field)->where($where)->select();
            print_r($info);die;
            if($flag) {

                $info = $model->find();
                if($info) $return[$type] = $info;
            } else {

                $info = $model->select();
                print_r($info);die;
                $return[$type] = empty($info) ? [] : $info;
            }
        }

        return $return;
    }
    private function getField($type) {
        if($type=='bank') {
            $lang = $this->getLang();

            $bname = '';
            if($lang=='tc') {
                $bname = 'name';
            } else {
                $bname = 'englishname';
            }
            $field = 'b1.id,b1.bankadd as inname,bankcard as cardnum,b1.status,b1.truename,b2.'.$bname.' as bname,b2.id as bank_id';
        }elseif ($type=='wechat') {
            $field = 'b1.id,b1.wechat as cardnum,b1.wechat_pic as img,b1.status,b1.truename';
        } elseif ($type=='alipay') {
            $field = 'b1.id,b1.alipay as cardnum,b1.alipay_pic as img,b1.status,b1.truename';
        }

        return $field;
    }
    //修改登录密码@标
     public function updatePassword()
    {
        if($this->request->isPost()){
        $oldPwd = input('post.oldpwd', '', 'strval');
        $newPwd = input('post.pwd', '', 'strval');
        $rePwd = input('post.repwd', '', 'strval');
        $model = model('member');
        $flag = $model->checkPwdModif($newPwd,$rePwd,$oldPwd);
        if(is_string($flag))  mobileAjaxReturn(['status' => 0, 'info' =>$flag]);
        $phone_user = Db::name('member')->field('pwd,pwdtrade')->where(['member_id'=>$this->member_id])->find();
        if (!$phone_user) mobileAjaxReturn(['status' => 0, 'info' =>lang('lan_Password_error1')]);

        $old_pwd = $model->password($oldPwd);
        if($old_pwd!=$phone_user['pwd']) mobileAjaxReturn(['status' => 0, 'info' => lang('lan_Password_error1')]);

        $new_password = $model->password($newPwd);
        if($new_password==$phone_user['pwdtrade'])mobileAjaxReturn(['status' => 0, 'info' => lang('lan_user_Transaction_password_login')]);  //交易密碼和登錄密碼不能一致

        $update = $model->where(['member_id' => $this->member_id])->setField('pwd',$new_password);
        if($update=== false) mobileAjaxReturn(['status' => 0, 'info' =>lang('lan_network_busy_try_again')]);
            session('USER_KEY_ID',null);
            session('USER_KEY_TOKEN',null);
            mobileAjaxReturn(['status' => 1, 'info' =>lang('lan_operation_success')]);
        }else {

            $member_info = Db::name('member')->where(['member_id' => $this->member_id])->find();
            $data['phone'] = $member_info['phone'];
            $data['type'] = 2;
            if (empty($member_info['phone'])) {
                $data['phone'] = $member_info['email'];
                $data['type'] = 3;
            }
            return $this->fetch('user/updatePassword',['data'=>$data]);
        }


    }

    /**
     * 修改交易密码@标
     */
    public function retradepass() {
        $oldPwd = input('post.oldpwd_b', '', 'strval');
        $newPwd = input('post.pwdtrade', '', 'strval');
        $rePwd = input('post.repwdtrade', '', 'strval');
        $phone_user = Db::name('member')->field('phone,email,country_code,pwd,pwdtrade')->where(['member_id'=>$this->member_id])->find();
        if(!$phone_user) mobileAjaxReturn(['status' => 0, 'info' =>lang('lan_input_personal_info')]);
        $model = model('member');
        $flag = $model->checkPwdTradeModif($newPwd,$rePwd,$oldPwd );
        if(is_string($flag)) mobileAjaxReturn(['status' => 0, 'info' =>$flag]);
        $old_pwd = $model->password($oldPwd);
        if($old_pwd!=$phone_user['pwdtrade']) mobileAjaxReturn(['status' => 0, 'info' => lang('lan_Password_error1')]);
        $new_password = $model->password($newPwd);
        if($new_password==$phone_user['pwd']) mobileAjaxReturn(['status' => 0, 'info' =>lang('lan_user_Transaction_password_login')]); //交易密碼和登錄密碼不能一致
        $update = $model->where(['member_id' => $this->member_id])->update(['pwdtrade'=>$new_password,'pwdtrade_error'=>0]);
        if($update=== false) mobileAjaxReturn(['status' => 0, 'info' =>lang('lan_network_busy_try_again')]);
        session('USER_KEY_ID',null);
        session('USER_KEY_TOKEN',null);
        mobileAjaxReturn(['status' => 1, 'info' =>lang('lan_operation_success')]);
    }
    //找回交易密码@标
    public function update_trade_pwd()
    {
        $newPwd = input('post.pwd', '', 'strval');
        $rePwd = input('post.repwd', '', 'strval');
        $code = input('post.code', '', 'strval');
        $account = input('post.account', '', 'strval');
        //校验验证码是否正确
        $sender=Db::name('sender')->where(['log_account'=>$account,'log_captcha'=>$code])->order('send_time desc')->find();
        if(empty($sender)) mobileAjaxReturn(['status' => 0, 'info' =>lang('lan_validation_incorrect')]);
        $phone_user = Db::name('member')->field('phone,email,country_code,pwd,pwdtrade')->where(['member_id'=>$this->member_id])->find();
        if(!$phone_user) mobileAjaxReturn(['status' => 0, 'info' =>lang('lan_input_personal_info')]);
        $model = model('member');
        $flag = $model->checkPwdTradeModif($newPwd,$rePwd);
        if(is_string($flag)) mobileAjaxReturn(['status' => 0, 'info' =>$flag]);
        $new_password = $model->password($newPwd);
        if($new_password==$phone_user['pwd']) mobileAjaxReturn(['status' => 0, 'info' =>lang('lan_user_Transaction_password_login')]); //交易密碼和登錄密碼不能一致
        $update = $model->where(['member_id' => $this->member_id])->setField('pwdtrade',$new_password);
        if($update=== false) mobileAjaxReturn(['status' => 0, 'info' =>lang('lan_network_busy_try_again')]);
        mobileAjaxReturn(['status' =>1, 'info' =>lang('lan_operation_success')]);
    }
    // 邀請獎勵
    public function invit()
    {
        $requset=request();
        $domain_url=$requset->domain();
        $member_info=Db::name('member')->field("invit_code")->where(['member_id'=>$this->member_id])->find();
        $url=$domain_url.url('Reg/index',['pid'=>$member_info['invit_code']]);

        $model_member=Db::name('member');
        //  $this->member_id=15012;
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $count=$model_member->where(['pid'=>$this->member_id])->count();
        $member_list=$model_member->field('member_id,phone,email,reg_time,nick')->where(['pid'=>$this->member_id])->limit(($page - 1) * $page_size, $page_size)->select();
        $pages = $this->getPages($count,$page,$page_size);
        if(!empty($member_list)){
            foreach( $member_list as $key=>$value){
//                $bind_info=Db::name('member_bind')->field("level")->where(['member_id'=>$value['member_id']])->find();
//                $member_list[$key]['level']=$bind_info['level']>0?'LV'.$bind_info['level']:'LV'.'0';
                $member_list[$key]['phone']=$value['phone']?$value['phone']:$value['email'];
            }
        }

        return $this->fetch('user/invit',['invit_code'=>$member_info['invit_code'],'qrcode_url'=>$url,'pid_cout'=>$count,'member_list'=>$member_list,'pages'=>$pages]);
    }
    public function qrcode() {

        require_once WEB_PATH.'../extend/phpqrcode'.DS.'phpqrcode.php';
        $size = intval(input('size'));
        if($size>10 || $size<=0) $size = 4;
        $member_info=Db::name('member')->field("invit_code")->where(['member_id'=>$this->member_id])->find();
        $url = urldecode(url('/mobile/Reg/mobile', ['pid'=>$member_info['invit_code']], false , $domain = true));
        $object = new \QRcode();
        ob_clean();//这个一定要加上，清除缓冲区
        $object->png($url, false, 'Q', $size, '2');
        exit;
    }
    // 贈送領取
    public function gift_collection()
    {
       // $this->member_id='15012';
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $count=Db::name('currency_user_num_award')->where(['member_id'=>$this->member_id])->count();
        $award=Db::name('currency_user_num_award')->where(['member_id'=>$this->member_id])->limit(($page - 1) * $page_size, $page_size)->select();
        $pages = $this->getPages($count,$page,$page_size);
        if(!empty($award)){
            foreach ($award as $key=>$val){
                $award[$key]['currency_name']=$this->currency()[$val['currency_id']];
                if($val['type']==1){
                    $award[$key]['type']=lang('lan_register_give');
                }elseif($val['type']==2){
                    $award[$key]['type']=lang('lan_invitation_give');
                }

            }
        }

        return $this->fetch('user/gift_collection',['award_list'=>$award,'pages'=>$pages]);
    }
    public function release(){
//        $this->member_id='15012';
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');

        $count=Db::name('currency_award_freed')->field("rate,money,total,time,currency_id")->where(['member_id'=>$this->member_id])->count();
        $freed=Db::name('currency_award_freed')->field("rate,money,total,time,currency_id")->where(['member_id'=>$this->member_id])->limit(($page - 1) * $page_size, $page_size)->select();
        $pages = $this->getPages($count,$page,$page_size);
        if(!empty($freed)){
            foreach ($freed as $k=>$vl){
                $freed[$k]['currency_name']=$this->currency()[$vl['currency_id']];
                $freed[$k]['rate']=($vl['rate']*10).'‰';
            }
        }
        return $this->fetch('user/release',['freed_list'=>$freed,'pages'=>$pages]);
    }
    private function currency(){
        $currency_list=Db::name('currency')->field("currency_name,currency_id,currency_logo")->select();

        $currency_arr=[];
        if(!empty($currency_list)){
            foreach ($currency_list as $key =>$val){
                $currency_arr[$val['currency_id']]=$val['currency_name'];
            }
        }
      return $currency_arr;
    }
    // 我的推薦
    public function recommend()
    {
        $model_member=Db::name('member');
      //  $this->member_id=15012;
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $count=$model_member->where(['pid'=>$this->member_id])->count();
        $member_list=$model_member->field('member_id,phone,email,reg_time')->where(['pid'=>$this->member_id])->limit(($page - 1) * $page_size, $page_size)->select();
        $pages = $this->getPages($count,$page,$page_size);
        if(!empty($member_list)){
            foreach( $member_list as $key=>$value){
                $bind_info=Db::name('member_bind')->field("level")->where(['member_id'=>$value['member_id']])->find();
                $member_list[$key]['level']=$bind_info['level']>0?'LV'.$bind_info['level']:'LV'.'0';
                $member_list[$key]['phone']=$value['phone']?$value['phone']:$value['email'];
            }
        }
        return $this->fetch('user/recommend',['pid_cout'=>$count,'member_list'=>$member_list,'pages'=>$pages]);
    }
    // 邀請挖礦
    public function mining()
    {
        return $this->fetch('user/mining');
    }

    //我的成交
    public function myDeal(){

        //获取主积分类型
        $currency =db('Currency')->where('is_line=1 ')->field("currency_mark,currency_id")->order('sort ASC')->select();

        $this->assign('culist',$currency);
        $currency_trade_list = ['XRP','USDT'];
        $culist_trade = [];

        foreach($currency as $key=>$value){
            if(in_array($value['currency_mark'],$currency_trade_list)){
                $culist_trade[] = $value;
            }
        }
        $this->assign('culist_trade',$culist_trade);
        $currencytype = intval(input('currency'));
        $currency_trade = intval(input('currency_trade'));
        $search['currency'] =  $currencytype;
        $search['currency_trade'] =  $currency_trade;

        if(!empty($currencytype)){
            $where['currency_id'] =$currencytype;
        }
        if(!empty($currency_trade)){
            $where['currency_trade_id'] =$currency_trade;
        }
        $where['member_id'] = session('USER_KEY_ID');

        $page = input('page',1);
        $numPage = input('rows',10);
        $startPage = ($page-1)*$numPage;
        $count = db('Trade')->where($where)->order('add_time desc')->count();
        $list = db('Trade')->where($where)->order('add_time desc')->limit($startPage,$numPage)->select();
        $show = $this->getPages($count,$page,$numPage);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('list',$list);
        $this->assign('search',$search);
        return $this->fetch('user/myDeal');
    }
    //委托管理
    public function manage(){
        //获取主积分类型
        $currency=db('Currency')->where('is_line=1 ')->field("currency_mark,currency_id")->order('sort ASC')->select();
        $this->assign('culist',$currency);
        $currency_trade_list = ['XRP','USDT'];
        $culist_trade = [];
        foreach($currency as $key=>$value){
            if(in_array($value['currency_mark'],$currency_trade_list)){
                $culist_trade[] = $value;
            }
        }
        $this->assign('culist_trade',$culist_trade);
        $currency_trade = intval(input('currency_trade'));
        $currencytype = intval(input('currency'));
        $status=intval(input('status'));
        $search = [
            'currency' => $currencytype,
            'currency_trade' => $currency_trade,
            'status' => $status,
            '_status' => input('status')
        ];
        if(input('status')==='0'){
            $search['status'] =2;
        }
        if(!empty($currencytype)){
            $where['currency_id'] =$currencytype;
        }
        if(!empty($currency_trade)){
            $where['currency_trade_id'] =$currency_trade;
        }
        $where['status'] =array('in',"$status");
        if($status == 2){
            $where['status'] = array('in','0,1');
        }
        $where['member_id'] = session('USER_KEY_ID');

        $page = input('page',1);
        $numPage = input('rows',10);
        $startPage = ($page-1)*$numPage;
        $count      = db('Orders')->where($where)->count();// 查询满足要求的总记录数

        $list = db('Orders')->where($where)->limit($startPage,$numPage)->order('add_time desc')->select();
        $show = $this->getPages($count,$page,$numPage);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('list',$list);
        $this->assign('search',$search);
        return $this->fetch('user/manage');
    }
    //委托历史
    public function history(){

        //获取主积分类型
        $currency=db('Currency')->where('is_line=1 ')->field("currency_mark,currency_id")->order('sort ASC')->select();
        $this->assign('culist',$currency);
        $currency_trade_list = ['XRP','USDT'];
        $culist_trade = [];
        foreach($currency as $key=>$value){
            if(in_array($value['currency_mark'],$currency_trade_list)){
                $culist_trade[] = $value;
            }
        }
        $this->assign('culist_trade',$culist_trade);
        $currency_trade = intval(input('currency_trade'));
        $currencytype = intval(input('currency'));
        $status=intval(input('status'));
        $search = [
            'currency' => $currencytype,
            'currency_trade' => $currency_trade,
            'status' => $status
        ];
        if(!empty($currencytype)){
            $where['currency_id'] =$currencytype;
        }
        if(!empty($currency_trade)){
            $where['currency_trade_id'] =$currency_trade;
        }

        $where['status'] = array('in','-1,2');
        $where['member_id'] = session('USER_KEY_ID');

        if(!empty($status)){
            $where['status'] =array('in',"$status");
        }
        $page = input('page',1);
        $numPage = input('rows',10);
        $startPage = ($page-1)*$numPage;


        $count      = db('Orders')->where($where)->count();// 查询满足要求的总记录数

        $list = db('Orders')->where($where)->limit($startPage,$numPage)->order('add_time desc')->select();
        $show = $this->getPages($count,$page,$numPage);
        $this->assign('page',$show);// 赋值分页输出

        $this->assign('list',$list);
        $this->assign('search',$search);
        return $this->fetch('user/history');
    }

    // 绑定手机@标
    public function phoneBinding()
    {
        if($this->request->isPost()){

            $country_code = intval(input('post.country_code'));
            $phone = input("post.phone", '', 'strval');
            $phone_code = input("post.phone_code", '', 'strval');

            $flag = model('member')->bindphone($this->member_id,$phone,$phone_code,$country_code);

            if(is_string($flag)) mobileAjaxReturn(['status' => 0, 'info' =>$flag]);
            mobileAjaxReturn(['status' => 1, 'info' =>lang('lan_operation_success')]);
        }else{
            $countries = Db::name("countries_code")->field($this->lang . '_name as name,phone_code as countrycode')->where('status=1')->order('sort asc')->select();

            return $this->fetch('user/phoneBinding',['countries'=>$countries]);
        }


    }
    // 绑定邮箱@标
    public function EMvalidation()
    {
        if($this->request->isPost()){
            $email = input('post.email');
            $email_code = input('email_code');
            $flag = model('member')->bindEmail($this->member_id,$email,$email_code);
            if(is_string($flag)) mobileAjaxReturn(['status' => 0, 'info' =>$flag]);
            mobileAjaxReturn(['status' => 1, 'info' =>lang('lan_operation_success')]);

        }else{
            return $this->fetch('user/EMvalidation');
        }

    }

    //更改绑定手机@标
    public function modifyphone() {
        $model_member=model('member');
        if($this->request->isPost()){
            $phone_code = input("post.phone_code", '', 'strval,trim');
            $new_phone = input("post.new_phone", '', 'strval,trim'); //原手机及验证码
            $new_phone_code = input("post.new_phone_code", '', 'strval,trim');
            $country_code = intval(input('post.country_code'));
            $member_phone=Db::name('member')->where('member_id',$this->member_id)->value('phone');
            if($member_phone){
                session('USER_KEY_ID',null);
                session('USER_KEY_TOKEN',null);
            }
            $flag = $model_member->modifyphone($this->member_id,$phone_code,$new_phone,$new_phone_code,$country_code);
            if(is_string($flag))   mobileAjaxReturn(['status' => 0, 'info' =>$flag]);
            mobileAjaxReturn(['status' => 1, 'info' =>lang('lan_operation_success')]);
        }else{
            $member_info=$model_member->where(['member_id'=>$this->member_id])->find();
            $member_info['phone']=substr($member_info['phone'],0,3).'****'.substr($member_info['phone'],9,2);
            $countries = Db::name("countries_code")->field($this->lang . '_name as name,phone_code as countrycode')->where('status=1')->order('sort asc')->select();
            return $this->fetch('user/modifyphone',['member_info'=>$member_info,'countries'=>$countries]);
        }

    }

    //更改绑定邮箱@标
    public function modifyemail() {
        if($this->request->isPost()){
            $email_code = input("post.email_code", '', 'strval,trim');
            $new_email = input("post.new_email", '', 'strval,trim'); //原手机及验证码
            $new_email_code = input("post.new_email_code", '', 'strval,trim');
            $member_phone=Db::name('member')->where('member_id',$this->member_id)->value('email');
            if($member_phone){
                session('USER_KEY_ID',null);
                session('USER_KEY_TOKEN',null);
            }
            $flag = model('member')->modifyemail($this->member_id,$email_code,$new_email,$new_email_code);
            if(is_string($flag)) mobileAjaxReturn(['status' => 0, 'info' =>$flag]);

            mobileAjaxReturn(['status' => 1, 'info' =>lang('lan_operation_success')]);
        }else{
            $email=Db::name('member')->where('member_id',$this->member_id)->value('email');
            $email=substr($email,0,3).'****'.substr($email,-7);
            $this->assign('email',$email);
            return $this->fetch('user/modifyemail');
        }

    }

}
