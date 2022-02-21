<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\Config;
use think\Db;

define("SMS_NAME", "火种云仓");
define("Email_NAME", "火种云仓");
define("SUCCESS", '10000');
define("ERROR1", "10001");
define("ERROR2", "10002");
define("ERROR3", "10003");
define("ERROR4", "10004");
define("ERROR5", "10005");
define("ERROR6", "10006");
define("ERROR7", "10007");
define("ERROR8", "10008");
define("ERROR9", "10009");
define("ERROR10", "10010");
define("ERROR11", "10011");
define("ERROR12", "10012");
define("VERSION_ERROR", "60000");
define('ERROR_NOT_LOGIN', '10100');
define("SMS_KEYWORD", 's_bao');
define("Version_ios", "1.1.1");
define("Version_ios_DowUrl", "https://d1.lhzty.com/fire");
define("Version_ios_Log", "功能优化");
define("Version_Android", "1.1.1");
define("Version_Android_DowUrl", "https://d1.lhzty.com/app/fire/1009.apk");
define("Version_Android_Log", "功能优化");
define("Version_Android_New", "1.0.0");
define("Version_Android_DowUrl_New", "https://d1.lhzty.com/app/fire/2001.apk");
define("Version_Android_Log_New", "功能优化");
define("Boss_pan_receive_xrpz", 1);
define("Boss_pan_receive_xrpz_new", 0);
define("PC_OPEN", false);
define("API_OPEN", true);
define("ACCESS_LOG_OPEN", true);
define("NEW_PRICE_UNIT", 'CNY');

define("PUBLIC_KEY", "jyoi904huo19uwoai");//16个英文字符
define("PRIVATE_KEY", "tbjiophuo1o80tob");//16个英文字符

// 应用公共文件
\think\Lang::setLangCookieVar('think_language');
\think\Lang::setAllowLangList(['zh-tw', 'en-us']);
//根据小数点位数,强制截断
//* @param   $num    要保留的小数
//* @param   $length 要保留的小数位数，默认是2位
function keepPoint($num, $length = 2)
{
    if (is_numeric($num)) {
        $t = pow(10, $length);
        return number_format(floor($num * $t) / $t, $length, '.', '');
    }
}

/**
 * float to Array 除去前置0和后置0
 * @param float|int $val
 * @return string
 * floattostr(0000001) = 1
 * floattostr(1.0000) = 1
 */
function floattostr($val)
{
    preg_match("#^([\+\-]|)([0-9]*)(\.([0-9]*?)|)(0*)$#", trim($val), $o);
    return $o[1] . sprintf('%d', $o[2]) . ($o[3] != '.' ? $o[3] : '');
}

/**
 * 保留位数并去除后置0
 * @param string|int $val
 * @param int $length
 * @return string
 */
function keepPointV2($val, $length = 2)
{
    $val = keepPoint($val, $length);
    return floattostr($val);
}

function filter_page($page)
{
    $page = intval($page);
    if ($page < 1) $page = 1;
    if ($page > 10000000) $page = 10000000;

    return $page;
}

/**
 * 截取字符串
 * @param $str
 * @param int $start
 * @param $length
 * @param string $charset
 * @param bool $suffix
 * @return string
 */

function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = true)
{
    if (function_exists("mb_substr")) {
        $slice = mb_substr($str, $start, $length, $charset);
    } elseif (function_exists('iconv_substr')) {
        $slice = iconv_substr($str, $start, $length, $charset);
    } else {
        $re['utf-8'] = "/[x01-x7f]|[xc2-xdf][x80-xbf]|[xe0-xef][x80-xbf]{2}|[xf0-xff][x80-xbf]{3}/";
        $re['gb2312'] = "/[x01-x7f]|[xb0-xf7][xa0-xfe]/";
        $re['gbk'] = "/[x01-x7f]|[x81-xfe][x40-xfe]/";
        $re['big5'] = "/[x01-x7f]|[x81-xfe]([x40-x7e]|xa1-xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("", array_slice($match[0], $start, $length));
    }
    $fix = '';
    if (strlen($slice) < strlen($str)) {
        $fix = '';
    }
    return $suffix ? $slice . $fix : $slice;
}

//是否合法的身份证@标
function is_idcard($idcard = '')
{
    if (strlen($idcard) != 18) return false;
    $idcard_base = substr($idcard, 0, 17);
    if (strlen($idcard_base) != 17) return false;
    $factor = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
    $verify_number_list = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');
    $checksum = 0;
    for ($i = 0; $i < strlen($idcard_base); $i++) {
        $checksum += substr($idcard_base, $i, 1) * $factor[$i];
    }
    $mod = $checksum % 11;
    $verify_number = $verify_number_list[$mod];
    if ($verify_number == strtoupper(substr($idcard, 17, 1))) return true;
    return false;
}

/**
 * 生成交易订单号 16位  年月日+8位随机
 * @return string
 */
function tradeSn()
{
    return date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
}


//格式化挂单买单还是卖单
function fomatOrdersType($type)
{
    switch ($type) {
        case 'buy':
            $type = lang('lan_buy');
            break;
        case 'sell':
            $type = lang('lan_sell');
            break;
        case 'onebuy':
            $type = '一积分购';
            break;
        default:
            $type = '无';
    }
    return $type;
}


/**价格优化
 * @param $price
 * @return flout
 */
function format_price($price)
{
    if (empty($price)) {
        return sprintf("%.8f", $price);
    }
    switch ($price) {
        case $price >= 100:
            $price = sprintf("%.2f", $price);
            break;
        case $price >= 0.1:
            $price = sprintf("%.4f", $price);
            break;
        case $price >= 0.01:
            $price = sprintf("%.6f", $price);
            break;
        case $price < 0.01:
            $price = sprintf("%.8f", $price);
            break;
        default:
            $price;
    }
    return floatval($price);
}

