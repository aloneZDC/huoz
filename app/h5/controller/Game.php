<?php

namespace app\h5\controller;

use app\common\model\Article;
use app\common\model\CurrencyUser;
use app\common\model\GameConfig;
use app\common\model\PublicMsgList;
use app\common\model\RoomLevelSetting;
use app\common\model\RoomRobotList;
use app\common\model\RoomUsersList;
use app\common\model\RoomUsersRecord;
use app\common\model\Member;
use app\common\model\UsersVotes;
use think\Cache;
use think\Db;
use think\Exception;
use think\Request;
use think\response\Json;

class Game extends Base
{
    protected $is_decrypt = false; //不验证签名

    /**
     * 游戏首页
     */
    public function index()
    {
        $list = RoomLevelSetting::with(['currency'])->field('rls_level,rls_currency_id,rls_num,rls_is_vip,rls_is_common')->select();
        $common_list = [];
        $vip_list = [];
        $record_list = [];
        $public_list = [];
        if (!empty($list)) {
            foreach ($list as $key => $value) {
                if ($value['rls_is_common'] == 1) {//能否创建普通房 1-是 2-否
                    $userWhere = [
                        'rul_status'=>1,
                        'rl_level_id'=>$value['rls_level'],
                        'rl_is_vip'=>2,
                        'rl_is_del'=>1,
                    ];
                    $user_num = Db::name('RoomUsersList')->alias('a')->join(config('DB_PREFIX') . 'room_list b', 'a.rul_room_id=b.rl_room_id', 'left')->where($userWhere)->count('rul_id');
                    //var_dump(Db::name('RoomUsersList')->getLastSql());
                    $common_list[] = [
                        'rls_level'=>$value['rls_level'],
                        'rls_num'=>intval($value['rls_num']),
                        'currency_name'=>$value['currency']['currency_name'],
                        'user_num'=>$user_num,
                    ];
                }
                if ($value['rls_is_vip'] == 1) {//能否创建VIP房 1-是 2-否
                    $vip_list[] = [
                        'rls_level'=>$value['rls_level'],
                        'rls_num'=>intval($value['rls_num']),
                        'currency_name'=>$value['currency']['currency_name'],
                    ];
                }
            }
        }
        $field = 'rur_id,rur_room_id,rur_member_id,rur_part_num,rur_seat_id,rur_result,rur_currency_id,rur_money,rur_create_time,rur_is_robot,rur_actual_money,rur_lock_num';
        $recordSelect = RoomUsersRecord::with(['currency'])->field($field)->limit(3)->order('rur_id', 'desc')->select();
        if (!empty($recordSelect)) {
            foreach ($recordSelect as $key => $value) {
                if ($value['rur_is_robot'] == 2) {//是否是机器人 1-普通用户 2-机器人
                    $user = RoomRobotList::where('rrl_id', $value['rur_member_id'])->field('rrl_nickname')->find();
                    $nick = $user['rrl_nickname'];
                }
                else {
                    $user = Member::where('member_id', $value['rur_member_id'])->field('nick')->find();
                    $nick = $user['nick'];
                }
                if ($value['rur_result'] == 2) {//输
                    $rur_money = number_format($value['rur_lock_num'],2,".","");
                    $currency_name = lang('votes_score_lock');//券
                }
                else {
                    $rur_money = number_format($value['rur_actual_money'],2,".","");
                    $currency_name = $value['currency']['currency_name'];
                }
                $record_list[] = [
                    'rur_id'=>$value['rur_id'],
                    'rur_result'=>$value['rur_result'],
                    //'rur_result_txt'=>RoomUsersRecord::RESULT_ENUM[$value['rur_result']],
                    'rur_result_txt'=>RoomUsersRecord::RESULT_ENUM[1],
                    'rur_money'=>$rur_money,
                    'currency_name'=>$currency_name,
                    'nick'=>$nick,
                    'rur_create_time'=>date('Y-m-d H:i:s', $value['rur_create_time']),
                ];
            }
        }
        $publicSelect = PublicMsgList::order('pml_create_time', 'DESC')->limit(3)->select();
        if (!empty($publicSelect)) {
            foreach ($publicSelect as $key => $value) {
                $user = Member::where('member_id', $value['pml_member_id'])->field('nick')->find();
                $public_list[] = [
                    'pml_id'=>$value['pml_id'],
                    'pml_content'=>userTextDecode($value['pml_content']),
                    'pml_room_id'=>$value['pml_room_id'],
                    'nick'=>$user['nick'],
                    'pml_create_time'=>date('Y-m-d H:i:s', $value['pml_create_time']),
                ];
            }
        }
        $game_currency_id = GameConfig::get_value('game_currency_id', 22);
        $usersCurrency = CurrencyUser::getCurrencyUser($this->member_id, $game_currency_id);
        $userWhere = [
            'rul_member_id'=>$this->member_id,
            'rul_status'=>1,
            'rl_is_del'=>1,
        ];
        $userFind = Db::name('RoomUsersList')->alias('a')->join(config('DB_PREFIX') . 'room_list b', 'a.rul_room_id=b.rl_room_id', 'left')->where($userWhere)->find();
        $roomId = 0;
        if ($userFind) {
            $roomId = $userFind['rl_room_id'];
        }
        $r['result'] = [
            'money'=>$usersCurrency['num'],
            'is_vip'=>intval(UsersVotes::check_create_vip_room($this->member_id)),
            'room_id'=>$roomId,
            'common_list'=>$common_list,
            'vip_list'=>$vip_list,
            'record_list'=>$record_list,
            'public_list'=>$public_list,
        ];
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        return mobileAjaxReturn($r);
    }

