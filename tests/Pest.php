<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BinaryIntegerReader;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BufferPayloadReaderFactory;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Writer\BufferPayloadWriter;
use Rcalicdan\MySQLBinaryProtocol\Constants\ColumnFlags;
use Rcalicdan\MySQLBinaryProtocol\Constants\MysqlType;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\BinaryRowOrEofParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\BinaryRow;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\ColumnDefinition;
use Rcalicdan\MySQLBinaryProtocol\Packet\CompressedPacketReader;
use Rcalicdan\MySQLBinaryProtocol\Packet\UncompressedPacketReader;

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

function generateTestRsaKeyPair(): array
{
    $tempDir = sys_get_temp_dir();
    $configFile = $tempDir . '/openssl_test_' . uniqid() . '.cnf';

    $opensslConfig = <<<CONFIG
[ req ]
default_bits = 2048
distinguished_name = req_distinguished_name

[ req_distinguished_name ]
CONFIG;

    file_put_contents($configFile, $opensslConfig);

    $config = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'config' => $configFile,
    ];

    $res = openssl_pkey_new($config);

    @unlink($configFile);

    if ($res === false) {
        throw new RuntimeException('Failed to generate RSA key pair: ' . openssl_error_string());
    }

    $publicKeyDetails = openssl_pkey_get_details($res);

    return [
        'resource' => $res,
        'public_key_pem' => $publicKeyDetails['key'],
    ];
}

/**
 * Null bitmap offset is +2 bits per the MySQL binary protocol spec.
 * For N columns: floor((N + 7 + 2) / 8) bytes.
 * Bit position for column i = i + 2.
 */
function buildNullBitmap(int $columnCount, array $nullIndexes = []): string
{
    $byteCount = (int) floor(($columnCount + 7 + 2) / 8);
    $bitmap = array_fill(0, $byteCount, 0);

    foreach ($nullIndexes as $i) {
        $bitPos = $i + 2;
        $bitmap[(int) floor($bitPos / 8)] |= (1 << ($bitPos % 8));
    }

    return implode('', array_map('chr', $bitmap));
}

function makeCol(int $type, int $flags = 0): ColumnDefinition
{
    return new ColumnDefinition('def', 'db', 'tbl', 'tbl', 'col', 'col', 33, 11, $type, $flags, 0);
}

function buildUnsignedLongLongPayload(string $hexLE): string
{
    return "\x00" . buildNullBitmap(1) . hex2bin($hexLE);
}

function buildSignedLongLongPayload(string $hexLE): string
{
    return "\x00" . buildNullBitmap(1) . hex2bin($hexLE);
}

function unsignedCol(): array
{
    return [makeCol(MysqlType::LONGLONG, ColumnFlags::UNSIGNED_FLAG)];
}

function signedCol(): array
{
    return [makeCol(MysqlType::LONGLONG)];
}

function parseLongLong(array $columns, string $payload): mixed
{
    $reader = createReader($payload);

    /** @var BinaryRow $row */
    $row = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    return $row->values[0];
}

function buildRawPacket(string $payload, int $sequence = 0): string
{
    return substr(pack('V', strlen($payload)), 0, 3) . chr($sequence) . $payload;
}

function makePacketReader(): UncompressedPacketReader
{
    return new UncompressedPacketReader(
        new BinaryIntegerReader(),
        new ReadBuffer(),
        new BufferPayloadReaderFactory()
    );
}

function makeCompressedPacketReader(): CompressedPacketReader
{
    return new CompressedPacketReader(
        new BinaryIntegerReader(),
        new ReadBuffer(),
        makePacketReader()
    );
}

/**
 * Builds a raw compressed protocol packet (7-byte header):
 *   3-byte LE compressed payload length
 *   1-byte sequence ID
 *   3-byte LE uncompressed length (0 = data is NOT compressed)
 *
 * The $innerData should already be a valid inner uncompressed packet
 * (i.e. built with buildRawPacket) before passing here.
 */
function buildCompressedProtocolPacket(string $innerData, int $sequence = 0, bool $compress = false): string
{
    if ($compress) {
        $compressedData = gzcompress($innerData, 6);
        $uncompressedLength = strlen($innerData);
    } else {
        $compressedData = $innerData;
        $uncompressedLength = 0; 
    }

    return substr(pack('V', strlen($compressedData)), 0, 3)
        . chr($sequence)
        . substr(pack('V', $uncompressedLength), 0, 3)
        . $compressedData;
}