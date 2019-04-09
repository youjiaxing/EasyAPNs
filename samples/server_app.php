<?php
/**
 * 从Redis中读取待推送的消息列表, 该进程会常驻内存.
 * @author : YJX
 * @version: 2019/4/9 17:30
 */
class App implements \Psr\Log\LoggerInterface
{
    use \EasyAPNs\Traits\Logger;

    /**
     * @var \EasyAPNs\Client
     */
    protected $client;

    /**
     * @var Redis
     */
    protected $redis;

    /**
     * @var string
     */
    protected $redisQueue;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var \Psr\Log\LoggerInterface[]
     */
    protected $loggers = [];

    /**
     * @var bool
     */
    protected $running;

    public function __construct($config)
    {
        $this->config = $config;
        $this->logger = $this->getLogger("app");
    }

    protected function getConfig($key, $defValue = null)
    {
        $keys = explode('.', $key);
        $config = &$this->config;
//        dd($config, $keys, $defValue);
        foreach ($keys as $key) {
            if (!is_array($config) || !array_key_exists($key, $config)) {
                return $defValue;
            }
            $config = &$config[$key];
        }
        return $config;
    }

    /**
     * @param string $name
     *
     * @return \Psr\Log\LoggerInterface
     * @throws Exception
     */
    public function getLogger($name = "app")
    {
        if (!isset($this->loggers[$name])) {
            $logger = new Monolog\Logger($name);
            $config = array_merge([
                "stdout" => true,
                "stdout_level" => \Monolog\Logger::DEBUG,

                "rotate" => true,
                "rotate_max_files" => 7,
                "level" => \Monolog\Logger::DEBUG,
                "filename" => "/tmp/apns.log",

                "memory_usage" => false,
                "memory_peak_usage" => false,
            ], $this->getConfig("logger", []));

            if ($config['rotate']) {
                $logger->pushHandler(new \Monolog\Handler\RotatingFileHandler(
                    $config['filename'],
                    $config['rotate_max_files'],
                    $config['level']
                ));
            } else {
                $logger->pushHandler(new \Monolog\Handler\StreamHandler(
                    $config['filename'],
                    $config['level']
                ));
            }

            if ($config['stdout']) {
                $logger->pushHandler(new \Monolog\Handler\StreamHandler(
                    "php://stdout",
                    $config['level']
                ));
            }

            if ($config['memory_usage']) {
                $logger->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor());
            }

            if ($config['memory_peak_usage']) {
                $logger->pushProcessor(new \Monolog\Processor\MemoryPeakUsageProcessor());
            }

            $this->loggers[$name] = $logger;
        }

