<?php

namespace app\api\controller;

class FeelMining extends Base
{
    // 获取体验矿机
    public function getList()
    {
        $res = \app\common\model\FeelMining::getList($this->member_id);
        return $this->output_new($res);
    }

    // 支持发行币种
    public function supportCurrency()
    {
        $res = \app\common\model\FeelMining::supportCurrency();
        return $this->output_new($res);
    }

    // 修改开采币种
    public function mineCurrency()
    {
        $feel_id = input('feel_id', 0);
        $real_currency_id = input('real_currency_id', 0);
        if (empty($feel_id) || empty($real_currency_id)) {
            $this->output(ERROR1, lang('parameter_error'));
        }

        $res = \app\common\model\FeelMining::mineCurrency($real_currency_id, $feel_id);
        return $this->output_new($res);
    }

    // 签到开采
    public function release()
    {
        $feel_id = input('feel_id', 0);
        if (empty($feel_id)) {
            $this->output(ERROR1, lang('parameter_error'));
        }
        $res = \app\common\model\FeelMining::release($this->member_id, $feel_id);
        return $this->output_new($res);
    }

}