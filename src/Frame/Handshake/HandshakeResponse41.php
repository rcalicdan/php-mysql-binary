<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Handshake;

use Rcalicdan\MySQLBinaryProtocol\Buffer\Writer\BufferPayloadWriterFactory;
use Rcalicdan\MySQLBinaryProtocol\Constants\CapabilityFlags;

final class HandshakeResponse41
{
    public function __construct(
        private readonly BufferPayloadWriterFactory $writerFactory = new BufferPayloadWriterFactory()
    ) {
    }

    public function build(
        int $capabilities,
        int $charset,
        string $username,
        string $authResponse,
        string $database = '',
        string $authPluginName = ''
    ): string {
        $writer = $this->writerFactory->create();

        $writer->writeUInt32($capabilities);

        $writer->writeUInt32(0x01000000);

        $writer->writeUInt8($charset);

        $writer->writeZeros(23);

        $writer->writeNullTerminatedString($username);

        if ($capabilities & CapabilityFlags::CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA) {
            $writer->writeLengthEncodedString($authResponse);
        } elseif ($capabilities & CapabilityFlags::CLIENT_SECURE_CONNECTION) {
            $writer->writeUInt8(\strlen($authResponse));
            $writer->writeString($authResponse);
        } else {
            $writer->writeNullTerminatedString($authResponse);
        }

        if (($capabilities & CapabilityFlags::CLIENT_CONNECT_WITH_DB) && $database !== '') {
            $writer->writeNullTerminatedString($database);
        }

        if (($capabilities & CapabilityFlags::CLIENT_PLUGIN_AUTH) && $authPluginName !== '') {
            $writer->writeNullTerminatedString($authPluginName);
        }

        return $writer->toString();
    }
}
