<?php
/*
第一步通过composer下载websocket库
命令行执行 composer require textalk/websocket
直接运行本程序 php ws.php
*/

require 'vendor/autoload.php';

use WebSocket\Client;

//for ($i=0;$i<=49;$i++){
//    coloredText($i,$i);
//}
//exit();


//密钥 可以在商户后台重置密钥得到，重置后之前密钥会失效
$Key = <<<EOD
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDg2Dgxgk2JTck8sJ5k/RlIbMCU
lVD6UFDhLy6/y3+NBSKWClvBvLQBxcB542X+qVXvuI9PcHhs7MYibBO2BjUc/s/w
O98wr0aIHkSg+z65Is3NTD1ofSMZcfuvKebakEJiO+stGSmqTKnWfFiJofeoAFXj
HlUvO+ymrksv/IraxwIDAQAB
-----END PUBLIC KEY-----
EOD;

//uuid向管理员索取
$uuid = '4a748c8a-2f94-468a-9535-f00a73a69a28';

//配置文件
$config = [
    'url' => 'wss://best4pay.com/ws/client/',
    'timeout' => 0xFFFFFFFF, //设置超时时间 单位为秒 0xFFFFFFFF为不超时
    'filter' => ['text', 'close'], //消息返回类型 Text,Binary,Ping,Pong,Close
    'return_obj' => true, //消息以对象方式返回
    'timestamp' => time(), //时间戳
    'Key' => $Key, //密钥 可以在商户后台重置后得到，重置后的密钥之前的会失效
    'uuid' => $uuid, //uuid向管理员索取
    'usertype' => 'client', //商户端 client  卡商端 cardmerch  银行设备端 bank'
    'reconnect' => true, //断开后是否重连
];


echo GetUrl($config);

$client = new Client(GetUrl($config), $config); //创建ws客户端

//-------------------------这里是重点-------------------------------
//发送消息方法

$json = <<<EOD
{
    "type":"deposit_request",
    "nonce_hash":"123123123123123456789010",
    "data":{
        "client_order_no":"123456789012345678",
        "amount":"10002",
        "data_type":"json",
        "from_account_name":"张三",
        "from_bank_name":"中国数字银行",
        "from_bank_account":"100",
        "remark":"备注"
    }
}
EOD;


$client->send($json); //连接成功后发送的内容

//收到消息方法  这里处理收到的消息
function Receive($message)
{
    global $client;
    if (!empty($message)) {
        coloredText($message, 32); //打印返回的内容
        echo PHP_EOL;
        $json = json_decode($message);
        $type = $json->type;
        switch ($type) {
            case "deposit_request_reply": //发送请求代付订单后的返回结果
                if ($json->result=="success"){
                    coloredText($json->msg, 13);
                    coloredText("姓名：", 20,null);
                    coloredText($json->data->to_account_name, 10); //姓名
                    coloredText("开户行：", 20,null);
                    coloredText($json->data->to_bank_name, 10); //开户行
                    coloredText("卡号：", 20,null);
                    coloredText($json->data->to_bank_account, 10);//卡号
                    coloredText("超时时间(分钟)：", 20,null);
                    coloredText($json->data->time_limit, 10); //支付超时时间
                    coloredText("支付金额：", 20,null);
                    coloredText($json->data->amount, 10); //需要支付金额
                    coloredText("支付卡姓名：", 20,null);
                    coloredText($json->data->from_account_name, 10); //支付卡姓名
                    coloredText("商户订单号：", 20,null);
                    coloredText($json->data->client_order_no, 10); //商户订单号
                    coloredText("接口类型：", 20,null);
                    coloredText($json->data->data_type, 10); //接口类型

                }else{
                    coloredText('错误：', 9,null);
                    coloredText($json->msg, 15);
                }
                break;
            case "deposit_completed": //代收支付成功回调

                coloredText("商户订单号：", 20,null);
                coloredText($json->data->client_order_no, 10); //商户订单号
                coloredText("成功支付金额：", 20,null);
                coloredText($json->data->payment_amount, 10); //成功支付金额
                coloredText("确认支付时间：", 20,null);
                coloredText($json->data->payment_time, 10);//确认支付时间
                coloredText("支付状态：", 20,null);
                coloredText($json->data->payment_status, 10);//卡号


                $json =[
                        'type' => 'deposit_completed_reply',
                        'nonce_hash' => $json->nonce_hash,
                        'data' => [
                            'client_order_no' => $json->data->client_order_no,
                        ],
                        'result' => 'success',
                ];

                $client->send(json_encode($json));
                //这里要发送确认消息给系统 deposit_completed_reply
                break;
            case "withdraw_request_reply": //发送请求代付订单下单后的返回结果
                break;
            case "withdraw_completed": //代付支付成功回调
                //这里要发送确认消息给系统 withdraw_completed_reply
                break;
            case "order_status_reply": //发送请求查询订单后的返回的结果
                break;
            case "user_order_paid_reply": //发送付款后提醒查账后的返回结果
                break;
            case "order_status_to_client_reply": //发送领取订单状态后的返回结果
                break;
            case "order_cancel_reply": //发送取消订单后的返回结果
                break;
            case "2":
                break;
            default:
        }
    }
}

