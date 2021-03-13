<?php

namespace Swrpc\Register;


/**
 * 注册中心服务
 * Class Service
 *
 * @package Swrpc\Register
 * @author wuzhc 2021311 10:25:46
 */
class Service
{
    protected $host;
    protected $port;
    protected $weight;

    public function __construct($host, $port, $weight)
    {
        $this->host = $host;
        $this->port = $port;
        $this->weight = $weight;
    }

    public static function build($host, $port, $weight)
    {
        return new static($host, $port, $weight);
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getWeight()
    {
        return $this->weight;
    }

    public function toArray(): array
    {
        return [
            'host'   => $this->host,
            'port'   => $this->port,
            'weight' => $this->weight
        ];
    }
}