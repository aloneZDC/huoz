<?php
namespace message;
use think\Db;
use think\Exception;

class Ucpaas extends Sms {
    protected $keyword = 's_yun';

    const BaseUrl = "https://open.ucpaas.com/ol/sms/";
    private $templateid ="355407";
    protected $setting = [];

    public function __construct($setting) {
        parent::__construct($setting);
    }

    public function send($mobile, $code, $type,$country_code='86') {
        if(!$this->setting) return false;

        $moduleId = $this->getModuleByType($type);
        if($country_code=='86') {
            $result = $this->ucpSendSms($code,$mobile,$moduleId);
            $newresult = json_decode($result);
            if($newresult && $newresult->code=="000000") return true;

            return $result;
        } else {
            $mobile = ltrim($mobile,'0');
            $mobile = '00'.$country_code.$mobile;
            $result = $this->ucpSendSmsEn($code,$mobile,$moduleId);
            $newresult = json_decode($result);
            if ($newresult && $newresult->resp->respCode == "000000") return true;

            return $result;
        }
    }

    private function getResult($url, $body = null, $method)
    {
        $data = $this->connection($url,$body,$method);
        if (isset($data) && !empty($data) && $data!=='false') {
            $result = $data;
        } else {
            $result = '没有返回数据';
        }
        return $result;
    }

    /**
     * @param $url    请求链接
     * @param $body   post数据
     * @param $method post或get
     * @return mixed|string
     */

    private function connection($url, $body,$method)
    {
        try{
            if (function_exists("curl_init")) {
                $header = array(
                    'Accept:application/json',
                    'Content-Type:application/json;charset=utf-8',
                );
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                if($method == 'post'){
                    curl_setopt($ch,CURLOPT_POST,1);
                    curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
                }
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                $result = curl_exec($ch);
                curl_close($ch);
            } else {
                $opts = array();
                $opts['http'] = array();
                $headers = array(
                    "method" => strtoupper($method),
                );
                $headers[]= 'Accept:application/json';
                $headers['header'] = array();
                $headers['header'][]= 'Content-Type:application/json;charset=utf-8';

                if(!empty($body)) {
                    $headers['header'][]= 'Content-Length:'.strlen($body);
                    $headers['content']= $body;
                }
                $headers['timeout'] = 5;
                $opts['http'] = $headers;
                $result = file_get_contents($url, false, stream_context_create($opts));
            }
        } catch(Exception $e) {
            $result = $e->getMessage();
        }

        return $result;
    }

    /**
     * 单条发送短信的function，适用于注册/找回密码/认证/操作提醒等单个用户单条短信的发送场景
     * @param string $mobile       接收短信的手机号码
     * @param string $moduleId   短信模板，可在后台短信产品→选择接入的应用→短信模板-模板ID，查看该模板ID
     * @param null|string $param   变量参数，多个参数使用英文逗号隔开（如：param=“a,b,c”）
     * @param string $uid			用于贵司标识短信的参数，按需选填。
     * @return mixed|string
     * @throws \Exception
     */
    public function ucpSendSms($param, $mobile, $moduleId, $uid = null)
    {
        if ($param) $param = str_replace('::', ',', $param);

        $url = self::BaseUrl . 'sendsms';
        $body_json = array(
            'sid' => $this->setting['account'],
            'token' => $this->setting['token'],
            'appid' => $this->setting['appid'],
            'templateid' => $this->getTemplateid($moduleId),
            'param' => $param,
            'mobile' => $mobile,
            'uid' => $uid,
        );
        $body = json_encode($body_json);
        $data = $this->getResult($url, $body, 'post');
        return $data;
    }

