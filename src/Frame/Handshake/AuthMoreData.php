<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Handshake;

use Rcalicdan\MySQLBinaryProtocol\Constants\AuthPacketType;
use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;

/**
 * Represents a MySQL Auth More Data packet (0x01).
 * Used for multi-step authentication (e.g. caching_sha2_password sending an RSA key).
 */
final readonly class AuthMoreData implements Frame
{
    public function __construct(
        public string $data,
        public int $sequenceNumber
    ) {
    }

    public function isFullAuthRequired(): bool
    {
        return \strlen($this->data) === 1 && \ord($this->data[0]) === AuthPacketType::FULL_AUTH_REQUIRED;
    }

    public function isFastAuthSuccess(): bool
    {
        return \strlen($this->data) === 1 && \ord($this->data[0]) === AuthPacketType::FAST_AUTH_SUCCESS;
    }
}