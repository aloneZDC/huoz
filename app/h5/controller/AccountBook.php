<?php

namespace app\h5\controller;

/**
 * 账本记录
 * Class AccountBook
 * @package app\h5\controller
 */
class AccountBook extends Base
{
    // 账本记录
    public function index()
    {
        $currency_id = input('currency_id', 0, 'intval');
        $type = input('type', 0, 'intval');
        $page = input("post.page", 1, 'intval');
        $rows = input('post.rows', 10, 'intval');
        $real_type = input('real_type', '');
        $where = [];
        switch ($real_type) {
            case 'chong':
                $where['type'] = 5;
                break;
            case 'ti':
                $where['type'] = 6;
                break;
            case 'ct':
                $where['type'] = ['in', [5, 6]];
                break;
        }

        $count = false;
        $list = model('AccountBook')->getLog($this->member_id, $currency_id, $type, $page, $rows, $this->lang, $count, $where);
        if(empty($list)) {
            $this->output(ERROR1, lang('not_data'));
        }
        $this->output(SUCCESS, lang('lan_operation_success'), $list);
    }
}