<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Result;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

class ColumnDefinitionParser implements FrameParser
{
    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame
    {
        $catalog = $payload->readLengthEncodedStringOrNull() ?? '';
        
        $schema = $payload->readLengthEncodedStringOrNull() ?? '';
        
        $table = $payload->readLengthEncodedStringOrNull() ?? '';
        
        $orgTable = $payload->readLengthEncodedStringOrNull() ?? '';
 
        $name = $payload->readLengthEncodedStringOrNull() ?? '';
        
        $orgName = $payload->readLengthEncodedStringOrNull() ?? '';

        $payload->readLengthEncodedIntegerOrNull();

        $charset = $payload->readFixedInteger(2);

        $columnLength = $payload->readFixedInteger(4);

        $type = $payload->readFixedInteger(1);

        $flags = $payload->readFixedInteger(2);

        $decimals = $payload->readFixedInteger(1);

        $payload->readFixedInteger(2);

        return new ColumnDefinition(
            $catalog,
            $schema,
            $table,
            $orgTable,
            $name,
            $orgName,
            (int) $charset,
            (int) $columnLength,
            (int) $type,
            (int) $flags,
            (int) $decimals
        );
    }
}