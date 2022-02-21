<?php
/**
 * 红包项目 - 节点奖励详情
**/
namespace app\common\model;


use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class HongbaoNodeAwardDetail extends Model
{
    static function truncate() {
        //清空奖励详情表
        self::execute('TRUNCATE table '.config("database.prefix").'hongbao_node_award_detail;');
        $count = HongbaoNodeAwardDetail::count();
        if($count>0) {
            Log::write('用户资产临时表清除失败');
            return false;
        }

        return true;
    }

    static function create_table($table) {
        try{
            $table_name = config('database.prefix').$table;
            $flag = Db::execute("CREATE TABLE IF NOT EXISTS `".$table_name."` (
`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
`user_id` INT(11) NOT NULL DEFAULT '0' COMMENT '用户ID',
`currency_id` INT(11) NOT NULL DEFAULT '0' COMMENT '币种',
`num` DECIMAL(18,6) UNSIGNED NOT NULL DEFAULT '0.000000' COMMENT '奖励数量',
`base_num` DECIMAL(18,6) UNSIGNED NOT NULL DEFAULT '0.000000' COMMENT '奖励基数',
`create_time` INT(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
`third_user_id` INT(11) NOT NULL DEFAULT '0' COMMENT '第三方ID',
PRIMARY KEY (`id`) USING BTREE,
INDEX `user_id` (`user_id`, `num`)
)
COMMENT='红包项目-节点奖-详情'
COLLATE='utf8_general_ci'
ENGINE=InnoDB
ROW_FORMAT=COMPACT
AUTO_INCREMENT=1
;
");
            if($flag===false) return false;

            Db::execute('insert into '.$table_name.' select * from '.config("database.prefix").'hongbao_node_award_detail');

            return true;
        }catch(Exception $e){
            echo $e->getMessage();
            return false;
        }
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
