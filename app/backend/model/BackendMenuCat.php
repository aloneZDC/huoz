<?php


namespace app\backend\model;


use think\Db;
use think\Model;

class BackendMenuCat extends Model
{
    static function getAll() {
        return self::order('sort_id asc,id asc')->select();
    }
}
