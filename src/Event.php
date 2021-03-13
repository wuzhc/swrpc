<?php

namespace Swrpc;

trait Event
{
    public function OnWorkerStart(\Swoole\Server $server, int $workerId)
    {
    }

    public function onStart(\Swoole\Server $server)
    {
    }

    public function onShutdown(\Swoole\Server $server)
    {
        
    }
}