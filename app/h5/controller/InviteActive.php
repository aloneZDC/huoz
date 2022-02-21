<?php
namespace app\h5\controller;

use app\common\model\Config;
use app\common\model\Currency;
use app\common\model\CurrencyUser;
use app\common\model\Member;
use app\common\model\MemberBindTask;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;

class InviteActive extends Base
{
    protected $public_action = ['init','invite_active'];

    public function init() {
        $number = floattostr(Config::get_value('invite_active_num', '0.006'));
        $giveNum = floattostr(Config::get_value('invite_active_give_num', '0.005'));
        $currency = Currency::where('currency_mark', Currency::PUBLIC_CHAIN_NAME)->find();
        $result = [
            'number'=>$number,
            'give_num'=>$giveNum,
            'currency_name'=>$currency['currency_name'],
        ];
        $this->output_new(10000,lang('lan_operation_success'),$result);
    }

    /**
     * 邀请激活
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function invite_active() {

        $active_name = input('post.active_name', '', 'strval,trim,strtolower');
        $username = input('post.username', '', 'strval,trim,strtolower');
        $password = input('post.password', '', 'strval');

        if (empty($active_name) || !checkUname($active_name)) $this->output_new(10001,lang('lan_Please_enter_the_correct'),null);
        if (empty($username) || !checkUname($username)) $this->output_new(10001,lang('lan_Please_enter_the_correct'),null);
        if (empty($password)) $this->output_new(10001,lang('lan_login_please_enter_your_password'),null);

        $userInfo = Db::name('member')->where('ename', $username)->find();
        if (!$userInfo) $this->output_new(10001,lang('lan_Account_does_not_exist'),null);//帐号不存在
        if ($userInfo['active_status'] != 1) $this->output_new(10001,lang('lan_user_not_active'),null);//帐号未激活，无法登录

        $activeUser = Db::name('member')->where(['ename'=>$active_name])->find();
        if (!$activeUser) $this->output_new(10001,lang('lan_Account_does_not_exist'),null);//帐号不存在
        if ($activeUser['active_status'] == 1) $this->output_new(10001,lang('lan_user_already_active'),null);//已激活

        $currency = Currency::where('currency_mark', Currency::PUBLIC_CHAIN_NAME)->find();
        $currency_id = $currency['currency_id'];
        $member_id = $userInfo['member_id'];
        $active_member_id = $activeUser['member_id'];

        $userCurrency = CurrencyUser::getCurrencyUser($member_id, $currency_id);
        $activeUserCurrency = CurrencyUser::getCurrencyUser($active_member_id, $currency_id);

        $number = floattostr(Config::get_value('invite_active_num', '0.006'));
        $giveNum = floattostr(Config::get_value('invite_active_give_num', '0.005'));
        $fee = floattostr(bcsub($number, $giveNum, 6));
        if (bccomp(floattostr($userCurrency['num']), $number, 6) == -1) {
            $this->output_new(10001,lang('insufficient_balance'),null);
        }

        try {
            Db::startTrans();

            $flag = Member::where(['member_id'=>$active_member_id, 'active_status'=>0])->update([
                'active_status'=>1,
                'pid'=>$member_id,
            ]);
            if(!$flag) throw new Exception('更新激活用户信息失败-in line:'.__LINE__);

            $flag = $log_id = db('MemberActiveLog')->insertGetId([
                'member_id' => $member_id,
                'active_member_id' => $active_member_id,
                'currency_id' => $currency_id,
                'num' => $number,
                'give_num' => $giveNum,
                'fee' => $fee,
                'add_time' => time(),
            ]);
            if ($flag === false) throw new Exception('添加激活记录失败-in line:'.__LINE__);

            $flag = model("AccountBook")->addLog([
                'member_id'=>$member_id,
                'currency_id'=> $currency_id,
                'number_type'=>2,
                'number'=>$number,
                'type'=>2700,
                'content'=>"lan_invite_active",
                'fee'=> $fee,
                'to_member_id'=>$active_member_id,
                'to_currency_id'=>0,
                'third_id'=> $log_id,
            ]);
            if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);

            //操作账户
            $flag = setUserMoney($member_id, $currency_id, $number, 'dec', 'num');
            if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

            $flag = model("AccountBook")->addLog([
                'member_id'=>$active_member_id,
                'currency_id'=> $currency_id,
                'number_type'=>1,
                'number'=>$giveNum,
                'type'=>2700,
                'content'=>"lan_invite_active",
                'fee'=> 0,
                'to_member_id'=>0,
                'to_currency_id'=>0,
                'third_id'=> $log_id,
            ]);
            if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);

            //操作账户
            $flag = setUserMoney($active_member_id, $currency_id, $giveNum, 'inc', 'num');
            if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

            //添加用户上下级关系定时任务
            $flag = MemberBindTask::add_task($active_member_id);
            if($flag === false) throw new Exception('添加关系定时任务失败-in line:'.__LINE__);

            Db::commit();
            $this->output_new(SUCCESS,lang('lan_operation_success'),null);
        }
        catch (Exception $e) {
            Db::rollback();

            $this->output_new(ERROR1,lang('lan_network_busy_try_again').',异常信息:'.$e->getMessage(),null);
        }
    }

    /**
     * 我的激活
     */
    public function my_active_old() {

        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $child_name = input('child_name', '', 'strval');
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['count'] = 0;
        $r['result'] = null;

        $where = [
            'a.member_id' => $this->member_id,
        ];
        if(!empty($child_name)) {
            $where['m.ename'] = $child_name;
        }
        $r['count'] = db('MemberActiveLog')->alias('a')->where($where)
            ->join(config("database.prefix") . "member m", "a.active_member_id=m.member_id", "LEFT")->count('id');
        $list = db('MemberActiveLog')->alias('a')->field('m.ename,m.phone,m.email,a.add_time')
            ->where($where)
            ->join(config("database.prefix") . "member m", "a.active_member_id=m.member_id", "LEFT")
            ->page($page, $page_size)->order("a.add_time desc")->select();
        if($list) {
            foreach ($list as $key=>&$value) {
                $value['add_time'] = date('m/d H:i', $value['add_time']);
                if(empty($value['phone'])) $value['phone'] = $value['email'];
                unset($value['email']);
            }
            $r['code'] = SUCCESS;
            $r['message'] = lang('data_success');
            $r['result'] = $list;
        }

        return $this->output_new($r);
    }

    // 我的直推
    public function my_active()
    {
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $child_name = input('child_name', '', 'strval');
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['count'] = 0;
        $r['result'] = null;

        $where['pid'] = $this->member_id;
        $r['count'] = db('Member')->alias('a')->where($where)->count();
        $list = db('Member')->field(['ename', 'email', 'phone', 'reg_time'])->where($where)->page($page, $page_size)->order(['reg_time' => 'desc'])->select();
        if ($list) {
            foreach ($list as $key => &$value) {
                $value['add_time'] = date('m/d H:i', $value['reg_time']);
                if (empty($value['phone'])) $value['phone'] = $value['email'];
                unset($value['email']);
            }
            $r['code'] = SUCCESS;
            $r['message'] = lang('data_success');
            $r['result'] = $list;
        }

        return $this->output_new($r);
    }
}
