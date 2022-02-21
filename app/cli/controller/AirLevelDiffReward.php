<?php


namespace app\cli\controller;


use app\common\model\AccountBook;
use app\common\model\AccountBookType;
use app\common\model\AirIncomeLog;
use app\common\model\AirLadderLevel;
use app\common\model\AirTeamCliLog;
use app\common\model\Config;
use app\common\model\CurrencyUser;
use app\common\model\HongbaoKeepLog;
use app\common\model\MemberBind;
use app\common\model\UserAirDiff;
use app\common\model\UserAirDiffDay;
use app\common\model\UserAirLevel;
use app\common\model\UserAirRecommend;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Request;

/**
 * Class AirLevelDiffReward
 * @package app\cli\controller
 */
class AirLevelDiffReward extends Command
{
    /**
     * @var string
     */
    public $name = '云梯级差奖定时任务';

    /**
     * @var AirIncomeLog
     */
    protected $airIncomeLog;

    /**
     * @var MemberBind
     */
    protected $memberBind;

    /**
     * @var UserAirRecommend
     */
    protected $userAirRecommend;

    /**
     * @var UserAirLevel
     */
    protected $userAirLevel;

    protected $airLadderLevel;

    /**
     * AirLevelDiffReward constructor.
     * @param null $name
     */
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->airIncomeLog = new AirIncomeLog();
        $this->memberBind = new MemberBind();
        $this->userAirRecommend = new UserAirRecommend();
        $this->userAirLevel = new UserAirLevel();
        $this->airLadderLevel = new AirLadderLevel();

    }


    protected function configure()
    {
        $this->setName('AirLevelDiffReward')->setDescription('Air level diff reward command');
    }


    /**
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function execute(Input $input, Output $output)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);
        Request::instance()->module('cli');

        $this->doRun();
        $this->teamIncome();
    }

    /**
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function doRun()
    {
        $logs = $this->airIncomeLog->whereTime('add_time', 'yesterday')->order('add_time', 'desc')->select();
        Log::write("云梯级差奖定时任务：开始" . date("Y-m-d H:i:s"), "INFO");
        $airLevelDiffMaxRadio = $this->airLadderLevel->max('reward_radio');
        if (!empty($logs)) {
            foreach ($logs as $log) {
                try {
                    Db::startTrans();
                    $parents = $this->memberBind->where('child_id', $log['user_id'])->order('level', 'asc')->select();
                    if (empty($parents)) { // 冇得上级
                        continue;
                    }

                    $levelDiffMaxRadio = $airLevelDiffMaxRadio; // 级差奖最多分配80%
                    $recommend = $this->userAirRecommend->where('income_id', $log['id'])->find(); // 查询是否有直推奖

                    if (empty($recommend)) {
                        $recommendLevel = null;
                    } else {
                        $recommendLevel = $this->userAirLevel->getAirInfo($recommend['user_id']);
                    }

                    $recommendRadio = empty($recommend) ? 0 : $recommend['award_radio'];// 开始的奖励比例 直推奖存在就用奖励比例 否则就是 0
                    if ($recommend['award_number'] <= 0 ) { // 20-05-27: 没有拿到直推奖 减去当前等级奖励比例
                        $recommendRadio = $recommendLevel['level']['reward_radio'];
                    }
                    if ($recommendRadio >= $levelDiffMaxRadio) { // 直推拿走所有奖励
                        continue;
                    }
                    $levelDiffMaxRadio -= $recommendRadio; // 50 >= 80 - 50 = 30
                    $isSubRecommend = true; // 减直推比例还是上次的比例
                    $lastLevelRadio = null;//empty($recommend) ? null : $recommend['award_radio'];  // 上次的比例

                    $lastLevelId = empty($recommend) ? 1 : $recommendLevel['level_id']; // 上次拿到奖励的等级
                    foreach ($parents as $parent) {
                        if ($parent['level'] <= 1) { // 直推拿不到级差
                            continue;
                        }

                        if (!empty($recommend) and $parent['member_id'] == $recommend['user_id']) {// 拿了直推奖的上级无级差奖
                            continue;
                        }

                        //  or $recommendRadio >= $levelDiffMaxRadio
                        if ($levelDiffMaxRadio <= 0) { // 级差奖奖励分配完毕
                            break;
                        }

                        $userAIrInfo = $this->userAirLevel->getAirInfo($parent['member_id']);
                        if (empty($userAIrInfo) or 1 == $userAIrInfo['level_id'] or $userAIrInfo['level_id'] < $lastLevelId) { // 没有玩云梯 或者是 D0
                            continue;
                        }

                        $isReleased = UserAirDiff::where('income_id', $log['id'])->where('reward_user_id', $parent['member_id'])->find();
                        if (!empty($isReleased)) { // 这条入金记录已经释放给该用户
                            continue;
                        }

                        // 级差奖比例
                        if ($isSubRecommend) {
                            $rewardRadio = $userAIrInfo['level']['reward_radio'] - $recommendRadio;
                        } else {
                            $rewardRadio = $userAIrInfo['level']['reward_radio'] - $lastLevelRadio;
                        }

                        if ($rewardRadio >= $levelDiffMaxRadio) {
                            $rewardRadio = $levelDiffMaxRadio;
                        }

                        if ($rewardRadio > 0) { // 20-05-27: 满足级差奖励条件就更新 最后一次奖励数据 （不管是否已经释放XRP+成功）
                            $levelDiffMaxRadio -= $rewardRadio;
                            $isSubRecommend = false;
                            $lastLevelRadio = $userAIrInfo['level']['reward_radio']; // 上次拿到奖励的比例
                            $lastLevelId = $userAIrInfo['level_id']; // 上次拿到奖励的等级ID
                        }

                        $currencyUser = CurrencyUser::getCurrencyUser($parent['member_id'], $log['currency_id']);
                        if ($currencyUser['keep_num'] < 0) { // 没有可释放额度
                            continue;
                        }

                        // 释放xrp+ 有烧伤
                        //  + $userAIrInfo['team_income']
                        $number = min($userAIrInfo['income'] + $userAIrInfo['team_income'], $log['number']);
                        $awardNumber = keepPoint($number * ($rewardRadio * .01), 6);
                        // 手续费
//                        $fee = keepPoint($awardNumber * ($feeRadio * .01));
                        $releaseNumber = $awardNumber;

                        if (bccomp($currencyUser['keep_num'], $releaseNumber, 6) == -1) { // keep_num == release or keep_num < release
                            $releaseNumber = $currencyUser['keep_num']; // 实得奖励
                        }

                        if ($releaseNumber <= 0) { // 释放数量为0
                            continue;
                        }

                        $feeRadio = Config::get_value('air_diff_fee'); // 级差手续费
                        $fee = keepPoint($releaseNumber * ($feeRadio * .01));
                        $realNumber = $releaseNumber - $fee;

                        $dayDiffLogId = UserAirDiffDay::add_log(
                            $parent['member_id'],
                            $log['currency_id'],
                            $realNumber,
                            $fee);
                        if (empty($dayDiffLogId) or $dayDiffLogId == false) {
                            throw new Exception("系统错误，添加级差奖每日日志信息失败！");
                        }

                        $diffLogId = UserAirDiff::add_log(
                            $log['currency_id'],
                            $log['id'],
                            $parent['member_id'],
                            $log['user_id'],
                            $userAIrInfo['level_id'],
                            $realNumber,
                            $fee,
                            $rewardRadio,
                            $dayDiffLogId);
                        if (empty($diffLogId)) {
                            throw new Exception("系统错误，添加级差奖日志信息失败！");
                        }

                        // 扣除手续费
                        /*$flag = HongbaoKeepLog::add_log('air_fee', $parent['member_id'], $log['currency_id'], $fee, $diffLogId);
                        if (empty($flag)) {
                            throw new Exception("系统错误：HongbaoKeepLog add_log error");
                        }
                        $currencyUser['keep_num'] -= $fee;*/
                        // $releaseNumber -= $fee;
                        /*if (bccomp($currencyUser['keep_num'], $releaseNumber, 6) == -1) { // keep_num == release or keep_num < release
                            $releaseNumber = $currencyUser['keep_num']; // 实得奖励
                        }*/

                        $flag = HongbaoKeepLog::add_log('air_release', $parent['member_id'], $log['currency_id'], $releaseNumber, $diffLogId);
                        if (empty($flag)) {
                            throw new Exception("系统错误：HongbaoKeepLog add_log error");
                        }

                        $currencyUser['keep_num'] -= $releaseNumber;
                        // 释放数量增加到可用数量
                        $accountBookFlag = AccountBook::add_accountbook($parent['member_id'], $log['currency_id'], AccountBookType::AIR_DIFF_REWARD, 'air_ladder_release', 'in', $realNumber, $diffLogId, $fee, $log['currency_id']);
                        if (empty($accountBookFlag)) {
                            throw new Exception("系统错误：添加账本信息失败！");
                        }

                        $currencyUser['num'] += $realNumber;
                        if (!$currencyUser->save()) {
                            throw new Exception("系统错误：保存用户资产数据失败!");
                        }

                        $userAIrInfo['level_diff_reward'] += $realNumber;
                        if (!$userAIrInfo->save()) {
                            throw new Exception("系统错误：保存用户云梯信息失败!");
                        }

