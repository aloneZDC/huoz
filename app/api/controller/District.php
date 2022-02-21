<?php
namespace app\api\controller;
use think\Exception;
use think\Db;

class District extends Base{
	protected $public_action = ['read','group','version','androidversion'];
    //已使用 read group  to_like follows members comment_push district_comment_remove
    //可删除

    //发现首页
    public function read() {
        $request_type = input('post.request_type', '', 'trim,strval');
        if (!in_array($request_type, ['list', 'my_index', 'index', 'detail'])) $this->output(10001,lang('lan_operation_failure'));

        $result = [];
        //单个动态
        if ($request_type == 'detail') {
            $district_id = input('post.district_id', 0, 'intval');
            if($district_id<=0) $this->output(10001,lang('lan_operation_failure'));

            $result = model('District')->detail($district_id,$this->member_id);
            $this->output(10000,lang('lan_operation_success'),$result);
        } elseif (in_array($request_type, ['list', 'my_index', 'index'])) {
            //list 首页
            $page = input('post.page', 1, 'intval,filter_page');
            $page_size = input('post.page_size', 10, 'intval,filter_page');
            $type = input('type',0,'intval');

            $where = [];
            if($type>0) $where['type'] = $type;

            $is_comments = true;
            if ($request_type == 'list') {
                $group_id = input('post.group_id', 0, 'intval');
                if($group_id>=0) $where['a.group_id'] = $group_id;
                $is_comments = false;
            } elseif ($request_type=='my_index') {
                if($this->member_id<=0) $this->output(10100,lang('lan_modifymember_please_login_first'));
                $where['a.member_id'] = $this->member_id;
            } elseif ($request_type=='index') {
                $member_id = input('post.member_id', 0, 'intval');
                if($member_id<=0) $this->output(10000,lang('lan_operation_success'),[]);
                $where['a.member_id'] = $member_id;
            }

            $result = model('District')->getList($this->member_id,$where,'a.add_time desc',$page,$page_size,$is_comments);
            $count = Db::name('District')->alias('a')->where($where)->count();
            $this->output(10000,lang('lan_operation_success'),['list'=>$result,'count'=>$count]);
        }
    }

    //群组
    public function group() {
        $request_type = input('post.request_type','','trim,strval');
        if(!in_array($request_type, ['list', 'one'])) $this->output(10001,lang('lan_operation_failure'));

        if($request_type==='one'){
            //获取单条群组信息
            $group_id = input("post.group_id", '', 'intval');
            if(empty($group_id)) $this->output(10001,lang('lan_operation_failure'));

            $result = Db::name('district_group')->field('id as group_id,name as group_name,logo as group_logo,note_count,popularity_count')->where(['id'=>$group_id])->find();

            $this->output(10000,lang('lan_operation_success'),$result ? $result : []);
        } elseif($request_type==='list') {
            //获取群组列表

            $field = 'id as group_id,name as group_name,logo as group_logo,note_count,popularity_count';
            $hot = Db::name('district_group')->field($field)->order("popularity_count desc")->limit(3)->select();
            if(!$hot) $hot = [];

            $list = Db::name('district_group')->field($field)->order("popularity_count desc")->select();
            if(!$list) $list = [];
            $this->output(10000,lang('lan_operation_success'),['hot'=>$hot,'list'=>$list]);
        }
    }

    //发布评论
    public function comment_push() {
        $district_id = input('post.district_id', 0, 'intval');
        $content = input('post.content', '', 'strval,htmlspecialchars');
        $parent_id = input('post.parent_id', 0, 'intval');

        if($district_id<=0) $this->output(10001,lang('lan_operation_failure'));
        if(empty($content)) $this->output(10001,lang('lan_say_something'));

        $info = Db::name('district')->where(['id'=>$district_id])->find();
        if(!$info) $this->output(10001,lang('lan_district_notexists'));

        $result = model('DistrictComments')->addLog($this->member_id,$district_id,$parent_id,$content);
        if(is_string($result)) $this->output(10001,$result);

        $return_data = [];
        $return_data['content'] = html_entity_decode($result['content']);
        $return_data['parent_id'] = $result['parent_id'];
        $return_data['add_time'] = $result['add_time'];
        $return_data['member_id'] = $result['member_id'];

        $userInfo = Db::name('member')->alias('a')->field('a.nick as username,a.head as userhead')->where(['member_id'=>$result['member_id']])->find();
        if(empty($userInfo['userhead'])) $userInfo['userhead'] = model('member')->default_head;
        $return_data = array_merge($return_data,$userInfo);

        $return_data['reply_username'] = '';
        $return_data['reply_member_id'] = '';
        if ($parent_id > 0) {
            $reply_userinfo = Db::name('district_comments')->alias('a')->field('b.member_id,b.nick as username,b.head as userhead')->join('__MEMBER__ b','a.member_id=b.member_id','LEFT')->where(['a.id'=>$parent_id])->find();
            if($reply_userinfo) {
                $return_data['reply_userhead'] = empty($reply_userinfo['userhead']) ? model('member')->default_head : $reply_userinfo['userhead'];
                $return_data['reply_username'] = empty($reply_userinfo['username']) ? strval($reply_userinfo['member_id']) : $reply_userinfo['username'];
                $return_data['reply_member_id'] = $reply_userinfo['member_id'];
            }
        }
        $this->output(10000,lang('lan_operation_success'),$return_data);
    }

