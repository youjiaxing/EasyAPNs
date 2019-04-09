<?php
/**
 *
 * @author : 尤嘉兴
 * @version: 2019/4/9 17:32
 */
require "../vendor/autoload.php";
require "server_app.php";

define('DS', DIRECTORY_SEPARATOR);

$baseDir = __DIR__ . '/';
$app = new App([
    "apns" => [
        'sslCert' => __DIR__ . "/aps_dis.pem",
        'sslCertPwd' => "123456",
        'caCert' => __DIR__ . "/cacert.pem",
        'concurrentRequest' => 100,
        'maxHostConn' => 1,
    ],

    "logger" => [
        "stdout" => true,
        "stdout_level" => \Monolog\Logger::DEBUG,

        "rotate" => true,
        "rotate_max_files" => 7,
        "level" => \Monolog\Logger::DEBUG,
        "filename" => __DIR__ . "/logs/app.log",

        "memory_usage" => true,
        "memory_peak_usage" => true,
    ],

    "redis" => [
        "host" => "192.168.0.16",
        "port" => 6379,
        "db" => 0,
        "queue_key" => "ApnsListIOS",
    ]
]);

$app->run();