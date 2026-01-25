<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Packet;

interface PacketWriter
{
    /**
     * Wraps a payload in the appropriate packet header.
     */
    public function write(string $payload, int $sequenceId): string;
}
