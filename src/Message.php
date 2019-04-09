<?php
/**
 *
 * @author : 尤嘉兴
 * @version: 2019/4/1 16:41
 */

namespace EasyAPNs;

class Message
{
    // https://developer.apple.com/library/archive/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/CreatingtheNotificationPayload.html#//apple_ref/doc/uid/TP40008194-CH10-SW1
    const PAYLOAD_MAXIMUM_SIZE = 4096;
    const APPLE_RESERVED_KEY = "aps";

    /**
     * 是否沙盒模式
     *
     * @var bool
     */
    protected $sandbox = false;

    /**
     * @var string
     */
    protected $apnsId;

    /**
     * @var string app bundle id
     */
    protected $apnsTopic;

    /**
     * @var
     */
    protected $apnsExpiration = 0;

    /**
     * @var int
     */
    protected $apnsPriority = 10;

    /**
     * @var string
     */
    protected $apnsCollapseId;

    /**
     * @var string
     */
    protected $deviceToken;

    /**
     * @var array
     */
    protected $customProperties = [];

    /**
     * @var string 显示给用户的alert消息正文
     */
    protected $body;

    /**
     * @var string 显示给用户的alert消息标题, 为空则默认显示app名
     */
    protected $title;

    /**
     * @var array alert 子健中的额外字段
     */
    protected $alertAdditional = [];

    /**
     * @var int app图标的的徽章数字
     */
    protected $badge;

    /**
     * @var string 播放声音文件名
     */
    protected $sound;

    /**
     * @var string notification’s type. This value corresponds to the value in the identifierproperty of one of your app’s registered categories.
     */
    protected $category;

    /**
     * @var int 1|0 配置后台更新通知?
     */
    protected $contentAvailable;

    /**
     * @var string app-specific identifier for grouping notifications.
     */
    protected $threadId;


    public function __construct($deviceToken = null)
    {
        if (!is_null($deviceToken)) {
            $this->setDeviceToken($deviceToken);
        }
    }

    public function __toString()
    {
        $payload = $this->getPayload();
        return <<<EOF
apns-id: $this->apnsId
apns-topic: $this->apnsTopic
apns-expiration: $this->apnsExpiration
apns-priority: $this->apnsPriority
apns-collapse-id: $this->apnsCollapseId

$payload
EOF;
    }

