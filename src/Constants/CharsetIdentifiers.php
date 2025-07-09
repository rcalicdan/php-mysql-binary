<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Constants;

/**
 * MySQL character set identifiers.
 * 
 * These constants define the numeric identifiers for common
 * character sets used in MySQL protocol communication.
 */
class CharsetIdentifiers
{
    public const UTF8 = 33;
    public const LATIN1 = 8;
    public const UTF8MB4 = 255;
}