<?php
namespace app\api\controller;
use think\Db;
use think\Exception;

class AwardRelease extends Base {
    protected $is_method_filter = true;

    //首页
    public function index() {
        $page = input('page', 1, 'intval,filter_page');
        $page_size = input('page_size', 10, 'intval,filter_page');
        $platform = input('post.platform', '', 'strval,trim,strtolower');

        $user_num = model('AwardRelease')->getUserNum($this->member_id);
        $list = model('AwardRelease')->getList($this->member_id,$page,$page_size,$platform);

        $this->output(10000,lang('lan_operation_success'),['user_num'=>$user_num,'list'=>$list]);
    }

    public function info() {
        $user_num = model('AwardRelease')->getUserNum($this->member_id);

        $user_num['currency_name'] = 'XRP';
        $user_num['xrp_btc'] = model('AwardRelease')->toXrp($this->member_id);
        $user_num['btc'] = $this->config['award_release_xrp_num'];
        $user_num['title'] = lang('lan_award_release_title');
        $user_num['desc'] = lang('lan_award_release_dec');

        $this->output(10000,lang('lan_operation_success'),$user_num);
    }
}