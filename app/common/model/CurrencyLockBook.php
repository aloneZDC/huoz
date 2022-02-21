<?php
namespace app\common\model;

use think\Exception;
use think\Log;
use think\Model;

//资产锁仓账本记录
class CurrencyLockBook extends Model
{
    //支持的锁仓字段
    const SUPPORT_FIELD = ['num_award','node_lock','forzen_num','lock_num','common_lock_num','release_lock','global_lock','register_lock','fil_lock_num','fil_area_lock_num','alone_lock_num'];

    //锁仓字段 对应的币种 ID
    const FIELD_CURRENCY_ID = [
        'node_lock' => 38,
        'forzen_num' => 63,
        'lock_num' => 67,
        'common_lock_num' => 67,
        'release_lock' => 85,
        'global_lock' => 67,
        'register_lock' => 5,
    ];

    //字段类型
    const FIELD_TYPE_LIST = [
        'node_lock' => ['convert','release','award','t_award'],
        'forzen_num' => ['award', 'activation'],
        'lock_num' => ['fil_mining_release_award11','fil_mining_release_award12','fil_mining_release_award13','release','release_out'],
        'common_lock_num' => ['common_mining_award1','common_mining_award2','common_mining_award3','release'],
        'release_lock' => ['common_mining_award_lock','release'],
        'global_lock' => ['common_mining_award7','release'],
        'register_lock' => ['register_handsel','release'],
        'fil_lock_num' => ['fil_mining_award_lock','release'],
        'fil_area_lock_num' => ['fil_mining_area_lock','release'],
        'alone_lock_num' => ['alone_mining_lock','release'],
        'num_award' => ['award','release', 'convert'],
    ];
    //减少类型
    const FIELD_DEC_TYPE_LIST = [
        'node_lock' => ['release'],
        'forzen_num' => ['activation'],
        'lock_num' => ['release','release_out'],
        'common_lock_num' => ['release'],
        'release_lock' => ['release'],
        'global_lock' => ['release'],
        'register_lock' => ['release'],
        'fil_lock_num' => ['release'],
        'fil_area_lock_num' => ['release'],
        'alone_lock_num' => ['release'],
        'num_award' => ['release'],
    ];
    //增加类型
    const FIELD_INC_TYPE_LIST = [
        'node_lock' => ['convert','award','t_award'],
        'forzen_num' => ['award'],
        'lock_num' => ['fil_mining_release_award11','fil_mining_release_award12','fil_mining_release_award13'],
        'common_lock_num' => ['common_mining_award1','common_mining_award2','common_mining_award3'],
        'release_lock' => ['common_mining_award_lock'],
        'global_lock' => ['common_mining_award7'],
        'register_lock' => ['register_handsel'],
        'fil_lock_num' => ['fil_mining_award_lock'],
        'fil_area_lock_num' => ['fil_mining_area_lock'],
        'alone_lock_num' => ['alone_mining_lock'],
        'num_award' => ['award'],
    ];
    //类型对应语言包
    const FIELD_TYPE_LIST_NAME = [
        'node_lock' => [
            'convert' => 'node_convert',
            'release' => 'node_release',
            'award' => 'node_convert_award',
            't_award' => 'node_convert_t_award',
        ],
        'forzen_num' => [
            'award' => 'bfw_activate_reward',
            'activation' => 'bfw_activation'
        ],
        'lock_num' => [
            'fil_mining_release_award11' => 'fil_mining_release_award11',
            'fil_mining_release_award12' => 'fil_mining_release_award12',
            'fil_mining_release_award13' => 'fil_mining_release_award13',
            'release' => 'fil_mining_release_award16',
            'release_out' => 'fil_mining_lock_release_out',
        ],
        'common_lock_num' => [
            'common_mining_award1' => 'common_mining_award1',
            'common_mining_award2' => 'common_mining_award2',
            'common_mining_award3' => 'common_mining_award3',
            'release' => 'common_mining_award5',
        ],
        'release_lock' => [
            'common_mining_award_lock' => 'common_mining_award_lock',
            'release' => 'common_mining_award6',
        ],
        'global_lock' => [
            'common_mining_award7' => 'common_mining_award7',
            'release' => 'common_mining_award8',
        ],
        'register_lock' => [
            'register_handsel' => 'register_handsel',
            'release' => 'release',
        ],
        'fil_lock_num' => [
            'fil_mining_award_lock' => 'fil_mining_award_lock',
            'release' => 'fil_mining_release_lock',
        ],
        'fil_area_lock_num' => [
            'fil_mining_area_lock' => 'fil_mining_area_lock',
            'release' => 'fil_mining_area_release',
        ],
        'alone_lock_num' => [
            'alone_mining_lock' => 'alone_mining_lock',
            'release' => 'alone_mining_release',
        ],
        'num_award' => [
            'award' => 'take_delivery_of_goods',
            'release' => 'apply_for_credit_limit',
        ]
    ];

