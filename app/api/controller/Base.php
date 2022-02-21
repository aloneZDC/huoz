<?php
// +------------------------------------------------------
// | Author:
// +------------------------------------------------------
namespace app\api\controller;

use app\im\model\MnemonicToken;
use think\captcha\Captcha;
use think\Exception;
use think\Db;

class Base extends \app\common\controller\Common
{
    private $SIGNKEY = "KYxvUytrniy5638Rja9IIj5rjHN9QcOs";
    protected $public_action = []; //无需登录即可访问
    protected $is_method_filter = false; //是否验证请求方式,默认不验证
    protected $login_keep_time = 7200; //登录保持时间
    protected $member_id = false;
    protected $member = [];
    protected $exchange_rate_type = NEW_PRICE_UNIT; //类型：USD：美元，CNY：人民币
    protected $is_decrypt = true;

    public function _initialize()
    {
        parent::_initialize();
        if (!API_OPEN) $this->output(10001, 'close');
        $price_unit = input("exchange_rate_type", "");
        if (in_array($price_unit, ['CNY', 'USD'])) {
            $this->exchange_rate_type = $price_unit;//设置默认币价格显示
        }
        $this->getLang(true);

        //用户请求后重置过期时间
        $this->reLogin();

        //请求方式验证
        if ($this->is_method_filter) $this->method_filter('post');
        $this->decrypt();

        //登录验证
        $this->public_action = array_flip(array_change_key_case(array_flip($this->public_action), CASE_LOWER));
        $action = strtolower($this->request->action());
        if (!$this->checkLogin() && !in_array($action, $this->public_action)) $this->output(10100, lang('lan_modifymember_please_login_first'));

        if (!in_array($action, $this->public_action)) {
            $app_version = strval(input('post.cur_version', ''));
            $cur_version_type = strval(input('post.cur_version_type', ''));
            if ($cur_version_type) {
                if (intval(str_replace('.', '', $app_version)) < intval(str_replace('.', '', Version_Android_New))) {
                    $this->output(VERSION_ERROR, lang('version'));
                }
            } else {
                if (intval(str_replace('.', '', $app_version)) < intval(str_replace('.', '', Version_Android))) {
                    $this->output(VERSION_ERROR, lang('version'));
                }
            }

        }
    }

    /**
     * 签名验证
     * Created by Red.
     * Date: 2019/3/8 19:03
     */
    private function decrypt()
    {
        if (!$this->is_decrypt) return true;

        $data = input();
        $postsign = input("post.sign");
        $time = input("post.sign__time");

        $cur_url = strtolower($this->request->controller() . '/' . $this->request->action());
        $public_api = ['news/newsdetails', 'sms/code', 'account/checkphone', 'email/code', 'account/checkemail', 'account/touxiang', 'sms/check', 'email/check', 'district/version',
            'district/androidversion', 'account/findpass', 'account/resetpass', 'jim/chat', 'jim/upload', 'jim/send_messages', 'jim/get_messages', 'wallet/income',
            'wallet/takeincome', 'account/findpass', 'account/resetpass', 'index/show_img', 'index/create_code', 'wallet/take', 'wallet/summary_address', 'sms/up', 'sms/status', 'error/report'
        ];

        //android图片上传不参与加密
        $img_flag = true;
        if ($cur_url == 'bank/add' || $cur_url == 'account/member_verify') {
            $platform = input('platform', '', 'strval,strtolower');
            if ($platform == 'android') $img_flag = false;
        }

        if (ACCESS_LOG_OPEN) member_access_log($this->member_id, '/api/' . $cur_url, $data, $this->uuid);

        //过滤这两个更新app的方法
        if (!in_array($cur_url, $public_api)) {
            $sign = createSign($data, $this->SIGNKEY, $img_flag);

            if (empty($postsign) || !is_numeric($time)) $this->output(2001, lang("lan_orders_illegal_request"));
            if ($sign != $postsign) $this->output(2002, lang("lan_orders_illegal_request"));
            $newTime = time() + 120;
            //时间大于当前时间的120秒则超时了
            if ($time > $newTime) {
                $this->output(2003, lang("lan_orders_illegal_request"));
            }
        }
    }

    //用户请求后重置过期时间
    protected function reLogin()
    {
        $this->checkLogin(true);
    }

