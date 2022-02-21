<?php

namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;
use think\Db;

/**
 * 奇亚矿机操作日志表
 * Class ChiaMiningLog
 * @package app\common\model
 */
class ChiaMiningLog extends Model
{
	/**
     * 添加操作日志
     * @param string $operation 操作
     * @param string $operation_detail 操作详情
     * @param int $third_id 表ID
     * @param string $third_table_name 表名
     * @return int
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
	static function addLog($operation, $operation_detail, $third_id, $third_table_name){
		$data = [];
		$data['operation'] = $operation;
		$data['operation_detail'] = $operation_detail;
		$data['third_id'] = $third_id;
		$data['third_table_name'] = $third_table_name;
		return self::insertGetId($data);
	}
}