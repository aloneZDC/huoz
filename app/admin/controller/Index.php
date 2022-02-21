<?php
namespace app\admin\controller;
use think\Controller;

class Index extends Admin {
    public function _initialize(){
        parent::_initialize();
    }
    //空操作
    public function _empty(){
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }
    public function index(){
        return $this->fetch();
    }

    /**
     * 统计全站信息
     */
    public function infoStatistics(){
        //统计全站信息
        //总人数
        $member_count=M('Member')->count();
        //众筹总数量
        $issue_count=M('issue')->field("sum(num)-sum(deal) as count")->find();
        //人民币收入
        $pay_money_count = M('pay')->where("status = 1 ")->sum('count');
        //人民币支出
        $withdraw_money_count = M('withdraw')->where(" status = 2")->sum("money");
        //充值单数
        $pay_count = M('pay')->where("status = 1 ")->count();
        //提现单数
        $withdraw_count = M('withdraw')->where(" status = 2")->count();
        //全站积分类型统计
       
        
        $currency_u_info = M('currency')
                        ->alias('a')
                        ->field('a.currency_name,sum(b.num) as num,sum(b.forzen_num) as forzen_num,a.currency_id')
                        ->join('left join yang_currency_user AS b on a.currency_id = b.currency_id')
                        ->group('a.currency_id')
                        ->select();
        //获取现价
       $all_name = 'rs_all_currency_user';
        $rs1 = S($all_name);
        if (empty($rs1)) {
            foreach ($currency_u_info as $k => $v) {
                $Currency_message[$v['currency_id']] = parent::getCurrencyMessageById($v['currency_id']);
            }
            S($all_name, $Currency_message, 1020);
        }
        $Currency_message = S($all_name);
        //获取众筹
        foreach ($currency_u_info as $k => $v) {
            $where['cid']=$v['currency_id'];
            $issue_log = M('issue_log') ->field('sum(num) as all_num')->where($where)->select();
           
            $issue[$v['currency_id']]['all_num'] = $issue_log['0']['all_num'];
            
        }
        
         foreach ($currency_u_info as $k => $v) {
            $allmoney = $v['num'] * $Currency_message[$v['currency_id']]['new_price'];
            $currency_u_info[$k]['allmoney'] = $allmoney;
            $forzen_allmoney = $v['forzen_num'] * $Currency_message[$v['currency_id']]['new_price'];
            $currency_u_info[$k]['forzen_allmoney'] = $forzen_allmoney;
            $currency_u_info[$k]['issue_all_num'] = $issue[$v['currency_id']]['all_num'];
            $issue_allmoney =$Currency_message[$v['currency_id']]['new_price']*$issue[$v['currency_id']]['all_num'];
            $currency_u_info[$k]['issue_allmoney'] = $issue_allmoney;
            $currency_u_info[$k]['all_allmoney']=$allmoney+$issue_allmoney+$forzen_allmoney;
            $currency_u_info[$k]['all_allnum']=$v['num']+$issue[$v['currency_id']]['all_num']+$v['forzen_num'];
        }
        
        
        $this->assign('member',$member_count);
        $this->assign('issue_count',$issue_count);
        $this->assign('pay_money_count',$pay_money_count);
        $this->assign('withdraw_money_count',$withdraw_money_count);
        $this->assign('pay_count',$pay_count);
        $this->assign('withdraw_count',$withdraw_count);
        $this->assign('currency_u_info',$currency_u_info);
        $this->display();
    }
    public function infoStatistics2(){
        $count =  M('mingxi')->select();
        $Page  = new \Think\Page(count($count),20);// 实例化分页类 传入总记录数和每页显示的记录数(25)
        //给分页传参数
        $Page->parameter = array(
        
            
        );
        $show       = $Page->show();// 分页显示输出
        $list= M('mingxi')->field('')
        ->limit($Page->firstRow.','.$Page->listRows)
        
        ->order('id desc')
        ->select();
        $this->assign('page',$show);
        $this->assign('list',$list);
    $this->display();
    }
    public function add(){
        $id=I('id');
        if(!empty($id)){
            $list=M("mingxi")->where("id='$id'")->find();
            $this->assign("list",$list);
        }
        $this->display();
    }
    public function add_mingxi(){
    
        if(empty($_POST['data'])){
            $this->error("日期");
        }
        /*if(empty($_POST['pay_num'])){
            $this->error("充值单数");
        }
        if(!isset($_POST['pay_money'])){
            $this->error("充值金额");
        }
        if(empty($_POST['with_money'])){
            $this->error("提现金额");
        }
        if(!isset($_POST['with_fee'])){
            $this->error("手续费");
        }*/
        $id=I('id');
    
        $data['data']=I('data');
        $data['pay_num']=I('pay_num');
        $data['pay_money']=I('pay_money');
        $data['with_money']=I('with_money');
        $data['with_fee']=I('with_fee');
        if(empty($id)){
            $re=M("mingxi")->add($data);
        }else{
            $re=M("mingxi")->where("id='$id'")->save($data);
        }
        if($re){
            $this->success("添加成功",U('Index/infoStatistics2'));
        }else{
            $this->error("添加失败");
        }
    
    }