//                        $levelDiffMaxRadio -= $rewardRadio;
//                        $isSubRecommend = false;
//                        $lastLevelRadio = $userAIrInfo['level']['reward_radio']; // 上次拿到奖励的比例
//                        $lastLevelId = $userAIrInfo['level_id']; // 上次拿到奖励的等级ID
                    }

                    Db::commit();
                } catch (\Exception $exception) {
                    Db::rollback();
                    Log::write("云梯级差奖定时任务：异常：" . $exception->getMessage() . date("Y-m-d H:i:s"), "INFO");
                    echo $exception->getMessage() . " FILE: " . $exception->getFile() . " LINE: " . $exception->getLine() . "\n";
                }
            }
        }
        Log::write("云梯级差奖定时任务：完成" . date("Y-m-d H:i:s"), "INFO");
    }


    /**
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    protected function teamIncome()
    {
        // 查询昨日入金数据
        $logs = $this->airIncomeLog->whereTime('add_time', 'yesterday')->order('add_time', 'desc')->select();
        Log::write("团队入金等级处理定时任务：开始" . date("Y-m-d H:i:s"), "INFO");
        if (!empty($logs)) {
            foreach ($logs as $log) {
                $parents = $this->memberBind->where('child_id', $log['user_id'])->select();
                if (empty($parents)) {
                    continue;
                }

                // 查询云梯信息
                foreach ($parents as $parent) {
                    try {
                        Db::startTrans();
                        $airInfo = $this->userAirLevel->getAirInfo($parent['member_id'], true);
//                        if (empty($airInfo)) {
//                            continue;
//                        }
                        // 查询是否已经添加过
                        $flag = (new AirTeamCliLog)->where('income_id', $log['id'])->where('user_id', $parent['member_id'])->value('id');
                        if (!empty($flag)) { // 已经增加过团队入金数量
                            Db::commit();
                            continue;
                        }
                        // 查询是否满足升级要求
                        if ($airInfo['income'] > 0 and UserAirLevel::ACTIVATED == $airInfo['is_activate'] and $airInfo['level_id'] > 1) {
                            $level = $this->airLadderLevel->getLevelByNumber($airInfo['income'] + $airInfo['team_income'] + $log['number']);
                        } else {
                            $level['id'] = $airInfo['level_id'];
                        }
                        $oldLevelId = $airInfo['level_id'];
                        $isLevelUp = 1;
                        if ($level['id'] > $airInfo['level_id']) {
                            // 升级
                            $airInfo['level_id'] = $level['id'];
                            $airInfo['up_time'] = time();
                            $isLevelUp = 2;
                            $flag = $this->userAirLevel->dealConvertNumber($airInfo['id'], $oldLevelId, $level['id']);
                            if (false === $flag) {
                                throw new Exception("系统错误，累加共振额度失败!");
                            }
                        }
                        $oldNumber = $airInfo['team_income'];
                        $airInfo['team_income'] += $log['number'];

                        if (!$airInfo->save()) {
                            throw new Exception("系统错误，保存用户信息失败!");
                        }

                        AirTeamCliLog::create([
                            'income_id' => $log['id'],
                            'user_id' => $parent['member_id'],
                            'child_id' => $log['user_id'],
                            'number' => $log['number'],
                            'old_number' => $oldNumber,
                            'is_level_up' => $isLevelUp,
                            'add_time' => time()
                        ]);
                        Db::commit();
                    } catch (\Exception $exception) {
                        Db::rollback();
                        Log::write("团队入金等级处理定时任务：异常：" . $exception->getMessage() . ' Date: ' . date("Y-m-d H:i:s"), 'INFO');
                    }

                }
            }
        }


        Log::write("团队入金等级处理定时任务：完成" . date("Y-m-d H:i:s"), "INFO");
    }

}