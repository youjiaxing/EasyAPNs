<?php
/**
 * 一个简单的推送示例, 推送N条消息给APNs
 * @author : YJX
 * @version: 2019/4/9 17:07
 */
require "../vendor/autoload.php";
define("DS", DIRECTORY_SEPARATOR);

$deviceToken = "5bf60353b124f2603806cc916a9aef1c3c8b31b324d40e21f053c4fbe2a1d32c";

$logger = new Monolog\Logger(
    "EasyApns",
    [
        new \Monolog\Handler\StreamHandler("php://stdout", \Monolog\Logger::DEBUG, true),
    ]
);

$client = (new \EasyAPNs\Client(__DIR__ . "/aps_dis.pem", "123456"))
    ->setConcurrentMaxRequest(100)// 单个连接的并发请求数
//    ->setCaInfo("cacert.pem") // 生产环境中最好是验证一下服务端证书
    ->setCaInfo(false)
    ->setLogger($logger)
    ->setResponseCallback(function (\EasyAPNs\Client $client, \EasyAPNs\Request $request, \EasyAPNs\Response $response) {
        if ($response->getHttpCode() != 200) {
            $client->error("!! \"{$response->getCurlError()}\" {$response->getHttpProtocol()} {$response->getHttpCode()} {$response->getReason()} {$response->getDeviceToken()}");
        } else {
            $client->info("{$response->getHttpProtocol()} {$response->getHttpCode()} {$response->getHttpPhrase()} {$response->getDeviceToken()} {$response->getApnsId()}");
        }
    });


$msg = (new \EasyAPNs\Message())
    ->setSandbox(true)
    ->setDeviceToken($deviceToken)
    ->setAlert("text body", "text title")
    ->setApnsTopic("com.wanxiami.tanks.cn") // bundle id
;
$client->addMsg($msg);

$client->send();