<?php


namespace app\common\model;


use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * Class DcLockLog
 * @package app\common\model
 */
class DcLockLog extends Model
{
    /**
     * @var string
     */
    const TYPE_AIR_GIVE = 'air_give';

    /**
     * @var string
     */
    const TYPE_TRANSFER_OUT = 'transfer_out';

    /**
     * @var string
     */
    const TYPE_TRANSFER_IN = 'transfer_in';

    /**
     * @var string
     */
    const TYPE_RELEASE = 'release';

    /**
     * @var string 系统结算
     */
    const TYPE_ERROR_GIVE = 'error_give';

    /**
     * @var string 系统赠送
     */
    const TYPE_SYSTEM_GIVE = 'system_give';

    /**
     * @var string DNC空投
     */
    const TYPE_AIRDROP = 'airdrop';

    /**
     * @var string 每日空投
     */
    const TYPE_EVERYDAY_AIRDROP = 'everyday_airdrop';

    const DNC_BACK = 'dnc_back';

    /**
     * @var string 加速释放
     */
    const TYPE_FAST_RELEASE = 'fast_release';

    /**
     * 币币交易释放
     */
    const TRADE_RELEASE = 'trade_release';

    /**
     * @var array
     */
    const TYPE_ENUM = [
        self::TYPE_AIR_GIVE => '云梯尊享',
        self::TYPE_TRANSFER_OUT => '互转转出',
        self::TYPE_TRANSFER_IN => '互转转入',
        self::TYPE_RELEASE => '每日释放',
        self::TYPE_ERROR_GIVE => '系统结算',
        self::TYPE_SYSTEM_GIVE => '系统赠送',
        self::TYPE_AIRDROP => '云梯爆礼',
        self::DNC_BACK => 'DNC缓存',
        self::TYPE_EVERYDAY_AIRDROP => '登陆爆礼',
        self::TYPE_FAST_RELEASE => '加速释放',
        self::TRADE_RELEASE => '交易加速释放',
    ];

    /**
     * @var array 收入类型
     */
    const TYPE_IN = [self::TYPE_AIR_GIVE, self::TYPE_TRANSFER_IN, self::TYPE_ERROR_GIVE, self::TYPE_SYSTEM_GIVE, self::TYPE_AIRDROP, self::DNC_BACK, self::TYPE_EVERYDAY_AIRDROP];

    /**
     * @var array 支出类型
     */
    const TYPE_OUT = [self::TYPE_TRANSFER_OUT, self::TYPE_RELEASE, self::TYPE_FAST_RELEASE,self::TRADE_RELEASE];

    /**
     * @var array 所有类型
     */
    const ALL_TYPE = [
        self::TYPE_AIR_GIVE,
        self::TYPE_TRANSFER_OUT,
        self::TYPE_TRANSFER_IN,
        self::TYPE_SYSTEM_GIVE,
        self::TYPE_ERROR_GIVE,
        self::TYPE_RELEASE,
        self::TYPE_AIRDROP,
        self::DNC_BACK,
        self::TYPE_EVERYDAY_AIRDROP,
        self::TYPE_FAST_RELEASE,
        self::TRADE_RELEASE
    ];


    const ASSESS_TYPE_DNC_LOCK = 1;

    const ASSESS_TYPE_DNC_OTHER_LOCK = 2;


