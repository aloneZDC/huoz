<?php
//跳跃排名倒序加权算法 - 用户收益汇总
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class JumpRankingMemberSummary extends Model
{
    static function update_ranking($member_id,$currency_id,$ranking,$income,$today) {
        try{
            $info = self::where(['member_id'=>$member_id,'currency_id'=>$currency_id])->find();
            if($info) {
                $flag = self::where(['member_id'=>$member_id,'currency_id'=>$currency_id])->update([
                    'total_income' => ['inc',$income],
                    'total_ranking_income' => ['inc',$income],
                    'today_ranking' => $ranking,
                    'today_ranking_income' => $income,
                    'today_ranking_time' => $today,
                ]);
            } else {
                $flag = self::insertGetId([
                    'member_id' => $member_id,
                    'currency_id' => $currency_id,
                    'total_income' => $income,
                    'total_ranking_income' => $income,
                    'today_ranking' => $ranking,
                    'today_ranking_income' => $income,
                    'today_ranking_time' => $today,
                ]);
            }

            return $flag;
        } catch (Exception $e) {
            return false;
        }
    }

    static function update_power($member_id,$currency_id,$power,$income,$today) {
        try{
            $info = self::where(['member_id'=>$member_id,'currency_id'=>$currency_id])->find();
            if($info) {
                $flag = self::where(['member_id'=>$member_id,'currency_id'=>$currency_id])->update([
                    'total_income' => ['inc',$income],
                    'total_power_income' => ['inc',$income],
                    'today_power' => $power,
                    'today_power_income' => $income,
                    'today_power_time' => $today,
                ]);
            } else {
                $flag = self::insertGetId([
                    'member_id' => $member_id,
                    'currency_id' => $currency_id,
                    'total_income' => $income,
                    'total_power_income' => $income,
                    'today_power' => $power,
                    'today_power_income' => $income,
                    'today_power_time' => $today,
                ]);
            }

            return $flag;
        } catch (Exception $e) {
            return false;
        }
    }

    static function getList($member_id,$currency_id=0) {
        $currency = JumpRankingCurrencyConfig::alias('a')->field('a.currency_id,a.raning_min_mum,a.auto_start_time,b.currency_name,b.currency_logo')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->select();
        $jump_ranking = self::where(['member_id'=>$member_id])->field('member_id',true)->select();
        if($jump_ranking) {
            $jump_ranking = array_column($jump_ranking,null,'currency_id');
        } else {
            $jump_ranking = [];
        }


        //最佳
        $summary = JumpRankingSummary::where('today',date('Y-m-d'))->select();
        if($summary) {
            $summary = array_column($summary,null,'currency_id');
        } else {
            $summary = [];
        }

        $today_start = todayBeginTimestamp();
        foreach ($currency as &$value){
            $value['price'] = CurrencyPriceTemp::get_price_currency_id($value['currency_id'],'CNY');
            if(isset($jump_ranking[$value['currency_id']])) {
                if($jump_ranking[$value['currency_id']]['today_ranking_time']<$today_start) $jump_ranking[$value['currency_id']]['today_ranking_income'] =0;
                if($jump_ranking[$value['currency_id']]['today_power_time']<$today_start) $jump_ranking[$value['currency_id']]['today_power_income'] =0;
                $value = array_merge($value->toArray(),$jump_ranking[$value['currency_id']]->toArray());

                $value['total_income'] = keepPoint($value['total_income'],2);
                $value['total_ranking_income'] = keepPoint($value['total_ranking_income'],2);
                $value['today_power_income'] = keepPoint($value['today_power_income'],2);
            } else {
                $value['total_income'] = $value['total_ranking_income'] = $value['today_ranking'] = $value['today_ranking_income']= $value['today_ranking_time']= 0;
                $value['total_child'] = $value['total_power_income'] = $value['today_power'] = $value['today_power_income']= $value['today_power_time']= 0;
            }
            $value['total_income_cny'] = keepPoint($value['price'] * $value['total_income'],2);

            $value['best_num'] = 0;
            if(isset($summary[$value['currency_id']])) $value['best_num'] = $summary[$value['currency_id']]['jump_ranking_max_mul'];
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $currency;
        return $r;
    }

    static function getOne($member_id,$currency_id) {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];

        $currency = JumpRankingCurrencyConfig::alias('a')->field('a.currency_id,a.raning_min_mum,a.auto_start_time,b.currency_name,b.currency_logo')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->where('a.currency_id',$currency_id)
            ->find();
        if(!$currency) return $r;

        $jump_ranking = self::where(['member_id'=>$member_id,'currency_id'=>$currency_id])->field('member_id,currency_id',true)->find();
        if(!$jump_ranking) $jump_ranking = [];

        //最佳
        $summary = JumpRankingSummary::where('today',date('Y-m-d'))->where('currency_id',$currency_id)->find();
        if(!$summary) $summary = [];

        $today_start = todayBeginTimestamp();
        if($jump_ranking) {
            if($jump_ranking['today_ranking_time']<$today_start) $jump_ranking['today_ranking_income'] =0;
            if($jump_ranking['today_power_time']<$today_start) $jump_ranking['today_power_income'] =0;
            $currency = array_merge($currency->toArray(),$jump_ranking->toArray());
        } else {
            $currency['total_income'] = $currency['total_ranking_income'] = $currency['today_ranking'] = $currency['today_ranking_income']= $currency['today_ranking_time']= 0;
            $value['total_child'] = $currency['total_power_income'] = $currency['today_power'] = $currency['today_power_income']= $currency['today_power_time']= 0;
        }

        $currency['best_num'] = 0;
        if($summary) $currency['best_num'] = $summary['jump_ranking_max_mul'];

        $currency['price'] = CurrencyPriceTemp::get_price_currency_id($currency['currency_id'],'CNY');
        $currency['total_income_cny'] = keepPoint($currency['price'] * $currency['total_income'],2);
        $usdtCurrency = Currency::where('currency_mark', 'USDT')->find();
        $currency['price_usd'] = Trade::getLastTradePrice($currency['currency_id'], $usdtCurrency['currency_id']);
        $currency['total_income_usd'] = keepPoint($currency['price_usd'] * $currency['total_income'],2);

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $currency;
        return $r;
    }

    static function getIncomeList($member_id,$currency_id,$type,$lang,$page,$page_size) {
        $r = [
            'code' => ERROR1,
            'message' => lang('not_data'),
            'result' => null
        ];

        if($type=='ranking') {
            $type_where = ['a.type' => 2203];
        } else if ($type=='power') {
            $type_where = ['a.type' => 2205];
        } else {
            $type_where = [ 'a.type' => ['in',[2203,2205]]];
        }


        $account = new AccountBook();
        $count = false;
        $list =  $account->getLog($member_id,$currency_id,'',$page,$page_size,$lang,$count,$type_where);
        if(!$list) return $r;

        $income = [];
        foreach ($list as $value) {
            $income[] = [
                'currency_name' => $value['from_name'],
                'currency_logo' => $value['currency_logo'],
                'title' => $value['type_name_ori'],
                'number' => $value['number'],
                'add_time' => $value['add_time'],
            ];
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $income;
        return $r;
    }

    static function myTeam($member_id,$currency_id,$child_name,$page,$page_size) {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['update_time'] = '';
        $r['result'] = null;
        $r['total'] = 0;
        $r['currency_num'] = 0;

        $currency = Currency::where(['currency_id'=>$currency_id])->field('currency_id,currency_name')->find();
        $r['currency_name'] = $currency ? $currency['currency_name'] : '';

        $summary = JumpRankingSummary::where('currency_id',$currency_id)->order('add_time desc')->find();
        if(!$summary) return $r;
        $r['update_time'] = date('Y-m-d H:i:s',$summary['add_time']);

        $currency_user = CurrencyUser::getCurrencyUser($member_id,$currency_id);
        if($currency_user) $r['currency_num'] = $currency_user['num'];

        $table_name = JumpRankingCurrencyUser::getTableName([
            'today_start' => strtotime($summary['today']),
        ]);
        $where = [
            'a.currency_id' => $currency_id,
            'b.member_id' => $member_id,
            'b.level' => 1,
        ];
        if(!empty($child_name)) {
            $where['m.ename'] = ['like',$child_name.'%'];
        }
        $list = Db::table($table_name)->alias('a')->field('a.member_id,cu.num,a.child_num,a.total_child,a.ranking,a.power,s.total_income,m.ename,m.phone,m.email')
            ->where($where)
            ->join(config("database.prefix") . "member_bind b", "a.member_id=b.child_id", "LEFT")
            ->join(config("database.prefix") . "member m", "a.member_id=m.member_id", "LEFT")
            ->join(config("database.prefix") . "jump_ranking_member_summary s", "s.member_id=a.member_id and s.currency_id=a.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency_user cu", "cu.member_id=a.member_id and cu.currency_id=a.currency_id", "LEFT")
            ->page($page, $page_size)->order("a.total_child desc,a.member_id asc")->select();
        if(!$list) return $r;

        $total = Db::table($table_name)->alias('a')->field('a.member_id,cu.num,a.child_num,a.total_child,a.ranking,a.power,s.total_income,m.ename,m.phone,m.email')
            ->where($where)
            ->join(config("database.prefix") . "member_bind b", "a.member_id=b.child_id", "LEFT")
            ->join(config("database.prefix") . "member m", "a.member_id=m.member_id", "LEFT")
            ->join(config("database.prefix") . "jump_ranking_member_summary s", "s.member_id=a.member_id and s.currency_id=a.currency_id", "LEFT")
            ->join(config("database.prefix") . "currency_user cu", "cu.member_id=a.member_id and cu.currency_id=a.currency_id", "LEFT")
            ->count();

        foreach ($list as $key=>&$value) {
            $value['count'] = ($page-1) * $page_size + $key + 1;
            $value['currency_name'] = $currency ? $currency['currency_name'] : '';
            if(!$value['total_income']) $value['total_income'] = 0;
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        $r['total'] = $total;
        return $r;
    }

    static function myTeambyDay($today,$member_id,$currency_id,$child_name,$page,$page_size) {
        $r = [
            'code' => ERROR1,
            'message' => lang('not_data'),
            'result' => null
        ];

        $currency = Currency::where(['currency_id'=>$currency_id])->field('currency_id,currency_name')->find();

        $summary = JumpRankingSummary::where('currency_id',$currency_id)->where('today',$today)->order('add_time desc')->find();
        if(!$summary) return $r;

        $table_name = JumpRankingCurrencyUser::getTableName([
            'today_start' => strtotime($summary['today']),
        ]);

        $where = [
            'a.currency_id' => $currency_id,
            'b.member_id' => $member_id,
            'b.level' => 1,
        ];
        if(!empty($child_name)) {
            $where['m.ename'] = $child_name;
        }
        $list = Db::table($table_name)->alias('a')->field('a.member_id,a.num,a.child_num,a.total_child,a.ranking,a.power,s.total_income,m.ename,m.phone,m.email')
            ->where($where)
            ->join(config("database.prefix") . "member_bind b", "a.member_id=b.child_id", "LEFT")
            ->join(config("database.prefix") . "member m", "a.member_id=m.member_id", "LEFT")
            ->join(config("database.prefix") . "jump_ranking_member_summary s", "s.member_id=a.member_id and s.currency_id=a.currency_id", "LEFT")
            ->page($page, $page_size)->order("a.total_child desc,a.member_id asc")->select();
        if(!$list) return $r;

        foreach ($list as $key=>&$value) {
            $value['count'] = ($page-1) * $page_size + $key + 1;
            $value['currency_name'] = $currency ? $currency['currency_name'] : '';
            if(!$value['total_income']) $value['total_income'] = 0;
        }

        $total = Db::table($table_name)->alias('a')->field('a.member_id,a.num,a.child_num,a.total_child,a.ranking,a.power,s.total_income,m.ename,m.phone,m.email')
            ->where($where)
            ->join(config("database.prefix") . "member_bind b", "a.member_id=b.child_id", "LEFT")
            ->join(config("database.prefix") . "member m", "a.member_id=m.member_id", "LEFT")
            ->join(config("database.prefix") . "jump_ranking_member_summary s", "s.member_id=a.member_id and s.currency_id=a.currency_id", "LEFT")
            ->count();

        $r['total'] = $total;
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['currency_name'] = $currency ? $currency['currency_name'] : '';
        $r['update_time'] = date('Y-m-d H:i:s',$summary['add_time']);
        $r['result'] = $list;
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
