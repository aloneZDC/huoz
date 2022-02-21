<?php


namespace app\common\model;


use think\Db;
use think\Exception;
use think\Model;

class AirDncGifts extends Model
{
    /**
     * @var int 未领取
     */
    const NOT_TAKE = 1;

    /**
     * @var int 已领取
     */
    const ALREADY_TAKE = 2;

    /**
     * @var int 礼包（进入可用）
     */
    const TYPE_GIFT = 1;

    /**
     * @var int 入金空投（进入锁仓）
     */
    const TYPE_AIRDROP = 2;

    /**
     * @var int 每日空投 (进入锁仓)
     */
    const TYPE_EVERYDAY_AIRDROP = 3;

    /**
     * 添加礼物
     * @param int $userId
     * @param int $currencyId
     * @param double $number
     * @param int $type
     * @return int|string
     */
    public static function addGift($userId, $currencyId, $number, $type = self::TYPE_GIFT)
    {
        return (new self)->insertGetId([
            'user_id' => $userId,
            'currency_id' => $currencyId,
            'number' => $number,
            'is_take' => self::NOT_TAKE,
            'type' => $type,
            'add_time' => time()
        ]);
    }

    /**
     * 每日礼包
     * @param int $userId
     * @param double $number
     * @return void
     */
    public static function everyAirdrop($userId, $number)
    {
        // 今天是否拿过奖励
        $flag = (new self)->where('user_id', $userId)
            ->where('type', self::TYPE_EVERYDAY_AIRDROP)
            ->whereTime('add_time', 'today')
            ->value('id');

        if (empty($flag)) {
            self::addGift($userId, Currency::DNC_ID, $number, self::TYPE_EVERYDAY_AIRDROP);
        }
    }

    /**
     * 领取礼物
     * @param int $id
     * @param int $userId
     * @return array
     */
    public static function takeGift($id, $userId)
    {
        $r = [
            'code' => ERROR1,
            'message' => lang('system_error_please_try_again_later'),
            'result' => null
        ];
        try {
            Db::startTrans();
            $gift = AirDncGifts::where('id', $id)
                ->where('user_id', $userId)
                ->where('is_take', AirDncGifts::NOT_TAKE)
                ->whereTime('add_time', '-24 hours')
                ->find();
            if (empty($gift)) {
                throw new Exception(lang('air_gift_not_exist'));
            }

            $currencyUser = CurrencyUser::getCurrencyUser($userId, $gift['currency_id']);
            switch ($gift['type']) {
                // 可用
                case self::TYPE_GIFT:
                    // 添加账本
                    $flag = AccountBook::add_accountbook($userId, $gift['currency_id'], AccountBookType::DNC_GIFT, 'dnc_gift', 'in', $gift['number'], $gift['id']);
                    if (empty($flag)) {
                        throw new Exception(lang('system_error_please_try_again_later'));
                    }
                    $currencyUser['num'] += $gift['number'];
                    break;
                // 锁仓
                case self::TYPE_EVERYDAY_AIRDROP:
                case self::TYPE_AIRDROP:
                    $logType = DcLockLog::TYPE_AIRDROP;
                    if ($gift['type'] == self::TYPE_EVERYDAY_AIRDROP) {
                        $logType = DcLockLog::TYPE_EVERYDAY_AIRDROP;
                    }

                    $flag = DcLockLog::add_log($logType, $userId, $gift['currency_id'], $gift['number'], $gift['id']);
                    if (empty($flag)) {
                        throw new Exception(lang('system_error_please_try_again_later'));
                    }
                    $currencyUser['dnc_lock'] += $gift['number'];
                    break;
                default:
                throw new Exception(lang('system_error_please_try_again_later'));
            }
            if (!$currencyUser->save()) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }

            $gift['is_take'] = self::ALREADY_TAKE;
            $gift['take_time'] = time();
            if (!$gift->save()) {
                throw new Exception(lang('system_error_please_try_again_later'));
            }
            Db::commit();
            $r['code'] = SUCCESS;
            $r['message'] = lang('lan_operation_success');
            $r['result'] = [
                'number' => (double)keepPoint($gift['number'], 2),
                'currency_name' => 'DNC',
                'type' => $gift['type']
            ];
            return $r;
        } catch (Exception $exception) {
            Db::rollback();
            $r['message'] = $exception->getMessage();
            return $r;
        }
    }
}