//获取随机的小数
function randomFloat($min = 0, $max = 1)
{
    return $min + mt_rand() / mt_getrandmax() * ($max - $min);
}

/**
 * 格式化挂单记录status状态
 * @param unknown $status 状态
 * @return unknown
 */
function formatOrdersStatus($status)
{
    switch ($status) {
        case 0:
            $status = lang('lan_not_completed');//'未成交';
            break;
        case 1:
            $status = lang('lan_partial_transaction');//'部分成交';
            break;
        case 2:
            $status = lang('lan_concluded');//'已成交';
            break;
        case -1:
            $status = lang('lan_rescinded');//'已撤销';
            break;
        default:
            $status = lang('lan_not_completed');//'未成交';
            break;
    }
    return $status;
}

/**
 * 格式化用户名
 * @param unknown $currency_id 积分类型id
 * @return unknown
 */
function getCurrencynameByCurrency($currency_id)
{
    if (isset($currency_id)) {
        if ($currency_id == 0) {
            //return lang('lan_order_renminbi');//"人民币";
            return 'RMB';
        }
        /*$lang = cookie('think_language');
        if ($lang == 'en-us') {
            return M('Currency')->field('currency_mark')->where("currency_id='{$currency_id}'")->find()['currency_mark'];
        } else {
            return M('Currency')->field('currency_name')->where("currency_id='{$currency_id}'")->find()['currency_name'];
        }*/
        return db('Currency')->field('currency_mark')->where("currency_id='{$currency_id}'")->find()['currency_mark'];
    } else {
        return "未知钱积分";
    }
}

/**数量优化
 * @param $num
 * @return flout
 */
function format_num($num)
{
    if (empty($num)) {
        return sprintf("%.3f", $num);
    }
    switch ($num) {
        case $num >= 100000:
            $num = intval(($num / 1000)) . 'K';
            break;
        case $num >= 10000:
            $num = sprintf("%.1f", ($num / 1000)) . 'K';
            break;
        case $num >= 1000:
            $num = sprintf("%.2f", ($num / 1000)) . 'K';
            break;
        case $num >= 100:
            $num = intval($num);
            break;
        case $num >= 10:
            $num = sprintf("%.1f", $num);
            break;
        case $num >= 1:
            $num = sprintf("%.2f", $num);
            break;
        case $num < 1:
            $num = sprintf("%.3f", $num);
            break;
        default:
            $num;
    }
    return $num;
}

/**价格优化
 * @param $price
 * @return flout
 */
function format_price_usd($price)
{
    if (empty($price)) {
        return $price = sprintf("%.5f", $price);
    }
    switch ($price) {

        case $price >= 0.01:
            $price = sprintf("%.2f", $price);
            break;

        case $price < 0.01:
            $price = sprintf("%.5f", $price);
            break;
        default:
            $price;
    }
    return $price;
}

function checkPwd_new($pwd)
{
    if (is_numeric($pwd)) return false;

    $pattern = "/^[0-9a-zA-Z]{8,20}$/";
    if (preg_match($pattern, $pwd)) {
        return true;
    } else {
        return false;
    }
}

//验证码设为已用
function usedCode($log_id)
{
    return Db::name('sender')->where(['id' => $log_id])->setField('status', 1);
}

/**
 * 获取美元的汇率
 * @return mixed
 */
function usd2cny()
{
//    return getUsdtCny2();
    $where['key'] = ['in', 'usd_cny_update_time,usd_cny'];
    $configs = db('config')->where($where)->select();
    foreach ($configs as $val) {
        $config[$val['key']] = $val['value'];
    }
    if ($config['usd_cny_update_time'] != strtotime(date('Y-m-d', time()))) {
        $url = "http://web.juhe.cn:8080/finance/exchange/rmbquot";
        $data['key'] = 'd0e3a94bf3e734598a477063d5272be8';
        $request = _curl($url, $data);
        $result = "6.5000";
        if ($request) {
            $request = json_decode($request, true);
            if ($request['resultcode'] == 200) {
                foreach ($request['result'][0] as $val) {
                    if ($val['name'] == '美元') {
                        $result = $val['fBuyPri'] / 100;
                        break;
                    }
                }
            }
        }
        db('config')->where(['key' => 'usd_cny_update_time'])->setField('value', strtotime(date('Y-m-d', time())));
        db('config')->where(['key' => 'usd_cny'])->setField('value', $result);
    } else {
        $result = $config['usd_cny'];
    }
    return $result;
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
 * @return mixed
 */
function get_client_ip_extend($type = 0, $adv = true)
{
    $type = $type ? 1 : 0;
    static $ip = NULL;
    if ($ip !== NULL) return $ip[$type];
    if ($adv) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos) unset($arr[$pos]);
            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u", ip2long($ip));
    $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}

/**
 * 验证手机号支持以下号段
 *      移动：134、135、136、137、138、139、150、151、152、157、158、159、182、183、184、187、188、178(4G)、147(上网卡)；
 * 联通：130、131、132、155、156、185、186、176(4G)、145(上网卡)；
 * 电信：133、153、180、181、189 、174、177(4G)；
 * 卫星通信：1349
 * 虚拟运营商：170
 * @param $mobile
 * @return bool
 */
function checkMobile($mobile)
{
    if (!is_numeric($mobile)) {
        return false;
    }
    return preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,1,3,4,5,6,7,8]{1}\d{8}$|^18[\d]{9}$|^19[\d]{9}$|^16[\d]{9}#', $mobile) ? true : false;
}

/**
 * 验证邮箱
 * @param $email
 * @return bool
 */
function checkEmail($email)
{
    $flag = filter_var($email, FILTER_VALIDATE_EMAIL);
    if (!$flag) return false;
    return true;

    if (strpos($email, '@mail.ba-ex.io') !== false) return true;

    $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
    if (preg_match($pattern, $email)) {
        return true;
    } else {
        return false;
    }
}

/**
 * 验证用户名
 * @param $username
 * @return bool
 */
