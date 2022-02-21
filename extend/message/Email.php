<?php
namespace message;
use think\Db;
use think\Exception;

use PHPMailer\PHPMailer\PHPMailer;

/**
* 邮件接口
*/
class Email {
    private $name = '邮件';
    private $hosts = [];

    public function __construct($name)
    {
        $this->name = $name;
    }

    //发送邮件
    public function send_email($cur_host,$email,$types,$content)
    {
        $msg = $this->getMsg($types, $content);

        $r = $this->_send($cur_host,$email, $msg['title'], $msg['body']);

        if (empty($r)) {
            return true;
        } else {
            return $r;
        }
    }

    //正式发送
    private function _send($cur_host,$email, $title, $body){
        $host = $cur_host['es_ip'];
        $port = $cur_host['es_port'];
        $username = $cur_host['es_user'];
        $password = $cur_host['es_pwd'];

        try{
            $mail=new PHPMailer();//建立邮件发送类
            $mail->IsSMTP();//使用SMTP方式发送 设置设置邮件的字符编码，若不指定，则为'UTF-8
            $mail->Host= $host;//'smtp.qq.com';//您的企业邮局域名
            $mail->Port = $port;
            $mail->SMTPAuth=true;//启用SMTP验证功能   设置用户名和密码。
            $mail->Username=$username;//'mail@koumang.com'//邮局用户名(请填写完整的email地址)
            $mail->Password= $password;//'xiaowei7758258'//邮局密码
            $mail->From=$username;//'mail@koumang.com'//邮件发送者email地址
            $mail->FromName=$this->name;//邮件发送者名称
            $mail->AddAddress($email);// 收件人邮箱，收件人姓名
            $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
            $mail->IsHTML(true); // set email format to HTML //是否使用HTML格式
            $mail->CharSet= 'UTF-8';
            $mail->Subject="=?UTF-8?B?".base64_encode($title)."?=";
            $mail->Body=$body; //邮件内容
            $mail->AltBody = "这是一封HTML格式的电子邮件。"; //附加信息，可以省略
            $mail->Send();
            return $mail->ErrorInfo;
        } catch(Exception $e){
            return 'error';
        }
    }

    private function getMsg($type='',$param=[]) {
        $msg = ['title'=>'','body'=>'','code'=>''];

        $code = '';
        if(isset($param['code'])) {
            $code = $param['code'];
        } else {
            $code = rand(100000,999999);
        }

        if($type=='register') {
            $type = 'Register';
        } elseif($type=='bindemail') {
            $type = 'Binding Email';
        } elseif($type=='findpwd') {
            $type = 'Find Password';
        } elseif(in_array($type, ['invit','otc_pay','otc_fangxing','otc_sell','e_notice','chongbi','news'])) {

        } elseif($type=='modifypwd') {
            $type = 'modify Password';
        } elseif($type=='retradepwd') {
            $type = 'modify Trade Password';
        } elseif($type=='modifyemail') {
            $type = 'modify Email';
        } elseif($type=='tcoin') {
            $type = 'take Coin';
        } else {
            $type = 'Validate';
        }

        if(in_array($type, ['invit','otc_pay','otc_fangxing','otc_sell','e_notice','chongbi','news'])) {
            $msg = $this->getContentSelf($type,$code,$param);
        } else {
            $content = $this->getContent($type,$code,$param);
            $msg['code'] = $code;
            $msg['title'] = $code.' is your verification code for '.$type;
            $msg['body'] = $content;
        }

        return $msg;
    }

