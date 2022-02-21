<?php
namespace encrypt;

/**
 * Class RsaController
 * @package Api\Controller
 * RSA算法类
 * 签名及密文编码：base64字符串/十六进制字符串/二进制字符串流
 * 填充方式: PKCS1Padding（加解密）/NOPadding（解密）
 *
 * Notice:Only accepts a single block. Block size is equal to the RSA key size!
 * 如密钥长度为1024 bit，则加密时数据需小于128字节，加上PKCS1Padding本身的11字节信息，所以明文需小于117字节
 */
class Rsa
{
    private $pubKey = null;
    public $priKey = null;
    private $noresource_pubKey = null;
    private $noresource_priKey = null;

    /**
     * 自定义错误处理
     */
    private function _error($msg)
    {
        die('RSA Error:' . $msg); //TODO
    }

    /**
     * 构造函数
     *
     * @param string 公钥（验签和加密时传入）
     * @param string 私钥（签名和解密时传入）
     */
    public function __construct()
    {
        //开启防抵赖
        //此处私钥需要自己生成
        $private_key = "MIIEoQIBAAKCAQEAy/0A557ut/BMDbaFgCSky5kftPIhSEyFs70MzDnozpa8BqxptVsVfhY1Phs9JkMiPIpo6MpPP1hxIeXQIYgoxRb4ReJN+q0xXXueDKNaW8/4CO4RxhSIjH1lqiHg7K/g/O7ACVQDXA0bJeMPCRfJz1l1bVNxPqDVu19glOiSQqRxU4I6mhX7anOR6UH+mEo2KNVdkk2NMn60eP65TFISUIQ6svjgkbTV8wDbFfJT29peS15cYlX7ns2uXhZonxM5BUokbhcOB6YQ7y003/rih2UY65vwTP+pgq5rCXd3WZRduLzTIm4F+TBIhDHcDoH4l/9xJcYmEtcZqUnmDNA3dQIBIwKCAQAF1AdXEytkV1KhTluV8msbwovgmTQmoxnDTotWSswjKODM/ZyeyBaOkuuUD2glqiWMs39XG7kfESfNxLzNwg/LHenWHGigwx6q5kZYIeyjiZlfV0JWHdgEA5UwvyOu/bX4mRtt+xYJ8b7ydDOhLJC8yAqrWifOluGJArmd2sJZrOA8kSQJ+LnFfCihvvyO/VfalZXdOftuQhx1TY4N1jHwvjIA+0TonuEe4wwF4DkWWTcdjDQn8Cc02V/qiHs+mB+Lx/m1VNh/QJYFRd86UmoinKs04JsG380sCNb8rzWU8dsRwl3tarZiFqlFqVHjsRlqyhaWWTWPXSLuSYgC43aPAoGBAOXPlBgWfhk1ITwrO7i/4vjz325wkrhFuIIvqtg38zBS7iRngLidmlGyEWFrn7RUsSxZZCI8kFWSw8+hOb5/s4c1Z6HkKrBRNrymZVejetQb3u2mEs4xjmSki2FOFnSOo7ZZaSrpbim4kr/XhHMbbna4NrF1VjEuoefKbapLQbgzAoGBAOM8FjUmlnw1WMeg6b5LxDpSgnHizXTZvE4/uXUwFg4TlUApHbwmYb0H5NTcqtF49qjnzxiv8EnSUcGsM9CQk2Gzi16/UsRQmtGQOlhDO4uwGsMK0/Aq2DfY7aUvbK9t37PxwNDDaOzXhGQmhzbR3QhGJ6kiPaJKeWtKzqNcdUm3AoGAE7K07B8vYT8Rei+XZ5trOekEhc8ihNLGrBK231VAuv/LRPLtxq5sUCU0sJQyQqgscYQBRMNc1CKFz/fgYMkeBEZn9+78WEF5uGYIr72OL3AabCQtf2NVWRVrCFcmfwTpdgep7bw8pH2I699F3fsJd+Pnbkvi0QP/P8Dk1BUUQv0CgYEAlVNfDPwZv1ZBp8GDqO/1+nC8HvQgnT6gUK2IgDWK1gzea/3DFT3LMxPQ46bz6L00YF3RPBvYawaNf0VGnv/r0n1M9R6kGptebHS4oGayGfdiCyRfY09a4t8CkR8qKiOhoiKGBYe5+rmC4ro7mQ2RP/OWZ9SsKNHawtljOCbQu2kCgYAn6SU8zoCFvQqMIDUfEDO9GGMcrYSE+WDNXY3u3DHEnqlcYosxUbWUC8S1dEODJS92BFzMWBFKJ/RqvIq6YdtbpiUxBfgZhFrlB0V4BEGO1IzGuaxITSXe8yLsMJGDX+mIJ5My1IftD0IPqsw025mBKUad/88XJzlLv0+sJdy3sg==";

        //公钥由双乾提供
        $public_key = "AAAAB3NzaC1yc2EAAAABIwAAAQEAy/0A557ut/BMDbaFgCSky5kftPIhSEyFs70MzDnozpa8BqxptVsVfhY1Phs9JkMiPIpo6MpPP1hxIeXQIYgoxRb4ReJN+q0xXXueDKNaW8/4CO4RxhSIjH1lqiHg7K/g/O7ACVQDXA0bJeMPCRfJz1l1bVNxPqDVu19glOiSQqRxU4I6mhX7anOR6UH+mEo2KNVdkk2NMn60eP65TFISUIQ6svjgkbTV8wDbFfJT29peS15cYlX7ns2uXhZonxM5BUokbhcOB6YQ7y003/rih2UY65vwTP+pgq5rCXd3WZRduLzTIm4F+TBIhDHcDoH4l/9xJcYmEtcZqUnmDNA3dQ==";

        $pemPriKey = chunk_split($private_key, 64, "\n");
        $pemPriKey = "-----BEGIN RSA PRIVATE KEY-----\n" . $pemPriKey . "-----END RSA PRIVATE KEY-----\n";

        $pemPubKey = chunk_split($public_key, 64, "\n");
        $pemPubKey = "-----BEGIN PUBLIC KEY-----\n" . $pemPubKey . "-----END PUBLIC KEY-----\n";

        //$this->priKey = openssl_get_privatekey($pemPriKey);
        //$this->pubKey = openssl_get_publickey($pemPubKey);

        $this->priKey = $pemPriKey;
        $this->pubKey = $pemPubKey;
    }


