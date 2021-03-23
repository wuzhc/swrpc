<?php

namespace Swrpc\Request;


/**
 * Class AsyncRequest
 *
 * @package Swrpc\Request
 * @author wuzhc 2021313 9:10:2
 */
class AsyncRequest extends Request
{
    public function init()
    {
        $this->setSync(false);
        $this->setSystem(false);
    }
}