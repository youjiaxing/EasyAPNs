<?php

namespace EasyAPNs;

use EasyAPNs\Traits\Logger;

/**
 *
 * @author : 尤嘉兴
 * @version: 2019/4/1 14:28
 */
class Client implements \Psr\Log\LoggerInterface
{
    use Logger;

    // ca根证书
    protected $caInfo;
    // 客户端证书, pem 格式
    protected $clientCert;
    // 客户端证书密码
    protected $clientCertPwd;

    protected $sandboxClientCert;
    protected $sandboxClientCertPwd;

    // 开发环境 apns url
//    protected $devUrl = "https://api.development.push.apple.com:443/3/device/";
    // 生产环境 apns url
//    protected $prodUrl = "https://api.push.apple.com:443/3/device/";

    // curl 默认选项
    protected $curlOptions = [];

    protected $mh;

    // 与同一host最大连接数
    protected $maxHostConn;
    // 单个连接的多路复用最大并发请求数
    protected $concurrentMaxRequest = 100;

    // 当前请求数
    protected $currentRequestCnt = 0;

    protected $totalRequestCnt = 0;

    // 待发送的消息队列
    protected $msgs = [];

    // bool
//    protected $running = false;

    /**
     * @var \Closure 单个请求的回调处理, 参数1: \EasyAPNs\Client, 参数2: \EasyAPNs\Response
     */
    protected $responseCallback;

    /**
     * @var \Closure 消息队列处理完毕后的回调处理, 参数1: \EasyAPNs\Client
     *               run() 中, 每一轮执行完毕后会调用一次, 若返回true则继续run(), 否则中止
     */
//    protected $completeCallback;


    public function __construct($sslCert, $sslCertPwd = null, $maxHostConn = 1)
    {
        if (!is_readable($sslCert)) {
            throw new \InvalidArgumentException("ssl cert $sslCert is unreadable!");
        }

        // 客户端证书
        $this->curlOptions[CURLOPT_SSLCERT] = $sslCert;
        if (!is_null($sslCertPwd)) {
            $this->curlOptions[CURLOPT_SSLCERTPASSWD] = $sslCertPwd;
        }

        $this->maxHostConn = $maxHostConn;
    }

