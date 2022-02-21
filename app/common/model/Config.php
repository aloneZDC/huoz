<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Db;

class Config extends Base
{
    /**
     * @param array $where
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function byField($where = [])
    {
        $return = [];

        $list = Db::name('Config')->select();
        if ($list) {
            foreach ($list as $key => $value) {
                $return[$value['key']] = $value['value'];
            }
        }
        return $return;
    }

    /**
     * 获取配置
     * @param string $key
     * @param string $default
     * @return mixed|string
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function get_value($key, $default = "")
    {
        $find = (new Config)->where('key', $key)->find();
        if (empty($find)) {
            return $default;
        }
        return $find['value'];
    }
}
