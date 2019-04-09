这是一个基于HTTP/2的APNs Provider, 应用多路复用, 实现高性能的苹果消息推送.

# 环境要求
使用 [city-fan](https://mirror.city-fan.org/ftp/contrib/yum-repo/?C=M;O=A) 的yum源对curl进行更新(为支持 HTTP/2 multiplexing, 版本需至少 7.43.0)

```sh
# centos 6
rpm -Uvh http://mirror.city-fan.org/ftp/contrib/yum-repo/city-fan.org-release-2-1.rhel6.noarch.rpm
# centos 7
rpm -Uvh http://mirror.city-fan.org/ftp/contrib/yum-repo/city-fan.org-release-2-1.rhel7.noarch.rpm

yum list libnghttp2  --disablerepo="*"  --enablerepo="epel"
yum list curl --disablerepo="*" --enablerepo="city*"

yum install libnghttp2  --disablerepo="*"  --enablerepo="epel" -y
yum install curl --disablerepo="*" --enablerepo="city*" -y
```



确认curl支持 HTTP/2:

```sh
# HTTP/1.1
curl -I https://nghttp2.org/

# HTTP/2, 不支持时会报错
curl --http2 -I https://nghttp2.org/
```

# 示例
- [简单的消息推送](https://github.com/youjiaxing/EasyAPNs/blob/HEAD/samples/simple_push.php)
- [从 redis 读取消息列表的推送服务](https://github.com/youjiaxing/EasyAPNs/blob/HEAD/samples/server.php)

# 简单使用
```php
$config = [
	'sslCert' => "APNs客户端证书绝对路径",
	'sslCertPwd' => "APNs客户端证书密码",
	'maxHostConn' => "与苹果推送服务器保持的连接数量, 默认1",
	'concurrentRequest' => "单个连接最大并发请求数",
];

// $logger 需实现Psr\Log\LoggerInterface
$logger = new Monolog\Monolog("apns");

$client = (new \EasyAPNs\Client($config['sslCert'], $config['sslCertPwd'], $config['maxHostConn']))
                ->setConcurrentMaxRequest($config['concurrentRequest'])
                ->setConcurrentMaxRequest(1)
                ->setLogger($logger)
;

$msg = (new \EasyAPNs\Message())
    ->setSandbox(true)
    ->setDeviceToken("devicetoken")
    ->setAlert("text body", "text title")
    ->setApnsTopic("***********") // bundle id
;
$client->add($msg);

$client->send();
```