<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
use think\Exception;
use think\Db;

class District extends Base {
    //发布动态
    public function publish($district_data,$attachments_data) {
        if(empty($attachments_data)) {
            $district_data['type'] = 1;
        } elseif($attachments_data['type']=='image') {
            $district_data['type'] = 2;
        } elseif($attachments_data['type']=='video') {
            $district_data['type'] = 3;
        }

        Db::startTrans();
        try{
            $district_id = Db::name('district')->insertGetId($district_data);
            if(!$district_id) throw new Exception(lang('lan_network_busy_try_again'));
            $attachments_data['district_id'] = $district_id;

            $attachments_id = Db::name('district_attachments')->insertGetId($attachments_data);
            if(!$attachments_id) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();
            return ['district_id'=>$district_id];
        } catch (Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

	/**
	 * 获取动态列表
	 *@param member_id 当前用户ID
	 *@param where 查询条件
	 *@param order 排序
	 */
	public function getList($member_id,$where=[],$order='',$page=1,$page_size=10,$is_comments=true) {
		$model = Db::name('district')->alias('a')
                ->field('a.id as district_id,a.group_id,a.member_id,a.add_time as add_time,a.content,a.like_count as likes,a.comment_count,a.longitude,a.latitude,a.location,a.view_count,a.type,b.src as attachments,b.type as attachments_type,b.thumbnail as attachments_thumbnail,m.nick as username,m.head as userhead')
                ->join(config('database.prefix').'district_attachments b','a.id=b.district_id','LEFT')
                ->join(config('database.prefix').'member m','a.member_id=m.member_id','LEFT');

        if(!empty($where)) $model = $model->where($where);
        if(!empty($order)) $model = $model->order($order);

        $result = $model = $model->limit(($page - 1) * $page_size, $page_size)->select();
        if(!$result) return [];

        foreach ($result as $key => $value) {
        	$value = $this->parseDistrict($value,$value['district_id'],$member_id,$is_comments);
        	$result[$key] = $value;
        }

        return $result;
	}

    /**
     *获取动态详情
     *district_id 动态ID
     *member_id 当前用户ID
     */
    public function detail($district_id,$member_id) {
        $result = Db::name('district')->alias('a')
                ->field('a.id as district_id,a.group_id,a.member_id,a.add_time as add_time,a.content,a.like_count as likes,a.comment_count,a.longitude,a.latitude,a.location,a.view_count,b.src as attachments,b.type as attachments_type,b.thumbnail as attachments_thumbnail,m.nick as username,m.head as userhead')
                ->join(config('database.prefix').'district_attachments b','a.id=b.district_id','LEFT')
                ->join(config('database.prefix').'member m','a.member_id=m.member_id','LEFT')
                ->where(['a.id'=>$district_id])->find();
        if(!$result) return [];

        //人气值 +1
        Db::name('district')->where(['id'=>$district_id])->setInc("view_count", 1);
        //群组表信息总量 +1
        Db::name('district_group')->where(['id'=>$result['group_id']])->setInc("popularity_count", 1);

        $result = $this->parseDistrict($result,$district_id,$member_id);
        return $result;
    }

    private function parseDistrict($result,$district_id,$member_id,$is_comments=true) {
        $result['userhead'] =  empty($result['userhead']) ? $this->default_head : $result['userhead'];
    	$result['content'] =  htmlspecialchars_decode($result['content']);
        $result['attachments'] = empty($result['attachments']) ? [] : unserialize($result['attachments']);
        $result['attachments_type'] = empty($result['attachments_type']) ? '' : $result['attachments_type'];
        $result['attachments_thumbnail'] = empty($result['attachments_thumbnail']) ? '' : $result['attachments_thumbnail'];
        $result['username'] = empty($result['username']) ? $result['member_id'] : $result['username'];
        $result['is_like'] = $this->isLike($district_id,$member_id);
        $result['is_follow'] = $this->isFollow($member_id,$result['member_id']);
        $result['comment'] = $is_comments ? $this->getCommlist($result['district_id'], 0, 10) : [];
        $result['attachments'] = $this->parseAttachments($result['attachments']);

        return $result;
    }

    //是否点赞
    private function isLike($district_id,$member_id) {
    	if($member_id<=0) return 0;
    	return Db::name('district_likes')->where(["member_id" => $member_id, "district_id" => $district_id])->count();
    }

    //是否关注
    private function isFollow($member_id,$to_member_id) {
    	if($member_id<=0) return 0;
    	return Db::name('member_follow')->where(["member_id" => $member_id, "to_member_id" => $to_member_id])->count();
    }

    //转换附件格式
    private function parseAttachments($attachments){
    	$image = ['pjpeg', 'jpeg', 'jpg', 'gif', 'bmp', 'png'];
        $video = ['mov', '3gp', 'mp4', 'avi'];
        $array = [];
        foreach ($attachments as $_key => $attachment) {
            preg_match('/(.*?(jpg|jpeg|gif|png|bmp|mov|3gp|mp4|avi))/', $attachment, $file_info);
            if (in_array($file_info[2], $image)) {
                $array[] = [
                    'image' => $attachment
                ];
            }

            if (in_array($file_info[2], $video)) {
                $array[] = [
                    'video' => $attachment
                ];
            }
        }
        return $array;
    }

    /**
     * 递归获取评论列表 [内部复用] 一维数组
     * @param int $district_id
     * @param int $page
     * @param int $page_size
     * @param int $parent_id
     * @param array $result
     * @return array
     */
    public function getCommlist($district_id = 0, $page = 0, $page_size = 10, $parent_id = 0, $result = []) {
        $where = [
            'district_id' => $district_id,
            'parent_id' => $parent_id,
        ];

        $model = Db::name('district_comments')->alias('a')
                ->field('a.id as comment_id,a.parent_id,a.member_id,a.add_time,a.content,m.nick as username,m.head as userhead')
                ->join('__MEMBER__ m','a.member_id=m.member_id','LEFT')
                ->where($where)
                ->order('a.add_time desc');

        $parent_id = intval($parent_id);
        if($parent_id>0) $model = $model->limit($page, $page_size);
        $list = $model->select();

        $result = [];
        if($list) {
            foreach ($list as $value) {
                $value['content'] = htmlspecialchars_decode($value['content']);
                $value['username'] = empty($value['username']) ? $value['member_id'] : $value['username'];
                $value['userhead'] =  empty($value['userhead']) ? $this->default_head : $value['userhead'];
                $value['reply_username'] = "";
                $value['reply_member_id'] = "";
                if ($parent_id > 0) {
                    $reply_userinfo = Db::name('district_comments')->alias('a')
                                        ->field('m.member_id,m.nick as username,m.head as userhead')
                                        ->join(config('database.prefix').'member m','a.member_id=m.member_id','LEFT')
                                        ->where(['a.id'=>$value['parent_id']])->find();
                    if($reply_userinfo) {
                        $value['reply_username'] = empty($reply_userinfo['username']) ? $reply_userinfo['member_id'] : $reply_userinfo['username'];
                        $value['reply_member_id'] = $reply_userinfo['member_id'];
                        $value['reply_userhead'] = empty($reply_userinfo['userhead']) ? $this->default_head : $reply_userinfo['userhead'];
                    }
                }
                $result[$value['comment_id']] = $value;
                $comment_child = $this->getCommlist($district_id, 0, 10, $value['comment_id']);
                if (!empty($comment_child)) {
                    foreach ($comment_child as $_value) {
                        $result[] = $_value;
                    }
                }
            }

            if (!empty($result)) {
                $_array = [];
                foreach ($result as $value) {
                    $_array[] = $value;
                }
                $result = $_array;
                unset($_array);
            }
        }

        return $result;
    }
}
