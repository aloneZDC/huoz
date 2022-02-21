<?php
namespace app\cli\controller;
use think\Log;
use think\Db;
use think\Exception;

/**
 *领取静态分红
 */
class BounsReceive {
    //推荐奖励自动到账 receive_time领取时间
    public static function bouns_insert_receive($insert_data,$receive_time) {
    	try{
    		Db::startTrans();

            //去除小数点,减少波比
            if($insert_data['num']>1) $insert_data['num'] = intval($insert_data['num']);

    		$insert_data['receive_status'] = 1;
    		$insert_data['receive_time'] = $receive_time;
    		$insert_id = Db::name('boss_bouns_log')->insertGetId($insert_data);
    		if(!$insert_id) throw new Exception('插入出错:'.$insert_data['member_id']);

    		//增加瑞波钻及创新区资产 创新区关闭后100%进入瑞波币
            $is_percent_open = Db::name('config')->where(['key'=>'is_percent_open'])->find();
            if(!$is_percent_open || $is_percent_open['value']==1){
                $xrpz_percent =  Boss_pan_receive_xrpz;
                $member_percent = Db::name('boss_plan_percent')->where(['member_id'=>$insert_data['member_id']])->find();
                if($member_percent) {
                    if($member_percent['percent']>=0 && $member_percent['percent']<=1) $xrpz_percent = (1-$member_percent['percent']);
                }
            } else { //关闭后默认100%
                $xrpz_percent = 1;
            }
    		$xrpz_num = keepPoint($insert_data['num']*$xrpz_percent,6);

    		// $flag = Db::name('boss_plan_info')->where(['member_id'=>$insert_data['member_id']])->setInc('xrpz_num',$xrpz_num);
    		// if(!$flag) throw new Exception('增加瑞波钻出错:'.$insert_data['member_id']);

            //2019.2.20修改为直接到XRP资产
            if($xrpz_num>0) {
                $xrp_currency = Db::name('currency')->field('currency_id,currency_name')->where(['currency_name'=>'XRP'])->find();
                if($xrp_currency) {
                    $lang_title = self::bouns_log_config($insert_data['type']);
                    //添加账本
                    $result = model('AccountBook')->addLog([
                        'member_id' => $insert_data['member_id'],
                        'currency_id' => $xrp_currency['currency_id'],
                        'type'=> 24,
                        'content' => $lang_title['title'],
                        'number_type' => 1,
                        'number' => $xrpz_num,
                        'fee' => 0,
                        'third_id' => $insert_id,
                    ]);
                    if(!$result) throw new Exception("分红:账本记录添加失败");

                    $currency_user = Db::name('currency_user')->lock(true)->where(['member_id'=>$insert_data['member_id'],'currency_id'=>$xrp_currency['currency_id']])->find();
                    if($currency_user) {
                        $flag = Db::name('currency_user')->where(['member_id'=>$insert_data['member_id'],'currency_id'=>$xrp_currency['currency_id']])->setInc('num',$xrpz_num);
                        if(!$flag) throw new Exception('分红增加XRP出错:'.$insert_data['member_id']);
                    } else {
                        $flag = Db::name('currency_user')->insertGetId([
                            'member_id' => $insert_data['member_id'],
                            'currency_id' => $xrp_currency['currency_id'],
                            'num' => $xrpz_num,
                        ]);
                        if(!$flag) throw new Exception('分红增加XRP出错:'.$insert_data['member_id']);
                    }
                }
            }

    		$xrpz_new_num = $insert_data['num'] - $xrpz_num;
            
            $log_type = $insert_data['type']==9 ? 6 : $insert_data['type'];
            $log_type = $insert_data['type']==10 ? 15 : $insert_data['type']; //创业奖励
            if($xrpz_new_num>0) {
        		$flag = Db::name('boss_plan_info')->where(['member_id'=>$insert_data['member_id']])->setInc('xrpz_new_num',$xrpz_new_num);
        		if(!$flag) throw new Exception('增加创新区出错:'.$insert_data['member_id']);

        		//增加领取记录
        		$time = time();
        		$lang_title = self::bouns_log_config($insert_data['type']);

        		$data =  [
        			'l_member_id'=> $insert_data['member_id'],
        			'l_value'=> $xrpz_num,
        			'l_time'=> $time,
        			'l_title'=> $lang_title['title'],
        			'l_type'=> $log_type,
        			'l_type_explain'=> $lang_title['type_explain']
        		];
        		// $flag = Db::name('xrp_log')->insertGetId($data);
        		// if(!$flag) throw new Exception('增加瑞波钻领取日志出错:'.$insert_data['member_id']);

        		$data['l_value'] = $xrpz_new_num;
        		$flag = Db::name('innovate_log')->insertGetId($data);
        		if(!$flag) throw new Exception('增加瑞波钻领取日志出错:'.$insert_data['member_id']);
            }

    		//增加已领取收益
    		$boss_plan_count = Db::name('boss_plan_count')->lock(true)->where(['member_id'=>$insert_data['member_id']])->find();
    		$flag = false;
            $log_type = $log_type==15 ? 10 : $log_type; //创业奖励
    		if(!$boss_plan_count) {
	    		$flag = Db::name('boss_plan_count')->where(['member_id'=>$insert_data['member_id']])->insertGetId([
	    			'member_id' => $insert_data['member_id'],
	    			'num'.$log_type => $insert_data['num'],
		            'total_profit'=> $insert_data['num'],   
	    		]);
    		} else {
    			//增加已领取收益
	    		$flag = Db::name('boss_plan_count')->where(['member_id'=>$insert_data['member_id']])->update([
	    			'num'.$log_type => ['inc',$insert_data['num']],
		            'total_profit'=> ['inc',$insert_data['num']],   
	    		]);
    		}
    		if($flag===false) throw new Exception('增加已领取收益出错:'.$insert_data['member_id']);

    		Db::commit();
    		return true;
    	}catch(Exception $e) {
            Db::rollback();
            $msg = $e->getMessage();
            Log::write("领取分红错误:".$msg);
        }

        return false;
    }

    //分红与奖励日志配置
    private static function bouns_log_config($num){
    	$empty = ['title'=>'','type_explain'=>''];

        $config=[
            '1'=>[ 'title'=>'lan_boss_title_log1','type_explain'=>'lan_bonus'], //基礎分红
            '2'=>['title'=>'lan_boss_title_log2','type_explain'=>'lan_bonus' ], //增加分红
            '3'=>['title'=>'lan_boss_title_log3','type_explain'=>'lan_bonus'], //一級分红
            '4'=> ['title'=>'lan_boss_title_log4','type_explain'=>'lan_reward' ], //互助分红
            '5'=> ['title'=>'lan_boss_title_log5','type_explain'=>'lan_reward'], //推薦獎勵
            '6'=>['title'=>'lan_boss_title_log6','type_explain'=>'lan_reward' ], //社區獎勵
            '7'=> ['title'=>'lan_boss_title_log7','type_explain'=>'lan_reward'], //平級獎勵
            '8'=> ['title'=>'lan_boss_title_log8','type_explain'=>'lan_reward'], //管理獎勵
            '9'=>['title'=>'lan_boss_title_log6','type_explain'=>'lan_reward' ],//社區獎勵(全球加权平分)
            '10'=>['title'=>'lan_boss_title_log5','type_explain'=>'lan_reward' ],//创业奖励
            '15'=>['title'=>'lan_boss_title_log5','type_explain'=>'lan_reward' ],//创业奖励
        ];
        return isset($config[$num]) ? $config[$num] : $empty;
    }
}