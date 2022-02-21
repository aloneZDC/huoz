<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/11 0011
 * Time: 16:59
 */

namespace app\api\controller;

use app\common\model\Reads;
use think\Exception;
use think\Page, think\Db;
use think\Request;

class  News extends Base
{
    protected $public_action = ['newsList', 'newsDetails', 'aboutus', 'ad', 'ad_list','help','article_list', 'reads', 'read']; //无需登录即可访问

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * @Desc:新闻列表
     * @return array
     * @Date: 2018/12/11 0011 17:02
     * @author: Administrator
     */
    public function newsList()
    {
        $lang = $this->getLang();
        if (empty($lang)) {
            $field = 'article_id,title,content,art_pic,add_time';
        } else {
            $field = 'article_id,' . $lang . '_title as title,' . $lang . '_content as content,art_pic,add_time';
        }
        $articleModel = db('Article');
        $numPage = input('rows', 10);
        $page = input('page', 1);
        $startPage = ($page - 1) * $numPage;
        $zixun = $articleModel->field($field)->where('position_id=129')->order('add_time desc')->limit($startPage, $numPage)->select();
        foreach ($zixun as $key => $value) {
            $lenth = strlen($value['title']);
            if ($lenth >= 15) {
                $value['title'] = strip_tags(html_entity_decode($value['title']));
            } else {
                $value['title'] = trim(strip_tags(html_entity_decode($value['title'])));
            }
            $lenth = strlen($value['content']);
            if ($lenth >= 30) {
                $value['content'] = strip_tags(html_entity_decode($value['content']));
            } else {
                $value['content'] = trim(strip_tags(html_entity_decode($value['content'])));
            }
            $value['add_time'] = date('m/d H:i', $value['add_time']);
            $zixun[$key] = $value;
        }
        self::output(10000, '請求成功', $zixun);
    }

    /**
     * 广告@标
     */
    public function ad()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_not_data");
        $r['result'] = [];
        $lang = $this->getLang();
        if (empty($lang)) {
            $field = 'article_id,title,content,art_pic,add_time';
        } else {
            $field = 'article_id,' . $lang . '_title as title';
        }
        $type = input('post.type', 1, 'intval');
        if (!in_array($type, [1, 2])) {
            $this->output_new($r);
        }
        if ($type == 1) {
            $type = 12;
        } elseif ($type == 2) {
            $type = 181;
        }
        $article = Db::name('article')->field($field)->where(['position_id' => $type])->order('add_time desc')->find();
        if ($article) {
            $lenth = strlen($article['title']);
            if ($lenth >= 15) {
                $article['title'] = strip_tags(html_entity_decode($article['title']));
            } else {
                $article['title'] = trim(strip_tags(html_entity_decode($article['title'])));
            }
            $r['result'] = $article;
            $r['message'] = lang("lan_data_success");
            $r['code'] = SUCCESS;
        }

