<?php
namespace app\cli\controller;

use app\common\model\RocketBuyList;
use app\common\model\RocketOrder;
use think\Log;
use think\Db;
use think\Exception;
use think\console\Input;
use think\console\Output;
use think\console\Command;
use app\common\model\RocketConfig;
use app\common\model\RocketGoods;
use app\common\model\RocketGoodsList;

class RocketLevel extends Command
{
    protected $name = '生成闯关定时任务';
    protected $today_config = [];
    protected $mining_config = [];

    protected $all_levels = [];

    protected function configure()
    {
        $this->setName('RocketLevel')->setDescription('This is a RocketLevel');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set("display_errors", 1);
        ini_set('memory_limit', '-1');
        config('database.break_reconnect', true);
        \think\Request::instance()->module('cli');

        $this->doRun();
    }

    public function doRun($today = '') {
        if (empty($today)) $today = date('Y-m-d');
        $today_start = strtotime($today);
        $this->today_config = [
            'today' => $today,
            'today_start' => $today_start,
            'today_end' => $today_start + 86399,
            'yestday_start' => $today_start - 86400,
            'yestday_stop' => $today_start - 1,
        ];

        $this->mining_config = RocketConfig::get_key_value();
        if (empty($this->mining_config)) {
            Log::write($this->name . " 配置为空");
            return;
        }
        //生成闯关
        $this->create_level();

        //生成方舟
        $this->create_ark_level();
    }

    //生成闯关
    public function create_level() {
        if ($this->mining_config['set_game_start'] != 1) {
            Log::write($this->name . " 创建闯关 关闭");
            return;
        }

        $res = RocketGoods::where(['status' => 1])->select();
        if (!$res){
            Log::write($this->name . " 创建闯关：没有开启的闯关");
            return;
        }
        Db::startTrans();
        try {
            foreach ($res as $key => $value) {
                $level = 1;
                $list = RocketGoodsList::where(['goods_id' => $value['id']])->order('id desc')->find();
                if (!empty($list) && $list['is_show'] == 1) {
                    //存在队列，跳过生成闯关
                    $order_list = RocketBuyList::where(['goods_list_id' => $list['id'], 'status' => 0])->find();
                    if ($order_list) {
                        continue;
                    }

                    $flag = true;
                    $status = 1;
                    //闯关金额完成生成下一关
                    if (sprintf('%.2f', $list['finish_money']) >= sprintf('%.2f', $list['price'])) {
                        $status = 2;
                    }
                    if (time() >= $list['end_time'] && $status == 2) {//闯关成功
                        $status = 2;
                    } elseif (time() >= $list['end_time'] && $status != 2) {//闯关失败
                        $status = 3;
                    }

                    if ($status == 2) {
                        //闯关成功，创建下一关
                        $flag = RocketGoodsList::add_goods($value['id'], 2);
                        $level = $list['level'] + 1;
                    } elseif ($status == 3) {
                        //闯关失败，创建第一关
                        $flag = RocketGoodsList::add_goods($value['id']);
                    }
                    if ($flag === false) throw new Exception("创建闯关失败");

                    $flag = RocketGoods::where(['id' => $value['id']])->update(['max_level' => $level]);
                    if ($flag === false)  throw new Exception("更新最大闯关数失败" . $value['id']);
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::write($e->getMessage());
        }
    }

    //生成方舟
    public function create_ark_level() {
        $config = \app\common\model\ArkConfig::get_key_value();
        if (empty($config) || $config['set_game_start'] != 1) {
            Log::write($this->name . " 创建方舟 关闭");
            return;
        }

        $res = \app\common\model\ArkGoods::where(['status' => 1])->select();
        if (!$res){
            Log::write($this->name . " 创建方舟：没有开启的方舟");
            return;
        }
        Db::startTrans();
        try {
            foreach ($res as $key => $value) {
                $level = 1;
                $list = \app\common\model\ArkGoodsList::where(['goods_id' => $value['id']])->order('id desc')->find();
                if (!empty($list) && $list['is_show'] == 1) {
                    //存在队列，跳过生成方舟
                    $order_list = \app\common\model\ArkBuyList::where(['goods_list_id' => $list['id'], 'status' => 0])->find();
                    if ($order_list) {
                        continue;
                    }

                    $flag = true;
                    $status = 1;
                    //闯关金额完成生成下一关
                    if (sprintf('%.2f', $list['finish_money']) >= sprintf('%.2f', $list['price'])) {
                        $status = 2;
                    }
                    if (time() >= $list['end_time'] && $status == 2) {//闯关成功
                        $status = 2;
                    } elseif (time() >= $list['end_time'] && $status != 2) {//闯关失败
                        $status = 3;
                    }

                    if ($status == 2) {
                        //闯关成功，创建下一关
                        $flag = \app\common\model\ArkGoodsList::add_goods($value['id'], 2);
                        $level = $list['level'] + 1;
                    } elseif ($status == 3) {
                        //闯关失败，创建第一关
                        $flag = \app\common\model\ArkGoodsList::add_goods($value['id']);
                    }
                    if ($flag === false) throw new Exception("创建方舟失败");

                    $flag = \app\common\model\ArkGoods::where(['id' => $value['id']])->update(['max_level' => $level]);
                    if ($flag === false)  throw new Exception("更新最大闯关数失败" . $value['id']);
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::write($e->getMessage());
        }
    }
}