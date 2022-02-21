<?php
namespace app\h5\controller;

class Areas extends Base
{
    protected $is_decrypt = false; //不验证签名
    protected $public_action = ['index'];
    public function index() {
        $type = intval(input('type')); //0:country 1:province 2:city  3:district
        $parent_id = intval(input('parent_id'));
        $list = \app\common\model\Areas::get_list($type,$parent_id);

        $r['code']= SUCCESS;
        $r['message']=lang("data_success");
        $r['result']= $list;
        $this->output_new($r);
    }
}
