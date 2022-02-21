<?php
//投票 俱乐部
namespace app\api\controller;
use think\Db;

class Flash extends Base
{
    protected $public_action = ['find'];
    public function find() {
        $where = [];
        $type = intval(input('type'));
        if(!$type) $type = -1;

        $where['type'] = $type;
        $where['lang'] = $this->lang;
        $flash = Db::name('Flash')->field('flash_id,pic,jump_url')->order('sort asc')->where($where)->limit(8)->select();

        $this->output_new([
            'code' => SUCCESS,
            'message' => '',
            'result' => [
                'flash' => $flash ?: [],
            ],
        ]);
    }
}