//----------------------------------------------------------------



//----------------------下面所有代码可以忽略--------------------------
//下面循环得到消息队列
while (true) { //死循环
    try {
        $message = $client->receive(); //收到的消息对象
        $type = $message->getOpcode(); //消息对象中的消息类型

        switch ($type) //判断消息类型
        {
            case "close": //如果收到close消息执行下面动作
                if (!$config['reconnect']) { //如果 $reconnect = true 那么会执行重新连接而不退出程序
                    coloredText("收到服务器断开指令,程序退出", 11);
                    exit(); //收到服务器断开指令结束程序
                } else {
                    coloredText("收到服务器断开指令,尝试重新连接", 11);
                    //$client->send('{}'); //重连后要发送的内容
                }
                break;
            case "text": //如果收到text消息执行下面动作
                coloredText("收到文本消息", 11);
                Receive($message->getContent());
                break;
            default:
                coloredText("收到一条未知类型消息", 11);
        }
    } catch (\WebSocket\ConnectionException $e) {
        coloredText("ws错误:" + $e, 9);
    }
}


//加密
function Encrypt($config)
{

    // 加载密钥
    $publicKeyResource = openssl_get_publickey($config['Key']);
    // 执行加密
    if (openssl_public_encrypt($config['uuid'] . "," . $config['timestamp'], $encryptedData, $publicKeyResource)) {
        // 加密成功
        $encryptedData = base64_encode($encryptedData);
        return $encryptedData;
    } else {
        // 加密失败
        coloredText("加密失败，程序已退出", 9);
        exit(); //加密失败退出程序
    }

}

//替换url无法处理字符串
function url_safe_base64_encode($str)
{
    $str = str_replace("+", "-", $str);
    $str = str_replace('/', '_', $str);
    $str = str_replace('=', '', $str);
    return $str;
}

//获得正确访问url
function GetUrl($config)
{
    $token = url_safe_base64_encode(Encrypt($config)); //得到token

    return $config['url'] . $config['uuid'] . "/?token=" . $token . "&usertype=" . $config['usertype'];
}

//输出带有颜色的文本
//0: 黑色 1: 深红色 2: 深绿色 3: 棕色 4: 深蓝色 5: 深品红色 6: 深青色 7: 浅灰色 8: 深灰色 9: 红色 10: 绿色
//11: 黄色 12: 蓝色 13: 品红色 14: 青色 15: 白色 16: 亮黑色 17: 亮红色 18: 亮绿色 19: 亮黄色 20: 亮蓝色
//21: 亮品红色 22: 亮青色 23: 亮白色 24: 深灰蓝色 25: 深灰品红色 26: 深灰青色 27: 深灰黄色 28: 深灰绿色 29: 深灰亮红色 30: 深灰亮青色
//31: 红色 32: 鲜绿色 33: 黄色 34: 蓝色 35: 紫红色 36: 青色 37: 浅灰色 38: 原色 39: 默认颜色 40: 黑色背景
//41: 红色背景 42: 绿色背景 43: 黄色背景 44: 蓝色背景 45: 品红色背景 46: 青色背景 47: 白色背景 48: 另一种黑色背景 49: 默认背景颜色
function coloredText($text, $color,$NewLine=PHP_EOL)
{
    // 转换为 256 色的 ANSI 转义序列
    $ansiColor = "\033[38;5;" . $color . "m";

    // 输出带有颜色的文本
    echo $ansiColor . $text . "\033[0m" . $NewLine;
}

//web服务器，还没写好
function web(){
    $host = 'localhost';
    $port = 8000;

// 创建服务器套接字
    $serverSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_set_option($serverSocket, SOL_SOCKET, SO_REUSEADDR, 1);

// 绑定服务器套接字到指定主机和端口
    socket_bind($serverSocket, $host, $port);

// 监听连接
    socket_listen($serverSocket);

    echo "Server running at http://$host:$port" . PHP_EOL;

    while (true) {
        // 接受客户端连接
        $clientSocket = socket_accept($serverSocket);

        // 读取客户端请求
        $request = socket_read($clientSocket, 4096);

        print_r($request);

        // 构建响应
        $response = "HTTP/1.1 200 OK\r\n";
        $response .= "Content-Type: text/html\r\n\r\n";
        $response .= "<h1>Hello, World!</h1>";
        $response .= "<p>This is a simple HTML page.</p>";

        // 发送响应给客户端
        socket_write($clientSocket, $response, strlen($response));

        // 关闭客户端套接字
        socket_close($clientSocket);
    }

// 关闭服务器套接字
    socket_close($serverSocket);
}


//----------------------------------------------------------------
