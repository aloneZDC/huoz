<?php
//跳跃排名倒序加权算法 - 订单
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class JumpRankingCurrencyUser extends Model
{
    static function getTableName($today_config) {
        return config('database.prefix').'jump_ranking_currency_user'.date('Ymd',$today_config['today_start']);
    }

    //重新加载最新数据
    static function load_today($currency_configs,$today_config) {
        try {
            $table_name = self::getTableName($today_config);
            $db_prefix =  config("database.prefix");

            $member_count = Member::count();
            //加载新数据
            foreach ($currency_configs as $currency) {
                Db::execute("insert into {$table_name}(cu_id,member_id,currency_id,num,child_num) select cu_id,member_id,currency_id,num,num from {$db_prefix}currency_user where currency_id=".$currency['currency_id']);
            }

            //补齐没有资产的用户
            foreach ($currency_configs as $currency) {
                Db::execute("insert into {$table_name}(member_id,currency_id,num,child_num) 
                select member_id,{$currency['currency_id']},0,0 from {$db_prefix}member where member_id not in(
                select member_id from {$table_name} where currency_id={$currency['currency_id']}
        )");
            }

            //去除不合格的下级数量
            Db::execute("update {$table_name} set child_num=0 where child_num<".$currency['raning_min_mum']);

            $count = Db::table($table_name)->count();
            if($count>=$member_count*count($currency_configs)) return true;

            return false;
        } catch (Exception $e) {
            Log::error("JumpRankingCurrencyUser:load_today".$e->getMessage());
        }
        return false;
    }

    //设置排名
    static function setRanking($id,$num,$currency_config,$today_config) {
        $table_name = self::getTableName($today_config);
        $count = Db::table($table_name)->where('currency_id='.$currency_config['currency_id'].' and num>='.$currency_config['raning_min_mum'].' and num<'.$num)->count();
        $ranking = $count+1;
        return Db::table($table_name)->where(['cu_id'=>$id])->setField('ranking',$ranking);
    }

    //增加团队业绩
    static function addParentTeam($member_id,$num,$currency_config,$today_config) {
        $table_name = self::getTableName($today_config);
        return self::execute('update '.$table_name.' a,'.config("database.prefix").'member_bind b 
            set a.child_num=a.child_num+'.$num.',a.total_child=a.total_child+1 where a.member_id = b.member_id and a.currency_id='.$currency_config['currency_id'].' and  b.child_id='.$member_id.';');
    }


    //设置算力
    static function setPower($member_id,$currency_config,$today_config) {
        //无算力
        $max_power = self::getMaxPower($member_id,$currency_config,$today_config);
        if($max_power<=0) return true;

        $max_power_num = intval(pow($max_power, 1 / 3)); //开立方根
        $low_power_total = self::getLowPower($member_id,$currency_config,$max_power,$today_config);
        $max_power_total = 0;
        if($max_power>$currency_config['lower_num']) {
            $max_power_total = self::getHignPower($member_id,$currency_config,$max_power,$today_config);
        }

        $total = $max_power_num + $low_power_total + $max_power_total;
        $table_name = self::getTableName($today_config);
        return Db::table($table_name)->where(['member_id'=>$member_id,'currency_id'=>$currency_config['currency_id']])->setField('power',$total);
    }

    //获取下级中最大的算力区
    static function getMaxPower($member_id,$currency_config,$today_config) {
        //获取直推中的最大区
        $child_max_num = self::query('select max(child_num) as child_num  from '.self::getTableName($today_config).' where member_id in(
            select child_id from '.config("database.prefix").'member_bind where member_id='.$member_id.' and level=1
        ) and currency_id='.$currency_config['currency_id']);
        if($child_max_num && isset($child_max_num[0])) {
            $child_max_num = intval($child_max_num[0]['child_num']);
        } else {
            $child_max_num = 0;
        }
        return $child_max_num;
    }

    //获取小于等于1W 区域的总和
    static function getLowPower($member_id,$currency_config,$max_power,$today_config) {
        //小于等于10000的 直接*10
        $low_num = self::query('select sum(child_num) as sum from '.self::getTableName($today_config).' where member_id in(
            select child_id from '.config("database.prefix").'member_bind where member_id='.$member_id.' and level=1
        ) and currency_id='.$currency_config['currency_id'].' and child_num<='.$currency_config['lower_num']);
        if(!empty($low_num) && isset($low_num[0])) {
            $total_lower_num = intval($low_num[0]['sum']) * $currency_config['multiple'];
        } else {
            $total_lower_num = 0;
        }
        //如果最大区
        if($max_power<=$currency_config['lower_num']) {
            $total_lower_num -= $max_power * $currency_config['multiple'];
        }
        Log::write($member_id." child_low".$total_lower_num."\r\n");
        return $total_lower_num;
    }

    //获取大于1W 区域的总和
    static function getHignPower($member_id,$currency_config,$max_power,$today_config) {
        //大于10000的
        $hign_num = self::query('select sum(child_num-'.$currency_config['lower_num'].') as sum,count(*) as count from '.self::getTableName($today_config).' where member_id in(
            select child_id from '.config("database.prefix").'member_bind where member_id='.$member_id.' and level=1
        ) and currency_id='.$currency_config['currency_id'].' and child_num>'.$currency_config['lower_num']);
        if(!empty($hign_num) && isset($hign_num[0])) {
            $total_hign_num = intval($hign_num[0]['sum']) + $hign_num[0]['count'] * $currency_config['lower_num'] * $currency_config['multiple'];
        } else {
            $total_hign_num = 0;
        }

        if($max_power>$currency_config['lower_num']) {
            $total_hign_num = $total_hign_num - $currency_config['lower_num'] * $currency_config['multiple'] - ($max_power-$currency_config['lower_num']);
        }

        Log::write($member_id."  child_hign".$total_hign_num."\r\n");
        return $total_hign_num;
    }

    static function create_table($today_config) {
        try{
            $table_name = self::getTableName($today_config);
            //IF NOT EXISTS
            $flag = Db::execute("CREATE TABLE `{$table_name}` (
  `cu_id` int(32) NOT NULL AUTO_INCREMENT,
  `member_id` int(32) NOT NULL DEFAULT '0' COMMENT '用户id',
  `currency_id` int(32) NOT NULL DEFAULT '0' COMMENT '货币id',
  `num` decimal(20,6) unsigned NOT NULL DEFAULT '0.000000' COMMENT '数量',
  `child_num` decimal(20,6) unsigned NOT NULL DEFAULT '0.000000' COMMENT '下级资产数量 + 本人数量',
  `total_child` int(11) NOT NULL DEFAULT '0' COMMENT '总团队数量',
  `ranking` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '排名',
  `power` decimal(20,6) NOT NULL DEFAULT '0.000000' COMMENT '算力',
  PRIMARY KEY (`cu_id`) USING BTREE,
  UNIQUE KEY `member_id_2` (`member_id`,`currency_id`) USING BTREE,
  KEY `currency_id` (`currency_id`),
  KEY `currency_num` (`currency_id`,`num`),
  KEY `currency_child_num` (`currency_id`,`child_num`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='奇点模式-用户币资产临时备份表'
");
            if($flag===false) return false;

            return true;
        }catch(Exception $e){
            echo $e->getMessage();
            return false;
        }
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