    const ASSESS_TYPE_ENUM = [
        self::ASSESS_TYPE_DNC_LOCK => 'dnc_lock',
        self::ASSESS_TYPE_DNC_OTHER_LOCK => 'dnc_other_lock'
    ];

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(Member::class, 'user_id');
    }

    /**
     * @return BelongsTo
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->field('currency_id, currency_name');
    }

    /**
     * 释放锁仓
     * @param int $userId 用户ID
     * @param double $number 释放数量
     * @param string $type 释放类型
     * @param int $accountBookTypeId 账本类型
     * @param string $content 账本content
     * @param int $assessType 资产类型
     * @return array
     */
    public function release($userId, $number, $assessType = self::ASSESS_TYPE_DNC_LOCK, $type = self::TYPE_RELEASE, $accountBookTypeId = AccountBookType::DNC_RELEASE, $content = 'dnc_release') {
        $r = [
            'code' => ERROR1,
            'message' => ("parameter_error"),
            'result' => null
        ];
        if (empty($userId) or $number <= 0) {
            return $r;
        }
        $userCurrency = CurrencyUser::getCurrencyUser($userId, Currency::DNC_ID);
        if (empty($userCurrency)) {
            return $r;
        }
        $assessTypeString = self::ASSESS_TYPE_ENUM[$assessType];
        if ($userCurrency[$assessTypeString] <= 0) {
            $r['code'] = SUCCESS;
            $r['message'] = ('success_operation');
            return $r;
        }

        if (bccomp($userCurrency[$assessTypeString], $number, 6) == -1) {
            $number = $userCurrency[$assessTypeString];
        }

        $flag = self::add_log($type, $userId, Currency::DNC_ID, $number, 0, $assessType);
        if (empty($flag)) {
            $r['message'] = ('system_error_please_try_again_later');
            return $r;
        }
        // var_dump($userCurrency['dnc_lock'], $number);die;
        $userCurrency[$assessTypeString] -= $number;

        // 账本
        $accountFlag = AccountBook::add_accountbook($userId, Currency::DNC_ID, $accountBookTypeId, $content, 'in', $number, 0);
        if (!$accountFlag) {
            $r['message'] = ('system_error_please_try_again_later');
            return $r;
        }
        $userCurrency['num'] += $number;
        // var_dump($userCurrency->toArray());
        if (!$userCurrency->save()) {
            $r['message'] = ('system_error_please_try_again_later');
            return $r;
        }
        $r['code'] = SUCCESS;
        $r['message'] = ('success');
        return $r;
    }

    /**
     * 添加DNC锁仓记录
     * @param string $type 类型（使用类常量）
     * @param int $userId 用户ID
     * @param int $currency_id 币种ID
     * @param double $number 数量
     * @param int $third_id 第三方表ID
     * @param int $assess_type 资产类型
     * @return int|string
     */
    public static function add_log($type, $userId, $currency_id, $number, $third_id = 0, $assess_type = self::ASSESS_TYPE_DNC_LOCK)
    {
        return (new DcLockLog)->insertGetId([
            'type' => $type,
            'user_id' => $userId,
            'currency_id' => $currency_id,
            'number' => $number,
            'third_id' => $third_id,
            'assess_type' => $assess_type,
            'create_time' => time()
        ]);
    }

    /**
     * 锁仓信息
     * @param int $userId
     * @return array
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public static function num($userId)
    {
        $currency = (new Currency)->where('currency_id', Currency::DNC_ID)->field('currency_id, currency_name, currency_logo')->find();
        $r = [
            'currency_name' => $currency['currency_name'],
            'keep_num' => 0,
            'cny' => 0,
            'currency_logo' => $currency['currency_logo'],
            'currency_id' => $currency['currency_id'],
            'exchange_switch' => 2,
            'convert_switch' => 2,
            'exchange_type' => 'dnc_lock'
        ];

        if ($currency) {
            $user_currency = CurrencyUser::getCurrencyUser($userId, $currency['currency_id']);
            if ($user_currency) {
                $r['keep_num'] = $user_currency['dnc_lock'] + $user_currency['dnc_other_lock'];
                // $r['currency_logo'] = $currency['currency_logo'];
            }
            $currency_price = CurrencyPriceTemp::get_price_currency_id($currency['currency_id'], 'CNY');
            if ($currency_price) $r['cny'] = keepPoint($currency_price * $r['keep_num'], 2);
            $transfer_config = (new CurrencyUserTransferConfig)->where('currency_id', $currency['currency_id'])->where('type', 'dnc_lock')->value('is_open');
            if (!empty($transfer_config)) {
                $r['exchange_switch'] = $transfer_config;
            }

            $convert_config = ConvertKeepConfig::where('to_currency_id',$currency['currency_id'])->find();
            if($convert_config) {
                $r['convert_switch'] = 1;
            }
        }
        return $r;
    }

    /**
     * 锁仓记录
     * @param int $user_id
     * @param string $type
     * @param int $page
     * @param int $rows
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public static function get_list($user_id, $type = '', $page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (!isInteger($user_id) or $rows > 100) {
            return $r;
        }
        $where = [];
        if (!empty($type)) {
            if ('ins' == $type) {
                $where['type'] = ['in', self::TYPE_IN];
            } elseif('out' == $type) {
                $where['type'] = ['in', self::TYPE_OUT];
            }
        }

        $field = "number, create_time, type, currency_id";
        $list = (new self)->field($field)->where($where)
            ->page($page, $rows)->order("id", 'desc')->where('user_id', $user_id)->with('currency')->select();
        if (empty($list)) {
            $r['message'] = lang('lan_No_data');
            return $r;
        }
        foreach ($list as &$item) {
            $item['title'] = self::TYPE_ENUM[$item['type']];
            if (in_array($item['type'], self::TYPE_IN)) {
                $item['number'] = '+' . $item['number'];
            } else {
                $item['number'] = '-' . $item['number'];
            }
            $item['currency_name'] = $item['currency']['currency_name'];
            $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
            unset($item['currency']);
            unset($item['currency_id']);
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }
}