    /**
     * @param $field 资产锁仓字段
     * @param $type convert 兑换  release 释放  award奖励 t_award下级奖励 activation激活账号
     * @param $user_id
     * @param $currency_id
     * @param $number
     * @param int $third_id
     * @param int $base_num
     * @param int $percent
     * @return bool|int|string
     */
    static function add_log($field,$type, $user_id, $currency_id, $number, $third_id = 0, $base_num = 0, $percent = 0, $third_member_id=0)
    {
        if(!in_array($field,self::SUPPORT_FIELD) || !in_array($type,self::FIELD_TYPE_LIST[$field])) return false;

        return self::insertGetId([
            'field' => $field,
            'type' => $type,
            'user_id' => $user_id,
            'currency_id' => $currency_id,
            'number' => $number,
            'third_id' => $third_id,
            'third_member_id' => $third_member_id,
            'base_num' => $base_num,
            'percent' => $percent,
            'create_time' => time(),
        ]);
    }

    /**
     * @param $user_id
     * @param $field 锁仓字段
     * @param string $income_type 收入类型 income收入 expend支出
     * @param string $book_type 锁仓类型
     * @param int $page
     * @param int $rows
     */
    static function get_list($user_id,$field, $income_type = '',$book_type='', $page = 1, $rows = 10)
    {
        $r['code'] = ERROR1;
        $r['message'] = lang("parameter_error");
        $r['result'] = null;
        if (isInteger($user_id) && $rows <= 100 && in_array($field,self::SUPPORT_FIELD)) {

            $where = [
                'a.field' => $field,
                'a.user_id' => $user_id,
            ];
            if (!empty($income_type)) {
                if ($income_type == 'income' ) {
                    $where['a.type'] = ['in', self::FIELD_INC_TYPE_LIST[$field]];
                } elseif ($income_type == 'expend') {
                    $where['a.type'] = ['in', self::FIELD_DEC_TYPE_LIST[$field]];
                }
            }

            if(!empty($book_type)){
                if(is_array($book_type)) {
                    $where['a.type'] = ['in', $book_type];
                } else if(in_array($book_type,self::FIELD_TYPE_LIST[$field])) {
                    $where['a.type'] = $book_type;
                }
            }

            if($book_type && in_array($book_type,self::FIELD_TYPE_LIST[$field])) $where['a.type'] = $book_type;

            $fields = "a.number,a.create_time,a.type,b.currency_name";
            $list = self::field($fields)->alias('a')
                ->join(config("database.prefix") . "currency b", "a.currency_id=b.currency_id", "LEFT")
                ->where($where)->page($page, $rows)->order("a.id desc")->select();
            if (!empty($list)) {
                $title = \think\Db::name('accountbook_type')->where(['id' => 7121])->value('name_tc');
                foreach ($list as &$value) {
                    if ($value['type'] == 'convert') {
                        $value['title'] = $title;
                    } else {
                        $value['title'] = lang(self::FIELD_TYPE_LIST_NAME[$field][$value['type']]);
                    }
                    if (in_array($value['type'], self::FIELD_INC_TYPE_LIST[$field])) {
                        $value['number'] = '+' . $value['number'];
                    } elseif (in_array($value['type'], self::FIELD_DEC_TYPE_LIST[$field])) {
                        $value['number'] = '-' . $value['number'];
                    } elseif ($value['type'] == 'convert') {
                        $value['number'] = '-' . $value['number'];
                        $value['type'] = 'release';
                    }
                    $value['create_time'] = date('Y-m-d H:i:s', $value['create_time']);
                }
                $r['code'] = SUCCESS;
                $r['message'] = lang("data_success");
                $r['result'] = $list;
            } else {
                $r['message'] = lang("lan_No_data");
            }
        }
        return $r;
    }

    //获取锁仓数量
    static function getLockNum($user_id,$field) {
        $r = [
            'num' => 0,
            'cny' => 0,
            'currency_logo' => 0,
            'currency_id' => 0,
            'currency_name' => '',
        ];

        if(!in_array($field,self::SUPPORT_FIELD)) return $r;

        $currency = Currency::where(['currency_id'=>self::FIELD_CURRENCY_ID[$field]])->field('currency_id,currency_name,currency_logo')->find();
        if($currency) {
            $r['currency_id'] = $currency['currency_id'];
            $r['currency_logo'] = $currency['currency_logo'];
            $r['currency_name'] = $currency['currency_name'];

            $user_currency = CurrencyUser::getCurrencyUser($user_id,$currency['currency_id']);
            if($user_currency) {
                $r['num'] = $user_currency[$field];
            }

            $currency_price = CurrencyPriceTemp::get_price_currency_id($currency['currency_id'],'CNY');
            if($currency_price) $r['cny'] = keepPoint($currency_price * $r['num'],2);
        }
        return $r;
    }

    public function users()
    {
        return $this->belongsTo('app\\common\\model\\Member', 'user_id', 'member_id')->field('member_id,email,phone,nick,name');
    }
    public function currency() {
        return $this->belongsTo('app\\common\\model\\Currency', 'currency_id', 'currency_id')->field('currency_id,currency_name');
    }
}
