<?php

namespace Swrpc\Packer;


use Swrpc\Request\Request;

/**
 * Interface PackerInterface
 *
 * @package Swrpc\Packer
 * @author wuzhc 202139 11:37:10
 */
interface PackerInterface
{
    function pack(Request $data):string;
    function unpack(string $data);
}