    /**根据模板id进行对应获取云之讯模板id
     * @param $moduleId
     */
    public  function getTemplateid($moduleId){
        switch($moduleId){
            case 100:
                $templateid = "610320";
                break;
            case 200:
                $templateid = "610320";
                break;
            case 300:
                $templateid = "610320";
                break;
            case 400:
                $templateid = "610320";
                break;
            case 500:
                $templateid = "610320";
                break;
            case 600:
                $templateid = "610320";
                break;
            case 700:
                $templateid = "610328";//暂未通过
                break;
            case 800:
                $templateid = "538659"; // OTC付款通知
                break;
            case 900:
                $templateid = "610320";
                break;
            case 1000:
                $templateid = "538661"; // 已放行
                break;
            case 1100:
                $templateid = "538663";// 出售通知
                break;
        }
        return $templateid;
    }

    /**根据模板id进行对应获取云之讯模板id-英文
     * @param $moduleId
     */
    public  function getEnTemplateid($moduleId){
        switch($moduleId){
            case 100:
                $templateid = "538656";
                break;
            case 200:
                $templateid = "538656";
                break;
            case 300:
                $templateid = "538656";
                break;
            case 400:
                $templateid = "538656";
                break;
            case 500:
                $templateid = "538656";
            case 600:
                $templateid = "538656";
                break;
            case 700:
                $templateid = "538658";//暂未通过
                break;
            case 800:
                $templateid = "538660";
                break;
            case 900:
                $templateid = "538656";
                break;
            case 1000:
                $templateid = "538662";
                break;
            case 1100:
                $templateid = "538664";
                break;
        }
        return $templateid;
    }

    /**
    群发送短信的function，适用于运营/告警/批量通知等多用户的发送场景
     * @param $appid        应用ID
     * @param $mobileList   接收短信的手机号码，多个号码将用英文逗号隔开，如“18088888888,15055555555,13100000000”
     * @param $templateid   短信模板，可在后台短信产品→选择接入的应用→短信模板-模板ID，查看该模板ID
     * @param null $param   变量参数，多个参数使用英文逗号隔开（如：param=“a,b,c”）
     * @param $uid			用于贵司标识短信的参数，按需选填。
     * @return mixed|string
     * @throws Exception
     */
    public function SendSms_Batch($appid,$templateid,$param=null,$mobileList,$uid){
        $url = self::BaseUrl . 'sendsms_batch';
        $body_json = array(
            'sid'=>$this->setting['account'],
            'token'=>$this->setting['token'],
            'appid'=>$this->setting['appid'],
            'templateid'=>$this->templateid,
            'param'=>$param,
            'mobile'=>$mobileList,
            'uid'=>$uid,
        );
        $body = json_encode($body_json);
        $data = $this->getResult($url, $body,'post');
        return $data;
    }


    public function ucpSendSmsEn($param,$mobile,$moduleId) {
        $time = date('YmdHis');
        $sigParameter = strtoupper(md5($this->setting['account'].$this->setting['token'].$time));
        $smsapi = "https://api.ucpaas.com/2014-06-30/Accounts/{$this->setting['account']}/Messages/templateSMS?sig={$sigParameter}";
        $data = [
            //'sig'=>$sigParameter,
            'templateSMS'=>[
                "appId"=>$this->setting['appid'],
                "param"=>$param,
                "templateId"=>$this->getEnTemplateid($moduleId),
                "to"=>$mobile
            ]
        ];
        $authorization = base64_encode($this->setting['account'].":".$time);

        try{
            $ssl = substr($smsapi, 0, 8) == "https://" ? TRUE : FALSE;
            $ch = curl_init();
            $fields =  json_encode($data) ;
            $headers = array(
                "Accept: application/json",
                "Content-Type: application/json;charset=utf-8",
                "Authorization: ".$authorization,
                //"User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36 SE 2.X MetaSr 1.0",
                //'Content-Length: '.strlen($fields),
            );

            curl_setopt($ch, CURLOPT_URL, $smsapi);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if ($ssl){
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            }
            $result = curl_exec($ch);
        } catch(Exception $e){
            $result = $e->getMessage();
        }

        return $result;
    }
}