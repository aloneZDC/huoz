<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
use think\Exception;
use think\Db;

class Finance extends Base {
    /**
     * 添加财务日志方法
     * @param unknown $member_id
     * @param unknown $type
     * @param unknown $content
     * @param unknown $money
     * @param unknown $money_type 收入=1/支出=2
     * @param unknown $currency_id 积分类型id 0是rmb
     * @return
     */
    public function addLog($member_id, $type, $content, $money, $money_type, $currency_id, $trade_id=0)
    {
        try{
            $data = [
                'member_id' => $member_id,
                'trade_id' => $trade_id,
                'type' => $type,
                'content' => $content,
                'money_type' => $money_type,
                'money' => $money,
                'add_time' => time(),
                'currency_id' => $currency_id,
                'ip' => get_client_ip_extend(),
            ];
            $log_id = Db::name('finance')->insertGetId($data);
            if(!$log_id) return false;

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
