<?php


namespace app\common\model;


use PDOStatement;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Model;

class UserAirLevel extends Model
{
    /**
     * 正常
     */
    const STATUS_OPEN = 1;

    /**
     * 禁用
     */
    const STATUS_CLOSE = 2;

    /**
     * 未激活
     */
    const NOT_ACTIVE = 1;

    /**
     * 已激活
     */
    const ACTIVATED = 2;


    /**
     * 邀请码长度
     */
    // const CODE_LEN = 8;


    public function level()
    {
        return $this->belongsTo(AirLadderLevel::class, 'level_id')->field('id, name, give_dnc_max_number, reward_radio');
    }

    public function user()
    {
        return $this->belongsTo(Member::class, 'user_id')->field('member_id, email, phone');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->field('currency_id, currency_name');
    }


    /**
     * 是否已激活
     * @param int $userId
     * @return bool
     */
    public function isActivated($userId)
    {
        $isActive = $this->where('user_id', $userId)->value('is_activate');
        return null != $isActive and self::ACTIVATED == $isActive;
    }

    /**
     * 获取用户云梯信息
     * @param int $userId
     * @param bool $isInit 是否初始化
     * @return UserAirLevel|array|false|PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function getAirInfo($userId, $isInit = false)
    {
        $currencyId = Config::get_value('air_currency_id');
        $info = $this->where('user_id', $userId)->with(['level', 'currency'])->where('currency_id', $currencyId)->find();
        if (empty($info) and $isInit) {
            return $this->initInfo($userId, $currencyId);
        }

        return $info;
    }

    /**
     * 是否在活动期间
     * @return bool
     */
    public function isActivityTime()
    {
        $startTime = Config::get_value('air_activity_level_start_time');
        $endTime = Config::get_value('air_activity_level_end_time');
        return time() > strtotime(date($startTime)) and time() < strtotime(date($endTime));
    }

