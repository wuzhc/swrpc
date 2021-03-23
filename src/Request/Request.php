<?php

namespace Swrpc\Request;


use Swrpc\Tracer\TracerContext;

abstract class Request
{
    protected string $module;
    protected string $method;
    protected array $params;
    protected bool $isSync = true; //是否同步请求，默认是
    protected bool $isSystem = false; //是否系统请求，默认否
    protected $error;
    protected ?TracerContext $traceContext;

    public static function create($method, $params, ?TracerContext $traceContext = null)
    {
        return new static ($method, $params, $traceContext);
    }

    public function __construct($method, $params, ?TracerContext $traceContext = null)
    {
        $this->method = $method;
        $this->params = $params;
        $this->traceContext = $traceContext;
        $this->init();
    }

    abstract public function init();

    public function getModule(): string
    {
        return $this->module;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params)
    {
        $this->params = $params;
    }

    public function setModule(string $name)
    {
        $this->module = $name;
    }

    public function mergeParams(array $params)
    {
        $this->params = array_merge($this->params, $params);
    }

    public function getTraceContext(): ?TracerContext
    {
        return $this->traceContext;
    }

    public function setTraceContext($traceID, $parentID, $url)
    {
        $this->traceContext = TracerContext::create($traceID, $parentID, $url);
    }

    public function setSync(bool $value)
    {
        $this->isSync = $value;
    }

    public function isSync(): bool
    {
        return $this->isSync;
    }

    public function setSystem(bool $value)
    {
        $this->isSystem = $value;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function getError()
    {
        return $this->error;
    }

    public function setError($err)
    {
        $this->error = $err;
    }
}