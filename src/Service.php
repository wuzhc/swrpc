<?php

namespace Swrpc;


use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use Swrpc\Request\Request;

/**
 * Class Service
 *
 * @package Swrpc
 * @author wuzhc 202139 11:39:41
 */
class Service
{
    private array $services = [];
    protected array $filers
        = [
            'factory',
            'initTracer',
            'setModule',
            'setTracerUrl',
            'setParams',
            'setTracerContext',
            'getTracerContext'
        ];

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    /**
     * 注册服务实例
     *
     * @param $obj
     * @param $prefix
     * @return bool
     * @author wuzhc 202138 13:43:21
     */
    public function addInstance($obj, $prefix = ''): bool
    {
        if (is_string($obj)) {
            $obj = new $obj();
        }
        if (!is_object($obj)) {
            $this->logger->error('Service is not an object.', ['service' => $obj]);
            return false;
        }
        if (!($obj instanceof LogicService)) {
            $this->logger->error('The Service does not inherit LogicService', ['service' => get_class($obj)]);
            return false;
        }
        $className = get_class($obj);
        $methods = get_class_methods($obj);
        foreach ($methods as $method) {
            if (in_array($method, $this->filers)) {
                continue;
            }
            if (strlen($prefix) > 0) {
                $key = $prefix . '_' . $className . '_' . $method;
            } else {
                $key = $className . '_' . $method;
            }
            $this->services[$key] = $className;
            $this->logger->info(sprintf('import %s => %s.', $key, $className));
        }

        return true;
    }

    /**
     * 获取服务
     *
     * @param $key
     * @return mixed|null
     * @author wuzhc 202138 13:43:17
     */
    public function getService($key)
    {
        return $this->services[$key] ?? null;
    }

    /**
     * 获取所有服务
     * getServices
     *
     * @return array
     * @author wuzhc 202138 15:23:58
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * count
     *
     * @return int
     * @author wuzhc 202139 12:56:46
     */
    public function count(): int
    {
        return count($this->services);
    }

    /**
     * @param $key
     * @return bool
     * @author wuzhc 202138 14:32:50
     */
    public function isExist($key): bool
    {
        return isset($this->services[$key]);
    }

    /**
     * 调用服务
     *
     * @param Request $request
     * @return Response
     * @throws \ReflectionException
     * @author wuzhc 202139 10:17:59
     */
    public function call(Request $request): Response
    {
        if ($err = $request->getError()) {
            return Response::error($err);
        }

        $service = $this->getService($request->getMethod());
        if (!$service) {
            $this->logger->debug('service is not exist.', ['method' => $request->getMethod()]);
            return Response::error('service is not exist.');
        }

        $methodArr = explode('_', $request->getMethod());
        $methodName = array_pop($methodArr);
        $reflect = new ReflectionClass($service);
        $instance = $reflect->newInstanceArgs();
        if (!method_exists($instance, $methodName)) {
            $this->logger->debug('method is not exist.', ['method' => $request->getMethod()]);
            return Response::error(sprintf('%s method[%s] is not exist.', $service, $methodName));
        }

        $ctx = $request->getTraceContext();
        if ($ctx && method_exists($instance, 'setTracerContext')) {
            $instance->setTracerUrl($ctx->getReporterUrl())->setTracerContext($ctx);
        }

        try {
            $methodObj = new ReflectionMethod($reflect->getName(), $methodName);
            $result = $methodObj->invokeArgs($instance, $request->getParams());
        } catch (\Throwable $e) {
            return Response::error($e->getMessage());
        }

        return Response::success([
            'result' => $result
        ]);
    }
}