    //检测是否登录
    protected function checkLogin($reset = false)
    {
        if ($this->member_id) return true;

        $key = input('post.key', '', 'strval');
        if (!empty($key)) {
            $token_id = input("post.token_id", '', 'intval');
            $token = cache('auto_login_' . $token_id, '', $this->login_keep_time);
            if ($token === $key) {
                $this->member_id = $token_id;
                if (!empty($this->uuid)) $this->member = cache($this->cache_name, '', $this->login_keep_time);

                if ($reset) {
                    //重置过期时间
                    cache('auto_login_' . $token_id, $token, $this->login_keep_time);
                    if (!empty($this->uuid)) cache($this->cache_name, $this->member, $this->login_keep_time);
                }
                return true;
            } else {
                $userInfo = Db::name('member')->where('member_id', $token_id)->find();
                if ($userInfo['token_value'] === $key) {
                    $token = $userInfo['token_value'];
                    $this->member_id = $token_id;
                    if (!empty($this->uuid)) $this->member = cache($this->cache_name, '', $this->login_keep_time);

                    if ($reset) {
                        //重置过期时间
                        cache('auto_login_' . $token_id, $token, $this->login_keep_time);
                        if (!empty($this->uuid)) cache($this->cache_name, $this->member, $this->login_keep_time);
                    }
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 请求来源判断
     * @param $method
     */
    protected function method_filter($method = 'post')
    {
        $_method = $this->request->method(true);
        if (strtolower($method) !== strtolower($_method)) $this->output(10400, lang('lan_orders_illegal_request'));
    }


    /**
     *  获取当前登陆账号指定积分类型的金额
     * @param int $currency_id 积分类型ID
     * @param char $field num  forzen_num
     * @return array 当前登陆人账号信息
     */
    protected function getUserMoney($currency_id, $field)
    {
        if (!isset($currency_id) || $currency_id == 0) {
            switch ($field) {
                case 'num':
                    $field = 'rmb';
                    break;
                case 'forzen_num':
                    $field = 'forzen_rmb';
                    break;
                default:
                    $field = 'rmb';
            }

            $num = db('member')->where(array('member_id' => $this->member_id))->value($field);
        } else {
            $num = db('Currency_user')->where(array('member_id' => $this->member_id, 'currency_id' => $currency_id))->value($field);
        }

        return number_format($num, 4, '.', '');
    }

    //验证网易验证码
    protected function checkNECaptchaValidate($NECaptchaValidate)
    {
        //验证码验证
        $valudate = new \message\NECaptchaVerifier();
        if (!$valudate->verify($NECaptchaValidate)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 获取用户资的资产折算值
     * 返回数组如：
     *  'allmoneys' => string '1,390.3094' (length=10)
     * 'allmoneys_usd' => string '495.59' (length=6)
     * 'allmoneys_cny' => string '1,390.31' (length=8)
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Created by Red.
     * Date: 2019/2/26 11:47
     */
    protected function getUsersAssetConversion()
    {
        //个人账户资产
        $kok_price = empty($this->config['kok_price']) ? 1 : $this->config['kok_price'];

        $data['allmoneys'] = number_format(0, 4);
        $data['allmoneys_usd'] = number_format(0, 2);
        $data['allmoneys_cny'] = number_format(0, 2);
        if (!empty($this->member_id)) {

            //获取所有账号总额
            $where_user['member_id'] = $this->member_id;

            $currency_user = db('currency_user')->where($where_user)->select();
            $allmoneys = 0;
            $all_name = 'rs_all_currency_user';
            $rs1 = cache($all_name);
            if (empty($rs1)) {
                foreach ($currency_user as $k => $v) {
                    $Currency_message[$v['currency_id']] = $this->getCurrencyMessageById($v['currency_id'], 5);
                }
                $Currency_message = empty($Currency_message) ? [] : $Currency_message;
                cache($all_name, $Currency_message, 300);
            }
            $Currency_message = cache($all_name);
            $usd2cny = $this->getCurrencyMessageById(8, 5)['new_price'];
            $currency = db('currency')->field("currency_id,currency_mark")->select();
            $currency = array_column($currency, null, "currency_id");
            foreach ($currency_user as $k => $v) {
//                if ($v['currency_id'] == "8") {
//                    //查询手续费奖励和红包利息
//                    $allmoney = $v['num'];
//                } else {
//                    $allmoney = ($v['num'] + $v['lock_num'] + $v['exchange_num'] + $v['forzen_num'] + $v['num_award']) * $Currency_message[$v['currency_id']]['new_price'];
                $price = $this->exchange_rate_type == "CNY" ? $Currency_message[$v['currency_id']]['new_price_cny'] : $Currency_message[$v['currency_id']]['new_price_usd'];
                $allmoney = $v['num'] * $price;

//                }
                var_dump($v['currency_id'] . '---' . $allmoney);
                $currency_user[$k]['now_price'] = number_format($allmoney, 2, '.', '');
                $data[$currency[$v['currency_id']]['currency_mark']]['cny'] = number_format($allmoney * $kok_price, 2, '.', '');
                $data[$currency[$v['currency_id']]['currency_mark']]['usd'] = number_format($allmoney * $kok_price * $usd2cny, 2, '.', '');
                $allmoneys += $allmoney;
            }

            $data['allmoneys'] = number_format($allmoneys, 4, '.', '');
            $data['allmoneys_usd'] = number_format($allmoneys * $kok_price * $usd2cny, 2, '.', '');
            $data['allmoneys_cny'] = number_format($allmoneys * $kok_price, 2, '.', '');

        }
        var_dump($data);
        die();
        return $data;
    }

    /**
     * 获取用户资的资产折算值
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * Created by Red.
     * Date: 2019/4/9 12:08
     */
    protected function getUsersAssetConversion1()
    {
        $where_user['member_id'] = $this->member_id;
        $currency_user = db('currency_user')->where($where_user)->select();
        $currency = db('currency')->field("currency_id,currency_mark")->select();
        $currency = array_column($currency, null, "currency_id");
        $data = null;
        foreach ($currency_user as $k => $v) {
            $priceCNY = $this->getPriceByCurrencyId($v['currency_id'])['cny'];
            $priceUSD = $this->getPriceByCurrencyId($v['currency_id'])['usd'];
            $allmoneyCNY = $v['num'] * $priceCNY;
            $allmoneyUSD = $v['num'] * $priceUSD;
            $data[$currency[$v['currency_id']]['currency_mark']]['cny'] = number_format($allmoneyCNY, 2, '.', '');
            $data[$currency[$v['currency_id']]['currency_mark']]['usd'] = number_format($allmoneyUSD, 2, '.', '');
        }
        return $data;
    }


    /**
     * 根据币种id获取币等价的人民币和美元
     * @param $currency_id
     * @return
     * 'usd' => string '0.71' (length=4)
     * 'cny' => string '2.00' (length=4)
     * Created by Red.
     * Date: 2019/4/8 16:59
     */
    function getPriceByCurrencyId($currency_id)
    {
//        $kok_price = empty($this->config['kok_price']) ? 1 : $this->config['kok_price'];
        $data['usd'] = number_format(0, 2);
        $data['cny'] = number_format(0, 2);
        $all_name = 'getPriceByCurrencyId_' . $currency_id;
        $rs1 = cache($all_name);
        if (empty($rs1)) {
            if ($currency_id != 5) {
                $Currency_message = $this->getCurrencyMessageById($currency_id, 5);
            } else {
                $Currency_message = $this->getCurrencyMessageById($currency_id, 8);
            }
            $Currency_message = empty($Currency_message) ? [] : $Currency_message;
            cache($all_name, $Currency_message, 60);
        }
        $Currency_message = cache($all_name);
        $usd2cny = $this->getCurrencyMessageById(8, 5)['new_price'];
//            $allmoney = NEW_PRICE_UNIT=="CNY"?$Currency_message['new_price_cny']:$Currency_message['new_price_usd'];
        if ($currency_id == 5) {
            $data['cny'] = usd2cny();
            $data['usd'] = 1;
        } else {
            $data['cny'] = number_format($Currency_message['new_price_cny'], 2, '.', '');
            $data['usd'] = number_format($Currency_message['new_price_usd'] * $usd2cny, 2, '.', '');
        }
        return $data;
    }

    /**
     * 获取当前积分类型的信息
     * @param int $id 积分类型id
     * @return 24H成交量 24H_done_num  24H成交额 24H_done_money 24H涨跌 24H_change 7D涨跌  7D_change
     * @return 最新价格 new_price 买一价 buy_one_price 卖一价 sell_one_price 最高价 max_price 最低价 min_price
     *
     * 新增加  $toId 目标兑换币
     */
    protected function getCurrencyMessageById($id, $toId = '', $from = 0)
    {
        $currency = db('Currency')->where('is_line=1 ')->order('sort ASC')->select();
        $currency_id_mark = array_column($currency, 'currency_mark', 'currency_id');
        static $usd2cny = 0;
        static $static_new_price = [];
        $usd2cny = $usd2cny ?: usd2cny();
        $all_name = 'rs_all' . $id . '_' . $toId;
        $data = ($from == 1) ? [] : cache($all_name);
        $currency_mark_id = array_flip($currency_id_mark);
        $usdt_id = $currency_mark_id["USDT"];
        $currency_id_value = [];
        foreach ($currency as $k => $value) {
            $currency_id_value[$value['currency_id']] = $value;
        }
        if (empty($data)) {
            $where['currency_id'] = $id;
            empty($toId) ?: $where['currency_trade_id'] = $toId;
            $where['type'] = 'buy';
            $Currency_model = db('Currency');
            $trade_model = db('trade');

            $list = !empty($currency_id_value[$id]) ? $currency_id_value[$id] : ($Currency_model->where(array('currency_id' => $id, 'is_line' => 1))->cache()->find());
            $time = time();
            //一天前的时间
            $old_time = strtotime(date('Y-m-d', $time));

            //最新价格
            $order = 'add_time desc';
            $rs = $trade_model->where($where)->order($order)->cache(true, 60)->find();


            if ($toId == $usdt_id) {
                $data['new_price'] = sprintf('%.8f', $rs['price']);
                $new_price = 1;
            } else {
                if (isset($static_new_price[$toId . "_" . $usdt_id]) && !empty($static_new_price[$toId . "_" . $usdt_id])) {
                    $new_price = $static_new_price[$toId . "_" . $usdt_id];
                } else {
                    $usdt_new_price = self::getCurrencyMessageById($toId, $usdt_id);
                    $new_price = $static_new_price[$toId . "_" . $usdt_id] = $usdt_new_price['new_price'];
                }
                $new_price = empty($new_price) ? 1 : $new_price;
                $data['new_price'] = sprintf('%.8f', $rs['price']);
            }
            $data['new_price_usd'] = sprintf('%.4f', round($rs['price'] * $new_price, 2));//对应美元价格
            $data['new_price_cny'] = sprintf('%.4f', round($rs['price'] * $new_price * $usd2cny / 1, 2));//对应美元价格


            $where2['currency_id'] = $id;
            empty($toId) ?: $where2['currency_trade_id'] = $toId;
            $where2['type'] = 'sell';

            //24H成交量
            $rs = $trade_model->field("SUM(num) as num ,SUM(num * price) as numPrice")->where($where)->where("add_time>$time-60*60*24")->find();

            if ($rs['num'] == 0) {
                $data['24H_done_num'] = '0.00';
            } else {

                $data['24H_done_num'] = round($rs['num'] * 2 + $list['num_number'], 6);
            }

            //24H成交额
            if ($rs['numPrice'] == 0) {
                $data['24H_done_money'] = '0.00';
            } else {

                $data['24H_done_money'] = round($rs['numPrice'] * 2 + $list['num_number'] * $data['new_price'], 6);
            }

            //24H最低价
            $sql_time = $time - 60 * 60 * 24;
            $rs = $trade_model->field('price,min(price) as minprice,max(price) as maxprice')->where($where)->where("add_time>$sql_time")->find();
            $data['min_price'] = $rs['minprice'];
            if ($data['min_price'] == 0) {
                $data['min_price'] = '0.00';
            }

            //24H最高价
            $data['max_price'] = $rs['maxprice'];
            if ($data['max_price'] == 0) {
                $data['max_price'] = '0.00';
            }

            if ($rs['price'] > $data['new_price']) {
                //说明价格下降
                $data['new_price_status'] = 0;
            } else {
                $data['new_price_status'] = 1;
            }
            $data['24H_change'] = !empty($rs['price']) ? sprintf("%.2f", ($data['new_price'] - $rs['price']) / $rs['price'] * 100) : 0;
            $data['24H_change_price'] = abs(sprintf("%.2f", ($data['new_price'] - $rs['price'])));//24H价格变化值
            if ($data['24H_change'] == 0) {
                $data['24H_change'] = '0.00';
            }

            cache($all_name, $data, 15);
        }

        //返回
        return $data;
    }


    /**
     * 验证码
     * @param string $captcha 验证码
     * @return bool
     */
    public function verifyCaptcha($captcha = '')
    {
        if ((new Captcha())->check($captcha)) {
            return true;
        }

        return false;
    }

}
