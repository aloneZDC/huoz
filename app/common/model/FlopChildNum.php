<?php
namespace app\common\model;
use think\Exception;
use think\Log;
use think\Model;

class FlopChildNum extends Model
{


    const TYPE_ENUM = [
        'hongbao' => '红包',
        'flop' => '翻牌'
    ];

    public function user()
    {
        return $this->belongsTo(Member::class, 'user_id', 'member_id');
    }

    static function getTodayTotalNum($type,$user_id,$today='') {
        if(!$today) $today = date('Y-m-d',time()-86400);
        $info = self::where(['type'=>$type,'user_id'=>$user_id])->find();
        if(!$info || $info['today']!=$today) return 0;
        return $info['child_num'];
    }

    static function getTodayNum($type,$user_id,$today='') {
        if(!$today) $today = date('Y-m-d',time()-86400);
        $info = self::where(['type'=>$type,'user_id'=>$user_id])->find();
        if(!$info || $info['today']!=$today) return 0;
        return $info['child_avail_num'];
    }

    static function reload_today() {
        //删除旧数据
        self::execute('TRUNCATE table '.config("database.prefix").'flop_child_num;');
        //检测
        $count = self::count();
        if($count>0) {
            Log::write('用户资产临时表清除失败');
            return false;
        }
        return true;
    }

    static function parent_inc($type,$user_id,$today) {
        $member = Member::where(['member_id'=>$user_id])->field('member_id,pid')->find();
        if(!$member || $member['pid']<=0) return false;

        try {
            $info = self::where(['type'=>$type,'user_id'=>$member['pid']])->find();
            if($info) {
                $flag = self::where(['id'=>$info['id']])->update([
                    'child_num' => ['inc',1],
                    'child_avail_num' => ['inc',1],
                ]);
            } else {
                $flag = self::insertGetId([
                    'type' => $type,
                    'user_id' => $member['pid'],
                    'child_num' => 1,
                    'child_avail_num' => 1,
                    'today' => $today,
                ]);
            }
            return $flag;
        } catch (Exception $e) {
            return false;
        }
        return true;
    }
}