    //删除评论
    public function district_comment_remove() {
        $comment_id = input('post.comment_id', '', 'intval');
        if ($comment_id <= 0) $this->output(10001,lang('lan_district_notexists'));

        $district_id = Db::name('district_comments')->field("district_id,member_id")->where(['id' => $comment_id])->find();
        if(!$district_id) $this->output(10000,lang('lan_operation_success'));

        $result = model('DistrictComments')->removeLog($this->member_id,$comment_id);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'));
    }

    public function comment_get() {
        $request_type = input('post.request_type', '', 'trim,strval');
        if (!in_array($request_type, ['list','detail'])) $this->output(10001,lang('lan_operation_failure'));

        $result = [];
        //单个动态
        if ($request_type == 'detail') {
            $this->output(10000,lang('lan_operation_success'),[]);
        } elseif (in_array($request_type, ['list'])) {
            $district_id = input('post.district_id', '', 'intval');
            if($district_id<=0) $this->output(10000,lang('lan_operation_success'),[]);

            $page = input('post.page', 1, 'intval,filter_page');
            $page_size = input('post.page_size', 10, 'intval,filter_page');
            $result = model('district')->getCommlist($district_id, $page, $page_size);
            $this->output(10000,lang('lan_operation_success'),$result);
        }
    }

    //点赞/取消点赞
    public function to_like() {
        $district_id = input('post.district_id', '', 'intval');
        if($district_id<=0) $this->output(10001,lang('lan_operation_failure'));

        $is_like = Db::name('district_likes')->where(['member_id' => $this->member_id,'district_id' => $district_id])->find();

        $result = '';
        if(!$is_like) {
            $result = model('DistrictLikes')->addlike($district_id,$this->member_id);
        } else {
            $result = model('DistrictLikes')->cancelLike($district_id,$this->member_id);
        }
        if(is_string($result)) $this->output(10001,$result);

        $count = Db::name('district')->where(['id' => $district_id])->value('like_count');
        $this->output(10000,lang('lan_operation_success'),['count'=>$count,'is_like'=>((!$is_like) ? 1 : 0)]);
    }

    //用户关注/取消关注
    public function follows() {
        $request_type = input('post.request_type','','trim,strval');
        if(!in_array($request_type, ['to_follow', 'canel_follow'])) $this->output(10001,lang('lan_operation_failure'));

        $to_member_id = input('post.member_id', 0, 'intval');
        if($to_member_id<=0) $this->output(10001,lang('lan_operation_failure'));

        $has_follow = Db::name('member_follow')->where(['member_id'=>$this->member_id,'to_member_id'=>$to_member_id])->count();
        if($request_type==='to_follow'){
            if($has_follow>0) $this->output(10000,lang('lan_operation_success'));

            $id = Db::name('member_follow')->insertGetId([
                'member_id' => $this->member_id,
                'to_member_id' => $to_member_id,
                'add_time' => time(),
            ]);

            if($id) {
                $this->output(10000,lang('lan_operation_success'));
            } else {
                $this->output(10001,lang('lan_operation_failure'));
            }
        } elseif($request_type==='canel_follow') {
            if($has_follow==0) $this->output(10000,lang('lan_operation_success'));

            $delete = Db::name('member_follow')->where(['member_id'=>$this->member_id,'to_member_id'=>$to_member_id])->delete();
            if($delete) {
                $this->output(10000,lang('lan_operation_success'));
            } else {
                $this->output(10001,lang('lan_operation_failure'));
            }
        }
    }

