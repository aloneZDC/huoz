<?php
//Fil项目 涡轮增压 收入
namespace app\common\model;

use think\Log;
use think\Model;
use think\Db;
use think\Exception;

class FilMiningIncome extends Base
{
    /**
     * @param $base_fil_mining //奖励矿机
     * @param $member_id //奖励用户
     * @param $currency_id //奖励币种
     * @param $award_level //奖励类型
     * @param $award_num //奖励可用数量
     * @param $base_id //来源第三方ID
     * @param $base_num //来源第三方数量
     * @param $release_id //释放ID
     * @param $base_percent //奖励可用比例
     * @param $today_start
     * @param int $third_member_id //来源第三方用户
     * @param int $award_lock_num //奖励锁仓数量 $base_num * $award_lock_percent
     * @param int $award_lock_percent //奖励锁仓比例
     * @return bool
     */
    static function award($base_fil_mining,$member_id,$currency_id,$award_level,$award_num,$base_id,$base_num,$release_id,$base_percent,$today_start,$third_member_id=0,$award_lock_num=0,$award_lock_percent=0,$fil_lock_num=0) {
        $account_book_content = 'fil_mining_release_award'.$award_level;
        if($award_level==1) {
            $account_book_id = 6323;
        } elseif ($award_level==2) {
            $account_book_id = 6324;
        } elseif ($award_level==3) {
            $account_book_id = 6325;
        } elseif ($award_level==4) {
            $account_book_id = 6326;
        } elseif ($award_level==5) {
            $account_book_id = 6327;
        } elseif ($award_level==11) {
            $account_book_id = 6328;
        } elseif ($award_level==12) {
            $account_book_id = 6329;
        } elseif ($award_level==13) {
            $account_book_id = 6330;
        } elseif ($award_level==15) {
            $account_book_id = 6331;
        } else {
            return false;
        }

        $currency_user = CurrencyUser::getCurrencyUser($member_id,$currency_id);
        if(empty($currency_user)) return false;

        try {
            self::startTrans();

            if($award_num>0) {
                //20200118修改为 推荐+级差最大9倍
//                $release_num_avail_num = min($base_fil_mining['release_num_avail'],$award_num);
                $flag = FilMining::where(['id'=>$base_fil_mining['id'],'release_num_avail'=>$base_fil_mining['release_num_avail']])->update([
//                    'release_num_avail' => ['dec',$release_num_avail_num],
                    'total_child'.$award_level => ['inc',$award_num],
                ]);
                if(!$flag) throw new Exception("更新奖励总量失败");
            }

            //添加奖励记录
            $item_id = self::insertGetId([
                'member_id' => $member_id,
                'currency_id' => $currency_id,
                'type' => $award_level,
                'num' => $award_num,
                'lock_num' => $award_lock_num>0?$award_lock_num:$fil_lock_num,
                'add_time' => time(),
                'award_time' => $today_start,
                'third_percent' => $base_percent,
                'third_lock_percent' => $award_lock_percent,
                'third_num' => $base_num,
                'third_id' => $base_id,
                'third_member_id' => $third_member_id,
                'release_id' => $release_id,
            ]);
            if(!$item_id) throw new Exception("添加奖励记录");

            if($award_num>0) {
                //增加账本 增加资产
                $flag = AccountBook::add_accountbook($currency_user['member_id'],$currency_user['currency_id'],$account_book_id,$account_book_content,'in',$award_num,$item_id,0);
                if(!$flag) throw new Exception("添加账本失败");

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'num'=>$currency_user['num']])->setInc('num',$award_num);
                if(!$flag) throw new Exception("添加资产失败");
            }

            if($award_lock_num>0) {
                $flag = CurrencyLockBook::add_log('lock_num',$account_book_content,$currency_user['member_id'],$currency_user['currency_id'],$award_lock_num,$base_id,$base_num,$award_lock_percent,$third_member_id);
                if(!$flag) throw new Exception("添加锁仓资产记录失败");

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'lock_num'=>$currency_user['lock_num']])->setInc('lock_num',$award_lock_num);
                if(!$flag) throw new Exception("添加锁仓资产失败");
            }

            if($fil_lock_num>0.000001) {
                // 增加锁仓记录 增加锁仓资产
                $flag = CurrencyLockBook::add_log('fil_area_lock_num','fil_mining_area_lock',$currency_user['member_id'],$currency_user['currency_id'], $fil_lock_num,$item_id, $base_num,$award_lock_percent,0);
                if(!$flag) throw new Exception("添加锁仓资产记录失败");

                $flag = CurrencyUser::where(['cu_id'=>$currency_user['cu_id'],'fil_area_lock_num'=>$currency_user['fil_area_lock_num']])->setInc('fil_area_lock_num',$fil_lock_num);
                if(!$flag) throw new Exception("添加锁仓资产失败");
                $flag = FilMining::where(['id'=>$base_fil_mining['id'],'total_release'.$award_level=>$base_fil_mining['total_release'.$award_level]])->update([
                    'total_release'.$award_level => ['inc',$fil_lock_num],
                ]);
                if(!$flag) throw new Exception("更新奖励总量失败");
            }

            self::commit();

            return true;
        } catch (Exception $e) {
            self::rollback();
            Log::write("涡轮增压:失败".$e->getMessage());
        }
        return false;
    }

    static function getList($member_id,$type=[],$page=1,$rows=10) {
        $r['code'] = ERROR1;
        $r['message'] = lang('not_data');
        $r['result'] = null;

        $where = [
            'a.member_id'=> $member_id,
            'a.num' => ['gt',0],
        ];
        if(!empty($type)) {
            $where['a.type'] = ['in',$type];
        }

        $list = self::alias('a')->field('a.id,a.currency_id,a.num,a.type,a.add_time,b.currency_name,m.ename')
            ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
            ->join(config("database.prefix") . "member m", "a.third_member_id=m.member_id", "LEFT")
            ->where($where)
            ->page($page, $rows)->order("a.id desc")->select();
        if(!$list) return $r;

        foreach ($list as &$item) {
            $item['title'] = lang('fil_mining_release_award'.$item['type']);
            $item['add_time'] = date('m-d H:i',$item['add_time']);
            if(!$item['ename']) $item['ename'] = '';
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    static function getLockNum($member_id) {
        $config = FilMiningConfig::get_key_value();
        $currency_user = CurrencyUser::getCurrencyUser($member_id,$config['pay_currency_id']);

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'lock_num' => $currency_user ? $currency_user['lock_num'] : 0,
            'percent' => $config['lock_release_percent'],
        ];
        return $r;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
    public function thirdmember() {
        return $this->belongsTo('app\\common\\model\\Member', 'third_member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }
}
