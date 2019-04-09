<?php
/**
 *
 * @author : 尤嘉兴
 * @version: 2019/4/1 17:10
 */

namespace EasyAPNs;

class Request
{
    const APNS_DEVELOPMENT_SERVER = "https://api.development.push.apple.com:443";
    const APNS_PRODUCTION_SERVER = "https://api.push.apple.com:443";

    const APNS_PATH_SCHEMA = "/3/device/";

    const HEADER_APNS_ID = "apns-id";
    const HEADER_APNS_TOPIC = "apns-topic";
    const HEADER_APNS_EXPIRATION = "apns-expiration";
    const HEADER_APNS_PRIORITY = "apns-priority";
    const HEADER_APNS_COLLAPSE_ID = "apns-collapse-id";

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var string
     */
    protected $body;

    /**
     * @var array
     */
    protected $curlOptions;

    /**
     * @var string
     */
    protected $deviceToken;

    public function __construct($sandbox = false)
    {
        if (!defined("CURL_HTTP_VERSION_2")) {
            define("CURL_HTTP_VERSION_2", 3);
        }

        $this->baseUrl = $sandbox ? self::APNS_DEVELOPMENT_SERVER : self::APNS_PRODUCTION_SERVER;
    }

    /**
     * @return mixed
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * 获取实际请求的url地址
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->baseUrl . self::APNS_PATH_SCHEMA . $this->deviceToken;
    }

    /**
     * @param $key
     * @param $value
     *
     * @return $this
     */
    public function addHeader($key, $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * @param $headers
     *
     * @return Request
     */
    public function addHeaders($headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * 生成curl使用的http header数组格式
     * @return array
     */
    public function getDecoratedHeaders(): array
    {
        $resp = [];
        foreach ($this->headers as $k => $v) {
            $v = (string)$v;
            if ($v === '') {
                // cURL requires a special format for empty headers.
                $resp[] = $k . ";";
            } else {
                $resp[] = $k . ":" . $v;
            }

        }
        return $resp;
    }

    /**
     * @param array $headers
     *
     * @return Request
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param string $body
     *
     * @return Request
     */
    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCurlOptions()
    {
        $curlOptions = [
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2,
            CURLOPT_URL => $this->getUrl(),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $this->body,
            CURLOPT_RETURNTRANSFER => true,
            CURLINFO_HEADER_OUT => true,    // 可以通过 curl_getinfo($ch)['request_header'] 获取到http请求头
            CURLOPT_HEADER => true,         // 获取内容时同时返回http响应头
            CURLOPT_HTTPHEADER => $this->getDecoratedHeaders(),
            CURLOPT_PRIVATE => base64_encode(serialize($this)),
            CURLOPT_CONNECTTIMEOUT => 30,
        ];
        return $curlOptions;
    }

    /**
     * @param mixed $curlOptions
     */
    public function setCurlOptions($curlOptions): self
    {
        $this->curlOptions = $curlOptions;
        return $this;
    }

    /**
     * @param $k
     * @param $v
     *
     * @return Request
     */
    public function addOption($k, $v): self
    {
        $this->curlOptions[$k] = $v;
        return $this;
    }

    /**
     * @return string
     */
    public function getDeviceToken(): string
    {
        return $this->deviceToken;
    }

    /**
     * @param string $deviceToken
     *
     * @return Request
     */
    public function setDeviceToken(string $deviceToken): Request
    {
        $this->deviceToken = $deviceToken;
        return $this;
    }


}