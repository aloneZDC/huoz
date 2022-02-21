<?php


namespace app\cli\controller;


use app\common\model\AirIncomeLog;
use app\common\model\AirLadderLevel;
use app\common\model\AirTeamCliLog;
use app\common\model\MemberBind;
use app\common\model\UserAirLevel;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Request;

/**
 * Class AirTeamIncomeLevel
 * @package app\cli\controller
 */
class AirTeamIncomeLevel extends Command
{
    /**
     * @var string
     */
    public $name = '云梯团队入金等级处理定时任务';

    /**
     * @var AirIncomeLog
     */
    protected $airIncomeLog;

    /**
     * @var MemberBind
     */
    protected $memberBind;

    /**
     * @var UserAirLevel
     */
    protected $userAirLevel;

    /**
     * @var AirLadderLevel
     */
    protected $airLadderLevel;

    /**
     * AirTeamIncomeLevel constructor.
     * @param null $name
     */
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->airIncomeLog = new AirIncomeLog();
        $this->memberBind = new MemberBind();
        $this->userAirLevel = new UserAirLevel();
        $this->airLadderLevel = new AirLadderLevel();
    }


    protected function configure()
    {
        $this->setName('AirTeamIncomeLevel')->setDescription('Air ladder team income command');
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
     * @throws ModelNotFoundException
     * @throws DbException
     */
    protected function doRun()
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
                        $airInfo = $this->userAirLevel->getAirInfo($parent['member_id']);
                        if (empty($airInfo)) {
                            continue;
                        }
                        // 查询是否满足升级要求
                        $level = $this->airLadderLevel->getLevelByNumber($airInfo['income'] + $airInfo['team_income'] + $log['number']);
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

                    } catch (\Exception $exception) {
                        Log::write("团队入金等级处理定时任务：异常：" . $exception->getMessage() . ' Date: ' . date("Y-m-d H:i:s"), 'INFO');
                    }

                }
            }
        }


        Log::write("团队入金等级处理定时任务：完成" . date("Y-m-d H:i:s"), "INFO");
    }
}