function checkUname($username)
{
    if (preg_match('/^(?![0-9])[0-9A-Za-z.]{5,30}$/', $username)) return true;
    //公链钱包地址 0-5 a-z . 5-12位
//    if (preg_match('/[0-9A-Za-z.]{5,12}$/', $username)) return true;
    return false;
}

/** 随机生成数字（默认6位）
 * @param int $num 要生成的位数
 * @return string
 */
function randNum($num = 6)
{

    $chars = "0123456789";
    $str = "";
    for ($i = 0; $i < $num; $i++) {
        $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;

    //return str_pad(mt_rand(0, 999), $num, "0", STR_PAD_BOTH);
}

function _curl($url, $data = null, $json = false, $method = 'POST', $timeout = 30)
{
    $ssl = substr($url, 0, 8) == "https://" ? TRUE : FALSE;
    $ch = curl_init();
    $fields = $data;
    $headers = array();
    if ($json && is_array($data)) {
        $fields = json_encode($data);
        $headers = array(
            "Content-Type: application/json charset=utf-8",
            'Content-Length: ' . strlen($fields),
        );
    }

    $opt = array(
        CURLOPT_URL => $url,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
    );

    if ($ssl) {
        $opt[CURLOPT_SSL_VERIFYHOST] = 2;
        $opt[CURLOPT_SSL_VERIFYPEER] = FALSE;
    }
    curl_setopt_array($ch, $opt);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @return mixed
 */
function get_client_ip($type = 0)
{
    $type = $type ? 1 : 0;
    static $ip = NULL;
    if ($ip !== NULL) return $ip[$type];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $pos = array_search('unknown', $arr);
        if (false !== $pos) unset($arr[$pos]);
        $ip = trim($arr[0]);
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u", ip2long($ip));
    $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}

//验证网易验证码
function checkNECaptchaValidate($NECaptchaValidate)
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
 * 以太坊钱包地址是否合法
 * Returns true if provided string is a valid ethereum address.
 *
 * @param string $address Address to check
 * @return bool
 */
function isValidAddress($address = "")
{

    return (is_string($address)) ? preg_match("/^0x[0-9a-fA-F]{40}$/", $address) : false;
}

/**
 * 生成随机字符串
 * @param int $length 长度
 * @param bool $case 是否生成大小写(默认生成小写)
 * @return 产生的随机字符串
 */
function getNonceStr($length = 32, $case = false)
{
    $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
    $str = "";
    if ($case == true) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    }
    for ($i = 0; $i < $length; $i++) {
        $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    $check = check_system_keywords($str);
    if ($check) {
        return $str;
    } else {
        return getNonceStr($length, $case);
    }
}

/**获取主流币的价格（返回为人民币，缓存2分钟）
 * @param $currency_name        币名称，如：BTC、ETH、USDT
 * @return float
 * Created by Red.
 * Date: 2018/12/12 16:16
 */
function getPrice($currency_name)
{
    $price = cache("HUOBIPRO:" . $currency_name . "USDT");
    if ($price > 0) return floatval($price);
    if ($currency_name != 'USDT') {
        $ticker = "HUOBIPRO:" . $currency_name . "USDT";
        //$url = "http://api.coindog.com/api/v1/tick/" . $ticker . "?unit=cny";
        $url = "http://api.coindog.com/api/v1/tick/" . $ticker;
        $res = _curl($url, '', '', 'GET', 5);
        $res = json_decode($res, true);
        if (isset($res['close'])) {
            $cu_price = round(isset($res['close']) ? $res['close'] : 0, 6);
            cache("forever_HUOBIPRO:" . $currency_name . "USDT", $cu_price);
        } else {
            //获取不到则从永久缓存里取
            $cu_price = cache("forever_HUOBIPRO:" . $currency_name . "USDT");
        }

    } else {
        $ticker = "HUOBIPRO:BTCUSDT";
        //$url = "http://api.coindog.com/api/v1/tick/" . $ticker . "?unit=cny";
        $url = "http://api.coindog.com/api/v1/tick/" . $ticker;
        $res = _curl($url, '', '', 'GET', 5);
        $res = json_decode($res, true);
        $url2 = "http://api.coindog.com/api/v1/tick/" . $ticker . "?unit=base";
        $res2 = _curl($url2, '', '', 'GET', 5);
        if (isset($res['close'])) {
            $res2 = json_decode($res2, true);
            $cu_price = round($res['close'] / $res2['close'], 6);
            cache("forever_HUOBIPRO:" . $currency_name . "USDT", $cu_price);
        } else {
            //获取不到则从永久缓存里取
            $cu_price = cache("forever_HUOBIPRO:" . $currency_name . "USDT");
        }
    }
    if ($cu_price > 0) {
        cache("HUOBIPRO:" . $currency_name . "USDT", $cu_price, 120);
    }
    return $cu_price;
}

/**获取主流币的价格（返回为美元，缓存2分钟）
 * @param $currency_name        币名称，如：BTC、ETH、USDT
 * @return float
 * Created by Red.
 * Date: 2018/12/12 16:16
 */
function getPriceUSD($currency_name)
{
    $price = cache("getPriceUSD_HUOBIPRO:" . $currency_name . "USDT");
    if ($price > 0) return floatval($price);
    if ($currency_name != 'USDT') {
        $ticker = "HUOBIPRO:" . $currency_name . "USDT";
        //$url = "http://api.coindog.com/api/v1/tick/" . $ticker . "?unit=cny";
        $url = "http://api.coindog.com/api/v1/tick/" . $ticker . "?unit=base";
        $res = _curl($url, '', '', 'GET', 5);
        $res = json_decode($res, true);
        if (isset($res['close'])) {
            $cu_price = round(isset($res['close']) ? $res['close'] : 0, 6);
            cache("forever_getPriceUSD_HUOBIPRO:" . $currency_name . "USDT", $cu_price);
        } else {
            //获取不到则从永久缓存里取
            $cu_price = cache("forever_getPriceUSD_HUOBIPRO:" . $currency_name . "USDT");
        }
    } else {
        $ticker = "HUOBIPRO:BTCUSDT";
        //$url = "http://api.coindog.com/api/v1/tick/" . $ticker . "?unit=cny";
        $url = "http://api.coindog.com/api/v1/tick/" . $ticker . "?unit=base";
        $res = _curl($url, '', '', 'GET', 5);
        $res = json_decode($res, true);
        $url2 = "http://api.coindog.com/api/v1/tick/" . $ticker . "?unit=base";
        $res2 = _curl($url2, '', '', 'GET', 5);
//        $cu_price = round(isset($res2['close'])?$res2['close']:0, 6);
        if (isset($res['close'])) {
            $res2 = json_decode($res2, true);
            $cu_price = round($res['close'] / $res2['close'], 6);
            cache("forever_getPriceUSD_HUOBIPRO:" . $currency_name . "USDT", $cu_price);
        } else {
            //获取不到则从永久缓存里取
            $cu_price = cache("forever_getPriceUSD_HUOBIPRO:" . $currency_name . "USDT");
        }

    }
    if ($cu_price > 0) {
        cache("getPriceUSD_HUOBIPRO:" . $currency_name . "USDT", $cu_price, 120);
    }
    return $cu_price;
}

/*
 *  获取当天开始时间，比如今天获取到:2017-05-13 00:00:00
 * @author hong
 * Date: 2017-05-13 15:39
 */
function todayBeginDate()
{
    return date("Y-m-d H:i:s", mktime(0, 0, 0, date('m'), date('d'), date('Y')));
}

/*
 *获取当天结束时间，比如今天获取到:2017-05-13 23:59:59
 * @author hong
 * Date: 2017-05-13 15:40
 */
function todayEndDate()
{
    return date("Y-m-d H:i:s", mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1);
}

/*
 *获取当天开始时间戳，比如今天获取到:2017-05-13 00:00:00
 * @author hong
 * Date: 2017-08-17 09:42
 */
function todayBeginTimestamp()
{
    return strtotime(date('Y-m-d', time()));
}

/*
 *获取当天结束时间戳，比如今天获取到:2017-05-14 00:00:00(昨天是13号)
 * @author hong
 * Date: 2017-08-17 09:43
 */
function todayEndTimestamp()
{
    return strtotime(date('Y-m-d', time())) + 86400;
}

function yesterdayBeginTimestamp()
{
    return strtotime(date("Y-m-d", strtotime('yesterday')));
}

function yesterdayEndTimestamp()
{
    return strtotime(date("Y-m-d 23:59:59", strtotime('yesterday')));
}

function get_from_url($url)
{
    $opts = ["http" => ["timeout" => 3]];
    $context = stream_context_create($opts);
    $result = @file_get_contents($url, false, $context);
    return $result;
}

/**
 * 获取USDT的人民币价格 可能失效
 * @return mixed
 */
function getUsdtCny2()
{
    $price = 0;

    $cache_key_expire = 'common_usdt_key2';
    $is_expire = cache($cache_key_expire);
    if (!$is_expire) {
        $cny_price = $usdt_price = 0;
        $data = @get_from_url("https://www.huobi.br.com/-/x/general/exchange_rate/list?r=dfbd3r");
        if ($data) {
            $data = json_decode($data, true);
            if (!empty($data['data'])) {
                foreach ($data['data'] as $value) {
                    if ($value['name'] == 'usdt_cny') {
                        $cny_price = $value['rate'];
                        break;
                    }
                }
            }
        }

        if ($cny_price > 0) {
            $price = keepPoint($cny_price, 2);
            cache('common_usdt_price2', $price);
            cache($cache_key_expire, 1, 600);
        }
    }

    if ($price <= 0) $price = floatval(cache('common_usdt_price2'));

    return $price;
}

/**
 * 获取USDT的人民币价格
 * @return mixed
 */
function getUsdtCny()
{
    $price = 0;

    $cache_key_expire = 'common_usdt_key';
    $is_expire = cache($cache_key_expire);
    if (!$is_expire) {
        $cny_price = $usdt_price = 0;
        $data = @get_from_url("http://api.coindog.com/api/v1/tick/HUOBIPRO:BTCUSDT?unit=cny");
        if ($data) {
            $data = json_decode($data, true);
            if ($data['close']) {
                $cny_price = $data['close'];
            }
        }

        $data = @get_from_url("http://api.coindog.com/api/v1/tick/HUOBIPRO:BTCUSDT?unit=usd");
        if ($data) {
            $data = json_decode($data, true);
            if ($data['close']) {
                $usdt_price = $data['close'];
            }
        }
        if ($cny_price > 0 && $usdt_price > 0) {
            $price = sprintf('%.4f', $cny_price / $usdt_price);
            cache('common_usdt_price', $price);
            cache($cache_key_expire, 1, 60);
        }
    }

    if ($price <= 0) $price = floatval(cache('common_usdt_price'));

    return $price;
}

//获取火币网行情
function getCnyPrice($symbol = '')
{
    $return = [];

    $cache_key_expire = 'common_' . $symbol . '_key';
    $is_expire = cache($cache_key_expire);

    $cache_key = 'common_' . $symbol;
    if ($is_expire) {
        $return = cache($cache_key);
    } else {
        $data = @get_from_url("http://api.coindog.com/api/v1/tick/HUOBIPRO:" . $symbol . "?unit=cny");
        if ($data) {
            $result = json_decode($data, true);
            if (isset($result['close'])) {
                cache($cache_key_expire, 1, 60); //60秒请求一次
                cache($cache_key, $result); //缓存结果永久
                $return = $result;
            }
        }
    }
    if (empty($return)) return [];

    return $return;
}

function getLangName($lang, $params = [])
{
    //占位符 ##
    $lang = explode("##", $lang);
    foreach ($lang as $key => $value) {
        $p = isset($params[$key]) ? $params[$key] : '';
        $lang[$key] = $value . $p;
    }
    return implode('', $lang);
}

function cutArticle($data, $cut = 0, $str = "....")
{

    $data = strip_tags($data);//去除html标记
    $pattern = "/&[a-zA-Z]+;/";//去除特殊符号
    $data = preg_replace($pattern, '', $data);
    if (!is_numeric($cut))
        return $data;
    if ($cut > 0)
        $data = mb_strimwidth($data, 0, $cut, $str);


    return $data;
}

/**
 * 生成签名
 * @param $args //要发送的参数
 * @param $key //keycode
 * @param $img_flag 图片是否参与加密
 * @return string
 */
function createSign($args, $key = '', $img_flag = true)
{
    $signPars = ""; //初始化
    ksort($args); //键名升序排序
    foreach ($args as $k => $v) {
        if (!isset($v) || strtolower($k) == "sign") {
            continue;
        }
        if (!$img_flag && in_array(strtolower($k), ['pic1', 'pic2', 'pic3', 'img'])) {
            continue;
        }
        $signPars .= $k . "=" . $v . "&";
    }
    $signPars .= "key=" . $key; //签名的参数和key秘钥连接
    $sign = md5($signPars); //md5加密
    $sign = strtoupper($sign); //转为大写
    return $sign; //最终的签名
}

/**
 * 访问日志
 * @param $cur_url //访问模块
 * @param $data //参数
 */
function member_access_log($member_id, $cur_url, $data, $uuid = '')
{
    $filter_param = ['pic1', 'pic2', 'pic3', 'img', 'pwd', 'repwd', 'password', 'pwdtrade', 'repwdtrade'];
    foreach ($filter_param as $filter) {
        if (isset($data[$filter])) $data[$filter] = '';
    }

    $ip = get_client_ip();
    Db::name('member_access_log')->insertGetId([
        'member_id' => intval($member_id),
        'module' => $cur_url,
        'param' => json_encode($data),
        'uuid' => strval($uuid),
        'access_ip' => $ip,
        'access_time' => time(),
    ]);
}

/**
 * 返回json格式数据
 * @param array $data 要返回的数据
 * @param str $msg 返回提示消息
 * @param int $code 返回码、状态码
 * @return json
 */
function ajaxReturn($data = [])
{
    if (func_num_args() > 2) {
        $args = func_get_args();
        array_shift($args);
        $info = array();
        $info['result'] = $data;
        $info['message'] = array_shift($args);
        $info['code'] = array_shift($args);
        $data = $info;
    }
    header('Content-Type:application/json; charset=utf-8');
    //不加密模式
    if (!Config::get("app_encrypt")) {
        exit(json_encode($data));
    } else {
        //加密返回
        $data = ir_gzip_encode($data);
        echo $data;
        exit();
    }
}

/**
 * 返回json格式数据,h5端的不加密
 * @param array $data 要返回的数据
 * @param $encrypt      是否加密模式,默认：是
 * @param str $msg 返回提示消息
 * @param int $code 返回码、状态码
 * @return json
 */
function mobileAjaxReturn($data = [])
{
    if (func_num_args() > 2) {
        $args = func_get_args();
        array_shift($args);
        $info = array();
        $info['result'] = $data;
        $info['message'] = array_shift($args);
        $info['code'] = array_shift($args);
        $data = $info;
    }
    $__tmp = json_encode($data);
    header('Content-Type:application/json; charset=utf-8');
    //header('Content-Length: '.strlen($__tmp));
    exit($__tmp);
}

function successJson($code, $message = "", $data = [])
{
    if (is_array($code)) {
        return json($code);
    }
    return ['code' => $code, 'message' => $message, 'data' => $data];
}

/**
 * 清空文件夹函数和清空文件夹后删除空文件夹函数的处理
 * @param $path
 * Create by: Red
 * Date: 2019/8/20 11:19
 */
function deldir($path)
{
    //如果是目录则继续
    if (is_dir($path)) {
        //扫描一个文件夹内的所有文件夹和文件并返回数组
        $p = scandir($path);
        foreach ($p as $val) {
            //排除目录中的.和..
            if ($val != "." && $val != "..") {
                //如果是目录则递归子目录，继续操作
                if (is_dir($path . $val)) {
                    //子目录中操作删除文件夹和文件
                    deldir($path . $val . '/');
                    //目录清空后删除空文件夹
                    @rmdir($path . $val . '/');
                } else {
                    //如果是文件直接删除
                    unlink($path . $val);
                }
            }
        }
    }
}

/**
 * 获取用户状态
 * @param string $status 状态
 * @return boolean|string 状态
 */
function getMemberStatus($status)
{
    if (empty($status)) {
        return false;
    }
    switch ($status) {
        case 0 :
            $status = "未填写个人信息";
            break;
        case 1 :
            $status = "正常";
            break;
        case 2 :
            $status = "禁用";
            break;
        default:
            $status = "未知状态";
    }
    return $status;
}

/**委托记录type
 * @param $type
 * @return string
 */
function getOrdersType($type)
{
    switch ($type) {
        case "buy":
            $data = "买入";
            break;
        case "sell" :
            $data = "卖出";
            break;
        case "onebuy" :
            $data = "一积分购";
            break;
        default:
            $data = "未知状态";
    }
    return $data;
}

//充值状态格式化
function payStatus($num)
{
    switch ($num) {
        case 0:
            $data = "请付款";
            break;
        case 1:
            $data = "充值成功";
            break;
        case 2:
            $data = "充值失败";
            break;
        case 3:
            $data = "已失效";
            break;
        default:
            $data = "暂无";
    }
    return $data;
}

/**
 * @param string $string 哈希地址 或 钱包地址
 * @param int $type 1:BTC 2:USDT 3:ETH和代币 4：EOS
 * @param null|int $isAddress
 * @return string 跳转URL
 */
function coinUrl($string, $type = 1, $isAddress = false)
{
    $baseUrl = "";
    switch ($type) {
        case 1:
            $baseUrl = "https://btc.com/";
            break;
        case 2:
            $baseUrl = "https://omniexplorer.info/search/";
            break;
        case 3:
            $baseUrl = $isAddress ? "https://cn.etherscan.com/address/" : "https://cn.etherscan.com/tx/";
            break;
        case 4:
            $baseUrl = $isAddress ? "https://eospark.com/account/" : "https://eospark.com/tx/";
            break;
        case 5:
            $baseUrl = "https://bithomp.com/explorer/";
            break;
    }
    return $baseUrl . $string;
}

/**委托记录状态
 * @param $status  状态
 * @return string
 */
function getOrdersStatus($status)
{
    switch ($status) {
        case 0 :
            $data = "挂单";
            break;
        case 1 :
            $data = "部分成交";
            break;
        case 2 :
            $data = "成交";
            break;
        case -1 :
            $data = "已撤销";
            break;
        default:
            $data = "未知状态";
    }
    return $data;
}

/*
 * 导出Excel
 * @param array $columnName
 * @param array $list
 * @param string $sheetTitle
 * @param string $fileName
 * @return string|void
 * @throws PHPExcel_Exception
 * @throws PHPExcel_Reader_Exception
 * @throws PHPExcel_Writer_Exception
 */
function exportExcel($columnName = [], $list = [], $fileName = 'excel', $sheetTitle = 'Sheet1')
{
    if (empty($columnName) || empty($list)) {
        return '列名或者内容不能为空';
    }


    if (count($list[0]) != count($columnName)) {
        return '列名跟数据的列不一致';
    }
    require_once(APP_PATH . '/../extend/PHPExcel/PHPExcel.php');
    $phpExcel = new PHPExcel();
    $phpSheet = $phpExcel->getActiveSheet();
    $phpSheet->setTitle($sheetTitle);
    $letter = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
        'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'
    ];
    for ($i = 0; $i < count($list[0]); $i++) {
        //$letter[$i]1 = A1 B1 C1  $letter[$i] = 列1 列2 列3
        $phpSheet->setCellValue("$letter[$i]1", $columnName[$i]);
    }


    //内容第2行开始
    foreach ($list as $key => $val) {
        //array_values 把一维数组的键转为0 1 2 3 ..
        foreach (array_values($val) as $key2 => $val2) {
            //$letter[$key2].($key+2) = A2 B2 C2 ……
            $phpSheet->setCellValue($letter[$key2] . ($key + 2), $val2);
        }
    }

    $phpWriter = PHPExcel_IOFactory::createWriter($phpExcel, "Excel2007");
    $suffix = date("Ymd") . rand(1000, 9999);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename=' . $fileName . $suffix . '.xlsx');
    header('Cache-Control: max-age=0');
    $phpWriter->save("php://output");
}

/**
 * Excel导出
 * @param $expTitle         标题
 * @param $expCellName      导出列
 * @param $expTableData     数据源
 * @throws PHPExcel_Exception
 * @throws PHPExcel_Reader_Exception
 * @throws PHPExcel_Writer_Exception
 * Create by: Red
 * Date: 2019/9/11 11:49
 */
function export_excel($expTitle, $expCellName, $expTableData)
{
    $xlsTitle = iconv('utf-8', 'gb2312', $expTitle);//文件名称
    $fileName = $expTitle . date('_YmdHis');//or $xlsTitle 文件名称可根据自己情况设定
    $cellNum = count($expCellName);
    $dataNum = count($expTableData);
    require_once(APP_PATH . '/../extend/PHPExcel/PHPExcel.php');


    $objPHPExcel = new PHPExcel();
    $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');

    $objPHPExcel->getActiveSheet(0)->mergeCells('A1:' . $cellName[$cellNum - 1] . '1');//合并单元格
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle . '  Export time:' . date('Y-m-d H:i:s'));
    for ($i = 0; $i < $cellNum; $i++) {
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i] . '2', $expCellName[$i][1]);
    }
    // Miscellaneous glyphs, UTF-8
    for ($i = 0; $i < $dataNum; $i++) {
        for ($j = 0; $j < $cellNum; $j++) {
            $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 3), $expTableData[$i][$expCellName[$j][0]]);
        }
    }

    header('pragma:public');
    header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $xlsTitle . '.xls"');
    header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
    exit;
}

