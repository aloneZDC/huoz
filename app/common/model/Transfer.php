<?php
// +------------------------------------------------------
// | Author: 黄树标 <18899716854@qq.com>
// +------------------------------------------------------
namespace app\common\model;
use think\Model;
use think\Exception;
use think\Db;

class Transfer extends Base {
    protected $multiple=6;//默认入金额的倍数
    protected $transfer_title="lan_transfer_title";//说明文
    protected $type_explain="lan_type_explain";//说明文
    protected $fee=0;//手续费
    //获取币信息@标
    public function currency($type='XRP',$field='*'){
        $info=Db::name('currency')->field($field)->where(['currency_name'=>$type])->find();
        return empty($info)?0:$info;
    }
    //用户瑞波币金额@标
    public function currency_info($member_id){
        if($member_id>0){
            $info=Db::name('boss_plan_info')->field('xrpz_num')->where(['member_id'=>$member_id])->find();
        }
        $content=lang("lan_transfer_content2")."\n".lang("lan_transfer_content3")."\n".lang("lan_transfer_content4")."\n";
        $r['result']['xrp_num']=empty($info)?0:strval(floatval($info['xrpz_num']));
        $r['result']['content']=$content;
        $r['code'] = SUCCESS;
        $r['message'] = lang("lan_data_success");
        return $r;

    }
    //验证是否是老板计划用户
    public function member_plan_info($member_id){
        $info=Db::name('boss_plan_info')->field('member_id,pid')->where(['member_id'=>$member_id])->find();
        if(empty($info)){
            return false;
        }
        return $info;
    }
    public function currency_xrp($member_id){
        $r['message'] = lang("lan_modifymember_parameter_error");
        $currency=$this->currency();
        $info1=Db::name('currency_user')->field('num')->where(['member_id'=>$member_id,'currency_id'=>$currency['currency_id']])->find();

        $currency_gac=$this->currency('GAC','currency_id');
        $info_gac =Db::name('currency_user')->field('lock_num,internal_buy,remaining_principal')->where(['member_id'=>$member_id,'currency_id'=>$currency_gac['currency_id']])->find();

        $info2=Db::name('boss_plan_info')->field('xrpz_num,xrpj_num')->where(['member_id'=>$member_id])->find();
        $fee=Db::name('boss_config')->field('value,key')->where(['key'=>['in','xrp_fee,xrpj_fee,wallet_fee,gac_lock_fee,gac_internal_buy_fee,gac_xrp_exchange_gac_fee']])->select();
        if(empty($fee)){
            $r['result']['xrp_fee']='0';
            $r['result']['xrpj_fee']='0';
            $r['result']['wallet_fee']='0';
            $r['result']['gac_lock_fee']='0';
            $r['result']['gac_internal_buy_fee'] ='0';
            $r['result']['gac_xrp_exchange_gac_fee'] ='0';
        }else{
            foreach ($fee as $key =>$val){
                if($val['key']=='xrp_fee'){
                    $r['result']['xrp_fee']=strval(($val['value']*100));
                }elseif ($val['key']=='xrpj_fee'){
                    $r['result']['xrpj_fee']=strval(($val['value']*100));
                }elseif($val['key']=='wallet_fee'){
                    $r['result']['wallet_fee']=strval(($val['value']*100));
                }elseif($val['key']=='gac_lock_fee'){
                    $r['result']['gac_lock_fee']=strval(($val['value']*100));
                }elseif($val['key']=='gac_internal_buy_fee'){
                    $r['result']['gac_internal_buy_fee']=strval(($val['value']*100));
                }elseif($val['key']=='gac_xrp_exchange_gac_fee'){
                    $r['result']['gac_xrp_exchange_gac_fee']=strval(($val['value']*100));
                }
            }
        }
        $r['result']['num1']=empty($info1['num'])?'0':keepPoint($info1['num'],6);
          //  empty($info1['num'])?'0':strval(floatval($info1['num']));
        $r['result']['num2']=empty($info2['xrpz_num'])?'0':keepPoint($info2['xrpz_num'],6);
        $r['result']['num3']=empty($info2['xrpj_num'])?'0':keepPoint($info2['xrpj_num'],6);
        $r['result']['num4']=empty($info_gac['lock_num'])?'0':keepPoint($info_gac['lock_num'],6);
        $r['result']['num6']=empty($info_gac['internal_buy'])?'0':keepPoint($info_gac['internal_buy'],6);
        $r['result']['num7']=empty($info_gac['remaining_principal'])?'0':keepPoint($info_gac['remaining_principal'],6);
        $r['result']['num7_name']= lang('lan_gac_welfare_transfer_name');
        $r['result']['num4_name'] = lang('lan_gac_transfer_name');
        $r['result']['num6_name']= lang('lan_internal_buy_transfer_name');
        //  empty($info1['num'])?'0':strval(floatval($info1['num']));
        $r['code'] = SUCCESS;
        $r['message'] = lang("lan_data_success");
        return  $r;
    }
    //入金额的倍数限制@标
   public function money_limit($member_id){

      if($member_id>0){
          //获取用户入金量
          $info=Db::name('boss_plan_info')->field('num,xrpz_num')->where(['member_id'=>$member_id])->find();

          //获取xrp币信息
          $currency=$this->currency('XRP','currency_id');

          //获取划转支出xrp总数量
          $log_num=Db::name('accountbook')->where(['member_id'=>$member_id,'currency_id'=>$currency['currency_id'],'number_type'=>1,'type'=>22])->sum('number');

          //获取可划转余额
          $remain_num=($info['num']*$this->multiple)-$log_num;
      }
       $fee=Db::name('boss_config')->field('value,key')->where(['key'=>['in','transfer_fee']])->find();
      if(empty($fee)){
          $r['result']['transfer_fee']="0";
      }else{
          $r['result']['transfer_fee']=strval(($fee['value']*100));
      }
       $content=lang("lan_transfer_content1");
      // $r['result']['xrp_num']=empty($info)?'0':strval(floatval($remain_num));
       $r['result']['xrp_num']="";
       $r['result']['xrpz_num']=empty($info)?'0':keepPoint($info['xrpz_num'],6);
      // empty($info)?'0':strval(floatval($info['xrpz_num']));
      //$r['result']['content']=$content;
       $r['result']['content']="";
      $r['code'] = SUCCESS;
      $r['message'] = lang("lan_data_success");
      return $r;
   }
    //判断小数的位数@标
    public function getFloatLength($num) {
        $count = 0;

        $temp = explode ( '.', $num );
        if (sizeof ( $temp ) > 1) {
            $decimal = end ( $temp );
            $count=strlen($decimal);
        }
        return $count;
    }
   //用户瑞波币划转功能@标
   public function payment_xrp($member_id,$pwd,$num,$phone_code){
       $r['result']=[];
       $r['code'] = ERROR1;
       $r['message'] = lang("lan_modifymember_parameter_error");
       $model_boss_plan_info=Db::name('boss_plan_info');
       $date=time();
       //手机验证码
//       $senderLog = model('Sender')->auto_check($member_id,'transfer',$phone_code,false);
//       if(is_string($senderLog)) {
//           $r['message'] = $senderLog;
//           return $r;
//       }
       //暂停交易校验
       $config=Db::name('boss_config')->field('value')->where(['key'=>'ransfer_switch'])->find();
       if($config['value']==2){
           $r['message'] = lang("lan_transaction_pause");
           return $r;
       }

       //判断输入金额不能超出3位小数
       if($this->getFloatLength($num)>3){
           $r['message'] = lang("lan_decimal_limit");
           return $r;
       }
       //用户是否锁定校验
       $boss_plan = Db::name('boss_plan')->field('lock_status')->where(['member_id'=>$member_id])->find();
       if($boss_plan['lock_status']==2){
           $r['message'] = lang("lan_lock_user");
           return $r;
       }
       $info=$model_boss_plan_info->field('num,xrpz_num')->where(['member_id'=>$member_id])->find();
       //验证数量格式
       if(!is_numeric($num)||$num<=0){
        $r['message'] = lang("lan_num_no_format");
        return $r;
       }
       //获取xrp币信息
       $currency=$this->currency('XRP','currency_id');
       //获取划转支出xrp总数量
        $log_num=Db::name('accountbook')->where(['member_id'=>$member_id,'currency_id'=>$currency['currency_id'],'number_type'=>2,'type'=>22])->sum('number');
       //获取可划转余额
//       $remain_num=($info['num']*$this->multiple)-$log_num;
//       if($remain_num<$num){
//           $r['message'] = lang("lan_trade_underbalance");
//           return $r;
//       }
       //手续费
       $config_fee=Db::name('boss_config')->field('value')->where(['key'=>'transfer_fee'])->find();
       $feeNum = ($config_fee['value'] * $num);
       //用户余额验证
       if(empty($info)||$info['xrpz_num']<($num+$feeNum)){
           $r['code'] = ERROR2;
           $r['message'] = lang("lan_trade_underbalance");
           return $r;
       }

       //支付密码验证
       $paypwd=Member::verifyPaypwd($member_id,$pwd);
       if($paypwd['code']!=SUCCESS){
           $r['code'] = ERROR3;
           $r['message'] = $paypwd['message'];
           return $r;
       }

       Db::startTrans();
       try{

           //添加日志
            $data =  ['l_member_id'=>$member_id,'l_value'=>-($num+$feeNum),'l_time'=>$date,'l_title'=>$this->transfer_title,'l_type'=>9,'l_type_explain'=>$this->type_explain,'l_current_num'=>$info['xrpz_num'],'l_change_num'=>$info['xrpz_num']-($num+$feeNum),'l_transfer_fee'=>$feeNum];
           //支出日志
           $insert_xrp_log = Db::name('xrp_log')->insertGetId($data);
           if(!$insert_xrp_log){
               $r['code'] = ERROR7;
               throw new Exception(lang('lan_transfer_error'));
           }
           $insert_data=['member_id'=>$member_id,'currency_id'=>$currency['currency_id'],'number_type'=>1,'number'=>$num,'type'=>22,'content'=>$this->transfer_title,'to_member_id'=>$member_id,'to_currency_id'=>$currency['currency_id'],'third_id'=>$insert_xrp_log];
           //添加划转日志
           $model_account_book=new AccountBook();
           //划转收入
           $data2=$model_account_book->addLog($insert_data);
           if(!$data2){
               $r['code'] = ERROR9;
               throw new Exception(lang('lan_transfer_error'));
           }
           //减瑞波币金额
           $update_xrpz_num=$model_boss_plan_info->where(['member_id'=>$member_id])->setDec('xrpz_num',($num+$feeNum));
           if(!$update_xrpz_num){
               $r['code'] = ERROR4;
               throw new Exception(lang('lan_transfer_error'));
           }
           $update_currency_user=Db::name('currency_user')->where(['member_id'=>$member_id,'currency_id'=>$currency['currency_id']])->setInc('num',$num);
           if(!$update_currency_user){
               $r['code'] = ERROR5;
               throw new Exception(lang('lan_transfer_error'));
           }
           $r['code'] = SUCCESS;
           $r['message']= lang("lan_transfer_succ");
           Db::commit();
       } catch (Exception $e) {
           Db::rollback();
           $r['message']=$e->getMessage();
       }
//       model('Sender')->hasUsed($senderLog['id']);
       return $r;
   }
   //用户转账@标
   public function transfer_accounts($type=1,$account="",$member_id=0,$to_member_id=0,$num=0,$pwd="",$phone_code){

       $r['result']=[];
       $r['code'] = ERROR1;
       $r['message'] = lang("lan_modifymember_parameter_error");
       $date=time();
       //手机验证码
       $senderLog = model('Sender')->auto_check($member_id,'transfer',$phone_code,false);
       if(is_string($senderLog)) {
           $r['message'] = $senderLog;
           return $r;
       }
       //暂停交易校验
       $config=Db::name('boss_config')->field('value')->where(['key'=>'mutual_turn_switch'])->find();
       if($config['value']==2){
           $r['message'] = lang("lan_transaction_pause");
           return $r;
       }
       //判断输入金额不能超出3位小数
       if($this->getFloatLength($num)>3){
           $r['message'] = lang("lan_decimal_limit");
           return $r;
       }
       //用户是否锁定校验
       $boss_plan = Db::name('boss_plan')->field('lock_status')->where(['member_id'=>$member_id])->find();
       if($boss_plan['lock_status']==2){
           $r['message'] = lang("lan_lock_user");
           return $r;
       }
       if(!in_array($type,[1,2,3])){
           return $r;
       }
       if(empty(trim($account))){
           $r['code'] = ERROR2;
           $r['message'] = lang("lan_Account_does_not_exist");
           return $r;
       }
       //用户信息
       $member = Db::name('member')->field('member_id')->where(['phone|email'=>$account,'member_id'=>$member_id])->find();
       if(!empty($member)){
           $r['code'] = ERROR3;
           $r['message'] = lang("lan_can_not_transfer_yourself");
           return $r;
       }
       //接收方用户信息
       $to_member = Db::name('member')->field('member_id')->where(['phone|email'=>$account,'member_id'=>$to_member_id])->find();
       if(empty($to_member)){
           $r['code'] = ERROR4;
           $r['message'] = lang("lan_account_member_not_exist");
           return $r;
       }
       if(!is_numeric($num)||$num<=0){
           $r['message'] = lang("lan_num_no_format");
           return $r;
       }
       //支付密码验证
       $paypwd=Member::verifyPaypwd($member_id,$pwd);
       if($paypwd['code']!=SUCCESS){
           $r['code'] = ERROR5;
           $r['message'] = $paypwd['message'];
           return $r;
       }
       $currency=$this->currency('XRP','currency_id');
       Db::startTrans();
       try{
           if($type==1){//xrp钱包对xrp钱包(yang_currency_user)


               $currency_user = db('currency_user');
               CurrencyUser::getCurrencyUser($to_member_id,$currency['currency_id']);//验证是否有对方有资产信息，没有就创建
               $currency_user_num = $currency_user->field('num')->where(['member_id'=>$member_id,'currency_id'=>$currency['currency_id']])->find();
               //手续费
               $config_fee=Db::name('boss_config')->field('value')->where(['key'=>'wallet_fee'])->find();
               $feeNum = ($config_fee['value'] * $num);
               if($currency_user_num['num'] < ($num+$feeNum)){//余额不足
                   $r['code'] = ERROR6;
                   $r['message'] = lang("lan_your_credit_is_running_low") ;
                   return $r;
               }
//               //添加日志
//               $data1 =  ['l_member_id'=>$member_id,'l_value'=>-$num,'l_time'=>$date,'l_title'=>$to_member_id,'l_type'=>13,'l_type_explain'=>'lan_xrp_currency_user'];
//               //支出xrp日志
//               $insert_xrp_log1 = Db::name('xrp_log')->insertGetId($data1);
//               if(!$insert_xrp_log1){
//                   $r['code'] = ERROR2;
//                   throw new Exception(lang('lan_transfer_error'));
//               }
               //支出账户日志
               $model_account_book=new AccountBook();
               $insert_data1=['member_id'=>$member_id,'currency_id'=>$currency['currency_id'],'number_type'=>2,'number'=>($num+$feeNum),'type'=>18,'content'=>$to_member_id,'to_member_id'=>$to_member_id,'fee'=>$feeNum];
               $data1=$model_account_book->addLog($insert_data1);
               if(!$data1){
                   $r['code'] = ERROR7;
                   throw new Exception(lang('lan_transfer_error'));
               }
//               //添加日志
//               $data2 =  ['l_member_id'=>$to_member_id,'l_value'=>$num,'l_time'=>$date,'l_title'=>$member_id,'l_type'=>13,'l_type_explain'=>'lan_xrp_currency_user'];
//               //收入xrp日志
//               $insert_xrp_log2 = Db::name('xrp_log')->insertGetId($data2);
//               if(!$insert_xrp_log2){
//                   $r['code'] = ERROR3;
//                   throw new Exception(lang('lan_transfer_error'));
//               }
               //收入对方账户日志
               $insert_data2=['member_id'=>$to_member_id,'currency_id'=>$currency['currency_id'],'number_type'=>1,'number'=>$num,'type'=>18,'content'=>$member_id,'to_member_id'=>$member_id];
               $data2=$model_account_book->addLog($insert_data2);
               if(!$data2){
                   $r['code'] = ERROR8;
                   throw new Exception(lang('lan_transfer_error'));
               }
               //扣除用户瑞波钻(钱包)
               $operation1 = $currency_user->where(['member_id'=>$member_id,'currency_id'=>$currency['currency_id']])->setDec('num',($num+$feeNum));

               if(empty($operation1)){
                   $r['code'] = ERROR9;
                   throw new Exception(lang('lan_trade_the_operation_failurer'));
               }

               //添加对方用户瑞波钻(钱包)
               $operation2 = $currency_user->where(['member_id'=>$to_member_id,'currency_id'=>$currency['currency_id']])->setInc('num',$num);
               if(empty($operation1) || empty($operation2)){
                   $r['code'] = ERROR10;
                   throw new Exception(lang('lan_trade_the_operation_failurer'));
               }
           }elseif($type==2){//xrp+对xrp+(yang_boss_plan_info)
               $model_boss_plan_info=Db::name('boss_plan_info');
               $info=$model_boss_plan_info->field('xrpz_num')->where(['member_id'=>$member_id])->find();
               $to_info=$model_boss_plan_info->field('xrpz_num')->where(['member_id'=>$to_member_id])->find();
               //手续费
               $config_fee=Db::name('boss_config')->field('value')->where(['key'=>'xrp_fee'])->find();
               $feeNum = ($config_fee['value'] * $num);
               if($info['xrpz_num'] < ($num+$feeNum)){//余额不足
                   $r['code'] = ERROR6;
                   $r['message'] = lang("lan_your_credit_is_running_low") ;
                   return $r;
               }
               $xrp_data1 =  ['l_member_id'=>$member_id,'l_value'=>-($num+$feeNum),'l_time'=>$date,'l_title'=>$to_member_id,'l_type'=>10,'l_type_explain'=>'lan_in_platform_transfer','l_current_num'=>$info['xrpz_num'],'l_change_num'=>$info['xrpz_num']-($num+$feeNum),'l_xrp_fee'=>$feeNum];
               //支出瑞波钻日志
               $insert_xrp_log1 = Db::name('xrp_log')->insert($xrp_data1);
               if(!$insert_xrp_log1){
                   $r['code'] = ERROR9;
                   throw new Exception(lang('lan_transfer_error'));
               }
               if(!$this->member_plan_info($to_member_id)){
                   $r['code'] = ERROR10;
                   throw new Exception(lang('lan_no_boss_member'));
               }
               $data2=$model_boss_plan_info->where(['member_id'=>$to_member_id])->setInc('xrpz_num',($num));
               if(!$data2){
                   $r['code'] = ERROR11;
                   throw new Exception(lang('lan_trade_the_operation_failurer'));
               }
               $xrp_data2 =  ['l_member_id'=>$to_member_id,'l_value'=>$num,'l_time'=>$date,'l_title'=>$member_id,'l_type'=>10,'l_type_explain'=>'lan_in_platform_transfer','l_current_num'=>$to_info['xrpz_num'],'l_change_num'=>$to_info['xrpz_num']+$num];
               //收入瑞波钻日志
               $insert_xrp_log2 = Db::name('xrp_log')->insert($xrp_data2);
               if(!$insert_xrp_log2){
                   $r['code'] = ERROR11;
                   throw new Exception(lang('lan_transfer_error'));
               }
               //添加日志
               $data1=$model_boss_plan_info->where(['member_id'=>$member_id])->setDec('xrpz_num',($num+$feeNum));
               if(!$this->member_plan_info($member_id)){
                   $r['code'] = ERROR7;
                   throw new Exception(lang('lan_no_boss_member'));
               }
               if(!$data1){
                   $r['code'] = ERROR8;
                   throw new Exception(lang('lan_trade_the_operation_failurer'));
               }
           }elseif($type==3){//xrpj+对xrpj+(yang_boss_plan_info)
               $model_boss_plan_info=Db::name('boss_plan_info');
               if(!$this->member_plan_info($to_member_id)){
                   $r['code'] = ERROR10;
                   throw new Exception(lang('lan_no_boss_community_member'));
               }
               if(!$this->member_plan_info($member_id)){
                   $r['code'] = ERROR7;
                   throw new Exception(lang('lan_no_boss_community_member'));
               }
               //校验是否该用户社员
               if(($member_id!=$this->member_plan_info($to_member_id)['pid'])&&($to_member_id!=$this->member_plan_info($member_id)['pid'])) {
                   $is_parent1 = Db::name('member_bind')->where(['member_id'=>$member_id,'child_id'=>$to_member_id])->find();
                   $is_parent2 = Db::name('member_bind')->where(['member_id'=>$to_member_id,'child_id'=>$member_id])->find();
                   if(!$is_parent1 && !$is_parent2) {
                       $r['code'] = ERROR10;
                       throw new Exception(lang('lan_no_boss_community_member'));
                   };
               }
               //手续费
               $config_fee=Db::name('boss_config')->field('value')->where(['key'=>'xrpj_fee'])->find();
               $feeNum = ($config_fee['value'] * $num);
               $info=$model_boss_plan_info->field('xrpj_num')->where(['member_id'=>$member_id])->find();
               $to_info=$model_boss_plan_info->field('xrpj_num')->where(['member_id'=>$to_member_id])->find();
               if($info['xrpj_num'] < ($num+$feeNum)){//余额不足
                   $r['code'] = ERROR6;
                   $r['message'] = lang("lan_your_credit_is_running_low") ;
                   return $r;
               }
               $xrp_data1 =  ['l_member_id'=>$member_id,'l_value'=>-($num+$feeNum),'l_time'=>$date,'l_title'=>$to_member_id,'l_type'=>10,'l_type_explain'=>'lan_in_platform_transfer','l_current_num'=>$info['xrpj_num'],'l_change_num'=>$info['xrpj_num']-($num+$feeNum),'l_xrpj_fee'=>$feeNum];
               //支出瑞波金日志
               $insert_xrpj_log1 = Db::name('xrpj_log')->insert($xrp_data1);
               if(!$insert_xrpj_log1){
                   $r['code'] = ERROR9;
                   throw new Exception(lang('lan_transfer_error'));
               }

               $xrp_data2 =  ['l_member_id'=>$to_member_id,'l_value'=>$num,'l_time'=>$date,'l_title'=>$member_id,'l_type'=>10,'l_type_explain'=>'lan_in_platform_transfer','l_current_num'=>$to_info['xrpj_num'],'l_change_num'=>$to_info['xrpj_num']+$num];
               //收入瑞波金日志
               $insert_xrpj_log2 = Db::name('xrpj_log')->insert($xrp_data2);
               if(!$insert_xrpj_log2){
                   $r['code'] = ERROR11;
                   throw new Exception(lang('lan_transfer_error'));
               }


               $data1=$model_boss_plan_info->where(['member_id'=>$member_id])->setDec('xrpj_num',($num+$feeNum));
               if(!$data1){
                   $r['code'] = ERROR8;
                   throw new Exception(lang('lan_trade_the_operation_failurer'));
               }
               $data2=$model_boss_plan_info->where(['member_id'=>$to_member_id])->setInc('xrpj_num',$num);
               if(!$data2){
                   $r['code'] = ERROR11;
                   throw new Exception(lang('lan_trade_the_operation_failurer'));
               }
           }

           $r['code'] = SUCCESS;
           $r['message']= lang("lan_operation_success");
           Db::commit();
       } catch (Exception $e) {
           Db::rollback();
           $r['message']=$e->getMessage();
       }
      //设置验证码为已用
       model('Sender')->hasUsed($senderLog['id']);
       return $r;

   }



