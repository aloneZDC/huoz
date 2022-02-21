<?php
namespace Admin\Controller;
use Common\Controller\CommonController;
use Think\Page;
use Think\Exception;

class MeetingController extends AdminController {
    public function index(){
        $phone=I('phone');
        $member_id = I('member_id');
        $pay_id = I('pay_id');
        
        if(!empty($phone)) $where['b.phone'] = $phone;
        if(!empty($member_id)) $where['a.member_id'] = $member_id;
        if(!empty($pay_id)) $where['a.pay_id'] = $pay_id;

        $status = I('status','');
        if($status!='') {
            $status = intval($status);
            $where['a.status'] = $status;
        }

        $count      = M('Meeting')->alias('a')->join('LEFT JOIN yang_member as b on a.member_id = b.member_id ')->where($where)->count();
        $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        setPageParameter($Page, array('phone'=>$phone,'member_id'=>$member_id,'pay_id'=>$pay_id,'status'=>$status));
         
        $show       = $Page->show();// 分页显示输出
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M('Meeting')->alias('a')->field('a.*,b.phone as attend_phone,b.name as attend_name,b.email as attend_email,c.phone as pay_phone,c.email as pay_email,c.name as pay_name')
            ->where($where)
            ->join('LEFT JOIN yang_member as b on a.member_id = b.member_id ')
            ->join('LEFT JOIN yang_member as c on a.pay_id = c.member_id ')
            ->order("a.id desc")
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }

    //添加参与资格
    public function meeting_pass() {
        if(IS_POST) {
            $member_id = intval(I('member_id'));
            if(empty($member_id)) $this->error('用户ID不能为空');
            $member = M('member')->where(['member_id'=>$member_id])->find();
            if(empty($member)) $this->error('用户不存在');

            $info = M('meeting_pass')->where(['member_id'=>$member_id])->find();
            if($info)  $this->error('已经具备资格');

            $flag = M('meeting_pass')->add(['member_id'=>$member_id]);
            if($flag) {
                $this->success('修改成功');
            } else {
                $this->error('添加失败');
            }
        } else {
            $this->display();
        }
    }

