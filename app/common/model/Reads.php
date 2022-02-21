<?php


namespace app\common\model;


use think\Model;

class Reads extends Model
{
    const TYPE_NEWS = 1;

    const TYPE_FANS = 2;

    const READ_AVATAR = "http://io-app.oss-cn-shanghai.aliyuncs.com/turbo/reads.png";

//    const READ_NAME = "雷火令牌";

    public static function getReads($type = 1,$is_hot=0, $page = 1, $rows = 10, $language = 'zh-cn')
    {
        if (self::TYPE_NEWS == $type) {
            // 资讯列表 不显示content
            $field = 'article_id, add_time, art_pic, type';
            $field .= strtolower($language) == "en-us" ? ' ,en_title as title,en_content as content,from_name_en as from_name' : ' ,title,content,from_name';
        } else {
            $field = 'article_id, add_time, art_pic, type, from_name';
            $field .= strtolower($language) == "en-us" ? ' ,en_title as title,en_content as content' : ' ,title, content';
        }

        $data = (new self)->field($field)->where('type', $type)->where('is_hot',$is_hot)->order('article_id desc')->page($page, $rows)->select();

        foreach ($data as &$item) {
//            $item['add_time'] = self::format_date($item['add_time']);
            $item['add_time'] = date('Y-m-d H:i',$item['add_time']);
            $item['art_pic'] = json_decode($item['art_pic']);
            if (!empty($item['content'])) {
                $item['content'] = htmlspecialchars_decode($item['content']);
            } else {
                $item['content'] = '';
            }
            unset($item['type']);
        }

        return $data;
    }


    public static function read($id)
    {
        $data = (new self)->field('article_id, title, content, add_time')->where('article_id', $id)->find();
//        $data['add_time'] = self::format_date($data['add_time']);

        return $data;
    }

    private static function format_date($timestamp)
    {
        $t = time() - $timestamp;

        $f = [
            31536000 => lang('lan_year'),
            2592000 => lang('lan_month'),
            604800 => lang('lan_week'),
            86400 => lang('lan_day'),
            3600 => lang('lan_hour'),
            60 => lang('lan_minute'),
            1 => lang('lan_second')
        ];

        foreach ($f as $k => $v) {
            if (0 != $c = floor($t / (int)$k)) {
                return $c .' '. $v .' '. lang('lan_front');
            }
        }

        return '';
    }


}
