<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Constants;

/**
 * MySQL character set identifiers.
 *
 * These constants define the numeric identifiers for common
 * character sets used in MySQL protocol communication.
 */
final class CharsetIdentifiers
{
    public const int UTF8 = 33;
    public const int LATIN1 = 8;
    public const int UTF8MB4 = 255;
}
