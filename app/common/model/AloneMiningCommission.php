<?php


namespace app\common\model;

use think\Exception;
use think\Model;

class AloneMiningCommission extends Model
{
    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }

    public function cusers() {
        return $this->belongsTo('app\\common\\model\\Member', 'child_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }

    /**
     * 新增/编辑记录
     * @param int $data 数据
     * @return int
     * */
    static function addItem($data) {
        try {
            self::startTrans();

            if (!empty($data['id'])) {
                $item_id = $data['id'];
                unset($data['id']);
                $flag = self::where(['id' => $item_id])->update($data);
                if ($flag === false) throw new Exception(lang('operation_failed_try_again'));
            } else {
                $check = self::where(['member_id' => $data['member_id'], 'child_id' => $data['child_id']])->find();
                if (!empty($check)) {
                    throw new Exception(lang('operation_failed_try_again'));
                }
                $data['add_time'] = time();
                //插入订单
                $item_id = self::insertGetId($data);
                if (!$item_id) throw new Exception(lang('operation_failed_try_again'));
            }
            self::commit();

        } catch (Exception $e) {
            self::rollback();
            return false;
        }
        return $item_id;
    }
}