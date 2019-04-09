<?php
/**
 *
 * @author : 尤嘉兴
 * @version: 2019/4/1 17:10
 */

namespace EasyAPNs;

class Response
{
    protected $request;

    protected $curlError;

    protected $httpCode;
    protected $httpProtocol;
    protected $httpPhrase;

    protected $headers;
    protected $headerStr;
    protected $body;

    protected $apnsId;
    protected $deviceToken;
    protected $reason;
    protected $timestamp;

    protected $requestUrl;

    public function __construct($headers, $body, $deviceToken)
    {
        $this->deviceToken = $deviceToken;
        $this->body = $body;
        $this->headerStr = $headers;

        $this->parseHeaders($headers);
        $this->parseBody($body);
    }

    /**
     * 解析http头
     *
     * @param $headerStr
     */
    protected function parseHeaders($headerStr)
    {
        $data = explode("\r\n", trim($headerStr));
        $firstLine = array_shift($data);
        $parts = explode(' ', $firstLine, 3);
        $this->httpProtocol = trim($parts[0]);
        $this->httpCode = intval(trim($parts[1] ?? ""));
        $this->httpPhrase = isset($parts[2]) ? trim($parts[2]) : "";

        $headers = [];
        foreach ($data as $subData) {
            $parts = explode(':', $subData, 2);
            array_map('trim', $parts);
            if (!empty($parts[0])) {
                $headers[$parts[0]] = $parts[1] ?? "";
            }
        }
        $this->headers = $headers;

        if (isset($headers['apns-id'])) {
            $this->apnsId = $headers['apns-id'];
        }
    }

    /**
     * 解析消息体
     *
     * @param $body
     */
    protected function parseBody($body)
    {
        if (empty($body)) {
            return;
        }

        $data = json_decode($body, true);
        $this->reason = $data['reason'] ?? "";
        $this->timestamp = $data['timestamp'] ?? 0;
    }

    public function __toString()
    {
        $headers = implode("\n", $this->headers);
        return <<<EOF
Url: $this->requestUrl
$this->httpProtocol $this->httpCode $this->httpPhrase
$headers
DeviceToken: {$this->deviceToken}
$this->body
EOF;
    }

    /**
     * @return mixed
     */
    public function getRequestUrl()
    {
        return $this->requestUrl;
    }

    /**
     * @param mixed $requestUrl
     *
     * @return Response
     */
    public function setRequestUrl($requestUrl)
    {
        $this->requestUrl = $requestUrl;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHttpCode()
    {
        return $this->httpCode;
    }

    /**
     * @return mixed
     */
    public function getHttpProtocol()
    {
        return $this->httpProtocol;
    }

    /**
     * @return mixed
     */
    public function getHttpPhrase()
    {
        return $this->httpPhrase;
    }

    /**
     * @return mixed
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return mixed
     */
    public function getHeaderStr()
    {
        return $this->headerStr;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return mixed
     */
    public function getDeviceToken()
    {
        return $this->deviceToken;
    }

    /**
     * @return mixed
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * @return mixed
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @return mixed
     */
    public function getApnsId()
    {
        return $this->apnsId;
    }

    /**
     * @return mixed
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param mixed $request
     *
     * @return Response
     */
    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCurlError()
    {
        return $this->curlError;
    }

    /**
     * @param mixed $curlError
     *
     * @return Response
     */
    public function setCurlError($curlError)
    {
        $this->curlError = $curlError;
        return $this;
    }


}
