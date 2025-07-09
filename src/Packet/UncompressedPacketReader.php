<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Packet;

use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BinaryIntegerReader;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BufferPayloadReaderFactory;
use Rcalicdan\MySQLBinaryProtocol\Exception\IncompleteBufferException;

/**
 * Reads uncompressed MySQL protocol packets from a data stream.
 * 
 * This implementation handles the standard MySQL packet format with
 * 3-byte length header and 1-byte sequence number, supporting
 * multi-packet messages and proper sequencing.
 */
class UncompressedPacketReader implements PacketReader
{
    private const LENGTH = 0;
    private const SEQUENCE = 1;

    private int $awaitedPacketLength = 0;
    private array $packets = [];
    private array $remainingPacketLength = [];
    private BinaryIntegerReader $binaryIntegerReader;
    private ReadBuffer $readBuffer;
    private BufferPayloadReaderFactory $payloadReaderFactory;

    /**
     * Creates a new uncompressed packet reader.
     *
     * @param BinaryIntegerReader $binaryIntegerReader Reader for binary integers
     * @param ReadBuffer $readBuffer Buffer for storing incoming data
     * @param BufferPayloadReaderFactory $payloadReaderFactory Factory for payload readers
     */
    public function __construct(
        BinaryIntegerReader $binaryIntegerReader,
        ReadBuffer $readBuffer,
        BufferPayloadReaderFactory $payloadReaderFactory
    ) {
        $this->binaryIntegerReader = $binaryIntegerReader;
        $this->readBuffer = $readBuffer;
        $this->payloadReaderFactory = $payloadReaderFactory;
    }

    /**
     * Appends raw data to the packet reader and processes any complete packets.
     *
     * @param string $data The raw binary data to append
     */
    public function append(string $data): void
    {
        do {
            $data = $this->registerPacket($data);
        } while ($data !== '');
    }

    /**
     * Attempts to read a complete packet payload using the provided reader callback.
     *
     * @param callable $reader Callback function to process the payload
     * @return bool True if a complete packet was processed, false if more data is needed
     */
    public function readPayload(callable $reader): bool
    {
        try {
            $reader(
                $this->payloadReaderFactory->createFromBuffer($this->readBuffer, $this->remainingPacketLength),
                $this->packets[0][self::LENGTH],
                $this->packets[0][self::SEQUENCE]
            );
            $this->advancePacketLength($this->readBuffer->flush());
        } catch (IncompleteBufferException $exception) {
            return false;
        }

        return true;
    }

    /**
     * Registers a new packet from the provided data stream.
     *
     * @param string $dataToParse The data to parse for packet information
     * @return string Any remaining unparsed data
     */
    private function registerPacket(string $dataToParse): string
    {
        if ($this->awaitedPacketLength) {
            $trimLength = min(strlen($dataToParse), $this->awaitedPacketLength);
            $this->readBuffer->append(substr($dataToParse, 0, $trimLength));
            $this->awaitedPacketLength -= $trimLength;
            return substr($dataToParse, $trimLength);
        }

        $this->awaitedPacketLength = $this->binaryIntegerReader->readFixed(
            substr($dataToParse, 0, 3),
            3
        );

        $this->packets[] = [
            self::LENGTH => $this->awaitedPacketLength,
            self::SEQUENCE => $this->binaryIntegerReader->readFixed($dataToParse[3], 1)
        ];

        $this->remainingPacketLength[] = $this->awaitedPacketLength;

        return substr($dataToParse, 4);
    }

    /**
     * Advances the packet tracking based on the number of bytes read.
     *
     * @param int $readLength Number of bytes that were read from the buffer
     */
    private function advancePacketLength(int $readLength): void
    {
        while ($this->remainingPacketLength[0] <= $readLength) {
            $readLength -= $this->remainingPacketLength[0];
            array_shift($this->packets);
            array_shift($this->remainingPacketLength);

            if (!$this->packets) {
                return;
            }
        }

        $this->remainingPacketLength[0] -= $readLength;
    }
}