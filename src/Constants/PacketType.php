<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Constants;

/**
 * MySQL Protocol Packet Type Identifiers.
 *
 * These constants define the first byte of various packet types
 * used in the MySQL binary protocol for response identification.
 */
final class PacketType
{
    public const int OK = 0x00;
    public const int ERR = 0xFF;
    public const int EOF = 0xFE;
    public const int LOCAL_INFILE = 0xFB;
    public const int EOF_MAX_LENGTH = 9;
}