<?php


namespace app\common\model;


use think\Model;

class Information extends Model
{
    /**
     * @var string
     */
    protected $table = "yang_information";


    /**
     * @return \think\model\relation\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(InformationCategory::class, 'category_id');
    }
}