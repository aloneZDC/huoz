<?php


namespace app\cli\controller;


use app\common\model\AccountBook;
use app\common\model\AccountBookType;
use app\common\model\BflPool;
use app\common\model\Config;
use app\common\model\Currency;
use app\common\model\CurrencyPriceTemp;
use app\common\model\CurrencyUser;
use app\common\model\GroupMiningConfig;
use app\common\model\GroupMiningGoldLog;
use app\common\model\GroupMiningIncomeAirdropDetail;
use app\common\model\GroupMiningIncomeDetail;
use app\common\model\GroupMiningIncomeDividendDetail;
use app\common\model\GroupMiningIncomeFeeDetail;
use app\common\model\GroupMiningIncomeGoldDetail;
use app\common\model\GroupMiningIncomeLog;
use app\common\model\GroupMiningIncomePioneerDetail;
use app\common\model\GroupMiningLog;
use app\common\model\GroupMiningRewardLevel;
use app\common\model\GroupMiningRewardLevelLog;
use app\common\model\GroupMiningSourceBuy;
use app\common\model\GroupMiningSourceLevel;
use app\common\model\GroupMiningUser;
use app\common\model\Member;
use app\common\model\MemberBind;
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
 * 拼团挖矿奖励定时任务
 * Class GroupMiningReward
 * @package app\cli\controller
 */
class GroupMiningReward extends Command
{
    /**
     * @var string
     */
    public $name = '拼团挖矿奖励定时任务';

    /**
     * @var GroupMiningSourceLevel
     */
    protected $sourceLevel;

    /**
     * @var GroupMiningRewardLevel
     */
    protected $rewardLevel;

    /**
     * @var GroupMiningUser
     */
    protected $groupMiningUser;

    /**
     * @var GroupMiningSourceBuy
     */
    protected $groupMiningSourceBuy;

    /**
     * @var GroupMiningLog
     */
    protected $groupMiningLog;

    /**
     * @var GroupMiningGoldLog
     */
    protected $groupMiningGoldLog;

    /**
     * @var MemberBind
     */
    protected $memberBind;

    /**
     * @var Member
     */
    protected $memberModel;

    /**
     * @var GroupMiningIncomeLog
     */
    protected $incomeLog;

    /**
     * @var GroupMiningIncomeDetail
     */
    protected $incomeDetail;

    /**
     * @var GroupMiningIncomeFeeDetail
     */
    protected $incomeFeeDetail;

    /**
     * @var GroupMiningIncomeGoldDetail
     */
    protected $incomeGoldDetail;

    /**
     * @var GroupMiningIncomeAirdropDetail
     */
    protected $incomeAirdropDetail;

    /**
     * @var GroupMiningIncomeDividendDetail
     */
    protected $incomeDividendDetail;

    /**
     * @var GroupMiningIncomePioneerDetail
     */
    protected $incomePioneerDetail;

    /**
     * @var GroupMiningRewardLevelLog
     */
    protected $rewardLevelLog;

    protected $configs;

    protected $today_config = [];

    protected $source_levels = [];

    protected $reward_levels = [];

    protected $user_list = [];

    protected $mining_yesterday = [];

    /**
     * MineraReward constructor.
     * @param null $name
     */
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->sourceLevel = new GroupMiningSourceLevel();
        $this->rewardLevel = new GroupMiningRewardLevel();
        $this->memberBind = new MemberBind();
        $this->groupMiningUser = new GroupMiningUser();
        $this->groupMiningSourceBuy = new GroupMiningSourceBuy();
        $this->groupMiningLog = new GroupMiningLog();
        $this->groupMiningGoldLog = new GroupMiningGoldLog();
        $this->incomeLog = new GroupMiningIncomeLog();
        $this->incomeDetail = new GroupMiningIncomeDetail();
        $this->incomeFeeDetail = new GroupMiningIncomeFeeDetail();
        $this->incomeGoldDetail = new GroupMiningIncomeGoldDetail();
        $this->incomeAirdropDetail = new GroupMiningIncomeAirdropDetail();
        $this->incomeDividendDetail = new GroupMiningIncomeDividendDetail();
        $this->incomePioneerDetail = new GroupMiningIncomePioneerDetail();
        $this->rewardLevelLog = new GroupMiningRewardLevelLog();