/*
 *以GET方式获取数据，替代file_get_contents
 * @author hong
 * Date: 2017-08-05 20:22
 */
function get_data($url, $timeout = 60)
{
    $msg = $flat = '';
    if (strpos($url, 'http://') !== false || strpos($url, 'https://') !== false) {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // 跳过证书检查
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); // 不检查证书
        $res = curl_exec($ch);

        $flat = curl_errno($ch);
        if ($flat) {
            $msg = curl_error($ch);
        }
        curl_close($ch);
    } else {
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 30
            )
        )); // 超时时间，单位为秒

        $res = file_get_contents($url, 0, $context);
    }

    return $res;
}

/**
 * 兼容tp3的U方法
 * @param string $url
 * @param string $vars
 * @param bool $suffix
 * @param bool $domain
 * @return string
 * Create by: Red
 * Date: 2019/8/28 9:12
 */
function U($url = '', $vars = '', $suffix = true, $domain = false)
{
    return url($url, $vars, $suffix, $domain);
}

/**
 * 兼容tp3的I方法
 * @param string $key
 * @param null $default
 * @param string $filter
 * @return mixed
 * Create by: Red
 * Date: 2019/8/28 10:17
 */
function I($key = '', $default = null, $filter = '')
{
    return input($key, $default, $filter);
}

