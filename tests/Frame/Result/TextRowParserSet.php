<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Buffer\Writer\BufferPayloadWriter;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\TextRow;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\TextRowParser;

test('parses text row with mixed values', function () {
    $writer = new BufferPayloadWriter();
    $writer->writeLengthEncodedString('hello')
        ->writeLengthEncodedString('123')
        ->writeLengthEncodedInteger(0xFB)
    ;

    $payload = $writer->toString();
    $reader = createRowReader($payload);

    $parser = new TextRowParser(3);

    /** @var TextRow $row */
    $row = $parser->parse($reader, strlen($payload), 1);

    expect($row)->toBeInstanceOf(TextRow::class)
        ->and($row->values)->toHaveCount(3)
        ->and($row->values[0])->toBe('hello')
        ->and($row->values[1])->toBe('123')
        ->and($row->values[2])->toBeNull()
    ;
});

test('parses empty row', function () {
    $parser = new TextRowParser(0);
    $reader = createRowReader('');

    /** @var TextRow $row */
    $row = $parser->parse($reader, 0, 1);

    expect($row->values)->toBeArray()->toBeEmpty();
});
