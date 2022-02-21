<?php


namespace app\cli\controller;


use app\common\model\AccountBook;
use app\common\model\AccountBookType;
use app\common\model\AirIncomeLog;
use app\common\model\AirJackpotLog;
use app\common\model\AirLadderLevel;
use app\common\model\Config;
use app\common\model\Currency;
use app\common\model\CurrencyUser;
use app\common\model\HongbaoKeepLog;
use app\common\model\MemberBind;
use app\common\model\UserAirJackpot;
use app\common\model\UserAirLevel;
use Redis;
use think\Cache;
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
 * Class AirJackpotReward
 * @package app\cli\controller
 */
class AirJackpotReward extends Command
{
    /**
     * @var string
     */
    public $name = '云梯周分红定时任务';

    /**
     * @var UserAirLevel
     */
    protected $userAirLevel;

    /**
     * @var AirIncomeLog
     */
    protected $airIncomeLog;

    /**
     * @var MemberBind
     */
    protected $memberBind;

    /**
     * @var UserAirJackpot
     */
    protected $userAirJackpot;

    /**
     * AirJackpotReward constructor.
     * @param null $name
     */
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->userAirLevel = new UserAirLevel();
        $this->airIncomeLog = new AirIncomeLog();
        $this->memberBind = new MemberBind();
        $this->userAirJackpot = new UserAirJackpot();
    }


    protected function configure()
    {
        $this->setName('AirJackpotReward')->setDescription('Air jackpot reward command');
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
    }

    /**
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function doRun()
    {
        // 查询等级大于D6的用户
        Log::write("云梯周分红定时任务：开始" . date("Y-m-d H:i:s"), "INFO");
        // 查询今日入金数量
        $todayIncomeNumber = $this->airIncomeLog->where('add_time', 'between', [yesterdayBeginTimestamp(), yesterdayEndTimestamp()])->sum('number');
        if ($todayIncomeNumber <= 0) {
            Log::write("云梯周分红定时任务：昨日无入金金额.任务结束" . date("Y-m-d H:i:s"), "INFO");
            die();
        }
        $userAirs = $this->userAirLevel->where('level_id', 'gt', 7)->select(); // 高于D6的用户
        if (empty($userAirs)) {
            Log::write("云梯周分红定时任务：昨日暂无用户达到D7或以上级别。任务结束" . date("Y-m-d H:i:s"), "INFO");
            die();
        }

        // 初始化奖励队列
        $rewardUser = ["D7" => [], "D8" => [], "D9" => [], "D10" => []];
        /**
         * @var Redis $handler
         */
        $handler = Cache::store('redis')->handler();
        $today = date("Ymd");
        $key = "{$today}:level"; // $key => Ymd:level
        foreach ($userAirs as $air) {
            // 查询部门
            $departments = $this->memberBind->where('member_id', $air['user_id'])->where('level', 1)->select(); // 直推部门
            if (count($departments) < 3) { // 不满足三个部门
                continue;
            }

            // 查询部门下是否有满足要求的下级
            $requireNumber = 0; // 满足要求的部门数量
            foreach ($departments as $department) {
                // 直推部门人是否满足条件
                $departmentAir = $this->userAirLevel->getAirInfo($department['child_id']);

                if ($this->isRequirement($air['level_id'], $departmentAir['level_id']) and $this->levelIsRequirement($departmentAir['level_id'], $departmentAir['income'] + $departmentAir['team_income'])) {
                    $this->hastSet($handler, $key, $air['user_id'], $department['child_id'], $departmentAir['level']['name']);
                    $requireNumber++;
                    continue;
                }

                $departmentChild = $this->memberBind->where('member_id', $department['child_id'])->select();
                foreach ($departmentChild as $child) {
                    // 下级的云梯信息
                    $childAir = $this->userAirLevel->getAirInfo($child['child_id']);
                    if ($this->isRequirement($air['level_id'], $childAir['level_id']) and $this->levelIsRequirement($childAir['level_id'], $childAir['income'] + $childAir['team_income'])) {
                        $this->hastSet($handler, $key, $air['user_id'], $department['child_id'], $childAir['level']['name']);
                        $requireNumber++;
                        break;
                    }
                }
            }

            if ($requireNumber >= 3) {
                // 满足要求
                $keyNumber = $air['level_id'] - 1;
                if (!isset($rewardUser["D{$keyNumber}"])) { // PHP7 warning
                    $rewardUser["D{$keyNumber}"] = [];
                }
                array_push($rewardUser["D{$keyNumber}"], $air['user_id']);
                // break;
            }
        }
        foreach ($rewardUser as $key => $value) {
            if (count($value) <= 0) {
                continue;
            }
            // 计算奖励数量
            $levelRadio = (double)Config::get_value("air_{$key}_jackpot_radio", 0);
            $persons = count($value);
            $number = keepPoint(($todayIncomeNumber * ($levelRadio * .01)) / $persons, 6);
            if ($number <= 0) {
                continue;
            }
            // 添加log记录
            $logId = AirJackpotLog::add_log($key, $levelRadio, $number, $persons);
            if (empty($logId)) {
                Log::write("云梯周分红定时任务：系统错误，添加分红奖统计数据失败." . date("Y-m-d H:i:s"), "INFO");
            }
            foreach ($value as $userId) {
                $flag = $this->giveJackpotReward($userId, Currency::XRP_PLUS_ID, $number, $logId);
                if (!$flag) {
                    Log::write("云梯周分红定时任务：系统错误，奖励数量：{$number}，分配给Uid:{$userId} 失败." . date("Y-m-d H:i:s"), 'INFO');
                }
            }
        }

        Log::write("云梯周分红定时任务: 完成" . date("Y-m-d H:i:s"), "INFO");
    }

    protected function giveJackpotReward($userId, $currencyId, $number, $logId = 0)
    {
        try {
            Db::startTrans();
            if ($this->userAirJackpot->todayExist($userId)) { // 今日已经拿过
                return false;
            }
            $userCurrency = CurrencyUser::getCurrencyUser($userId, $currencyId);
            if (bccomp($userCurrency['keep_num'], $number, 6) == -1) { // keep_num == release or keep_num < release
                $number = $userCurrency['keep_num']; // 实得奖励
            }

            $feeRadio = Config::get_value("air_jackpot_fee");
            $fee = keepPoint($number * ($feeRadio * .01));
            $realNumber = $number - $fee;
            if ($realNumber <= 0 or $number <= 0) {
                return true;
            }

            $id = UserAirJackpot::add_log($userId, $currencyId, $realNumber, $logId, $fee);
            if (empty($id)) {
                return false;
            }

            $keepLogId = HongbaoKeepLog::add_log('air_jackpot', $userId, $currencyId, $number, $id);
            if (empty($keepLogId)) {
                return false;
            }

            $userCurrency['keep_num'] -= $number;
            if (!$userCurrency->save()) {
                return false;
            }

            // 记录
            $userAir = $this->userAirLevel->getAirInfo($userId);
            $flag = $this->userAirLevel->incField($userAir['id'], 'jackpot_reward', $realNumber);
            if (!$flag) {
                return false;
            }

            // 周一发放奖励到可用
            $week = date("w", time());
            if ($week == 1) { // 周一
                $notIncomes = $this->userAirJackpot->getNotIncomeData($userId, $currencyId);
                if (empty($notIncomes)) {
                    return true;
                }
                $totalNumber = array_sum(array_map(function ($item) {
                    return $item['number'];
                }, $notIncomes));


                $userCurrency = CurrencyUser::getCurrencyUser($userId, $currencyId);

//                if (bccomp($userCurrency['keep_num'], $totalNumber, 6) == -1) { // keep_num == release or keep_num < release
//                    $totalNumber = $userCurrency['keep_num']; // 实得奖励
//                }

                if ($totalNumber <= 0) {
                    return true;
                }

//                $feeRadio = Config::get_value("air_jackpot_fee");
//                $fee = keepPoint($totalNumber * ($feeRadio * .01));
//                // $realNumber = $totalNumber - $fee;
//                $totalNumber -= $fee;

                // 添加账本记录
                $accountFlag = AccountBook::add_accountbook($userId, $currencyId, AccountBookType::AIR_JACKPOT_REWARD, 'air_jackpot_reward', 'in', $totalNumber, $keepLogId);
                if (empty($accountFlag)) {
                    return false;
                }
                $ids = array_map(function ($item) {
                    return $item['id'];
                }, $notIncomes);
                $res = $this->userAirJackpot->setAlreadyIncome($ids);
                if (!$res) {
                    return false;
                }
                $userCurrency['num'] += $totalNumber;

                if (!$userCurrency->save()) {
                    return false;
                }
            }
            Db::commit();
            return true;
        } catch (Exception $exception) {
            Db::rollback();
            Log::write("云梯周分红定时任务：异常：" . $exception->getLine() . date("Y-m-d H:i:s"), "INFO");
            return false;
        }
    }

    /**
     * @param Redis $handler
     * @param string $key 缓存的key
     * @param int $userId 用户ID
     * @param int $childId 下级ID
     * @param string $levelName 下级满足的等级
     * @return boolean|int
     */
    protected function hastSet($handler, $key, $userId, $childId, $levelName)
    {
        $hashField = "user_id:{$userId}:child_id:{$childId}"; // hashField => user_id:$uid:child_id:$child_Id
        $flag = $handler->hSet($key, $hashField, $levelName);
        if (-1 == $handler->ttl($key)) {
            $handler->expire($key, 86400);
        }
        return $flag;
    }

    protected function isRequirement($levelId, $childLevelId)
    {
        return $levelId - 1 <= $childLevelId;
    }

    protected function levelIsRequirement($levelId, $number)
    {
        $level = (new AirLadderLevel())->getLevelById($levelId);
        return $number >= $level['up_number'];
    }
}