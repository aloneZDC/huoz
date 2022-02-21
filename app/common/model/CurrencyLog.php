<?php
/**
 * Created by PhpStorm.
 * User: tt
 * Date: 2018/12/14
 * Time: 11:43
 */

namespace app\common\model;


use think\Db;
use think\Exception;

class CurrencyLog extends Base
{
    protected $resultSetType = 'collection';

    /**
     * @param string $tx
     * @param string $tag
     * @return array
     */
    public static function modifyTag($tx, $tag)
    {
        $r = [
            'code' => ERROR1,
            'message' => 'no message',
            'result' => null
        ];
        try {
            Db::startTrans();

            $member = Member::where('member_id', $tag)->field('member_id')->find();
            if (empty($member)) {
                throw new Exception("Tag不存在系统内请检查Tag是否正确");
            }
            $log = self::where('tx', $tx)->find();
            if (empty($log)) {
                throw new Exception("tx不存在");
            }
            // 2失败 4处理(地址不存在系统内)
            if (!in_array($log['status'], [2, 4])) {
                throw new Exception("该数据不可更改");
            }

            $ato = explode("_", $log['ato']);
            $newAto = $ato[0] . "_" . $tag;

            $trans = json_decode($log['trans'], true);
//            if (!isset($trans['des_tag'])) {
//                throw new Exception("json元数据des_tag不存在!");
//            }
            $trans['des_tag'] = $tag;

            $log['ato'] = $newAto;
            $log['trans'] = json_encode($trans);
            $log['check_status'] = 2;
            $log['is_modify'] = 2;
            $log['status'] = 0;
            if (!$log->save()){
                throw new Exception('系统错误');
            }
            $r['code'] = SUCCESS;
            $r['message'] = "修改成功";
            Db::commit();
            return $r;
        } catch (Exception $exception) {
            Db::rollback();
            $r['message'] = $exception->getMessage();
            return $r;
        }
    }

    /**
     * 充值错误处理
     * @param $tx
     * @return array
     */
    public static function chargeError($tx) {
        $r = [
            'code' => ERROR1,
            'message' => 'no message',
            'result' => null
        ];
        try {
            Db::startTrans();

            $log = self::where('tx', $tx)->find();
            if (empty($log)) {
                throw new Exception("tx不存在");
            }
            // 2失败 4处理(地址不存在系统内)
            if (!in_array($log['status'], [2, 4])) {
                throw new Exception("该数据不可更改");
            }

            $log['check_status'] = 2;
            $log['is_modify'] = 2;
            $log['status'] = 0;
            $log['update_time'] = time();
            if (!$log->save()){
                throw new Exception('系统错误');
            }
            $r['code'] = SUCCESS;
            $r['message'] = "重置成功";
            Db::commit();
            return $r;
        } catch (Exception $exception) {
            Db::rollback();
            $r['message'] = $exception->getMessage();
            return $r;
        }
    }
}