    public function __destruct()
    {
        if (is_resource($this->mh)) {
            curl_multi_close($this->mh);
        }
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger(): \Psr\Log\LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @param \Psr\Log\LoggerInterface $logger
     *
     * @return $this
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getClientCert()
    {
        return $this->clientCert;
    }

    /**
     * @param mixed $clientCert
     *
     * @return Client
     */
    public function setClientCert($clientCert)
    {
        if (!is_readable($clientCert)) {
            throw new \Exception("client cert {$clientCert} unreadable!");
        }
        $this->clientCert = $clientCert;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getClientCertPwd()
    {
        return $this->clientCertPwd;
    }

    /**
     * @param mixed $clientCertPwd
     *
     * @return Client
     */
    public function setClientCertPwd($clientCertPwd)
    {
        $this->clientCertPwd = $clientCertPwd;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSandboxClientCert()
    {
        return $this->sandboxClientCert;
    }

    /**
     * @param mixed $sandboxClientCert
     *
     * @return Client
     */
    public function setSandboxClientCert($sandboxClientCert)
    {
        if (!is_readable($sandboxClientCert)) {
            throw new \Exception("debug client cert {$sandboxClientCert} unreadable!");
        }
        $this->sandboxClientCert = $sandboxClientCert;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSandboxClientCertPwd()
    {
        return $this->sandboxClientCertPwd;
    }

    /**
     * @param mixed $sandboxClientCertPwd
     *
     * @return Client
     */
    public function setSandboxClientCertPwd($sandboxClientCertPwd)
    {
        $this->sandboxClientCertPwd = $sandboxClientCertPwd;
        return $this;
    }


    /**
     * @return mixed
     */
    public function getCaInfo()
    {
        return $this->caInfo;
    }

    /**
     * CA根证书
     *
     * @param string|false $caInfo CA根证书的绝对路径
     *
     * @return $this
     */
    public function setCaInfo($caInfo)
    {
        $conf = &$this->curlOptions;
        if ($caInfo === false) {
            $conf[CURLOPT_SSL_VERIFYHOST] = false;
            $conf[CURLOPT_SSL_VERIFYPEER] = false;
            unset($conf[CURLOPT_CAINFO]);
            $this->caInfo = false;
            return $this;
        }

        if (!is_readable($caInfo)) {
            throw new \InvalidArgumentException("ca info $caInfo is unreadable!");
        }

        $conf[CURLOPT_CAINFO] = $caInfo;
        $conf[CURLOPT_SSL_VERIFYPEER] = true;
        $conf[CURLOPT_SSL_VERIFYHOST] = 2;
        $this->caInfo = $caInfo;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getResponseCallback()
    {
        return $this->responseCallback;
    }

    /**
     * @param \Closure|null $responseCallback
     *
     * @return $this
     */
    public function setResponseCallback($responseCallback)
    {
//        if ($responseCallback instanceof \Closure) {
//            $responseCallback->bindTo($this);
//        }
        $this->responseCallback = $responseCallback;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCompleteCallback()
    {
        return $this->completeCallback;
    }

    /**
     * @param \Closure|null $completeCallback
     *
     * @return Client
     */
//    public function setCompleteCallback($completeCallback)
//    {
////        if ($completeCallback instanceof \Closure) {
////            $completeCallback->bindTo($this);
////        }
//        $this->completeCallback = $completeCallback;
//        return $this;
//    }

    /**
     * @return int
     */
    public function getConcurrentMaxRequest(): int
    {
        return $this->concurrentMaxRequest;
    }

    /**
     * @param int $concurrentMaxRequest
     *
     * @return $this
     */
    public function setConcurrentMaxRequest(int $concurrentMaxRequest)
    {
        $this->concurrentMaxRequest = $concurrentMaxRequest;
        return $this;
    }

    /**
     * @return resource
     */
    public function getMultiHandler()
    {
        if (empty($this->mh)) {
            $this->mh = $mh = curl_multi_init();
            // 设置多路复用
            curl_multi_setopt($mh, CURLMOPT_PIPELINING, CURLPIPE_MULTIPLEX);
            // 设置与同一host最大连接数
            curl_multi_setopt($mh, CURLMOPT_MAX_HOST_CONNECTIONS, $this->maxHostConn);
        }
        return $this->mh;
    }

    public function setMultiHandler($mh)
    {
        if (!is_resource($mh)) {
            throw new \InvalidArgumentException("multi handler invalid!");
        }
        $this->mh = $mh;
    }

    public function addMsg($msg)
    {
        $this->msgs[] = $msg;
    }

    public function addMsgs($msgs)
    {
        foreach ($msgs as $notification) {
            $this->addMsg($notification);
        }
    }

    /**
     * 获取当前待处理的消息队列长度
     * @return int
     */
    public function getMsgsLength()
    {
        return count($this->msgs);
    }

    /**
     * @return array
     */
    public function send()
    {
        $responseCollection = [];

        $addRequest = $this->fillRequest();
        if ($addRequest == 0) {
            return [];
        }

        $handleRequest = 0;
        $mh = $this->getMultiHandler();

        do {
            curl_multi_exec($mh, $running);
            if (curl_multi_select($mh) === -1 && $running > 0) {
                usleep(250);
            }

            $addRequest = 0;

            // 查询批处理句柄是否单独的传输线程中有消息或信息返回
            // https://www.php.net/manual/zh/function.curl-multi-info-read.php
            while ($done = curl_multi_info_read($mh)) {
                $handle = $done['handle'];

                /* @var Request $request */
                $request = unserialize(base64_decode(curl_getinfo($handle, CURLINFO_PRIVATE)));     // 获取 handler 绑定的 token(在Request中绑定的)
                $deviceToken = $request->getDeviceToken();
                $requestUrl = curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
                $doneResult = $done['result'];

                $headers = "";
                $body = "";
                $resultParts = explode("\r\n\r\n", curl_multi_getcontent($handle), 2);
                if (isset($resultParts[0])) {
                    $headers = $resultParts[0];
                }
                if (isset($resultParts[1])) {
                    $body = $resultParts[1];
                }

//                $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

                $response = (new Response($headers, $body, $deviceToken))
                    ->setRequestUrl($requestUrl)
//                    ->setRequest($request)
                ;

                // 访问出错(请求可能还未到达APNs)
                if ($doneResult !== CURLE_OK) {
                    $errMsg = curl_error($handle);
                    $response->setCurlError($errMsg);
//                    $this->error("curl_multi_info_read error!", compact('doneResult', 'deviceToken', 'requestUrl', 'errMsg'));
                }

                // 若设置了请求回调, 则调用, 否则整合成数组并返回
                if ($this->responseCallback instanceof \Closure) {
                    call_user_func($this->responseCallback, $this, $request, $response);
                } else {
                    $responseCollection[] = $response;
                }

                // 移除用完的句柄
                curl_multi_remove_handle($mh, $handle);
                curl_close($handle);

                $this->currentRequestCnt--;
                $this->totalRequestCnt++;
                $handleRequest++;

                $addRequest = $this->fillRequest();
            }
        } while ($running > 0 || $addRequest > 0);

        $this->info("本次处理 $handleRequest 条请求");
        return $responseCollection;
    }

//    public function run()
//    {
//        if ($this->running) {
//            throw new \LogicException("已有正在运行的实例, 禁止递归调用.");
//        }
//
//        $this->running = true;
//        while ($this->running) {
//            $this->send();
//
//            if (call_user_func($this->completeCallback, $this) !== true) {
//                $this->running = false;
//            } else {
//                usleep(100 * 1000); //微秒
//            }
//        }
//    }

    protected function fillRequest()
    {
        if (empty($this->msgs)) {
            return 0;
        }

        $mh = $this->getMultiHandler();

        $addCnt = 0;
        while (!empty($this->msgs) && $this->currentRequestCnt < $this->concurrentMaxRequest) {
            $this->currentRequestCnt++;
            // 此处不考虑队列优先, 因为 array_shift() 的复杂度是 O(n), 而 array_pop() 时间复杂度是 O(1)
            $ch = $this->prepareHandler(array_pop($this->msgs));
            curl_multi_add_handle($mh, $ch);
            $addCnt++;
        }

        $this->debug("url请求队列新增 $addCnt 条, 当前已处理 $this->totalRequestCnt 条请求.");
        return $addCnt;
    }

    /**
     * @param Message $msg
     *
     * @return resource
     */
    public function prepareHandler(Message $msg)
    {
        $request = RequestFactory::createFromMessage($msg);

        $ch = curl_init();
        curl_setopt_array($ch, $request->getCurlOptions() + $this->curlOptions);
        return $ch;
    }

    /**
     * @return int
     */
    public function getTotalRequestCnt(): int
    {
        return $this->totalRequestCnt;
    }

    /**
     * @return array
     */
    public function getCurlOptions(): array
    {
        return $this->curlOptions;
    }


}