    /**
     * 生成签名
     *
     * @param string 签名材料
     * @param string 签名编码（base64/hex/bin）
     * @return 签名值
     */
    public function sign($data, $code = 'base64')
    {
        $ret = false;
        if (openssl_sign($data, $ret, $this->priKey)) {
            $ret = $this->_encode($ret, $code);
        }
        return $ret;
    }

    /**
     * 验证签名
     *
     * @param string 签名材料
     * @param string 签名值
     * @param string 签名编码（base64/hex/bin）
     * @return bool
     */
    public function verify($data, $sign, $code = 'base64')
    {
        $ret = false;
        $sign = $this->_decode($sign, $code);

        if ($sign !== false) {
            switch (openssl_verify($data, $sign, $this->pubKey)) {
                case 1:
                    $ret = true;
                    break;
                case 0:
                case -1:
                default:
                    $ret = false;
            }
        }

        return $ret;
    }

    /**
     * 加密
     *
     * @param string 明文
     * @param string 密文编码（base64/hex/bin）
     * @param int 填充方式（貌似php有bug，所以目前仅支持OPENSSL_PKCS1_PADDING）
     * @return string 密文
     */
    public function encrypt($data, $code = 'base64', $padding = OPENSSL_PKCS1_PADDING)
    {
        $ret = false;
        if (!$this->_checkPadding($padding, 'en')) $this->_error('padding error');
        if (openssl_public_encrypt($data, $result, $this->pubKey, $padding)) {
            $ret = $this->_encode($result, $code);
        }
        return $ret;
    }

    /**
     * 解密
     *
     * @param string 密文
     * @param string 密文编码（base64/hex/bin）
     * @param int 填充方式（OPENSSL_PKCS1_PADDING / OPENSSL_NO_PADDING）
     * @param bool 是否翻转明文（When passing Microsoft CryptoAPI-generated RSA cyphertext, revert the bytes in the block）
     * @return string 明文
     */
    public function decrypt($data, $code = 'base64', $padding = OPENSSL_PKCS1_PADDING, $rev = false)
    {
        $ret = false;
        $data = $this->_decode($data, $code);
        if (!$this->_checkPadding($padding, 'de')) $this->_error('padding error');
        if ($data !== false) {
            if (openssl_private_decrypt($data, $result, $this->priKey, $padding)) {
                $ret = $rev ? rtrim(strrev($result), "\0") : '' . $result;
            }
        }
        return $ret;
    }

