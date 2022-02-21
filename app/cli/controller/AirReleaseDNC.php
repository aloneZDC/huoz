<?php


namespace app\cli\controller;


use app\common\model\Config;
use app\common\model\Currency;
use app\common\model\CurrencyUser;
use app\common\model\DcLockLog;
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
 * TODO: 20-06-30 下午2:00上线
 * Class AirReleaseDNC
 * @package app\cli\controller
 */
class AirReleaseDNC extends Command
{
    /**
     * @var string
     */
    protected $name = "DNC锁仓释放定时任务";

    /**
     * @var CurrencyUser
     */
    protected $currencyUser;

    /**
     * @var DcLockLog
     */
    protected $dcLockLog;

    /**
     * AirReleaseDNC constructor.
     * @param null $name
     */
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->currencyUser = new CurrencyUser();
        $this->dcLockLog = new DcLockLog();
    }


    protected function configure()
    {
        $this->setName('AirReleaseDNC')->setDescription('Air  release DNC command');
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
        // 查询锁仓金额大于0的数据
        Log::write("{$this->name}：开始" . date("Y-m-d H:i:s"), "INFO");

        $data = $this->currencyUser->where('dnc_lock', 'gt', 0)->where('currency_id', Currency::DNC_ID)->select();
        if (!empty($data)) {
            foreach ($data as $datum) {
                try {
                    Db::startTrans();
                        $this->releaseAny($datum['member_id'], $datum['dnc_lock'], DcLockLog::ASSESS_TYPE_DNC_LOCK, 'dc_release_min_radio');
                        /*$flag = $this->dcLockLog
                            ->whereTime('create_time', 'today')
                            ->where('user_id', $datum['member_id'])
                            ->where('type', DcLockLog::TYPE_RELEASE)
                            ->value('id');
                        if (!empty($flag)) { // 今日释放已释放
                            continue;
                        }
                        // 查询配置
                        $releaseMinRadio = Config::get_value('dc_release_min_radio');
                        // $releaseMaxRadio = Config::get_value('dc_release_max_radio');

                        // 释放DNC
                        // $radio = mt_rand($releaseMinRadio, $releaseMaxRadio);
                        $radio = $releaseMinRadio;
                        $releaseNumber = keepPoint($datum['dnc_lock'] * ($radio * .01), 6);

                        if ($releaseNumber > 0) {
                            $flag = $this->dcLockLog->release($datum['member_id'], $releaseNumber);
                            if (SUCCESS != $flag['code']) {
                                throw new Exception($flag['message']);
                            }
                        }*/

                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    Log::write("{$this->name}：异常" . $e->getMessage() .date("Y-m-d H:i:s"), "INFO");
                }
            }
        }
        Log::write("{$this->name}: 结束" . date("Y-m-d H:i:s"), 'INFO');


        // 查询锁仓金额大于0的数据
        Log::write("{$this->name}：other开始" . date("Y-m-d H:i:s"), "INFO");
        $data = $this->currencyUser->where('dnc_other_lock', 'gt', 0)->where('currency_id', Currency::DNC_ID)->select();
        if (!empty($data)) {
            foreach ($data as $datum) {
                try {
                    Db::startTrans();
                    $this->releaseAny($datum['member_id'], $datum['dnc_other_lock'], DcLockLog::ASSESS_TYPE_DNC_OTHER_LOCK, 'dc_other_release_min_radio');
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    Log::write("{$this->name}：异常" . $e->getMessage() .date("Y-m-d H:i:s"), "INFO");
                }
            }
        }

        Log::write("{$this->name}: other结束" . date("Y-m-d H:i:s"), 'INFO');
    }

    /**
     * @param int $memberId
     * @param double $number
     * @param int $assessType
     * @param string $configKey
     * @throws Exception
     */
    protected function releaseAny($memberId, $number, $assessType = DcLockLog::ASSESS_TYPE_DNC_LOCK, $configKey = 'dc_release_min_radio')
    {
        $flag = $this->dcLockLog
            ->whereTime('create_time', 'today')
            ->where('user_id', $memberId)
            ->where('type', DcLockLog::TYPE_RELEASE)
            ->where('assess_type', $assessType)
            ->value('id');
        if (empty($flag)) { // 今日释放已释放
            // 查询配置
            $releaseMinRadio = Config::get_value($configKey);
            // $releaseMaxRadio = Config::get_value('dc_release_max_radio');

            // 释放DNC
            // $radio = mt_rand($releaseMinRadio, $releaseMaxRadio);
            $radio = $releaseMinRadio;
            $releaseNumber = keepPoint($number * ($radio * .01), 6);

            if ($releaseNumber > 0) {
                $flag = $this->dcLockLog->release($memberId, $releaseNumber, $assessType);
                if (SUCCESS != $flag['code']) {
                    throw new Exception($flag['message']);
                }
            }
        }

    }
}
