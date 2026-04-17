<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Handshake;

use Rcalicdan\MySQLBinaryProtocol\Buffer\Writer\BufferPayloadWriterFactory;
use Rcalicdan\MySQLBinaryProtocol\Constants\CapabilityFlags;

/**
 * Builds the SSL Request packet.
 *
 * Sent by the client to request an SSL connection before sending the full HandshakeResponse.
 * This is effectively a truncated HandshakeResponse containing only the client capabilities,
 * max packet size, and character set.
 */
final class SslRequest
{
    public function __construct(
        private readonly BufferPayloadWriterFactory $writerFactory = new BufferPayloadWriterFactory()
    ) {
    }

    public function build(int $capabilities, int $charset): string
    {
        $writer = $this->writerFactory->create();

        $writer->writeUInt32($capabilities | CapabilityFlags::CLIENT_SSL);
        $writer->writeUInt32(0x01000000); // Max packet size (16MB)
        $writer->writeUInt8($charset);
        $writer->writeZeros(23); // Reserved filler

        return $writer->toString();
    }
}
