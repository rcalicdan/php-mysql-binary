<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Packet;

use InvalidArgumentException;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Writer\BinaryWriter;

class CompressedPacketWriter implements PacketWriter
{
    private const int MAX_PACKET_SIZE = 16777215;

    /**
     * MySQL optimization: Only compress if payload is > 50 bytes.
     * Below this, the overhead of compression headers often makes the packet larger.
     */
    private const int COMPRESSION_THRESHOLD = 50;

    private BinaryWriter $binaryWriter;

    public function __construct(?BinaryWriter $binaryWriter = null)
    {
        if (! extension_loaded('zlib')) {
            throw new \RuntimeException('The zlib extension is required for MySQL compression.');
        }
        $this->binaryWriter = $binaryWriter ?? new BinaryWriter();
    }

    public function write(string $payload, int $sequenceId): string
    {
        $innerLength = \strlen($payload);

        if ($innerLength > self::MAX_PACKET_SIZE) {
            throw new InvalidArgumentException(
                \sprintf('Payload size %d exceeds maximum packet size of %d', $innerLength, self::MAX_PACKET_SIZE)
            );
        }

        $innerHeader = $this->binaryWriter->writeUInt24($innerLength) .
            $this->binaryWriter->writeUInt8($sequenceId);

        $dataToPotentialCompress = $innerHeader . $payload;
        $uncompressedSize = \strlen($dataToPotentialCompress);

        $compressedData = '';
        $finalUncompressedLength = 0;

        if ($uncompressedSize < self::COMPRESSION_THRESHOLD) {
            $compressedData = $dataToPotentialCompress;
            $finalUncompressedLength = 0;
        } else {
            $compressedData = gzcompress($dataToPotentialCompress, 6);

            if ($compressedData === false) {
                throw new \RuntimeException('Failed to compress MySQL packet using zlib.');
            }

            $finalUncompressedLength = $uncompressedSize;
        }

        $compressedSize = \strlen($compressedData);

        $header = $this->binaryWriter->writeUInt24($compressedSize) .
            $this->binaryWriter->writeUInt8($sequenceId) .
            $this->binaryWriter->writeUInt24($finalUncompressedLength);

        return $header . $compressedData;
    }
}
