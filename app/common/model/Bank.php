<?php
// +------------------------------------------------------
// |
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
use think\Exception;
use think\Db;

//支付方式
class Bank extends Base {
    public function banklist($lang) {
        $field = '';
        if($lang=='tc') {
            $field ='id,name';
        } else {
            $field = 'id,englishname as name';
        }
        $list = Db::name('banklist')->field($field)->select();
        if(!$list) $list = [];

        return $list;
    }

    //添加支付方式
    public function addLog($member_id,$type,$id,$pwd,$name,$cardnum,$img='',$bname=0,$inname='',$config=[]) {
        $checkPwd = model('Member')->checkMemberPwdTrade($member_id,$pwd,true);
        if(is_string($checkPwd)) return $checkPwd;

        $data = [];

        $name = Db::name('member')->where(['member_id' => $member_id])->value('name');
        if(empty($name)) return lang('lan_user_authentication_first');

        //if(empty($name)) return lang('lan_user_namenot_empty');

        if($type=='wechat'){
            if(!empty($config) && !empty($config['not_support_wechat'])) return lang('lan_pay_wechat_notallow');

            if(empty($cardnum)) return lang("lan_account_name_not_empty");
            if(empty($id) && empty($img)) return lang("lan_qrcode_not_empty");

            $data['wechat'] = $cardnum;
            if(!empty($img)) $data['wechat_pic'] = $img;
        } elseif($type=='alipay') {
            if(!empty($config) && !empty($config['not_support_alipay'])) return lang('lan_pay_alipay_notallow');

            if(empty($cardnum)) return lang("lan_account_name_not_empty");
            if(empty($id) && empty($img)) return lang("lan_qrcode_not_empty");

            $data['alipay'] = $cardnum;
            if(!empty($img)) $data['alipay_pic'] = $img;
        } elseif ($type=='bank') {
            if(empty($bname)) return lang("lan_bankname_not_empty");

            $bankInfo = Db::name('banklist')->where(['id'=>$bname])->find();
            if(!$bankInfo) return lang("lan_bankname_not_empty");

            if(empty($inname)) return lang("lan_bankadd_not_empty");
            if(empty($cardnum)) return lang("lan_bankcard_not_empty");

            if(!$this->checkBankCard($cardnum))  return lang("lan_bankcard_not_incorrect");

            $data['bankname'] = $bname;
            $data['bankadd'] = $inname;
            $data['bankcard'] = $cardnum;
        } else {
            return lang('lan_operation_failure');
        }

        $data['truename'] = $name;
        $data['member_id'] = $member_id;
        $data['add_time'] = time();

        $active = Db::name('member_'.$type)->where(['member_id'=>$member_id,'status'=>1])->find();
        if(!$active) $data['status'] = 1; //没有选中的默认选中

        if($id) {
            //OTC有訂單不能编辑
            $count = Db::name('orders_otc')->where(['member_id'=>$member_id,'status'=>['lt',2],$type=>$id])->count();
            if($count>0) return lang('lan_otc_bank_cannot_edit');

            //C2C有訂單不能编辑
            $pay_type = ['bank'=>1, 'alipay'=>2, 'wechat'=>3];
            $count = Db::name('c2c_order')->where(['member_id'=>$member_id,'status'=>0,'pay_id'=>$id,'pay_type'=>$pay_type[$type]])->count();
            if($count>0) return lang('lan_c2c_bank_cannot_edit');

            $flag = Db::name('member_'.$type)->where(['id'=>$id,'member_id'=>$member_id])->update($data);
        } else {
            $id = $flag = Db::name('member_'.$type)->insertGetId($data);
        }
        if($flag===false) return lang('lan_operation_failure');

        return ['id'=>$id];
    }

    public function changeActive($member_id,$type,$id,$config=[]) {
        if(empty($type) || !in_array($type, ['bank','alipay','wechat'])) return lang('lan_operation_failure');

        if($type=='wechat'){
            if(!empty($config) && !empty($config['not_support_wechat'])) return lang('lan_pay_wechat_notallow');
        } elseif ($type=='alipay') {
            if(!empty($config) && !empty($config['not_support_alipay'])) return lang('lan_pay_alipay_notallow');
        }

        Db::startTrans();
        try{
            $info = Db::name('member_'.$type)->lock(true)->where(['id'=>$id,'member_id'=>$member_id])->find();
            if(!$info) throw new Exception(lang('lan_network_busy_try_again'));

            $status = 0;
            if($info['status']==1) {
                $status = 0;
            } else {
                $status = 1;
            }

            //如果设置为可用,则把其他设置为非可用
            if($status==1) $flag = Db::name('member_'.$type)->where(['member_id'=>$member_id,'status'=>1])->setField('status',0);

            $flag = Db::name('member_'.$type)->where(['id'=>$id])->setField('status',$status);
            if(!$flag) throw new Exception(lang('lan_network_busy_try_again'));

            Db::commit();
            return ['flag'=>true];
        } catch(Exception $e){
            Db::rollback();
            return $e->getMessage();
        }
    }

