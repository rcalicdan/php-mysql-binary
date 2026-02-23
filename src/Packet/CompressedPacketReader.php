<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Packet;

use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BinaryIntegerReader;
use Rcalicdan\MySQLBinaryProtocol\Exception\InvalidBinaryDataException;

class CompressedPacketReader implements PacketReader
{
    private const int PACKET_HEADER_SIZE = 7;

    private int $compressedPayloadLength = 0;

    private int $originalCompressedLength = 0;

    private int $uncompressedLength = 0;

    private string $partialHeader = '';

    private BinaryIntegerReader $binaryIntegerReader;
    private ReadBuffer $compressedBuffer;
    private UncompressedPacketReader $innerPacketReader;

    public function __construct(
        BinaryIntegerReader $binaryIntegerReader,
        ReadBuffer $readBuffer,
        UncompressedPacketReader $innerPacketReader
    ) {
        if (!extension_loaded('zlib')) {
            throw new \RuntimeException('The zlib extension is required for MySQL compression.');
        }

        $this->binaryIntegerReader = $binaryIntegerReader;
        $this->compressedBuffer = $readBuffer;
        $this->innerPacketReader = $innerPacketReader;
    }

    public function append(string $data): void
    {
        // If the reader has a partial header from a previous chunk, prepend it
        if ($this->partialHeader !== '') {
            $data = $this->partialHeader . $data;
            $this->partialHeader = '';
        }

        do {
            $data = $this->processChunk($data);
        } while ($data !== '');
    }

    public function readPayload(callable $reader): bool
    {
        return $this->innerPacketReader->readPayload($reader);
    }

    public function hasPacket(): bool
    {
        return $this->innerPacketReader->hasPacket();
    }

    private function processChunk(string $data): string
    {
        if ($this->compressedPayloadLength > 0) {
            $trimLength = min(\strlen($data), $this->compressedPayloadLength);
            $this->compressedBuffer->append(substr($data, 0, $trimLength));
            $this->compressedPayloadLength -= $trimLength;

            // If the reader received the full compressed payload, process it
            if ($this->compressedPayloadLength === 0) {
                $this->decompressAndPush();
            }

            return substr($data, $trimLength);
        }

        if (\strlen($data) < self::PACKET_HEADER_SIZE) {
            $this->partialHeader = $data;
            return '';
        }

        $header = substr($data, 0, self::PACKET_HEADER_SIZE);

        $this->compressedPayloadLength = (int) $this->binaryIntegerReader->readFixed(substr($header, 0, 3), 3);
        $this->originalCompressedLength = $this->compressedPayloadLength;


        $this->uncompressedLength = (int) $this->binaryIntegerReader->readFixed(substr($header, 4, 3), 3);

        $payloadData = substr($data, self::PACKET_HEADER_SIZE);
        $trimLength = min(\strlen($payloadData), $this->compressedPayloadLength);

        if ($trimLength > 0) {
            $this->compressedBuffer->append(substr($payloadData, 0, $trimLength));
        }
        $this->compressedPayloadLength -= $trimLength;

        if ($this->compressedPayloadLength === 0) {
            $this->decompressAndPush();
        }

        return substr($payloadData, $trimLength);
    }

    private function decompressAndPush(): void
    {
        $rawData = $this->compressedBuffer->read($this->originalCompressedLength);
        $this->compressedBuffer->flush();

        if ($this->uncompressedLength === 0) {
            $this->innerPacketReader->append($rawData);
            return;
        }

        set_error_handler(static function (int $errno, string $errstr): never {
            throw new InvalidBinaryDataException(
                'Failed to decompress MySQL packet using zlib: ' . $errstr
            );
        });

        try {
            $inflatedData = gzuncompress($rawData);
        } finally {
            restore_error_handler();
        }

        if ($inflatedData === false) {
            throw new InvalidBinaryDataException('Failed to decompress MySQL packet using zlib.');
        }

        if (\strlen($inflatedData) !== $this->uncompressedLength) {
            throw new InvalidBinaryDataException(\sprintf(
                'Decompression size mismatch. Expected %d bytes, got %d bytes.',
                $this->uncompressedLength,
                \strlen($inflatedData)
            ));
        }

        $this->innerPacketReader->append($inflatedData);
    }
}
