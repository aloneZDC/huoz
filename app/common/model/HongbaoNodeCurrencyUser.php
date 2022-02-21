<?php
/**
 * 红包项目 - 用户资产临时表
 **/
namespace app\common\model;


use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class HongbaoNodeCurrencyUser extends Model
{
    //重新加载最新数据
    static function reload_today($currency) {
        //删除旧数据
        self::execute('TRUNCATE table '.config("database.prefix").'hongbao_node_currency_user;');
        //检测
        $count = self::count();
        if($count>0) {
            Log::write('用户资产临时表清除失败');
            return false;
        }

        //加载新数据
        $db_prefix =  config("database.prefix");
        Db::execute("insert into {$db_prefix}hongbao_node_currency_user(cu_id,member_id,currency_id,num,child_num) select cu_id,member_id,currency_id,num,num as child_num from {$db_prefix}currency_user where currency_id={$currency['currency_id']}");

        //补齐没有资产的用户
        Db::execute("insert into {$db_prefix}hongbao_node_currency_user(member_id,currency_id,num,child_num) 
                select member_id,".$currency['currency_id'].",0,0 from {$db_prefix}member where member_id not in(
                select member_id from {$db_prefix}hongbao_node_currency_user
        )");

        $right_count = Member::count();
        //检测数据
        $count = self::count();
        if($count<=0 || $right_count!=$count) {
            Log::write('加载新数据失败');
            return false;
        }
        return true;
    }

    //批量增加上级业绩
    static function parent_inc($user_id,$num,$config) {
        $count = MemberBind::where(['child_id'=>$user_id])->count(); //上级人数
        if($count==0) return true;

        if($num>=$config['node_child_currency_min']) {
            //批量增加上级业绩 增加满足条件的下级数量
            $update = self::execute('update '.config("database.prefix").'hongbao_node_currency_user a,'.config("database.prefix").'member_bind b 
            set a.child_num=a.child_num+'.$num.',a.child_count=a.child_count+1,a.all_child_count=a.all_child_count+1 
            where a.member_id = b.member_id and  b.child_id='.$user_id.';');
        } elseif($num>0) {
            //批量增加上级业绩 增加满足条件的下级数量
            $update = self::execute('update '.config("database.prefix").'hongbao_node_currency_user a,'.config("database.prefix").'member_bind b 
            set a.child_num=a.child_num+'.$num.',a.all_child_count=a.all_child_count+1 
            where a.member_id = b.member_id and  b.child_id='.$user_id.';');
        } else {
            $update = self::execute('update '.config("database.prefix").'hongbao_node_currency_user a,'.config("database.prefix").'member_bind b 
            set a.all_child_count=a.all_child_count+1 
            where a.member_id = b.member_id and  b.child_id='.$user_id.';');
        }
        return $update==$count;
    }


    //获取资产详情
    static function currency_num($user_id,$currency_id) {
        return self::where(['member_id'=>$user_id,'currency_id'=>$currency_id])->find();
    }

    //检测是否通过考核
    static function check_pass($currency_user,$config) {
        //直推数量大于10 且 资产最少大于100
        $child_currency_user = self::alias('b')->field('b.num,b.child_num')->where(['a.member_id'=>$currency_user['member_id'],'a.level'=>1,'b.num'=>['egt',$config['node_child_currency_min']]])
            ->join(config("database.prefix") . "member_bind a", "a.child_id=b.member_id", "LEFT")
            ->order('b.child_num desc')->limit($config['node_child_num'])->select();
        if(count($child_currency_user)<$config['node_child_num']) return false;

        // 且有3个团队的资产分别大于5W 3W 2W
        if( ($child_currency_user[0]['child_num']) <$config['node_department1']) return false;
        if( ($child_currency_user[1]['child_num']) <$config['node_department2']) return false;
        if( ($child_currency_user[2]['child_num']) <$config['node_department3']) return false;

        //设置状态为已通过
        return self::where(['cu_id'=>$currency_user['cu_id']])->setField('is_pass',1);
    }

    //获取昨日的等级
    static function getYestdayLevel($member_id,$currency_id,$config=[]) {
        $res = [
            'level' => 0,
            'level_name' => '',
        ];

        $currency_user = self::currency_num($member_id,$currency_id);
        if($currency_user) return $res;

        if(empty($config)) $config = HongbaoConfig::get_key_value();
        $cur_award = $config['node_max_award_count'];
        while (true) {
            if(!isset($config['node_level'.$cur_award]) || !isset($config['node_award'.$cur_award])) break;
            if($currency_user['all_child_count']>=$config['node_level'.$cur_award]) {
                $res = [
                    'level' => $cur_award,
                    'level_name' => isset($config['nodel_levelname'.$cur_award]) ? $config['nodel_levelname'.$cur_award] : '',
                ];
                break;
            }
            $cur_award--;
        }
        return $res;
    }

    //节点奖励详情
    static function node_info($member_id) {
        $r['code'] = ERROR1;
        $r['message'] = lang("not_data");
        $r['result'] = null;

        //是否统计完成
        $info = Db::name('hongbao_node_award_summary')->order('id desc')->find();
        if(!$info || $info['today']!=date('Y-m-d')) return $r;


        $config = HongbaoConfig::get_key_value();
        $node_currency = Currency::where(['currency_mark'=>$config['node_currency_mark']])->field('currency_id,currency_name')->find();
        if(!$node_currency) return $r;

        $node_currency_user = self::where(['member_id'=>$member_id,'currency_id'=>$node_currency['currency_id']])->find();
        if(!$node_currency_user) return $r;
        $node_currency_user = $node_currency_user->toArray();


        //所有等级配置
        $all_level = self::getAllLevel($config);

        $cache_key = 'node_'.$member_id;
        $node_data = cache($cache_key);
        if(empty($node_data) || $node_data['today']!=$info['today']) {
            $node_currency_user['today'] = $info['today'];

            //所有符合条件的直推数量
            $one_count = self::alias('b')->field('b.num,b.child_num')->where(['a.member_id'=>$node_currency_user['member_id'],'a.level'=>1,'b.num'=>['egt',$config['node_child_currency_min']]])
                ->join(config("database.prefix") . "member_bind a", "a.child_id=b.member_id", "LEFT")
                ->count();
            $node_currency_user['recommand_one'] = $one_count;

            //直推数量大于10 且 资产最少大于100
            $child_currency_user = self::alias('b')->field('b.num,b.child_num')->where(['a.member_id'=>$node_currency_user['member_id'],'a.level'=>1,'b.num'=>['egt',$config['node_child_currency_min']]])
                ->join(config("database.prefix") . "member_bind a", "a.child_id=b.member_id", "LEFT")
                ->order('b.child_num desc')->limit(3)->select();
            if(!$child_currency_user) {
                $node_currency_user['department1'] = $node_currency_user['department2'] = $node_currency_user['department3'] = 0;
            } else {
                $node_currency_user['department1'] = isset($child_currency_user[0]['child_num']) ? $child_currency_user[0]['child_num'] : 0;
                $node_currency_user['department2'] = isset($child_currency_user[1]['child_num']) ? $child_currency_user[1]['child_num'] : 0;
                $node_currency_user['department3'] = isset($child_currency_user[2]['child_num']) ? $child_currency_user[2]['child_num'] : 0;
            }
            //本人资产是否通过
            $node_currency_user['self_num_pass'] = $node_currency_user['num'] > $config['node_user_currency_min'] ? 1 : 0;
            $node_currency_user['level'] = self::getCurLevel($node_currency_user,$config);
            //可兑换最高数量
            $node_currency_user['echange_num_max'] = $all_level[$node_currency_user['level']]['echange_num'];

            $node_data = $node_currency_user;
            cache($cache_key,$node_data);
        }

        //赠送数量 需要领取且之能领取一次
        $node_data['exchange_award'] = FlopOrders::getExchangeAward($member_id,$config);
        $node_data['currency_name'] = $node_currency['currency_name'];
        $r['code'] = SUCCESS;
        $r['message'] = lang('success');
        $r['result'] = [
            'all_level' => $all_level,
            'node_currency_user' => $node_data,
        ];
        return $r;
    }

    //获取兑换币种
    static function getExchangeCurrency($config) {
        $node_currency = Currency::where(['currency_mark'=>$config['node_level_exchange_mark']])->field('currency_id,currency_name')->find();
        return $node_currency;
    }

    static function getExchangeNumByLevel($member_id) {
        $config = HongbaoConfig::get_key_value();
        $node_currency = Currency::where(['currency_mark'=>$config['node_currency_mark']])->field('currency_id,currency_name')->find();
        if(!$node_currency) return 0;

        $node_currency_user = self::where(['member_id'=>$member_id,'currency_id'=>$node_currency['currency_id']])->find();
        if(!$node_currency_user) return 0;

        $level = self::getCurLevel($node_currency_user,$config);
        return isset($config['node_level_exchange'.$level]) ? $config['node_level_exchange'.$level]: 0;
    }

    //获取所有等级的配置
    static function getAllLevel($config) {
        $all_level = [];
        $all_level[0] = [
            'level' => 0,
            'level_name' => '普通会员',
            //该等级可兑换数量
            'echange_num' => $config['node_level_exchange0'],
            //该等级单有效用户奖励数量
            'award_num' => 0,
            'require' => [
                //最少邀请注册量
                'min_reg_num' => 0,
                //本人最少资产数量
                'self_num' => 0,
                //直推人数
                'recommand_one' => 0,
                //直推下级 最少资产量
                'recommand_one_num' => 0,
                //部门1考核
                'department1' => 0,
                //部门2考核
                'department2' => 0,
                //部门3考核
                'department3' => 0,
            ]
        ];

        $cur_award = 1;
        while (true) {
            if(!isset($config['node_level'.$cur_award]) || !isset($config['node_award'.$cur_award])) break;
            $all_level[$cur_award] = [
                'level' => $cur_award,
                'level_name' => $config['nodel_levelname'.$cur_award],
                // 该等级可兑换数量
                'echange_num' => $config['node_level_exchange'.$cur_award],
                //该等级单有效用户奖励数量
                'award_num' => $config['node_award'.$cur_award],
                //考核要求
                'require' => [
                    //最少邀请注册量
                    'min_reg_num' => $config['node_level'.$cur_award],
                    //本人最少资产数量
                    'self_num' => $config['node_user_currency_min'],
                    //直推人数
                    'recommand_one' => $config['node_child_num'],
                    //直推下级 最少资产量
                    'recommand_one_num' => $config['node_child_currency_min'],
                    //部门1考核
                    'department1' =>  $config['node_department1'],
                    //部门2考核
                    'department2' =>  $config['node_department2'],
                    //部门3考核
                    'department3' =>  $config['node_department3'],
                ],
            ];
            $cur_award++;
        }
        return $all_level;
    }

    //获取当前等级
    static function getCurLevel($node_currency_user,$config) {
        $level = 0;
        if(!$node_currency_user || $node_currency_user['is_pass']!=1) return $level;

        $cur_award = 1;
        while (true) {
            if (!isset($config['node_level'.$cur_award]) || !isset($config['node_award' . $cur_award])) break;

            if($node_currency_user['all_child_count']>=$config['node_level' . $cur_award]) {
                $level = $cur_award;
                break;
            }
            $cur_award++;
        }
        return $level;
    }
}