/**
 * 验证缓存里的图片验证码是正确
 * @param $mark     验证码标识
 * @param $code     验证码
 * @param $delete   验证成功后是否删除
 * @return bool
 * Create by: Red
 * Date: 2019/8/30 11:34
 */
function verify_code($mark, $code, $delete = true)
{
    if (!empty($mark) && !empty($code)) {
        $cache = cache("verificationcode_" . $mark);
        if (!empty($cache) && strtolower($cache) == strtolower($code)) {
            if ($delete) {
                cache("verificationcode_" . $mark, null);
            }
            return true;
        }
    }
    return false;
}

/**
 * 加密方法（gzip压缩后加密（优化版））
 * @param $data         要加密的数据
 * @param $debug
 * @return string
 * Created by Red
 * Date: 2019/5/14 16:42
 */
function ir_gzip_encode($data, $debug = 1)
{
    $content = json_encode($data);                    //json数据
    if (!$debug) {    //调试状态
        header('Content-Type: application/json; charset=utf-8');
        return $content;                    //1:压缩加密输出   0：非压缩加密调试
    } else {
        //非调试状态
//        header('Content-Type: application/text; charset=utf-8');
//        header("Content-Encoding: gzip");
//        header("Vary: Accept-Encoding");
        $content = encryptionEncode($content);        //加密
        //  $content = gzencode($content, 6);                //gzip压缩
        return $content;
    }
}