    /**
     * 活动奖励等级
     * @param int $userId
     * @return false|int
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function activityLevel($userId)
    {
        $info = $this->getAirInfo($userId);
        // 提升等级
        $info['level_id'] += 1;
        $info['activity_level'] += 1;
        $info['up_time'] = time();
        return $info->save();
    }

    /**
     * 累加可共振额度
     * @param int $id 云梯信息ID
     * @param int $startLevelId 开始等级ID
     * @param int $endLevelId 结束等级ID
     * @return bool|int|true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function dealConvertNumber($id, $startLevelId, $endLevelId)
    {
        $levels = (new AirLadderLevel())->field('id, dnc_max_convert')->where('id', 'between', [$startLevelId, $endLevelId])->select();
        $number = array_sum(array_map(function ($level) {
            return $level['dnc_max_convert'];
        }, $levels));

        if ($number <= 0) {
            return true;
        }
        return $this->incField($id, 'convert_dnc_number', $number);
        // $this->where('user_id',$userId)->
    }

    /**
     * @param int $userId
     * @return array|false|PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getAirINfoByUserId($userId)
    {
        return $this->where('user_id', $userId)->find();
    }

    /**
     * @param int $id
     * @return array|false|PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getAirInfoById($id)
    {
        return $this->where('id', $id)->find();
    }

    /**
     * 初始化云梯
     * @param int $userId
     * @param int $currencyId
     * @return array|false|PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function initInfo($userId, $currencyId)
    {
        $id = $this->insertGetId([
            'user_id' => $userId,
            'currency_id' => $currencyId,
            'level_id' => 1,
            'income' => 0,
            'team_income' => 0,
            'recommend_reward' => 0,
            'level_diff_reward' => 0,
            'give_dnc_reward' => 0,
            'jackpot_reward' => 0,
//            'recommend_number' => 0, // 弃用
//            'invitation_code' => $this->createCode(), // 弃用
            'convert_dnc_number' => 0,
            'is_activate' => self::NOT_ACTIVE,
            'status' => self::STATUS_OPEN,
            'add_time' => time(),
        ]);
        return $this->getAirInfo($userId);
    }

    /**
     * 初始化用户激活信息
     * @param int $userId 用户ID
     * @param int $income 入金数量
     * @return false|int
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function setActiveInfo($userId, $income)
    {
        $airInfo = $this->getAirInfo($userId);
        $level = (new AirLadderLevel)->getLevelByNumber($income);

        if ($level['id'] > 1) {
            // $income = $income + $airInfo['team_income'];
            $number = $income + $airInfo['team_income'];
            $level = (new AirLadderLevel)->getLevelByNumber($number);
        }
        // 查询是否达到升级标准
        if ($level['id'] > $airInfo['level_id']) {
            // 升级 level up :)
            $airInfo['level_id'] = $level['id'];
            $airInfo['up_time'] = time(); // 最近升级时间
        }

        $airInfo['activate_time'] = time();
        $airInfo['income'] += $income;
        $airInfo['is_activate'] = self::ACTIVATED;
        return $airInfo->save();
    }

    /**
     * 入金后等级处理
     * @param int $userId
     * @param int $income
     * @return bool|false|int
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function incomeDealLevel($userId, $income)
    {
        $airInfo = $this->getAirInfo($userId);
        $oldLevelId = $airInfo['level_id'];
        $level = (new AirLadderLevel())->getLevelByNumber($airInfo['income'] + $income); // D2 500 + 0 + 500 = D2
        // 查询入金后达标的等级
        if ($level['id'] > 1) {
            $incomeNumber = $airInfo['income'] + $airInfo['team_income'];
            $level = (new AirLadderLevel())->getLevelByNumber($incomeNumber + $income); // D2 500 + 0 + 500 = D2
        }
        
        if ($level['id'] > $airInfo['level_id']/* or ($this->isActivityTime() and $airInfo['level_id'] > 1 and ($level['id'] + $airInfo['activity_level']) > $airInfo['level_id'])*/) {
            /*if ($this->isActivityTime()) {
                $level['id'] += $airInfo['activity_level'];
            }*/
            $airInfo['level_id'] = $level['id'];
            $airInfo['up_time'] = time(); // 最近升级时间
            // 累加可共振额度
            $flag = $this->dealConvertNumber($airInfo['id'], $oldLevelId, $level['id']);
            if (false === $flag) {
                return false;
            }
        }

        $airInfo['income'] += $income;
        return $airInfo->save();
    }

    /**
     * 自增某个字段
     * @param int $id
     * @param string $field
     * @param double $step
     * @return int|true
     * @throws Exception
     */
    public function incField($id, $field, $step)
    {
        return $this->where('id', $id)->setInc($field, $step);
    }

    /**
     * 直推奖
     * @param int $userId 推荐人ID
     * @param int $recommendUserId 入金人ID
     * @param int $currencyId 入金币种ID
     * @param int $number 入金数量
     * @param int $incomeId 入金记录ID
     * @param double|int $DNCReleaseBaseNumber 下级DNC释放数量
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function recommendedAward($userId, $recommendUserId, $currencyId, $number, $incomeId, $DNCReleaseBaseNumber = 0)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('parameter_error'),
            'result' => null
        ];

        if (empty($userId) or empty($recommendUserId) or empty($currencyId) or empty($incomeId) or empty($number) or $number < 0) {
            return $r;
        }

        $userAirInfo = $this->getAirInfo($userId, true); // 等级信息
        if (empty($userAirInfo)) { // 上级没有开通云梯
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_invit_pid_not_exists');
            return $r;
        }

        $userLevel = (new AirLadderLevel)->getLevelById($userAirInfo['level_id']);
        /* + $userAirInfo['team_income']*/
        $minNumber = min($userAirInfo['income'], $number);
        $feeRadio = Config::get_value('air_recommend_fee'); // 手续费比例

        $awardNumber = keepPoint($minNumber * ($userLevel['reward_radio'] * .01), 6); // 应得奖励

        // 释放金额
        $currencyUser = CurrencyUser::getCurrencyUser($userId, $currencyId);
        $releaseNumber = $awardNumber;

        if ($currencyUser['keep_num'] < 0) {// 无赠送额
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_operation_success');
            return $r;
        }

        if (bccomp($currencyUser['keep_num'], $releaseNumber, 6) == -1) { // keep_num == release or keep_num < release
            $releaseNumber = $currencyUser['keep_num']; // 实得奖励
        }

        // 添加直推奖数据
        /**
         * 20-05-27 修改逻辑：直推奖必有数据，不管是 D0 还是 赠送金归0
         * 都认为拿到直推奖，后续级差奖再减去结算时直推人等级的比例
         */
        $fee = keepPoint($releaseNumber * ($feeRadio * .01));
        // $releaseNumber -= $fee;
        $realNumber = $releaseNumber - $fee;
        $recommendId = (new UserAirRecommend)->insertGetId([
            'currency_id' => $currencyId,
            'income_id' => $incomeId,
            'user_id' => $userId,
            'recommend_user_id' => $recommendUserId,
            'number' => $number,
            'min_number' => $minNumber,
            'award_radio' => $userLevel['reward_radio'],
            'award_number' => $realNumber,
            'fee' => $fee,
            'add_time' => time()
        ]);

        if (empty($recommendId)) {
            $r['message'] = lang('system_error_please_try_again_later');
            return $r;
        }
        if ($releaseNumber > 0) {
            // 手续费扣除
            /*$flag = HongbaoKeepLog::add_log('air_fee', $userId, $currencyId, $fee, $recommendId);
            if (empty($flag)) {
                $r['message'] = lang('system_error_please_try_again_later');
                return $r;
            }
            $currencyUser['keep_num'] -= $fee;*/


            $flag = HongbaoKeepLog::add_log('air_release', $userId, $currencyId, $releaseNumber, $recommendId);
            if (empty($flag)) {
                $r['message'] = lang('system_error_please_try_again_later');
                return $r;
            }

            $currencyUser['keep_num'] -= $releaseNumber;

            // 释放数量增加到可用数量
            $accountBookFlag = AccountBook::add_accountbook($userId, $currencyId, AccountBookType::AIR_LADDER_RELEASE, 'air_ladder_release', 'in', $realNumber, $recommendId, $fee, $currencyId);
            if (empty($accountBookFlag)) {
                $r['message'] = lang('system_error_please_try_again_later');
                return $r;
            }

            $currencyUser['num'] += $realNumber;
            if (!$currencyUser->save()) {
                $r['message'] = lang('system_error_please_try_again_later');
                return $r;
            }

            $userAirInfo['recommend_reward'] += $realNumber; // 直推奖总计
            if (!$userAirInfo->save()) {
                $r['message'] = lang('system_error_please_try_again_later');
                return $r;
            }
        }

        $DNCCurrencyUser = CurrencyUser::getCurrencyUser($userId, Currency::DNC_ID);
        if ($DNCReleaseBaseNumber > 0 and $DNCCurrencyUser['dnc_lock'] > 0) {
            $airDNCFastReleaseRadio = Config::get_value('air_dnc_fast_release_radio');
            $fastReleaseNumber = min($DNCCurrencyUser['dnc_lock'], $DNCReleaseBaseNumber) * ($airDNCFastReleaseRadio * .01);
            $fastReleaseNumber = keepPoint($fastReleaseNumber, 6);

            $dcLockFlag = (new DcLockLog())->release($userId, $fastReleaseNumber, DcLockLog::ASSESS_TYPE_DNC_LOCK, DcLockLog::TYPE_FAST_RELEASE, AccountBookType::DNC_FAST_RELEASE, 'fast_release');
            if (SUCCESS != $dcLockFlag['code']) {
                $r['message'] = $dcLockFlag['message'];
                return $r;
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('success_operation');
        return $r;
    }

    /**
     * 邀请码释放存在
     * @param string $code
     * @return int|mixed
     */
    /*public function invitationCodeIsExist($code)
    {
        return $this->where('invitation_code', $code)->value('id');
    }*/


    /**
     * 创建邀请码
     * @return string
     */
    /*protected function createCode()
    {
        $code = getNonceStr(self::CODE_LEN, true);
        $flag = $this->where('invitation_code', $code)->value('id');
        if (!empty($flag)) {
            return $this->createCode();
        }

        return $code;
    }*/
}