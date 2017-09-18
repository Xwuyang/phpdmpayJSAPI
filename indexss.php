<?php
include_once 'Antfin.class.php';
header("Content-type:text/html;charset=utf-8");
/*
 * 以下参数为沙箱测试环境下参数
 */
$config['appId'] = '******************';
$config['gatewayUrl'] = 'https://openapi.alipaydev.com/gateway.do';
$config['notify_url'] = $_SERVER['SERVER_NAME'].'/notify.php';
$config['return_url'] = $_SERVER['SERVER_NAME'].'/return.php';
//密钥 暂时只支持RSA加密，不支持RSA2     RSA签名验签工具生成密钥时请使用【PKCS1非JAVA】    密钥暂时不支持读取文件   有需要可以改类里的  sign签名方式
$config['alipayPublicKey'] = '***********************';
$config['rsaPrivateKey'] = '***********************************';
$antfin = new Antfin($config);//$config 可以传蚂蚁金服参数，不传默认查询数据库里的参数（数据库查询用的是thinkphp框架查询）
$data = array(//订单信息
    'subject'=>'测试订单',
    'out_trade_no'=>time().rand(1,200),
    'total_amount'=>0.01,
    'buyer_id'=>'2088102168961974',//用户的user_id
    /*'goods_detail'=>array(array(
        ''
    )),*/
);
$s = $antfin->alipaytradecreate($data);
 ?>
<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.0//EN" "http://www.wapforum.org/DTD/xhtml-mobile10.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>支付宝当面付2.0</title>
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body>
<h1>点击以下按钮唤起收银台支付</h1>
<a href="javascript:void(0)" class="tradeno">交易号唤起</a>
<input type="hidden" value="<?php echo $s['trade_no']; ?>" id="tno" />
<script src="https://as.alipayobjects.com/g/component/antbridge/1.1.1/antbridge.min.js"></script>
<script>
    function ready(callback) {
        // 如果jsbridge已经注入则直接调用
        if (window.AlipayJSBridge) {
            callback && callback();
        } else {
            // 如果没有注入则监听注入的事件
            document.addEventListener('AlipayJSBridgeReady', callback, false);
        }
    }
    ready(function(){
        document.querySelector('.tradeno').addEventListener('click', function(){
            var tno = document.getElementById('tno').value;
            AlipayJSBridge.call("tradePay",{
                tradeNO: tno
            }, function(result){
                alert(JSON.stringify(result));
            });
        });
    });
</script>
</body>
</html>
