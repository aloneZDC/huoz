<?php
//投票 俱乐部 配置
namespace app\common\model;


use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class UsersVotesConfig extends Model
{
    static function get_config()
    {
        $list = self::select();
        if(empty($list)) return [];
        return array_column($list, null, 'uvs_key');
    }

    static function get_key_value() {
        $list = self::select();
        if(empty($list)) return [];
        return array_column($list, 'uvs_value', 'uvs_key');
    }

    //获取配置的最高等级
    static function getMaxLevel() {
        $level =  self::where(['uvs_type'=>'level'])->max('uvs_level');
        return $level ? $level : 0;
    }

    //获取升级需要的票数
    static function getLevelNeedVotes($level,$votes_config) {
        if($level<=0) return 0;
        if(!isset($votes_config['level'.$level])) return 0;
        return $votes_config['level'.$level]['uvs_value'];
    }

    static function getNextLevel($level,$max_level) {
        $next_level = $level+1;
        if($next_level>$max_level) $next_level = $max_level;
        return $next_level;
    }

    //根据投票获取 等级
    static function getLevelByVotes($votes,$vote_config=[]) {
        if(empty($vote_config)) $vote_config = self::get_config();
        $cur_level =  0;
        foreach ($vote_config as $config) {
            if($config['uvs_type']=='level' && $votes>=$config['uvs_value'] & $config['uvs_level']>=$cur_level){
                $cur_level = $config['uvs_level'];
            }
        }
        return $cur_level;
    }

    //根据积分获取 等级
    static function getLevelByNum($num,$vote_config=[]) {
        if(empty($vote_config)) $vote_config = self::get_config();

        $votes = intval($num/$vote_config['votes_one_num']['uvs_value']);
        return self::getLevelByVotes($votes,$vote_config);
    }

    static function getLevelName($level) {
        $info = self::where(['uvs_key'=>'level'.$level])->find();
        if(!$info) return 'V'.$level;

        return $info['uvs_desc'];
    }

    static function getLevelNameList() {
        $list =  self::where(['uvs_type'=>'level'])->select();
        if(!$list) return [];
        return array_column($list,'uvs_desc','uvs_level');
    }

    static function getLevelNameListArray() {
        $list =  self::where(['uvs_type'=>'level'])->field('uvs_level,uvs_desc')->select();
        if(!$list) return [];
        return $list;
    }
}