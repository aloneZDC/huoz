<?php
//翻牌币种
namespace app\common\model;
use think\Exception;
use think\Model;

class FlopTradeReleaseConfig extends Model
{
    static function getList() {
        $list = self::order('num desc')->select();
        if(!$list) return [];
        return $list;
    }

    //根据持仓数量得到比例
    static function getConfigByNum($configList,$num) {
        $config = [];
        foreach ($configList as $item) {
            if($num>=$item['num']) {
                $config = $item;
                break;
            }
        }
        return $config;
    }

    static function getUserConfig($num) {
        $configList = self::getList();
        $config = [];
        foreach ($configList as $item) {
            if($num>=$item['num']) {
                $config = $item;
                break;
            }
        }
        return $config;
    }
}