    /**
     *        <tr>
            <td colspan="3" align="center" style="background-color:#454c6d; height: 55px; padding: 30px 0"><a href="'.$host.'" target="_blank" rel="noopener"><img src="'.$host.'/Public/Home/images/image/hblogo.png"></a></td>
        </tr>
        Authentication code is only used in '.$host.' .
        <a href="'.$host.'" rel="noopener" target="_blank">'.$host.'</a><br>
     */
    private function getContent($type,$code,$param) {
        // $host = 'http://iof.miaoxiangcl.com/';

        return '<table cellpadding="0" cellspacing="0" style="border: 1px solid #cdcdcd; width: 640px; margin:auto;font-size: 12px; color: #1E2731; line-height: 20px;">
    <tbody>
        <tr>
            <td colspan="3" align="center" style="background-color:#454c6d; height: 55px; padding: 30px 0"><a href="javascript::" rel="noopener"><img style="height: 100px;" alt="logo" src="//io-app.oss-cn-shanghai.aliyuncs.com/rocket/logo.jpg"></a></td>
        </tr>
        <tr style="height: 30px;"></tr>
        <tr>
            <td width="20"></td>
            <td style="line-height: 40px">
                Hello：<br>
                【'.$this->name.'】 Verification Code：<b><span style="border-bottom:1px dashed #ccc;z-index:1" t="7" onclick="return false;" data="374000">'.$code.'</span></b>. You are trying to 【'.$type.'】. The Verification Code will expire in One hour.<br>
                If you do not operate by yourself, please do not enter the Authentication Code anywhere and change the login password immediately!<br>
                In order to prevent illegal fraud, do not enter account number, password, authentication code in any other third-party website except official website.<br>
            </td>
            <td width="20"></td>
        </tr>
        <tr style="height: 20px;"></tr>
        <tr>
            <td width="20"></td>
            <td>
                <br>【'.$this->name.'】 Team<br>
            </td>
            <td width="20"></td>
        </tr>
        <tr style="height: 50px;"></tr>
    </tbody>
</table>';
    }

