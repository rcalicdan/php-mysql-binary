<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Packet;

use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BinaryIntegerReader;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BufferPayloadReader;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BufferPayloadReaderFactory;
use Rcalicdan\MySQLBinaryProtocol\Exception\IncompleteBufferException;

class UncompressedPacketReader implements PacketReader
{
    private const LENGTH = 0;
    private const SEQUENCE = 1;
    private int $awaitedPacketLength = 0;
    private array $packets = [];
    private array $remainingPacketLength = [];
    private BinaryIntegerReader $binaryIntegerReader;
    private ReadBuffer $readBuffer;
    private BufferPayloadReader $payloadReader;

    public function __construct(
        BinaryIntegerReader $binaryIntegerReader,
        ReadBuffer $readBuffer,
        BufferPayloadReaderFactory $payloadReaderFactory
    ) {
        $this->binaryIntegerReader = $binaryIntegerReader;
        $this->readBuffer = $readBuffer;
        $this->payloadReader = $payloadReaderFactory->createFromBuffer(
            $this->readBuffer,
            $this->remainingPacketLength
        );
    }

    public function append(string $data): void
    {
        do {
            $data = $this->registerPacket($data);
        } while ($data !== '');
    }

    public function hasPacket(): bool
    {
        return !empty($this->packets);
    }

    public function readPayload(callable $reader): bool
    {
        if (!$this->hasPacket()) {
            throw new IncompleteBufferException('No packet available to read.');
        }

        try {
            $reader(
                $this->payloadReader,
                $this->packets[0][self::LENGTH],
                $this->packets[0][self::SEQUENCE]
            );
            $this->advancePacketLength($this->readBuffer->flush());
        } catch (IncompleteBufferException $exception) {
            return false;
        }
        return true;
    }

    private function registerPacket(string $dataToParse): string
    {
        if ($this->awaitedPacketLength) {
            $trimLength = min(strlen($dataToParse), $this->awaitedPacketLength);
            $this->readBuffer->append(substr($dataToParse, 0, $trimLength));
            $this->awaitedPacketLength -= $trimLength;
            return substr($dataToParse, $trimLength);
        }

        if (strlen($dataToParse) < 4) {
            $this->readBuffer->append($dataToParse);
            return '';
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

        $payloadData = substr($dataToParse, 4);
        $trimLength = min(strlen($payloadData), $this->awaitedPacketLength);
        if ($trimLength > 0) {
            $this->readBuffer->append(substr($payloadData, 0, $trimLength));
        }
        $this->awaitedPacketLength -= $trimLength;

        return substr($payloadData, $trimLength);
    }

    private function advancePacketLength(int $readLength): void
    {
        while (isset($this->remainingPacketLength[0]) && $this->remainingPacketLength[0] <= $readLength) {
            $readLength -= $this->remainingPacketLength[0];
            array_shift($this->packets);
            array_shift($this->remainingPacketLength);
        }

        if (isset($this->remainingPacketLength[0])) {
            $this->remainingPacketLength[0] -= $readLength;
        }
    }
}
