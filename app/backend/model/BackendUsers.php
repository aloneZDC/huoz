<?php


namespace app\backend\model;


use think\Model;

/**
 * Class AdminUser
 * @package app\common\model
 */
class BackendUsers extends Model
{
    /**
     * @var array
     */
    protected $type = [
        'last_login_time'  =>  'timestamp:Y/m/d H:i',
    ];


    public function address()
    {

    }


    /**
     * 币种ID获取器
     * @param string $value
     * @return array
     */
    public function getCurrencyIdsAttr($value)
    {
        return explode(",", $value);
    }

    /**
     * Paper用户获取器
     * @param string $value
     * @return array
     */
    public function getPaperUserIds($value)
    {
        return explode(",", $value);
    }

   /**
     * 根据用户名和密码获取用户信息
     * @param string $username
     * @param string $password
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getUserWithLogin($username, $password)
    {
        return self::where(['username' => $username, 'password' => md5($password)])->find();
    }

    /**
     * 更新用户信息
     * @param int $id
     * @param int $time
     * @param string $loginIp
     * @return AdminUser
     */
    public static function updateLoginInfo($id, $time, $loginIp = "0.0.0.0")
    {
        return self::where('id', $id)->update([
            'last_login_time' => $time,
            'last_login_ip' => $loginIp
        ]);
    }

    /**
     * 根据id获取用户信息
     * @param int $id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getUserInfoBy($id) {
        return self::where('id', $id)->find();
    }

}
