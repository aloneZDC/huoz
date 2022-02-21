<?php
namespace app\admin\controller;
use think\Db;

class Admin extends Common {
	protected $admin;
	protected $config;
	protected $not_allow_shop = true; //是否要验证店家权限
	public function _initialize(){
		parent::_initialize();

        $vpn_config_file =  ROOT_PATH.'admin_ip_allow.php';
        if(file_exists($vpn_config_file)) {
            $ip = get_client_ip();
            $allow_ip = require_once $vpn_config_file;
            $ip_check = true;
            if(!empty($allow_ip) && is_array($allow_ip)){
                $ip_check = false;
                foreach ($allow_ip as $a_ip) {
                    if(!empty($a_ip) && strpos($ip,$a_ip)!==false) $ip_check = true;
                }
            }
            if(!$ip_check) die($ip.' not allow');
        }


        $admin_userid=session('admin_userid');
		//验证登录
		if (empty($admin_userid)){
			return $this->redirect('Login/login');
		}

		//管理员信息
		$uid=$admin_userid;
		$admin_user=Db::name("Admin")->where("admin_id='$uid'")->find();
		$this->admin=$admin_user;
		//网站配置信息
		$config=Db::name("Config")->field('key,value')->select();
		foreach ($config as $k=>$v){
			$config[$v['key']]=$v['value'];
		}
		$this->config=$config;
		//**************权限管理***********
//		$URL_MODULE_MAP = array_keys(C('URL_MODULE_MAP'))[0];
		$URL_MODULE_MAP = "admin";
		$adminquanxian=Db::name("Admin")->field('nav')->where(['admin_id'=>$admin_userid])->find();
		$rules=$adminquanxian['nav'];
		if(empty($rules)){
			$this->error('此账号尚未分配权限',$URL_MODULE_MAP.'/Login/login');
		}
		$rules=explode(',', $rules);
		foreach ($rules as $k=>$v){
			$list[]=Db::name("Nav")->where('nav_id='.$v)->find();
		}

        if($admin_user['rule']=='shop' && strtolower($this->request->controller())=='index'){
            $this->redirect('Store/index');
            die();
        }

		if($admin_user['rule']=='shop' && $this->not_allow_shop) {
            $this->error('没有权限');
            die();
        }

// 		$igNore = array(1, 53, 54);  // for permission
// 		if( in_array($uid, $igNore) ){
// 		}else{
// 		    $url = "/". strtolower( trim($_SERVER['PATH_INFO'] ) );
// 		    $c_v = explode("/", $url);
// 		    // var_dump($c_v);die();
// 		    if( $c_v[1]=="member" || $url=="/index/index" || $url=="/currency/showverify" ){

// 		    }else{
//         		    $isExist = 0;
//         		    foreach ($list as $k=>$v){
//         		        $v_nav = strtolower ( trim( $v["nav_url"] ) );
//         		        $r = strstr($v_nav, $url);
//         		        if( $r!="" ){
//         		            $isExist = 1;
//         		            break;
//         		        }
//         		    }
//         		    if($isExist==0) {
//         		        $this->error('此账号尚未分配权限',$URL_MODULE_MAP.'/Login/login');
//         		        die();
//         		    }
// 		    }
// 		}

		foreach ($list as $k=>$v){
			$v['nav_url'] =url( '/'.$URL_MODULE_MAP.$v['nav_url']);
			$value[$v['cat_id']][]=$v;
		}
		foreach ($value as $k=>$v){
			$this->assign($k."_nav",$v);
		}
		$this->assign('URL_MODULE_MAP',$URL_MODULE_MAP);
	}
	//图片处理
	public function upload($file=[]){
        $upload  = $this->oss_upload($file = [], $path = 'certificate');
        if(!empty($upload)){

            $keys = array_keys($_FILES)[0];
            return $upload[$keys];
        }
	    switch($file['type'])
	    {
	        case 'image/jpeg': $ext = 'jpg'; break;
	        case 'image/gif': $ext = 'gif'; break;
	        case 'image/png': $ext = 'png'; break;
	        case 'image/tiff': $ext = 'tif'; break;
	        default: $ext = ''; break;
	    }
	    if (empty($ext)){
	        return false;
	    }
		$upload = new \Think\Upload();// 实例化上传类
		$upload->maxSize   =     3145728 ;// 设置附件上传大小
		$upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
		$upload->savePath  =      './Public/Uploads/'; // 设置附件上传目录
		// 上传文件
		$info   =  $upload->uploadOne($file);
		if(!$info) {
			// 上传错误提示错误信息
			$this->error($upload->getError());exit();
		}else{
			// 上传成功
			$pic=$info['savepath'].$info['savename'];
			$url='/Uploads'.ltrim($pic,".");
		}
		return $url;
	}
	//空操作
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		$this->display('Public:404');
	}

	/**
	 +----------------------------------------------------------
	 * Export Excel | 2013.08.23
	 * Author:HongPing <hongping626@qq.com>
	 +----------------------------------------------------------
	 * @param $expTitle     string File name
	 +----------------------------------------------------------
	 * @param $expCellName  array  Column name
	 +----------------------------------------------------------
	 * @param $expTableData array  Table data
	 +----------------------------------------------------------
	 */
	public function exportExcel($expTitle,$expCellName,$expTableData){
	    $xlsTitle = iconv('utf-8', 'gb2312', $expTitle);//文件名称
	    $fileName =date('_YmdHis');//or $xlsTitle 文件名称可根据自己情况设定
	    $cellNum = count($expCellName);
	    $dataNum = count($expTableData);
//	    require_once('ThinkPHP/Extend/Vendor/PHPExcel/PHPExcel.php');
        import("PHPExcel/PHPExcel",ROOT_PATH.'extend');

	    $objPHPExcel = new \PHPExcel();
	    $cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

	    $objPHPExcel->getActiveSheet(0)->mergeCells('A1:'.$cellName[$cellNum-1].'1');//合并单元格
	    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.'  Export time:'.date('Y-m-d H:i:s'));
	    for($i=0;$i<$cellNum;$i++){
	        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i].'2', $expCellName[$i][1]);
	    }
	    // Miscellaneous glyphs, UTF-8
	    for($i=0;$i<$dataNum;$i++){
	        for($j=0;$j<$cellNum;$j++){
	            $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+3), $expTableData[$i][$expCellName[$j][0]]);
	        }
	    }

	    header('pragma:public');
	    header('Content-type:application/vnd.ms-excel;charset=utf-8;name="'.$xlsTitle.'.xls"');
	    header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
	    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
	    $objWriter->save('php://output');
	    exit;
	}

	/**
	 +----------------------------------------------------------
	 * Import Excel | 2013.08.23
	 * Author:HongPing <hongping626@qq.com>
	 +----------------------------------------------------------
	 * @param  $file   upload file $_FILES
	 +----------------------------------------------------------
	 * @return array   array("error","message")
	 +----------------------------------------------------------
	 */
	public function importExecl($file){
	    if(!file_exists($file)){
	        return array("error"=>0,'message'=>'file not found!');
	    }
	    Vendor("PHPExcel.PHPExcel.IOFactory");
	    $objReader = \PHPExcel_IOFactory::createReader('Excel5');
	    try{
	        $PHPReader = $objReader->load($file);
	    }catch(\Exception $e){}
	    if(!isset($PHPReader)) return array("error"=>0,'message'=>'read error!');
	    $allWorksheets = $PHPReader->getAllSheets();
	    $i = 0;
	    foreach($allWorksheets as $objWorksheet){
	        $sheetname=$objWorksheet->getTitle();
	        $allRow = $objWorksheet->getHighestRow();//how many rows
	        $highestColumn = $objWorksheet->getHighestColumn();//how many columns
	        $allColumn = \PHPExcel_Cell::columnIndexFromString($highestColumn);
	        $array[$i]["Title"] = $sheetname;
	        $array[$i]["Cols"] = $allColumn;
	        $array[$i]["Rows"] = $allRow;
	        $arr = array();
	        $isMergeCell = array();
	        foreach ($objWorksheet->getMergeCells() as $cells) {//merge cells
	            foreach (\PHPExcel_Cell::extractAllCellReferencesInRange($cells) as $cellReference) {
	                $isMergeCell[$cellReference] = true;
	            }
	        }
	        for($currentRow = 1 ;$currentRow<=$allRow;$currentRow++){
	            $row = array();
	            for($currentColumn=0;$currentColumn<$allColumn;$currentColumn++){;
	            $cell =$objWorksheet->getCellByColumnAndRow($currentColumn, $currentRow);
	            $afCol = \PHPExcel_Cell::stringFromColumnIndex($currentColumn+1);
	            $bfCol = \PHPExcel_Cell::stringFromColumnIndex($currentColumn-1);
	            $col = \PHPExcel_Cell::stringFromColumnIndex($currentColumn);
	            $address = $col.$currentRow;
	            $value = $objWorksheet->getCell($address)->getValue();
	            if(substr($value,0,1)=='='){
	                return array("error"=>0,'message'=>'can not use the formula!');
	                exit;
	            }
	            if($cell->getDataType()==\PHPExcel_Cell_DataType::TYPE_NUMERIC){
	                $cellstyleformat=$cell->getParent()->getStyle( $cell->getCoordinate() )->getNumberFormat();
	                $formatcode=$cellstyleformat->getFormatCode();
	                if (preg_match('/^([$[A-Z]*-[0-9A-F]*])*[hmsdy]/i', $formatcode)) {
	                    $value=gmdate("Y-m-d", \PHPExcel_Shared_Date::ExcelToPHP($value));
	                }else{
	                    $value=\PHPExcel_Style_NumberFormat::toFormattedString($value,$formatcode);
	                }
	            }
	            if($isMergeCell[$col.$currentRow]&&$isMergeCell[$afCol.$currentRow]&&!empty($value)){
	                $temp = $value;
	            }elseif($isMergeCell[$col.$currentRow]&&$isMergeCell[$col.($currentRow-1)]&&empty($value)){
	                $value=$arr[$currentRow-1][$currentColumn];
	            }elseif($isMergeCell[$col.$currentRow]&&$isMergeCell[$bfCol.$currentRow]&&empty($value)){
	                $value=$temp;
	            }
	            $row[$currentColumn] = $value;
	            }
	            $arr[$currentRow] = $row;
	        }
	        $array[$i]["Content"] = $arr;
	        $i++;
	    }
	    spl_autoload_register(array('Think','autoload'));//must, resolve ThinkPHP and PHPExcel conflicts
	    unset($objWorksheet);
	    unset($PHPReader);
	    unset($PHPExcel);
	    unlink($file);
	    return array("error"=>1,"data"=>$array);
	}
	protected function ajaxReturn($data=[]){
        if (func_num_args() > 2) {
            $args = func_get_args();
            array_shift($args);
            $info = array();
            $info['result'] = $data;
            $info['message'] = array_shift($args);
            $info['code'] = array_shift($args);
            $data = $info;
        }
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($data));
    }

    protected function successJson($code, $message = '', $result = null)
    {
        if (is_array($code)) {
            return $code;
        }
        return ['code' => $code, 'message' => $message, 'result' => $result];
    }
}



