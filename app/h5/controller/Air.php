<?php


namespace app\h5\controller;


use app\common\model\AccountBook;
use app\common\model\AccountBookType;
use app\common\model\AirDncGifts;
use app\common\model\AirEditLevelLog;
use app\common\model\AirIncomeLog;
use app\common\model\AirLadderLevel;
use app\common\model\AirWaitRecommendParams;
use app\common\model\Config;
use app\common\model\Currency;
use app\common\model\CurrencyPriceTemp;
use app\common\model\CurrencyUser;
use app\common\model\CurrencyUserTransfer;
use app\common\model\DcLockLog;
use app\common\model\DcPrice;
use app\common\model\HongbaoAirNumLog;
use app\common\model\Member;
use app\common\model\MemberBind;
use app\common\model\ResonanceLog;
use app\common\model\Sender;
use app\common\model\Trade;
use app\common\model\UserAirDiffDay;
use app\common\model\UserAirJackpot;
use app\common\model\UserAirLevel;
use app\common\model\UserAirRecommend;
use Redis;
use think\Cache;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Request;

/**
 * Class Air
 * @package app\h5\controller
 */
class Air extends Base
{
    /**
     * @var AirLadderLevel
     */
    protected $airLadder;

    /**
     * @var UserAirLevel
     */
    protected $userAirLevel;

    /**
     * @var MemberBind
     */
    protected $memberBind;

    /**
     * @var Member
     */
    protected $memberModel;

    /**
     * @var DcPrice
     */
    protected $dcPrice;

    /**
     * @var UserAirRecommend
     */
    protected $userAirRecommend;

    /**
     * @var UserAirJackpot
     */
    protected $userAirJackpot;

    /**
     * @var UserAirDiffDay
     */
    protected $userAirDiffDay;

    /**
     * @var ResonanceLog
     */
    protected $resonanceLog;

    protected $closeFunc = [/*'convertInfo', */'convert'];
    /**
     * Air constructor.
     * @param Request|null $request
     */
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $airStartCountTime = Config::get_value('air_start_count_time');
        $airEndCountTime = Config::get_value('air_end_count_time');
        if (time() > strtotime($airStartCountTime) and time() < strtotime($airEndCountTime)) {
            return $this->output_new([
                'code' => ERROR10,
                'message' => lang('air_close'),
                'result' => null
            ]);
        }
        if (in_array($request->action(true), $this->closeFunc)) {
            return $this->output_new([
                'code' => ERROR1,
                'message' => lang('lan_close'),
                'result' => null
            ]);
        }

        $this->airLadder = new AirLadderLevel();
        $this->userAirLevel = new UserAirLevel();
        $this->memberModel = new Member();
        $this->memberBind = new MemberBind();
        $this->dcPrice = new DcPrice();
        $this->resonanceLog = new ResonanceLog();

        $this->userAirRecommend = new UserAirRecommend();
        $this->userAirJackpot = new UserAirJackpot();
        $this->userAirDiffDay = new UserAirDiffDay();
    }

    /**
     * 获取所有等级
     * @return string
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     */
    public function levels()
    {
        $levels = $this->airLadder
            ->field('id, level, name, reward_radio, up_number, dnc_max_convert')
            ->where('status', AirLadderLevel::STATUS_OPEN)
            ->select();

        if (count($levels) < 1) {
            return $this->output_new([
                'code' => ERROR1,
                'message' => lang('lan_exchange_no_data'),
                'result' => null
            ]);
        }

        return $this->output_new([
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => $levels
        ]);
    }

    /**
     * 获取云梯信息接口
     * @return string|mixed
     */
    public function userLevel()
    {
        try {
            $info = $this->userAirLevel->getAirInfo($this->member_id, true);
            $dncInfo = CurrencyUser::getCurrencyUser($this->member_id, Currency::DNC_ID);
            $info['dnc_num'] = (double)$dncInfo['num'];
            $info['dnc_lock_num'] = (double)$dncInfo['dnc_lock'];
            $info['dnc_radio'] = $this->getRadio($info['currency_id'], $this->exchange_rate_type);
            $info['is_activity_time'] = $this->userAirLevel->isActivityTime();
            $info['max_level_id'] = Config::get_value('air_income_max_level') - 1;
            $info['max_level_id'] = $info['max_level_id'] <= 0 ? 0 : $info['max_level_id'];
            $info['air_activity_level_start_time'] = Config::get_value('air_activity_level_start_time');
            $info['air_activity_level_end_time'] = Config::get_value('air_activity_level_end_time');
            if ($info['level_id'] <= 1 or UserAirLevel::NOT_ACTIVE == $info['is_activate'] or $info['income'] <= 0) {
                $info['team_income'] = 0;
            }
            $info['income'] = (double)keepPoint($info['income'], 6);
            $info['team_income'] = (double)keepPoint($info['team_income'], 6);
            $info['recommend_reward'] = (double)keepPoint($info['recommend_reward'], 6);
            $info['level_diff_reward'] = (double)keepPoint($info['level_diff_reward'], 6);
            $info['jackpot_reward'] = (double)keepPoint($info['jackpot_reward'], 6);
            $info['convert_dnc_number'] = (double)keepPoint($info['convert_dnc_number'], 6);
            $info['give_dnc_reward'] = (double)keepPoint($info['give_dnc_reward'], 6);

            $xrpPlusInfo = CurrencyUser::getCurrencyUser($this->member_id, Currency::XRP_PLUS_ID);
            $info['air_num'] = (double)keepPoint($xrpPlusInfo['air_num'], 6);
            $info['num'] = (double)keepPoint($xrpPlusInfo['num'], 6);
            // 已激活(D1)用户每天空投礼包，礼包数量随机
            $everydayAirdropSwitch = Config::get_value('air_everyday_airdrop_switch');
            if ($info['level_id'] > 1 and UserAirLevel::ACTIVATED == $info['is_activate'] and 1 == $everydayAirdropSwitch) {
                $everydayAirDropNum = Config::get_value('air_everyday_airdrop_num');
                $everydayAirDropMaxNum = Config::get_value('air_everyday_airdrop_max_num');
                $number = mt_rand($everydayAirDropNum, $everydayAirDropMaxNum);
                AirDncGifts::everyAirdrop($this->member_id, $number);
            }


            $giftId = AirDncGifts::where('user_id', $this->member_id)
                ->where('type', AirDncGifts::TYPE_GIFT)
                ->where('is_take', AirDncGifts::NOT_TAKE)
                ->whereTime('add_time', '-24 hours') // 24小时内的
                ->value('id');
            // 空投
            $airdropId = AirDncGifts::where('user_id', $this->member_id)
                ->where('type', 'in', [AirDncGifts::TYPE_AIRDROP, AirDncGifts::TYPE_EVERYDAY_AIRDROP])
                ->where('is_take', AirDncGifts::NOT_TAKE)
                ->whereTime('add_time', '-24 hours') // 24小时内的
                ->value('id');

            $info['gift_id'] = $giftId;
            $info['airdrop_id'] = $airdropId;
            // $info['everyday_airdrop_id'] = null;
            return $this->output_new([
                'code' => SUCCESS,
                'message' => lang('data_success'),
                'result' => $info
            ]);
        } catch (Exception $e) {
            return $this->output_new([
                'code' => ERROR1,
                'message' => $e->getMessage(),
                'result' => null
            ]);
        }

    }

    /**
     * 入金接口
     * @param Request $request
     * @return string
     */
    public function income(Request $request)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('parameter_error'),
            'result' => null
        ];
        $number = $request->post('number', null); // 入金数量
        $payType = $request->post('pay_type', 1);// 激活类型：1自己付款 2给下级付款
        $payAssetsType = $request->post('pay_assets_type', 'num');
        $childAccount = $request->post('child_account', null);
        $childId = $request->post('child_id', null);
        $type = $request->post('type', null); // active激活 diff补差价
        $payPwd = $request->post('pay_pwd', null);
        $captcha = $request->post('captcha', null);

        // $levelId = $request->post('level_id');
        // $invitationCode = $request->post('invitation_code', null);
        $allowPayType = ['active', 'diff'];
        $allowPayAssetsType = [AirIncomeLog::ASSETS_TYPE_NUM, AirIncomeLog::ASSETS_TYPE_COMBINE];
        if (empty($number) or !in_array($type, $allowPayType) or !in_array($payType, [1, 2]) or empty($payPwd) or !in_array($payAssetsType, $allowPayAssetsType)) {
            return $this->output_new($r);
        }

        if ($number < 1) {
            $r['message'] = lang('air_active_min_limit', ['num' => 1]);
            return $this->output_new($r);
        }

        if (2 == $payType and (empty($childAccount) or empty($childId) or empty($captcha) or $payAssetsType != AirIncomeLog::ASSETS_TYPE_NUM)) {
            $r['message'] = lang('child_info_must_not_empty');
            return $this->output_new($r);
        }

        // 安全密码验证
        $verifyPwd = Member::verifyPaypwd($this->member_id, $payPwd);
        if ($verifyPwd['code'] != SUCCESS) {
            return $this->output_new($verifyPwd);
        }


        /*if (empty($levelId)) {
            return $this->output_new($r);
        }*/

