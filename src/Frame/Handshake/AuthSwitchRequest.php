<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Handshake;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;

/**
 * Represents a MySQL Auth Switch Request (0xFE).
 * Sent by the server to switch to a different authentication plugin.
 */
final readonly class AuthSwitchRequest implements Frame
{
    public function __construct(
        public string $pluginName,
        public string $authData,
        public int $sequenceNumber
    ) {
    }
}