//将压缩后的字符串加密: 服务端
function encryptionEncode($content)
{
    $aes = new \safe\EasyAESCrypt(PRIVATE_KEY, 128, PUBLIC_KEY);
    return $aes->encrypt($content);
//    //【MCRYPT_3DES  DES-EDE3-CBC】  【MCRYPT_RIJNDAEL_128 AES-128-CB部分加密为空,暂时不知什么原因】
//    if(PHP_VERSION<5.6 && extension_loaded('mcrypt')){
//        $encrypted = base64_encode(mcrypt_encrypt(MCRYPT_3DES,PRIVATE_KEY,$content,MCRYPT_MODE_CBC,PUBLIC_KEY));
//        return $encrypted;
//    } elseif (extension_loaded('openssl')) {
//        $len = strlen($content);
//        if($len%8){
//            //使用空字符填充字符串的右侧,使字符串位数变为8的倍数
//            $content = str_pad($content,$len+8-$len%8,"\0");
//        }
//        $encrypted = base64_encode(openssl_encrypt($content,'DES-EDE3-CBC',PRIVATE_KEY,OPENSSL_RAW_DATA|OPENSSL_NO_PADDING,PUBLIC_KEY));
//        return $encrypted;
//    }
//    return $content;
}

/**
 * 解密方法
 * @param $content
 * @return array
 * Created by Red
 * Date: 2019/5/14 16:44
 */
