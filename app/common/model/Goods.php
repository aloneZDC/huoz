<?php

namespace app\common\model;

use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Model;

class Goods extends Model
{
    const HOT_GOODS = 2;

    const STATUS_UP = 1;
    const STATUS_DOWN = 2;
    const STATUS_DEL = 3;

    public function giveCurrency()
    {
        return $this->belongsTo(Currency::class, 'goods_currency_give_id')->field('currency_id, currency_name');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'goods_currency_id')->field('currency_id, currency_name');
    }

    public function otherCurrency()
    {
        return $this->belongsTo(Currency::class, 'goods_currency_other_id')->field('currency_id, currency_name');
    }

    public static function formatGoods(&$goods)
    {
        $goods['goods_price'] = floatval($goods['goods_price']);
        $goods['goods_currency_num'] = floatval($goods['goods_currency_num']);
        $goods['goods_currency_other_num'] = floatval($goods['goods_currency_other_num']);
        $goods['goods_market'] = floatval($goods['goods_market']);
        $goods['goods_postage'] = floatval($goods['goods_postage']);
    }


    /**
     * 获取商品列表
     * @param int $page
     * @param int $rows
     * @param string|null $keyword
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function get_goods_list($page = 1, $rows = 10, $cat_id, $children_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($page) && $rows <= 50) {
            $where = [];

            if ($children_id <= 0) {
                $get_category_list = GoodsCategory::where(['pid' => $cat_id, 'status' => 1])->column('id');
                $where['category_id'] = ['in', $get_category_list];
            } else {
                $where['category_id'] = $children_id;
            }

            if (!empty($keyword)) {
                $where['goods_title'] = ['like', "%{$keyword}%"];
            }

            $field = "goods_id,goods_title,goods_img,goods_price,
            goods_currency_give_id,goods_currency_give_num";
//           goods_postage,goods_market, goods_currency_id, goods_currency_num, goods_currency_type, goods_currency_other_id, goods_currency_other_num, goods_currency_other_type,  goods_sale_number,goods_desc
            $list = self::where(['goods_status' => self::STATUS_UP, 'goods_stock' => ['egt', 1]])->where($where)
//                ->with(['currency', 'other_currency'])
                ->page($page, $rows)->field($field)->order("goods_sort asc")->select();
            if (!empty($list)) {
                $hm_price = ShopConfig::get_value('hm_price', 6.1);
                foreach ($list as &$value) {
                    $value = $value->toArray();
                    $img = !empty($value['goods_img']) ? explode(",", $value['goods_img']) : null;
                    $value['goods_img'] = !empty($img) ? $img[0] : null;
                    $value['goods_price'] = floatval($value['goods_price']);
                    $value['hm_price'] = $hm_price;
//                    $value['goods_currency_num'] = floatval($value['goods_currency_num']);
//                    $value['goods_currency_other_num'] = floatval($value['goods_currency_other_num']);
//                    $value['goods_price_after_coupon'] = floatval($value['goods_currency_num']);
//                    $value['goods_market'] = floatval($value['goods_market']);
//                    $value['goods_postage'] = floatval($value['goods_postage']);
                    $value['goods_currency_give_name'] = Currency::where(['currency_id' => $value['goods_currency_give_id']])->value('currency_name', '火米');
                    unset($value['goods_currency_give_id']);
//                   $value['other_currency']['currency_name'] = ShopPayType::TYPE_ENUM[$value['goods_currency_other_type']];
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("no_data");
            }
        }
        return $r;
    }

    /**
     * 获取商品详情
     * @param int $goods_id 商品id
     * @param int $member_id 用户id
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function get_goods_details($goods_id, $member_id)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($goods_id)) {
            $field = "goods_id,goods_title,goods_img,goods_price,
              goods_banners, goods_sale_number, goods_stock, goods_content, goods_desc, goods_market, goods_postage, 
              goods_currency_give_id, goods_currency_give_num,category_id";
            $find = Goods::where(['goods_id' => $goods_id, 'goods_status' => Goods::STATUS_UP])->field($field)
                ->find();

            if ($find) {
                $find['goods_price'] = floatval($find['goods_price']);
                $find['goods_img'] = !empty($find['goods_img']) ? explode(",", $find['goods_img']) : null;
                $find['goods_content'] = !empty($find['goods_content']) ? html_entity_decode($find['goods_content']) : null;
                $find['goods_banners'] = $find['goods_banners'] ? json_decode($find['goods_banners']) : null;

                $goods_category = GoodsCategory::where(['id' => $find['category_id']])->find();
                $find['category_pid'] = $goods_category['pid'];
                if ($goods_category['pid'] == 2) {
                    $find['goods_postage'] = 0;
                }
                $find['hm_price'] = ShopConfig::get_value('hm_price', 6.1);

                $find['goods_market'] = floatval($find['goods_market']);
                $find['goods_currency_give_name'] = Currency::where(['currency_id' => $find['goods_currency_give_id']])->value('currency_name', '火米');
                unset($find['goods_currency_give_id'], $find['category_id']);

                $find['format_list'] = GoodsFormat::get_format_list($goods_id);
                $buy_shop_num = RocketMember::where(['member_id' => $member_id])->value('buy_shop_num');
                $find['buy_shop_num'] = $buy_shop_num ? 1 : 0;//新人专区购买数量

                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $find;
            } else {
                $r['message'] = lang("no_data");
            }
        }
        return $r;
    }

    /**
     * 申请信用额度
     * @param $member_id
     * @param $num
     * @return array
     * @throws \think\exception\PDOException
     */
    static function huo_mi_apply($member_id, $num)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;

        if (empty($member_id) || empty($num)) {
            return $r;
        }

        $currency_user = CurrencyUser::getCurrencyUser($member_id, Currency::USDT_ID);
        if ($currency_user['num_award'] < $num) {
            $r['message'] = lang("insufficient_balance");
            return $r;
        }

        try {
            self::startTrans();
            $num_award = $num;

            //抱彩分红
            $welfare_rate = RocketConfig::getValue('welfare_rate');
            if ($welfare_rate) {
                $welfare_num = sprintf('%.6f', $num * ($welfare_rate / 100));
                $flag = RocketWelfare::addItem($welfare_num);
                if (!$flag) throw new Exception(lang("添加抱彩分红失败") . '-' . __LINE__);

                $flag = CurrencyLockBook::add_log('num_award', 'convert', $member_id, Currency::USDT_ID, $welfare_num, 0);
                if (!$flag) throw new Exception(lang("operation_failed_try_again") . '-' . __LINE__);

                $flag = AccountBook::add_accountbook($member_id, $currency_user['currency_id'], 7121, "register_reward4", "out", $welfare_num,0);
                if (!$flag) throw new Exception(lang("operation_failed_try_again") . '-' . __LINE__);

                $num = sprintf('%.6f', $num - $welfare_num);
            }

            $flag = CurrencyLockBook::add_log('num_award', 'release', $member_id, Currency::USDT_ID, $num, 0);
            if (!$flag) throw new Exception(lang("operation_failed_try_again") . '-' . __LINE__);

            $flag = AccountBook::add_accountbook($member_id, $currency_user['currency_id'], 123, "shopping", "in", $num,0);
            if (!$flag) throw new Exception(lang("operation_failed_try_again") . '-' . __LINE__);

            //更新锁仓资产
            $flag = CurrencyUser::where(['cu_id' => $currency_user['cu_id'], 'num_award' => $currency_user['num_award']])->update([
                'num_award' => ['dec', $num_award],
                'num' => ['inc', $num]
            ]);
            if (!$flag) throw new Exception(lang('operation_failed_try_again') . '-' . __LINE__);

            self::commit();
            $r['message'] = lang("successful_operation");
            $r['code'] = SUCCESS;
        } catch (Exception $exception) {
            self::rollback();
            $r['message'] = $exception->getMessage();
        }
        return $r;
    }
}