   //xrp 明细@标
   public function detail_xrp($type='all',$member_id,$page=1,$page_size=10,&$count=false){
       $r['message'] = lang("lan_not_data");
       $where['l_member_id']=$member_id;
       if($type==1){
           $where['l_value']=array('gt',0);
       }elseif($type==2){
           $where['l_value']=array('lt',0);
       }
       $log=$this->detail('xrp_log',"l_title,l_value,l_time,l_type_explain,l_type,l_votes,l_transfer_fee,l_xrp_fee",$where,$page,$page_size,$count);
       if(!empty($log)){
           $r['message'] = lang("lan_data_success");
       }
       $r['result']=empty($log)?[]:$log;
       $r['code'] = SUCCESS;
       return $r;
   }
    //创新区 明细@标
   public function detail_new($member_id,$page=1,$page_size=10,&$count=false){
           $r['message'] = lang("lan_not_data");
         $where['l_member_id']=$member_id;
         $log=$this->detail('innovate_log',"l_title,l_value,l_time,l_type_explain,l_type",$where,$page,$page_size,$count);

         $plan=db('boss_plan_info')->field('xrpz_new_num')->where(['member_id'=>$member_id])->find();
         $r['result']['log']=empty($log)?[]:$log;
         $r['result']['new_num']=empty($plan)?'0':keepPoint($plan['xrpz_new_num'],6);//strval(floatval($plan['xrpz_new_num']))
           if(!empty($log)||$plan){
               $r['message'] = lang("lan_data_success");
           }
         $r['code'] = SUCCESS;
       return $r;
   }
    //瑞波金 明细@标
    public function detail_xrpj($member_id,$page=1,$page_size=10,&$count=false){
        $r['message'] = lang("lan_not_data");
        $where['l_member_id']=$member_id;
        $log=$this->detail('xrpj_log',"l_title,l_value,l_time,l_type_explain,l_type,l_votes,l_xrpj_fee as l_xrp_fee",$where,$page,$page_size,$count);
        $plan=db('boss_plan_info')->field('xrpj_num')->where(['member_id'=>$member_id])->find();
        $r['result']['log']=empty($log)?[]:$log;
        $r['result']['xrpj_num']=empty($plan)?'0':keepPoint($plan['xrpj_num'],6);//strval(floatval($plan['xrpj_num']))
        if(!empty($log)||$plan){
            $r['message'] = lang("lan_data_success");
        }
        $r['code'] = SUCCESS;
        return $r;
    }
   //公用详情@标
   public function detail($data_name,$field="*",$where,$page,$page_size,&$count=false){

       $model_member=new Member();
       if($count){
           $count=Db::name($data_name)->field($field)->where($where)->count();
       }
        $log=Db::name($data_name)->field($field)->where($where)->limit(($page - 1) * $page_size, $page_size)->order('l_time desc')->select();

        if($log){
            foreach ($log as $key=>$val){
                $log[$key]['l_title']=lang($val['l_title']);
                if($val['l_type']==10){
                    $nick_info=$model_member->member_info($val['l_title']);
                    $nick=empty($nick_info)?"******":$nick_info['nick'];
                    if($val['l_value']>0){
                        $log[$key]['l_title']=lang('lan_mutual_transfer2').$nick.lang('lan_mutual_transfer3');
                    }elseif($val['l_value']<0){
                        $log[$key]['l_title']=lang('lan_mutual_transfer1').$nick;
                    }

                }
                if($val['l_type']==11){
                    $log[$key]['l_title']=$val['l_votes'].lang('lan_accountbook_boss_plan_ticket');
                }
                if($val['l_type']==12){
                    $nick_info=$model_member->member_info($val['l_title']);
                    $nick=empty($nick_info)?"******":$nick_info['nick'];
                    $log[$key]['l_title']=lang('lan_accountbook_boss_plan_wei').$nick.lang('lan_accountbook_boss_plan_active').$val['l_votes'].lang('lan_accountbook_boss_plan_ticket');
                }
                $log[$key]['l_type_explain']=lang($val['l_type_explain']);
                if($val['l_type']==20) {
                  $type_explain = explode(":", $log[$key]['l_type_explain']);
                  if(count($type_explain)==2) $log[$key]['l_type_explain']=lang($type_explain[0]).$type_explain[1];
                }
                $log[$key]['l_time']=date('Y-m-d H:i:s',$val['l_time']);
                $log[$key]['l_state']=$val['l_value']>0?1:2;
                $log[$key]['l_value']=$val['l_value']>0?'+'.keepPoint($val['l_value'],6).'xrp':keepPoint($val['l_value'],6).'xrp';
                unset($log[$key]['l_type']);

            }
            $r['message'] = lang("lan_data_success");
        }
        return $log;
    }

}