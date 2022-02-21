<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
use think\Exception;
use think\Db;
class DistrictComments extends Base {
	/**
	 *添加评论
	 *@param member_id 用户ID
	 *@param district_id 动态ID
	 *@param parent_id 上级ID
	 *@param content 内容
	 */
	public function addLog($member_id,$district_id,$parent_id,$content) {
		Db::startTrans();
        try{
        	$data = [
        		'member_id' => $member_id,
	            'district_id' => $district_id,
	            'parent_id' => $parent_id,
	            'content' => htmlentities($content),
	            'add_time' => time(),
        	];
			$log_id = Db::name('district_comments')->insertGetId($data);
           	if(!$log_id) throw new Exception(lang('lan_network_busy_try_again'));

           	$data['id'] = $log_id;

           	$flag = Db::name('district')->where(['id'=>$district_id])->setInc('comment_count');
           	if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();
            return $data;
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
	}

	/**
	 *删除评论
	 *@param member_id 用户ID
	 *@param district_id 动态ID
	 *@param parent_id 上级ID
	 *@param content 内容
	 */
	public function removeLog($member_id,$comment_id) {
		Db::startTrans();
        try{
        	$district_id = Db::name('district_comments')->lock(true)->field("district_id,member_id")->where(['id' => $comment_id])->find();
	        if(!$district_id || $district_id['member_id']!=$member_id) throw new Exception(lang('lan_network_busy_try_again'));

	        $number = 1;
	        //查询下级评论
	        $comment_sum = Db::name('district_comments')->lock(true)->where(['parent_id'=>$comment_id])->count();
	        if($comment_sum>0) {
	        	$flag = Db::name('district_comments')->lock(true)->where(['parent_id'=>$comment_id])->delete();
	        	if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));
	        	$number += $comment_sum;
	        }

	        $flag = Db::name('district')->where(['id'=>$district_id['district_id']])->setDec('comment_count',$number);
	        $flag = Db::name('district_comments')->where(['id' => $comment_id])->delete();
	        if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();
            return ['flag'=>true];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
	}
}
