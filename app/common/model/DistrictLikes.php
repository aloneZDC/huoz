<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
use think\Exception;
use think\Db;

class DistrictLikes extends Base {
	//点赞操作
    public function addlike($district_id,$member_id) {
    	Db::startTrans();
        try{
			$is_like = Db::name('district_likes')->lock(true)->where(['member_id'=>$member_id,'district_id'=>$district_id])->find();
			if($is_like) throw new Exception(lang('lan_network_busy_try_again'));

           	$log_id = Db::name('district_likes')->insertGetId([
           		'member_id' => $member_id,
                'district_id' => $district_id,
                'add_time' => time(),
           	]);
           	if(!$log_id) throw new Exception(lang('lan_network_busy_try_again'));

           	$flag = Db::name('district')->where(['id'=>$district_id])->setInc('like_count');
           	if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();
            return ['id'=>$log_id];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    //取消赞
    public function cancelLike($district_id,$member_id) {
    	Db::startTrans();
        try{
			$is_like = Db::name('district_likes')->where(['member_id'=>$member_id,'district_id'=>$district_id])->find();
			if(!$is_like) throw new Exception(lang('lan_network_busy_try_again'));

            $flag = Db::name('district_likes')->where(['id' => $is_like['id']])->delete();
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

           	$flag = Db::name('district')->where(['id'=>$district_id])->setDec('like_count');
           	if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();
            return ['flag'=>true];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }
}
