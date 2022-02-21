<?php
//跳跃排名倒序加权算法
namespace app\api\controller;

use app\common\model\Currency;
use app\common\model\JumpRankingMemberSummary;
use think\Db;

class JumpRanking extends Base
{
    public function info() {
        $currency_id = Currency::PUBLIC_CHAIN_ID;
        $res = JumpRankingMemberSummary::getOne($this->member_id,$currency_id);
        return $this->output_new($res);
    }
}
