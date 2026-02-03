<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Constants;

/**
 * Defines the value boundaries for MySQL's integer data types.
 *
 * These values are used for correctly converting unsigned integers
 * read from the binary protocol into their signed PHP equivalents.
 */
final class DataTypeBounds
{
    public const int TINYINT_SIGN_BIT = 128;
    public const int TINYINT_RANGE = 256;
    public const int SMALLINT_SIGN_BIT = 32768;
    public const int SMALLINT_RANGE = 65536;
    public const int MEDIUMINT_SIGN_BIT = 8388608;
    public const int MEDIUMINT_RANGE = 16777216;
    public const int INT_SIGN_BIT = 2147483648;
    public const int INT_RANGE = 4294967296;
}
