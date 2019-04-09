<?php
/**
 *
 * @author : 尤嘉兴
 * @version: 2019/4/1 18:24
 */

namespace EasyAPNs;

class RequestFactory
{
    public static function createFromMessage(Message $msg)
    {
        $request = (new Request($msg->isSandbox()))
            ->setBody($msg->getPayload())
            ->setHeaders(static::prepareHeaders($msg))
            ->setDeviceToken($msg->getDeviceToken());

        return $request;
    }

    /**
     * 从 Message 中生成后续使用的Http header
     *
     * @param Message $msg
     */
    protected static function prepareHeaders(Message $msg)
    {
        $headers = [];
        if (!empty($apnsId = $msg->getApnsId())) {
            $headers[Request::HEADER_APNS_ID] = $apnsId;
        }

        if (!empty($apnsTopic = $msg->getApnsTopic())) {
            $headers[Request::HEADER_APNS_TOPIC] = $apnsTopic;
        }

        if (is_int($apnsPriority = $msg->getApnsPriority())) {
            $headers[Request::HEADER_APNS_PRIORITY] = $apnsPriority;
        }

        if (!empty($apnsCollapseId = $msg->getApnsCollapseId())) {
            $headers[Request::HEADER_APNS_COLLAPSE_ID] = $apnsCollapseId;
        }

        if (is_int($apnsExpiration = $msg->getApnsExpiration())) {
            $headers[Request::HEADER_APNS_EXPIRATION] = $apnsExpiration;
        }

        return $headers;
    }
}