<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Constants;

/**
 * Defines the bitmask flags for column definitions in a result set.
 */
final class ColumnFlags
{
    public const int NOT_NULL_FLAG = 1;
    public const int PRI_KEY_FLAG = 2;
    public const int UNIQUE_KEY_FLAG = 4;
    public const int MULTIPLE_KEY_FLAG = 8;
    public const int BLOB_FLAG = 16;
    public const int UNSIGNED_FLAG = 32;
    public const int ZEROFILL_FLAG = 64;
    public const int BINARY_FLAG = 128;
}
