<?php

namespace Swrpc\Register;


use SensioLabs\Consul\ServiceFactory;
use SensioLabs\Consul\Services\Agent;
use SensioLabs\Consul\Services\AgentInterface as AgentInterfaceAlias;
use SensioLabs\Consul\Services\Catalog;
use SensioLabs\Consul\Services\CatalogInterface;
use SensioLabs\Consul\Services\Health;
use Swrpc\Exceptions\RpcException;

class Consul implements RegisterInterface
{
    protected $sf;
    protected array $options;
    protected array $serviceCache
        = [
            'ttl'            => 10,
            'services'       => [],
            'lastUpdateTime' => 0,
        ];

    public function __construct($uri = 'http://127.0.0.1:8500', $options = [])
    {
        $this->options = $options;
        $this->sf = new ServiceFactory([
            'base_uri' => $uri
        ]);
    }

    public function getName(): string
    {
        return 'Consul';
    }

    /**
     * 注册节点
     *
     * @param string $module
     * @param string $host
     * @param        $port
     * @param int    $weight
     * @author wuzhc 202139 23:17:5
     */
    public function register($module, $host, $port, $weight = 1)
    {
        $id = $host . '_' . $port;
        /** @var Agent $agent */
        $agent = $this->sf->get(AgentInterfaceAlias::class);
        $agent->registerService([
            'ID'      => $id,
            'Name'    => $module,
            'Port'    => $port,
            'Address' => $host,
            'Tags'    => [
                'port_' . $port,
            ],
            'Weights' => [
                'Passing' => $weight,
                'Warning' => 1,
            ],
            'Check'   => [
                'TCP'                            => $host . ':' . $port,
                'Interval'                       => $this->options['interval'] ?? '10s',
                'Timeout'                        => $this->options['timeout'] ?? '5s',
                'DeregisterCriticalServiceAfter' => $this->options['deregisterCriticalServiceAfter'] ?? '30s',
            ],
        ]);
    }

    /**
     * 注销节点
     * http://127.0.0.1:8500/v1/agent/service/deregister/service_id
     *
     * @param $host
     * @param $port
     * @author wuzhc 202139 23:16:51
     */
    public function unRegister($host, $port)
    {
        $id = $host . '_' . $port;
        /** @var Agent $agent */
        $agent = $this->sf->get(AgentInterfaceAlias::class);
        $agent->deregisterService($id);
    }

    /**
     * 获取模块下所有的服务
     *
     * @param string $module
     * @return array
     * @author wuzhc 2021310 9:44:16
     */
    public function getServices(string $module): array
    {
        $cache = $this->serviceCache;
        $ttl = $this->options['ttl'] ?? $cache['ttl'];

        //本地缓存所有节点信息，避免每次请求都要从consul拉一遍数据
        if ($cache['lastUpdateTime'] + $ttl < time()) {
            $health = new Health();
            $servers = $health->service($module)->json();
            if (empty($servers)) {
                return [];
            }
            $result = [];
            foreach ($servers as $server) {
                $result[] = Service::build($server['Service']['Address'], $server['Service']['Port'], $server['Service']['Weights']['Passing']);
            }
            $cache['service'] = $result;
            $cache['lastUpdateTime'] = time();
        }

        return $cache['service'];
    }

    /**
     * 随机获取一个服务
     *
     * @param string $module
     * @return Service
     * @author wuzhc 2021310 9:44:27
     */
    public function getRandomService(string $module): Service
    {
        $services = $this->getServices($module);
        if (!$services) {
            throw new RpcException('It has not register module');
        }

        return $services[rand(0, count($services) - 1)];
    }

    /**
     * 获取权重服务
     *
     * @param string $module
     * @return Service
     * @author wuzhc 2021310 9:44:38
     */
    public function getWeightService(string $module): Service
    {
        $serviceArr = [];
        $totalWeight = 0;
        $services = $this->getServices($module);
        if (!$services) {
            throw new RpcException('It has not register module');
        }

        /** @var Service $service */
        foreach ($services as $service) {
            $totalWeight += $service->getWeight();
            $sort[] = $service->getWeight();
            $serviceArr[] = $service->toArray();
        }

        array_multisort($serviceArr, SORT_DESC, $sort);

        $start = 0;
        $rand = rand(1, $totalWeight);
        foreach ($serviceArr as $service) {
            if ($start + $service['weight'] >= $rand) {
                return Service::build($service['host'], $service['port'], $service['weight']);
            }
            $start = $start + $service['weight'];
        }
    }
}