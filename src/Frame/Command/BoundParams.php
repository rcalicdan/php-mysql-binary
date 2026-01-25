<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Command;

/**
 * Represents bound parameters for COM_STMT_EXECUTE packets.
 *
 * This class holds the null bitmap, types, and values for bound parameters
 * used in COM_STMT_EXECUTE packets.
 */
final readonly class BoundParams
{
    public function __construct(
        public string $nullBitmap,
        public string $types,
        public string $values
    ) {
    }
}
