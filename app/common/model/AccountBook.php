<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;

use think\Model;
use think\Db;
use think\Exception;

class AccountBook extends Base
{
    /**
     * 添加账本 (兼容砝码)
     * @param int $user_id 用户ID
     * @param int $currency_id 币种ID
     * @param int $abt_id accountBook_type表ID
     * @param string $content 语言包
     * @param string $type 类型 in 收入|out 支出
     * @param double $num 数量
     * @param int $third_id 第三方表ID
     * @param int|double $fee 手续费
     * @param null|int $fee_currency_id 手续费币种ID(无用)
     * @param int $other_user_id 目标用户id
     * @param int $to_currency_id 目标币种id
     * @param string $address 用户钱包地址
     * @param null|string $other_address 目标用户钱包地址(无用)
     * @param null|string $remark 备注(无用)
     * @return bool|int|string
     */
    public static function add_accountbook($user_id, $currency_id, $abt_id, $content, $type, $num, $third_id, $fee = 0, $fee_currency_id = null, $other_user_id = 0, $to_currency_id = 0, $address = '', $other_address = null, $remark = null)
    {
        $type = $type == "in" ? 1 : 2;

        return (new self)->addLog([
            'member_id' => $user_id,
            'currency_id' => $currency_id,
            'number_type' => $type,
            'number' => $num,
            'type' => $abt_id,
            'content' => $content,
            'fee' => $fee,
            'to_member_id' => $other_user_id,
            'to_currency_id' => $to_currency_id,
            'address' => $address,
            'third_id' => $third_id
        ]);
    }

