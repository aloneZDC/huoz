<?php
//用户上下级关系定时任务
namespace app\common\model;
use think\Model;
use think\Exception;
use think\Db;

class MemberBindTask extends Base {
    static function add_task($member_id) {
        //增加直推人数
        $flag = Member::where(['member_id'=>$member_id])->setInc('recommand_num',1);
        if(!$flag) return false;

        return self::insertGetId([
            'member_id' => $member_id,
            'add_time' => time(),
        ]);
    }
}