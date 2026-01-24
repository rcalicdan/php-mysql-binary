<?php

use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BinaryIntegerReader;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BufferPayloadReaderFactory;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Writer\BufferPayloadWriter;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\ColumnDefinition;

uses(PHPUnit\Framework\TestCase::class)->in('.');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

function binaryReader(): BinaryIntegerReader
{
    return new BinaryIntegerReader();
}

function createReader(string $data)
{
    return (new BufferPayloadReaderFactory())->createFromString($data);
}

function createRowReader(string $data)
{
    return (new BufferPayloadReaderFactory())->createFromString($data);
}

function createBinaryRowReader(string $data)
{
    return (new BufferPayloadReaderFactory())->createFromString("\x00" . $data);
}

function createColumnDef(int $type): ColumnDefinition
{
    return new ColumnDefinition('def', 'db', 'tbl', 'tbl', 'col', 'col', 33, 0, $type, 0, 0);
}

function buildColumnPayload(
    string $name = 'id',
    string $table = 'users',
    int $type = MysqlType::LONGLONG
): string {
    $writer = new BufferPayloadWriter();
    $writer->writeLengthEncodedString('def')
        ->writeLengthEncodedString('test_db')
        ->writeLengthEncodedString($table)
        ->writeLengthEncodedString($table)
        ->writeLengthEncodedString($name)
        ->writeLengthEncodedString($name)
        ->writeLengthEncodedInteger(0x0C)
        ->writeUInt16(33)
        ->writeUInt32(11)
        ->writeUInt8($type)
        ->writeUInt16(0)
        ->writeUInt8(0)
        ->writeUInt16(0);

    return $writer->toString();
}
