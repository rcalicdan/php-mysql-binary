<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Result;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

class TextRowParser implements FrameParser
{
    public function __construct(
        private int $columnCount
    ) {
    }

    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame
    {
        $values = [];

        for ($i = 0; $i < $this->columnCount; $i++) {
            $values[] = $payload->readLengthEncodedStringOrNull();
        }

        return new TextRow($values);
    }
}
