<?php
//线下商家
namespace app\common\model;

use think\Db;
use think\Model;

class ArticleCategory extends Model {
    static function getArticles($lang,$position_id) {
        if (empty($lang)) {
            $field = 'c.article_id,c.title,c.art_pic,c.add_time,t.name as title_name';
            $cat_field = 'id,name';
        } else {
            $field = 'c.article_id,c.' . $lang . '_title as title,c.art_pic,c.add_time,t.name_' . $lang . ' as title_name';
            $cat_field = 'id,name_'.$lang.' as name';
        }

        $where = [];
        if($position_id) $where['id'] = $position_id;
        $cats = self::where('is_help=1')->where($where)->field($cat_field)->order('sort asc')->select();
        $articleModel = db('Article');
        foreach ($cats as &$cat) {
            $list = $articleModel->field($field)->alias('c')->join("article_category t ", "   c.position_id=t.id ", 'left')->where(['position_id'=>$cat['id']])->order('c.sort asc,c.add_time desc')->select();
            $cat['articles'] = $list;
        }
        return $cats;
    }
}
