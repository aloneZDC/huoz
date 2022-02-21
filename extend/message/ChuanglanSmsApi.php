<?php
namespace message;
header("Content-type:text/html; charset=UTF-8");
/* *
 * 类名：ChuanglanSmsApi
 * 功能：创蓝接口请求类
 * 详细：构造创蓝短信接口请求，获取远程HTTP数据
 * 版本：1.3
 * 日期：2017-04-12
 * 说明：
 * 以下代码只是为了方便客户测试而提供的样例代码，客户可以根据自己网站的需要，按照技术文档自行编写,并非一定要使用该代码。
 * 该代码仅供学习和研究创蓝接口使用，只是提供一个参考。
 */
class ChuanglanSmsApi {

	//参数的配置 请登录zz.253.com 获取以下API信息 ↓↓↓↓↓↓↓
	const API_SEND_URL='http://smssh1.253.com/msg/send/json'; //创蓝发送短信接口URL
	
	const API_VARIABLE_URL = 'http://smssh1.253.com/msg/variable/json';//创蓝变量短信接口URL
	
	const API_BALANCE_QUERY_URL= 'http://smssh1.253.com/msg/balance/json';//创蓝短信余额查询接口URL

	const API_INTERNATIONAL_URL= 'http://intapi.253.com/send/json';//创蓝发送国际短信接口URL
	public $API_ACCOUNT= ''; // 创蓝API账号
	
	public $API_PASSWORD= '';// 创蓝API密码
	//参数的配置 请登录zz.253.com 获取以上API信息 ↑↑↑↑↑↑↑

	function __construct($account=null,$password=null)
	{
		if(!empty($account)){
			$this->API_ACCOUNT=$account;
		}
		if(!empty($password)){
			$this->API_PASSWORD=$password;
		}
	}

	/**
	 * 发送短信
	 *
	 * @param string $mobile 		手机号码
	 * @param string $msg 			短信内容
	 * @param string $needstatus 	是否需要状态报告
	 */
	public function sendSMS( $mobile, $msg, $needstatus = 'true') {
		//创蓝接口参数
		$postArr = array (
			'account'  =>  $this->API_ACCOUNT,
			'password' => $this->API_PASSWORD,
			'msg' => urlencode($msg),
			'phone' => $mobile,
			'report' => $needstatus,
       		);
		$result = $this->curlPost( self::API_SEND_URL, $postArr);
		return $result;
	}
	
	/**
	 * 发送变量短信
	 *
	 * @param string $msg 			短信内容
	 * @param string $params 	最多不能超过1000个参数组
	 */
	public function sendVariableSMS( $msg, $params) {
		
		//创蓝接口参数
		$postArr = array (
			'account'  =>  $this->API_ACCOUNT,
			'password' => $this->API_PASSWORD,
			'msg' => $msg,
			'params' => $params,
			'report' => 'true'
        	);
		
		$result = $this->curlPost( self::API_VARIABLE_URL, $postArr);
		return $result;
	}

	/**
	 * 发送国际短信
	 * 返回数据格式如下：
		code	string	0代表发送成功，其他code代表出错，详细见"返回值说明"页面
		error	string	提交成功返回空，或者相应错误信息描述
		msgid	string	消息id
	 * @param $msg
	 * @param $mobile
	 * Create by: Red
	 * Date: 2019/9/4 16:37
	 */
	public function sendInternationalSMS($msg,$mobile){
		//创蓝接口参数
		$postArr = array (
			'account'  =>  $this->API_ACCOUNT,
			'password' => $this->API_PASSWORD,
			'msg' => $msg,
			'mobile' => $mobile,
		);
		$result = $this->curlPost( self::API_INTERNATIONAL_URL, $postArr);
		return $result;
	}
	
	/**
	 * 查询额度
	 *
	 *  查询地址
	 */
	public function queryBalance() {
		
		//查询参数
		$postArr = array (
			'account'  =>  $this->API_ACCOUNT,
			'password' => $this->API_PASSWORD,
		);
		$result = $this->curlPost(self::API_BALANCE_QUERY_URL, $postArr);
		return $result;
	}

	/**
	 * 通过CURL发送HTTP请求
	 * @param string $url  //请求URL
	 * @param array $postFields //请求参数 
	 * @return mixed
	 *  
	 */
	private function curlPost($url,$postFields){
		$postFields = json_encode($postFields);
		$ch = curl_init ();
		curl_setopt( $ch, CURLOPT_URL, $url ); 
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json; charset=utf-8'   //json版本需要填写  Content-Type: application/json;
			)
		);
		curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); //若果报错 name lookup timed out 报错时添加这一行代码
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_POST, 1 );
         	curl_setopt( $ch, CURLOPT_POSTFIELDS, $postFields);
       		curl_setopt( $ch, CURLOPT_TIMEOUT,60); 
        	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);
        	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);
		$ret = curl_exec ( $ch );
        if (false == $ret) {
            $result = curl_error($ch);
        } else {
            $rsp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 != $rsp) {
                $result = "请求状态 ". $rsp . " " . curl_error($ch);
            } else {
                $result = $ret;
            }
        }
		curl_close ( $ch );
		return $result;
	}
	
}





?>
