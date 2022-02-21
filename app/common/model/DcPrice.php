<?php


namespace app\common\model;


use PDOStatement;
use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;

class DcPrice extends Model
{

    public static function getPrice($timestamp, $type = "CNY")
    {
        $today = self::where('add_time', $timestamp)->find();
        if (empty($today)) {
            return self::getPrice($timestamp - 86400, $type);
        }
        switch ($type) {
            case "USD":
                $usdtCNYPrice = CurrencyPriceTemp::get_price_currency_id(Currency::USDT_ID, "CNY");
                return keepPoint($today['price'] / $usdtCNYPrice, 6);
            case "CNY":
            default:
                return $today['price'];
        }
    }

    /**
     * 获取价格走势图数据
     * @param string $type
     * @param int $page
     * @param int $rows
     * @return false|PDOStatement|string|Collection
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function getLine($type = 'weeks', $page = 1, $rows = 3)
    {
        switch ($type) {
            case 'weeks':
            case 'w':
                return $this->field(
                    "FROM_UNIXTIME(add_time, '%Y%u') only_mark, avg(price) price, min(price) low, max(price) high, count(id) day_count, FROM_UNIXTIME(min(add_time), '%Y-%m-%d') start_time, FROM_UNIXTIME(max(add_time), '%Y-%m-%d') end_time")
                    ->order('only_mark', 'desc')
                    ->page($page, $rows)
                    ->group("only_mark")
                    ->select();
            case 'months':
            case 'm':
            return $this->field(
                "FROM_UNIXTIME(add_time, '%Y%m') only_mark, avg(price) price, min(price) low, max(price) high, count(id) day_count, FROM_UNIXTIME(min(add_time), '%Y-%m-%d') start_time, FROM_UNIXTIME(max(add_time), '%Y-%m-%d') end_time")
                ->order('only_mark', 'desc')
                ->page($page, $rows)
                ->group("only_mark")
                ->select();
            case 'days':
            case 'd':
            default:
                // FROM_UNIXTIME(add_time, "%Y-%m-%d")
                return $this->field('add_time date, price steps')->order('add_time', 'asc')->limit(500)/*->page($page, $rows)*/->select();
        }
    }
}