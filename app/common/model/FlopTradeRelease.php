<?php
//翻牌订单释放
namespace app\common\model;
use think\Exception;
use think\Log;
use think\Model;

class FlopTradeRelease extends Model
{
    static function release_num($flop_trade,$flop_release_config,$flop_team_release_max=0,$flop_team_contract_min=0) {
        $currency_user = CurrencyUser::getCurrencyUser($flop_trade['member_id'],$flop_trade['currency_id']);
        if(!$currency_user) return false;

        //根据用户持仓量获取配置
        $config = FlopTradeReleaseConfig::getConfigByNum($flop_release_config,$currency_user['keep_num']);
        if(empty($config)) return false;

        //20200628改为随机比例
        $config_percent = keepPoint(randomFloat($config['min_percent'],$config['percent']),6);

        $release_num = keepPoint($flop_trade['num'] * $config_percent/100,6); //本人可释放量
        $release_num = min($release_num,$currency_user['keep_num']);//取最小值
        if($release_num<0.000001) $release_num = 0;

        //如果设置不能释放  则不释放
        if($flop_trade['is_can_release']!=1) $release_num = 0;

        //到可用数量
        $num_num = keepPoint($release_num * $config['num_percent']/100,6);
        $num_num = $num_num<0.000001 ? 0 : $num_num;
        //到云攒金数量
        $air_num = keepPoint($release_num-$num_num,6);
        $air_num = $air_num<0.000001 ? 0 : $air_num;

        //释放本人
        try{
            self::startTrans();
            $flag = FlopTrade::where(['trade_id'=>$flop_trade['trade_id'],'is_release'=>0])->update([
                'is_release' => 1,
                'release_num' => $release_num,
            ]);
            if(!$flag) throw new Exception("更改翻牌订单状态失败");

            if($release_num>0) {
                $insert_id = self::insertGetId([
                    'type' => 'release',
                    'trade_id' => $flop_trade['trade_id'],
                    'member_id' => $currency_user['member_id'],
                    'currency_id' => $currency_user['currency_id'],
                    'num' => $release_num,
                    'percent' => $config_percent,
                    'base_num' => $flop_trade['num'],
                    'add_time' => time(),
                ]);
                if(!$insert_id) throw new Exception("添加释放记录失败");

                //添加KOIC 减少记录
                $flag = HongbaoKeepLog::add_log('release',$currency_user['member_id'],$currency_user['currency_id'],$release_num,$insert_id,$flop_trade['num'],$config_percent);
                if(!$flag) throw new Exception("添加KOIC释放记录失败");

                if($num_num>0) {
                    $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],1005,'flop_release','in',$num_num,$flop_trade['trade_id'],0);
                    if(!$flag) throw new Exception("添加账本失败");
                }

                if($air_num>0) {
                    $flag = HongbaoAirNumLog::add_log('flop',$currency_user['member_id'],$currency_user['currency_id'],$air_num,$insert_id,$release_num,keepPoint(100-$config['num_percent'],2));
                    if(!$flag) throw new Exception("添加KOIC释放记录失败");
                }

                if($release_num>=0.000001 || $num_num>=0.000001|| $air_num>=0.000001) {
                    $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'keep_num'=>$currency_user['keep_num']])->update([
                        'num' => ['inc',$num_num],
                        'air_num' => ['inc',$air_num],
                        'keep_num'=> ['dec',$release_num],
                    ]);
                    if(!$flag) throw new Exception("增加资产失败");
                }
            }

            self::commit();
        }catch (Exception $e) {
            self::rollback();
            Log::write($flop_trade['trade_id']."翻牌释放错误:".$e->getMessage());
            return false;
        }

        if($config['levels_percent']<=0) return true;

        $member_id = $flop_trade['member_id'];
        for ($count=1;$count<=$config['levels'];$count++) {
            $level_release_num = keepPoint($flop_trade['num'] * $config['levels_percent']/100,6); //本人可释放量
            $cur_member = Member::where(['member_id'=>$member_id])->field('member_id,pid')->find();
            if(!$cur_member) break;
            if($cur_member['pid']<=0) break;
            $member_id = $cur_member['pid'];

            $level_currency_user = CurrencyUser::getCurrencyUser($cur_member['pid'],$flop_trade['currency_id']);
            if(!$level_currency_user) continue;

            //团队释放数量达到限制时 合约活跃用户才能继续拿奖励
            $contract_trade_num = ContractOrder::get_order_total($cur_member['pid']); //合约中已交易数量
            if($contract_trade_num<$flop_team_contract_min) { //合约活跃考核 交易5000KOI
                if($flop_team_release_max>0) { //团队释放数量限制30000
                    $release_team_num = self::release_team_num($cur_member['pid']);
                    $release_yu = keepPoint($flop_team_release_max - $release_team_num,6);
                    if($release_yu<=0) {
                        $level_release_num = 0;
                    } else {
                        $level_release_num = min($level_release_num,$release_yu);
                    }
                }
            }

            $level_release_num_real = min($level_release_num,$level_currency_user['keep_num']);
            if($level_release_num_real<0.000001) $level_release_num_real = 0;
            if($level_release_num_real>0){
                self::release($flop_trade,$level_currency_user,$level_release_num_real,$config);
            }
        }
    }

    static function release($flop_trade,$currency_user,$release_num,$config) {
        //释放本人
        try{
            self::startTrans();
            $insert_id = self::insertGetId([
                'type' => 'team_release',
                'trade_id' => $flop_trade['trade_id'],
                'member_id' => $currency_user['member_id'],
                'currency_id' => $currency_user['currency_id'],
                'num' => $release_num,
                'percent' => $config['levels_percent'],
                'base_num' => $flop_trade['num'],
                'add_time' => time(),
            ]);
            if(!$insert_id) throw new Exception("添加释放记录失败");

            //添加KOIC 减少记录
            $flag = HongbaoKeepLog::add_log('release',$currency_user['member_id'],$currency_user['currency_id'],$release_num,$insert_id,$flop_trade['num'],$config['levels_percent']);
            if(!$flag) throw new Exception("添加KOIC释放记录失败");

            $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],1005,'flop_release','in',$release_num,$flop_trade['trade_id'],0);
            if(!$flag) throw new Exception("添加账本失败");

            $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'keep_num'=>$currency_user['keep_num']])->update([
                'num' => ['inc',$release_num],
                'keep_num'=> ['dec',$release_num],
            ]);
            if(!$flag) throw new Exception("增加资产失败");
            self::commit();
            return true;
        }catch (Exception $e) {
            self::rollback();
            Log::write($flop_trade['trade_id']."翻牌释放错误:{$currency_user['member_id']}:".$e->getMessage());
            return false;
        }
    }

    //获取团队释放的金额
    static function release_team_num($member_id) {
        $num = self::where(['type'=>'team_release','member_id'=>$member_id])->sum('num');
        return $num ? $num : 0;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,phone,email');
    }

    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
