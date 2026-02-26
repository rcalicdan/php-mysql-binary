<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Constants\MysqlType;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ColumnDefinitionOrEofParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\EofPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ErrPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\MetadataOmittedRowMarker;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\ColumnDefinition;

test('ColumnDefinitionOrEofParser parses a full column definition', function () {
    $payload = buildColumnPayload(name: 'id', table: 'users', type: MysqlType::LONG);

    $reader = createReader($payload);

    /** @var ColumnDefinition $packet */
    $packet = (new ColumnDefinitionOrEofParser())->parse($reader, strlen($payload), 1);

    expect($packet)->toBeInstanceOf(ColumnDefinition::class)
        ->and($packet->catalog)->toBe('def')
        ->and($packet->schema)->toBe('test_db')
        ->and($packet->table)->toBe('users')
        ->and($packet->orgTable)->toBe('users')
        ->and($packet->name)->toBe('id')
        ->and($packet->orgName)->toBe('id')
        ->and($packet->type)->toBe(MysqlType::LONG)
    ;
});

test('ColumnDefinitionOrEofParser parses column definition with VARCHAR type', function () {
    $payload = buildColumnPayload(name: 'username', table: 'users', type: MysqlType::VAR_STRING);

    $reader = createReader($payload);

    /** @var ColumnDefinition $packet */
    $packet = (new ColumnDefinitionOrEofParser())->parse($reader, strlen($payload), 2);

    expect($packet)->toBeInstanceOf(ColumnDefinition::class)
        ->and($packet->name)->toBe('username')
        ->and($packet->type)->toBe(MysqlType::VAR_STRING)
    ;
});

test('ColumnDefinitionOrEofParser parses EOF packet', function () {
    $payload = "\xFE" . pack('v', 2) . pack('v', 8);

    $reader = createReader($payload);

    /** @var EofPacket $packet */
    $packet = (new ColumnDefinitionOrEofParser())->parse($reader, strlen($payload), 4);

    expect($packet)->toBeInstanceOf(EofPacket::class)
        ->and($packet->warnings)->toBe(2)
        ->and($packet->statusFlags)->toBe(8)
        ->and($packet->sequenceNumber)->toBe(4)
    ;
});

test('ColumnDefinitionOrEofParser parses EOF packet with zero warnings', function () {
    $payload = "\xFE" . pack('v', 0) . pack('v', 0);

    $reader = createReader($payload);

    /** @var EofPacket $packet */
    $packet = (new ColumnDefinitionOrEofParser())->parse($reader, strlen($payload), 1);

    expect($packet)->toBeInstanceOf(EofPacket::class)
        ->and($packet->warnings)->toBe(0)
        ->and($packet->statusFlags)->toBe(0)
    ;
});

test('ColumnDefinitionOrEofParser parses ERR packet', function () {
    $errorMsg = "Table 'db.missing' doesn't exist";
    $payload = "\xFF" . pack('v', 1146) . '#42S02' . $errorMsg;

    $reader = createReader($payload);

    /** @var ErrPacket $packet */
    $packet = (new ColumnDefinitionOrEofParser())->parse($reader, strlen($payload), 2);

    expect($packet)->toBeInstanceOf(ErrPacket::class)
        ->and($packet->errorCode)->toBe(1146)
        ->and($packet->sqlStateMarker)->toBe('#')
        ->and($packet->sqlState)->toBe('42S02')
        ->and($packet->errorMessage)->toBe($errorMsg)
    ;
});

test('ColumnDefinitionOrEofParser returns MetadataOmittedRowMarker when first byte is 0x00', function () {
    $payload = "\x00";

    $reader = createReader($payload);

    $packet = (new ColumnDefinitionOrEofParser())->parse($reader, strlen($payload), 1);

    expect($packet)->toBeInstanceOf(MetadataOmittedRowMarker::class);
});

test('ColumnDefinitionOrEofParser parses column with different name and orgName', function () {
    $payload = buildColumnPayload(name: 'aliased_col', table: 'orders', type: MysqlType::LONGLONG);

    $reader = createReader($payload);

    /** @var ColumnDefinition $packet */
    $packet = (new ColumnDefinitionOrEofParser())->parse($reader, strlen($payload), 1);

    expect($packet)->toBeInstanceOf(ColumnDefinition::class)
        ->and($packet->name)->toBe('aliased_col')
        ->and($packet->table)->toBe('orders');
});
