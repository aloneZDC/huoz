<?php


namespace app\common\model;


use think\Model;

class Loan extends Model
{
    const STATUS_WAIT = 1;
    const STATUS_SUCCESS = 3;
    const STATUS_FAIL = 2;

    const STATUS_ENUM = [
        self::STATUS_WAIT => '审核中',
        self::STATUS_FAIL => '拒绝',
        self::STATUS_SUCCESS => '成功'
    ];

    protected $table = "yang_loan";

    public function user()
    {
        return $this->belongsTo(Member::class, 'user_id', 'member_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class)->field('currency_id, currency_name, currency_mark');
    }

    /**
     * 是否是可审核状态
     * @param int $id
     * @return bool
     */
    public function isWaitStatus($id)
    {
        return $this->where('id', $id)->value('status') == self::STATUS_WAIT;
    }

    /**
     * 获取信用额度
     * 额度  =  （购买成交总量 / 18000） * 500  - 已申请成功的锁仓KOI
     * @param int $userId
     * @return int
     */
    public function getCreditQuota($userId)
    {
        $buyNumber = (new FlopTrade)->where('member_id', $userId)
            ->where('currency_id', Currency::KOI_ID)
            ->where('type', 'buy')
            ->sum('num');
        $lockNumber = $this->where('user_id', $userId)
            ->where('status', 'in', [self::STATUS_WAIT, self::STATUS_SUCCESS]) // 申请中 和 成功 的额度
            ->where('currency_id', Currency::KOI_ID)
            ->sum('money');

//        $creditQuota = (($buyNumber / 18000) * 500) - $lockNumber;
        $creditQuota = ($buyNumber / 18000) * 500;
        return (double) keepPoint($creditQuota, 6);
    }

    /**
     * 贷款申请
     * @param int $userId
     * @param double $money
     * @param double $lossMoney
     * @param string $lossProject
     * @return int|string
     */
    public function addApply($userId, $money, $lossMoney, $lossProject)
    {
        return $this->insertGetId([
            'user_id' => $userId,
            'currency_id' => Currency::KOI_ID,
            'money' => $money,
            'loss_money' => $lossMoney,
            'loss_project' => $lossProject,
            'status' => self::STATUS_WAIT,
            'create_time' => time()
        ]);
    }

    /**
     * 设置状态 success or fail
     * @param int $id
     * @param int $status 1审核中 2拒绝 3成功
     * @param string $reasonsRefusal 拒绝理由
     * @return Loan
     */
    public function setStatus($id, $status, $reasonsRefusal = '')
    {
        if ($status != 2) { // 拒绝的时候才有拒绝理由
            $reasonsRefusal = '';
        }
        return $this->where('id', $id)->update([
            'status' => $status,
            'reasons_refusal' => $reasonsRefusal
        ]);
    }


}