    //发布动态
    public function publish() {
        $group_id = input('post.group_id', 0, 'intval');
        $content = input('post.content', '', 'strval,htmlspecialchars');
        $attachments = input('post.attachments', '', 'trim,strval');
        $attachments_type = input('post.attachments_type', 'image', 'trim,strval');
        $longitude = input('post.longitude', '', 'trim');
        $latitude = input('post.latitude', '', 'trim');
        $location = input('post.location', '', 'trim,strval');
        $attachments_thumbnail = "";
        $attachments_thumbnail_data = "";

        if($group_id<=0) $this->output(10001,lang('lan_group_notexists'));
        $group = Db::name('district_group')->where(['id' => $group_id])->find();
        if (!$group) $this->output(10001,lang('lan_group_notexists'));


        if (!empty($attachments)) {
            if (!in_array($attachments_type, ['image', 'video'])) $this->output(10001,lang('lan_operation_failure'));
            if ($attachments_type === 'image') $attachments = @json_decode(base64_decode($attachments), true);
            if ($attachments_type === 'video') $attachments_thumbnail_data = input('post.attachments_thumbnail', '', 'trim,strval');
        }
        if (empty($content) && empty($attachments)) $this->output(10001,lang('lan_say_something'));

        $district_attachments_data = [];
        if (!empty($attachments)) {
            $attachments_list = [];
            if ($attachments_type === 'image') {
                $attachments_list = $this->oss_base64_upload($attachments, 'district');
            } elseif ($attachments_type === 'video') {
                $attachments_list = $this->oss_base64_upload($attachments, 'district', 'video');
            }

            if (!empty($attachments_list)) {
                if ($attachments_list['Code'] == 0 || !is_array($attachments_list['Msg']) || count($attachments_list['Msg']) == 0) $this->output(10001,$attachments_list['Msg']);

                $attachments_list = serialize($attachments_list['Msg']); //附件地址序列化
                $district_attachments_data = [
                    'src' => $attachments_list,
                    'type' => $attachments_type,
                    'thumbnail' => '',
                    'add_time' => time()
                ];

                //视频缩略图附件
                if (!empty($attachments_thumbnail_data)) {
                    $thumbnail_list = $this->oss_base64_upload($attachments_thumbnail_data, 'district');
                    if ($thumbnail_list['Code'] == 1 && !empty($thumbnail_list['Msg'][0])) {
                        $district_attachments_data['thumbnail'] = $thumbnail_list['Msg'][0];
                    }
                }
            }
        }
        $district_data = [
            'group_id' => $group_id,
            'member_id' => $this->member_id,
            'add_time' => time(),
            'content' => $content,
            'longitude' => $longitude,
            'latitude' => $latitude,
            'location' => $location,
        ];
        $result = model('district')->publish($district_data,$district_attachments_data);
        if(is_string($result)) $this->output(10001,$result);

        $this->output(10000,lang('lan_operation_success'),['district_id'=>$result['district_id']]);

    }

    //获取用户资料
    public function members() {
        $member_id = input('post.member_id', 0, 'intval');
        if($member_id<=0) $this->output(10001,lang('lan_operation_failure'));

        $result = Db::name('member')->field('member_id,nick as nickname,head as userhead,profile')->where(['member_id'=>$member_id])->find();
        if(empty($result)) $this->output(10001,lang('lan_operation_failure'));

        //添加访问记录
        if(!empty($this->member_id) && $this->member_id!=$member_id) {
            $is_view = Db::name('member_views')->field('id')->where(['member_id'=>$member_id,'to_member_id'=>$this->member_id])->find();
            if(!$is_view) Db::name('member_views')->insertGetId(['member_id' => $member_id, 'to_member_id' => $this->member_id]);
        }

        $result['userhead'] = empty($result['userhead']) ? model('member')->default_head : $result['userhead'];
        $result['nickname'] = empty($result['nickname']) ? $result['member_id'] : $result['nickname'];
        $result['views'] = Db::name('member_views')->where(['member_id' => $member_id])->count();
        $result['follows'] = Db::name('member_follow')->where(['member_id' => $member_id])->count();
        $result['fans'] = Db::name('member_follow')->where(['to_member_id' => $member_id])->count();
        $result['is_follow'] = 0;

        if(!empty($this->member_id)) $result['is_follow'] = Db::name('member_follow')->where(["member_id" => $this->member_id, "to_member_id" => $member_id])->count();

        $this->output(10000,lang('lan_operation_success'),$result);
    }