    /**
     * 生成密钥
     */
    public function GenerateKey($dn = NULL, $config = NULL, $passphrase = NULL)
    {
        if (!$dn) {
            $dn = array(
                "countryName" => "CN",
                "stateOrProvinceName" => "JIANGSU",
                "localityName" => "Suzhou",
                "organizationName" => "95epay",
                "organizationalUnitName" => "Moneymoremore",
                "commonName" => "www.moneymoremore.com",
                "emailAddress" => "csreason@95epay.com"
            );
        }
        /*
        if (!$config)
		{
			$config = array(
			"digest_alg" => "sha1",
			"private_key_bits" => 1024,
			"private_key_type" => OPENSSL_KEYTYPE_RSA,
			"encrypt_key" => false
			);
		}
		*/

        $privkey = openssl_pkey_new();
        echo "private key:";
        echo "<br>";
        if ($passphrase != NULL) {
            openssl_pkey_export($privkey, $privatekey, $passphrase);
        } else {
            openssl_pkey_export($privkey, $privatekey);
        }
        echo $privatekey;
        echo "<br><br>";

        /*
        $csr = openssl_csr_new($dn, $privkey);
        $sscert = openssl_csr_sign($csr, null, $privkey, 65535);
        echo "CSR:";
        echo "<br>";
        openssl_csr_export($csr, $csrout);

        echo "Certificate: public key";
        echo "<br>";
        openssl_x509_export($sscert, $publickey);
        */
        $publickey = openssl_pkey_get_details($privkey);
        $publickey = $publickey["key"];

        echo "public key:";
        echo "<br>";
        echo $publickey;

        $this->noresource_pubKey = $publickey;
        $this->noresource_priKey = $privatekey;
    }


    // 私有方法

    /**
     * 检测填充类型
     * 加密只支持PKCS1_PADDING
     * 解密支持PKCS1_PADDING和NO_PADDING
     *
     * @param int 填充模式
     * @param string 加密en/解密de
     * @return bool
     */
    private function _checkPadding($padding, $type)
    {
        if ($type == 'en') {
            switch ($padding) {
                case OPENSSL_PKCS1_PADDING:
                    $ret = true;
                    break;
                default:
                    $ret = false;
            }
        } else {
            switch ($padding) {
                case OPENSSL_PKCS1_PADDING:
                case OPENSSL_NO_PADDING:
                    $ret = true;
                    break;
                default:
                    $ret = false;
            }
        }
        return $ret;
    }

    private function _encode($data, $code)
    {
        switch (strtolower($code)) {
            case 'base64':
                $data = base64_encode('' . $data);
                break;
            case 'hex':
                $data = bin2hex($data);
                break;
            case 'bin':
            default:
        }
        return $data;
    }

    private function _decode($data, $code)
    {
        switch (strtolower($code)) {
            case 'base64':
                $data = base64_decode($data);
                break;
            case 'hex':
                $data = $this->_hex2bin($data);
                break;
            case 'bin':
            default:
        }
        return $data;
    }

    private function _getPublicKey($file)
    {
        $key_content = $this->_readFile($file);
        if ($key_content) {
            $this->pubKey = openssl_get_publickey($key_content);
        }
    }

    private function _getPrivateKey($file)
    {
        $key_content = $this->_readFile($file);
        if ($key_content) {
            $this->priKey = openssl_get_privatekey($key_content);
        }
    }

    private function _readFile($file)
    {
        $ret = false;
        if (!file_exists($file)) {
            $this->_error("The file {$file} is not exists");
        } else {
            $ret = file_get_contents($file);
        }
        return $ret;
    }


    private function _hex2bin($hex = false)
    {
        $ret = $hex !== false && preg_match('/^[0-9a-fA-F]+$/i', $hex) ? pack("H*", $hex) : false;
        return $ret;
    }

    /**
     * 参数拼接
     * @param array $sign_params
     * @return string
     */
    public function joinMapValue($sign_params = [])
    {
        $sign_str = "";
        ksort($sign_params);
        foreach ($sign_params as $key => $val) {
            $sign_str .= sprintf("%s=%s&", $key, $val);
        }
        return substr($sign_str, 0, -1);
    }
}