<?php

namespace app\common\model;

use app\common\model\ChiaMiningLevelConfig;
use app\common\model\MemberBind;
use app\common\model\ChiaMiningPay;
use app\common\model\ChiaMiningMemberSummary;
use app\common\model\ChiaMiningLevelIncomeDetail;
use app\common\model\AccountBook;
use app\common\model\CurrencyUser;
use think\Log;
use think\Db;
use think\Exception;

/**
 * Chia矿机用户汇总
 * Class ChiaMiningMember
 * @package app\common\model
 */
class ChiaMiningMember extends Base
{
	/**
     * 更新矿工身份
     * @param int $member_id 用户ID
     * @return bool
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
	public function updateLevel($member_id) {
		$all_levels = ChiaMiningLevelConfig::getAllLevel();
        if (empty($all_levels)) {
            Log::write($this->name . " 等级为空");
            return;
        }
        foreach ($all_levels as $cur_level) {
            $chia_mining_member = self::where([
                'member_id' => $member_id,
                'level' => $cur_level['level_id'] -1,
            ])->order('id asc')->find();
            if (empty($chia_mining_member)) {
                Log::write("chia矿机用户升级已完成C" . $cur_level['level_id']);
                continue;
            }

            // 自身入金考核
            if ($cur_level['level_self_num'] > 0) {
            	$pay_num = ChiaMiningPay::where(['member_id' => $member_id])->sum('tnum');
                if ($pay_num < $cur_level['level_self_num']) {
                    continue;
                }
            }

            if ($cur_level['level_id'] == 1) {//c1
            	//直推有效账户
            	if ($cur_level['level_child_level_count'] > 0) {
            		$valid_count = self::getTeamValidCount($member_id);
            		if ($valid_count < $cur_level['level_child_level_count']) {
	                    continue;
	                }
            	}
            	//团队累计T数
            	if ($cur_level['level_team_num'] > 0) {
            		$team_num = self::getTeamNum($member_id);
            		if ($team_num < $cur_level['level_team_num']) {
	                    continue;
	                }
            	}
            } else {
	            // 团队考核
	            if ($cur_level['level_child_recommand'] > 0) {
	                $recommand_count = self::getTeamRecommandMember($member_id, $cur_level['level_child_level']);
	                Log::write('团队考核'.$recommand_count);
	                if ($recommand_count < $cur_level['level_child_recommand']) {
	                    continue;
	                }
	            }
            }
            
            Log::write($chia_mining_member['id'] . " 升级成功" . $cur_level['level_id']);

            // 更新等级
            self::where(['member_id' => $member_id])->update(['level'=> $cur_level['level_id']]);
            // 更新团队最高等级
            self::updateTeamMaxLevel($member_id, $cur_level['level_id']);
            //更新用户汇总信息
            self::updateMember($member_id);
            
            // 增加升级记录
            Db::name('chia_mining_level_log')->insert([
                'third_id' => $member_id,
                'level' => $cur_level['level_id'],
                'add_time' => time(),
            ]);
            Log::write("chia矿机用户升级已完成C" . $cur_level['level_id']);
        }
	}

	/**
     * 获取直推有效账户
     * @param int $member_id 用户ID
     * @return int
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
	static function getTeamValidCount($member_id) {
		$result = MemberBind::alias('a')->join(config("database.prefix") . "chia_mining_pay b", 'a.child_id = b.member_id')->where(['a.member_id' => $member_id, 'a.level' => 1])->group('b.member_id')->count('a.child_id');
		return $result;
	}

	/**
     * 获取团队累计T数
     * @param int $member_id 用户ID
     * @return int
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
	public function getTeamNum($member_id) {
		$result = MemberBind::alias('a')->join(config("database.prefix") . "chia_mining_pay b", 'a.child_id = b.member_id')->where(['a.member_id' => $member_id])->sum('b.tnum');
		return $result;
	}

	/**
     * 获取团队累计购买数量
     * @param int $member_id 用户ID
     * @return int
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
	public function getTeamPayNum($member_id) {
		$result = MemberBind::alias('a')->join(config("database.prefix") . "chia_mining_pay b", 'a.child_id = b.member_id')->where(['a.member_id' => $member_id])->sum('b.real_pay_num');
		return $result;
	}

	/**
     * 团队考核（伞下团队）
     * @param int $member_id 用户ID
     * @param int $level_child_level 用户级别
     * @return int
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
	static function getTeamRecommandMember($member_id, $level_child_level) {
		$result = MemberBind::alias('a')->join(config("database.prefix") . "chia_mining_member b", 'a.child_id = b.member_id')->where(['a.member_id' => $member_id, 'b.level' => $level_child_level])->group('b.member_id')->count('b.member_id');
		return $result;
	}

	/**
     * 更改团队中的最大等级
     * @param int $child_member_id 用户ID
     * @param int $level 用户级别
     * @return int
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function updateTeamMaxLevel($child_member_id, $level) {
        return self::execute('update '.config("database.prefix").'chia_mining_member a,'.config("database.prefix").'member_bind b
            set a.team_max_level='.$level.' where a.member_id = b.member_id and a.team_max_level<'.$level.' and  (b.child_id='.$child_member_id.' or a.member_id='.$child_member_id.');');
    }
 
    /**
     * 添加Chia矿机用户汇总
     * @param int $member_id 用户ID
     * @param int $pay_tnum 购买T数
     * @return int
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function addItem($member_id,$pay_tnum=0) {
        try{
            $info = self::where(['member_id'=>$member_id])->find();
            if($info) {
                $flag = true;
                if ($pay_tnum > 0) {
                    $flag = self::where(['member_id'=>$member_id])->update([
                        'pay_tnum' => ['inc',$pay_tnum],
                    ]);
                }
            } else {
                $flag = self::insertGetId([
                    'member_id' => $member_id,
                    'pay_tnum' => $pay_tnum,
                    'add_time' => time(),
                ]);
            }

            return $flag;
        } catch (Exception $e) {
            return false;
        }
    }

    //更新用户汇总信息
    static function updateMember($member_id) {
    	$valid_count = self::getTeamValidCount($member_id) ?: 0;
	    $arr = self::getLevelArr($member_id);
	    $data = ['valid_num' => $valid_count,];
    	$data = array_filter(array_merge($data, $arr));
    	return self::where(['member_id' => $member_id])->update($data);
    }

    //获取级别数组
    static function getLevelArr($member_id) {
    	$first_team_num = self::getTeamRecommandMember($member_id, 1);
    	$second_team_num = self::getTeamRecommandMember($member_id, 2);
    	$third_team_num = self::getTeamRecommandMember($member_id, 3);
    	$fourth_team_num = self::getTeamRecommandMember($member_id, 4);
    	$fifth_team_num = self::getTeamRecommandMember($member_id, 5);

    	$result = [
    		'first_team_num' => $first_team_num,
    		'second_team_num' => $second_team_num,
    		'third_team_num' => $third_team_num,
    		'fourth_team_num' => $fourth_team_num,
    		'fifth_team_num' => $fifth_team_num,
    	];
    	return $result;
    }

    /**
     * 市场日新增业绩
     * @param int $member_id 用户ID
     * @param date $yestday 日期
     * @param array $mining_archive 数据
     * @param int $currency_id 币种
     * @return bool
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function addAchievement($member_id, $yestday, $mining_archive, $currency_id) {
    	$res = self::alias('a')->join(config("database.prefix") . 'member_bind b', 'a.member_id=b.member_id')->field('a.member_id,a.level')->where(['b.child_id' => $member_id])->order('b.level ASC')->select();
    	if (!$res) return true;

    	$first = ChiaMiningLevelConfig::order('level_id DESC')->value('level_percent');//最大级差
    	$second = 0;//平级
    	$third = 0;//级别
    	$fourth = 0;//已存在的级差
    	foreach ($res as $key => $value) {
    		try {
    			$currency_user = CurrencyUser::getCurrencyUser($value['member_id'], $currency_id);
        		if (empty($currency_user)) return false;

	            Db::startTrans();
	    		$income = 0;
	    		if ($value['level'] > $third && $first > 0) {//级差
	    			$level_percent = ChiaMiningLevelConfig::where(['level_id' => $value['level']])->value('level_percent');
	    			if ($fourth > 0) {
	    				$rate = $level_percent - $fourth;
	    			} else {
	    				$rate = $level_percent;
	    			}
	    			$income = sprintf('%.6f', $mining_archive['real_pay_num'] * ($rate / 100));
	    			// 添加每日汇总
	    			ChiaMiningMemberSummary::addSummary($value['member_id'], $yestday, $value['level'], $income);

	    			$third = $value['level'];
	    			$first = $first - $rate;
	    			$second = ChiaMiningLevelConfig::value('level_global');
	    			$fourth = $fourth + $rate;

	    			// 添加级差记录
	    			$item_id = ChiaMiningLevelIncomeDetail::addIncome($value['member_id'], $yestday, $currency_user['currency_id'], $value['level'], 1, $income, $level_percent, $mining_archive['real_pay_num'], $mining_archive['id'], $mining_archive['member_id']);
	    			//账本类型
	    			$account_book_id = 7001;
	    			$account_book_content = 'chia_mining_income1';

	    		} elseif ($value['level'] == $third && $second > 0) {//平级
	    			$level_global = ChiaMiningLevelConfig::where(['level_id' => $value['level']])->value('level_global');
	    			$income = sprintf('%.6f', $mining_archive['real_pay_num'] * ($level_global / 100));
	    			// 添加每日汇总
	    			ChiaMiningMemberSummary::addSummary($value['member_id'], $yestday, $value['level'], 0, $income);

	    			$third = $value['level'];
	    			$second = 0;

	    			// 添加级差记录
	    			$item_id = ChiaMiningLevelIncomeDetail::addIncome($value['member_id'], $yestday, $currency_user['currency_id'], $value['level'], 2, $income, $level_global, $mining_archive['real_pay_num'], $mining_archive['id'], $mining_archive['member_id']);
	    			//账本类型
	    			$account_book_id = 7002;
	    			$account_book_content = 'chia_mining_income2';
	    		}
	    		if (!empty($income) && !empty($item_id)) {
	    			//增加账本 增加资产
	                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $income, $item_id);
	                if (!$flag) throw new Exception("添加账本失败");

	                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $income);
	                if (!$flag) throw new Exception("添加资产失败");

	                self::where(['member_id' => $value['member_id']])->setInc('personal_num', $income);
	    		}
		    	Db::commit();

	        } catch (Exception $e) {
	            Db::rollback();
	            Log::write("Chia矿机:失败" . $e->getMessage());
	        }
	    }

	    // 添加每日汇总
	    $level = self::where(['member_id' => $member_id])->value('level');
	    ChiaMiningMemberSummary::addSummary($member_id, $yestday, $level);
	    
	    return true;
    }

    /**
     * 市场日新增挖矿
     * @param int $member_id 用户ID
     * @param date $today 日期
     * @param array $mining_archive 数据
     * @param int $currency_id 币种
     * @return bool
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function addXch($member_id, $yestday, $mining_archive, $currency_id) {
    	$res = self::alias('a')->join(config("database.prefix") . 'member_bind b', 'a.member_id=b.member_id')->field('a.member_id,a.level')->where(['b.child_id' => $member_id])->order('b.level ASC')->select();
    	if (!$res) return true;
    	$first = ChiaMiningLevelConfig::order('level_id DESC')->value('level_xch_percent');//最大级差
    	$second = 0;//平级
    	$third = 0;//级别
    	$fourth = 0;//已存在的级差
    	foreach ($res as $key => $value) {
    		try {
    			$currency_user = CurrencyUser::getCurrencyUser($value['member_id'], $currency_id);
        		if (empty($currency_user)) return false;

	            Db::startTrans();
	    		$income = 0;
	    		if ($value['level'] > $third && $first > 0) {//级差
	    			$level_percent = ChiaMiningLevelConfig::where(['level_id' => $value['level']])->value('level_xch_percent');
	    			if ($fourth > 0) {
	    				$rate = $level_percent - $fourth;
	    			} else {
	    				$rate = $level_percent;
	    			}
	    			$income = sprintf('%.6f', $mining_archive['num'] * ($rate / 100));
	    			// 添加每日汇总
	    			ChiaMiningMemberSummary::addSummary($value['member_id'], $yestday, $value['level'], 0, 0, $income);

	    			$third = $value['level'];
	    			$first = $first - $rate;
	    			$second = ChiaMiningLevelConfig::value('level_global');
	    			$fourth = $fourth + $rate;

	    			// 添加级差记录
	    			$item_id = ChiaMiningLevelIncomeDetail::addIncome($value['member_id'], $yestday, $currency_user['currency_id'], $value['level'], 3, $income, $level_percent, $mining_archive['num'], $mining_archive['id'], $mining_archive['member_id']);
	    			//账本类型
	    			$account_book_id = 7003;
	    			$account_book_content = 'chia_mining_income3';

	    		} elseif ($value['level'] == $third && $second > 0) {//平级
	    			$level_global = ChiaMiningLevelConfig::where(['level_id' => $value['level']])->value('level_global');
	    			$income = sprintf('%.6f', $mining_archive['num'] * ($level_global / 100));
	    			// 添加每日汇总
	    			ChiaMiningMemberSummary::addSummary($value['member_id'], $yestday, $value['level'], 0, 0, 0,$income);

	    			$third = $value['level'];
	    			$second = 0;

	    			// 添加级差记录
	    			$item_id = ChiaMiningLevelIncomeDetail::addIncome($value['member_id'], $yestday, $currency_user['currency_id'], $value['level'], 4, $income, $level_global, $mining_archive['num'], $mining_archive['id'], $mining_archive['member_id']);
	    			//账本类型
	    			$account_book_id = 7004;
	    			$account_book_content = 'chia_mining_income4';
	    		}
	    		if (!empty($income) && !empty($item_id)) {
	    			//增加账本 增加资产
	                $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $income, $item_id);
	                if (!$flag) throw new Exception("添加账本失败");

	                $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $income);
	                if (!$flag) throw new Exception("添加资产失败");

	                self::where(['member_id' => $value['member_id']])->setInc('release_num', $income);
	    		}
		    	Db::commit();

	        } catch (Exception $e) {
	            Db::rollback();
	            Log::write("Chia矿机:失败" . $e->getMessage());
	        }
	    }

	    // 添加每日汇总
	    $level = self::where(['member_id' => $member_id])->value('level');
	    ChiaMiningMemberSummary::addSummary($member_id, $yestday, $level);

	    return true;
    }

    /**
     * 增加团队业绩
     * @param int $child_member_id 用户ID
     * @param int $num 业绩
     * @return int
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function addParentTeam($child_member_id, $num=0)
    {
    	return self::execute('update ' . config("database.prefix") . 'chia_mining_member a,' . config("database.prefix") . 'member_bind b
            set a.team_total=a.team_total+' .$num. ' where a.member_id = b.member_id and  b.child_id=' . $child_member_id . ';');
    }

    /**
     * 增加直推业绩
     * @param int $child_member_id 用户ID
     * @param int $tnum T数
     * @param int $num 业绩
     * @return int
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function addOneTeam($child_member_id, $tnum=0, $num=0)
    {
        return self::execute('update ' . config("database.prefix") . 'chia_mining_member a,' . config("database.prefix") . 'member_bind b
            set a.one_team_tnum=a.one_team_tnum+' . $tnum . ', a.one_team_total = a.one_team_total+' .$num. ' where a.member_id = b.member_id and b.level=1 and b.child_id=' . $child_member_id . ';');
    }

    /**
     * 增加团队挖矿收益
     * @param int $child_member_id 用户ID
     * @param int $num 购买数量
     * @return int
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function addReleaseTeam($child_member_id, $num)
    {
        return self::execute('update ' . config("database.prefix") . 'chia_mining_member a,' . config("database.prefix") . 'member_bind b
            set a.team_release_num=a.team_release_num+' . $num . ' where a.member_id = b.member_id and b.child_id=' . $child_member_id . ';');
    }

    /**
     * 奖池收益列表
     * @param int $member_id 用户ID
     * @return array
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function award_list($member_id) {
        $r = ['code' => ERROR1, 'message' => lang("operation_failed_try_again"), 'result' => null];
        $result = self::field('id,member_id,level,pay_tnum,valid_num,team_tnum,first_team_num,second_team_num,third_team_num,fourth_team_num,fifth_team_num,personal_num,release_num,team_num,team_release_num')->where(['member_id' => $member_id])->find();
        if (!empty($result)) {
            $settlementInfo = \app\common\model\ChiaMiningMemberSettlement::field('team_num,team_release_num,today')->where(['member_id' => $member_id])->order('today DESC')->find();
            $team_num = $team_release_num = 0;
            if ($settlementInfo) {
                $summaryInfo = \app\common\model\ChiaMiningMemberSummary::field('sum(team1_num) as team1_num,sum(team2_num) as team2_num,sum(team3_num) as team3_num,sum(team4_num) as team4_num')->where(['member_id' => $member_id, 'today' => ['gt', $settlementInfo['today']]])->find();
                if ($summaryInfo) {
                    $team_num = sprintf('%.6f', $summaryInfo['team1_num'] + $summaryInfo['team2_num']);
                    $team_release_num = sprintf('%.6f', $summaryInfo['team3_num'] + $summaryInfo['team4_num']);
                }
                $result['total_info'] = ['num' => $result['team_num'], 'release_num' => $result['team_release_num']];
                $result['release_info'] = ['num' => $team_num, 'release_num' => $team_release_num];
                $today = date('m月d日', strtotime($settlementInfo['today']));
            } else {
                $summaryInfo = \app\common\model\ChiaMiningMemberSummary::field('sum(team1_num) as team1_num,sum(team2_num) as team2_num,sum(team3_num) as team3_num,sum(team4_num) as team4_num')->where(['member_id' => $member_id])->find();
                if ($summaryInfo) {
                    $team_num = sprintf('%.6f', $summaryInfo['team1_num'] + $summaryInfo['team2_num']);
                    $team_release_num = sprintf('%.6f', $summaryInfo['team3_num'] + $summaryInfo['team4_num']);
                }
                $result['total_info'] = ['num' => $result['team_num'], 'release_num' => $result['team_release_num']];
                $result['release_info'] = ['num' => $team_num, 'release_num' => $team_release_num];
                $today = date('m月d日', time());
            }
            
            $result['level_info'] = self::getLevelInfo($result);
            $result['today'] = $today;
            unset($result['team_num'], $result['team_release_num']);
        } else {
            $result = self::getmemberInfo($member_id);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        $r['result'] = $result;
        return $r;
    }

    // 初始化用户汇总
    static function getmemberInfo($member_id) {
        $result['member_id'] = $member_id;
        $result['level'] = 0;
        $result['personal_num'] = 0;
        $result['release_num'] = 0;
        $result['total_info'] = ['num' => 0, 'release_num' => 0];
        $result['release_info'] = ['num' => 0, 'release_num' => 0];
        $result['level_info'] = ['level_child_recommand' => 5, 'level_self_num' => 1, 'level_team_num' => 50, 'finish_recommand' => 0, 'finish_self_num' => 0, 'finish_team_num' => 0, 'term_text' => '直推有效5账户'];
        $result['today'] = date('m月d日', time());
        return $result;
    }

    //获取级别信息
    static function getLevelInfo($data) {
    	$result = [];
    	$level = $data['level'] + 1;
    	switch ($data['level']) {
    		case '0':
    			$result = ChiaMiningLevelConfig::field('level_self_num,level_team_num,level_child_level_count')->where(['level_id' => $data['level'] + 1])->find();
    			$result['level_child_recommand'] = $result['level_child_level_count'];
    			$result['finish_recommand'] = $data['valid_num'];
    			$result['finish_self_num'] = $data['pay_tnum'];
    			$result['finish_team_num'] = $data['team_tnum'];
    			$result['term_text'] = '直推有效'.$result['level_child_level_count'].'账户';
    			break;
    		case '1':
    			$result = ChiaMiningLevelConfig::field('level_child_recommand,level_self_num,level_child_level')->where(['level_id' => $level])->find();
    			$result['finish_recommand'] = $data['first_team_num'];
    			$result['finish_self_num'] = $data['pay_tnum'];
    			$result['term_text'] = '伞下'.$result['level_child_recommand'].'个团队各有1个X'.$result['level_child_level'];
    			break;
    		case '2':
    			$result = ChiaMiningLevelConfig::field('level_child_recommand,level_self_num,level_child_level')->where(['level_id' => $level])->find();
    			$result['finish_recommand'] = $data['second_team_num'];
    			$result['finish_self_num'] = $data['pay_tnum'];
    			$result['term_text'] = '伞下'.$result['level_child_recommand'].'个团队各有1个X'.$result['level_child_level'];
    			break;
    		case '3':
    			$result = ChiaMiningLevelConfig::field('level_child_recommand,level_self_num,level_child_level')->where(['level_id' => $level])->find();
    			$result['finish_recommand'] = $data['third_team_num'];
    			$result['finish_self_num'] = $data['pay_tnum'];
    			$result['term_text'] = '伞下'.$result['level_child_recommand'].'个团队各有1个X'.$result['level_child_level'];
    			break;
    		case '4':
    			$result = ChiaMiningLevelConfig::field('level_child_recommand,level_self_num,level_child_level')->where(['level_id' => $level])->find();
    			$result['finish_recommand'] = $data['fourth_team_num'];
    			$result['finish_self_num'] = $data['pay_tnum'];
    			$result['term_text'] = '伞下'.$result['level_child_recommand'].'个团队各有1个X'.$result['level_child_level'];
    			break;
    		case '5':
    			$result = ChiaMiningLevelConfig::field('level_child_recommand,level_self_num,level_child_level')->where(['level_id' => $data['level']])->find();
    			$result['finish_recommand'] = $data['fifth_team_num'];
    			$result['finish_self_num'] = $data['pay_tnum'];
    			$result['term_text'] = '伞下'.$result['level_child_recommand'].'个团队各有1个X'.$result['level_child_level'];
    			break;
    	}

    	return $result;
    }

    public function users() {
        return $this->belongsTo('app\\common\\model\\Member', 'member_id', 'member_id')->field('member_id,email,phone,nick,name,ename');
    }

    /**
     * 更新矿工等级
     * @param int $member_id 用户ID
     * @param int $child_member_id 直推ID
     * @param int $level 等级
     * @return array
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function updateMemberLevel($member_id, $child_member_id, $level) {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];
        if (!$level) {
            $r['message'] = '请选择晋级级别';
            return $r;
        }
        $res = self::where(['member_id' => $child_member_id, 'is_edit_level' => 1])->find();
        if (!$res) return $r;
        try {
            self::startTrans();
            self::where(['member_id' => $child_member_id])->update(['level' => $level, 'is_edit_level' => 0]);
            
            $remarks = 'app更新等级，从' . $res['level'] . '到' . $level;
            // 增加升级记录
            Db::name('chia_mining_level_log')->insert([
                'third_id' => $child_member_id,
                'level' => $level,
                'remarks' => $remarks,
                'add_time' => time(),
            ]);

            $check = self::alias('a')->join('member_bind b', 'a.member_id=b.child_id')->where(['b.member_id' => $member_id, 'b.level' => 1, 'a.is_edit_level' => 1])->value('a.id');
            $level_open = self::where(['member_id' => $member_id])->value('level_open');
            if (empty($check) && $level_open == 1) {//关闭开关
                self::where(['member_id' => $member_id])->update(['level_open' => 0]);
            }

            self::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = '';
        } catch (Exception $e) {
            self::rollback();
            $r['message'] = $e->getMessage() . $e->getLine();
        }
        return $r;
    }

    /**
     * 开启（关闭）直推修改矿工级别
     * @param int $member_id 用户ID
     * @param int $level_open 1开启 0关闭
     * @return array
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function openMemberLevel($member_id, $level_open) {
        return self::execute('update ' . config("database.prefix") . 'chia_mining_member a,' . config("database.prefix") . 'member_bind b
            set a.is_edit_level=' . $level_open . ' where a.member_id = b.child_id and b.level=1 and b.member_id=' . $member_id . ';');
    }

    /**
     * 提成计算
     * @param int $member_id 用户ID
     * @param date $today 日期
     * @param array $mining_archive 数据
     * @param int $currency_id 币种
     * @return bool
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     */
    static function addCommission($member_id, $yestday, $mining_archive, $mining_config) {
        $res = self::alias('a')->join(config("database.prefix") . 'member_bind b', 'a.member_id=b.member_id')->field('a.member_id,a.level')->where(['b.child_id' => $member_id])->order('b.level ASC')->select();
        if (!$res) return true;

        foreach ($res as $key => $value) {
            try {
                $rate = \app\common\model\ChiaMiningCommission::where(['member_id' => $value['member_id'], 'child_id' => $member_id])->value('rate');//提成率
                //不存在提成配置就跳过
                if (empty($rate) && $mining_config['commission_rate'] == 0) {
                    continue;
                }

                $currency_user = CurrencyUser::getCurrencyUser($value['member_id'], $mining_config['release_currency_id']);
                if (empty($currency_user)) return false;

                Db::startTrans();
                $income = sprintf('%.6f', $mining_archive['num'] * ($rate / 100));

                if (!empty($income)) {
                    // 添加级差记录
                    $item_id = \app\common\model\ChiaMiningIncome::addIncome($value['member_id'], $yestday, $mining_config['release_currency_id'], 1, $income, $rate, $mining_archive['num'], $mining_archive['id'], $mining_archive['member_id']);
                    //账本类型
                    $account_book_id = 7007;
                    $account_book_content = 'chia_mining_income';

                    //增加账本 增加资产
                    $flag = AccountBook::add_accountbook($currency_user['member_id'], $currency_user['currency_id'], $account_book_id, $account_book_content, 'in', $income, $item_id);
                    if (!$flag) throw new Exception("添加账本失败");

                    $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num' => $currency_user['num']])->setInc('num', $income);
                    if (!$flag) throw new Exception("添加资产失败");

                    self::where(['member_id' => $value['member_id']])->setInc('reward', $income);
                }
                Db::commit();

            } catch (Exception $e) {
                Db::rollback();
                Log::write("Chia矿机:失败" . $e->getMessage());
            }
        }

        return true;
    }
}