<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Constants;

/**
 * MySQL Protocol Length-Encoded Integer Markers.
 *
 * These constants define the marker bytes used in length-encoded
 * integers and strings in the MySQL binary protocol.
 */
final class LengthEncodedType
{
    public const int NULL_MARKER = 0xFB;
    public const int INT16_LENGTH = 0xFC;
    public const int INT24_LENGTH = 0xFD;
    public const int INT64_LENGTH = 0xFE;
}