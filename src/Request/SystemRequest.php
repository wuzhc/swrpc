<?php

namespace Swrpc\Request;


/**
 * Class AsyncRequest
 *
 * @package Swrpc\Request
 * @author wuzhc 2021313 9:10:2
 */
class SystemRequest extends Request
{
    public function init()
    {
        $this->setSync(true);
        $this->setSystem(true);
    }
}