    //删除动态
    public function district_remove() {
        $district_id = input('post.district_id', 0, 'intval');
        if($district_id<= 0) $this->output(10001,lang('lan_operation_failure'));

        $info = Db::name('district')->field('group_id,member_id,view_count,like_count,comment_count')->where(['id'=>$district_id])->find();
        if(!$info || $info['member_id']!=$this->member_id) $this->output(10000,lang('lan_operation_success'));

        $flag = Db::name('district')->where(['id'=>$district_id])->delete();
        if($flag) {
            //删除附件
            $flag = Db::name('district_attachments')->where(['district_id'=>$district_id])->delete();
            //删除评论
            if($info['comment_count']>0) Db::name('district_comments')->where(['district_id'=>$district_id])->delete();
            //删除点赞
            if($info['like_count']>0) Db::name('district_likes')->where(['district_id'=>$district_id])->delete();

            //群组表信息总量 群组表人气值 减1
            Db::name('district_group')->where(['id' => $info['group_id']])->update([
                'note_count' => ['dec','1'],
                'popularity_count' => ['dec','1'],
            ]);

            $this->output(10000,lang('lan_operation_success'));
        } else {
            $this->output(10001,lang('lan_operation_failure'));
        }
    }


    /**
     * 关注和粉丝
     */
    public function get_member_follow() {
        $member_id = input('member_id',0,'intval');
        $request_type = input('post.request_type','','trim,strval');
        if($member_id<=0 || !in_array($request_type, ['follow', 'fans'])) $this->output(10001,lang('lan_operation_failure'));

        $where = [];
        if($request_type==='follow'){   //他关注的人
            $where['a.member_id'] = $member_id;
        } elseif($request_type==='fans') {//他的粉丝
            $where['a.to_member_id'] = $member_id;
        }

        $count = Db::name('member_follow')->alias('a')->where(['a.member_id'=>$member_id])->count();
        $list = Db::name('member_follow')->alias('a')
                    ->field('a.id as follow_id,a.to_member_id as member_id,a.add_time as add_time,member.nick as usernick,member.head as userhead')
                    ->join('__MEMBER__ M','a.member_id=m.member_id','LEFT')
                    ->where($where)->limit(50)->select();
        if($list) {
            foreach ($list as $key => $value) {
                $value['userhead'] = empty($value['userhead']) ? mode('member')->default_head : $value['userhead'];
                if ($request_type == 'fans') {
                    $is_follow = Db::name('member_follow')->where(['member_id' => $this->member_id, 'to_member_id' => $value['member_id']])->find();
                    $value['is_follow'] = !$is_follow ? 0 : 1;
                }
                $list[$key] = $value;
            }
        }
        $this->output(10000,lang('lan_operation_success'),['list'=>$list,'count'=>$count]);
    }




    //获取赞的数量
    public function get_like() {
        $district_id = input('post.district_id', '', 'intval');
        if(empty($district_id)) $this->output(10001,lang('lan_operation_failure'));

        $count = Db::name('district_likes')->where(['district_id'=>$district_id])->count();
        $this->output(10000,lang('lan_operation_success'),['likes'=>$count]);
    }



