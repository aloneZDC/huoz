<?php
//兑换到赠送金配置

namespace app\common\model;


use think\Model;
use think\model\relation\BelongsTo;
use think\model\relation\HasMany;

/**
 * Class ConvertConfig
 * @package app\common\model
 */
class ConvertKeepConfig extends Model
{
    /**
     * @var string
     */
    protected $pk = "id";

    /**
     * @return BelongsTo
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id')->field('currency_id, currency_name as currency_mark');
    }

    /**
     * @return BelongsTo
     */
    public function toCurrency()
    {
        return $this->belongsTo(Currency::class, 'to_currency_id','currency_id')->field('currency_id, currency_name as currency_mark');
    }

    /**
     * @return HasMany
     */
    public function toConfig()
    {
        return $this->hasMany(ConvertKeepConfig::class, 'currency_id', 'currency_id')->field('id, currency_id, to_currency_id, day_max_num,ratio')->with('to_currency');
    }

    //获取兑换配置
    static function getConvertConfig($member_id,$currencyId) {
        $r['code'] = ERROR1;
        $r['message'] = lang("unopened_exchange");
        $r['result'] = null;

        $where = [];
        if ($currencyId>0) {
            $where['currency_id'] = $currencyId;
        }
        // day_max_num,
        $configs = self::where($where)->field('id, currency_id, fee')->with(['currency', 'to_config'])->group('currency_id')->select();
        if (empty($configs)) return $r;

        foreach ($configs as $key => &$config) {
            $currency_user = CurrencyUser::getCurrencyUser($member_id, $config['currency_id']);
            $config['number'] = $currency_user ? $currency_user['num'] : 0;

            foreach ($config['to_config'] as $k => $item) {
                $toDayNum = ConvertKeepLog::where([
                    'user_id' => $member_id,
                    'cc_id' => $item['id'],
                ])->whereTime('create_time', 'today')->sum('to_number'); // 今日兑换数量
                $item['day_max_num'] -= $toDayNum;
                $item['to_currency']['currency_mark'] .= lang('keep_log');
            }
        }

        $r['code'] = SUCCESS;
        $r['message'] = lang("data_success");
        $r['result'] = $configs;
        return $r;
    }
}