        return $this->loggers[$name];
    }

    protected function getClientConfig()
    {
        return $config = array_merge([
            'concurrentRequest' => 1000,
            'maxHostConn' => 1,
        ], $this->getConfig("apns", []));
    }

    /**
     * @return \EasyAPNs\Client
     */
    public function getClient(): \EasyAPNs\Client
    {
        if (is_null($this->client)) {
            $config = $this->getClientConfig();

            if (empty($config['sslCert']) || empty($config['sslCertPwd'])) {
                throw new \InvalidArgumentException("请传入必须的 apns 参数: 'sslCert' & 'sslCertPwd'");
            }

            $this->client = $client = (new \EasyAPNs\Client($config['sslCert'], $config['sslCertPwd'], $config['maxHostConn']))
                ->setConcurrentMaxRequest($config['concurrentRequest'])
//                ->setConcurrentMaxRequest(1)
                ->setLogger($this->getLogger("client"));

            if (!empty($config['caCert'])) {
                $client->setCaInfo($config['caCert']);
            }

            $client->setResponseCallback($this->defaultResponseCallback());
        }
        return $this->client;
    }

    /**
     * @return Closure
     */
    public function defaultResponseCallback()
    {
        return function (\EasyAPNs\Client $client, \EasyAPNs\Request $request, \EasyAPNs\Response $response) {
            if ($response->getHttpCode() != 200) {
//                $client->error("!! {$response->getHttpProtocol()} {$response->getHttpCode()} {$response->getReason()} {$response->getDeviceToken()}  {$response->getRequestUrl()}");
                $client->error("!! \"{$response->getCurlError()}\" {$response->getHttpProtocol()} {$response->getHttpCode()} {$response->getReason()} {$response->getDeviceToken()}");
            } else {
                $client->info("{$response->getHttpProtocol()} {$response->getHttpCode()} {$response->getHttpPhrase()} {$response->getDeviceToken()} {$response->getApnsId()}");
            }

            // 若请求等待队列长度为空, 则再次获取
            if ($client->getMsgsLength() === 0) {
                $this->loadMsgs(1);
            }
        };
    }

    public function setResponseCallback(\Closure $callback)
    {
        $this->getClient()->setResponseCallback($callback);
    }

    /**
     * @param \EasyAPNs\Client $client
     *
     * @return $this
     */
    public function setClient(\EasyAPNs\Client $client)
    {
        $this->client = $client;
        return $this;
    }

    /**
     * @param Redis $redis
     */
    public function setRedis($redis, $queueKey)
    {
        $this->redis = $redis;
        $this->redisQueue = $queueKey;
        return $this;
    }

    protected function getRedis()
    {
        if (is_null($this->redis)) {
            $config = array_merge([
                "host" => "127.0.0.1",
                "port" => 6379,
                "db" => 0,
                "queue_key" => "ApnsListIOS",
            ], $this->getConfig("redis"));

            $this->redis = new Redis();
            if (false === $this->redis->pconnect($config['host'], $config['port'])) {
                throw new \Exception("default redis 连接失败");
            }
            $this->redisQueue = $config['queue_key'];
        }
        return $this->redis;
    }

    /**
     * 注册信号处理
     */
    protected function registerSignalHandle()
    {
        foreach ([SIGINT, SIGTERM, SIGQUIT, SIGHUP] as $signal) {
            pcntl_signal($signal, [$this, "onSignal"]);
        }
    }

    /**
     * 信号处理
     *
     * @param $signo
     */
    protected function onSignal($signo)
    {
        $this->info("收到信号: $signo");
        switch ($signo) {
            // 终端会话结束信号
            case SIGHUP:
                // do nothing
                break;

            // 程序中断(interrupt)信号, 通常由 Ctrl + C 发出
            case SIGINT:
                // 程序终止(terminate)信号, shell命令的kill默认发出该信号
            case SIGTERM:
                // 程序退出信号, 与SIGINT类似, 但由 QUIT 字符(Ctrl + \)控制, 进程在因收到SIGQUIT退出时会产生core文件, 在这个意义上类似于一个程序错误信号。
            case SIGQUIT:
                $this->running = false;
                break;
        }
    }

    /**
     * @return false|int
     *              false 表示运行终止
     *              int 表示本轮处理的请求数
     */
    public function run()
    {
        $this->running = true;
        $client = $this->getClient();
        $this->info("apns provider start success");
        $last = time();
        while ($this->running) {
            if (time() - $last >= 5 * 60) {
                $this->debug("pong");
                $last = time();
            }

            // 从redis批量加载数据
            $loadMsg = $this->loadMsgs($client->getConcurrentMaxRequest());

            $client->send();

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            if ($this->running) {
                if ($loadMsg > 0) {
                    usleep(250000);
                } else {
                    usleep(500000);
                }
            }
        }
        $this->info("进程中止.");
    }

    /**
     * @return int 返回新增的推送消息数
     * @throws Exception
     */
    public function loadMsgs($maxCnt = 9999)
    {
        $redis = $this->getRedis();
        $cnt = 0;
        while ($cnt < $maxCnt && false !== ($msgStr = $redis->lPop($this->redisQueue))) {
            $msg = json_decode($msgStr, true);

            if ($msg['time'] + 24 * 3600 < time()) {
                $this->info("忽略一条过期消息", $msg);
                continue;
            }

            $cnt++;
            try {
                $msg = $this->createMsgFromRedisData($msg);
            } catch (\Exception $e) {
                $this->warning("组装消息时发生错误, 消息: $msgStr, 原因:" . $e->getMessage());
                $cnt--;
                continue;
            }
            $this->getClient()->addMsg($msg);
        }
        return $cnt;
    }

    /**
     *
     * @param $data
     *
     * @return \EasyAPNs\Message
     */
    public function createMsgFromRedisData($data)
    {
        if (!in_array($data['environment'], ["sandbox", "production"])) {
            throw new \InvalidArgumentException("参数 environment 错误: {$data['environment']}, 允许的值: 'sandbox' || 'production'");
        }

        if (empty($data['deviceToken'])) {
            throw new \InvalidArgumentException("参数 deviceToken 不能为空");
        }

        if (empty($data['message'])) {
            throw new \InvalidArgumentException("参数 message 不能为空");
        }

        if (empty($data['message'])) {
            throw new \InvalidArgumentException("参数 bundId 不能为空");
        }

        // {"taskId":"E8A77AAE8C20C5BDA58A","time":1554706092,"type":2,"environment":"sandbox","message":"full server push test","messageType":"alert","userId":2160001,"userName":"\u8d1d\u5229\u626c\u7279","bundId":"com.wanxiami.tanks.cn","deviceToken":"80a8f62c262f99a0fe1afe7a9cfbe93ba2d1ddb74a9a52c3bdd7cdce25d65ed5"}
        $msg = (new \EasyAPNs\Message())
            ->setSandbox($data['environment'] === "sandbox")
            ->setDeviceToken($data['deviceToken'])
//            ->setDeviceToken("5bf60353b124f2603806cc916a9aef1c3c8b31b324d40e21f053c4fbe2a1d32c")    // debug: dx
//            ->setDeviceToken("80a8f62c262f99a0fe1afe7a9cfbe93ba2d1ddb74a9a52c3bdd7cdce25d65ed5")    // debug: iphone x
            ->setAlert($data['message'])
            ->setApnsTopic($data['bundId'])//            ->setApnsCollapseId("dx")
        ;
        return $msg;
    }

    /**
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running;
    }
}
