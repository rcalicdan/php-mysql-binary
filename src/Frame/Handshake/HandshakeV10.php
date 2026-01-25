<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Handshake;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;

/**
 * Represents a MySQL handshake version 10 frame.
 *
 * This frame contains the initial handshake information sent by the MySQL server
 * to establish connection capabilities and authentication parameters.
 */
final readonly class HandshakeV10 implements Frame
{
    public function __construct(
        public string $serverVersion,
        public int $connectionId,
        public string $authData,
        public int $capabilities,
        public int $charset = 0,
        public int $status = 0,
        public string $authPlugin = '',
        public int $sequenceNumber = 0
    ) {
    }
}
