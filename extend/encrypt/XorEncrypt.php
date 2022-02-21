<?php
namespace encrypt;

/**
 * 异或加密：利用异或运算的原理
 * A ^ 0 = A
 * A ^ A = 0

$xorEncrypt = new \encrypt\XorEncrypt("TheKey");
$result = $xorEncrypt->encrypt("hello");
var_dump($result); //加密后结果
$ori = $xorEncrypt->encrypt($result);
var_dump($ori); //解密后结果

与前端交互 前端数据进行了base64加密 需先解密
$data = base64_decode("HF0VOQRKOT8rIzYPGQ0LGFBANV4IJCQ3DDE2HwsMAAw2KQM1IDoRGjZKAT4IcjY7BCJQ");
$xorEncrypt = new \encrypt\XorEncrypt("TheKey");
$result = $xorEncrypt->encrypt($data); //解密后的结果
 */
class XorEncrypt
{
    public $key = "TheKey"; //加密密钥

    public function __construct($key)
    {
        $this->key = $key;
    }

    function encrypt($data) {
        $dataBytes = $this->strToBytes($data);
        $keyBytes = $this->strToBytes($this->key);

        $encryptBytes = [];
        for($i=0; $i< count($dataBytes); $i++){
            $encryptBytes[$i] = $dataBytes[$i]^$keyBytes[$i%count($keyBytes)];
        }

        $encryptStr = $this->bytesToStr($encryptBytes);
        return $encryptStr;
    }

    //字符串转为ascii码
    function strToBytes($string) {
        $bytes = array();
        for($i = 0; $i < strlen($string); $i++){
            $bytes[] = ord($string[$i]);
        }
        return $bytes;
    }

    //ascii码数组转为字符串
    function bytesToStr($bytes) {
        $str = '';
        foreach($bytes as $ch) {
            $str .= chr($ch);
        }
        return $str;
    }
}