    //删除参与资格
    public function meeting_pass_del() {
        if(IS_POST) {
            $member_id = intval(I('member_id'));
            if(empty($member_id)) $this->error('用户ID不能为空');
            $member = M('member')->where(['member_id'=>$member_id])->find();
            if(empty($member)) $this->error('用户不存在');

            $info = M('meeting_pass')->where(['member_id'=>$member_id])->find();
            if(empty($info))  $this->error('不具备资格');

            $flag = M('meeting_pass')->where(['member_id'=>$member_id])->delete();
            if($flag) {
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        } else {
            $this->display();
        }
    }

    //导出
    public function export() {
        // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
        $list = M('Meeting')->alias('a')->field('a.*')->order("a.id desc")->limit(1000)->select();

        foreach ($list as $key => &$value) {
            $value['sex'] = $value['sex']==1 ? '男' : '女';
            $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']);
            if($value['status']==0) {
                $value['status'] = '冻结中';
            } else {
                $value['status'] = '资金已返还';
            }
            $value['idcard'] = '#'.$value['idcard'];
        }

        $xlsName  = "泰国会议报名记录";
        $xlsCell  = array(
            array('id','序列号'),
            array('pay_id','支付者ID'),
            array('member_id','用户ID'),
            array('phone','手机'),
            array('name','姓名'),
            array('idcard','身份证号'),
            array('passport','护照'),
            array('sex','性别'),
            array('age','年龄'),
            array('add_time','时间'),
            array('status','状态'),
        );
        // $xlsModel = M('Post');
        $xlsData  = $list;
        $this->exportExcel($xlsName,$xlsCell,$xlsData);
    }

    //返还
    public function cancel() {
        $id=intval(I('id'));
        $meetingInfo = M('Meeting')->where(['id'=>$id])->find();
        if(empty($meetingInfo)) self::output(0, '记录不存在');

        if($meetingInfo['status']!=0) self::output(0, '已处理过,不能重复处理');

        M()->startTrans();
        try{
            //添加账本
            $result = D('Accountbook')->addLog([
                'member_id' => $meetingInfo['pay_id'],
                'currency_id' => $meetingInfo['currency_id'],
                'type'=> 25,
                'content' => 'lan_user_acctend_metting_back',
                'number_type' => 1,
                'number' => $meetingInfo['pay_number'],
                'fee' => 0,
                'to_member_id' => 0,
                'to_currency_id' => 0,
                'third_id' => $meetingInfo['id'],
                'add_time' => time(),
            ]);
            if(!$result) {
                M()->rollback();
                self::output(0, '操作失败');
            }

            $flag = M('currency_user')->where(['member_id'=>$meetingInfo['pay_id'],'currency_id'=>$meetingInfo['currency_id']])->save([
                'num' => ['exp','num+'.$meetingInfo['pay_number']],
                'forzen_num'=> ['exp','forzen_num-'.$meetingInfo['pay_number']],   
            ]);                
            if(!$flag) {
                M()->rollback();
                self::output(0, '操作失败');
            }

            $flag = M('Meeting')->where(['id'=>$meetingInfo['id'],'status'=>0])->setField('status',1);
            if(!$flag) {
                M()->rollback();
                self::output(0, L('lan_network_busy_try_again'));
            }

            M()->commit();
            self::output(1, L('lan_operation_success'));
        } catch(Exception $e) {
            M()->rollback();
            self::output(0, L('lan_operation_failure'));
        }
    }

    public function edit() {
        $id = I('id', '', 'intval');
        $meeting = M('Meeting');
        if (IS_POST) {
            $where['id'] = $id;
            $list = $meeting->where($where)->find();

            $data = [];

            $name = I('name');
            if(!empty($name)) $data['name'] = $name;

            $phone = I('phone');
            if(!empty($phone)) $data['phone'] = $phone;

            $idcard = I('idcard');
            if(!empty($idcard)) $data['idcard'] = $idcard;

            $passport = I('passport');
            if(!empty($passport)) $data['passport'] = $passport;

            $data['sex'] = intval(I('sex'));
            $data['age'] = intval(I('sex'));
            
            $r = $meeting->save($_POST);
            if ($r !== false) {
                $this->success('修改成功');
            } else {
                $this->error('修改失败');
                return;
            }
        } else {
            if ($id) {
                $where['id'] = $id;
                $list = $meeting->where($where)->find();
                $this->assign('list', $list);
                $this->display();
            } else {
                $this->error('参数错误');
                return;
            }
        }
    }


    /**
     * 批量返还冻结并补贴930XRP
     * Created by Red.
     * Date: 2019/4/3 11:53
     */
    public function batchCancel() {
        // $member_ids=array(105327,
        //     279561,
        //     100835,
        //     97624,
        //     97183,
        //     121325,
        //     286564,
        //     112011,
        //     162722,
        //     275218,
        //     292813,
        //     283682,
        //     186055,
        //     288907,
        //     115380,
        //     112544,
        //     212754,
        //     142569,
        //     211691,
        //     274562,
        //     89456,
        //     91961,
        //     213286,
        //     246700,
        //     231726,
        //     102545,
        //     284730,
        //     217808,
        //     293640,
        //     103310,
        //     273724,
        //     293956,
        //     292815,
        //     286943,
        //     240670,
        //     294312,
        //     107076,
        //     294905,
        //     266605,
        //     288669,
        //     131400,
        //     169669,
        //     91642,
        //     114268,
        //     285328,
        //     120571,
        //     256354,
        //     102847,
        //     137648,
        //     180835,
        //     148298,
        //     127190,
        //     292372,
        //     115565,
        //     112591,
        //     88944,
        //     293862,
        //     295904,
        //     138521,
        //     296319,
        //     113300,
        //     287596,
        //     111950,
        //     291944,
        //     291939,
        //     292122,
        //     292026,
        //     297430,
        //     294763,
        //     293398,
        //     291968,
        //     296357,
        //     286688,
        //     121232,
        //     291986,
        //     291716,
        //     299890,
        //     113048,
        //     295402,
        //     298019,
        //     95970,
        //     294821,
        //     295598,
        //     297001,
        //     287572,
        //     297292,
        //     193178,
        //     295592,
        //     287946,
        //     292887,
        //     114509,
        //     114608,
        //     300234,
        //     242742,
        //     295587,
        //     177054,
        //     296927,
        //     102528,
        //     284994,
        //     114644,
        //     294090,
        //     283727,
        //     105235,
        //     295068,
        //     296984,
        //     291695,
        //     133400,
        //     299307,
        //     298547,
        //     291915,
        //     112124,
        //     297164,
        //     291914,
        //     300073,
        //     290138,
        //     294842,
        //     297938,
        //     300045,
        //     295188,
        //     296190,
        //     295143,
        //     297514,
        //     297353,
        //     297591,
        //     217675,
        //     298481,
        //     217749,
        //     291927,
        //     298522,
        //     295394,
        //     119804,
        //     134768,
        //     298503,
        //     292027,
        //     185934,
        //     290716,
        //     292226,
        //     294156,
        //     296331,
        //     129023,
        //     298535,
        //     146427,
        //     300029,
        //     301246,
        //     301019,
        //     298314,
        //     301261,
        //     301289,
        //     296469,
        //     299292,
        //     298954,
        //     120588,
        //     282489,
        //     93274,
        //     238365,
        //     296817,
        //     295589,
        //     301765,
        //     294239,
        //     136385,
        //     300081,
        //     281347,
        //     218158,
        //     298610,
        //     291720,
        //     297955,
        //     298096,
        //     111589,
        //     225576,
        //     298398,
        //     145820,
        //     292799,
        //     298633,
        //     297957,
        //     300074,
        //     292552,
        //     297279,
        //     302392,
        //     302398,
        //     302391,
        //     301357,
        //     254735,
        //     302344,
        //     302117,
        //     298904,
        //     182068,
        //     298761,
        //     295159,
        //     288414,
        //     302409,
        //     302410,
        //     302600,
        //     302634,
        //     302637,
        //     302643,
        //     285840,
        //     286255,
        //     302386,
        //     287605,
        //     292565,
        //     302414,
        //     287739,
        //     298520,
        //     292471,
        //     300810,
        //     105949,
        //     302674,
        //     120604,
        //     302520,
        //     302626,
        //     302607,
        //     302595,
        //     302597,
        //     301995,
        //     275208,
        //     270907,
        //     300663,
        //     300935,
        //     296294,
        //     293471,
        //     300390,
        //     295719,
        //     295269,
        //     241399,
        //     299995,
        //     296248,
        //     296272,
        //     296280,
        //     296285,
        //     91951,
        //     303575,
        //     99899,
        //     267684,
        //     299906,
        //     300290,
        //     116726,
        //     291975,
        //     142867,
        //     304327,
        //     304390,
        //     204661,
        //     304720,
        //     304115,
        //     156015,
        //     297550,
        //     302646,
        //     302877,
        //     282670,
        //     302474,
        //     302694,
        //     273446,
        //     295371,
        //     101533,
        //     102313,
        //     279927
        // );

        $member_ids=array(
            112539,
            217516,
            291952,
            301850,
        );
        $where['member_id']  = array('in',$member_ids);
        $meetingList = M('Meeting')->where(['status'=>0])->where($where)->select();
       // var_dump($meetingList);die();
        foreach ($meetingList as $value){
            var_dump($value['id']);
            M()->startTrans();
            try{
                //添加账本
                $result = D('Accountbook')->addLog([
                    'member_id' => $value['pay_id'],
                    'currency_id' => $value['currency_id'],
                    'type'=> 25,
                    'content' => 'lan_user_acctend_metting_back',
                    'number_type' => 1,
                    'number' => $value['pay_number'],
                    'fee' => 0,
                    'to_member_id' => 0,
                    'to_currency_id' => 0,
                    'third_id' => $value['id'],
                    'add_time' => time(),
                ]);
                if(!$result) {
                    var_dump("操作失败1：".$value['id']);
                    throw new Exception("错误");
                }

                $flag = M('currency_user')->where(['member_id'=>$value['pay_id'],'currency_id'=>$value['currency_id']])->save([
                    'num' => ['exp','num+'.$value['pay_number']],
                    'forzen_num'=> ['exp','forzen_num-'.$value['pay_number']],
                ]);
                if(!$flag) {
                    var_dump("操作失败2：".$value['id']);
                    throw new Exception("错误");
                }
                $addNum=930;//补贴数量
                //添加补贴账本
                $resultTrave = D('Accountbook')->addLog([
                    'member_id' => $value['member_id'],
                    'currency_id' => 8,
                    'type'=> 28,
                    'content' => 'lan_travel_subsidy',
                    'number_type' => 1,
                    'number' => $addNum,
                    'fee' => 0,
                    'to_member_id' => 0,
                    'to_currency_id' => 0,
                    'third_id' => $value['id'],
                    'add_time' => time(),
                ]);
                if(!$resultTrave) {
                    var_dump("操作失败3：".$value['id']);
                    throw new Exception("错误");
                }
                $flagTrave = M('currency_user')->where(['member_id'=>$value['member_id'],'currency_id'=>8])->save([
                    'num' => ['exp','num+'.$addNum]
                ]);
                if(!$flagTrave) {
                    var_dump("操作失败4：".$value['id']);
                    throw new Exception("错误");
                }

                $flag = M('Meeting')->where(['id'=>$value['id'],'status'=>0])->setField('status',1);
                if(!$flag) {
                    var_dump("操作失败5：".$value['id']);
                    throw new Exception("错误");
                }

                M()->commit();
                var_dump("处理成功：".$value['id']);
            } catch(Exception $e) {
                var_dump("处理异常：".$value['id']);
                M()->rollback();
            }
        }
        var_dump("处理完成");

    }
}