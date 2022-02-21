<?php

namespace app\admin\controller;

use app\common\model\BfwCurrencyConfig;
use app\common\model\BfwCurrencyTransfer;
use app\common\model\Currency;
use think\Request;

class BfWallet extends Admin
{

    /**
     * 划转、提现配置
     * @param Request $request
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function config(Request $request)
    {
        $list = BfwCurrencyConfig::with(['currency', 'tocurrency', 'currencytype'])->order('cc_id', 'desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        foreach ($list as &$val) {
            $val['type_currency_name'] = $val['currencytype']['currency_name'];
            $val['dh_currency_name'] = $val['currency']['currency_name'] . ' - ' . lang('bfw_' . $val['currency']['account_type']);
            $val['to_currency_name'] = $val['tocurrency']['currency_name'] . ' - ' . lang('bfw_' . $val['tocurrency']['account_type']);

            $val['cc_type'] = lang($val['cc_type']);
            $val['status'] = $val['status'] ? '关闭' : '开启';
        }
        return $this->fetch(null, ['list' => $list, 'page' => $page]);
    }

    /**
     * 创建、修改配置
     * @param Request $request
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function config_create(Request $request)
    {
        if ($request->isPost()) {
            $currencyId = $request->post('currency_id');
            $toCurrencyId = $request->post('to_currency_id');
            $cc_type = $request->post('cc_type');
            $currency_type = $request->post('cu_type');

            if (!$currencyId or !$toCurrencyId or !$cc_type or !$currency_type) {
                $this->error("参数错误!");
                die();
            }

            if ($currencyId == $toCurrencyId) {
                $this->error('币种和兑换币种冲突!');
                die();
            }

            if (!empty($request->post('cc_id'))) {
                $r = BfwCurrencyConfig::where('cc_id', $request->post('cc_id'))->update($request->post());
            } else {
                $isRepeat = BfwCurrencyConfig::where([
                    'cu_type' => $currency_type,
                    'currency_id' => $currencyId, 'to_currency_id' => $toCurrencyId, 'cc_type' => $cc_type])->find();
                if ($isRepeat) {
                    $this->error('该币种已经有一个兑换配置, 请勿重复添加!');
                    die();
                }

                $r = BfwCurrencyConfig::create([
                    'cu_type' => $currency_type,
                    'currency_id' => $currencyId,
                    'to_currency_id' => $toCurrencyId,
                    'cc_type' => $cc_type,
                    'min' => $request->post('min', 0),
                    'max' => $request->post('max', 0),
                    'fee' => $request->post('fee', 0),
                    'abt_id' => $request->post('abt_id', 0),
                    'status' => $request->post('status', 0),
                ]);
            }
            if (empty($r)) {
                $this->error("系统错误, 请稍后再试!");
                die();
            }
            $this->success('提交成功!', url('config'));
        }

        $cc_id = input("cc_id");
        $list = !empty($cc_id) ?
            BfwCurrencyConfig::where('cc_id', $cc_id)->find()
            : null;
        $this->assign('list', $list);

        // 币种
        $currency = Currency::where(['is_line' => 1, 'account_type' => 'wallet'])->select();
        $this->assign('currency', $currency);

        $currency_list = Currency::where('is_line', 1)->select();
        foreach ($currency_list as &$val) {
            $val['currency_mark_name'] = $val['currency_name'] . ' - ' . lang('bfw_' . $val['account_type']);
        }
        return $this->fetch(null, ['currency_list' => $currency_list]);
    }

    /**
     * 配置删除
     * @param Request $request
     */
    public function config_delete(Request $request)
    {
        $cc_id = $request->param('cc_id');
        if (!$cc_id) {
            $this->error('参数错误');
            die();
        }

        $r = BfwCurrencyConfig::where('cc_id', $cc_id)->delete();
        if (!$r) {
            $this->error('系统错误请稍后再试');
            die();
        }
        $this->success('删除成功!');
    }

    /**
     * 兑换记录
     * @param Request $request
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function transfer(Request $request)
    {
        $where = null;
        if ($member_id = $request->get('member_id', 0)) {
            $where['member_id'] = $member_id;
        }
        if ($currency_id = $request->get('currency_id', 0)) {
            $where['currency_id'] = $currency_id;
        }
        if ($to_currency_id = $request->get('to_currency_id', 0)) {
            $where['to_currency_id'] = $to_currency_id;
        }
        if ($ct_type = $request->get('ct_type', '')) {
            $where['ct_type'] = $ct_type;
        }

        $list = BfwCurrencyTransfer::with(['currency', 'tocurrency', 'member'])->where($where)->order('add_time', 'desc')->paginate(null, false, ['query' => $request->get()]);
        $page = $list->render();
        foreach ($list as &$val) {
            $val['dh_currency_name'] = $val['currency']['currency_name'] . ' - ' .
                lang('bfw_' . $val['currency']['account_type'])
                . '(' . $val['currency_id'] . ')';
            $val['to_currency_name'] = $val['tocurrency']['currency_name'] . ' - ' .
                lang('bfw_' . $val['tocurrency']['account_type'])
                . '(' . $val['to_currency_id'] . ')';

            $val['ct_type'] = lang($val['ct_type']);
            $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
        }

        $currency_list = Currency::where('is_line', 1)->select();
        foreach ($currency_list as &$value) {
            $value['account_name'] = $value['currency_name'] . ' - ' .
                lang('bfw_' . $value['account_type']);
        }

        $cc_type = ['bfw_qb_hz' => lang('bfw_qb_hz'), 'bfw_zh_qb' => lang('bfw_zh_qb')];
        return $this->fetch(null, ['list' => $list, 'page' => $page,
            'ct_type' => $cc_type, 'currency_list' => $currency_list
        ]);
    }


}