    /**
     * 删除缓存方法
     */
    public function cache(){
//        $cacheDir = $_POST['type'];
//        $type = $cacheDir;
//        //将传递过来的值进行切割，我是已“-”进行切割的
//        $name = explode('-', $type);
//        //得到切割的条数，便于下面循环
//        $count = count($name);
//        //循环调用上面的方法
//        for ($i = 0; $i < $count; $i++)
//        {
//            //得到文件的绝对路径
//            $abs_dir = dirname(dirname(dirname(dirname(__FILE__))));
//            //组合路径
//            $pa = $abs_dir . str_replace("/", "\\", str_replace("./", "\\", RUNTIME_PATH)); //得到运行时的目录
//            $runtime = $pa . 'common~runtime.php';
//            if (file_exists($runtime))//判断 文件是否存在
//            {
//                unlink($runtime); //进行文件删除
//            }
//            //调用删除文件夹下所有文件的方法
//            $this->rmFile($pa, $name[$i]);
//        }
        //删除runtime目录下的全部文件及文件夹
        deldir(RUNTIME_PATH);
        $data['status'] = 1;
        $data['info'] = "清理成功";
        $this->ajaxReturn($data);
    }

    /**
     * 删除文件和目录
     * @param type $path 要删除文件夹路径
     * @param type $fileName 要删除的目录名称
     */
    private function rmFile($path, $fileName)
    {//删除执行的方法
        //去除空格
        $path = preg_replace('/(\/){2,}|{\\\}{1,}/', '/', $path);
        //得到完整目录
        $path.= $fileName;
        //判断此文件是否为一个文件目录
        if (is_dir($path))
        {
            //打开文件
            if ($dh = opendir($path))
            {
                //遍历文件目录名称
                while (($file = readdir($dh)) != false)
                {
                    $sub_file_path = $path . "\\" . $file;
                    if ("." == $file || ".." == $file)
                    {
                        continue;
                    }
                    if (is_dir($sub_file_path))
                    {
                        $this->rmFile($sub_file_path, "");
                        rmdir($sub_file_path);
                    }
                    //逐一进行删除
                    unlink($sub_file_path);
                }
                //关闭文件
                closedir($dh);
            }
            rmdir($sub_file_path);//删除当前目录
        }
    }

    /**
     * 图片上传
     * @return array
     */
    public function img_upload() {
        $file = $this->request->file('file');
        if($file==null) return ['code'=>ERROR1,'message'=>'图片不能为空'];

        if(!$file->checkImg()) return ['code'=>ERROR3,'message'=>'图片不能为空'];
        ;
        $res = $this->oss_upload([$file->getInfo()]);
        if(empty($res) || !is_array($res)) return ['code'=>ERROR3,'message'=>'图片上传失败,请重试！'];

        return ['code'=>SUCCESS,'获取成功'=>'图片上传失败,请重试！','data'=>['path'=>$res[0],'src'=>$res[0]]];
    }

    /**
     * 富文本图片上传
     * @return false|string
     */
    public function  oss_file_upload(){
        $upload = $this->oss_upload($file = [], $path = 'backend_ke');
        if(!empty($upload['imgFile'])){
            return json_encode(['error'=>0,'url'=>$upload['imgFile']]);
        }else{
            return json_encode(['error'=>0,'message'=>'上传失败']);
        }
        exit;
    }
}