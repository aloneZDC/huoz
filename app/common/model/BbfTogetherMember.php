<?php
//四币连发配置表
namespace app\common\model;

use think\Model;
use think\Db;
use think\Exception;

class BbfTogetherMember extends Base
{
    static function addMember($member_id,$currency_id) {
        $item = self::where([
            'member_id' => $member_id,
            'currency_id' => $currency_id,
        ])->find();

        if(!empty($item)) return true;

        return self::insertGetId([
            'member_id' => $member_id,
            'currency_id' => $currency_id,
            'team_total' => 0,
            'add_time' => time(),
        ]);
    }

    static function addParentTeamNum($child_member_id,$currency_id,$num) {
        return self::execute('update '.config("database.prefix").'bbf_together_member a,'.config("database.prefix").'member_bind b 
            set a.team_total=a.team_total+'.$num.' where a.member_id = b.member_id and a.currency_id='.$currency_id.' and  b.child_id='.$child_member_id.';');
    }

    //能否获取3代奖励
    static function isThirdAward($pid_member_id,$child_member_id,$currency_id) {
        //获取直推中的最大区
        $child_max_num = self::query('select *  from '.config("database.prefix").'bbf_together_member where member_id in(
            select child_id from '.config("database.prefix").'member_bind where member_id='.$pid_member_id.' and level=1
        ) and currency_id='.$currency_id.' order by team_total desc limit 1;');

        $max_member_id = 0;
        if($child_max_num && isset($child_max_num[0])) {
            //在大区中 获取不到奖励
            $max_member_id = intval($child_max_num[0]['member_id']);
            $member_bind = MemberBind::where(['member_id'=>$max_member_id,'child_id'=>$child_member_id])->find();
            if(!empty($member_bind)) return false;
        } else {
            //没有大区 获取不到奖励
            return false;
        }
        return true;
    }
}
