<?php

namespace Swrpc;


use Swoole\Client as SwClient;
use Swrpc\Exceptions\RpcException;
use Swrpc\Packer\PackerInterface;
use Swrpc\Packer\SerializeLengthPacker;
use Swrpc\Register\RegisterInterface;
use Swrpc\Register\Service;
use Swrpc\Request\Request;

/**
 * Class Client
 *
 * @package Swrpc
 * @author wuzhc 202139 11:36:25
 */
class Client
{
    protected $services = [];
    protected $connects = [];


    const STRATEGY_RANDOM = 1;
    const STRATEGY_WEIGHT = 2;

    protected $mode;
    protected $timeout = 3;
    protected array $options;
    protected string $module;
    protected int $strategy;
    protected ?RegisterInterface $register = null;
    protected ?PackerInterface $packer = null;

    protected array $defaultOptions
        = [
            'open_length_check'     => true,
            'package_length_type'   => 'N',
            'package_length_offset' => 0, //第N个字节是包长度的值
            'package_body_offset'   => 4, //第几个字节开始计算长度
            'package_max_length'    => 81920, //协议最大长度
        ];

    /**
     * Client constructor.
     *
     * @param string $module
     * @param array  $services
     * @param int    $mode
     * @param int    $timeout
     * @param array  $options
     */
    public function __construct(string $module, array $services, $mode = SWOOLE_SOCK_TCP, $timeout = 3, $options = [])
    {
        $this->module = $module;
        $this->services = $services;
        $this->mode = $mode;
        $this->timeout = $timeout;
        if (empty($options)) {
            $options = $this->defaultOptions;
        }
        $this->options = $options;

    }

    /**
     * @param string $module
     * @param string $host
     * @param int    $port
     * @param int    $mode
     * @param array  $options
     * @return Client
     * @author wuzhc 2021313 18:31:17
     */
    public static function create(
        string $module,
        string $host,
        int $port,
        $mode = SWOOLE_SOCK_TCP,
        $timeout = 3,
        $options = []
    ): Client {
        $service = Service::build($host, $port, 1);
        return new static($module, [$service], $mode, $timeout, $options);
    }

    /**
     * @param string            $module
     * @param RegisterInterface $register
     * @param int               $strategy
     * @param int               $mode
     * @param int               $timeout
     * @param array             $options
     * @return Client
     * @author wuzhc 2021313 18:31:22
     */
    public static function createBalancer(
        string $module,
        RegisterInterface $register,
        $strategy = self::STRATEGY_RANDOM,
        $mode = SWOOLE_SOCK_TCP,
        $timeout = 3,
        $options = []
    ): Client {
        $client = new static($module, [], $mode, $timeout, $options);
        $client->strategy = $strategy;
        $client->addRegister($register);
        return $client;
    }

    /**
     * @param RegisterInterface $register
     * @return $this
     * @author wuzhc 2021313 18:27:20
     */
    public function addRegister(RegisterInterface $register): Client
    {
        $this->register = $register;
        $this->services = $this->register->getServices($this->module);
        return $this;
    }

    /**
     * @param PackerInterface $packer
     * @return $this
     * @author wuzhc 2021313 18:27:24
     */
    public function addPacker(PackerInterface $packer): Client
    {
        $this->packer = $packer;
        return $this;
    }

    /**
     * @return SwClient
     * @throws RpcException
     * @author wuzhc 2021313 18:23:37
     */
    public function connect(): SwClient
    {
        $n = count($this->services);
        if ($n == 0) {
            throw new RpcException('No services available');
        }

        /** @var Service $service */
        if ($n == 1) { //单个服务节点
            $service = $this->services[0];
            $key = $service->getHost() . '_' . $service->getPort();
        } else { //多个服务节点
            $key = $this->getConnectKey();
        }

        if (isset($this->connects[$key]) && $this->connects[$key]->isConnected()) {
            return $this->connects[$key];
        }
        $client = new SwClient($this->mode ?: SWOOLE_SOCK_TCP);
        if (!$client->connect($service->getHost(), $service->getPort(), $this->timeout ?? 3)) {
            throw new RpcException("connect failed. Error: {$client->errCode}");
        }
        $client->set($this->options);
        $this->connects[$key] = $client;
        return $this->connects[$key];
    }

    /**
     * 发送请求
     *
     * @param Request $request
     * @return mixed
     * @throws RpcException
     * @author wuzhc 202139 13:35:25
     */
    public function send(Request $request)
    {
        /** @var \Swoole\Client $conn */
        $conn = $this->connect();

        if (!$this->packer) {
            $this->packer = new SerializeLengthPacker([
                'package_length_type' => $options['package_length_type'] ?? 'N',
                'package_body_offset' => $options['package_body_offset'] ?? 4,
            ]);
        }

        $request->setModule($this->module);
        $conn->send($this->packer->pack($request));

        /** @var Response $response */
        $response = @unserialize($conn->recv());
        if (!($response instanceof Response)) {
            throw new RpcException('The server return type is not a Swrpc\Response');
        }
        if ($response->code == Response::RES_ERROR) {
            throw new RpcException($response->msg);
        }

        return $response->data['result'] ?? null;
    }

    /**
     * @return string
     * @author wuzhc 2021313 18:20:38
     */
    public function getConnectKey(): string
    {
        /** @var Service $service */
        if ($this->strategy == self::STRATEGY_RANDOM) {
            $service = array_rand($this->services);
            return $service->getHost() . '_' . $service->getPort();
        } else {
            /** @var Service $service */
            foreach ($this->services as $service) {
                $totalWeight += $service->getWeight();
                $sort[] = $service->getWeight();
                $serviceArr[] = $service->toArray();
            }

            array_multisort($serviceArr, SORT_DESC, $sort);

            $start = 0;
            $rand = rand(1, $totalWeight);
            foreach ($serviceArr as $service) {
                if ($start + $service['weight'] >= $rand) {
                    return $service['host'] . '_' . $service['port'];
                }
                $start = $start + $service['weight'];
            }
        }
    }

    /**
     * 关闭客户端连接
     *
     * @return mixed
     * @author wuzhc 2021310 9:16:46
     */
    public function close()
    {
        foreach ($this->connects as $connect) {
            $connect->close(true);
        }
    }

    /**
     * 刷新节点服务信息
     * 客户端使用长连接的情况下，需要起一个定时器来定时更新节点服务信息
     *
     * @author wuzhc 2021313 18:24:23
     */
    public function refreshServices()
    {
        if ($this->register) {
            $this->services = $this->register->getServices($this->module);
            $this->connects = [];
        }
    }
}