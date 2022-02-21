<?php


namespace app\backend\model;


use think\Db;
use think\Model;

class Article extends Model
{
    public function category()
    {
        return $this->belongsTo('app\\common\\model\\ArticleCategory', 'position_id', 'id');
    }
}
