<?php

namespace Swrpc\Middlewares;


use Closure;
use Swrpc\Request\Request;
use Swrpc\Response;

/**
 * Interface MiddlewareInterface
 *
 * @package Swrpc\Middlewares
 * @author wuzhc 202139 11:37:39
 */
interface MiddlewareInterface
{
    function handle(Request $request, Closure $next): Response;
}