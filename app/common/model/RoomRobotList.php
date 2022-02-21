<?php
//房间机器人列表
namespace app\common\model;


use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Model;

class RoomRobotList extends Model
{
    /**
     * 添加机器人
     * @param $num
     * @return bool
     * @throws \Exception
     */
    static function create_robot($num)
    {
        $list = [];
        for ($i = 1; $i <= $num; $i++)
        {
            $rand = mt_rand(1, 2);
            if ($rand == 1) {
                $mobile = self::randomMobile(1);
                $nickname = substr($mobile,0,3).'****'.substr($mobile,-4);
            }
            else {
                $email = self::randomEmail(1);
                $nickname = substr($email,0,3).'****'.substr($email,-7);
            }
            //$nickname = "etk_" . getNonceStr(8);
            $list[] = [
                'rrl_nickname'=>$nickname,
            ];
        }
        $robot = new RoomRobotList;
        $robot->saveAll($list);
        return true;
    }

    /**
     * 创建一个和数据库不存在的昵称
     * @return string
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    static function create_nickname()
    {
        $nickname = "etk_" . getNonceStr(8);
        $find = self::field("rrl_id")->where(['rrl_nickname' => $nickname])->find();
        if (empty($find)) {
            return $nickname;
        } else {
            return self::create_nickname();
        }
    }

    //随机生成手机号
    static function randomMobile()
    {
        $tel_arr = array(
            '130','131','132','133','134','135','136','137','138','139','144','147','150','151','152','153','155','156','157','158','159','176','177','178','180','181','182','183','184','185','186','187','188','189',
        );
        $tmp = $tel_arr[array_rand($tel_arr)].mt_rand(1000,9999).mt_rand(1000,9999);
        // $tmp = $tel_arr[array_rand($tel_arr)].'xxxx'.mt_rand(1000,9999);
        return $tmp;
    }

    //随机生成邮箱
    static function randomEmail()
    {
        $tmp = mt_rand(10000,99999).mt_rand(1000,9999).'@qq.com';
        return $tmp;
    }
}