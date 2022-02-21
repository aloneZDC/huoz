<?php
//投票 俱乐部 升级记录
namespace app\common\model;

use think\Exception;
use think\Model;

class UsersVotesUpgrade extends Model
{
    /**
     * @param $type 升级方式 admin管理员设置 pid上级设置  team团队业绩升级 game游戏积分升级
     * @param $user_id 用户ID
     * @param $level 级别
     * @param int $team_num 团队业绩
     * @param int $game_num 游戏积分
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    static function upgrade($type,$user_id,$level,$team_num=0,$game_num=0){
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        try{
            self::startTrans();
            $log_id = self::insertGetId([
                'type' => $type,
                'user_id' => $user_id,
                'level' => $level,
                'team_num' => $team_num,
                'game_num' => $game_num,
                'add_time' => time(),
            ]);
            if(!$log_id) {
                $r['code'] = ERROR2;
                throw new Exception("operation_failed_try_again");
            }

            $flag = UsersVotes::where(['user_id'=>$user_id])->setField('level',$level);
            if($flag===false) {
                $r['code'] = ERROR3;
                throw new Exception("operation_failed_try_again");
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage();
        }
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }
}