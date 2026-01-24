<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Result;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;

final readonly class ColumnDefinition implements Frame
{
    public function __construct(
        public string $catalog,
        public string $schema,
        public string $table,
        public string $orgTable,
        public string $name,
        public string $orgName,
        public int $charset,
        public int $columnLength,
        public int $type,
        public int $flags,
        public int $decimals
    ) {}
}