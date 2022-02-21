<?php


namespace app\common\model;


use PDOStatement;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;

class AirLadderLevel extends Model
{
    const STATUS_OPEN = 1;

    const STATUS_CLOSE = 2;

    const STATUS_ENUM = [
        self::STATUS_OPEN => '启用',
        self::STATUS_CLOSE => '禁用',
    ];

    /**
     * 根据ID获取Level信息
     * @param int $levelId
     * @return array|false|PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function getLevelById($levelId)
    {
        return $this->where('id', $levelId)->where('status', self::STATUS_OPEN)->find();
    }

    /**
     * 更具数量获取等级信息
     * @param double $number
     * @return array|false|PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getLevelByNumber($number)
    {
        return $this->where('up_number', '<=', $number)->order('id', 'desc')->find();
    }

}