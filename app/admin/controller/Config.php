<?php
namespace app\admin\controller;
use think\Db;
use Think\Page;
class Config extends Admin {
	public function _initialize(){
		parent::_initialize();
	}
	//空操作
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		$this->display('Public:404');
	}
	
	public function index(){
		$list=Db::name("Config")->select();
		foreach ($list as $k=>$v){
               $list[$v['key']]=$v['value'];
				
		}
		$this->assign('config',$list);
       return $this->fetch();
     }
     
     public function customerService(){
     	$this->display();
     }
     
     public function shortMessage(){
     	return $this->fetch();
     }

     public function payment()
     {
         $this->display();
     }
    public function indexConfig(){
        $this->display();
    }
    public function atConfig(){
        $this->display();
    }
     public function finance(){
         $where['id']= 78;
         
         $list=M('Config')->where($where)->find();
         
         $coin_currency=$list['value'];
         $where['currency_id']= $coin_currency;
         
         $coin_name=M('currency')->where($where)->find();
         $coin_name2=$coin_name['currency_name'];
         //获取积分类型
         $currency = M('Currency')->field('currency_id,currency_name')->select();
         $this->assign('currency',$currency);
         $this->assign('coin_name',$coin_name2);
     	$this->display();
     }
     public function information(){
     	$this->display();
     }
     
     
     public function websiteBank(){
     	$this->display();
     }
     
     public function updateCofig(){
         if(isset($_FILES["logo"]["tmp_name"])&&$_FILES["logo"]["tmp_name"]){
                $_POST['logo']=$this->upload($_FILES["logo"]);
                if (!$_POST['logo']){
                    $this->error('非法上传');
                }
         }
         if(isset($_FILES["weixin"]["tmp_name"])&&$_FILES["weixin"]["tmp_name"]){
              $_POST['weixin']=$this->upload($_FILES["weixin"]);
              if (!$_POST['weixin']){
                  $this->error('非法上传');
              }
         }
     /*	$_POST['friendship_tips'] = I('post.friendship_tips','','html_entity_decode');
     	$_POST['withdraw_warning'] = I('post.withdraw_warning','','html_entity_decode');
     	$_POST['risk_warning'] = I('post.risk_warning','','html_entity_decode');
     	$_POST['VAP_rule'] = I('post.VAP_rule','','html_entity_decode');
     	$_POST['disclaimer'] = I('post.disclaimer','','html_entity_decode');
     	$_POST['FWTK'] = I('post.FWTK','','html_entity_decode');*/
         $rs=null;
     	foreach ($_POST as $k=>$v){
     		$rs[]=Db::name("Config")->where(['key'=>$k])->update(['value'=>$v]);
     	}
     	if($rs){
     		return $this->success('配置修改成功');
     	}else{
     		return $this->error('配置修改失败');
     	}
     }
     public  function atmine(){
         $conut =M('census')->where(['type'=>1])->count();
	    $page = new Page($conut,10);
         $show = $page->show();
	    $list = M('census')->where(['type'=>1])->order(" datetime desc" )->limit($page->firstRow.",".$page->listRows)->select();
	    $this->assign('page',$show);
	    $this->daysnum($list,1);
	    $this->assign('list',$list);
	    $this->assign('startnum',$page->firstRow);
        $this->display();
     }
     public function daysnum(&$list ,$type){
         foreach ($list as $key => $value) {
             if( $value['daysnum'] == null){
                 $start_time = strtotime(date("Y-m-d 00:00:00", $value['datetime']));
                 $end_time = $start_time + 3600 * 24;
                 $w['add_time'] = ["between", [$start_time, $end_time]];
                 $w['column'] = $type;
                 $total_num = M('mining_bonus')->where($w)->sum('num');
                 $total_num = $total_num ?: 0;
                 $list[$key]['daysnum'] = number_format($total_num,6);
                 M('census')->where(['id' => $value['id']])->save(['daysnum' => $total_num]);
             }
         }
     }
     public  function atbonus(){
         $conut =M('census')->where(['type'=>2])->count();
         $page = new Page($conut,10);
         $show = $page->show();
         $list = M('census')->where(['type'=>2])->order(" datetime desc" )->limit($page->firstRow.",".$page->listRows)->select();
         $this->assign('page',$show);
         $this->assign('list',$list);
         $this->daysnum($list ,2);
         $this->assign('startnum',$page->firstRow);
         $this->display();
     }
     public function atdetailed()
     {
         $type = I('type');
         $tm = I('tm');
         $kes = I('kes');
         $s['tm'] =$tm;
         $s['type'] =$type;
         $s['kes'] =$kes;
         $this->assign('s',$s);
         if($tm){
             $start_time = strtotime($tm.'00:00:00');
             $end_time = $start_time+3600*24;
             $w['yang_mining_bonus.add_time'] = ["between", [$start_time, $end_time]];
         }
         $w['yang_mining_bonus.column'] = $type;
         if($kes){
             $w['u.phone|u.name'] = ['like',"%{$kes}%"];
         }
         $conut =  M('mining_bonus')->join(" left join yang_member as u on u.member_id = yang_mining_bonus.member_id")->where($w)->order(" yang_mining_bonus.add_time desc")->count();
         $page = new Page($conut, 10);
         $show = $page->show();
         $fiele = "yang_mining_bonus.*,u.phone,u.name";
         $list = M('mining_bonus')->join(" left join yang_member as u on u.member_id = yang_mining_bonus.member_id")->where($w)->field($fiele)->order(" yang_mining_bonus.add_time desc")->limit($page->firstRow . "," . $page->listRows)->select();
         $this->assign('page', $show);
         $this->assign('list', $list);
         $this->assign('startnum', $page->firstRow);
         $this->display();
     }


     public  function  sendsms(){
        $sendSMS = Db::name("send_system")->select();
        $this->assign('sendSMS',$sendSMS);
        return $this->fetch();
     }
     public  function  addsendsms(){
         $s_id=  trim(input('s_id'));
         $SmsInfo=null;
         if(!empty($s_id)){
          $SmsInfo =  Db::name("send_system")->where(['s_id'=>$s_id])->find();
          $SmsInfo || (  $this->success('数据不存在',url('Config/sendsms')));
         }
         if(!empty($_POST)){
//             $data['title'] =  trim(input('title'));
             $data['s_name'] =  trim(input('s_name'));
             $data['s_status'] =  trim(input('s_status'));
             $data['appid'] =  trim(input('appid'));
             $data['account'] =  trim(input('account'));
             $data['token'] =  trim(input('token'));
             $data['uc_keyword'] =  trim(input('uc_keyword'));
             if($s_id){
                $return =  Db::name("send_system")->where(['s_id'=>$s_id])->update($data);
                 $msg = '更新成功';
             }else{
                $return =   Db::name("send_system")->insertGetId($data);
                 $msg = '保存成功';
             }
             if($return){
                 return $this->success($msg,url('Config/sendsms'));
             }
         }
         $this->assign('SmsInfo',$SmsInfo);
         return $this->fetch();
     }

    /**
     * 删除一条短信配置
     * Create by: Red
     * Date: 2019/8/20 15:50
     */
     public  function  delsendSMS(){
         $s_id=  trim(input('s_id'));
         if(!empty($s_id)){
             $SmsInfo = Db::name("send_system")->where(['s_id'=>$s_id])->find();
             $SmsInfo || (  $this->success('数据不存在',url('Config/sendsms')));
         }else{
             $this->success('数据不存在',url('Config/sendsms'));
         }
        if( Db::name('send_system')->where(['s_id'=>$s_id])->delete()){
            $this->success('删除成功',url('Config/sendsms'));
        }
     }

    /**
     * 全局配置设置
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
     public function config_global() {
         $list = \app\common\model\Config::where('desc','<>','')->order('id asc')->select();
         return $this->fetch(null, compact('list'));
     }

    /**
     * 修改全局配置
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function config_update() {
        $allow_field = ['value'];
        $id = intval(input('id'));
        $info = \app\common\model\Config::where(['id'=>$id])->find();
        if(empty($info)) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'配置不存在']);

        $check_filed = $filed = input('field');
        if(empty($filed) || !in_array($filed,$allow_field)) $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'不允许修改']);

        $value = input('value');
        $data = [$filed=>$value];
        $flag = \app\common\model\Config::where(['id'=>$info['id']])->update($data);
        if($flag===false) {
            $this->ajaxReturn(['result'=>null,'code'=>ERROR1,'message'=>'修改失败']);
        } else {
            $this->ajaxReturn(['result'=>null,'code'=>SUCCESS,'message'=>'修改成功']);
        }
    }
}