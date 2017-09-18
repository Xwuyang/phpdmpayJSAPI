<?php
/*
 * time 2017年9月6日 10:27:24
 * by 五羊
 */

class Antfin{
    //应用ID
    public $appId;
    //私钥文件路径
    public $rsaPrivateKeyFilePath;

    //私钥值
    public $rsaPrivateKey;
    //网关
    public $gatewayUrl = "https://openapi.alipay.com/gateway.do";
    //返回数据格式
    public $format = "json";
    //api版本
    public $apiVersion = "1.0";

    // 表单提交字符集编码
    public $postCharset = "UTF-8";


    public $alipayPublicKey = null;

    public $alipayrsaPublicKey;


    public $debugInfo = false;

    private $fileCharset = "UTF-8";


    //签名类型
    public $signType = "RSA";

    protected $alipaySdkVersion = "alipay-sdk-php-20161101";

    protected $alipaySet;
    protected $member;
    protected $notify_url = "";
    protected $return_url = "";
    public function __construct($config=array()) {
        if(empty($config)){
            $antfin = M('setting')->where(array('model'=>'antfin'))->getField('setting');
            $this->alipaySet = unserialize($antfin);
        }else{
            $antfin = $config;
            $this->alipaySet = $antfin;
        }
        $this->gatewayUrl = $this->alipaySet['gatewayUrl']?:$this->gatewayUrl;
        $this->appId = $this->alipaySet['appId'];
        $this->rsaPrivateKey = $this->alipaySet['rsaPrivateKey'];
        $this->alipayrsaPublicKey = $this->alipaySet['alipayPublicKey'];

        $this->apiVersion = $this->alipaySet['apiversion']?:$this->apiVersion;
        $this->postCharset = $this->alipaySet['postcharset']?:$this->postCharset;
        $this->format = $this->alipaySet['format']?:$this->format;
        $this->notify_url = $this->alipaySet['notify_url'];
        $this->return_url = $this->alipaySet['return_url'];
    }
    /**
     * @return 支付
     */
    public function alipaytradecreate($data){
        $ss['content']['biz_content'] = json_encode($data);
        $ss['apiMethodName'] = "alipay.trade.create";
        $re = $this->execute($ss);
        return $re['alipay_trade_create_response'];
    }
    function json_encode_ex($value)
    {
        if (version_compare(PHP_VERSION,'5.4.0','<'))
        {
            $str = json_encode($value);
            $str = preg_replace_callback(
                "#\\\u([0-9a-f]{4})#i",
                function($matchs)
                {
                    return iconv('UCS-2BE', 'UTF-8', pack('H4', $matchs[1]));
                },
                $str
            );
            return $str;
        }
        else
        {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
    }
    /*提交接口
	 *
	 * @return info
	 */
    public function execute($request, $authToken = null, $appInfoAuthtoken = null){
        if ($this->checkEmpty($this->postCharset)) {
            $this->postCharset = "UTF-8";
        }
        $this->fileCharset = mb_detect_encoding($this->appId, "UTF-8,GBK");
        //		//  如果两者编码不一致，会出现签名验签或者乱码
        if (strcasecmp($this->fileCharset, $this->postCharset)) {
            // writeLog("本地文件字符集编码与表单提交编码不一致，请务必设置成一样，属性名分别为postCharset!");
            return array('err'=>-1,'msg'=>"文件编码：[" . $this->fileCharset . "] 与表单提交编码：[" . $this->postCharset . "]两者不一致!");
        }
        //api版本
        if (!$this->checkEmpty($request['apiVersion'])) {
            $iv = $request['apiVersion'];
        } else {
            $iv = $this->apiVersion;
        }
        //组装系统参数
        $sysParams["app_id"] = $this->appId;
        $sysParams["version"] = $iv;
        $sysParams["format"] = $this->format;
        $sysParams["sign_type"] = $this->signType;
        $sysParams["method"] = $request['apiMethodName'];
        $sysParams["timestamp"] = date("Y-m-d H:i:s");
        $sysParams["auth_token"] = $authToken;
        $sysParams["alipay_sdk"] = $this->alipaySdkVersion;
        $sysParams["terminal_type"] = $request['terminalType'];
        $sysParams["terminal_info"] = $request['terminalInfo'];
        $sysParams["prod_code"] = $request['prodCode'];
        $sysParams["notify_url"] = $request['notifyUrl'];
        $sysParams["charset"] = $this->postCharset;
        $sysParams["app_auth_token"] = $appInfoAuthtoken;
        //签名
        $apiParams = $request['content'];
        if(empty($apiParams)){
            $apiParams = array();
        }
        $sysParams["sign"] = $this->generateSign(array_merge($apiParams, $sysParams), $this->signType);
        //系统参数放入GET请求串
        $requestUrl = $this->gatewayUrl . "?";
        foreach ($sysParams as $sysParamKey => $sysParamValue) {
            $requestUrl .= "$sysParamKey=" . urlencode($this->characet($sysParamValue, $this->postCharset)) . "&";
        }
        $requestUrl = substr($requestUrl, 0, -1);
        file_put_contents('../b.txt',$requestUrl);
        file_put_contents('../c.txt',json_encode($apiParams));
        //发起HTTP请求
        try {
            $resp = $this->curl($requestUrl, $apiParams);
            $resp = iconv($this->postCharset, $this->fileCharset . "//IGNORE", $resp);
            $resp = json_decode($resp,true);
            return $resp;
        } catch (Exception $e) {
            //错误处理
            //$this->logCommunicationError($sysParams["method"], $requestUrl, "HTTP_ERROR_" . $e->getCode(), $e->getMessage());
            return false;
        }
    }
    /*
		页面提交执行方法
		@param：跳转类接口的request; $httpmethod 提交方式。两个值可选：post、get
		@return：构建好的、签名后的最终跳转URL（GET）或String形式的form（POST）
		auther:笙默
	*/
    function pageExecute($request,$httpmethod = "POST") {
        if ($this->checkEmpty($this->postCharset)) {
            $this->postCharset = "UTF-8";
        }
        $this->fileCharset = mb_detect_encoding($this->appId, "UTF-8,GBK");
        //		//  如果两者编码不一致，会出现签名验签或者乱码
        if (strcasecmp($this->fileCharset, $this->postCharset)) {
            // writeLog("本地文件字符集编码与表单提交编码不一致，请务必设置成一样，属性名分别为postCharset!");
            return array('err'=>-1,'msg'=>"文件编码：[" . $this->fileCharset . "] 与表单提交编码：[" . $this->postCharset . "]两者不一致!");
        }
        //api版本
        if (!$this->checkEmpty($request['apiVersion'])) {
            $iv = $request['apiVersion'];
        } else {
            $iv = $this->apiVersion;
        }
        //组装系统参数
        $sysParams["app_id"] = $this->appId;
        $sysParams["version"] = '1.0';
        $sysParams["format"] = $this->format;
        $sysParams["sign_type"] = $this->signType;
        $sysParams["method"] = $request['apiMethodName'];
        $sysParams["timestamp"] = date("Y-m-d H:i:s");
        $sysParams["alipay_sdk"] = $this->alipaySdkVersion;
        $sysParams["return_url"] = $this->return_url;
        $sysParams["terminal_type"] = $request['terminalType'];
        $sysParams["terminal_info"] = $request['terminalInfo'];
        $sysParams["prod_code"] = $request['prodCode'];
        $sysParams["notify_url"] = $this->notify_url;
        $sysParams["charset"] = $this->postCharset;
        //签名
        $apiParams = $request['content'];

        if(empty($apiParams)){
            $apiParams = array();
        }
        $sysParams = array_merge($apiParams, $sysParams);
        $sysParams["sign"] = $this->generateSign(array_merge($apiParams, $sysParams), $this->signType);
        return $this->buildRequestForm($sysParams);
    }
    protected function curl($url, $postFields = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $postBodyString = "";
        $encodeArray = Array();
        $postMultipart = false;


        if (is_array($postFields) && 0 < count($postFields)) {

            foreach ($postFields as $k => $v) {
                if ("@" != substr($v, 0, 1)) //判断是不是文件上传
                {

                    $postBodyString .= "$k=" . urlencode($this->characet($v, $this->postCharset)) . "&";
                    $encodeArray[$k] = $this->characet($v, $this->postCharset);
                } else //文件上传用multipart/form-data，否则用www-form-urlencoded
                {
                    $postMultipart = true;
                    $encodeArray[$k] = new \CURLFile(substr($v, 1));
                }

            }
            unset ($k, $v);
            curl_setopt($ch, CURLOPT_POST, true);
            if ($postMultipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encodeArray);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, substr($postBodyString, 0, -1));
            }
        }

