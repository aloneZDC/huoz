<?php

namespace app\cli\controller;

use app\common\model\MemberBind;
use think\console\Command;
use think\Log;
use think\Db;
use think\Exception;
use think\console\Input;
use think\console\Output;

/**
 * 更新团队新业绩
 */
class MemberLevelTask extends Command
{
    protected function configure()
    {
        $this->setName('MemberLevelTask')->setDescription('This is a test');
    }

    protected function execute(Input $input, Output $output)
    {
        \think\Request::instance()->module('cli');
        $this->update_level();
    }

    function update_level($same_day,$level_cur)
    {
        echo "update_level:".$level_cur."\r\n";
        Log::write("等级升级运行开始:".date("Y-m-d H:i:s",time()) , "INFO");
        try {

            $config = Db::name('boss_config')->where("key","in" ,["V1","V2","V3","V4","V5","V6"])->select();
            if (empty($config)) {
                throw new Exception("参数没有配置");
            }
            $config=array_column($config,null,"key");
            //->where("upgrade_time","not between",[todayBeginTimestamp(),todayEndTimestamp()])
            $count = Db::name("boss_plan_info")->where("level", "=", $level_cur)->count("member_id");
            $rows = 100;
            $page = ceil($count / $rows);
            for ($i = 1; $i <= $page; $i++) {
                //查询用户的等级
                $list = Db::name("boss_plan_info")->where("level", "=", $level_cur)->page($i, $rows)->order("level asc")->select();
                foreach ($list as $value) {
                    Db::startTrans();
                    $nextLevel = "V" . ($value['level'] + 1);
                    $pass = false;
                    //团队的业绩
                    $sum1 = Db::name('boss_bouns_week')->field('sum(num+child_num) as num')->where(['member_id'=>$value['member_id'],'bonus_time'=>['lt',$same_day['yestoday_stop']]])->find();
                    $sum1 = $sum1 ? $sum1['num'] : 0;
                    if ($nextLevel == "V1") {
                        $v1Config = isset($config["V1"]) ? $config["V1"] : null;
                        if (!empty($v1Config)) {
                            $v1Config['value'] = unserialize($v1Config['value']);
//                            $v1Count = MemberBind::statisticsChildLevelByLevel($value['member_id'], 0);
                            //直推的人数
//                            if ($v1Count >= $v1Config['value']['recommend']) {
                                if ($sum1 >= $v1Config['value']['money']) {
                                    Log::write($value['member_id'].'升级成功V1');
                                    //更新用户的等级
                                    $time = time();
                                    $update = Db::name("boss_plan_info")->where(['member_id' => $value['member_id']])->update(['level' => 1,'upgrade_time_real'=>$time,'upgrade_time'=>$time]);
                                    if ($update) {
                                        $pass = true;
                                        //更新关系表里的等级
                                        MemberBind::updateChileLevel($value['member_id'], 1);
                                    } else {
                                        throw new Exception("用户id:" . $value['member_id'] . " 升级V1时更新失败");
                                    }
                                } else {
                                    Log::write($value['member_id'].'升级V1业绩不足'.$sum1);
                                }
//                            }else{
//                                //更新用户当前所培养的人数
//                                Db::name("boss_plan_info")->where(['member_id' => $value['member_id']])->update(['next_leve_num' =>$v1Count]);
//                                Log::write($value['member_id'].'升级V1直推不足'.$v1Count);
//                            }

                        } else {
                            throw new Exception("V1参数没有配置");
                        }
                    }
                    if ($nextLevel == "V2") {
                        $v2Config = isset($config["V2"]) ? $config["V2"] : null;
                        if (!empty($v2Config)) {
                            $v2Config['value'] = unserialize($v2Config['value']);
//                            $le = explode( "V",$v2Config['value']['culture']);
//                            $level = $le[1];
//                            $v2Count = MemberBind::statisticsChildLevelByLevel($value['member_id'], $level);
                            //直推的人数
//                            if ($v2Count >= $v2Config['value']['recommend']) {
                                if ($sum1 >= $v2Config['value']['money']) {
                                    Log::write($value['member_id'].'升级成功V2');
                                    $update = Db::name("boss_plan_info")->where(['member_id' => $value['member_id']])->update(['level' => 2,'upgrade_time_real'=>time(),'upgrade_time'=>time()]);
                                    if ($update) {
                                        $pass = true;
                                        //更新关系表里的等级
                                        MemberBind::updateChileLevel($value['member_id'], 2);
                                    } else {
                                        throw new Exception("用户id:" . $value['member_id'] . " 升级V2时更新失败");
                                    }
                                }else{
                                    Log::write($value['member_id'].'升级V2业绩不足'.$sum1);
                                }

//                            } else {
//                                //更新用户当前所培养的人数
//                                Db::name("boss_plan_info")->where(['member_id' => $value['member_id']])->update(['next_leve_num' =>$v2Count]);
//                                $pass = false;
//                                Log::write($value['member_id'].'升级V2，V1人数不足'.$v2Count);
//                            }

                        } else {
                            throw new Exception("V2参数没有配置");
                        }

                    }
                    if ($nextLevel == "V3") {
                        $v3Config = isset($config["V3"]) ? $config["V3"] : null;
                        if (!empty($v3Config)) {
                            $v3Config['value'] = unserialize($v3Config['value']);
//                            $le = explode( "V",$v3Config['value']['culture']);
//                            $level = $le[1];
//                            $v3Count = MemberBind::statisticsChildLevelByLevel($value['member_id'], $level);
                            //直推的人数
//                            if ($v3Count >= $v3Config['value']['recommend']) {
                                if ($sum1 >= $v3Config['value']['money']) {
                                    Log::write($value['member_id'].'升级成功V3');
                                    $update = Db::name("boss_plan_info")->where(['member_id' => $value['member_id']])->update(['level' => 3,'upgrade_time_real'=>time(),'upgrade_time'=>time()]);
                                    if ($update) {
                                        $pass = true;
                                        //更新关系表里的等级
                                        MemberBind::updateChileLevel($value['member_id'], 3);
                                    } else {
                                        throw new Exception("用户id:" . $value['member_id'] . " 升级V3时更新失败");
                                    }
                                }else{
                                    Log::write($value['member_id'].'升级V3业绩不足'.$sum1);
                                }
//                            } else {
//                                //更新用户当前所培养的人数
//                                Db::name("boss_plan_info")->where(['member_id' => $value['member_id']])->update(['next_leve_num' =>$v3Count]);
//                                $pass = false;
//                                Log::write($value['member_id'].'升级V3，V2人数不足'.$v3Count);
//                            }

                        } else {
                            throw new Exception("V3参数没有配置");
                        }

                    }
                    if ($nextLevel == "V4") {

                        $v4Config = isset($config["V4"]) ? $config["V4"] : null;
                        if (!empty($v4Config)) {
                            $v4Config['value'] = unserialize($v4Config['value']);
//                            $le = explode( "V",$v4Config['value']['culture']);
//                            $level = $le[1];
//                            $v4Count = MemberBind::statisticsChildLevelByLevel($value['member_id'], $level);
                            //直推的人数
//                            if ($v4Count >= $v4Config['value']['recommend']) {
                                if ($sum1 >= $v4Config['value']['money']) {
                                    Log::write($value['member_id'].'升级成功V4');
                                    $update = Db::name("boss_plan_info")->where(['member_id' => $value['member_id']])->update(['level' => 4,'upgrade_time_real'=>time(),'upgrade_time'=>time()]);
                                    if ($update) {
                                        $pass = true;
                                        //更新关系表里的等级
                                        MemberBind::updateChileLevel($value['member_id'], 4);
                                    } else {
                                        throw new Exception("用户id:" . $value['member_id'] . " 升级V4时更新失败");
                                    }
                                }else{
                                    Log::write($value['member_id'].'升级V4业绩不足'.$sum1);
                                }
//                            } else {
//                                //更新用户当前所培养的人数
//                                Db::name("boss_plan_info")->where(['member_id' => $value['member_id']])->update(['next_leve_num' =>$v4Count]);
//                                $pass = false;
//                                Log::write($value['member_id'].'升级V4，V3人数不足'.$v4Count);
//                            }

                        } else {
                            throw new Exception("V4参数没有配置");
                        }
                    }
                    if ($nextLevel == "V5") {

                        $v5Config = isset($config["V5"]) ? $config["V5"] : null;
                        if (!empty($v5Config)) {
                            $v5Config['value'] = unserialize($v5Config['value']);
//                            $le = explode( "V",$v5Config['value']['culture']);
//                            $level = $le[1];
//                            $v5Count = MemberBind::statisticsChildLevelByLevel($value['member_id'], $level);
//                            //直推的人数
//                            if ($v5Count >= $v5Config['value']['recommend']) {
                                if ($sum1 >= $v5Config['value']['money']) {
                                    Log::write($value['member_id'].'升级成功V5');
                                    $update = Db::name("boss_plan_info")->where(['member_id' => $value['member_id']])->update(['level' => 5,'upgrade_time_real'=>time(),'upgrade_time'=>time()]);
                                    if ($update) {
                                        $pass = true;
                                        //更新关系表里的等级
                                        MemberBind::updateChileLevel($value['member_id'], 5);
                                    } else {
                                        throw new Exception("用户id:" . $value['member_id'] . " 升级V5时更新失败");
                                    }
                                }else{
                                    Log::write($value['member_id'].'升级V5业绩不足'.$sum1);
                                }

//                            } else {
//                                //更新用户当前所培养的人数
//                                Db::name("boss_plan_info")->where(['member_id' => $value['member_id']])->update(['next_leve_num' =>$v5Count]);
//                                $pass = false;
//                                Log::write($value['member_id'].'升级V5，V4人数不足'.$v5Count);
//                            }

                        } else {
                            throw new Exception("V5参数没有配置");
                        }
                    }
                    if ($nextLevel == "V6") {
                        $v6Config = isset($config["V6"]) ? $config["V6"] : null;
                        if (!empty($v6Config)) {
                            $v6Config['value'] = unserialize($v6Config['value']);
//                            $le = explode( "V",$v6Config['value']['culture']);
//                            $level = $le[1];
//                            $v6Count = MemberBind::statisticsChildLevelByLevel($value['member_id'], $level);
//                            //直推的人数
//                            if ($v6Count >= $v6Config['value']['recommend']) {
                                if ($sum1 >= $v6Config['value']['money']) {
                                    Log::write($value['member_id'].'升级成功V6');
                                    $update = Db::name("boss_plan_info")->where(['member_id' => $value['member_id']])->update(['level' => 6,'upgrade_time_real'=>time(),'upgrade_time'=>time()]);
                                    if ($update) {
                                        //更新关系表里的等级
                                        MemberBind::updateChileLevel($value['member_id'], 6);
                                    } else {
                                        throw new Exception("用户id:" . $value['member_id'] . " 升级V6时更新失败");
                                    }
                                }else{
                                    Log::write($value['member_id'].'升级V6业绩不足'.$sum1);
                                }
//                            }else{
//                                //更新用户当前所培养的人数
//                                Db::name("boss_plan_info")->where(['member_id' => $value['member_id']])->update(['next_leve_num' =>$v6Count]);
//                                Log::write($value['member_id'].'升级V6，V5人数不足'.$v6Count);
//                            }

                        } else {
                            throw new Exception("V6参数没有配置");
                        }
                    }
                    Db::commit();
                }
            }
        } catch (Exception $exception) {
            Log::write("用户等级升级错误：" . $exception->getMessage(), "INFO");
            Db::rollback();
            return false;
        }
        Log::write("等级升级运行完成:".date("Y-m-d H:i:s",time()) , "INFO");
        return true;
    }

}