        $this->output_new($r);
    }

    /**
     * 广告列表@标
     */
    public function ad_list()
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_not_data");
        $r['result'] = [];
        $lang = $this->getLang();
        $page = input('post.page', 1, 'intval');
        $page_size = input('post.page_size', 10, 'intval');
        if (empty($lang)) {
            $field = 'article_id,title,content,art_pic,add_time';
        } else {
            $field = 'article_id,' . $lang . '_title as title,add_time';
        }
        $type = input('post.type', 1, 'intval');
        if (!in_array($type, [1, 2,4])) {
            $this->output_new($r);
        }
        if ($type == 1) {
            $type = 12;
        } elseif ($type == 2) {
            $type = 181;
        }
        $article = Db::name('article')->field($field)->where(['position_id' => $type])->order('add_time desc')->limit(($page - 1) * $page_size, $page_size)->select();
        if ($article) {
            foreach ($article as $key => $val) {
                $lenth = strlen($val['title']);
                if ($lenth >= 15) {
                    $article[$key]['title'] = strip_tags(html_entity_decode($val['title']));
                } else {
                    $article[$key]['title'] = trim(strip_tags(html_entity_decode($val['title'])));
                }
                $article[$key]['add_time'] = empty($val['add_time']) ? "" : date('Y-m-d H:i:s', $val['add_time']);
            }
            $r['result'] = $article;
            $r['message'] = lang("lan_data_success");
            $r['code'] = SUCCESS;
        }
        $this->output_new($r);
    }

    /**
     * @Desc:详情
     * @return array
     * @Date: 2018/12/11 0011 17:52
     * @author: Administrator
     */
    public function newsDetails()
    {
        $article_id = input("article_id", '', 'intval');
        $position_id = input("position_id", '', 'intval');
        if (!empty($article_id)) {
            $w['article_id'] = $article_id;
        } else {
            $w['position_id'] = $position_id;
        }
        $zixun = [];
        if (!empty($w)) {
            $lang = $this->getLang();
            $articleModel = db('Article');
            if (empty($lang)) {
                $field = 'c.article_id,c.title,c.content,c.art_pic,c.add_time,t.name as title_name';
            } else {
                $field = 'c.article_id,c.' . $lang . '_title as title,c.' . $lang . '_content as content,c.art_pic,c.add_time,t.name_' . $lang . ' as title_name';
            }
            $zixun = $articleModel->field($field)->alias('c')->join("article_category t ", "   c.position_id=t.id ", 'left')->where($w)->order('add_time desc')->find();
            @$zixun['content'] = html_entity_decode($zixun['content']);
            @$zixun['add_time'] = date('Y/m/d H:i', $zixun['add_time']);
        }
        self::output(10000, '請求成功', $zixun ?: []);
    }

    /**
     * @Desc:联系我们
     * @return array
     * @Date: 2018/12/13 0013 15:53
     * @author: Administrator
     */
    public function aboutus()
    {
        self::output(10000, "請求成功", ['contact' => [
            'email' => $this->config['email'],
            'qq1' => $this->config['qq1'],
            'qq2' => $this->config['qq2'],
            'weixin1' => $this->config['weixin_kf1'],
            'weixin2' => $this->config['weixin_kf2'],
        ]]);
    }

    /**
     * 获取帮助中心的二级分类
     * @return \json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Create by: Red
     * Date: 2019/9/5 14:15
     */
    function help()
    {
        $find = Db::name("article_category")->where(['keywords' => 'help'])->find();
        $list = null;
        if (!empty($find)) {
            //帮助中心二级分类
            $field="id,name_tc,name_en";
            $list = Db::name("article_category")->field($field)->where(['parent_id' => $find['id']])->order("sort asc")->select();
        }
        if (!empty($list)) {
            $lang=input('language');
            foreach ($list as &$value){
                if($lang=="en-us"){
                    $value['name_tc']=$value['name_en'];
                }
                unset($value['name_en']);
            }
            $r['code'] = SUCCESS;
            $r['message'] = lang("lan_data_success");
            $r['result'] = $list;
        } else {
            $r['code'] = ERROR1;
            $r['message'] = lang("lan_not_data");
            $r['result'] = [];
        }
        return ajaxReturn($r);
    }

    /**
     * 根据分类id获取其下的文章
     * @return \json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Create by: Red
     * Date: 2019/9/6 14:25
     */
    function article_list(){
        $id=input("post.id");
        $page=input("post.page");
        $r['code'] = ERROR1;
        $r['message'] = lang("lan_modifymember_parameter_error");
        $r['result'] = [];
        if(is_numeric($id)){
            $field="article_id,tc_title,en_title,art_pic,add_time";
            $list=Db::name("article")->field($field)->where(['position_id'=>$id])->order("sort asc")->page($page,20)->select();//当前分类下的文章
            if(!empty($list)){
                $lang=input('language');
                foreach ($list as &$value){
                    if($lang=="en-us"){
                        $value['tc_title']=$value['en_title'];
                    }
                    $value['add_time']=date("Y-m-d H:i:s",$value['add_time']);
                    unset($value['en_title']);
                }
                $r['code']=SUCCESS;
                $r['message']=lang("lan_data_success");
                $r['result']=$list;
            }else{
                $r['message']=lang("lan_not_data");
            }
        }
        return ajaxReturn($r);
    }

    public function reads(Request $request)
    {

        $type = $request->post('type', 1, 'intval');
        $hot = $request->post('hot', 0, 'intval');
        $page = $request->post('page', 1, 'intval');
        $rows = $request->post('rows', 10, 'intval');
        $language = $request->post('language', 'zh-cn');

        if (!in_array($type, [Reads::TYPE_NEWS, Reads::TYPE_FANS])) {
            return ajaxReturn([
                'code' => ERROR1,
                'message' => lang('lan_not_data'),
                'result' => null
            ]);
        }

        if($hot) $hot = 1;
        $data = Reads::getReads($type,$hot, $page, $rows, $language);

        if (count($data) < 1) {
            return ajaxReturn([
                'code' => ERROR1,
                'message' => lang('lan_not_data'),
                'result' => null
            ]);
        }

        foreach ($data as &$item) {
            $item['avatar'] = Reads::READ_AVATAR;
//            $item['name'] = Reads::READ_NAME;
            $item['name'] = lang('lan_read_name');
            $item['url'] = url('mobile/News/lists',['id'=>$item['article_id']]);
        }

        return ajaxReturn([
            'code' => SUCCESS,
            'message' => lang('lan_data_success'),
            'result' => [
                'list' => $data
            ]
        ]);
    }

    public function read(Request $request)
    {
        $id = $request->post('id', 0, 'intval');
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_not_data'),
            'result' => null
        ];
        if (empty($id)) {
            return ajaxReturn($r);
        }

        $data = Reads::read($id);
        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_data_success');
        $r['result'] = $data;
        return ajaxReturn($r);
    }
}