        $this->configs = GroupMiningConfig::get_configs();
    }

    protected function configure()
    {
        $this->setName('GroupMiningReward')->setDescription('GroupMining reward command');
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

        $today = date('Y-m-d');

        $today_start = strtotime($today);
        $this->today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'yestday_start' => $today_start - 86400,
            'yestday_stop' => $today_start - 1,
        ];

        $this->initLevels();

        //拼团挖矿联创红利奖励资格设置为更新
        if ($this->configs['group_mining_dividend_reward_auth_update'] == 1) {
            $this->update_dividend_auth();//更新联创红利奖励资格
        }
        else {
            $this->update_buy();//更新申购矿源状态
            $this->update_mining_log();//更新挖矿记录状态
            $this->update_user();//更新用户信息

            $this->up_reward_level();//更新拼团挖矿奖励等级

            $this->tickets_reward();//拼团券奖励
            $this->fee_reward();//矿工费奖励
            $this->gold_reward();//拼团金奖励
            $this->airdrop_reward();//ABF空投奖励
            $this->dividend_reward();//联创红利奖励
            $this->pioneer_reward();//开拓奖
        }
    }

    protected function initLevels()
    {
        $select1 = $this->sourceLevel->where('type', 1)->select();
        foreach ($select1 as $value) {
            $this->source_levels[$value['level_id']] = $value;
        }
        $select2 = $this->rewardLevel->select();
        foreach ($select2 as $value) {
            $this->reward_levels[$value['level_id']] = $value;
        }
    }

    /**
     * 更新申购矿源状态
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function update_buy()
    {
        $now = time();
        Log::write("更新申购矿源状态定时任务,start:" . date('Y-m-d H:i:s'), 'INFO');

        //将状态为开启中，结束时间<=当前时间的申购矿源设置为已结束
        $flag1 = $this->groupMiningSourceBuy->where(['status'=>2,'end_time'=>['elt', $now]])->setField('status', 3);

        //将状态为待开启，开始时间<=当前时间的申购矿源设置为开启中
        $flag2 = $this->groupMiningSourceBuy->where(['status'=>1,'start_time'=>['elt', $now]])->setField('status', 2);

        //将状态为开启中，上次挖矿时间<今天开始时间，今日挖矿次数>0的申购矿源数据初始化
        $todayStartTime = strtotime(date('Y-m-d 00:00:00', $now));
        $flag3 = $this->groupMiningSourceBuy->where(['status'=>2,'last_mining_time'=>['elt', $todayStartTime],'today_num'=>['gt',0]])->update([
            'today_num'=>0,
            'cur_mining_num'=>0,
            'cur_win_num'=>0,
            'cur_lose_num'=>0,
            'cur_round_num'=>1,
        ]);

        Log::write("更新申购矿源状态定时任务,end:" . date('Y-m-d H:i:s'), 'INFO');
    }

    /**
     * 更新挖矿记录状态
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function update_mining_log()
    {
        $now = time();
        Log::write("更新挖矿记录状态定时任务,start:" . date('Y-m-d H:i:s'), 'INFO');
        $userSelect = $this->groupMiningLog->whereTime('add_time', 'yesterday')->where(['result_type'=>1,'result_status'=>2])->order(['add_time'=>'ASC','mining_num'=>'DESC'])->group('user_id,buy_id,round_num')->select();
        if (count($userSelect) <= 0) {
            Log::write("更新挖矿记录状态定时任务,符合条件记录为空");
            return;
        }
        $moneyCurrencyId = GroupMiningConfig::get_value('group_mining_money_currency_id', 66);//拼团挖矿支付币种id
        foreach ($userSelect as $key => $value) {
            $userId = $value['user_id'];
            $buyId = $value['buy_id'];
            $roundNum = $value['round_num'];
            $logSelect = $this->groupMiningLog->whereTime('add_time', 'yesterday')->where(['user_id' => $userId,'buy_id' => $buyId,'round_num' => $roundNum,'result_status' => 2,])->select();

            $log = "user_id:{$userId},buy_id:{$buyId},round_num:{$roundNum}";
            $loseRewardTotal = 0;
            $loseFeeTotal = 0;
            $ticketsTotal = 0;
            $actualTotal = 0;
            $moneyTotal = 0;
            $miningNum = 0;
            $roundList = [];
            foreach ($logSelect as $value1) {
                $roundList[] = [
                    'id'=>$value1['id'],
                ];
                if ($value['result'] == 1) {//1-赢

                }
                else {//2-输
                    $loseRewardTotal += $value['reward_num'];
                    $loseFeeTotal += $value['lose_fee_num'];
                }
                $moneyTotal += $value['money'];
                $ticketsTotal += $value['tickets_num'];
                $miningNum = max($miningNum, $value['mining_num']);
            }
            $actualTotal = keepPoint($loseRewardTotal - $loseFeeTotal, 6);
            $log = ",mining_num:{$miningNum},moneyTotal:{$moneyTotal},ticketsTotal:{$ticketsTotal},loseRewardTotal:{$loseRewardTotal},loseFeeTotal:{$loseFeeTotal},actualTotal:{$actualTotal}";

            try {
                Db::startTrans();

                //添加挖矿记录
                $data = [
                    'user_id' => $userId,
                    'level_id' => $value['level_id'],
                    'buy_id' => $buyId,
                    'type' => $value['type'],//类型 1-申购 2-体验
                    'mining_type' => $value['mining_type'],//挖矿类型 1-单次挖矿 2-一键挖矿
                    'date' => date('Y-m-d', $now),
                    'round_num' => $roundNum,
                    'mining_num' => $miningNum,
                    'money' => $moneyTotal,
                    'money_currency_id' => $moneyCurrencyId,
                    'tickets_num' => $ticketsTotal,
                    'result' => 0,
                    'reward_num' => $loseRewardTotal,
                    'lose_fee_num' => $loseFeeTotal,
                    'actual_num' => $actualTotal,
                    'result_type' => 3,//结算类型 1-挖矿结算(每局) 2-补偿金结算(每轮) 3-补偿金失效(每天)
                    'result_status'=>1,//结算状态 1-已结算 2-未结算 3-已失效
                    'result_time' => $now,
                    'add_time' => $now,
                ];
                $item_id = GroupMiningLog::insertGetId($data);
                if(!$item_id) throw new Exception('添加挖矿记录失败-in line:'.__LINE__);

                //更新挖矿记录
                foreach ($roundList as $value) {
                    $flag = GroupMiningLog::where('id', $value['id'])->update([
                        'result_status'=>3,//结算状态 1-已结算 2-未结算 3-已失效
                        'result_time'=>$now,
                    ]);
                    if($flag == false) throw new Exception('更新挖矿失败-in line:'.__LINE__);
                }

                Db::commit();
                $log .= ",成功";
            } catch (Exception $e) {
                Db::rollback();
                $log .= ",失败,异常:".$e->getMessage();
            }
            Log::write("更新挖矿记录状态定时任务,".$log, 'INFO');
        }

        Log::write("更新挖矿记录状态定时任务,end:" . date('Y-m-d H:i:s'), 'INFO');
    }

    /**
     * 更新用户信息
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function update_user()
    {
        $now = time();
        Log::write("更新用户信息定时任务,start:" . date('Y-m-d H:i:s'), 'INFO');

        //将状态为开启中，上次挖矿时间<今天开始时间，今日挖矿次数>0的申购数据初始化
        $todayStartTime = strtotime(date('Y-m-d 00:00:00', $now));
        $flag1 = $this->groupMiningSourceBuy->where(['status'=>2,'last_mining_time'=>['elt', $todayStartTime],'today_num'=>['gt',0]])->setField('today_num', 0);

        //将拥有联创红利奖励资格的用户，资格结束时间<当前时间的用户的联创红利奖励资格设置为已过期
        $flag2 = $this->groupMiningUser->where(['dividend_auth_status'=>1,'dividend_end_time'=>['lt', $todayStartTime]])->setField('dividend_auth_status', 2);

        Log::write("更新用户信息定时任务,end:" . date('Y-m-d H:i:s'), 'INFO');
    }

    /**
     * 更新联创红利奖励资格
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function update_dividend_auth()
    {
        $now = time();
        Log::write("更新联创红利奖励资格定时任务,start:" . date('Y-m-d H:i:s'), 'INFO');

        /*if (!empty($this->configs['group_mining_dividend_reward_start_time'])) {
            if ($now > strtotime($this->configs['group_mining_dividend_reward_start_time'])) {
                Log::write("更新联创红利奖励资格定时任务,联创红利奖励已开始,无法再更新");
                return;
            }
        }*/
        if ($this->configs['group_mining_dividend_reward_auth_update'] != 1) {
            Log::write("更新联创红利奖励资格定时任务,未设置更新");
            return;
        }
        $dividendAuthLevel = $this->configs['group_mining_dividend_reward_auth_level'];//拼团挖矿联创红利奖励资格需申购的矿源等级
        $buyList = $this->groupMiningSourceBuy->where(['level_id'=>$dividendAuthLevel, 'type'=>1])->field('user_id,add_time')->order('add_time','ASC')->select();
        if (count($buyList) <= 0) {
            Log::write("更新联创红利奖励资格定时任务,申购用户数量为0");
            return;
        }
        $dividendAuthDay = $this->configs['group_mining_dividend_reward_auth_day'];//拼团挖矿联创红利奖励资格获取天数
        $dividendRewardStart = strtotime($this->configs['group_mining_dividend_reward_start_time']);
        foreach ($buyList as $value) {
            $userId = $value['user_id'];
            $userInfo = $this->groupMiningUser->where('user_id', $userId)->find();
            if ($userInfo['dividend_auth_status'] != 0) continue;
            $levelDay = 0;
            $authStartTime = strtotime(date('Y-m-d 00:00:00', $value['add_time'])) + 86400;
            if (!empty($dividendRewardStart)) $authStartTime = max($authStartTime, $dividendRewardStart);
            if ($userInfo['reward_level'] > 0) {
                for ($i = 1; $i <= $userInfo['reward_level']; $i++) {
                    $levelInfo = $this->reward_levels[$i];
                    $levelDay += $levelInfo['dividend_reward_time'];
                }
            }

            $flag = $this->groupMiningUser->where('user_id', $userId)->update([
                'dividend_auth_status'=>1,
                'dividend_start_time'=>$authStartTime,
                'dividend_end_time'=>$authStartTime + ($dividendAuthDay + $levelDay) * 86400,
            ]);
        }

        Log::write("更新联创红利奖励资格定时任务,end:" . date('Y-m-d H:i:s'), 'INFO');
    }

    /**
     * 拼团券奖励详情，拼团券奖励发放
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function tickets_reward()
    {
        Log::write("拼团券奖励定时任务,start:" . date('Y-m-d H:i:s'), 'INFO');
        if ($this->configs['group_mining_tickets_reward_switch'] != 1) {
            Log::write("拼团券奖励定时任务,拼团券奖励开关未开启");
            return;
        }
        if ($this->configs['group_mining_tickets_reward_type'] == 1) {//拼团挖矿拼团券奖励发放类型 1-每日新增 2-历史
            /*if (!empty($this->mining_yesterday)) {
                $logs = $this->mining_yesterday;
            }
            else {*/
                $this->mining_yesterday = $logs = $this->groupMiningLog->where('type', 1)->where(['result_type'=>2, 'result_status'=>1])->whereTime('add_time', 'yesterday')->order('add_time', 'asc')->select();
                //$this->mining_yesterday = $logs = $this->groupMiningLog->where('type', 1)->where(['result_type'=>2, 'result_status'=>1])->whereTime('add_time', 'today')->order('add_time', 'asc')->select();
            //}
            if (count($logs) <= 0) {
                Log::write("拼团券奖励定时任务,昨日挖矿记录为空");
                return;
            }
        }
        else {
            if (!empty($this->configs['group_mining_tickets_reward_last_time'])) {
                $logs = $this->groupMiningLog->where('type', 1)->where(['result_type'=>2, 'result_status'=>1])->whereTime('add_time', 'between', [date('Y-m-d', strtotime($this->configs['group_mining_tickets_reward_last_time'])), $this->today_config['today']])->order('add_time', 'asc')->select();
            }
            else {
                $logs = $this->groupMiningLog->where('type', 1)->where(['result_type'=>2, 'result_status'=>1])->whereTime('add_time', '<', $this->today_config['today'])->order('add_time', 'asc')->select();
            }
            if (count($logs) <= 0) {
                Log::write("拼团券奖励定时任务,历史挖矿记录为空");
                return;
            }
        }
        $now = time();
        //处理拼团券奖励详情
        $ticketsCurrencyId = $this->configs['group_mining_tickets_reward_currency_id'];
        $ticketsCurrencyPrice = CurrencyPriceTemp::BBFirst_currency_price($ticketsCurrencyId);//abf价格
        $ticketsFeeRate = $this->configs['group_mining_tickets_reward_fee_rate'];
        if (!empty($logs)) {
            $userParents = [];//缓存用户的上级，防止重复查询
            foreach ($logs as $value) {

                $childId = $value['user_id'];
                $ticketsNum = $value['tickets_num'];
                if (array_key_exists($childId, $userParents)) {
                    $parents = $userParents[$childId];
                }
                else {
                    //2020-11-30修改，未开启的矿源，所有奖励不享受，只有开启后，才能正常享受
                    $where = [
                        'child_id'=>$childId,
                        'level'=>['elt', 3],
                        'b.id'=>['gt', 0],
                        //'b.level_id'=>['gt', 1],
                        'b.type'=>['in', [1,3]],
                        'b.status'=>2,
                    ];
                    $parents = $this->memberBind->alias('a')
                        ->field('a.`level`,b.user_id,b.level_id,b.id')
                        ->where($where)
                        //->join(config("database.prefix")."group_mining_user b","b.user_id=a.member_id","LEFT")
                        ->join(config("database.prefix")."group_mining_source_buy b","b.user_id=a.member_id","LEFT")
                        ->order('level', 'asc')->select();
                    if (count($parents) <= 0) { // 冇得上级
                        continue;
                    }
                    $userParents[$childId] = $parents;
                }
                $perLevel = $this->incomeDetail->where(['type'=>1, 'log_id'=>$value['id'], 'date'=>$this->today_config['today']])->order('level', 'DESC')->value('level') ? : 0;
                foreach ($parents as $parent) {

                    //处理拼团券奖励详情
                    $userId = $parent['user_id'];
                    $levelNum = $parent['level'];
                    $levelId = $parent['level_id'];
                    $log = "user_id:{$userId},level_id:{$levelId},第{$levelNum}代下级:{$childId},log_id:{$value['id']},门票数量:{$ticketsNum},perLevel:{$perLevel}";

                    $where = [
                        'user_id'=>$userId,
                        'type'=>1,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励
                        'log_id'=>$value['id'],
                        'date'=>$this->today_config['today'],
                    ];
                    $detailFind = $this->incomeDetail->where($where)->find();
                    if ($detailFind) {
                        $log .= ",该挖矿记录今日奖励详情已生成";
                    }
                    else {
                        if ($levelNum <= $perLevel) {
                            $log .= ",$levelNum <= $perLevel,没有奖励";
                        }
                        else {
                            $needLevelId = $levelNum + 1;//当前代数可以获得奖励需要的矿源等级
                            if ($needLevelId > $levelId) {//用户矿源等级<$needLevelId，无法获得奖励
                                $log .= ",$needLevelId > $levelId,没有奖励";
                            }
                            else {
                                $minLevelId = min($needLevelId, $levelId);
                                if (!array_key_exists($minLevelId, $this->source_levels)) {
                                    $log .= ",等级信息为空,没有奖励";
                                }
                                else {
                                    $levelInfo = $this->source_levels[$minLevelId];
                                    $rewardRate = $levelInfo['tickets_reward_rate'];
                                    $log .= ",rewardRate:{$rewardRate}%";
                                    $rewardNum = keepPoint($ticketsNum * $rewardRate / 100, 2);
                                    if ($rewardNum < 0.01) {
                                        $log .= ",rewardNum<0.01,没有奖励";
                                    }
                                    else {

                                        try {
                                            Db::startTrans();

                                            $log_id = $this->incomeDetail->insertGetId([
                                                'user_id'=>$userId,
                                                'level_id'=>$levelId,
                                                'currency_id'=>$ticketsCurrencyId,
                                                'type'=>1,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励
                                                'date' => $this->today_config['today'],
                                                'log_id'=>$value['id'],
                                                'level' => $levelNum,
                                                'child_id' => $childId,
                                                'need_level_id' => $needLevelId,
                                                'reward_base' => $ticketsNum,
                                                'reward_num' => $rewardNum,
                                                'reward_rate' => $rewardRate,
                                                'add_time' => $now,
                                            ]);
                                            if (!$log_id) throw new Exception('添加奖励详情失败-in line:'.__LINE__);

                                            Db::commit();
                                            $log .= ",成功";
                                        } catch (\Exception $exception) {
                                            Db::rollback();
                                            $log .= ",失败,异常:".$exception->getMessage();
                                        }
                                    }
                                }
                            }
                        }
                    }

                    Log::write("拼团券奖励定时任务,拼团券奖励详情,".$log, 'INFO');
                }
            }
        }

        //发放拼团券奖励
        $where = [
            'type'=>1,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励
            'date'=>$this->today_config['today'],
        ];
        $field = 'user_id,level_id,SUM(reward_num) AS num';
        $detailSelect = $this->incomeDetail->field($field)->where($where)->group('user_id')->select();
        if (count($detailSelect) <= 0) {
            Log::write("拼团券奖励定时任务,拼团券奖励发放,拼团券奖励详情记录为空");
        }
        else {
            foreach ($detailSelect as $key => $value) {
                $userId = $value['user_id'];
                $levelId = $value['level_id'];

                $log = "user_id:{$userId},level_id:{$levelId}";

                $where = [
                    'user_id'=>$userId,
                    'type'=>1,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励 4-联创红利奖励 5-ABF空投奖励 6-开拓奖
                    'date'=>$this->today_config['today'],
                ];
                $incomeFind = $this->incomeLog->where($where)->find();
                if ($incomeFind) {
                    $log .= ",今日奖励已发放";
                }
                else {
                    $num = keepPoint($value['num'], 2);
                    $feeNum = $ticketsFeeRate > 0 ? keepPoint($num * $ticketsFeeRate / 100, 2) : 0;
                    $actualMoney = $feeNum > 0 ? keepPoint($num - $feeNum, 2) : $num;
                    $log .= ",num:{$num},feeNum:{$feeNum},actualMoney:{$actualMoney}";

                    try {
                        Db::startTrans();

                        $income_id = $this->incomeLog->insertGetId([
                            'user_id'=>$userId,
                            'currency_id'=>$ticketsCurrencyId,
                            'type'=>1,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励 4-联创红利奖励 5-ABF空投奖励 6-开拓奖
                            'date' => $this->today_config['today'],
                            'num'=>$num,
                            'fee_rate'=>$ticketsFeeRate,
                            'fee_num'=>$feeNum,
                            'add_time' => $now,
                        ]);
                        if (!$income_id) throw new Exception('添加奖励记录失败-in line:'.__LINE__);

                        $where = [
                            'date'=>$this->today_config['today'],
                            'type'=>1,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励
                            'user_id'=>$userId,
                        ];
                        $flag = $this->incomeDetail->where($where)->update([
                            'income_id'=>$income_id,
                            'reward_time'=>time(),
                        ]);
                        if (!$flag) throw new Exception('更新奖励详情失败-in line:'.__LINE__);

                        $flag = model('AccountBook')->addLog([
                            'member_id' => $userId,
                            'currency_id' => $ticketsCurrencyId,
                            'type'=> 6106,
                            'content' => 'lan_group_mining_tickets_reward',
                            'number_type' => 1,
                            'number' => $actualMoney,
                            'fee' => $feeNum,
                            'to_member_id' => 0,
                            'to_currency_id' => 0,
                            'third_id' => $income_id,
                        ]);
                        if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);

                        //操作账户
                        $currencyUser = CurrencyUser::getCurrencyUser($userId, $ticketsCurrencyId);
                        $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setInc('num',$actualMoney);
                        if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

                        //更新用户信息
                        $flag = $this->groupMiningUser->where('user_id', $userId)->setInc('total_tickets_reward', $actualMoney);
                        if ($flag === false) throw new Exception('更新用户拼团挖矿信息失败-in line:'.__LINE__);

                        /*//减去来源奖励矿池
                        $pool = keepPoint($num / $ticketsCurrencyPrice, 6);
                        $bflRes = BflPool::fromToTask(BflPool::Reward,BflPool::FLOW, $pool,'GroupMiningTicketsReward',$income_id);
                        if($bflRes['code']!=SUCCESS) throw new Exception($bflRes['message']);*/

                        Db::commit();
                        $log .= ",发放成功";
                    } catch (\Exception $exception) {
                        Db::rollback();
                        $log .= ",失败,异常:".$exception->getMessage();
                    }
                }
                Log::write("拼团券奖励定时任务,拼团券奖励发放,".$log, 'INFO');
            }
        }

        $flag = GroupMiningConfig::where('gmc_key', 'group_mining_tickets_reward_last_time')->setField('gmc_value', date('Y-m-d H:i:s'));

        Log::write("拼团券奖励定时任务,end:" . date('Y-m-d H:i:s'), 'INFO');
    }

    /**
     * 矿工费奖励详情，矿工费奖励发放
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function fee_reward()
    {
        Log::write("矿工费奖励定时任务,start:" . date('Y-m-d H:i:s'), 'INFO');
        if ($this->configs['group_mining_fee_reward_switch'] != 1) {
            Log::write("矿工费奖励定时任务,矿工费奖励开关未开启");
            return;
        }
        if ($this->configs['group_mining_fee_reward_type'] == 1) {//拼团挖矿矿工费奖励发放类型 1-每日新增 2-历史
            /*if (!empty($this->mining_yesterday)) {
                $logs = $this->mining_yesterday;
            }
            else {*/
                $this->mining_yesterday = $logs = $this->groupMiningLog->where('type', 1)->where(['result_type'=>2, 'result_status'=>1])->whereTime('add_time', 'yesterday')->where(['lose_fee_num'=>['gt', 0]])->order('add_time', 'asc')->select();
                //$this->mining_yesterday = $logs = $this->groupMiningLog->where('type', 1)->where(['result_type'=>2, 'result_status'=>1])->whereTime('add_time', 'today')->where(['lose_fee_num'=>['gt', 0]])->order('add_time', 'asc')->select();
            //}
            if (count($logs) <= 0) {
                Log::write("矿工费奖励定时任务,昨日挖矿记录为空");
                return;
            }
        }
        else {
            if (!empty($this->configs['group_mining_fee_reward_last_time'])) {
                $logs = $this->groupMiningLog->where('type', 1)->where(['result_type'=>2, 'result_status'=>1])->whereTime('add_time', 'between', [date('Y-m-d', strtotime($this->configs['group_mining_fee_reward_last_time'])), $this->today_config['today']])->order('add_time', 'asc')->select();
            }
            else {
                $logs = $this->groupMiningLog->where('type', 1)->where(['result_type'=>2, 'result_status'=>1])->whereTime('add_time', '<', $this->today_config['today'])->order('add_time', 'asc')->select();
            }
            if (count($logs) <= 0) {
                Log::write("矿工费奖励定时任务,历史挖矿记录为空");
                return;
            }
        }
        $now = time();
        //处理矿工费奖励详情
        $feeCurrencyId = $this->configs['group_mining_fee_reward_currency_id'];
        $feeCurrencyPrice = CurrencyPriceTemp::BBFirst_currency_price($feeCurrencyId);//abf价格
        $feeFeeRate = $this->configs['group_mining_fee_reward_fee_rate'];
        if (!empty($logs)) {
            $userParents = [];//缓存用户的上级，防止重复查询
            foreach ($logs as $value) {

                $childId = $value['user_id'];
                $feeNum = $value['lose_fee_num'];
                if (array_key_exists($childId, $userParents)) {
                    $parents = $userParents[$childId];
                }
                else {
                    //2020-11-30修改，未开启的矿源，所有奖励不享受，只有开启后，才能正常享受
                    $where = [
                        'child_id'=>$childId,
                        'level'=>['elt', 3],
                        'b.id'=>['gt', 0],
                        //'b.level_id'=>['gt', 1],
                        'b.type'=>['in', [1,3]],
                        'b.status'=>2,
                    ];
                    $parents = $this->memberBind->alias('a')
                        ->field('a.`level`,b.user_id,b.level_id,b.id,b.type,b.status')
                        ->where($where)
                        //->join(config("database.prefix")."group_mining_user b","b.user_id=a.member_id","LEFT")
                        ->join(config("database.prefix")."group_mining_source_buy b","b.user_id=a.member_id","LEFT")
                        ->order('level', 'asc')->select();
                    if (count($parents) <= 0) { // 冇得上级
                        continue;
                    }
                    $userParents[$childId] = $parents;
                }
                $perLevel = $this->incomeDetail->where(['type'=>2, 'log_id'=>$value['id'], 'date'=>$this->today_config['today']])->order('level', 'DESC')->value('level') ? : 0;
                foreach ($parents as $parent) {

                    //处理矿工费奖励详情
                    $userId = $parent['user_id'];
                    $levelNum = $parent['level'];
                    $levelId = $parent['level_id'];
                    $log = "user_id:{$userId},level_id:{$levelId},第{$levelNum}代下级:{$childId},log_id:{$value['id']},矿工费数量:{$feeNum},perLevel:{$perLevel}";

                    $where = [
                        'user_id'=>$userId,
                        'type'=>2,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励
                        'log_id'=>$value['id'],
                        'date'=>$this->today_config['today'],
                    ];
                    $detailFind = $this->incomeDetail->where($where)->find();
                    if ($detailFind) {
                        $log .= ",该挖矿记录今日奖励详情已生成";
                    }
                    else {
                        if ($levelNum <= $perLevel) {
                            $log .= ",$levelNum <= $perLevel,没有奖励";
                        }
                        else {
                            $needLevelId = $levelNum + 1;//当前代数可以获得奖励需要的矿源等级
                            if ($needLevelId > $levelId) {//用户矿源等级<$needLevelId，无法获得奖励
                                $log .= ",$needLevelId > $levelId,没有奖励";
                            }
                            else {
                                $minLevelId = min($needLevelId, $levelId);
                                if (!array_key_exists($minLevelId, $this->source_levels)) {
                                    $log .= ",等级信息为空,没有奖励";
                                }
                                else {
                                    $levelInfo = $this->source_levels[$minLevelId];
                                    $rewardRate = $levelInfo['fee_reward_rate'];
                                    $log .= ",rewardRate:{$rewardRate}%";
                                    $rewardNum = keepPoint($feeNum * $rewardRate / 100, 2);
                                    if ($rewardNum < 0.01) {
                                        $log .= ",rewardNum<0.01,没有奖励";
                                    }
                                    else {

                                        try {
                                            Db::startTrans();

                                            $log_id = $this->incomeDetail->insertGetId([
                                                'user_id'=>$userId,
                                                'level_id'=>$levelId,
                                                'currency_id'=>$feeCurrencyId,
                                                'type'=>2,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励
                                                'date' => $this->today_config['today'],
                                                'log_id'=>$value['id'],
                                                'level' => $levelNum,
                                                'child_id' => $childId,
                                                'need_level_id' => $needLevelId,
                                                'reward_base' => $feeNum,
                                                'reward_num' => $rewardNum,
                                                'reward_rate' => $rewardRate,
                                                'add_time' => $now,
                                            ]);
                                            if (!$log_id) throw new Exception('添加奖励详情失败-in line:'.__LINE__);

                                            Db::commit();
                                            $log .= ",成功";
                                        } catch (\Exception $exception) {
                                            Db::rollback();
                                            $log .= ",失败,异常:".$exception->getMessage();
                                        }
                                    }
                                }
                            }
                        }
                    }

                    Log::write("矿工费奖励定时任务,矿工费奖励详情,".$log, 'INFO');
                }
            }
        }
        //处理矿工费奖励(奖励等级)详情
        if (!empty($logs)) {
            $userParents = [];//缓存用户的上级，防止重复查询
            foreach ($logs as $value) {

                $childId = $value['user_id'];
                $feeNum = $value['lose_fee_num'];
                if (array_key_exists($childId, $userParents)) {
                    $parents = $userParents[$childId];
                }
                else {
                    $where = [
                        'child_id'=>$childId,
                        'b.id'=>['gt', 0],
                        'b.level_id'=>['gt', 1],
                        'b.reward_level'=>['gt', 0],
                    ];
                    $parents = $this->memberBind->alias('a')
                        ->field('a.`level`,b.user_id,b.level_id,b.id,b.reward_level')
                        ->where($where)
                        ->join(config("database.prefix")."group_mining_user b","b.user_id=a.member_id","LEFT")
                        ->order('level', 'asc')->select();
                    if (count($parents) <= 0) { // 冇得上级
                        continue;
                    }
                    $userParents[$childId] = $parents;
                }
                $perRewardLevel = $this->incomeFeeDetail->where(['log_id'=>$value['id'], 'date'=>$this->today_config['today']])->order('reward_level', 'DESC')->value('reward_level') ? : 0;
                foreach ($parents as $parent) {

                    //处理矿工费奖励(奖励等级)详情
                    $userId = $parent['user_id'];
                    $levelNum = $parent['level'];
                    $levelId = $parent['level_id'];
                    $rewardLevel = $parent['reward_level'];
                    $log = "user_id:{$userId},level_id:{$levelId},reward_level:{$rewardLevel},第{$levelNum}代下级:{$childId},log_id:{$value['id']},矿工费数量:{$feeNum},perRewardLevel:{$perRewardLevel}";

                    $where = [
                        'user_id'=>$userId,
                        'log_id'=>$value['id'],
                        'date'=>$this->today_config['today'],
                    ];
                    $detailFind = $this->incomeFeeDetail->where($where)->find();
                    if ($detailFind) {
                        $log .= ",该挖矿记录今日矿工费奖励详情已生成";
                    }
                    else {
                        if ($rewardLevel <= $perRewardLevel) {
                            $log .= ",$rewardLevel <= $perRewardLevel,没有奖励";
                        }
                        else {//级差，必须>上一个获得矿工费奖励的用户的奖励等级
                            if (!array_key_exists($rewardLevel, $this->reward_levels)) {
                                $log .= ",等级信息为空,没有奖励";
                            }
                            else {
                                $levelInfo = $this->reward_levels[$rewardLevel];
                                $perRewardRate = 0;
                                if ($perRewardLevel) {
                                    $perLevelInfo = $this->reward_levels[$perRewardLevel];
                                    $perRewardRate = $perLevelInfo['fee_reward_rate'];
                                    $rewardRate = $levelInfo['fee_reward_rate'] - $perRewardRate;
                                }
                                else {
                                    $rewardRate = $levelInfo['fee_reward_rate'];
                                }
                                $log .= ",rewardRate:{$rewardRate}%";
                                $rewardNum = keepPoint($feeNum * $rewardRate / 100, 2);
                                if ($rewardNum < 0.01) {
                                    $log .= ",rewardNum<0.01,没有奖励";
                                }
                                else {

                                    try {
                                        Db::startTrans();

                                        $log_id = $this->incomeFeeDetail->insertGetId([
                                            'user_id'=>$userId,
                                            'reward_level'=>$rewardLevel,
                                            'currency_id'=>$feeCurrencyId,
                                            'date' => $this->today_config['today'],
                                            'log_id'=>$value['id'],
                                            'level' => $levelNum,
                                            'child_id' => $childId,
                                            'per_reward_level' => $perRewardLevel,
                                            'per_reward_rate' => $perRewardRate,
                                            'self_reward_rate' => $levelInfo['fee_reward_rate'],
                                            'reward_base' => $feeNum,
                                            'reward_num' => $rewardNum,
                                            'reward_rate' => $rewardRate,
                                            'add_time' => $now,
                                        ]);
                                        if (!$log_id) throw new Exception('添加矿工费奖励详情失败-in line:'.__LINE__);

                                        Db::commit();
                                        $log .= ",成功";
                                    } catch (\Exception $exception) {
                                        Db::rollback();
                                        $log .= ",失败,异常:".$exception->getMessage();
                                    }
                                }
                            }
                        }
                        $perRewardLevel = $rewardLevel;
                    }

                    Log::write("矿工费奖励定时任务,矿工费奖励(奖励等级)详情,".$log, 'INFO');
                }
            }
        }

        //发放矿工费奖励
        $userList = [];
        $where = [
            'type'=>2,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励
            'date'=>$this->today_config['today'],
        ];
        $field = 'user_id,level_id,SUM(reward_num) AS num';
        $detailSelect = $this->incomeDetail->field($field)->where($where)->group('user_id')->select();
        if (count($detailSelect) <= 0) {
            Log::write("矿工费奖励定时任务,矿工费奖励发放,拼团券奖励详情记录为空");
        }
        else {
            foreach ($detailSelect as $key => $value) {
                $userId = $value['user_id'];
                $num = keepPoint($value['num'], 2);

                if (array_key_exists($userId, $userList)) {
                    $userList[$userId]['num'] += $num;
                }
                else {
                    $userList[$userId] = ['user_id'=>$userId, 'num'=>$num];
                }
            }
        }
        $where = [
            'date'=>$this->today_config['today'],
        ];
        $field = 'user_id,reward_level,SUM(reward_num) AS num';
        $detailSelect = $this->incomeFeeDetail->field($field)->where($where)->group('user_id')->select();
        if (count($detailSelect) <= 0) {
            Log::write("矿工费奖励定时任务,矿工费奖励发放,拼团券奖励(奖励等级)详情记录为空");
        }
        else {
            foreach ($detailSelect as $key => $value) {
                $userId = $value['user_id'];
                $num = keepPoint($value['num'], 2);

                if (array_key_exists($userId, $userList)) {
                    $userList[$userId]['num'] += $num;
                } else {
                    $userList[$userId] = ['user_id'=>$userId, 'num' => $num];
                }
            }
        }
        if (!empty($userList)) {
            foreach ($userList as $key => $value) {
                $userId = $value['user_id'];

                $log = "user_id:{$userId}";

                $where = [
                    'user_id'=>$userId,
                    'type'=>2,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励 4-联创红利奖励 5-ABF空投奖励 6-开拓奖
                    'date'=>$this->today_config['today'],
                ];
                $incomeFind = $this->incomeLog->where($where)->find();
                if ($incomeFind) {
                    $log .= ",今日奖励已发放";
                }
                else {
                    $num = $value['num'];
                    $feeNum = $feeFeeRate > 0 ? keepPoint($num * $feeFeeRate / 100, 2) : 0;
                    $actualMoney = $feeNum  > 0 ? keepPoint($num - $feeNum, 2) : $num;
                    $log .= ",num:{$num},feeNum:{$feeNum},actualMoney:{$actualMoney}";

                    try {
                        Db::startTrans();

                        $income_id = $this->incomeLog->insertGetId([
                            'user_id'=>$userId,
                            'currency_id'=>$feeCurrencyId,
                            'type'=>2,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励 4-联创红利奖励 5-ABF空投奖励 6-开拓奖
                            'date' => $this->today_config['today'],
                            'num'=>$num,
                            'fee_rate'=>$feeFeeRate,
                            'fee_num'=>$feeNum,
                            'add_time' => $now,
                        ]);
                        if (!$income_id) throw new Exception('添加奖励记录失败-in line:'.__LINE__);

                        $where = [
                            'date'=>$this->today_config['today'],
                            'type'=>2,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励
                            'user_id'=>$userId,
                        ];
                        $flag = $this->incomeDetail->where($where)->update([
                            'income_id'=>$income_id,
                            'reward_time'=>time(),
                        ]);
                        if ($flag === false) throw new Exception('更新奖励详情失败-in line:'.__LINE__);

                        $where = [
                            'date'=>$this->today_config['today'],
                            'user_id'=>$userId,
                        ];
                        $flag = $this->incomeFeeDetail->where($where)->update([
                            'income_id'=>$income_id,
                            'reward_time'=>time(),
                        ]);
                        if ($flag === false) throw new Exception('更新矿工费奖励详情失败-in line:'.__LINE__);

                        $flag = model('AccountBook')->addLog([
                            'member_id' => $userId,
                            'currency_id' => $feeCurrencyId,
                            'type'=> 6107,
                            'content' => 'lan_group_mining_fee_reward',
                            'number_type' => 1,
                            'number' => $actualMoney,
                            'fee' => $feeNum,
                            'to_member_id' => 0,
                            'to_currency_id' => 0,
                            'third_id' => $income_id,
                        ]);
                        if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);

                        //操作账户
                        $currencyUser = CurrencyUser::getCurrencyUser($userId, $feeCurrencyId);
                        $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setInc('num',$actualMoney);
                        if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

                        //更新用户信息
                        $flag = $this->groupMiningUser->where('user_id', $userId)->setInc('total_fee_reward', $actualMoney);
                        if ($flag === false) throw new Exception('更新用户拼团挖矿信息失败-in line:'.__LINE__);

                        /*//减去来源奖励矿池
                        $pool = keepPoint($num / $feeCurrencyPrice, 6);
                        $bflRes = BflPool::fromToTask(BflPool::Reward,BflPool::FLOW, $pool,'GroupMiningFeeReward',$income_id);
                        if($bflRes['code']!=SUCCESS) throw new Exception($bflRes['message']);*/

                        Db::commit();
                        $log .= ",发放成功";
                    } catch (\Exception $exception) {
                        Db::rollback();
                        $log .= ",失败,异常:".$exception->getMessage();
                    }
                }
                Log::write("矿工费奖励定时任务,矿工费奖励发放,".$log, 'INFO');
            }
        }

        $flag = GroupMiningConfig::where('gmc_key', 'group_mining_fee_reward_last_time')->setField('gmc_value', date('Y-m-d H:i:s'));

        Log::write("矿工费奖励定时任务,end:" . date('Y-m-d H:i:s'), 'INFO');
    }

    /**
     * 拼团金奖励详情，拼团金奖励发放
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function gold_reward()
    {
        Log::write("拼团金奖励定时任务,start:" . date('Y-m-d H:i:s'), 'INFO');
        if ($this->configs['group_mining_gold_reward_switch'] != 1) {
            Log::write("拼团金奖励定时任务,拼团金奖励开关未开启");
            return;
        }
        if ($this->configs['group_mining_gold_reward_type'] == 1) {//拼团挖矿拼团金奖励发放类型 1-每日新增 2-历史
            /*if (!empty($this->mining_yesterday)) {
                $logs = $this->mining_yesterday;
            }
            else {*/
                $this->mining_yesterday = $logs = $this->groupMiningGoldLog->where('type', 1)->whereTime('add_time', 'yesterday')->order('add_time', 'asc')->select();
                //$this->mining_yesterday = $logs = $this->groupMiningGoldLog->where('type', 1)->whereTime('add_time', 'today')->order('add_time', 'asc')->select();
            //}
            if (count($logs) <= 0) {
                Log::write("拼团金奖励定时任务,昨日拼团金记录为空");
                return;
            }
        }
        else {
            if (!empty($this->configs['group_mining_gold_reward_last_time'])) {
                $logs = $this->groupMiningGoldLog->where('type', 1)->whereTime('add_time', 'between', [date('Y-m-d', strtotime($this->configs['group_mining_gold_reward_last_time'])), $this->today_config['today']])->order('add_time', 'asc')->select();
            }
            else {
                $logs = $this->groupMiningGoldLog->where('type', 1)->whereTime('add_time', '<', $this->today_config['today'])->order('add_time', 'asc')->select();
            }
            if (count($logs) <= 0) {
                Log::write("拼团金奖励定时任务,历史拼团金记录为空");
                return;
            }
        }
        $now = time();
        //处理拼团金奖励详情
        $goldCurrencyId = $this->configs['group_mining_gold_reward_currency_id'];
        $goldCurrencyPrice = CurrencyPriceTemp::BBFirst_currency_price($goldCurrencyId);//abf价格
        $goldFeeRate = $this->configs['group_mining_gold_reward_fee_rate'];
        $totalGoldNum = 0;
        if (!empty($logs)) {
            $userParents = [];//缓存用户的上级，防止重复查询
            foreach ($logs as $value) {

                $childId = $value['user_id'];
                $goldNum = $value['num'];
                $totalGoldNum += $goldNum;
                if (array_key_exists($childId, $userParents)) {
                    $parents = $userParents[$childId];
                }
                else {
                    //2020-11-30修改，未开启的矿源，所有奖励不享受，只有开启后，才能正常享受
                    $where = [
                        'child_id'=>$childId,
                        'level'=>['elt', 3],
                        'b.id'=>['gt', 0],
                        //'b.level_id'=>['gt', 1],
                        'b.type'=>['in', [1,3]],
                        'b.status'=>2,
                    ];
                    $parents = $this->memberBind->alias('a')
                        ->field('a.`level`,b.user_id,b.level_id,b.id,b.type,b.status')
                        ->where($where)
                        //->join(config("database.prefix")."group_mining_user b","b.user_id=a.member_id","LEFT")
                        ->join(config("database.prefix")."group_mining_source_buy b","b.user_id=a.member_id","LEFT")
                        ->order('level', 'asc')->select();
                    if (count($parents) <= 0) { // 冇得上级
                        continue;
                    }
                    $userParents[$childId] = $parents;
                }
                $perLevel = $this->incomeDetail->where(['type'=>2, 'log_id'=>$value['id'], 'date'=>$this->today_config['today']])->order('level', 'DESC')->value('level') ? : 0;
                foreach ($parents as $parent) {

                    //处理拼团金奖励详情
                    $userId = $parent['user_id'];
                    $levelNum = $parent['level'];
                    $levelId = $parent['level_id'];
                    $log = "user_id:{$userId},level_id:{$levelId},第{$levelNum}代下级:{$childId},log_id:{$value['id']},拼团金数量:{$goldNum},perLevel:{$perLevel}";

                    $where = [
                        'user_id'=>$userId,
                        'type'=>3,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励
                        'log_id'=>$value['id'],
                        'date'=>$this->today_config['today'],
                    ];
                    $detailFind = $this->incomeDetail->where($where)->find();
                    if ($detailFind) {
                        $log .= ",该拼团金记录今日奖励详情已生成";
                    }
                    else {
                        if ($levelNum <= $perLevel) {
                            $log .= ",$levelNum <= $perLevel,没有奖励";
                        }
                        else {
                            $needLevelId = $levelNum + 1;//当前代数可以获得奖励需要的矿源等级
                            if ($needLevelId > $levelId) {//用户矿源等级<$needLevelId，无法获得奖励
                                $log .= ",$needLevelId > $levelId,没有奖励";
                            }
                            else {
                                $minLevelId = min($needLevelId, $levelId);
                                if (!array_key_exists($minLevelId, $this->source_levels)) {
                                    $log .= ",等级信息为空,没有奖励";
                                }
                                else {
                                    $levelInfo = $this->source_levels[$minLevelId];
                                    $rewardRate = $levelInfo['gold_reward_rate'];
                                    $log .= ",rewardRate:{$rewardRate}%";
                                    $rewardNum = keepPoint($goldNum * $rewardRate / 100, 2);
                                    if ($rewardNum < 0.01) {
                                        $log .= ",rewardNum<0.01,没有奖励";
                                    }
                                    else {

                                        try {
                                            Db::startTrans();

                                            $log_id = $this->incomeDetail->insertGetId([
                                                'user_id'=>$userId,
                                                'level_id'=>$levelId,
                                                'currency_id'=>$goldCurrencyId,
                                                'type'=>3,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励
                                                'date' => $this->today_config['today'],
                                                'log_id'=>$value['id'],
                                                'level' => $levelNum,
                                                'child_id' => $childId,
                                                'need_level_id' => $needLevelId,
                                                'reward_base' => $goldNum,
                                                'reward_num' => $rewardNum,
                                                'reward_rate' => $rewardRate,
                                                'add_time' => $now,
                                            ]);
                                            if (!$log_id) throw new Exception('添加奖励详情失败-in line:'.__LINE__);

                                            Db::commit();
                                            $log .= ",成功";
                                        } catch (\Exception $exception) {
                                            Db::rollback();
                                            $log .= ",失败,异常:".$exception->getMessage();
                                        }
                                    }
                                }
                            }
                        }
                    }

                    Log::write("拼团金奖励定时任务,拼团金奖励详情,".$log, 'INFO');
                }
            }
        }
        //处理拼团金奖励(奖励等级)详情
        if ($totalGoldNum > 0) {
            foreach ($this->reward_levels as $level) {
                $rewardLevel = $level['level_id'];
                $rewardRate = $level['gold_reward_rate'];
                $totalRewardNum = keepPoint($totalGoldNum * $rewardRate / 100, 2);

                $userSelect = $this->groupMiningUser->where(['reward_level'=>$rewardLevel, 'level_id'=>['gt', 1]])->select();
                $users = [];
                $totalSource = 0;
                foreach ($userSelect as $value) {
                    $where = [
                        'a.member_id'=>$value['user_id'],
                        'b.status'=>2,
                        'b.type'=>1,
                    ];
                    $selfSource = $this->memberBind->alias('a')
                        ->where($where)
                        ->join(config("database.prefix")."group_mining_source_buy b","b.user_id=a.child_id","LEFT")->sum('price') ? : 0;
                    if ($selfSource > 0) {
                        $users[$value['user_id']] = ['user_id'=>$value['user_id'],'self_source'=>$selfSource];
                        $totalSource += $selfSource;
                    }
                }

                foreach ($users as $value) {
                    //处理拼团金奖励(奖励等级)详情
                    $userId = $value['user_id'];
                    $selfSource = $value['self_source'];

                    $rewardNum = keepPoint($totalRewardNum * ($selfSource / $totalSource), 2);

                    $log = "user_id:{$userId},reward_level:{$rewardLevel},total_gold_num:{$totalGoldNum},reward_rate:{$rewardRate},total_reward_num:{$totalRewardNum},self_source:{$selfSource},total_source:{$totalSource},reward_num:{$rewardNum}";

                    $where = [
                        'user_id'=>$userId,
                        'date'=>$this->today_config['today'],
                    ];
                    $detailFind = $this->incomeGoldDetail->where($where)->find();
                    if ($detailFind) {
                        $log .= ",该拼团金记录今日拼团金奖励详情已生成";
                    }
                    else {
                        try {
                            Db::startTrans();

                            $log_id = $this->incomeGoldDetail->insertGetId([
                                'user_id'=>$userId,
                                'reward_level'=>$rewardLevel,
                                'currency_id'=>$goldCurrencyId,
                                'date' => $this->today_config['today'],
                                'total_gold_num' => $totalGoldNum,
                                'reward_rate' => $rewardRate,
                                'total_reward_num' => $totalRewardNum,
                                'self_source' => $selfSource,
                                'total_source' => $totalSource,
                                'reward_num' => $rewardNum,
                                'add_time' => $now,
                            ]);
                            if (!$log_id) throw new Exception('添加拼团金奖励详情失败-in line:'.__LINE__);

                            Db::commit();
                            $log .= ",成功";
                        } catch (\Exception $exception) {
                            Db::rollback();
                            $log .= ",失败,异常:".$exception->getMessage();
                        }
                    }

                    Log::write("拼团金奖励定时任务,拼团金奖励(奖励等级)详情,".$log, 'INFO');
                }
            }
        }

        //发放拼团金奖励
        $userList = [];
        $where = [
            'type'=>3,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励
            'date'=>$this->today_config['today'],
        ];
        $field = 'user_id,level_id,SUM(reward_num) AS num';
        $detailSelect = $this->incomeDetail->field($field)->where($where)->group('user_id')->select();
        if (count($detailSelect) <= 0) {
            Log::write("拼团金奖励定时任务,拼团金奖励发放,拼团券奖励详情记录为空");
        }
        else {
            foreach ($detailSelect as $key => $value) {
                $userId = $value['user_id'];
                $num = keepPoint($value['num'], 2);

                if (array_key_exists($userId, $userList)) {
                    $userList[$userId]['num'] += $num;
                }
                else {
                    $userList[$userId] = ['user_id'=>$userId, 'num'=>$num];
                }
            }
        }
        $where = [
            'date'=>$this->today_config['today'],
        ];
        $field = 'user_id,reward_level,SUM(reward_num) AS num';
        $detailSelect = $this->incomeGoldDetail->field($field)->where($where)->group('user_id')->select();
        if (count($detailSelect) <= 0) {
            Log::write("拼团金奖励定时任务,拼团金奖励发放,拼团券奖励(奖励等级)详情记录为空");
        }
        else {
            foreach ($detailSelect as $key => $value) {
                $userId = $value['user_id'];
                $num = keepPoint($value['num'], 2);

                if (array_key_exists($userId, $userList)) {
                    $userList[$userId]['num'] += $num;
                } else {
                    $userList[$userId] = ['user_id'=>$userId, 'num' => $num];
                }
            }
        }
        if (!empty($userList)) {
            foreach ($userList as $key => $value) {
                $userId = $value['user_id'];

                $log = "user_id:{$userId}";

                $where = [
                    'user_id'=>$userId,
                    'type'=>3,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励 4-联创红利奖励 5-ABF空投奖励 6-开拓奖
                    'date'=>$this->today_config['today'],
                ];
                $incomeFind = $this->incomeLog->where($where)->find();
                if ($incomeFind) {
                    $log .= ",今日奖励已发放";
                }
                else {
                    $num = $value['num'];
                    $feeNum = $goldFeeRate > 0 ? keepPoint($num * $goldFeeRate / 100, 2) : 0;
                    $actualMoney = $feeNum  > 0 ? keepPoint($num - $feeNum, 2) : $num;
                    $log .= ",num:{$num},feeNum:{$feeNum},actualMoney:{$actualMoney}";

                    try {
                        Db::startTrans();

                        $income_id = $this->incomeLog->insertGetId([
                            'user_id'=>$userId,
                            'currency_id'=>$goldCurrencyId,
                            'type'=>3,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励 4-联创红利奖励 5-ABF空投奖励 6-开拓奖
                            'date' => $this->today_config['today'],
                            'num'=>$num,
                            'fee_rate'=>$goldFeeRate,
                            'fee_num'=>$feeNum,
                            'add_time' => $now,
                        ]);
                        if (!$income_id) throw new Exception('添加奖励记录失败-in line:'.__LINE__);

                        $where = [
                            'date'=>$this->today_config['today'],
                            'type'=>3,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励
                            'user_id'=>$userId,
                        ];
                        $flag = $this->incomeDetail->where($where)->update([
                            'income_id'=>$income_id,
                            'reward_time'=>time(),
                        ]);
                        if ($flag === false) throw new Exception('更新奖励详情失败-in line:'.__LINE__);

                        $where = [
                            'date'=>$this->today_config['today'],
                            'user_id'=>$userId,
                        ];
                        $flag = $this->incomeGoldDetail->where($where)->update([
                            'income_id'=>$income_id,
                            'reward_time'=>time(),
                        ]);
                        if ($flag === false) throw new Exception('更新拼团金奖励详情失败-in line:'.__LINE__);

                        $flag = model('AccountBook')->addLog([
                            'member_id' => $userId,
                            'currency_id' => $goldCurrencyId,
                            'type'=> 6108,
                            'content' => 'lan_group_mining_gold_reward',
                            'number_type' => 1,
                            'number' => $actualMoney,
                            'fee' => $feeNum,
                            'to_member_id' => 0,
                            'to_currency_id' => 0,
                            'third_id' => $income_id,
                        ]);
                        if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);

                        //操作账户
                        $currencyUser = CurrencyUser::getCurrencyUser($userId, $goldCurrencyId);
                        $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setInc('num',$actualMoney);
                        if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

                        //更新用户信息
                        $flag = $this->groupMiningUser->where('user_id', $userId)->setInc('total_gold_reward', $actualMoney);
                        if ($flag === false) throw new Exception('更新用户拼团挖矿信息失败-in line:'.__LINE__);

                        /*//减去来源奖励矿池
                        $pool = keepPoint($num / $goldCurrencyPrice, 6);
                        $bflRes = BflPool::fromToTask(BflPool::Reward,BflPool::FLOW, $pool,'GroupMiningGoldReward',$income_id);
                        if($bflRes['code']!=SUCCESS) throw new Exception($bflRes['message']);*/

                        Db::commit();
                        $log .= ",发放成功";
                    } catch (\Exception $exception) {
                        Db::rollback();
                        $log .= ",失败,异常:".$exception->getMessage();
                    }
                }
                Log::write("拼团金奖励定时任务,拼团金奖励发放,".$log, 'INFO');
            }
        }

        $flag = GroupMiningConfig::where('gmc_key', 'group_mining_gold_reward_last_time')->setField('gmc_value', date('Y-m-d H:i:s'));

        Log::write("拼团金奖励定时任务,end:" . date('Y-m-d H:i:s'), 'INFO');
    }

    /**
     * 联创红利奖励详情，联创红利奖励发放
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function dividend_reward()
    {
        Log::write("联创红利奖励定时任务,start:" . date('Y-m-d H:i:s'), 'INFO');
        if ($this->configs['group_mining_dividend_reward_switch'] != 1) {
            Log::write("联创红利奖励定时任务,联创红利奖励开关未开启");
            return;
        }
        $now = time();
        if (!empty($this->configs['group_mining_dividend_reward_start_time'])) {
            if ($now < strtotime($this->configs['group_mining_dividend_reward_start_time'])) {
                Log::write("联创红利奖励定时任务,联创红利奖励未开始");
                return;
            }
        }
        if ($this->configs['group_mining_dividend_reward_type'] == 1) {//拼团挖矿拼团金奖励发放类型 1-每日新增 2-历史
            /*if (!empty($this->mining_yesterday)) {
                $logs = $this->mining_yesterday;
            }
            else {*/
                $totalTicketsNum = $this->groupMiningLog->where('type', 1)->where(['result_type'=>2, 'result_status'=>1])->whereTime('add_time', 'yesterday')->sum('tickets_num') ? : 0;
                //$totalTicketsNum = $this->groupMiningLog->where('type', 1)->where(['result_type'=>2, 'result_status'=>1])->whereTime('add_time', 'today')->sum('tickets_num') ? : 0;
            //}
            if ($totalTicketsNum <= 0) {
                Log::write("联创红利奖励定时任务,昨日新增门票总数为0");
                return;
            }
        }
        else {
            if (!empty($this->configs['group_mining_dividend_reward_last_time'])) {
                $totalTicketsNum = $this->groupMiningLog->where('type', 1)->where(['result_type'=>2, 'result_status'=>1])->whereTime('add_time', 'between', [date('Y-m-d', strtotime($this->configs['group_mining_dividend_reward_last_time'])), $this->today_config['today']])->sum('tickets_num') ? : 0;
            }
            else {
                $totalTicketsNum = $this->groupMiningLog->where('type', 1)->where(['result_type'=>2, 'result_status'=>1])->whereTime('add_time', '<', $this->today_config['today'])->sum('tickets_num') ? : 0;
            }
            if ($totalTicketsNum <= 0) {
                Log::write("联创红利奖励定时任务,历史新增门票总数为0");
                return;
            }
        }
        $now = time();
        //处理拼团金奖励详情
        $dividendCurrencyId = $this->configs['group_mining_dividend_reward_currency_id'];
        $dividendCurrencyPrice = CurrencyPriceTemp::BBFirst_currency_price($dividendCurrencyId);//abf价格
        $dividendRewardRate = $this->configs['group_mining_dividend_reward_rate'];
        $dividendFeeRate = $this->configs['group_mining_dividend_reward_fee_rate'];

        $totalRewardNum = keepPoint($totalTicketsNum * ($dividendRewardRate / 100), 2);

        //处理联创红利奖励详情
        $flag = $this->groupMiningUser->where(['dividend_auth_status'=>1,'dividend_end_time'=>['lt', $now]])->setField('dividend_auth_status', 2);
        $userSelect = $this->groupMiningUser->where(['dividend_auth_status'=>'1', 'dividend_start_time'=>['elt', $now]])->select();
        $totalUserNum = count($userSelect);
        foreach ($userSelect as $value) {
            $userId = $value['user_id'];
            $levelId = $value['level_id'];

            $rewardNum = keepPoint($totalRewardNum / $totalUserNum, 2);

            $log = "user_id:{$userId},level_id:{$levelId},total_reward_num:{$totalRewardNum},total_user_num:{$totalUserNum},reward_num:{$rewardNum}";

            $where = [
                'user_id'=>$userId,
                'date'=>$this->today_config['today'],
            ];
            $detailFind = $this->incomeDividendDetail->where($where)->find();
            if ($detailFind) {
                $log .= ",该用户今日联创红利奖励详情已生成";
            }
            else {
                try {
                    Db::startTrans();

                    $log_id = $this->incomeDividendDetail->insertGetId([
                        'user_id'=>$userId,
                        'currency_id'=>$dividendCurrencyId,
                        'date' => $this->today_config['today'],
                        'total_tickets_num' => $totalTicketsNum,
                        'reward_rate' => $dividendRewardRate,
                        'total_reward_num' => $totalRewardNum,
                        'total_user_num' => $totalUserNum,
                        'reward_num' => $rewardNum,
                        'add_time' => $now,
                    ]);
                    if (!$log_id) throw new Exception('添加联创红利奖励详情失败-in line:'.__LINE__);

                    Db::commit();
                    $log .= ",成功";
                } catch (\Exception $exception) {
                    Db::rollback();
                    $log .= ",失败,异常:".$exception->getMessage();
                }
            }

            Log::write("联创红利奖励定时任务,联创红利奖励详情,".$log, 'INFO');
        }

        //发放联创红利奖励
        $where = [
            'date'=>$this->today_config['today'],
        ];
        $field = 'user_id,SUM(reward_num) AS num';
        $detailSelect = $this->incomeDividendDetail->field($field)->where($where)->group('user_id')->select();
        if (count($detailSelect) <= 0) {
            Log::write("联创红利奖励定时任务,联创红利奖励发放,联创红利奖励详情记录为空");
        }
        else {
            foreach ($detailSelect as $key => $value) {
                $userId = $value['user_id'];
                //$levelId = $value['level_id'];

                $log = "user_id:{$userId}";

                $where = [
                    'user_id'=>$userId,
                    'type'=>4,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励 4-联创红利奖励 5-ABF空投奖励 6-开拓奖
                    'date'=>$this->today_config['today'],
                ];
                $incomeFind = $this->incomeLog->where($where)->find();
                if ($incomeFind) {
                    $log .= ",今日奖励已发放";
                }
                else {
                    $num = keepPoint($value['num'], 2);
                    $feeNum = $dividendFeeRate > 0 ? keepPoint($num * $dividendFeeRate / 100, 2) : 0;
                    $actualMoney = $feeNum > 0 ? keepPoint($num - $feeNum, 2) : $num;
                    $log .= ",num:{$num},feeNum:{$feeNum},actualMoney:{$actualMoney}";

                    try {
                        Db::startTrans();

                        $income_id = $this->incomeLog->insertGetId([
                            'user_id'=>$userId,
                            'currency_id'=>$dividendCurrencyId,
                            'type'=>4,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励 4-联创红利奖励 5-ABF空投奖励 6-开拓奖
                            'date' => $this->today_config['today'],
                            'num'=>$num,
                            'fee_rate'=>$dividendFeeRate,
                            'fee_num'=>$feeNum,
                            'add_time' => $now,
                        ]);
                        if (!$income_id) throw new Exception('添加奖励记录失败-in line:'.__LINE__);

                        $where = [
                            'date'=>$this->today_config['today'],
                            'user_id'=>$userId,
                        ];
                        $flag = $this->incomeDividendDetail->where($where)->update([
                            'income_id'=>$income_id,
                            'reward_time'=>time(),
                        ]);
                        if (!$flag) throw new Exception('更新联创红利奖励详情失败-in line:'.__LINE__);

                        $flag = model('AccountBook')->addLog([
                            'member_id' => $userId,
                            'currency_id' => $dividendCurrencyId,
                            'type'=> 6109,
                            'content' => 'lan_group_mining_dividend_reward',
                            'number_type' => 1,
                            'number' => $actualMoney,
                            'fee' => $feeNum,
                            'to_member_id' => 0,
                            'to_currency_id' => 0,
                            'third_id' => $income_id,
                        ]);
                        if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);

                        //操作账户
                        $currencyUser = CurrencyUser::getCurrencyUser($userId, $dividendCurrencyId);
                        $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setInc('num',$actualMoney);
                        if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

                        //更新用户信息
                        $flag = $this->groupMiningUser->where('user_id', $userId)->setInc('total_dividend_reward', $actualMoney);
                        if ($flag === false) throw new Exception('更新用户拼团挖矿信息失败-in line:'.__LINE__);

                        /*//减去来源奖励矿池
                        $pool = keepPoint($num / $dividendCurrencyPrice, 6);
                        $bflRes = BflPool::fromToTask(BflPool::Reward,BflPool::FLOW, $pool,'GroupMiningDividendReward',$income_id);
                        if($bflRes['code']!=SUCCESS) throw new Exception($bflRes['message']);*/

                        Db::commit();
                        $log .= ",发放成功";
                    } catch (\Exception $exception) {
                        Db::rollback();
                        $log .= ",失败,异常:".$exception->getMessage();
                    }
                }
                Log::write("联创红利奖励定时任务,联创红利奖励发放,".$log, 'INFO');
            }
        }

        $flag = GroupMiningConfig::where('gmc_key', 'group_mining_dividend_reward_last_time')->setField('gmc_value', date('Y-m-d H:i:s'));

        Log::write("联创红利奖励定时任务,end:" . date('Y-m-d H:i:s'), 'INFO');
    }

    /**
     * ABF空投奖励详情，ABF空投奖励发放
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function airdrop_reward()
    {
        Log::write("ABF空投奖励定时任务,start:" . date('Y-m-d H:i:s'), 'INFO');
        if ($this->configs['group_mining_airdrop_reward_switch'] != 1) {
            Log::write("ABF空投奖励定时任务,ABF空投奖励开关未开启");
            return;
        }
        $now = time();
        if (!empty($this->configs['group_mining_airdrop_reward_start_time'])) {
            if ($now < strtotime($this->configs['group_mining_airdrop_reward_start_time'])) {
                Log::write("ABF空投奖励定时任务,ABF空投奖励未开始");
                return;
            }
        }
        if (!empty($this->configs['group_mining_airdrop_reward_end_time'])) {
            if ($now > strtotime($this->configs['group_mining_airdrop_reward_end_time'])) {
                Log::write("ABF空投奖励定时任务,ABF空投奖励已结束");
                return;
            }
        }
        //处理ABF空投奖励详情
        $airdropFeeRate = $this->configs['group_mining_airdrop_reward_fee_rate'];

        //处理ABF空投奖励详情
        if (count($this->source_levels) > 0) {
            foreach ($this->source_levels as $level) {
                if ($level['type'] == 2) continue;
                $levelId = $level['level_id'];
                $totalRewardNum = $level['airdrop_reward_num'];

                $airdropCurrencyId = $level['airdrop_reward_currency_id'];

                //2020-11-30修改，开启过的矿源才能享受ABF空投奖励
                $buySelect = $this->groupMiningSourceBuy->where(['level_id'=>$levelId,'status'=>['in', [2,3]]])->select();
                $totalBuyNum = count($buySelect);
                if ($totalBuyNum > 0) {
                    $rewardNum = keepPoint($totalRewardNum / $totalBuyNum, 2);

                    foreach ($buySelect as $value) {
                        //处理ABF空投奖励详情
                        $userId = $value['user_id'];
                        $buyId = $value['id'];

                        $log = "user_id:{$userId},level_id:{$levelId},buy_id:{$buyId},total_reward_num:{$totalRewardNum},total_buy_num:{$totalBuyNum},reward_num:{$rewardNum}";

                        $where = [
                            'user_id'=>$userId,
                            'buy_id'=>$buyId,
                            'date'=>$this->today_config['today'],
                        ];
                        $detailFind = $this->incomeAirdropDetail->where($where)->find();
                        if ($detailFind) {
                            $log .= ",该申购记录今日ABF空投奖励详情已生成";
                        }
                        else {
                            try {
                                Db::startTrans();

                                $log_id = $this->incomeAirdropDetail->insertGetId([
                                    'user_id'=>$userId,
                                    'currency_id'=>$airdropCurrencyId,
                                    'level_id'=>$levelId,
                                    'buy_id'=>$buyId,
                                    'date' => $this->today_config['today'],
                                    'total_reward_num' => $totalRewardNum,
                                    'total_buy_num' => $totalBuyNum,
                                    'reward_num' => $rewardNum,
                                    'add_time' => $now,
                                ]);
                                if (!$log_id) throw new Exception('添加ABF空投奖励详情失败-in line:'.__LINE__);

                                Db::commit();
                                $log .= ",成功";
                            } catch (\Exception $exception) {
                                Db::rollback();
                                $log .= ",失败,异常:".$exception->getMessage();
                            }
                        }

                        Log::write("ABF空投奖励定时任务,ABF空投奖励详情,".$log, 'INFO');
                    }
                }
            }
        }

        //发放ABF空投奖励
        $where = [
            'date'=>$this->today_config['today'],
        ];
        $field = 'user_id,currency_id,level_id,SUM(reward_num) AS num';
        $detailSelect = $this->incomeAirdropDetail->field($field)->where($where)->group('user_id')->select();
        if (count($detailSelect) <= 0) {
            Log::write("ABF空投奖励定时任务,ABF空投奖励发放,ABF空投奖励详情记录为空");
        }
        else {
            foreach ($detailSelect as $key => $value) {
                $userId = $value['user_id'];
                $levelId = $value['level_id'];
                $airdropCurrencyId = $value['currency_id'];
                $airdropCurrencyPrice = CurrencyPriceTemp::BBFirst_currency_price($airdropCurrencyId);//abf价格

                $log = "user_id:{$userId},level_id:{$levelId}";

                $where = [
                    'user_id'=>$userId,
                    'type'=>5,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励 4-联创红利奖励 5-ABF空投奖励 6-开拓奖
                    'date'=>$this->today_config['today'],
                ];
                $incomeFind = $this->incomeLog->where($where)->find();
                if ($incomeFind) {
                    $log .= ",今日奖励已发放";
                }
                else {
                    $num = keepPoint($value['num'], 2);
                    $feeNum = $airdropFeeRate > 0 ? keepPoint($num * $airdropFeeRate / 100, 2) : 0;
                    $actualMoney = $feeNum > 0 ? keepPoint($num - $feeNum, 2) : $num;
                    $log .= ",num:{$num},feeNum:{$feeNum},actualMoney:{$actualMoney}";

                    try {
                        Db::startTrans();

                        $income_id = $this->incomeLog->insertGetId([
                            'user_id'=>$userId,
                            'currency_id'=>$airdropCurrencyId,
                            'type'=>5,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励 4-联创红利奖励 5-ABF空投奖励 6-开拓奖
                            'date' => $this->today_config['today'],
                            'num'=>$num,
                            'fee_rate'=>$airdropFeeRate,
                            'fee_num'=>$feeNum,
                            'add_time' => $now,
                        ]);
                        if (!$income_id) throw new Exception('添加奖励记录失败-in line:'.__LINE__);

                        $where = [
                            'date'=>$this->today_config['today'],
                            'user_id'=>$userId,
                        ];
                        $flag = $this->incomeAirdropDetail->where($where)->update([
                            'income_id'=>$income_id,
                            'reward_time'=>time(),
                        ]);
                        if (!$flag) throw new Exception('更新ABF空投奖励详情失败-in line:'.__LINE__);

                        $flag = model('AccountBook')->addLog([
                            'member_id' => $userId,
                            'currency_id' => $airdropCurrencyId,
                            'type'=> 6110,
                            'content' => 'lan_group_mining_airdrop_reward',
                            'number_type' => 1,
                            'number' => $actualMoney,
                            'fee' => $feeNum,
                            'to_member_id' => 0,
                            'to_currency_id' => 0,
                            'third_id' => $income_id,
                        ]);
                        if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);

                        //操作账户
                        $currencyUser = CurrencyUser::getCurrencyUser($userId, $airdropCurrencyId);
                        $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setInc('num',$actualMoney);
                        if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

                        //更新用户信息
                        $flag = $this->groupMiningUser->where('user_id', $userId)->setInc('total_airdrop_reward', $actualMoney);
                        if ($flag === false) throw new Exception('更新用户拼团挖矿信息失败-in line:'.__LINE__);

                        /*//减去来源奖励矿池
                        $pool = keepPoint($num / $airdropCurrencyPrice, 6);
                        $bflRes = BflPool::fromToTask(BflPool::Reward,BflPool::FLOW, $pool,'GroupMiningAirdropReward',$income_id);
                        if($bflRes['code']!=SUCCESS) throw new Exception($bflRes['message']);*/

                        Db::commit();
                        $log .= ",发放成功";
                    } catch (\Exception $exception) {
                        Db::rollback();
                        $log .= ",失败,异常:".$exception->getMessage();
                    }
                }
                Log::write("ABF空投奖励定时任务,ABF空投奖励发放,".$log, 'INFO');
            }
        }

        $flag = GroupMiningConfig::where('gmc_key', 'group_mining_airdrop_reward_last_time')->setField('gmc_value', date('Y-m-d H:i:s'));

        Log::write("ABF空投奖励定时任务,end:" . date('Y-m-d H:i:s'), 'INFO');
    }

    /**
     * 开拓奖
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function pioneer_reward()
    {
        Log::write("开拓奖定时任务,start:" . date('Y-m-d H:i:s'), 'INFO');
        if ($this->configs['group_mining_pioneer_reward_switch'] != 1) {
            Log::write("开拓奖定时任务,开拓奖开关未开启");
            return;
        }
        $now = time();
        if (!empty($this->configs['group_mining_pioneer_reward_start_time'])) {
            if ($now < strtotime($this->configs['group_mining_pioneer_reward_start_time'])) {
                Log::write("开拓奖定时任务,开拓奖未开始");
                return;
            }
        }
        if (!empty($this->configs['group_mining_pioneer_reward_end_time'])) {
            if ($now > strtotime($this->configs['group_mining_pioneer_reward_end_time'])) {
                Log::write("开拓奖定时任务,开拓奖已结束");
                return;
            }
        }
        if ($this->configs['group_mining_pioneer_reward_type'] == 1) {//拼团挖矿开拓奖发放类型 1-每日新增 2-历史
            /*if (!empty($this->mining_yesterday)) {
                $logs = $this->mining_yesterday;
            }
            else {*/
                $logs = $this->groupMiningSourceBuy->where('type', 1)->whereTime('add_time', 'yesterday')->order('add_time', 'asc')->select();
                //$logs = $this->groupMiningSourceBuy->where('type', 1)->whereTime('add_time', 'today')->order('add_time', 'asc')->select();
            //}
            if (count($logs) <= 0) {
                Log::write("开拓奖定时任务,昨日申购矿源记录为空");
                return;
            }
        }
        else {
            if (!empty($this->configs['group_mining_pioneer_reward_last_time'])) {
                $logs = $this->groupMiningSourceBuy->where('type', 1)->whereTime('add_time', 'between', [date('Y-m-d', strtotime($this->configs['group_mining_pioneer_reward_last_time'])), $this->today_config['today']])->order('add_time', 'asc')->select();
            }
            else {
                $logs = $this->groupMiningSourceBuy->where('type', 1)->whereTime('add_time', '<', $this->today_config['today'])->order('add_time', 'asc')->select();
            }
            if (count($logs) <= 0) {
                Log::write("开拓奖定时任务,历史申购矿源记录为空");
                return;
            }
        }
        //处理开拓奖详情
        $pioneerFeeRate = $this->configs['group_mining_pioneer_reward_fee_rate'];
        $currencyList = [];//缓存币种列表，防止重复查询
        if (!empty($logs)) {
            $userParents = [];//缓存用户的上级，防止重复查询
            foreach ($logs as $value) {

                $childId = $value['user_id'];
                $buyId = $value['id'];
                $levelId = $value['level_id'];
                $levelInfo = $this->source_levels[$levelId];
                if (array_key_exists($levelInfo['price_currency_id'], $currencyList)) {
                    $priceCurrency = $currencyList[$levelInfo['price_currency_id']];
                }
                else {
                    $priceCurrency = Currency::get($levelInfo['price_currency_id']);
                    $priceCurrency['price'] = CurrencyPriceTemp::BBFirst_currency_price($levelInfo['price_currency_id']);//abf价格
                    $currencyList[$levelInfo['price_currency_id']] = $priceCurrency;
                }
                $moneyList = [];
                if ($value['money'] > 0) {
                    $moneyList[] = [
                        'currency_id'=>$value['money_currency_id'],'money_num'=>$value['money'],
                    ];
                }
                if ($value['money1'] > 0) {
                    $moneyList[] = [
                        'currency_id'=>$value['money_currency_id1'],'money_num'=>$value['money1'],
                    ];
                }
                if (array_key_exists($childId, $userParents)) {
                    $parents = $userParents[$childId];
                }
                else {
                    $where = [
                        'child_id'=>$childId,
                        'b.id'=>['gt', 0],
                        'b.pioneer_reward_rate'=>['gt', 0],
                    ];
                    $parents = $this->memberBind->alias('a')
                        ->field('a.`level`,b.user_id,b.pioneer_reward_rate')
                        ->where($where)
                        ->join(config("database.prefix")."group_mining_user b","b.user_id=a.member_id","LEFT")
                        ->order('level', 'asc')->select();
                    if (count($parents) <= 0) { // 冇得上级
                        continue;
                    }
                    $userParents[$childId] = $parents;
                }
                $perRewardRate = $this->incomePioneerDetail->where(['buy_id'=>$buyId, 'date'=>$this->today_config['today']])->order('level', 'DESC')->value('self_reward_rate') ? : 0;
                foreach ($parents as $parent) {

                    //处理开拓奖详情
                    $userId = $parent['user_id'];
                    $levelNum = $parent['level'];
                    $selfRewardRate = $parent['pioneer_reward_rate'];
                    foreach ($moneyList as $value1) {
                        $moneyNum = $value1['money_num'];
                        $currencyId = $value1['currency_id'];
                        if (array_key_exists($currencyId, $currencyList)) {
                            $currency = $currencyList[$currencyId];
                        }
                        else {
                            $currency = Currency::get($currencyId);
                            $currency['price'] = CurrencyPriceTemp::BBFirst_currency_price($currencyId);//abf价格
                            $currencyList[$currencyId] = $currency;
                        }
                        $log = "user_id:{$userId},第{$levelNum}代下级:{$childId},申购矿源,buy_id:{$buyId},level_id:{$levelId},价格:{$levelInfo['price']} {$priceCurrency['currency_name']},下级支付:{$moneyNum} {$currency['currency_name']},self_reward_rate:{$selfRewardRate},per_reward_rate:{$perRewardRate}";
                        $where = [
                            'user_id'=>$userId,
                            'currency_id'=>$currencyId,
                            'buy_id'=>$buyId,
                            'date'=>$this->today_config['today'],
                        ];
                        $detailFind = $this->incomePioneerDetail->where($where)->find();
                        if ($detailFind) {
                            $log .= ",该申购记录币种({$currency['currency_name']})今日开拓奖详情已生成";
                        }
                        else {
                            if ($selfRewardRate <= $perRewardRate) {
                                $log .= ",selfRewardRate<=perRewardRate,没有奖励";
                            }
                            else {//开拓奖，必须>上一个获得开拓奖的用户的奖励比例
                                if ($perRewardRate) {
                                    $rewardRate = $selfRewardRate - $perRewardRate;
                                }
                                else {
                                    $rewardRate = $selfRewardRate;
                                }
                                $log .= ",reward_rate:{$rewardRate}%";
                                $rewardNum = keepPoint($moneyNum * $rewardRate / 100, 2);
                                if ($rewardNum < 0.01) {
                                    $log .= ",rewardNum<0.01,没有奖励";
                                }
                                else {

                                    try {
                                        Db::startTrans();

                                        $log_id = $this->incomePioneerDetail->insertGetId([
                                            'user_id'=>$userId,
                                            'currency_id'=>$currencyId,
                                            'level_id'=>$levelId,
                                            'buy_id'=>$buyId,
                                            'date' => $this->today_config['today'],
                                            'level' => $levelNum,
                                            'child_id' => $childId,
                                            'money_num' => $moneyNum,
                                            'per_reward_rate' => $perRewardRate,
                                            'self_reward_rate' => $selfRewardRate,
                                            'reward_rate' => $rewardRate,
                                            'reward_num' => $rewardNum,
                                            'add_time' => $now,
                                        ]);
                                        if (!$log_id) throw new Exception('添加开拓奖详情失败-in line:'.__LINE__);

                                        Db::commit();
                                        $log .= ",成功";
                                    } catch (\Exception $exception) {
                                        Db::rollback();
                                        $log .= ",失败,异常:".$exception->getMessage();
                                    }
                                }
                            }
                        }
                        Log::write("开拓奖定时任务,开拓奖详情,".$log, 'INFO');
                    }
                    if ($selfRewardRate > $perRewardRate) {
                        $perRewardRate = $selfRewardRate;
                    }
                }
            }
        }

        //发放开拓奖
        $where = [
            'date'=>$this->today_config['today'],
        ];
        $field = 'user_id,currency_id,level_id,SUM(reward_num) AS num';
        $pioneerDetailSelect = $this->incomePioneerDetail->field($field)->where($where)->group('user_id,currency_id')->select();
        if (count($pioneerDetailSelect) <= 0) {
            Log::write("开拓奖定时任务,开拓奖详情记录为空");
        }
        else {
            $pioneerCurrencyId1 = GroupMiningConfig::get_value('group_mining_pioneer_reward_currency_id1', 66);
            $pioneerCurrencyId2 = GroupMiningConfig::get_value('group_mining_pioneer_reward_currency_id2', 69);
            foreach ($pioneerDetailSelect as $key => $value) {
                $userId = $value['user_id'];
                $levelId = $value['level_id'];
                $currencyId = $value['currency_id'];
                if (array_key_exists($currencyId, $currencyList)) {
                    $currency = $currencyList[$currencyId];
                    $currencyPrice = $currency['price'];
                }
                else {
                    $currency = Currency::get($currencyId);
                    $currencyPrice = $currency['price'] = CurrencyPriceTemp::BBFirst_currency_price($currencyId);//abf价格
                    $currencyList[$currencyId] = $currency;
                }

                $log = "user_id:{$userId},currency_id:{$currencyId},level_id:{$levelId}";

                $where = [
                    'user_id'=>$userId,
                    'currency_id'=>$currencyId,
                    'type'=>6,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励 4-联创红利奖励 5-ABF空投奖励 6-开拓奖
                    'date'=>$this->today_config['today'],
                ];
                $incomeFind = $this->incomeLog->where($where)->find();
                if ($incomeFind) {
                    $log .= ",今日奖励已发放";
                }
                else {
                    $num = keepPoint($value['num'], 2);
                    $feeNum = $pioneerFeeRate > 0 ? keepPoint($num * $pioneerFeeRate / 100, 2) : 0;
                    $actualMoney = $feeNum > 0 ? keepPoint($num - $feeNum, 2) : $num;
                    $log .= ",num:{$num},feeNum:{$feeNum},actualMoney:{$actualMoney}";

                    try {
                        Db::startTrans();

                        $income_id = $this->incomeLog->insertGetId([
                            'user_id'=>$userId,
                            'currency_id'=>$currencyId,
                            'type'=>6,//类型 1-拼团券奖励 2-矿工费奖励 3-拼团金奖励 4-联创红利奖励 5-ABF空投奖励 6-开拓奖
                            'date' => $this->today_config['today'],
                            'num'=>$num,
                            'fee_rate'=>$pioneerFeeRate,
                            'fee_num'=>$feeNum,
                            'add_time' => $now,
                        ]);
                        if (!$income_id) throw new Exception('添加奖励记录失败-in line:'.__LINE__);

                        $where = [
                            'date'=>$this->today_config['today'],
                            'user_id'=>$userId,
                            'currency_id'=>$currencyId,
                        ];
                        $flag = $this->incomePioneerDetail->where($where)->update([
                            'income_id'=>$income_id,
                            'reward_time'=>time(),
                        ]);
                        if (!$flag) throw new Exception('更新开拓奖详情失败-in line:'.__LINE__);

                        $flag = model('AccountBook')->addLog([
                            'member_id' => $userId,
                            'currency_id' => $currencyId,
                            'type'=> 6111,
                            'content' => 'lan_group_mining_pioneer_reward',
                            'number_type' => 1,
                            'number' => $actualMoney,
                            'fee' => $feeNum,
                            'to_member_id' => 0,
                            'to_currency_id' => 0,
                            'third_id' => $income_id,
                        ]);
                        if ($flag === false) throw new Exception('添加账本失败-in line:'.__LINE__);

                        //操作账户
                        $currencyUser = CurrencyUser::getCurrencyUser($userId, $currencyId);
                        $flag = CurrencyUser::where(['cu_id'=>$currencyUser['cu_id'],'num'=>$currencyUser['num']])->setInc('num',$actualMoney);
                        if ($flag === false) throw new Exception('更新用户资产-可用失败-in line:'.__LINE__);

                        //更新用户信息
                        if ($currencyId == $pioneerCurrencyId1) {
                            $flag = $this->groupMiningUser->where('user_id', $userId)->setInc('total_pioneer_reward1', $actualMoney);
                            if ($flag === false) throw new Exception('更新用户拼团挖矿信息失败-in line:'.__LINE__);
                        }
                        else if ($currencyId == $pioneerCurrencyId2) {
                            $flag = $this->groupMiningUser->where('user_id', $userId)->setInc('total_pioneer_reward2', $actualMoney);
                            if ($flag === false) throw new Exception('更新用户拼团挖矿信息失败-in line:'.__LINE__);
                        }

                        /*//减去来源奖励矿池
                        $pool = keepPoint($num / $currencyPrice, 6);
                        $bflRes = BflPool::fromToTask(BflPool::Reward,BflPool::FLOW, $pool,'GroupMiningPioneerReward',$income_id);
                        if($bflRes['code']!=SUCCESS) throw new Exception($bflRes['message']);*/

                        Db::commit();
                        $log .= ",发放成功";
                    } catch (\Exception $exception) {
                        Db::rollback();
                        $log .= ",失败,异常:".$exception->getMessage();
                    }
                }
                Log::write("开拓奖定时任务,开拓奖发放,".$log, 'INFO');
            }
        }

        $flag = GroupMiningConfig::where('gmc_key', 'group_mining_pioneer_reward_last_time')->setField('gmc_value', date('Y-m-d H:i:s'));

        Log::write("开拓奖定时任务,end:" . date('Y-m-d H:i:s'), 'INFO');
    }

    /**
     * 更新拼团挖矿奖励等级
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function up_reward_level()
    {
        Log::write("拼团挖矿更新奖励等级定时任务,start:" . date('Y-m-d H:i:s'), 'INFO');
        $now = time();
        $maxLevelId = $this->rewardLevel->max('level_id');
        $where = [
            'level_id'=>['gt', 1],
            'reward_level'=>['elt', $maxLevelId],
        ];
        $userSelect = $this->groupMiningUser->where($where)->select();
        if (count($userSelect) <= 0) {
            Log::write("拼团挖矿更新奖励等级定时任务,符合条件的用户不存在");
        }
        else {
            foreach ($userSelect as $key => $value) {
                $userId = $value['user_id'];
                $levelId = $value['level_id'];
                $rewardLevel = $value['reward_level'];
                $burnNum = $value['burn_num'];
                $nextLevel = $rewardLevel + 1;
                $log = "user_id:{$userId},level_id:{$levelId},reward_level:{$rewardLevel},nextLevel:{$nextLevel}";

                $upLevel = 0;//升级级数
                $dividendDay = 0;
                $userUpdate = [
                    'level_update_time'=>$now,
                ];
                $zhituiNum = $this->getUserZhituiNum($userId);
                $teamNum = $this->getUserTeamNum($userId);
                $log .= ",burnNum:{$burnNum},zhituiNum:{$zhituiNum},teamNum:{$teamNum}";
                if ($burnNum <= 0) {
                    $log .= ",燃烧数量<=0";
                }
                else {
                    while ($nextLevel < $maxLevelId) {
                        if (!array_key_exists($nextLevel, $this->reward_levels)) {
                            $log .= ",新等级:{$nextLevel}信息获取失败,无法继续升级";
                            break;
                        }
                        else {
                            $levelInfo = $this->reward_levels[$nextLevel];
                            if ($burnNum < $levelInfo['burn_num']) {
                                $log .= ",新等级:{$nextLevel},burnNum<{$levelInfo['burn_num']},不符合条件,无法升级";
                                break;
                            }
                            else {
                                if ($zhituiNum < $levelInfo['zhitui_num']) {
                                    $log .= ",新等级:{$nextLevel},zhituiNum<{$levelInfo['zhitui_num']},不符合条件,无法升级";
                                    break;
                                }
                                else {
                                    if ($teamNum < $levelInfo['team_num']) {
                                        $log .= ",新等级:{$nextLevel},teamNum<{$levelInfo['team_num']},不符合条件,无法升级";
                                        break;
                                    }
                                    else {
                                        $nextLevel++;
                                        $upLevel++;
                                        $dividendDay += $levelInfo['dividend_reward_time'];
                                    }
                                }
                            }
                        }
                    }
                    $log .= ",upLevel:{$upLevel}";
                    if ($upLevel > 0) {
                        $userUpdate['reward_level'] = ['inc', $upLevel];
                    }
                    if ($value['dividend_auth_status'] == 1 && $dividendDay > 0) {
                        $userUpdate['dividend_end_time'] = ['inc', $dividendDay * 86400];
                    }
                }

                if ($zhituiNum > 0 || $teamNum > 0 || $value['zhitui_num'] > 0 || $value['team_num'] > 0) {
                    if ($zhituiNum != $value['zhitui_num']) $userUpdate['zhitui_num'] = $zhituiNum;
                    if ($teamNum != $value['team_num']) $userUpdate['team_num'] = $teamNum;
                }

                try {
                    Db::startTrans();

                    $flag = $this->groupMiningUser->where('user_id', $userId)->update($userUpdate);
                    if ($flag === false) throw new Exception('更新用户信息失败-in line:'.__LINE__);

                    $log_id = $this->rewardLevelLog->insertGetId([
                        'user_id'=>$userId,
                        'reward_level'=>($rewardLevel + $upLevel),
                        'zhitui_num'=>$zhituiNum,
                        'team_num'=>$teamNum,
                        'add_time' => $now,
                    ]);
                    if (!$log_id) throw new Exception('添加用户奖励等级记录失败-in line:'.__LINE__);

                    Db::commit();
                    $log .= ",升级成功";
                } catch (\Exception $exception) {
                    Db::rollback();
                    $log .= ",失败,异常:".$exception->getMessage();
                }

                Log::write("实体矿机更新等级定时任务,".$log, 'INFO');
            }
        }

        Log::write("实体矿机更新等级定时任务,end:" . date('Y-m-d H:i:s'), 'INFO');
    }

    /**
     * 获取用户的直推有效数量
     * @param $userId
     * @return array
     */
    private function getUserZhituiNum($userId)
    {
        $page = 1;
        $row = 1000;
        $childs = $this->memberBind
            ->where('member_id', $userId)
            ->where('level', 1)
            ->order('level', 'asc')
            ->page($page, $row)
            ->column('child_id');
        $zhituiNum = 0;
        $childCount = count($childs);
        if ($childCount > $row) {
            while ($childCount > 0) {
                $zhituiNum += $this->groupMiningUser->where(['user_id'=>['in', $childs],'total_num'=>['gt',0]])->count('user_id') ? : 0;

                $page++;
                $childs = $this->memberBind
                    ->where('member_id', $userId)
                    ->order('level', 'asc')
                    ->page($page, $row)
                    ->column('child_id');
                $childCount = count($childs);
            }
        }
        else {
            $zhituiNum += $this->groupMiningUser->where(['user_id'=>['in', $childs],'total_num'=>['gt',0]])->count('user_id') ? : 0;
        }
        return $zhituiNum;
    }

    /**
     * 获取用户的团队有效数量
     * @param $userId
     * @return array
     */
    private function getUserTeamNum($userId)
    {
        $page = 1;
        $row = 1000;
        $childs = $this->memberBind
            ->where('member_id', $userId)
            ->order('level', 'asc')
            ->page($page, $row)
            ->column('child_id');
        $teamNum = 0;
        $childCount = count($childs);
        if ($childCount > $row) {
            while ($childCount > 0) {
                $teamNum += $this->groupMiningUser->where(['user_id'=>['in', $childs],'total_num'=>['gt',0]])->count('user_id') ? : 0;

                $page++;
                $childs = $this->memberBind
                    ->where('member_id', $userId)
                    ->order('level', 'asc')
                    ->page($page, $row)
                    ->column('child_id');
                $childCount = count($childs);
            }
        }
        else {
            $teamNum += $this->groupMiningUser->where(['user_id'=>['in', $childs],'total_num'=>['gt',0]])->count('user_id') ? : 0;
        }
        return $teamNum;
    }
}