        if ($postMultipart) {

            $headers = array('content-type: multipart/form-data;charset=' . $this->postCharset . ';boundary=' . $this->getMillisecond());
        } else {

            $headers = array('content-type: application/x-www-form-urlencoded;charset=' . $this->postCharset);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);




        $reponse = curl_exec($ch);

        if (curl_errno($ch)) {

            throw new Exception(curl_error($ch), 0);
        } else {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 !== $httpStatusCode) {
                throw new Exception($reponse, $httpStatusCode);
            }
        }

        curl_close($ch);
        return $reponse;
    }
    protected function getMillisecond() {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }
    /**
     * 校验$value是否非空
     *  if not set ,return true;
     *    if is null , return true;
     **/
    protected function checkEmpty($value) {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;

        return false;
    }
    protected function getSignContent($params) {
        ksort($params);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {

                // 转换成目标字符集
                $v = $this->characet($v, $this->postCharset);

                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }

        unset ($k, $v);
        return $stringToBeSigned;
    }
    public function generateSign($params, $signType = "RSA") {
        return $this->sign($this->getSignContent($params), $signType);
    }
    protected function sign($data, $signType = "RSA") {
        if($this->checkEmpty($this->rsaPrivateKeyFilePath)){
            $priKey=$this->rsaPrivateKey;
            $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
                wordwrap($priKey, 64, "\n", true) .
                "\n-----END RSA PRIVATE KEY-----";
        }else {
            $priKey = file_get_contents($this->rsaPrivateKeyFilePath);
            $res = openssl_get_privatekey($priKey);
        }
        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');
        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($data, $sign, $res);
        }
        if(!$this->checkEmpty($this->rsaPrivateKeyFilePath)){
            openssl_free_key($res);
        }
        $sign = base64_encode($sign);file_put_contents('base.txt',$sign);
        return $sign;
    }
    /**
     * 验签方法
     * @param $arr 验签支付宝返回的信息，使用支付宝公钥。
     * @return boolean
     */
    function check($arr){
        $result = $this->rsaCheckV1($arr, $this->alipayrsaPublicKey, $this->signtype);
        return $result;
    }
    /** rsaCheckV1 & rsaCheckV2
     *  验证签名
     *  在使用本方法前，必须初始化AopClient且传入公钥参数。
     *  公钥是否是读取字符串还是读取文件，是根据初始化传入的值判断的。
     **/
    function rsaCheckV1($params, $rsaPublicKeyFilePath,$signType='RSA') {
        $sign = $params['sign'];
        $params['sign_type'] = null;
        $params['sign'] = null;
        return $this->verify($this->getSignContent($params), $sign, $rsaPublicKeyFilePath,$signType);
    }
    function rsaCheckV2($params, $rsaPublicKeyFilePath, $signType='RSA') {
        $sign = $params['sign'];
        $params['sign'] = null;
        return $this->verify($this->getSignContent($params), $sign, $rsaPublicKeyFilePath, $signType);
    }
    function verify($data, $sign, $rsaPublicKeyFilePath, $signType = 'RSA') {
        $sign = str_replace(' ', "+", $sign);
        if($this->checkEmpty($this->alipayrsaPublicKeyFile)){
            $pubKey= $this->alipayrsaPublicKey;
            $res = "-----BEGIN PUBLIC KEY-----\n" .
                wordwrap($pubKey, 64, "\n", true) .
                "\n-----END PUBLIC KEY-----";
        }else {
            //读取公钥文件
            $pubKey = file_get_contents($rsaPublicKeyFilePath);
            //转换为openssl格式密钥
            $res = openssl_get_publickey($pubKey);
        }
        ($res) or die('支付宝RSA公钥错误。请检查公钥文件格式是否正确');
        //调用openssl内置方法验签，返回bool值
        if ("RSA2" == $signType) {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
        } else {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res);
        }
        if(!$this->checkEmpty($this->alipayPublicKey)) {
            //释放资源
            openssl_free_key($res);
        }

        return $result;
    }
    /**
     * 转换字符集编码
     * @param $data
     * @param $targetCharset
     * @return string
     */
    function characet($data, $targetCharset) {


        if (!empty($data)) {
            $fileType = $this->fileCharset;
            if (strcasecmp($fileType, $targetCharset) != 0) {

                $data = mb_convert_encoding($data, $targetCharset);
                //				$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }


        return $data;
    }
    /**
     * 处理form表单请求
     * @param $data
     * @param $targetCharset
     * @return string
     */
    function buildRequestForm($para_temp) {
        $sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='".$this->gatewayUrl."?charset=".trim($this->postCharset)."' method='POST'>";

        while (list ($key, $val) = each ($para_temp)) {
            if (false === $this->checkEmpty($val)) {
                //$val = $this->characet($val, $this->postCharset);
                $val = str_replace("'","&apos;",$val);
                //$val = str_replace("\"","&quot;",$val);
                $sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
            }
        }
        //submit按钮控件请不要含有name属性
        $sHtml = $sHtml."<input type='submit' value='ok' style='display:none;''></form>";
        $sHtml = $sHtml."<script>document.forms['alipaysubmit'].submit();</script>";
        return $sHtml;
    }
}