    /**
     *member_id 用户ID
     *currency_id 币种ID
     *number_type 收入=1/支出=2
     *number 数量
     *type 表accountbook_type ID
     *content 描述语言包
     *fee 包含的手续费,非必填
     *to_member_id 目标用户ID,非必填
     *to_currency_id 目标用户ID,非必填
     *address 钱包地址,非必填
     *status  状态 默认都是成功 只有提币,兑换需要审核为0
     *third_id 第三方表记录ID
     *name 其他字段 充提币为地址标签
     */
    public function addLog($data)
    {
        if (empty($data['member_id']) || empty($data['currency_id'])) return false;

        $data['number'] = keepPoint($data['number'], 6);
        if ($data['number'] <= 0) return false;

        try {
            $currency_user = CurrencyUser::getCurrencyUser($data['member_id'], $data['currency_id']);
            if (!$currency_user) return false;
            $current = $currency_user['num'];

            $data['current'] = $current;
            $data['add_time'] = time();
            $log_id = Db::name('accountbook')->insertGetId($data);
            if (!$log_id) return false;

            return $log_id;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     *获取账本记录
     */
    public function getLog($member_id, $currency_id, $type, $page, $page_size, $lang = 'tc', &$count = false, $where = array())
    {
        $where['a.member_id'] = $member_id;
        if (!empty($currency_id)) $where['a.currency_id'] = $currency_id;
        $model_member = new Member();
        //1收入 2支出
        if (!empty($type)) {
            $where['a.number_type'] = $type;
            if ($type == 1) {//充币
                $where['a.type'] = ['in', [5, 13]];
            } elseif ($type == 2) {//提币
                $where['a.type'] = 6;
            }
        }
        $list = Db::name('accountbook')->alias('a')
            ->field('a.id as book_id,a.type,a.member_id,a.currency_id,a.content,a.number_type
            ,a.to_member_id,a.number,a.fee,a.address,a.add_time,a.status,a.name,a.third_id,b.currency_name as from_name,b.currency_logo,c.currency_name as to_name,d.name_' . $lang . ' as type_name_ori')
            ->where($where)
            ->join('__CURRENCY__ b', 'a.currency_id=b.currency_id', 'LEFT')
            ->join('__CURRENCY__ c', 'a.to_currency_id=c.currency_id', 'LEFT')
            ->join(config('database.prefix') . 'accountbook_type d', 'a.type=d.id', 'LEFT')
            // TODO 为什么这里会多了个排序?
            // ->order('a.id desc')
            ->limit(($page - 1) * $page_size, $page_size)->order("a.id desc")->select();
        if ($count) {
            $count = Db::name('accountbook')->alias('a')
                ->field('a.id as book_id,a.type,a.member_id,a.currency_id,a.content,a.number_type
            ,a.to_member_id,a.number,a.fee,a.address,a.add_time,a.status,a.name,b.currency_name as from_name,b.currency_logo,c.currency_name as to_name,d.name_' . $lang . ' as type_name_ori')
                ->where($where)
                ->join('__CURRENCY__ b', 'a.currency_id=b.currency_id', 'LEFT')
                ->join('__CURRENCY__ c', 'a.to_currency_id=c.currency_id', 'LEFT')
                ->join(config('database.prefix') . 'accountbook_type d', 'a.type=d.id', 'LEFT')
                ->order('a.id desc')
                ->count();
        }

        if (!$list) return [];

        foreach ($list as $key => $value) {
            if (empty($value['to_name'])) $value['to_name'] = '';
            $value['add_time'] = date('Y-m-d H:i:s', $value['add_time']);
            if ($value['number_type'] == 1) {
                $value['number'] = "+" . $value['number'];
            } elseif ($value['number_type'] == 2) {
                $value['number'] = "-" . $value['number'];
            }
            $ori_content = $value['content'];
            if (!empty($value['content'])) $value['content'] = lang($value['content']);

            $value['from_name'] = str_replace('-TRC20', '', $value['from_name']);
            if (in_array($value['type'], [1, 2, 3])) {
                //兑换
                if ($value['type'] == 1 && $value['number_type'] == 1) {
                    $tmp = $value['from_name'];
                    $value['from_name'] = $value['to_name'];
                    $value['to_name'] = $tmp;
                }
                $value['content'] = $value['from_name'] . ' TO ' . $value['to_name'];
                $value['type'] = $value['type_name_ori'];
            } elseif (in_array($value['type'], [5, 6, 7])) {
                //冲提币
                if ($value['type'] == 7) {
                    $value['type_name'] = $value['type_name_ori'];
                } else {
//                    $value['type_name'] = lang('lan_address_remark') . $value['name'];
                    $value['type_name'] = lang('lan_address_remark') . $value['address'];
                }

                if ($value['type'] == 5) {
                    $value['type_name_ori'] = lang('bfw_receive_payment');
                }

                $address = explode(":", $value['address']);
                if (!empty($adress[1])) {
                    $adress[0] = substr($adress[0], 0, 3) . '****' . substr($adress[0], -2);
                    $value['content'] = $adress[0] . ' TAG:' . $adress[1];
                } else {
                    $value['content'] = $value['address'];
                }
            } elseif (in_array($value['type'], [9, 10, 16, 31])) {
                //OTC
                if ($value['type'] == 9) {
                    if ($ori_content == 'lan_otc_sell_to_buy_cancel') {

                    } else {
                        $value['content'] = lang('lan_otc_buy') . ' ' . $value['from_name']; //买入 XRP
                    }
                    $value['type_name'] = "OTC";
                } elseif ($value['type'] == 16) {
                    $value['content'] = lang('lan_otc_sell') . ' ' . $value['from_name']; //挂卖 XRP
                    $value['type_name'] = "OTC";
                } elseif ($value['type'] == 10) {
                    $value['content'] = lang('lan_otc_accountbook_shengyu') . ' ' . $value['from_name'] . ' ' . lang('lan_otc_accountbook_return'); //剩余 XRP 返还
                    $value['type_name'] = lang('lan_otc_cancel'); //OTC广告返还
                } elseif ($value['type'] == 31) {
                    $value['content'] = $value['type_name_ori'] . ' ' . $value['from_name'];
                    $value['type_name'] = "OTC";
                }
            } elseif (in_array($value['type'], [11, 17])) {
                //币币交易
                if ($value['number_type'] == 1) {
                    $value['type_name'] = lang('lan_type') . '：' . lang('lan_income');
                } else {
                    $value['type_name'] = lang('lan_type') . '：' . lang('lan_expenditure');
                }
            } elseif (in_array($value['type'], [19, 20])) {
                //投票 激活审核
                if ($value['type'] == 19) {
                    //投票
                    $value['content'] = $value['name'] . lang('lan_accountbook_boss_plan_ticket');
                    $value['type_name'] = lang('lan_accountbook_boss_plan_buy');
                } else {
                    //审核
                    $param = explode("::", $value['name']);
                    $value['content'] = lang('lan_accountbook_boss_plan_wei') . $param[0] . lang('lan_accountbook_boss_plan_active') . (!empty($param[1]) ? $param[1] : '') . lang('lan_accountbook_boss_plan_ticket');
                    $value['type_name'] = lang('lan_accountbook_boss_plan_active');
                }
            } elseif (in_array($value['type'], [21])) {
                //赠送释放
                $value['content'] = lang('lan_otc_accountbook_today') . ' ' . $value['from_name'] . ' ' . lang('lan_otc_accountbook_gift_release'); //当日 XRP 赠送释放
                $value['type_name'] = $value['from_name'] . lang('lan_otc_accountbook_gift_release'); //XRP赠送释放
            } elseif (in_array($value['type'], [18])) {
                //平台内转账
                $value['type_name'] = lang('lan_in_platform_transfer');
                $nick_info = $model_member->member_info($value['to_member_id']);
                $nick = empty($nick_info) ? "****" : $nick_info['nick'];
                $nick = substr($nick, 0, 3) . '****' . substr($nick, -3);
                if ($value['number_type'] == 1) {
                    $value['content'] = lang('lan_mutual_transfer2') . $nick . lang('lan_mutual_transfer3');
                } elseif ($value['number_type'] == 2) {
                    $value['content'] = lang('lan_mutual_transfer1') . $nick;
                }

            } elseif (in_array($value['type'], [22])) {
                //划转
                $value['type_name'] = lang('lan_type_explain');
            } elseif (in_array($value['type'], [600])) {
                //转账
                $transfer_info = CurrencyUserTransfer::with(['users', 'targetusers'])->field('cut_id,cut_user_id,cut_target_user_id')->where(['cut_id' => $value['third_id']])->find();
                if ($value['number_type'] == 1) {
                    //收入
                    $value['type_name'] = lang('lan_type') . '：' . lang('lan_income');
                    $value['type_name_ori'] = lang('from_transfer', ['name' => (isset($transfer_info['users']) ? $transfer_info['users']['ename'] : '')]);
                } else {
                    $value['type_name'] = lang('lan_type') . '：' . lang('lan_expenditure');
                    $value['type_name_ori'] = lang('to_transfer', ['name' => (isset($transfer_info['targetusers']) ? $transfer_info['targetusers']['ename'] : '')]);
                }
            } elseif (in_array($value['type'], [5201, 5202, 5203])) {
                // 划转功能
                $transfer_info = BfwCurrencyTransfer::with(['currency', 'tocurrency'])->where('ct_id', $value['third_id'])->find();
                if ($value['type'] == 5201) { // 钱包/账户划转
                    if ($value['number_type'] == 1) {
                        $value['type_name'] = lang('lan_type') . '：' . lang('lan_income');
                        $value['type_name_ori'] = lang('bfw_from_transfer', ['name' => lang('bfw_' . $transfer_info['currency']['account_type'])]);
                    } else {
                        $value['type_name'] = lang('lan_type') . '：' . lang('lan_expenditure');
                        $value['type_name_ori'] = lang('bfw_to_transfer', ['name' => lang('bfw_' . $transfer_info['tocurrency']['account_type'])]);
                    }
                } elseif ($value['type'] == 5202) { // 账户互转
                    if ($value['number_type'] == 1) {
                        $value['type_name'] = lang('lan_type') . '：' . lang('lan_income');
                        $value['type_name_ori'] = lang('bfw_from_interchange', ['name' => lang('bfw_' . $transfer_info['currency']['account_type'])]);
                    } else {
                        $value['type_name'] = lang('lan_type') . '：' . lang('lan_expenditure');
                        $value['type_name_ori'] = lang('bfw_to_interchange', ['name' => lang('bfw_' . $transfer_info['tocurrency']['account_type'])]);
                    }
                } elseif ($value['type'] == 5203) { // 账户提现
                    if ($value['number_type'] == 1) {
                        $value['type_name'] = lang('lan_type') . '：' . lang('lan_income');
                        $value['type_name_ori'] = lang('bfw_from_withdraw', ['name' => lang('bfw_' . $transfer_info['currency']['account_type'])]);
                    } else {
                        $value['type_name'] = lang('lan_type') . '：' . lang('lan_expenditure');
                        $value['type_name_ori'] = lang('bfw_to_withdraw', ['name' => lang('bfw_' . $transfer_info['tocurrency']['account_type'])]);
                    }
                }

            } else {
                if ($value['number_type'] == 1) {
                    $value['type_name'] = lang('lan_type') . '：' . lang('lan_income');
                } else {
                    $value['type_name'] = lang('lan_type') . '：' . lang('lan_expenditure');
                }
            }
            $list[$key] = $value;
        }
        return $list;
    }

    /**
     * 资产明细
     * @param int $member_id 用户ID
     * @param int $currency_id 币种id
     * @param int $type 类型
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function book_list($member_id, $currency_id, $type, $page, $rows = 10)
    {
        $r = ['code' => ERROR1, 'message' => lang("not_data"), 'result' => null];
        $where = [
            'a.member_id' => $member_id,
            'a.currency_id' => $currency_id
        ];
        $type_name = self::getAccountType($currency_id, $type);
        if ($type_name) {
            $where['a.type'] = $type_name;
        }
        if ($type == 0) {
            $where['a.type'] = ['not in', [7118, 7119,7121]];
        }

        $list = Db::name('accountbook')->alias('a')
            ->join('currency b', 'a.currency_id=b.currency_id')
            ->join('accountbook_type c', 'a.type=c.id')
            ->field('a.id as book_id,a.type,a.number_type,a.number,b.currency_name,c.name_tc,a.add_time,a.third_id')
            ->where($where)
            ->page($page, $rows)
            ->order('a.id DESC')
            ->select();
        if (empty($list)) return $r;

        foreach ($list as &$value) {
            $number = $value['number'];
            if ($value['number_type'] == 1) {
                $value['number'] = "+" . $value['number'];
            } elseif ($value['number_type'] == 2) {
                $value['number'] = "-" . $value['number'];
            }
            $value['add_time'] = date('Y-m-d H:i:s', $value['add_time']);

//            $value['type_name_ori'] = $value['name_tc'];
            $value['type_name_ori'] = '';

            if ($value['type'] == 600) {
                $CurrencyUserTransfer = CurrencyUserTransfer::where(['cut_id' => $value['third_id']])->find();
                $value['name_tc'] = $CurrencyUserTransfer['cut_user_id'] . ' 转入';
                if ($value['number_type'] == 2) {
                    $value['name_tc'] = '转出 ' . $CurrencyUserTransfer['cut_target_user_id'];
                }
            } elseif ($value['type'] == 7120) {
                $value['number'] = "+" . $number;
            } elseif ($value['type'] == 125) {
                $WechatTransfer = WechatTransfer::where(['id' => $value['third_id']])->find();
                $value['type_name_ori'] = $WechatTransfer['desc'];
            }
            unset($value['third_id']);
        }
        $r['code'] = SUCCESS;
        $r['message'] = lang('data_success');
        $r['result'] = $list;
        return $r;
    }

    /**
     * 获取账本类型
     * @param int $currency_id 币种id
     * @param int $type 类型
     * @return string
     * */
    static function getAccountType($currency_id, $type)
    {
        $result = '';
        // USDT
        if ($currency_id == 5) {
            switch ($type) {
                case 1: //充币
                    $result = ['in', [5, 13]];
                    break;
                case 2: //提币
                    $result = ['in', [6, 122]];
                    break;
                case 3: //下单
                    $result = ['in', [7101, 7102, 7103, 7104, 7105, 6505, 115]];
                    break;
                case 13: //互转
                    $result = 600;
                    break;
            }
        } // FIL
        elseif ($currency_id == 81) {
            switch ($type) {
                case 1://充币
                    $result = 5;
                    break;
                case 2://提币
                    $result = 6;
                    break;
                case 3:
                    break;
                case 4:
                    $result = 'archive_alone_mining';//质押
                    break;
                case 5://挖币收益
                    $result = ['in', [6507, 6651, 6652, 6653]];
                    break;
                case 6://每日释放
                    $result = ['in', [6654, 6508]];
                    break;
                case 7:
                    $result = 'alone_mining_income';//提成奖励
                    break;
            }
        } // MTK 钱包
        else if ($currency_id == 93) {
            switch ($type) {
                case 1://充币
                    $result = 5;
                    break;
                case 2://提币
                    $result = 6;
                    break;
                case 3://下单
                    $result = ['in', [7106, 7201, 7202, 7203]];
                    break;
                case 11://划转
                    $result = 1;
                    break;
                case 5://M收益
                    $result = 1;
                    break;
                case 6://每日释放
                    $result = 1;
                    break;
                case 12://奖励
                    $result = 1;
                    break;
                case 13://互转
                    $result = 600;
                    break;
            }
        } // MTK 算力
        elseif ($currency_id == 99) {
            switch ($type) {
                case 1://加速器
                    $result = ['in', [6640,6641,6641]];
                    break;
                case 2://互转
                    $result = ['in', [600]];
                    break;
                case 3://娱乐
                    $result = ['in', [0]];
                    break;
                case 4://释放
                    $result = ['in', [7202]];
                    break;
                case 5://交易
                    $result = ['in', [11,17,]];
                    break;
            }
        } // XCH
        elseif ($currency_id == 95) {
            switch ($type) {
                case 1://充币
                    $result = 5;
                    break;
                case 2://提币
                    $result = 6;
                    break;
                case 4:
                    $result = 'chia_mining_release_income';//挖币收益
                    break;
                case 7:
                    $result = 'chia_mining_income';//提成奖励
                    break;
            }
        } // Y令牌
        elseif ($currency_id == 103) {
            switch ($type) {
                case 1://充币
                    $result = 5;
                    break;
                case 2://提币
                    $result = 6;
                    break;
                case 3:
                    $result = ['in', [7204, 7205, 7206, 7207, 7101, 7102, 7103, 7104, 7105, 6505, 115, 7106, 7201, 7202, 7203]];
                    break;
            }
        } // L令牌
        elseif ($currency_id == 104) {
            switch ($type) {
                case 1://充币
                    $result = 5;
                    break;
                case 2://提币
                    $result = 6;
                    break;
                case 3:
                    $result = ['in', [7112]];
                    break;
            }
        }
        elseif ($currency_id == 106) {//赠与收益
            switch ($type) {
                case 1://预约赠与
                    $result = 7109;
                    break;
                case 2://分享赠与
                    $result = 7102;
                    break;
                case 3://管理赠与
                    $result = ['in', [7122, 7123]];
                    break;
                case 4://服务赠与
                    $result = 7116;
                    break;
                case 5://出仓
                    $result = ['in', [122,6510,125,126]];
                    break;
            }
        }
        return $result;
    }
}
