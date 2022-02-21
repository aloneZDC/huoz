<?php
namespace app\h5\controller;

use app\common\model\AccountBook;
use app\common\model\Currency;
use app\common\model\CurrencyPriceTemp;
use app\common\model\CurrencyUser;
use app\common\model\GroupMiningConfig;
use app\common\model\GroupMiningIncomeLog;
use app\common\model\GroupMiningIncomePioneerDetail;
use app\common\model\GroupMiningLog;
use app\common\model\GroupMiningSourceBuy;
use app\common\model\GroupMiningSourceLevel;
use app\common\model\GroupMiningUser;
use app\common\model\Member;
use app\common\model\MemberBind;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Request;

class Linux extends Base
{
    protected $public_action = ['phone_task','email_task','deny_member','deny_child','update_user_buy','mining_lose_reward','pioneer_reward','get_cache'];
    protected $pwd = 'asd!@123';

    public function phone_task() {
        $pwd = strval(input('pwd',''));
        if($pwd!=$this->pwd) return 'welcome'.$pwd;

        exec('php '.ROOT_PATH.DS.'PhoneTask.php restart -d',$out);
        return json_encode($out);
    }

    public function email_task() {
        $pwd = strval(input('pwd',''));
        if($pwd!=$this->pwd) return 'welcome'.$pwd;

        exec('php '.ROOT_PATH.DS.'EmailTask.php restart -d',$out);
        return json_encode($out);
    }

    public function deny_member() {
        $pwd = strval(input('pwd',''));
        if($pwd!=$this->pwd) return 'welcome'.$pwd;

        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);
        set_time_limit(0);

        $ename = strval(input('ename',''));
        $pid_member = Member::where('ename',$ename)->find();
        if(!$pid_member) return 'not exist';

        Db::name("member")->where(['member_id' => $pid_member['member_id']])->update([
            'status' => 2,
            'token_value'=>md5($pid_member['member_id'].time()),
        ]);
        cache('auto_login_' . $pid_member['member_id'], null);//清除app的登录信息
        cache('pc_auto_login_' . $pid_member['member_id'], null);//清除pc的登录信息

        $list = Member::where('pid',$pid_member['member_id'])->select();
        if(!$list) return 'no child';

        foreach ($list as $item) {
            $update = Db::name("member")->where(['member_id' => $item['member_id']])->update([
                'status' => 2,
                'token_value'=>md5($item['member_id'].time()),
            ]);
            cache('auto_login_' . $item['member_id'], null);//清除app的登录信息
            cache('pc_auto_login_' . $item['member_id'], null);//清除pc的登录信息
        }
        return count($list);
    }

    public function deny_child() {
        $pwd = strval(input('pwd',''));
        if($pwd!=$this->pwd) return 'welcome'.$pwd;

        ini_set("display_errors", 1);
        ini_set('memory_limit','-1');
        config('database.break_reconnect',true);
        set_time_limit(0);

        $ename = strval(input('ename',''));
        $pid_member = Member::where('ename',$ename)->find();
        if(!$pid_member) return 'not exist';

        $ignore = strval(input('ignore',''));
        $ignore_list = [];
        if (!empty($ignore)) {
            $ignore_member = Member::where('ename',$ignore)->find();
            if ($ignore_member) {
                $ignore_list[] = $ignore_member['member_id'];
                $child_list = MemberBind::where('member_id',$ignore_member['member_id'])->column('child_id');
                $ignore_list = array_merge($ignore_list, $child_list);
            }
        }

        Db::name("member")->where(['member_id' => $pid_member['member_id']])->update([
            'status' => 2,
            'token_value'=>md5($pid_member['member_id'].time()),
        ]);
        cache('auto_login_' . $pid_member['member_id'], null);//清除app的登录信息
        cache('pc_auto_login_' . $pid_member['member_id'], null);//清除pc的登录信息

        $list = MemberBind::where('member_id',$pid_member['member_id'])->select();
        if(!$list) return 'no child';

        foreach ($list as $item) {
            if (!in_array($item['child_id'], $ignore_list)) {
                $update = Db::name("member")->where(['member_id' => $item['child_id']])->update([
                    'status' => 2,
                    'token_value'=>md5($item['child_id'].time()),
                ]);
                cache('auto_login_' . $item['child_id'], null);//清除app的登录信息
                cache('pc_auto_login_' . $item['child_id'], null);//清除pc的登录信息
            }
        }
        return count($list);
    }

    public function get_cache() {
        $pwd = strval(input('pwd',''));
        if($pwd!=$this->pwd) return 'welcome'.$pwd;

        $key = strval(input('key',''));
        return cache('regip_'.$key);
    }
}