function decryptionEncode($content)
{
    try {
//        $content = gzdecode($content);//解压缩
//        $iLen = strlen($content);
//        for ($i = 0; $i < 10; $i++) {
//            echo " " . ord($content[$i]);
//        }
//        die();
//        if(PHP_VERSION<5.6 && extension_loaded('mcrypt')) {
//            $result = rtrim(mcrypt_decrypt(MCRYPT_3DES, PRIVATE_KEY, base64_decode($content), MCRYPT_MODE_CBC, PUBLIC_KEY), '\0');
//            $result = @json_decode(trim($result), true);
//        } elseif(extension_loaded('openssl')) {
//            $result = openssl_decrypt(base64_decode($content),'DES-EDE3-CBC',PRIVATE_KEY,OPENSSL_RAW_DATA|OPENSSL_NO_PADDING,PUBLIC_KEY);
//            $result = @json_decode(trim($result), true);
//        } else {
//            $result = [];
//        }

        $aes = new \safe\EasyAESCrypt(PRIVATE_KEY, 128, PUBLIC_KEY);
        $result = $aes->decrypt($content);
        $result = @json_decode(trim($result), true);
        return $result;
    } catch (\think\Exception $exception) {
        return [];
    }
}

/**
 * 解密方法
 * @param $content
 * @return string
 * Created by Red
 * Date: 2019/5/14 16:44
 */
function decryptionEncodeStr($content)
{
    try {
        $aes = new \safe\EasyAESCrypt(PRIVATE_KEY, 128, PUBLIC_KEY);
        return $aes->decrypt($content);
    } catch (\think\Exception $exception) {
        return "";
    }
}

/**
 * 验证密码长度在6-20个字符之间
 * @param $pwd
 * @return bool
 */
function checkPwd($pwd)
{
    $pattern = "/^[\\w-\\.]{6,20}$/";
    if (preg_match($pattern, $pwd)) {
        return true;
    } else {
        return false;
    }
}

/**
 * 数组按字段排序
 * Sort array by filed and type, common utility method.
 * @param array $data
 * @param string $sort_filed
 * @param string $sort_type SORT_ASC or SORT_DESC
 */
function sortByOneField($data, $filed, $type)
{
    if (count($data) <= 0) {
        return $data;
    }
    foreach ($data as $key => $value) {
        $temp[$key] = $value[$filed];
    }
    array_multisort($temp, $type, $data);
    return $data;
}

function kline_config($currency_id, $currency_trade_id)
{
    $show_length = [
        '1' => 120,
        '5' => 120,
        '15' => 120,
        '30' => 120,
        '60' => 120,
        '1D' => 120,
        'D' => 120,
        '1W' => 120,
        '1M' => 120,
    ];
    $all_time = array('1' => '60', '5' => '300', '15' => '900', '30' => '1800', '60' => '3600', '1D' => '86400', 'D' => '86400', '1W' => '604800', '1M' => '2592000');
    $now = time();

    $result = [];
    foreach ($all_time as $key => $time) {
        $result[$key] = ['from' => 0, 'to' => 0];
        if (isset($show_length[$key])) {
            $start_time = $now - $time * $show_length[$key];//开始时间
            $info = Db::name('kline')->field('add_time')->where(['type' => $time, 'currency_id' => $currency_id, 'currency_trade_id' => $currency_trade_id, 'add_time' => ['egt', $start_time]])
                ->order('add_time asc')->find();
            if ($info) $result[$key]['from'] = $info['add_time'];

            $info = Db::name('kline')->field('add_time')->where(['type' => $time, 'currency_id' => $currency_id, 'currency_trade_id' => $currency_trade_id])->order('add_time desc')->find();
            if ($info) $result[$key]['to'] = $info['add_time'];
        }
    }
    return $result;
}