    /**
     * @return bool
     */
    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * @param bool $sandbox
     *
     * @return Message
     */
    public function setSandbox(bool $sandbox): Message
    {
        $this->sandbox = $sandbox;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPayloadArr()
    {
        $payload = [
            self::APPLE_RESERVED_KEY => []
        ];

        if (!empty($this->body)) {
            $payload[self::APPLE_RESERVED_KEY]['alert'] = [
                'body' => $this->body
            ];

            if (!empty($this->title)) {
                $payload[self::APPLE_RESERVED_KEY]['alert']['title'] = $this->title;
            }

            foreach ($this->alertAdditional as $key => $value) {
                $payload[self::APPLE_RESERVED_KEY]['alert'][$key] = $value;
            }
        }

        if (is_int($this->badge)) {
            $payload[self::APPLE_RESERVED_KEY]['badge'] = $this->badge;
        }

        if (!empty($this->sound)) {
            $payload[self::APPLE_RESERVED_KEY]['sound'] = $this->sound;
        }

        if (is_int($this->contentAvailable)) {
            $payload[self::APPLE_RESERVED_KEY]['content-available'] = $this->contentAvailable;
        }

        if (!empty($this->category)) {
            $payload[self::APPLE_RESERVED_KEY]['category'] = $this->category;
        }

        if (!empty($this->threadId)) {
            $payload[self::APPLE_RESERVED_KEY]['thread-id'] = $this->threadId;
        }

        if (is_array($this->customProperties)) {
            foreach ($this->customProperties as $key => $value) {
                if ($key === self::APPLE_RESERVED_KEY) {
                    continue;
                }
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    public function getPayload()
    {
        $payload = json_encode($this->getPayloadArr(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // 将 "aps" : [] 替换成 "aps" : {}
        $payload = str_replace(
            '"' . self::APPLE_RESERVED_KEY . '":[]',
            '"' . self::APPLE_RESERVED_KEY . '":{}',
            $payload
        );

        if (strlen($payload) > self::PAYLOAD_MAXIMUM_SIZE) {
            //TODO warning
        }

        return $payload;
    }

    /**
     * @return mixed
     */
    public function getApnsId()
    {
        return $this->apnsId;
    }

    /**
     * @param mixed $apnsId
     *
     * @return Message
     */
    public function setApnsId($apnsId)
    {
        $this->apnsId = $apnsId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getApnsTopic()
    {
        return $this->apnsTopic;
    }

    /**
     * @param mixed $apnsTopic
     *
     * @return Message
     */
    public function setApnsTopic($apnsTopic)
    {
        $this->apnsTopic = $apnsTopic;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getApnsExpiration()
    {
        return $this->apnsExpiration;
    }

    /**
     * @param mixed $apnsExpiration
     *
     * @return Message
     */
    public function setApnsExpiration($apnsExpiration)
    {
        $this->apnsExpiration = $apnsExpiration;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getApnsPriority()
    {
        return $this->apnsPriority;
    }

    /**
     * @param mixed $apnsPriority
     *
     * @return Message
     */
    public function setApnsPriority($apnsPriority)
    {
        $this->apnsPriority = $apnsPriority;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getApnsCollapseId()
    {
        return $this->apnsCollapseId;
    }

    /**
     * @param mixed $apnsCollapseId
     *
     * @return Message
     */
    public function setApnsCollapseId($apnsCollapseId)
    {
        $this->apnsCollapseId = $apnsCollapseId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDeviceToken()
    {
        return $this->deviceToken;
    }

    /**
     * @param mixed $deviceToken
     *
     * @return Message
     */
    public function setDeviceToken($deviceToken)
    {
        $this->deviceToken = $deviceToken;
        return $this;
    }

    /**
     * @return array
     */
    public function getCustomProperties(): array
    {
        return $this->customProperties;
    }

    /**
     * @param string $key
     * @param null   $defValue
     *
     * @return mixed|null
     */
    public function getCustomProperty($key, $defValue = null)
    {
        return array_key_exists($key, $this->customProperties) ? $this->customProperties[$key] : $defValue;
    }

    /**
     * @param array $customProperties
     *
     * @return self
     */
    public function setCustomProperty($key, $value): self
    {
        $this->customProperties[$key] = $value;
        return $this;
    }

    /**
     * @param string      $body
     * @param null|string $title
     * @param array       $additional
     *                         $additional 支持以下子键:
     *                         title-loc-key
     *                         title-loc-args
     *                         action-loc-key
     *                         loc-key
     *                         loc-args
     *                         launch-image
     *
     * @return Message
     */
    public function setAlert($body, $title = null, $additional = []): self
    {
        $this->body = $body;
        $this->title = $title;
        $this->alertAdditional = $additional;
        return $this;
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @param string $body
     *
     * @return Message
     */
    public function setBody(string $body): Message
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     *
     * @return Message
     */
    public function setTitle(string $title): Message
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return int
     */
    public function getBadge(): int
    {
        return $this->badge;
    }

    /**
     * @param int $badge
     *
     * @return Message
     */
    public function setBadge(int $badge): Message
    {
        $this->badge = $badge;
        return $this;
    }

    /**
     * @return string
     */
    public function getSound(): string
    {
        return $this->sound;
    }

    /**
     * @param string $sound
     *
     * @return Message
     */
    public function setSound(string $sound): Message
    {
        $this->sound = $sound;
        return $this;
    }

    /**
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * @param string $category
     *
     * @return Message
     */
    public function setCategory(string $category): Message
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return int
     */
    public function getContentAvailable(): int
    {
        return $this->contentAvailable;
    }

    /**
     * @param int $contentAvailable
     *
     * @return Message
     */
    public function setContentAvailable(int $contentAvailable): Message
    {
        $this->contentAvailable = $contentAvailable;
        return $this;
    }

    /**
     * @return string
     */
    public function getThreadId(): string
    {
        return $this->threadId;
    }

    /**
     * @param string $threadId
     *
     * @return Message
     */
    public function setThreadId(string $threadId): Message
    {
        $this->threadId = $threadId;
        return $this;
    }


}