<?php
//支付系统机器人token
$tgzfToken = '1672492102:AAEa97SesyWwcBh-Rj85H9vsm-x2Dkvleo4';
//转发到支付系统的地址
$zfDomain = 'http://www/zhifu.com/';
$tgzhTransUrl = $zfDomain . "index/tg/notify";

$action = $_GET['action'] ? $_GET['action'] : 'setNotifyUrl';
switch ($action) {
    case 'setNotifyUrl':
        //设置回调地址
        setZfWebhookUrl();
        break;
    case 'notify':
        $json = file_get_contents("php://input");
        file_put_contents('./tg.log', $json, FILE_APPEND);
        //支付系统回调入口
        transTgmessageTozf($json);
        break;
}


/**
 *转发到支付系统
 * @param $json
 */
function transTgmessageTozf($json)
{
    global $tgzhTransUrl;
    httpRequest($tgzhTransUrl, 'post', $json);
}

/**
 * 设置支付系统回调地址
 */
function setZfWebhookUrl()
{
    global $tgzfToken;
    $notifyUrl = "https://" . $_SERVER['SERVER_NAME'] . '/tgzhongzhuan.php?action=notify';
    $url = 'https://api.telegram.org/bot' . $tgzfToken . '/setwebhook';
    $data = [
        'url' => $notifyUrl,
    ];
    $result = json_decode(httpRequest($url, 'POST', $data), true);
    print_r($result);
}


/**
 * curl  模拟请求
 * @param $url
 * @param string $method
 * @param null $postfields
 * @param array $headers
 * @param bool $debug
 * @return bool|string
 */
function httpRequest($url, $method = "GET", $postfields = null, $headers = array(), $debug = false)
{
    $method = strtoupper($method);
    $ci = curl_init();
    /* Curl settings */
    curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
    curl_setopt($ci, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.2; WOW64; rv:34.0) Gecko/20100101 Firefox/34.0");
    curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 60); /* 在发起连接前等待的时间，如果设置为0，则无限等待 */
    curl_setopt($ci, CURLOPT_TIMEOUT, 7); /* 设置cURL允许执行的最长秒数 */
    curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
    switch ($method) {
        case "POST":
            curl_setopt($ci, CURLOPT_POST, true);
            if (!empty($postfields)) {
                $tmpdatastr = is_array($postfields) ? http_build_query($postfields) : $postfields;
                curl_setopt($ci, CURLOPT_POSTFIELDS, $tmpdatastr);
            }
            break;
        default:
            curl_setopt($ci, CURLOPT_CUSTOMREQUEST, $method); /* //设置请求方式 */
            break;
    }
    $ssl = preg_match('/^https:\/\//i', $url) ? TRUE : FALSE;
    curl_setopt($ci, CURLOPT_URL, $url);
    if ($ssl) {
        curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
        curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, FALSE); // 不从证书中检查SSL加密算法是否存在
    }
    //curl_setopt($ci, CURLOPT_HEADER, true); /*启用时会将头文件的信息作为数据流输出*/
    curl_setopt($ci, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ci, CURLOPT_MAXREDIRS, 2);/*指定最多的HTTP重定向的数量，这个选项是和CURLOPT_FOLLOWLOCATION一起使用的*/
    curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ci, CURLINFO_HEADER_OUT, true);
    /*curl_setopt($ci, CURLOPT_COOKIE, $Cookiestr); * *COOKIE带过去** */
    $response = curl_exec($ci);

    $requestinfo = curl_getinfo($ci);
    $http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
    if ($debug) {
        echo "=====post data======\r\n";
        var_dump($postfields);
        echo "=====info===== \r\n";
        print_r($requestinfo);
        echo "=====response=====\r\n";
        print_r($response);
    }
    curl_close($ci);
    return $response;
    //return array($http_code, $response,$requestinfo);
}