/**用户名、邮箱、手机账号中间字符串以*隐藏
 * @param $str
 * @return string|string[]|null
 * Create by: Red
 * Date: 2019/10/23 11:10
 */
function hideStar($str)
{
    if (strpos($str, '@')) {
        $email_array = explode("@", $str);
        $string = $email_array[0];
        if (strlen($string) >= 4) {
            return substr($email_array[0], 0, 2) . "***" . substr($email_array[0], -2) . "@" . $email_array[1];
        } else {
            return substr($email_array[0], 0, 1) . "***" . substr($email_array[0], -1) . "@" . $email_array[1];
        }
    } else {
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
 * 检测是否通过系统关键词检查(通过为true)
 * @param $str
 * @return bool
 * Create by: Red
 * Date: 2019/10/24 9:57
 */
function check_system_keywords($str)
{
    $flag = false;
    if (!empty($str)) {
        $str = strtolower($str);
        $key = array('curl', 'wget', 'chmod', 'eval', 'system', 'exec');
        $flag = true;
        foreach ($key as $value) {
            if (strpos($str, $value) !== false) {
                $flag = false;
                break;
            }
        }
    }
    return $flag;
}

/**
 *  判断一个数是否是正的整数(没有小数点)
 * @param $int
 * @return bool
 */
function isInteger($int)
{
    if (is_numeric($int) && $int > 0 && !strpos($int, ".")) {
        return true;
    } else {
        return false;
    }
}

/**
 * 把用户输入的文本转义（主要针对特殊符号和emoji表情）
 */
function userTextEncode($str)
{
    if (!is_string($str)) return $str;
    if (!$str || $str == 'undefined') return '';

    $text = json_encode($str); //暴露出unicode
    $text = preg_replace_callback("/(\\\u[ed][0-9a-f]{3})/i", function ($str) {
        return addslashes($str[0]);
    }, $text); //将emoji的unicode留下，其他不动，这里的正则比原答案增加了d，因为我发现我很多emoji实际上是\ud开头的，反而暂时没发现有\ue开头。
    return json_decode($text);
}

/**
 * 解码上面的转义
 */
function userTextDecode($str)
{
    $text = json_encode($str); //暴露出unicode
    $text = preg_replace_callback('/\\\\\\\\/i', function ($str) {
        return '\\';
    }, $text); //将两条斜杠变成一条，其他不动
    return json_decode($text);
}

function stores_fotmat_number($num)
{
    $num = intval($num);
    if ($num < 10000) return $num;

    $num = keepPoint($num / 10000, 2);
    return $num . '万';
}

/**
 * 设置账户资金
 * @param int $currency_id 积分类型ID
 * @param int $num 交易数量
 * @param char $inc_dec setDec setInc 是加钱还是减去
 * @param char forzen_num num
 */
function setUserMoney($member_id, $currency_id, $num, $inc_dec, $field)
{
    $inc_dec = strtolower($inc_dec);
    $field = strtolower($field);
    //允许传入的字段
    if (!in_array($field, array('num', 'forzen_num'))) {
        return false;
    }
    //如果是RMB
    if ($currency_id == 0) {
        //修正字段
        switch ($field) {
            case 'forzen_num':
                $field = 'forzen_rmb';
                break;
            case 'num':
                $field = 'rmb';
                break;
        }
        switch ($inc_dec) {
            case 'inc':
                $msg = db('Member')->where(array('member_id' => $member_id))->setInc($field, $num);
                break;
            case 'dec':
                $msg = db('Member')->where(array('member_id' => $member_id))->setDec($field, $num);
                break;
            default:
                return false;
        }
        return $msg;
    } else {
        switch ($inc_dec) {
            case 'inc':
                $msg = db('Currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setInc($field, $num);
                break;
            case 'dec':
                $msg = db('Currency_user')->where(array('member_id' => $member_id, 'currency_id' => $currency_id))->setDec($field, $num);
                break;
            default:
                return false;
        }
        return $msg;
    }
}

/**
 * 解密 私钥
 * @param $private_key
 * @return false
 */
function decryption_private_key($private_key)
{
    if (empty($private_key)) return false;

    $data = base64_decode($private_key);
    $xorEncrypt = new \encrypt\XorEncrypt("TheKey");
    $result = $xorEncrypt->encrypt($data); //解密后的结果
    return strrev($result);
}

function str_Limit($str, $len)
{
    $iLen = strlen($str);
    $r = "";
    if ($iLen <= $len) $r = $str;
    else $r = substr($str, 0, $len);
    return $r;
}

if (!function_exists('posix_getpid')) {
    function posix_getpid()
    {
        return getmypid();
    }
}

function public_thread()
{
    $token_id = input("token_id", 0, 'intval');
    $server = request()->ip() . "-" . posix_getpid(); // ip + pid
    $check = md5($token_id . request()->url());

    $flag = 0;
    try {
        $flag = Db::name('thread')->insert([
            'single' => str_Limit($check, 30),
            'tag' => str_Limit($server, 30)
        ]);
        _Log_For_M("thread", "server: " . $server . ' | check: ' . $check . ' | 1 flag:' . $flag);
        if ($flag > 0) return true;
        return false;
    } catch (\think\Exception $exception) {
        _Log_For_M("thread", "server: " . $server . ' | check: ' . $check . ' | 0 flag:' . $flag);
        return false;
    }
}