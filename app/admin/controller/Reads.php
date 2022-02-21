<?php


namespace app\admin\controller;


use app\common\model\Reads as ReadsModel;
use think\Request;

class Reads extends Admin
{
    protected $reads = null;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->reads = new ReadsModel();

    }

    public function index(Request $request)
    {
        $where = [];
        $type = $request->get('type', null, 'intval');
        $keywords = $request->get('keywords', null);
        if ($type) {
            $where['type'] = $type;
        }

        if ($keywords) {
            $where['title'] = ['like', "%{$keywords}%"];
        }

        $list = $this->reads->where($where)->order('sort', 'desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        $count = $list->total();
        foreach ($list as &$item) {
            // $item['add_time'] = date("Y-m-d H:i", $item['add_time']);
            $item['art_pic'] = json_decode($item['art_pic']);
            $item['content'] = mb_substr(strip_tags(htmlspecialchars_decode($item['content'])), 0, 50) . "...";
        }
        return $this->fetch(null, compact('list', 'page', 'count'));
    }

    public function edit(Request $request)
    {
        if ($request->isPost()) {
            $data = $request->post();
            if (!empty($_FILES['art_pic']) and $_FILES['art_pic']['size'][0] > 0) {
                $artPic = ['art_pic' => $_FILES['art_pic']];
                unset($_FILES['art_pic']);
                $up = $this->multiple_upload($artPic, 'reads');
                if (empty($up)) {
                    $this->error('图片上传失败');
                    return false;
                }
                $data['art_pic'] = json_encode($up);
            }
            $flag = $this->reads->update($data);
            if (empty($flag)) {
                $this->error("系统错误，修改失败");
            }
            $this->success("修改成功");
        }

        $id = $request->param('id');
        $data = $this->reads->where('article_id', $id)->find();

        return $this->fetch(null, ['data' => $data]);
    }


    public function add(Request $request)
    {
        if ($request->isPost()) {
            $type = intval($request->post('type'));
            $title = $request->post('title');
            $en_title = $request->post('en_title');
            $content = $request->post('content');
            $en_content = $request->post('en_content');
            $from_name = $request->post('from_name');
            $from_name_en = $request->post('from_name_en');
            $is_hot = intval($request->post('is_hot'));

            if (empty($_FILES['art_pic']) and $_FILES['art_pic']['size'][0] > 0 ) {
                $artPic = ['art_pic' => $_FILES['art_pic']];
                unset($_FILES['banners']);
                $up = $this->multiple_upload($artPic, 'reads');
                if (empty($up)) {
                    $this->error('图片上传失败');
                    return false;
                }
                $art_pic = json_encode($up);
            } else {
                $art_pic = json_encode([]);
            }

            $data = [
                'title' => $title,
                'en_title' => $en_title,
                'from_name' => $from_name,
                'from_name_en' => $from_name_en,
                'type' => $type,
                'content' => $content,
                'en_content' => $en_content,
                'art_pic' => $art_pic,
                'is_hot' => $is_hot,
                'add_time' => time()
            ];
            $id = $this->reads->insertGetId($data);
            if (empty($id)) {
                $this->error("系统错误添加失败");
                return false;
            }
            $this->success("添加成功!");
        }

        return $this->fetch();
    }


    public function delete(Request $request)
    {
        $id = intval(input('id'));
        $res = $this->reads->where('article_id', $id)->delete();
        if ($res == false) {
            $this->error("系统错误, 删除失败!");
            return false;
        }
        $this->success("删除成功!");
        return true;
    }
}
