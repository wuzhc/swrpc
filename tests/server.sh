#!/usr/bin/env /opt/php74/bin/php
<?php

use Swrpc\Register\Consul;
use Swrpc\Server;

$basePath = dirname(dirname(__FILE__));
require_once $basePath . "/vendor/autoload.php";

$options = [
    'enable_coroutine' => true,
    'pid_file'         => __DIR__ . '/swrpc.pid',
];
$server = new Server('School_Module', getenv('RPC_SERVER_HOST'), getenv('RPC_SERVER_PORT'), $options);
$server->addRegister(new Consul())
    ->addService(\SwrpcTests\services\UserService::class)
    ->addService(\SwrpcTests\services\SchoolService::class)
    ->addService(\SwrpcTests\services\ClassService::class)
    ->start();