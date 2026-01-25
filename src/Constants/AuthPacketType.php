<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Constants;

/**
 * MySQL authentication response packet header bytes.
 *
 * These constants define the first byte of packets received during
 * the authentication handshake process.
 */
final class AuthPacketType
{
    public const int OK = 0x00;
    public const int ERR = 0xFF;
    public const int AUTH_SWITCH_REQUEST = 0xFE;
    public const int AUTH_MORE_DATA = 0x01;
    public const int FULL_AUTH_REQUIRED = 0x04;
    public const int FAST_AUTH_SUCCESS = 0x03;
}