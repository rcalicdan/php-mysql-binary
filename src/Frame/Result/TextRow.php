<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Result;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;

final readonly class TextRow implements Frame
{
    /**
     * @param array<int, string|null> $values Indexed array of column values
     */
    public function __construct(
        public array $values
    ) {}
}