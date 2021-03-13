<?php

namespace Swrpc\Register;


/**
 * Interface RegisterInterface
 *
 * @package Swrpc\Register
 * @author wuzhc 202139 16:23:35
 */
interface RegisterInterface
{
    function getName(): string;

    function register($module, $host, $port, $weight = 1);

    function unRegister($host, $port);

    function getServices(string $module): array;

    function getRandomService(string $module): Service;

    function getWeightService(string $module): Service;
}