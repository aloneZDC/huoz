<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
use think\Db;

class Jim extends Base {
    public function getTradeConfig($member_id,$trade_id) {
        $jim = new \jim\Jim();
        $userinfo = @$jim->request('userinfo', ['username' => $member_id]); //极光获取用户信息
        $userinfo = json_decode($userinfo, true);
        //不存在就注册
        if(isset($userinfo['error']['code']) && $userinfo['error']['code'] == 899002){
            @$jim->request('register', ['username' => $member_id]);
        }

        $ordersInfo = Db::name('trade_otc')->alias('a')
                        ->field('a.*,b.nick as head,c.nick as other_head')
                        ->where(['a.trade_id'=>$trade_id,'a.member_id'=>$member_id])
                        ->join('__MEMBER__ b','a.member_id=b.member_id','left')
                        ->join('__MEMBER__ c','c.member_id=a.other_member','left')->find();
        $config = [];
        if($ordersInfo) {
            $ordersInfo['head'] = $ordersInfo['head'] ?: '';
            $ordersInfo['other_head'] = $ordersInfo['other_head'] ?: '';

            $ordersInfo['head'] = msubstr($ordersInfo['head'],0,1);
            $ordersInfo['other_head'] = msubstr($ordersInfo['other_head'],0,1);

            $config = [
                'appkey' => $jim->app_key,
                'secret' => $jim->master_secret,
                'random_str' => md5(rand()),
                'timestamp' => $this->getMillisecond(),
                'target_id' => $ordersInfo['other_member'],
                'target_name' => $ordersInfo['other_head'],
                'target_head' => $ordersInfo['other_head'],
            ];
            $config['name_prefix'] = $jim->name_prefix;
            $config['target_pass'] = $jim->request("gen_pass", $member_id);
            $config['signature'] = strtoupper(md5("appkey={$config['appkey']}&timestamp={$config['timestamp']}&random_str={$config['random_str']}&key={$config['secret']}"));

            if($ordersInfo['type']=='sell') {
                $config['send_id'] = 'kd_'.$ordersInfo['trade_id'].'_'.$ordersInfo['other_trade_id'];
            } else {
                $config['send_id'] = 'kd_'.$ordersInfo['other_trade_id'].'_'.$ordersInfo['trade_id'];
            }

            $config['user_id'] = $ordersInfo['member_id'];
            $config['user_name'] = $ordersInfo['head'];
            $config['user_pass'] = md5($config['name_prefix'] . $ordersInfo['member_id']);
            $config['user_head'] = msubstr($ordersInfo['head'],0,1);
        }

        return $config;
    }

    //系统消息
    public function sys_message($send_id,$body='') {
        return $this->send_messages($send_id,'order',0,0,$body,1);
    }

    public function send_messages($send_id,$send_type,$from_id,$target_id,$body,$msg_push_type=0) {
        $data = [
            'msg_extend' => $send_id,
            'msg_type' => $send_type,
            'across_id' => $from_id,
            'target_id' => $target_id,
            'msg_content' => $body,
            'msg_time' => time()*1000,
            'msg_push_type' => $msg_push_type,
        ];

        $flag = Db::name('im')->insertGetId($data);
        if($flag) {
            return [
                'msg_push_type' => $data['msg_push_type'],
                'msg_time' => $data['msg_time'],
            ];
        }

        return false;
    }

    //获取历史消息
    public function get_messages($member_id,$send_id) {
        $where = "im.msg_type = 'order' and im.msg_extend = '{$send_id}'";
        $field = "im.msg_id,im.msg_content as content,im.across_id,im.target_id,im.msg_time,im.msg_push_type,b.nick as across_head,b.name as across_head1";
        $list = Db::name('im')->alias('im')->field($field)
                ->join('__MEMBER__ b','im.across_id=b.member_id','left')
                ->where($where)->order("im.msg_time asc")->select();

        if($list) {
            foreach ($list as $key=>$value){
                //无昵称,使用真实姓名
                $value['across_head'] = $value['across_head'] ?: $value['across_head1'];
                $value['user_head'] = msubstr($value['across_head'],0,1);

                //系统消息
                if($value['msg_push_type']==1) {
                    $value['content'] = lang($value['content']);
                    $value['_position'] = 'm';
                } else {
                    $value['content'] = htmlspecialchars_decode($value['content']);
                    if($member_id == $value['across_id']){
                        $value['_position'] = "r";
                    } else {
                        $value['_position'] = 'l';
                    }
                }

                $list[$key] = $value;
            }
        } else {
            $list = [];
        }
        return $list;
    }

    private static function getMillisecond() {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }
}
