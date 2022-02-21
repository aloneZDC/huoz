<?php

namespace app\common\model;

use think\Model;
use think\Exception;
use think\Db;

/**
 *XRP社区管理计划层级关系
 */
class MemberBind extends Base
{
// 设置返回数据集的对象名
    protected $resultSetType = 'collection';

    /**
     * 根据用户id查询和社区等级，查询此用户同部门（同一伞下有多个达成只算一个达成）下的总人数
     * @param $member_id            用户id
     * @param int $level            要查询的社区等级
     * @return int
     * @throws Exception
     * Created by Red.
     * Date: 2019/1/10 14:18
     */
    static function statisticsChildLevelByLevel($member_id, $level = 0)
    {
        if (!empty($member_id)) {
            //查询用户直推一级的列表
            $list = self::where(['member_id' => $member_id, "level" => 1])->field('child_id,child_level')->select()->toArray();
            $count = 0;
            if (!empty($list)) {
                foreach ($list as $value) {
                    //判断等级是否大于或等于要查询的等级,是否总数+1，否则查询该下级的伞下是否有大于或等于要查询等级
                    if ($value['child_level'] >= $level) {
                        $count += 1;
                    } else {
                        $findCount = self::where(['member_id' => $value['child_id']])->where("child_level", ">=", $level)->count();
                        if ($findCount > 0) {
                            $count += 1;
                        }
                    }
                }
            }
            return $count;
        }
        return 0;
    }

    /**
     * 更新用户的社区等级字段(child_level)
     * @param $member_id        用户id
     * @param $chile_level      用户的等级
     * @return $this|bool
     * Created by Red.
     * Date: 2019/1/10 15:11
     */
    static function updateChileLevel($member_id,$chile_level){
        if(!empty($member_id)&&!empty($chile_level)){
            Db::name('boss_plan_level')->insertGetId([
                'member_id' => $member_id,
                'level' => $chile_level,
                'add_time' => time(),
            ]);
          return self::where(['child_id'=>$member_id])->update(['child_level'=>$chile_level]);
        }
        return false;
    }
}