    //商圈搜索
    public function search() {
        $keyword = input('post.keyword');
        if (empty($keyword)) $this->output(10001,lang('lan_search_keyword'));

        $page = input('post.page',1,'intval,filter_page');
        $page_size = input('post.page_size',1,'intval,filter_page');

        $where = [];
        $where['a.content'] = ['like',$keyword.'%'];
        if(is_numeric($keyword)) $where['a.member_id'] = $keyword;
        $where['m.nick'] = ['like',$keyword.'%'];
        $where['d.content'] = ['like',$keyword.'%'];

        $db_prefix = config('database.prefix');
        $count = Db::name('district')->alias('a')
                //->join($db_prefix.'district_attachments b','a.id=b.district_id','LEFT')
                ->join($db_prefix.'member m','a.member_id=m.member_id','LEFT')
                ->join($db_prefix.'district_comments d','a.id=c.district_id','LEFT')
                ->where($where)->count();

        $list = Db::name('district')->alias('a')
                ->field('a.id as district_id,a.group_id,a.member_id,a.add_time as add_time,a.content,a.like_count as likes,a.comment_count,a.longitude,a.latitude,a.location,a.view_count,b.src as attachments,b.type as attachments_type,b.thumbnail as  attachments_thumbnail,m.nick as username,m.head as userhead')
                ->join($db_prefix.'district_attachments b','a.id=b.district_id','LEFT')
                ->join($db_prefix.'member m','a.member_id=m.member_id','LEFT')
                ->join($db_prefix.'district_comments d','a.id=c.district_id','LEFT')
                ->where($where)->limit(($page - 1) * $page_size,$page_size)->select();

        if($list) {
            foreach ($list as $key => $value) {
                $value['userhead'] = empty($value['userhead']) ? mode('member')->default_head : $value['userhead'];
                $value['content'] = htmlspecialchars_decode($value['content']);
                $value['attachments'] = empty($value['attachments']) ? [] : unserialize($value['attachments']);
                $value['add_time'] = $value['add_time'];
                $value['username'] = empty($value['username']) ? $value['member_id'] : $value['username'];

                if(!empty($this->member_id)) {
                    //是否赞过
                    $value['is_like'] = Db::name('district_likes')->where(["member_id" => $this->member_id, "district_id" => $value['district_id']])->count();
                    $value['is_follow'] = Db::name('member_follow')->where(["member_id" => $this->member_id, "to_member_id" => $value['member_id']])->count();
                } else {
                    $value['is_like'] = "0";
                    $value['is_follow'] = "0";
                }
                $value['comment'] = $this->getCommlist($value['district_id'], 0, 10);

                if (!empty($value['attachments'])) {
                    $image = ['pjpeg', 'jpeg', 'jpg', 'gif', 'bmp', 'png'];
                    $video = ['mov', '3gp', 'mp4', 'avi'];
                    $array = [];
                    foreach ($value['attachments'] as $_key => $attachment) {
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
                    $value['attachments'] = $array;
                    unset($array);
                }
                $list[$key] = $value;
            }
        }
    }

    /**
     * 版本更新
     */
    public function version()
    {
        $platform = input('post.platform', '', 'strval,trim,strtolower');
        $type = input('type');
        if (empty($platform) || !in_array($platform, ['ios', 'android'])) $this->output(10001,'请传入平台类型');

        if ($platform == 'ios') {
            $version = Version_ios;
            $url = Version_ios_DowUrl;
        } else if ($platform == 'android') {
            if ($type == 1) {
                $version = Version_Android_New;
                $url = Version_Android_DowUrl_New;
                $explain = Version_Android_Log_New;
            } else {
                $version = Version_Android;
                $url = Version_Android_DowUrl;
                $explain = Version_Android_Log;
            }
        }

        $v = "1";
        $force = "是";
        if ($force == '是') {
            $force = 1;
        } else {
            $force = 2;
        }

        $explain = str_replace("，", ",", $explain);
        $arr = explode(',', $explain);
        $data['versionName'] = $version;
        $data['versionForce'] = $force;
        $data['versionNote'] = $explain;
        $data['downloadUrl'] = $url;
        $data['last_get'] = time();
        $data['clear_cache'] = 1; //1清楚webview缓存
        $data['domain'] = [ // 测试环境，默认IP 正式才配置
//'http://test.cqfll.com'
        ];
        foreach ($arr as $key => $value) {
            $data['mobile_apk_explain'][$key]['text'] = $value;
        }
        mobileAjaxReturn($data,'请传入平台类型',SUCCESS);
    }

    //安卓版本更新
    public function androidversion() {
        $version = "1.1.0";
        $type = input('type');
        if ($type == 1) {
            $url = Version_Android_DowUrl_New;
            $v =Version_Android_New;
            $explain = Version_Android_Log_New;
            $force = Version_Android_New;
        } else {
            $url = Version_Android_DowUrl;
            $v =Version_Android;
            $explain = Version_Android_Log;
            $force = Version_Android;
        }

        /*
        $force = "是";
        if ($force == '是') {
            $force = 1;
        } else {
            $force = 2;
        }
        */

        $explain = str_replace("，", ",", $explain);
        $arr = explode(',', $explain);
        $data['versionName'] = $version;
        $data['versionForce'] = $force;
        $data['versionNote'] = $v;
        $data['downloadUrl'] = $url;
        $data['last_get'] = time();
        $data['clear_cache'] = 1; //1清楚webview缓存
        $data['domain'] = [ // 测试环境，默认IP 正式才配置

        ];
        foreach ($arr as $key => $value) {
            $data['mobile_apk_explain'][$key]['text'] = $value;
        }
        mobileAjaxReturn($data,'请传入平台类型',SUCCESS);
    }
}

