<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Command;

/**
 * A Value Object holding the binary components for prepared statement parameters.
 */
final readonly class BoundParams
{
    public function __construct(
        public string $nullBitmap,
        public string $types,
        public string $values
    ) {}
}