    /**
     * 获取房间等级列表
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_room_level_list()
    {
        $list = RoomLevelSetting::with(['currency'])->select();
        if (!empty($list)) {
            foreach ($list as $key => &$value) {
                $value['room_num'] = 0;
                $value['user_num'] = 0;
            }
            return mobileAjaxReturn($list, lang("data_success"), SUCCESS);
        } else {
            return mobileAjaxReturn(null, lang("no_data"), ERROR1);
        }
    }

    /**
     * 获取游戏攻略
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_game_rule()
    {
        $r = ['code' => SUCCESS, 'message' => lang('data_success'), 'result' => []];
        //$result = Article::get_article_by_keyword('game_rule');
        $class_id = db::name('article_category')->where('keywords', 'game_rule')->value('id');
        if ($class_id) {
            $lang = $this->getLang();
            $articleModel = db('Article');
            if (empty($lang)) {
                $field = 'c.article_id,c.title,c.content,c.art_pic,c.add_time,t.name as title_name';
            } else {
                $field = 'c.article_id,c.' . $lang . '_title as title,c.' . $lang . '_content as content,c.art_pic,c.add_time,t.name_' . $lang . ' as title_name';
            }
            $zixun = $articleModel->field($field)->alias('c')->join("article_category t ", "   c.position_id=t.id ", 'left')->where('position_id',$class_id)->order('add_time desc')->find();
            @$zixun['content'] = html_entity_decode($zixun['content']);
//            @$zixun['add_time'] = date('Y/m/d H:i', $zixun['add_time']);
            @$zixun['add_time'] = '';
            $r['result'] = $zixun;
        }
        return mobileAjaxReturn($r);
    }

    /**
     * 我的战绩
     */
    public function my_record(Request $request)
    {
        $type = $request->post('type', 0);//类型 0-全部 1-赢 2-输
        $page = $request->post('page', 1);
        $length = $request->post('length', 10);

        $start = ($page - 1) * $length;
        $field = 'rur_id,rur_room_id,rur_member_id,rur_part_num,rur_seat_id,rur_result,rur_currency_id,rur_money,rur_create_time,rur_actual_money,rur_fee,rur_lock_num';
        $where = [
            'rur_member_id'=>$this->member_id,
            //'rur_result'=>$type,
        ];
        if ($type) $where['rur_result'] = $type;
        $recordSelect = RoomUsersRecord::with(['currency','room'])->field($field)->where($where)->limit($start, $length)->order('rur_id', 'desc')->select();
        if (!empty($recordSelect)) {
            foreach ($recordSelect as $key => $value) {
                //$user = Member::where('member_id', $value['rur_member_id'])->field('nick')->find();
                if ($value['rur_result'] == 2) {//输
                    $rur_money = number_format($value['rur_lock_num'],2,".","");
                    $currency_name = lang('votes_score_lock');//券
                }
                else {
                    $rur_money = number_format($value['rur_actual_money'],2,".","");
                    $currency_name = $value['currency']['currency_name'];
                }
                $data[] = [
                    'rur_id'=>$value['rur_id'],
                    'rur_result'=>$value['rur_result'],
                    //'rur_result_txt'=>RoomUsersRecord::RESULT_ENUM[$value['rur_result']],
                    'rur_result_txt'=>RoomUsersRecord::RESULT_ENUM[1],
                    'rur_money'=>$rur_money,
                    'rur_fee'=>number_format($value['rur_fee'],2,".",""),
                    'currency_name'=>$currency_name,
                    //'nick'=>$user['nick'],
                    'rur_create_time'=>date('Y-m-d H:i:s', $value['rur_create_time']),
                    'rur_room_id'=>$value['rur_room_id'],
                    'rur_currency_num'=>intval($value['room']['rl_num']).$value['currency']['currency_name'],
                    'room_type_name'=>$value['room']['rl_is_vip'] == 1 ? lang('VIP房间') : lang('普通房间'),
                ];
            }
            return mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $data]);
        }
        else {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('no_data'), 'result' => null]);
        }
    }

    /**
     * 战绩列表
     */
    public function record_list(Request $request)
    {
        $type = $request->post('type', 0);//类型 0-全部 1-赢 2-输
        $page = $request->post('page', 1);
        $length = $request->post('length', 10);

        $start = ($page - 1) * $length;

        $field = 'rur_id,rur_room_id,rur_member_id,rur_part_num,rur_seat_id,rur_result,rur_currency_id,rur_money,rur_create_time,rur_is_robot,rur_actual_money,rur_lock_num';
        $recordSelect = RoomUsersRecord::with(['currency'])->field($field)->limit($start, $length)->order('rur_id', 'desc')->select();
        $record_list = [];
        if (!empty($recordSelect)) {
            foreach ($recordSelect as $key => $value) {
                if ($value['rur_is_robot'] == 2) {//是否是机器人 1-普通用户 2-机器人
                    $user = RoomRobotList::where('rrl_id', $value['rur_member_id'])->field('rrl_nickname')->find();
                    $nick = $user['rrl_nickname'];
                }
                else {
                    $user = Member::where('member_id', $value['rur_member_id'])->field('nick')->find();
                    $nick = $user['nick'];
                }
                if ($value['rur_result'] == 2) {//输
                    $rur_money = number_format($value['rur_lock_num'],2,".","");
                    $currency_name = lang('votes_score_lock');//券
                }
                else {
                    $rur_money = number_format($value['rur_actual_money'],2,".","");
                    $currency_name = $value['currency']['currency_name'];
                }
                $record_list[] = [
                    'rur_id'=>$value['rur_id'],
                    'rur_result'=>$value['rur_result'],
                    //'rur_result_txt'=>RoomUsersRecord::RESULT_ENUM[$value['rur_result']],
                    'rur_result_txt'=>RoomUsersRecord::RESULT_ENUM[1],
                    'rur_money'=>$rur_money,
                    'currency_name'=>$currency_name,
                    'nick'=>$nick,
                    'rur_create_time'=>date('Y-m-d H:i:s', $value['rur_create_time']),
                ];
            }
            return mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $record_list]);
        }
        else {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('no_data'), 'result' => null]);
        }
    }

    /**
     * 公共消息列表
     */
    public function public_msg_list(Request $request)
    {
        $page = $request->post('page', 1);
        $length = $request->post('length', 10);

        $start = ($page - 1) * $length;

        $publicSelect = PublicMsgList::order('pml_create_time', 'DESC')->limit($start, $length)->select();
        if (!empty($publicSelect)) {
            foreach ($publicSelect as $key => $value) {
                $user = Member::where('member_id', $value['pml_member_id'])->field('nick')->find();
                $public_list[] = [
                    'pml_id'=>$value['pml_id'],
                    'pml_content'=>userTextDecode($value['pml_content']),
                    'pml_room_id'=>$value['pml_room_id'],
                    'nick'=>$user['nick'],
                    'pml_create_time'=>date('Y-m-d H:i:s', $value['pml_create_time']),
                ];
            }
            return mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $public_list]);
        }
        else {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('no_data'), 'result' => null]);
        }
    }

    /**
     * Banner列表
     */
    public function banner_list(Request $request)
    {
        $bannerSelect = Db::name('Flash')->where('type', 6)->where('lang',$this->lang)->order('sort', 'asc')->select();
        if (count($bannerSelect)) {
            $bannerList = [];
            foreach ($bannerSelect as $key => $value) {
                $bannerList[] = [
                    'flash_id'=>$value['flash_id'],
                    'title'=>$value['title'],
                    'pic'=>$value['pic'],
                    'jump_url'=>$value['jump_url'],
                ];
            }
            return mobileAjaxReturn(['code' => SUCCESS, 'message' => lang('data_success'), 'result' => $bannerList]);
        }
        else {
            return mobileAjaxReturn(['code' => ERROR1, 'message' => lang('no_data'), 'result' => null]);
        }
    }
}
