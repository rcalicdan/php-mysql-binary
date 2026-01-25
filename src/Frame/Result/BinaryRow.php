<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Result;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;

/**
 * Represents a single data row from a prepared statement (Binary Protocol).
 */
final readonly class BinaryRow implements Frame
{
    /**
     * @param array<int, mixed> $values An indexed array of column values.
     */
    public function __construct(
        public array $values
    ) {
    }
}