    //删除支付方式
    public function deleteLog($member_id,$pwd,$type,$id) {
        if(empty($pwd)) return lang('lan_Incorrect_transaction_password');
        if(empty($type) || !in_array($type, ['bank','alipay','wechat'])) return lang('lan_operation_failure');

        $checkPwd = model('Member')->checkMemberPwdTrade($member_id,$pwd,true);
        if(is_string($checkPwd)) return $checkPwd;

        $model = Db::name('member_'.$type);
        $info = Db::name('member_'.$type)->where(['id'=>$id,'member_id'=>$member_id])->find();
        if(!$info) return lang('lan_operation_failure');

        //OTC有訂單不能刪除
        $count = Db::name('orders_otc')->where(['member_id'=>$member_id,'status'=>['lt',2],$type=>$id])->count();
        if($count>0) return lang('lan_otc_bank_cannot_delete');

        //C2C有訂單不能刪除
        $pay_type = ['bank'=>1, 'alipay'=>2, 'wechat'=>3];
        $count = Db::name('c2c_order')->where(['member_id'=>$member_id,'status'=>0,'pay_id'=>$id,'pay_type'=>$pay_type[$type]])->count();
        if($count>0) return lang('lan_c2c_bank_cannot_delete');

        $flag = Db::name('member_'.$type)->where(['id'=>$id])->setField('status',2);
        if(!$flag) return lang('lan_operation_failure');

        return ['flag'=>true];
    }

    //获取用户选择的支付方式
    public function getMemberChoose($member_id) {
        $return = [];
        foreach (['bank','alipay','wechat'] as $type) {
            $type_id = input($type,0,'intval');
            if(!empty($type_id)) {
                $b = Db::name('member_'.$type)->where(['id'=>$type_id,'member_id'=>$member_id])->find();
                if($b) $return[$type] = $type_id;
            }
        }
        if(empty($return)) return lang('lan_please_select_payment_method');

        return $return;
    }

    public function getInfoByType($bank_id,$type,$lang='tc') {
        if(!in_array($type, ['bank','wechat','alipay'])) return lang('lan_operation_failure');

        $field = $this->getField($type,$lang);

        $model = Db::name('member_'.$type)->alias('b1')->field($field)->where(['b1.id'=>$bank_id]);
        if($type=='bank') $model = $model->join('__BANKLIST__ b2','b2.id = b1.bankname','LEFT');

        $info = $model->find();
        if(!$info) return [];

        $info['bankname'] = $type;
        return $info;
    }

    public function getList($member_id,$lang,$flag=false){
        $where = ['b1.member_id'=>$member_id];
        if($flag) {
            $where['b1.status'] = 1;
        } else {
            $where['b1.status'] = ['lt',2];
        }

        $return = [];
        foreach (['bank','wechat','alipay'] as $type) {
            $field = $this->getField($type,$lang);

            $model = Db::name('member_'.$type)->alias('b1');
            if($type=='bank') $model = $model->join('__BANKLIST__ b2','b2.id = b1.bankname','left');
            $model = $model->field($field)->where($where);

            if($flag) {
                $info = $model->find();
                if($info) $return[$type] = $info;
            } else {
                $info = $model->select();
                $return[$type] = $info ?: [];
            }

        }
        return $return;
    }

    private function getField($type,$lang='tc') {
        if($type=='bank') {
            $bname = '';
            if($lang=='tc') {
                $bname = 'name';
            } else {
                $bname = 'englishname';
            }
            $field = 'b1.id,b1.bankadd as inname,bankcard as cardnum,b1.status,b1.truename,b2.'.$bname.' as bname,b2.id as bank_id';
        }elseif ($type=='wechat') {
            $field = 'b1.id,b1.wechat as cardnum,b1.wechat_pic as img,b1.status,b1.truename';
        } elseif ($type=='alipay') {
            $field = 'b1.id,b1.alipay as cardnum,b1.alipay_pic as img,b1.status,b1.truename';
        }

        return $field;
    }

    private function checkBankCard($bankcard) {
        return true;

        if(strlen($bankcard) < 16 || strlen($bankcard) > 19){
            return false;
        }
        $arr_no = str_split($bankcard);
        $last_n = $arr_no[count($arr_no) - 1];
        krsort($arr_no);
        $i = 1;
        $total = 0;
        foreach ($arr_no as $n) {
            if ($i % 2 == 0) {
                $ix = $n * 2;
                if ($ix >= 10) {
                    $nx = 1 + ($ix % 10);
                    $total += $nx;
                } else {
                    $total += $ix;
                }
            } else {
                $total += $n;
            }
            $i++;
        }
        $total -= $last_n;
        $x = 10 - ($total % 10);
        if ($x == $last_n) {
            return true;
        } else {
            return false;
        }
    }
}
