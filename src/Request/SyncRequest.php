<?php

namespace Swrpc\Request;

/**
 * Class SyncRequest
 *
 * @package Swrpc\Request
 * @author wuzhc 2021313 9:9:54
 */
class SyncRequest extends Request
{
    public function init()
    {
        $this->setSync(false);
    }
}