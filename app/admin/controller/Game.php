<?php
namespace app\admin\controller;

use app\common\model\GameConfig;
use think\Db;
use think\Exception;
use think\Request;

class Game extends Admin {
    //空操作
    public function _empty(){
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    /**
     * 游戏配置
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function config()
    {
        $configSelect = GameConfig::select();
        $configList = [];
        foreach ($configSelect as $key => $value) {
            $configList[] = $value;
        }
        $this->assign('configList',$configList);
        return $this->fetch();
    }

    /**
     * 游戏配置-更新
     */
    public function updateCofig(Request $request)
    {
        $config = $request->post('config/a');
        foreach ($config as $key => $value) {
            $data[] = [
                'gc_key'=>$key,
                'gc_value'=>$value,
            ];
        }

        $gameConfig = new GameConfig;
        $save = $gameConfig->saveAll($data);

        if ($save === false) {
            return $this->error('修改失败!请重试');
        }

        return $this->success('修改成功!');
    }
}