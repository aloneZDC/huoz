<?php
//传统矿机
namespace app\h5\controller;

class CommonMining extends Base
{
    //我的团队
    public function my_team()
    {
        $page = intval(input('page'));
        $res = \app\common\model\CommonMiningMember::myTeam($this->member_id, $page);
        return $this->output_new($res);
    }

    /**
     * 订单合同
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function order_contract()
    {
        $order_type = input('order_type', 0, 'intval');
        $order_id = input('order_id', 0, 'intval');
//        $res = \app\common\model\CommonMiningPay::order_contract($this->member_id, $order_type, $order_id);
        $res = \app\common\model\MemberContract::order_contract($this->member_id);
        $this->output_new($res);
    }

    // 提交合同签名
    public function submit_autograph()
    {
        $order_type = input('order_type', 0, 'intval');
        $order_id = input('order_id', 0, 'intval');
        $autograph = input('autograph', '', 'strval');
//        $res = \app\common\model\CommonMiningPay::submit_autograph($this->member_id, $order_type, $order_id, $autograph);
        $res = \app\common\model\MemberContract::submit_autograph($this->member_id, $autograph);
        $this->output_new($res);
    }
}