//        $pid = null;
//        if ($invitationCode) {
//            $pid = $this->userAirLevel->invitationCodeIsExist($invitationCode);
//            if (empty($pid)) {
//                $r['message'] = lang('lan_invitation_code_does_not_exist');
//            }
//        }

        try {
            Db::startTrans();
            if ($payType == 2) {
                $member = Member::where('member_id', $this->member_id)->field('login_type, email, phone,send_type')->find();
                // 验证验证码
                $account = $member['send_type'] == 1 ? $member['phone'] : $member['email'];
                $sender = (new Sender)->check_log($member['send_type'], $account, 'air', $captcha);

                if (is_string($sender)) {
                    $r['message'] = $sender;
                    return $this->output_new($r);
                }

                /*$bind = $this->memberBind->where('member_id', $this->member_id)->where('child_id', $childId)->find();
                if (empty($bind)) {
                    $r['message'] = lang('child_info_mismatch');
                    return $this->output_new($r);
                }*/
                $child = $this->memberModel->where('member_id', $childId)->find();
                if ($child['email'] != $childAccount and $child['phone'] != $childAccount) {
                    $r['message'] = lang('child_info_mismatch');
                    return $this->output_new($r);
                }

                $incomeUserId = $child['member_id']; // 入金用户ID
            } else {
                $incomeUserId = $this->member_id;
            }
            $isActive = $type == 'active';

            $payUserId = $this->member_id; // 付钱的用户ID

            // 判断是否已激活
            $isActivated = $this->userAirLevel->isActivated($incomeUserId);
            if ($payType == 2) { // 前端不能确定激活状态
                $isActive = !$isActivated;
            }
            if ($isActivated and $isActive) {
                throw new Exception(lang('air_activated'));
            }

            // 先激活再入金
            if (!$isActivated and !$isActive) {
                throw new Exception(lang("air_not_activated"));
            }

            if ($isActive and $number < 100) {
                $r['message'] = lang('air_active_min_limit', ['num' => 100]);
                return $this->output_new($r);
            }

            $userAirLevel = $this->userAirLevel->getAirInfo($incomeUserId, true);

            // 个人入金最高到config.air_income_max_level income + team_income < D7.up_number
            $maxLevelId = Config::get_value('air_income_max_level');
            if ($maxLevelId > 0) {
                $maxLevel = $this->airLadder->getLevelById($maxLevelId);
                if ($number + $userAirLevel['team_income'] + $userAirLevel['income'] >= $maxLevel['up_number']) {
                    throw new Exception(lang('air_income_max_level', ['name' => $maxLevel['name']]));
                }
            }

            // 查询开通的金额
//        $level = $this->airLadder->getLevelById($levelId);
//            $level = $this->airLadder->getLevelByNumber($number);
//            if (empty($level)) {
//                $r['message'] = lang('level_not_exist');
//                return $this->output_new($r);
//            }
            $currencyId = Config::get_value('air_currency_id'); // 云梯入金币种
            $userCurrency = CurrencyUser::getCurrencyUser($payUserId, $currencyId);

            $combineNum = 0;
            $combineAirNum = 0;
            switch ($payAssetsType) {
                case AirIncomeLog::ASSETS_TYPE_NUM:
                case AirIncomeLog::ASSETS_TYPE_AIR_NUM:
                    $combineNum = $number;
                    if (bccomp($userCurrency[$payAssetsType], $combineNum, 6) == -1) {
                        throw new Exception(lang('insufficient_balance'));
                    }
                    break;
                case AirIncomeLog::ASSETS_TYPE_COMBINE:
                    $maxCombineAirNum = $number * 0.5;
                    if ($maxCombineAirNum >= $userCurrency[AirIncomeLog::ASSETS_TYPE_AIR_NUM]) {
                        $maxCombineAirNum = $userCurrency[AirIncomeLog::ASSETS_TYPE_AIR_NUM];
                    }

                    $combineNum = $number - $maxCombineAirNum;
                    $combineAirNum = $maxCombineAirNum;

                    if (bccomp($userCurrency[AirIncomeLog::ASSETS_TYPE_NUM], $combineNum, 6) == -1) {
                        throw new Exception(lang('insufficient_balance'));
                    }

                    if (bccomp($userCurrency[AirIncomeLog::ASSETS_TYPE_AIR_NUM], $combineAirNum, 6) == -1) {
                        throw new Exception(lang('insufficient_balance'));
                    }
                    break;
                default:
                    throw new Exception(lang('lan_modifymember_parameter_error'));
            }
//            $dncPrice = DcPrice::getPrice(todayBeginTimestamp(), "CNY"); // DNC今天的价格
//            $otherPrice = CurrencyPriceTemp::get_price_currency_id($currencyId, "CNY"); // xrp+的价格

            $radio = $this->getRadio($currencyId, $this->exchange_rate_type, false);
            $giveNumber = keepPoint($number / $radio, 6); // 赠送数量
            $airdropBaseNumber = $giveNumber;
            $giveCurrencyId = Config::get_value('price_line_currency');

            // 添加赠送数量到DNC锁仓
            $userAirLevel = $this->userAirLevel->getAirInfo($incomeUserId); // 最新的等级信息
            if (bccomp($userAirLevel['give_dnc_reward'], $userAirLevel['level']['give_dnc_max_number']) != -1) { // 最大赠送额判断
                $giveNumber = 0;
            } else {
                if ($userAirLevel['level']['give_dnc_max_number'] - $userAirLevel['give_dnc_reward'] < $giveNumber) { // 最后一次赠送额度
                    $giveNumber = $userAirLevel['level']['give_dnc_max_number'] - $userAirLevel['give_dnc_reward'];
                }
            }

            // 添加入金入金记录
            $incomeType = $isActive ? AirIncomeLog::TYPE_ACTIVATION : AirIncomeLog::TYPE_DIFF;
            $incomeLogId = AirIncomeLog::add_log($incomeUserId, $payUserId, $currencyId, $number, $giveCurrencyId, $giveNumber, $incomeType, $payAssetsType, $combineAirNum);
            if (empty($incomeLogId)) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            // 扣除资产账本
            if ($isActive) {
                $typeId = AccountBookType::AIR_ACTIVE;
                if ($incomeUserId != $this->member_id) {
                    $typeId = AccountBookType::AIR_PAY_ACTIVE;
                }
            } else {
                $typeId = AccountBookType::AIR_DIFF;
                if ($incomeUserId != $this->member_id) {
                    $typeId = AccountBookType::AIR_PAY_DIFF;
                }
            }

            switch ($payAssetsType) {
                case AirIncomeLog::ASSETS_TYPE_NUM:
                    $accountFlag = AccountBook::add_accountbook($payUserId, $userAirLevel['currency_id'], $typeId, 'air_income', 'out', $number, $incomeLogId);
                    $airNumFlag = true;
                    break;
                case AirIncomeLog::ASSETS_TYPE_AIR_NUM:
                    $accountFlag = true;
                    $airNumFlag = HongbaoAirNumLog::add_log('air', $payUserId, $userAirLevel['currency_id'], $number, $incomeLogId);
                    break;
                case AirIncomeLog::ASSETS_TYPE_COMBINE:
                    $accountFlag = AccountBook::add_accountbook($payUserId, $userAirLevel['currency_id'], $typeId, 'air_income', 'out', $combineNum, $incomeLogId);
                    $airNumFlag = true;
                    if ($combineAirNum > 0) {
                        $airNumFlag = HongbaoAirNumLog::add_log('air', $payUserId, $userAirLevel['currency_id'], $combineAirNum, $incomeLogId);
                    }

                    break;
                default:
                    throw new Exception(lang('lan_Illegal_operation'));
            }

            // $flag = AccountBook::add_accountbook($payUserId, $userAirLevel['currency_id'], $typeId, 'air_income', 'out', $number, $incomeLogId);
            if (empty($accountFlag) or empty($airNumFlag)) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            switch ($payAssetsType) {
                case AirIncomeLog::ASSETS_TYPE_NUM:
                case AirIncomeLog::ASSETS_TYPE_AIR_NUM:
                    $userCurrency[$payAssetsType] -= $number;
                    break;
                case AirIncomeLog::ASSETS_TYPE_COMBINE:
                    $userCurrency[AirIncomeLog::ASSETS_TYPE_NUM] -= $combineNum;
                    $userCurrency[AirIncomeLog::ASSETS_TYPE_AIR_NUM] -= $combineAirNum;
                    break;
                default:
                    throw new Exception(lang('lan_Illegal_operation'));
            }

            if (!$userCurrency->save()) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            $pid = $this->memberModel->where('member_id', $incomeUserId)->value('pid'); // 直推上级ID
            $recommendWaitTime = Config::get_value('air_recommend_wait_time');
            if (!empty($pid) and time() > strtotime($recommendWaitTime)) {
                // 直推奖
                $DNCReleaseBaseNumber = 0;
                // TODO：加速释放后续上线
                /*if (AirIncomeLog::ASSETS_TYPE_NUM == $payAssetsType) {
                    $DNCReleaseBaseNumber = $airdropBaseNumber;
                }*/
                $res = $this->userAirLevel->recommendedAward($pid, $incomeUserId, $currencyId, $number, $incomeLogId, $DNCReleaseBaseNumber);
                if (SUCCESS != $res['code']) {
                    throw new Exception($res['message']);
                }
            } else {
                // 添加到等待结算数据
                $flag = (new AirWaitRecommendParams())->insertGetId([
                    'user_id' => $pid,
                    'recommend_user_id' => $incomeUserId,
                    'currency_id' => $currencyId,
                    'number' => $number,
                    'income_id' => $incomeLogId
                ]);
                if (!$flag) {
                    throw new Exception(lang('system_error_please_try_again_later'));
                }
            }

            if ($isActive) {
                // 激活用户
                $flag = $this->userAirLevel->setActiveInfo($incomeUserId, $number);
            } else {
                // 等级处理
                $flag = $this->userAirLevel->incomeDealLevel($incomeUserId, $number);
            }

            if (!$flag) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            if ($giveNumber > 0) {
                // DNC锁仓记录
                $lockLog = DcLockLog::add_log(DcLockLog::TYPE_AIR_GIVE, $incomeUserId, $giveCurrencyId, $giveNumber, $incomeLogId);
                if (empty($lockLog)) {
                    throw new Exception(lang('system_error_please_try_again_later'));
                }
                // 增加锁仓资产
                $dncCurrencyUser = CurrencyUser::getCurrencyUser($incomeUserId, $giveCurrencyId);
                $dncCurrencyUser['dnc_lock'] += $giveNumber;
                $this->userAirLevel->incField($userAirLevel['id'], 'give_dnc_reward', $giveNumber); // 统计数据

                if (!$dncCurrencyUser->save()) {
                    throw new Exception(lang('system_error_please_try_again_later'));
                }
            }

            if (isset($sender)) {
                (new Sender())->hasUsed($sender['id']);
            }

            // 空投DNC锁仓
            if (AirIncomeLog::ASSETS_TYPE_NUM == $payAssetsType) {
                // 使用xrp+入金才有空投
                $airDNCAirdropSwitch = Config::get_value('air_dnc_airdrop_switch');
                if (1 == $airDNCAirdropSwitch) {
                    $airDNCAirdropRadio = Config::get_value('air_dnc_airdrop_radio');
                    $airDNCAirdropMaxRadio = Config::get_value('air_dnc_airdrop_max_radio');
                    $airdropNumber = $this->giftNumber($airdropBaseNumber, $airDNCAirdropRadio, $airDNCAirdropMaxRadio);

                    if ($airdropNumber > 0) {
                        $airdropFlag = AirDncGifts::addGift($incomeUserId, Currency::DNC_ID, $airdropNumber, AirDncGifts::TYPE_AIRDROP);
                        if (empty($airdropFlag)) {
                            throw new Exception(lang('system_error_please_try_again_later'));
                        }
                    }
                }
            }


            if ($isActive) {
                // 激活赠送共振额度
                /* $airActivatedGiveNumber = (double)Config::get_value('air_activated_give_number');
                $userAirLevel['convert_dnc_number'] = $airActivatedGiveNumber;
                if (false === $userAirLevel->save()) {
                    throw new Exception(lang('system_error_please_try_again_later'));
                }*/
                $userAirLevel = $this->userAirLevel->getAirInfo($incomeUserId);
                // 活动时间内激活额外提升等级
                if ($this->userAirLevel->isActivityTime() and $userAirLevel['level_id'] > 1 and $userAirLevel['level_id'] < 7) {
                    $flag = $this->userAirLevel->activityLevel($incomeUserId); // 额外赠送等级
                    if (false === $flag) {
                        throw new Exception(lang('system_error_please_try_again_later'));
                    }
                }

                // 累加可共振额度
                $lastAirInfo = $this->userAirLevel->getAirInfo($incomeUserId);
                $flag = $this->userAirLevel->dealConvertNumber($lastAirInfo['id'], 1, $lastAirInfo['level_id']); // 0 - N累加额度
                if (false === $flag) {
                    throw new Exception(lang('system_error_please_try_again_later'));
                }

                $airDNCGiftSwitch = Config::get_value('air_dnc_gift_switch'); // 1开启 2关闭
                if (1 == $airDNCGiftSwitch) {
                    // $randRadio = (mt_rand($airDNCGiftRadio * 10, $airDNCGiftMaxRadio * 10)) *  .1;

                    // $giftNumber = $number * ($randRadio * .01);
                    // DNC 礼包 （0.1% - 0.5%）随机比例
                    $airDNCGiftRadio = Config::get_value('air_dnc_gift_radio');
                    $airDNCGiftMaxRadio = Config::get_value('air_dnc_gift_max_radio');
                    $giftNumber = $this->giftNumber($number, $airDNCGiftRadio, $airDNCGiftMaxRadio);
                    if ($giftNumber > 0) {
                        $giftFlag = AirDncGifts::addGift($incomeUserId, Currency::DNC_ID, $giftNumber, AirDncGifts::TYPE_GIFT);
                        if (empty($giftFlag)) {
                            throw new Exception(lang('system_error_please_try_again_later'));
                        }
                    }
                }

                $r['code'] = SUCCESS;
                $r['message'] = lang('success_operation');
                $r['result'] = [
                    'activated_give_convert_number' => (double)$lastAirInfo['convert_dnc_number'],
                    'level_name' => $userAirLevel['level']['name'],
                    'give_number' => (double)$giveNumber,
                    'give_currency_name' => Currency::where('currency_id', Currency::DNC_ID)->value('currency_name'),
                    'combine_num' => (double)$combineNum,
                    'combine_air_num' => (double)$combineAirNum,
                ];
            } else {
                $userAirLevel = $this->userAirLevel->getAirInfo($incomeUserId);

                $r['code'] = SUCCESS;
                $r['message'] = lang('success_operation');
                $r['result'] = [
                    'activated_give_convert_number' => null,
                    'give_number' => (double)$giveNumber,
                    'level_name' => $userAirLevel['level']['name'],
//                    'base_number' => $airdropBaseNumber,
                    'give_currency_name' => Currency::where('currency_id', Currency::DNC_ID)->value('currency_name'),
                    'combine_num' => (double)$combineNum,
                    'combine_air_num' => (double)$combineAirNum,
                ];
            }

            Db::commit();
            return $this->output_new($r);
        } catch (\Exception $exception) {
            Db::rollback();
            $r['message'] = $exception->getMessage();
            return $this->output_new($r);
        }
    }

    /**
     * 获取随机比例数量
     * @param int $number
     * @param int|double $minRadio
     * @param int|double $maxRadio
     * @return float|int
     */
    protected function giftNumber($number = 0, $minRadio = 0, $maxRadio = 0)
    {
        if ($minRadio <= 0 or $maxRadio <= 0 or $number <= 0) {
            return 0;
        }

        $randRadio = (mt_rand($minRadio * 10, $maxRadio * 10)) * .1;

        return $number * ($randRadio * .01);
    }

    /**
     * DNC价格走势接口
     * @param Request $request
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function priceLine(Request $request)
    {
        $page = $request->post('page', 1);
        $rows = $request->post('row', 5);
        $type = $request->post('type', 'd'); // weeks w months m days d

        $data = $this->dcPrice->getLine($type, $page, $rows);
        // $xrpPlusPrice = CurrencyPriceTemp::get_price_currency_id(Currency::XRP_PLUS_ID, "CNY");
        $cny = CurrencyPriceTemp::get_price_currency_id(Currency::DNC_ID, 'CNY');
        $usd = CurrencyPriceTemp::get_price_currency_id(Currency::DNC_ID, 'USD');
        $radio = $cny / $usd;
        foreach ($data as $key => &$item) {
            if (isset($item['price'])) {
                $item['price'] = keepPoint($item['price'], 6);
            } else {
                if ('1' == date("j", $item['date'])) {
                    $item['first'] = true;
                } else {
                    $item['first'] = false;
                }
                $item['date'] = date("Y-m-d", $item['date']);
                $item['steps'] = (double)keepPoint($item['steps'] / $radio, 6);
                //$item['release_mark'] = (int)$item['release_mark'];
                // $item['count'] = (double)keepPoint($item['count'], 6);
            }
        }


        //array_unshift($data, ['release_mark' => (string)date("m-d", time()+86400), 'count' => 0]);
        $todayTimestamp = todayBeginTimestamp();
        $yesterdayTimestamp = $todayTimestamp - 86400;

        $todayPrice = keepPoint((DcPrice::getPrice($todayTimestamp, "CNY")) / $radio, 6);
        $firstPrice = DcPrice::order('add_time', 'asc')->find();
        $yesterdayPrice = keepPoint((DcPrice::getPrice($yesterdayTimestamp, "CNY")) / $radio, 6);
        $unit = "$";
        $firstPrice = keepPoint((DcPrice::getPrice($firstPrice['add_time'], "CNY") / $radio), 6);

        $gain = (double)keepPoint((($todayPrice - $firstPrice) / $firstPrice) * 100, 2);
        return $this->output_new([
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => [
                'list' => $data,
                'today_price' => $todayPrice,
                'yesterday_price' => $yesterdayPrice,
                'unit' => $unit,
                'gain' => $gain
            ]
        ]);
    }

    /**
     * 领取礼物
     * @param Request $request
     */
    public function gift(Request $request)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        $id = $request->post('id');
        if (empty($id)) {
            return $this->output_new($r);
        }

        $r = AirDncGifts::takeGift($id, $this->member_id);
        return $this->output_new($r);
    }

    /**
     * 共振信息
     * @return string
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     */
    public function convertInfo()
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('system_error_please_try_again_later'),
            'result' => null
        ];
        $airInfo = $this->userAirLevel->getAirInfo($this->member_id);

        if (!$airInfo) {
            $airInfo['convert_dnc_number'] = 0;
            $airInfo['currency_id'] = Currency::XRP_PLUS_ID;
        }
        $usedNumber = $this->resonanceLog->getUsedNumber($this->member_id);
        $surplusNumber = (double)$airInfo['convert_dnc_number'];
        $maxNumber = $usedNumber + $surplusNumber; // 最多兑换数量

        $convertCurrencyId = Config::get_value('price_line_currency');
        $toCurrency = (new Currency)->where('currency_id', $convertCurrencyId)->field('currency_id, currency_name')->find();
        $currency = (new Currency)->where('currency_id', $airInfo['currency_id'])->field('currency_id, currency_name')->find();
        $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $airInfo['currency_id']);
        // 兑换比例
        $currencyPrice = CurrencyPriceTemp::get_price_currency_id($airInfo['currency_id'], $this->exchange_rate_type);
        $toCurrencyPrice = DcPrice::getPrice(todayBeginTimestamp(), $this->exchange_rate_type);
        $radio = $currencyPrice / $toCurrencyPrice;
        $fee = ((double)Config::get_value('dc_convert_fee_ratio') * .01);
        $minLimit = Config::get_value('dc_convert_min_num');

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'max_number' => $maxNumber,
            'used_number' => $usedNumber,
            'surplus_number' => $surplusNumber,
            'currency_price' => $currencyPrice,
            'currency' => $currency,
            'currency_balance' => (double)$currencyUser['num'],
            'to_currency_info' => $toCurrency,
            'to_currency_price' => $toCurrencyPrice,
            'unit' => $this->exchange_rate_type,
            'radio' => keepPoint($radio, 6),
            'fee' => $fee,
            'min_limit' => $minLimit
        ];
        return $this->output_new($r);
    }

    /**
     * 提交共振
     * @param Request $request
     * @return string
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     */
    public function convert(Request $request)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        $toNumber = $request->post('to_number', 0);
        $payPwd = $request->post('pay_pwd', null);
        if (!$toNumber or $toNumber < 0 or empty($payPwd)) {
            return $this->output_new($r);
        }

        // 安全密码验证
        $verifyPwd = Member::verifyPaypwd($this->member_id, $payPwd);
        if ($verifyPwd['code'] != SUCCESS) {
            return $this->output_new($verifyPwd);
        }

        $airInfo = $this->userAirLevel->getAirInfo($this->member_id);
        if (empty($airInfo)) {
            $r['message'] = lang('level_not_exist');
            return $this->output_new($r);
        }


        // $usedNumber = $this->resonanceLog->getUsedNumber($this->member_id);
        $surplusNumber = $airInfo['convert_dnc_number'];
        // $maxNumber = $usedNumber + $surplusNumber;

        if ($toNumber > $surplusNumber) {
            $r['message'] = lang('insufficient_convert_num');
            return $this->output_new($r);
        }

        $minLimit = Config::get_value('dc_convert_min_num');
        if ($minLimit > 0 and $toNumber < $minLimit) {
            $r['message'] = lang('flop_min_num', ['num' => $minLimit]);
            return $this->output_new($r);
        }

        // 添加共振记录
        try {
            Db::startTrans();
            // 余额判断
            $radio = $this->getRadio($airInfo['currency_id'], $this->exchange_rate_type);
            $fee = ((double)Config::get_value('dc_convert_fee_ratio') * 0.01);
            $number = keepPoint($toNumber / $radio, 6); // XRP+数量
            $feeNumber = $number * $fee; // 手续费数量
            $totalNumber = $number + $feeNumber;

            $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $airInfo['currency_id']);
            if ($currencyUser['num'] < $totalNumber) {
                $r['message'] = lang('maybe_radio_error');
                return $this->output_new($r);
            }

            $toCurrencyId = Config::get_value('price_line_currency');

            $resonanceLog = ResonanceLog::add_log($this->member_id, $airInfo['currency_id'], $toCurrencyId, $number, $toNumber, $feeNumber, $radio);

            if (empty($resonanceLog)) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            // 账本 & 资产变动
            $accountBook = AccountBook::add_accountbook($this->member_id, $airInfo['currency_id'], AccountBookType::SUB_FOR_RESONANCE,
                'sub_for_resonance', 'out', $number, $resonanceLog, $feeNumber);

            if (empty($accountBook)) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, $airInfo['currency_id']);
            $currencyUser['num'] -= $totalNumber;
            if (!$currencyUser->save()) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            $toAccountBook = AccountBook::add_accountbook($this->member_id, $toCurrencyId, AccountBookType::ADD_FOR_RESONANCE,
                'add_for_resonance', 'in', $toNumber, $resonanceLog);
            if (empty($toAccountBook)) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            $toCurrencyUser = CurrencyUser::getCurrencyUser($this->member_id, $toCurrencyId);
            $toCurrencyUser['num'] += $toNumber;
            if (!$toCurrencyUser->save()) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            $airInfo['convert_dnc_number'] -= $toNumber;
            if (!$airInfo->save()) {
                throw new Exception('system_error_please_try_again_later');
            }

            // End
            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('success_operation');
            $r['result'] = $toNumber;
            return $this->output_new($r);
        } catch (\Exception $e) {
            Db::rollback();
            $r['code'] = ERROR3;
            $r['message'] = $e->getMessage() . $e->getFile() . $e->getLine();
            return $this->output_new($r);
        }
    }

    /**
     * 互转列表
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function transferList()
    {
        $list = (new Currency)->field('currency_id, currency_name, currency_logo')->where('currency_id', 'in', [/*Currency::XRP_PLUS_ID,*/ Currency::DNC_ID])->select();
        foreach ($list as &$item) {
            $item['type'] = Currency::TRANSFER_FIELD_TYPE[$item['currency_id']];
        }
        return $this->output_new([
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => $list
        ]);
    }

    /**
     * 互转配置
     * @param Request $request
     */
    public function transferConfig(Request $request)
    {
        $currencyId = $request->post('currency_id', 0, 'intval');
        $type = $request->post('type', 'num', 'strval');
        $result = CurrencyUserTransfer::get_config($this->member_id, $currencyId, $type);
        return $this->output_new($result);
    }


    /**
     * 资产互转
     * @param Request $request
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws PDOException
     */
    public function transfer(Request $request)
    {
        $currencyId = $request->post('currency_id', 0, 'intval');
        $type = $request->post('type', 'num', 'strval');
        $targetUserId = $request->post('target_user_id', 0, 'intval');
        $targetAccount = $request->post('target_account', '', 'strval');
        $num = input('num', 0);
        $memo = strval(input('memo', ''));
        $result = CurrencyUserTransfer::transfer($this->member_id, $currencyId, $targetUserId, $targetAccount, $num, $type, $memo);
        $this->output_new($result);
    }

    /**
     * 奖励列表
     * @param Request $request
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function rewards(Request $request)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        $type = $request->post('type', 'recommend');
        $page = $request->post('page', 1, 'intval');
        $rows = $request->post('row', 10, 'intval');

        if (!in_array($type, ['recommend', 'diff', 'jackpot']) or !$page or !$rows) {
            return $this->output_new($r);
        }

        switch ($type) {
            case 'diff':
                // 级差奖
                $data = $this->userAirDiffDay->field('user_id, number, fee, currency_id, add_time')->with('currency')->where('user_id', $this->member_id)->page($page, $rows)->order('id', 'desc')->select();
                break;
            case 'jackpot':
                $data = $this->userAirJackpot->field('user_id, number, fee, currency_id, add_time')->with('currency')->where('user_id', $this->member_id)->page($page, $rows)->order('id', 'desc')->select();
                break;
            case 'recommend':
            default:
                $data = $this->userAirRecommend->field('user_id, award_number as number, fee, currency_id, add_time, recommend_user_id')->where('award_number', 'gt', 0)->with(['currency', 'recommendUser'])->where('user_id', $this->member_id)->order('id', 'desc')->page($page, $rows)->select();
        }
        if (count($data) <= 0) {
            $r['message'] = lang('lan_not_data');
            return $this->output_new($r);
        }

        $typeEnum = [
            'recommend' => '分享奖',
            'diff' => '阶梯奖',
            'jackpot' => '周分红'
        ];
        $typeImgEnum = [
            'recommend' => 'https://io-app.oss-cn-shanghai.aliyuncs.com/share.png',
            'diff' => 'https://io-app.oss-cn-shanghai.aliyuncs.com/share.png',
            'jackpot' => 'https://io-app.oss-cn-shanghai.aliyuncs.com/share.png',
        ];
        foreach ($data as &$item) {
            $item['add_time'] = date("Y-m-d H:i", date($item['add_time']));
            $item['number'] = (double)$item['number'];
            $item['fee'] = (double)$item['fee'];
            $item['name'] = $typeEnum[$type];
            $item['image'] = $typeImgEnum[$type];
            $item['total_number'] = (double)bcadd($item['number'], $item['fee'], 6);
            if ($type == 'recommend') {
                $account = $item['recommendUser']['email'] ? $item['recommendUser']['email'] : $item['recommendUser']['phone'];
                $item['name'] = $item['name'] . '-' . $account;
                unset($item['recommend_user']);
                unset($item['recommendUser']);
                unset($item['recommend_user_id']);
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('lan_data_success');
        $r['result'] = $data;
        return $this->output_new($r);
    }

    public function airNum(Request $request)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_not_data'),
            'result' => null
        ];
        $page = $request->post('page', 1, 'intval');
        $rows = $request->post('row', 10, 'intval');

        $typeEnum = [
            'flop' => '方舟攒入',
            'hongbao' => '锦鲤攒入',
            'air' => '云梯入金'
        ];
        $inType = ['flop', 'hongbao','space_recommand','space_release','space_power'];

        $data = HongbaoAirNumLog::where('user_id', $this->member_id)->field('number, type, create_time, currency_id')->order('id', 'desc')->page($page, $rows)->select();
        if (count($data) < 1) {
            return $this->output_new($r);
        }
        $currencyName = Currency::where('currency_id', $data[0]['currency_id'])->value('currency_name');
        foreach ($data as &$item) {
            $item['create_time'] = date("Y-m-d H:i", date($item['create_time']));
            $item['number'] = (double)$item['number'];
            $item['title'] = isset($typeEnum[$item['type']]) ? $typeEnum[$item['type']] : lang($item['type']).'攒入';
            $item['currency_name'] = $currencyName;

            $item['operator'] = in_array($item['type'], $inType) ? "+" : "-";
            unset($item['type']);
            unset($item['currency_id']);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $data;
        return $this->output_new($r);
    }

    /**
     * 共振记录
     * @param Request $request
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function convertLog(Request $request)
    {
        $page = $request->post('page', 1);
        $rows = $request->post('row', 10);
        $list = $this->resonanceLog->field('id, currency_id, to_currency_id, member_id, number, to_number, fee, radio, add_time')->page($page, $rows)->where('member_id', $this->member_id)->order('id', 'desc')->with(['currency', 'toCurrency'])->select();
        if (count($list) <= 0) {
            return $this->output_new([
                'code' => ERROR1,
                'message' => lang('no_data'),
                'result' => null
            ]);
        }
        foreach ($list as &$item) {
            $item['add_time'] = date("Y-m-d H:i", $item['add_time']);
            $item['number'] = (double)$item['number'];
            $item['to_number'] = (double)$item['to_number'];
            $item['fee'] = (double)$item['fee'];
            $item['total_number'] = bcadd($item['number'], $item['fee'], 6);
        }
        return $this->output_new([
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => $list
        ]);
    }


    public function dncInfo(Request $request)
    {
        $currencyUser = CurrencyUser::getCurrencyUser($this->member_id, Currency::DNC_ID);
        $air = $this->userAirLevel->getAirInfo($this->member_id);
        $currency_name = Currency::where('currency_id', Currency::DNC_ID)->value('currency_name');
        return $this->output_new([
            'code' => SUCCESS,
            'success' => lang('data_success'),
            'data' => [
                'dnc_num' => (double)$currencyUser['num'],
                'dnc_lock' => (double)$currencyUser['dnc_lock'],
                'level_name' => $air['level']['name'],
                'currency_name' => $currency_name,
            ]
        ]);
    }


    public function share(Request $request)
    {
        $data = $this->memberModel->where('member_id', $this->member_id)->field('member_id, email, phone, invit_code')->find();
        if ($data['email']) {
            $account = $this->hideStr($data['email']);
        } else {
            $account = $this->hideStr($data['phone']);
        }
        $data['account'] = $account;

        $data['invit_url'] = url('mobile/Reg/mobile', ['invit_code' => $data['invit_code']], false, true);

        unset($data['email']);
        unset($data['phone']);
        return $this->output_new([
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => $data
        ]);
    }


    public function hideStr($str)
    {
        if (strpos($str, '@')) {
            $email_array = explode("@", $str);
            //邮箱前缀
            $prevfix = (strlen($email_array[0]) < 4) ? "" : substr($str, 0, 3);
            $count = 0;
            $str = preg_replace('/([\d\w+_-]{0,100})@/', '***@', $str, -1, $count);
            $rs = $prevfix . $str;
        } else {
            //正则手机号
            $pattern = '/(1[3458]{1}[0-9])[0-9]{4}([0-9]{4})/i';
            if (preg_match($pattern, $str)) {
                $rs = preg_replace($pattern, '$1****$2', $str); // substr_replace($name,'****',3,4);
            } else {
                $rs = substr($str, 0, 3) . "***" . substr($str, -1);
            }
        }
        return $rs;
    }


    /**
     * 发送验证码
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * Create by: Red
     * Date: 2019/9/6 11:42
     */
    public function code()
    {
        //图片验证验证码
//        $mark=input("post.validate");
//        if (!$this->verifyCaptcha($mark)){
//            return $this->output(10001,lang('lan_Picture_verification_refresh'));
//        }

        $type = input("post.type", "", "strval");

        if (empty($type) or $type != 'air') {
            return $this->output_new([
                'code' => ERROR1,
                'message' => lang('parameter_error'),
                'result' => null
            ]);
        }


        $phone_user = Db::name('member')->field('member_id, login_type, email, phone,country_code')->where('member_id', $this->member_id)->find();
        if ($phone_user['login_type'] == 1) {
            $phone = $phone_user['phone'];
            $country_code = $phone_user['country_code'];

            $result = model('Sender')->send_phone($country_code, $phone, $type);
            if (is_string($result)) $this->output(10001, $result);
        } elseif ($phone_user['login_type'] == 2) { // 邮箱
            $email = $phone_user['email'];
            $this->email_send($email, $type);
        }
        // login_type


        $this->output(10000, lang('lan_user_send_success'));
    }

    public function community()
    {
        $lowersCount = $this->memberBind->where('member_id', $this->member_id)->count();
        $airInfo = $this->userAirLevel->getAirInfo($this->member_id);


        return $this->output_new([
            'code' => SUCCESS,
            'message' => lang('data_success'),
            'result' => [
                'lowers_count' => $lowersCount,
                'income' => (double)$airInfo['income'],
                'team_income' => (double)$airInfo['team_income'],
                'can_edit' => $airInfo['can_edit'],
                'level_name' => $airInfo['level']['name'],
                'currency_name' => $airInfo['currency']['currency_name']
            ]
        ]);
    }

    public function matchLower(Request $request)
    {
        $childId = $request->post('child_id', null, 'intval');
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if (empty($childId)) {
            return $this->output_new($r);
        }

        $flag = $this->memberBind->where('member_id', $this->member_id)->where('child_id', $childId)->value('member_id');
        $matchBoolean = false;
        if (!empty($flag) and $flag == $this->member_id) {
            $matchBoolean = true;
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $matchBoolean;
        return $this->output_new($r);
    }

    public function lowers(Request $request)
    {
        $page = $request->post('page', 1);
        $rows = $request->post('row', 10);

        $data = $this->memberBind->alias('mb')
            ->field('mb.child_id, ual.income, ual.team_income, m.email, m.phone, all.name')
            ->where('mb.member_id', $this->member_id)
            ->where('mb.level', 1)
            ->join(config('database.prefix') . 'user_air_level ual', 'mb.child_id = ual.user_id', 'LEFT')
            ->join(config('database.prefix') . 'member m', 'mb.child_id = m.member_id', 'LEFT')
            ->join(config('database.prefix') . 'air_ladder_level all', 'ual.level_id = all.id', 'LEFT')
            ->page($page, $rows)
            ->select();

        if (count($data) <= 0) {
            return $this->output_new([
                'code' => ERROR1,
                'message' => lang('lan_not_data'),
                'result' => []
            ]);
        }

        try {
            /**
             * @var Redis $handler
             */
            $handler = Cache::store('redis')->handler();
            $today = date("Ymd");
            $key = "{$today}:level";
            foreach ($data as &$item) {
                if ($item['phone']) {
                    $account = $item['phone'];
                } else {
                    $account = $item['email'];
                }
                $item['account'] = $account;
                $item['total_number'] = (int)bcadd($item['income'], $item['team_income'], 6);
                $field = "user_id:{$this->member_id}:child_id:{$item['child_id']}";
                $item['satisfy_level'] = $handler->hExists($key, $field) ? $handler->hGet($key, $field) : null;
                $item['name'] = $item['name'] == null ? "D0" : $item['name'];
                unset($item['email']);
                unset($item['phone']);
                unset($item['income']);
                unset($item['team_income']);
            }

            return $this->output_new([
                'code' => SUCCESS,
                'message' => lang('data_success'),
                'result' => $data
            ]);
        } catch (\Exception $exception) {
            return $this->output_new([
                'code' => ERROR2,
                'message' => lang('system_error_please_try_again_later'),
                'result' => null
            ]);
        }
    }

    public function childInfo(Request $request)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        $childId = $request->post('child_id', null, 'intval');
        $childAccount = $request->post('child_account', null);
        if (empty($childId) or empty($childAccount)) {
            return $this->output_new($r);
        }

        $flag = $this->memberBind->where('member_id', $this->member_id)->where('child_id', $childId)->where('level', 1)->find();
        if (empty($flag)) {
            $r['message'] = lang('child_info_mismatch');
            return $this->output_new($r);
        }

        $child = $this->memberModel->field('member_id, phone, email')->where('member_id', $childId)->find();
        if ($childAccount != $child['phone'] and $childAccount != $child['email']) {
            $r['message'] = lang('child_info_mismatch');
            return $this->output_new($r);
        }

        $childAir = $this->userAirLevel->getAirInfo($childId, true);
        // $levels = [];
        $minEditLevel = $childAir['level_id'];
        $maxLevel = Config::get_value('air_edit_max_level_id');
        $airInfo = $this->userAirLevel->getAirInfo($this->member_id);
        if ($airInfo['can_edit'] != 2) {
            $r['message'] = lang('votes_no_authority');
            return $this->output_new($r);
        }
        $maxEditLevel = min($airInfo['level_id'] - 1, $maxLevel);

        $levels = $this->airLadder->where('id', 'gt', $minEditLevel)->field('id, name')->where('id', 'elt', $maxEditLevel)->select();
        if (count($levels) <= 0) {
            $r['message'] = lang('air_upgrade_levels');
            return $this->output_new($r);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = [
            'level_name' => $childAir['level']['name'],
            'income' => (double)$childAir['income'],
            'levels' => $levels
        ];
        return $this->output_new($r);
    }

    public function editLower(Request $request)
    {
        $levelId = $request->post('level_id', null, 'intval');
        $childId = $request->post('child_id', null, 'intval');
        $childAccount = $request->post('child_account', null);
        $payPwd = $request->post('pay_pwd', null);

        $r = [
            'code' => ERROR1,
            'message' => lang('lan_modifymember_parameter_error'),
            'result' => null
        ];
        if (empty($levelId) or empty($childId) or empty($childAccount) or empty($payPwd)) {
            return $this->output_new($r);
        }

        try {
            Db::startTrans();
            $res = Member::verifyPaypwd($this->member_id, $payPwd);
            if (SUCCESS != $res['code']) {
                throw new Exception($res['message']);
            }
            $airInfo = $this->userAirLevel->getAirInfo($this->member_id);
            if ($airInfo['can_edit'] != 2) {
                throw new Exception(lang('votes_no_authority'));
            }

            $flag = $this->memberBind->where('member_id', $this->member_id)->where('child_id', $childId)->where('level', 1)->find();
            if (empty($flag)) {
                throw new Exception(lang('child_info_mismatch'));
            }

            $child = $this->memberModel->field('member_id, phone, email')->where('member_id', $childId)->find();
            if ($childAccount != $child['phone'] and $childAccount != $child['email']) {
                throw new Exception(lang('child_info_mismatch'));
            }

            $childAir = $this->userAirLevel->getAirInfo($childId, true);

            if ($childAir['level_id'] >= $levelId) {
                throw new Exception(lang('air_cannot_demote'));
            }

            if ($childAir['income'] < 5000) {
                throw new Exception(lang('air_child_income_number_limit', ['num' => 5000]));
            }


            $maxLevel = Config::get_value('air_edit_max_level_id');
            $realLevelMax = min($airInfo['level_id'] - 1, $maxLevel);

            if ($levelId > $realLevelMax) {
                $levelName = (string)$realLevelMax - 1;
                throw new Exception(lang('max_edit_level_limit', ['level' => 'D' . $levelName]));
            }

            $flag = AirEditLevelLog::add_log($this->member_id, $childId, $levelId);
            if (empty($flag)) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            $childAir['level_id'] = $levelId;

            if (!$childAir->save()) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_operation_success');
            Db::commit();
        } catch (\Exception $exception) {
            $r['code'] = ERROR2;
            $r['message'] = $exception->getMessage();
            Db::rollback();
        }

        return $this->output_new($r);
    }


    /**
     * 发邮件
     * @param $email
     * @param $type
     * Create by: Red
     * Date: 2019/9/6 11:48
     */
    private function email_send($email, $type)
    {
        if ((empty($email) && $this->checkLogin())) {
            $email_user = Db::name('member')->where(['member_id' => $this->member_id])->value('email');
            if ($email_user) $email = $email_user;
        }

        if (empty($email) || !checkEmail($email)) $this->output(10001, lang('lan_emial_format_incorrect'));

        $result = model('Sender')->send_email($email, $type);
        if (is_string($result)) $this->output(10001, $result);

        $this->output(10000, lang('lan_reg_mailbox_sent'));
    }


    /**
     * 获取currency和DNC的价格比
     * @param int $currencyId
     * @param string $unit 暂时废弃
     * @param boolean $reversal 反转比例
     * @throws Exception
     * @return string
     */
    protected function getRadio($currencyId, $unit = "CNY", $reversal = true)
    {
//        $currencyPrice = CurrencyPriceTemp::get_price_currency_id($currencyId, $unit);
//        $toCurrencyPrice = DcPrice::getPrice(todayBeginTimestamp(), $unit);

//        return keepPoint($currencyPrice / $toCurrencyPrice, 6);
        $bbCurrencyId = Currency::where('trade_transfer_currency', Currency::DNC_ID)->value('currency_id');
        $tradeBbCurrencyId = Currency::where('trade_transfer_currency', $currencyId)->value('currency_id');
        $radio = Trade::getYestodayMaxPrice($bbCurrencyId, $tradeBbCurrencyId);
//        if ($radio == 0) {
//            // 最日最高价获取不到使用收盘价
//            $radio = Trade::getYestodayLastTradePrice($bbCurrencyId, $tradeBbCurrencyId);
//        }
        if ($radio <= 0) {
            throw new Exception(lang('system_error_please_try_again_later'));
        }
        if ($reversal) {
            return (double)keepPoint(1 / $radio, 6);
        }

        return $radio;
    }

}