        //自定义的Email
    private function getContentSelf($type,$code,$param) {
        $host = 'https://xcash.miaoxiangcl.com/';

        $title = $content = '';
        switch ($type) {
            case 'invit':
                $url = $host.'/Reg/registered/Member_id/'.$code;
                $title = 'HBDAex Team [ registration invitation ]';
                $content = '<table cellpadding="0" cellspacing="0" style="border: 1px solid #cdcdcd; width: 640px; margin:auto;font-size: 12px; color: #1E2731; line-height: 20px;">
                <tbody>
                    <tr>
                        <td colspan="3" align="center" style="background-color:#454c6d; height: 55px; padding: 30px 0"><a href="'.$url.'" target="_blank" rel="noopener"><img style="height: 100px;" src="//io-app.oss-cn-shanghai.aliyuncs.com/rocket/logo.jpg"></a></td>
                    </tr>
                    <tr style="height: 30px;"></tr>
                    <tr>
                        <td width="20"></td>
                        <td style="line-height: 40px">
                            Please click on the link: <a href="'.$url.'" rel="noopener" target="_blank">Completing the registration invitation</a>
                        </td>
                        <td width="20"></td>
                    </tr>
                    <tr style="height: 20px;"></tr>
                    <tr>
                        <td width="20"></td>
                        <td>
                            If the link cannot be clicked, please copy and open the following web address:<br>
                            <a href="'.$url.'" rel="noopener" target="_blank">'.$url.'</a>
                        </td>
                        <td width="20"></td>
                    </tr>
                    <tr style="height: 20px;"></tr>
                    <tr>
                        <td width="20"></td>
                        <td>
                            <br>【'.$this->name.'】 Team<br>
                            <a href="'.Version_ios_DowUrl.'" rel="noopener" target="_blank">'.Version_ios_DowUrl.'</a><br>
                        </td>
                        <td width="20"></td>
                    </tr>
                    <tr style="height: 50px;"></tr>
                </tbody>
            </table>';
                break;
            case 'otc_pay':
                $params = explode('::',$code);
                //$url = $host."/OrdersOtc/orders_info?orders_id=".$params[0];
                $url = "javascript:;";

                $title = 'Acknowledgement Of Order';
                $content = '<table cellpadding="0" cellspacing="0" style="border: 1px solid #cdcdcd; width: 640px; margin:auto;font-size: 12px; color: #1E2731; line-height: 20px;">
                <tbody>
                    <tr>
                        <td colspan="3" align="center" style="background-color:#454c6d; height: 55px; padding: 30px 0"><a href="'.$url.'" target="_blank" rel="noopener"><img style="height: 100px;" src="'.$host.'/static/home/images/logo.png"></a></td>
                    </tr>
                    <tr style="height: 30px;"></tr>
                    <tr>
                        <td width="20"></td>
                        <td style="line-height: 40px">
                            Hello：<br>
                            Order number '.$params[1].' has placed the order in '.$params[2].' and marked the payment，Please login to my advertisement and check it
                        </td>
                        <td width="20"></td>
                    </tr>
                    <tr style="height: 20px;"></tr>
                    <tr>
                        <td width="20"></td>
                        <td>
                            <br>【'.$this->name.'】 Team<br>
                            <a href="'.$host.'" rel="noopener" target="_blank">'.$host.'</a><br>
                        </td>
                        <td width="20"></td>
                    </tr>
                    <tr style="height: 50px;"></tr>
                </tbody>
            </table>';
                break;
            case 'otc_sell':
                //$url = $host."/OrdersOtc/orders_info?orders_id=".$params[0];
                $url = "javascript:;";

                $title = '确认订单';
                $content = '<table cellpadding="0" cellspacing="0" style="border: 1px solid #cdcdcd; width: 640px; margin:auto;font-size: 12px; color: #1E2731; line-height: 20px;">
                <tbody>
                    <tr>
                        <td colspan="3" align="center" style="background-color:#454c6d; height: 55px; padding: 30px 0"><a href="'.$url.'" target="_blank" rel="noopener"><img style="height: 100px;" src="//io-app.oss-cn-shanghai.aliyuncs.com/rocket/logo.jpg"></a></td>
                    </tr>
                    <tr style="height: 30px;"></tr>
                    <tr>
                        <td width="20"></td>
                        <td style="line-height: 40px">
                            你好：<br>
                            我们有一个新订单给你. 订单号为:'.$code.',请登录平台查看并完成支付操作.
                        </td>
                        <td width="20"></td>
                    </tr>
                    <tr style="height: 20px;"></tr>
                    <tr>
                        <td width="20"></td>
                        <td>
                            <br>【'.$this->name.'】 团队<br>
                            <a href="'.Version_ios_DowUrl.'" rel="noopener" target="_blank">'.Version_ios_DowUrl.'</a><br>
                        </td>
                        <td width="20"></td>
                    </tr>
                    <tr style="height: 50px;"></tr>
                </tbody>
            </table>';
                break;
            case 'otc_fangxing':
                $params = explode('::',$code);
                //$url = $host."/OrdersOtc/orders_info?orders_id=".$params[0];
                $url = "javascript:;";

                $title = '确认订单';
                $content = '<table cellpadding="0" cellspacing="0" style="border: 1px solid #cdcdcd; width: 640px; margin:auto;font-size: 12px; color: #1E2731; line-height: 20px;">
                <tbody>
                    <tr>
                        <td colspan="3" align="center" style="background-color:#454c6d; height: 55px; padding: 30px 0"><a href="'.$url.'" target="_blank" rel="noopener"><img style="height: 100px;" src="//io-app.oss-cn-shanghai.aliyuncs.com/rocket/logo.jpg"></a></td>
                    </tr>
                    <tr style="height: 30px;"></tr>
                    <tr>
                        <td width="20"></td>
                        <td style="line-height: 40px">
                            你好：<br>
                            订单号 '.$params[1].' 已于 '.$params[2].' 下单并注明付款，请登录我的广告查看.
                        </td>
                        <td width="20"></td>
                    </tr>
                    <tr style="height: 20px;"></tr>
                    <tr>
                        <td width="20"></td>
                        <td>
                            <br>【'.$this->name.'】 团队<br>
                            <a href="'.Version_ios_DowUrl.'" rel="noopener" target="_blank">'.Version_ios_DowUrl.'</a><br>
                        </td>
                        <td width="20"></td>
                    </tr>
                    <tr style="height: 50px;"></tr>
                </tbody>
            </table>';
                break;
            case 'e_notice':
                $url = "javascript:;";
                $title = 'Error notice of IO';
                $content = '<table cellpadding="0" cellspacing="0" style="border: 1px solid #cdcdcd; width: 640px; margin:auto;font-size: 12px; color: #1E2731; line-height: 20px;">
                <tbody>
                    <tr>
                        <td colspan="3" align="center" style="background-color:#454c6d; height: 55px; padding: 30px 0"><a href="'.$url.'" target="_blank" rel="noopener"><img style="height: 100px;" src="//io-app.oss-cn-shanghai.aliyuncs.com/rocket/logo.jpg"></a></td>
                    </tr>
                    <tr style="height: 30px;"></tr>
                    <tr>
                        <td width="20"></td>
                        <td style="line-height: 40px">
                            '.$code.'
                        </td>
                        <td width="20"></td>
                    </tr>
                    <tr style="height: 20px;"></tr>
                    <tr>
                        <td width="20"></td>
                        <td>
                            <br>【'.$this->name.'】 Team<br>
                            <a href="'.Version_ios_DowUrl.'" rel="noopener" target="_blank">'.Version_ios_DowUrl.'</a><br>
                        </td>
                        <td width="20"></td>
                    </tr>
                    <tr style="height: 50px;"></tr>
                </tbody>
            </table>';
            case 'chongbi':
                $url = "javascript:;";
                $title = 'Recharge reminder';
                $content = '<table cellpadding="0" cellspacing="0" style="border: 1px solid #cdcdcd; width: 640px; margin:auto;font-size: 12px; color: #1E2731; line-height: 20px;">
                <tbody>
                    <tr>
                        <td colspan="3" align="center" style="background-color:#454c6d; height: 55px; padding: 30px 0"><a href="'.$url.'" target="_blank" rel="noopener"><img style="height: 100px;" src="//io-app.oss-cn-shanghai.aliyuncs.com/rocket/logo.jpg"></a></td>
                    </tr>
                    <tr style="height: 30px;"></tr>
                    <tr>
                        <td width="20"></td>
                        <td style="line-height: 40px">
                            Your recharged '.$code.' has arrived.
                        </td>
                        <td width="20"></td>
                    </tr>
                    <tr style="height: 20px;"></tr>
                    <tr>
                        <td width="20"></td>
                        <td>
                            <br>【'.$this->name.'】 Team<br>
                            <a href="'.Version_ios_DowUrl.'" rel="noopener" target="_blank">'.Version_ios_DowUrl.'</a><br>
                        </td>
                        <td width="20"></td>
                    </tr>
                    <tr style="height: 50px;"></tr>
                </tbody>
            </table>';
                break;
            case 'news': //公告
                $url = "javascript:;";
                $title = '哥伦布（GAC）2019年4月8号交易';
                $content = '<table cellpadding="0" cellspacing="0" style="border: 0; width: 100%; font-size: 16px; color: #1E2731;">
                <tbody>
                    <tr>
                        <td width="10"></td>
                        <td style="">
                            <p style="font-size:16px;">尊敬的用户：</p><br>
                            <p style="padding-left:30px;margin-top:10px;font-size:16px">哥伦布(GAC)定于2019年4月8号开始交易，在交易的过程中也存在一定的风险，请不要盲目购买和交易，开盘交易价格为0.8CNY。</p>
                        </td>
                        <td width="20"></td>
                    </tr>
                    <tr>
                        <td width="10"></td>
                        <td style="line-height: 40px;text-align: right;font-size:16px">
                            XRP NC 数字交易所<br>
                            2019年4月3日
                        </td>
                        <td width="20"></td>
                    </tr>
                </tbody>
            </table>';
            default:
                break;
        }
        return ['code'=>$code,'title'=>$title,'body'=>$content];
    }
}
