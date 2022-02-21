<?php
//投票 俱乐部
namespace app\h5\controller;

use app\common\model\CurrencyUserTransfer;
use app\common\model\GameLockLog;
use app\common\model\UsersVotes;
use app\common\model\UsersVotesAward;
use app\common\model\UsersVotesConfig;
use app\common\model\UsersVotesPay;
use think\Db;
use think\Exception;
use think\Request;

class Votes extends Base
{
    protected $is_decrypt = false; //不验证签名

    //投票首页
    public function votes_info()
    {
        $result = UsersVotes::votes_info($this->member_id);
        $this->output_new($result);
    }

    //投票等级详情页面
    public function level_info() {
        $result = UsersVotes::level_info($this->member_id);
        $this->output_new($result);
    }

    //投票 使用i盾或O盾进行投票
    public function add_votes() {
        $votes = intval(input("post.votes"));
        $votes_type = strval(input("post.votes_type"));
        $pwd = strval(input("post.pwd",''));
        $result = UsersVotes::vote_by_dun($this->member_id,$votes,$votes_type,$pwd);
        $this->output_new($result);
    }

    //投票 使用豆或积分进行投票
//    public function add_votes() {
//        $votes = intval(input("post.votes"));
//        $votes_type = strval(input("post.votes_type"));
//        $pwd = strval(input("post.pwd"));
//        $result = UsersVotes::vote_by_dou($this->member_id,$votes,$votes_type,$pwd);
//        $this->output_new($result);
//    }

    //投票等级详情页面 好友页面
    public function level_child_list() {
        $page = intval(input("post.page"));
        $result = UsersVotes::child_list($this->member_id,$page);
        $this->output_new($result);
    }

    public function pay_list() {
        $page = intval(input("post.page"));
        $result = UsersVotesPay::pay_list($this->member_id,$page);
        $this->output_new($result);
    }

    public function award_list() {
        $page = intval(input("post.page"));
        $result = UsersVotesAward::get_list($this->member_id,$page);
        $this->output_new($result);
    }

    //授权修改下级等级
    public function change_child_level() {
        $child_user_id = intval(input('child_user_id'));
        $level = intval(input('level'));
        $result = UsersVotes::change_child_level($this->member_id,$child_user_id,$level);
        $this->output_new($result);
    }

    public function wallets() {
        $result = UsersVotes::wallets($this->member_id);
        $this->output_new($result);
    }

    public function wallets_info() {
        $curency_id = intval(input('currency_id'));
        $type = strval(input('type'));
        $result = UsersVotes::wallets_info($this->member_id,$curency_id,$type);
        $this->output_new($result);
    }

    //IO券记录
    public function game_lock_list() {
        $income_type = input('income_type','strval,trim');
        $page = intval(input("post.page"));
        $result = GameLockLog::get_list($this->member_id,$income_type,$page,10,true);
        if($result['code']==SUCCESS) {
            $result['result'] = $result['result']['list'];
        }
        $this->output_new($result);
    }

    //账本记录
    public function accountbook_list() {
        $currency_id = intval(input('currency_id',0));
        $type = intval(input('type',0));
        $page = input('page', 1, 'intval,filter_page');
        $page_size = 10;
        $list = model('AccountBook')->getLog($this->member_id,$currency_id,$type,$page,$page_size,$this->lang);
        if(empty($list)) {
            $this->output_new([
                'code' => ERROR1,
                'message' => lang('not_data'),
                'result' => null,
            ]);
        } else {
            $this->output_new([
                'code' => SUCCESS,
                'message' => lang('data_success'),
                'result' => $list,
            ]);
        }
    }

    //资产互转
    public function transfer() {
        $currency_id = intval(input('currency_id',0));
//        $target_user_id = intval(input('target_user_id',0));
        $target_account = strval(input('target_account',''));
        $type = strval(input('type','num'));
        $num = input('num',0);
        $memo = strval(input('memo',''));

        $result = CurrencyUserTransfer::transfer($this->member_id, $currency_id, $target_account, '', $num, $type,$memo);
        $this->output_new($result);
    }

    public function transfer_config() {
        $currency_id = intval(input('currency_id',0));
        $type = strval(input('type','num'));
        $result = CurrencyUserTransfer::get_config($this->member_id,$currency_id,$type);
        $this->output_new($result);
    }
}