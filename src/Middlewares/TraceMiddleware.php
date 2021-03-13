<?php

namespace Swrpc\Middlewares;


use Closure;
use Swrpc\Request\Request;
use Swrpc\Response;
use Zipkin\Endpoint;
use Zipkin\Propagation\TraceContext;
use Zipkin\Reporters\Http;
use Zipkin\Samplers\BinarySampler;
use Zipkin\TracingBuilder;

/**
 * 链路追踪中间件
 * Class TraceMiddleware
 *
 * @package Swrpc\Middlewares
 * @author wuzhc 2021310 16:41:6
 */
class TraceMiddleware implements MiddlewareInterface
{
    function handle(Request $request, Closure $next): Response
    {
        $context = $request->getTraceContext();
        if (!$context) {
            return $next($request);
        }

        $traceContext = TraceContext::create($context->getTraceID(), $context->getParentID(), null, true);
        $endpoint = Endpoint::create($request->getModule());
        $reporter = new Http(['endpoint_url' => $context->getReporterUrl()]);
        $sampler = BinarySampler::createAsAlwaysSample();
        $tracing = TracingBuilder::create()
            ->havingLocalEndpoint($endpoint)
            ->havingSampler($sampler)
            ->havingReporter($reporter)
            ->build();

        $tracer = $tracing->getTracer();
        $span = $tracer->newChild($traceContext);
        $span->setName($request->getMethod());
        $span->start();
        $span->tag('请求参数', serialize($request->getParams()));
        $request->setTraceContext($span->getContext()->getTraceId(), $span->getContext()
            ->getSpanId(), $context->getReporterUrl());

        $start = microtime(true);
        $result = $next($request);
        $end = microtime(true);

        $span->tag('响应状态码code', $result->code);
        $span->tag('响应提示语msg', $result->msg);
        $span->tag('响应耗时', $end - $start);
        $span->finish();
        $tracer->flush();

        return $result;
    }
}