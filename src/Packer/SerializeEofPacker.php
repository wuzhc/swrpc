<?php

namespace Swrpc\Packer;


use Swrpc\Request\Request;

/**
 * Class SerializeEofPacker
 *
 * @package Swrpc\Packer
 * @author wuzhc 202139 11:37:17
 */
class SerializeEofPacker implements PackerInterface
{
    /**
     * @var string
     */
    protected $eof;

    public function __construct(array $options = [])
    {
        $this->eof = $options['settings']['package_eof'] ?? "\r\n";
    }

    public function pack(Request $data): string
    {
        return serialize($data);
    }

    public function unpack(string $data)
    {
        return unserialize($data);
    }
}