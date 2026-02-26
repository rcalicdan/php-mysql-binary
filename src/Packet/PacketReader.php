<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Packet;

/**
 * Interface for reading MySQL protocol packets from a data stream.
 *
 * Packet readers handle the low-level packet framing and provide
 * access to packet payloads for higher-level processing.
 */
interface PacketReader
{
    /**
     * Appends raw data to the packet reader's internal buffer.
     *
     * @param string $data The raw binary data to append
     */
    public function append(string $data): void;

    /**
     * Attempts to read a complete packet payload and pass it to the provided reader.
     *
     * @param callable $reader Callback function to process the payload
     * @return bool True if a complete packet was read, false if more data is needed
     */
    public function readPayload(callable $reader): bool;

    /**
     * Checks if at least one complete packet is available to be read from the buffer.
     *
     * This is used to control the read loop in the connection manager.
     *
     * @return bool True if a packet is ready, false otherwise.
     */
    public function hasPacket(): bool;
}
