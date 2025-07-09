<?php
declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol;

use Rcalicdan\MySQLBinaryProtocol\Frame\HandshakeV10Builder;

class HandshakeParser
{
    private $frameBuilder;
    private $frameReceiver;

    public function __construct(HandshakeV10Builder $frameBuilder, callable $frameReceiver)
    {
        $this->frameBuilder = $frameBuilder;
        $this->frameReceiver = $frameReceiver;
    }

    public function __invoke(PayloadReader $reader)
    {
        $reader->readFixedInteger(1);
        $frameBuilder = $this->frameBuilder->withServerInfo(
            $reader->readNullTerminatedString(),
            $reader->readFixedInteger(4)
        );

        $authData = $reader->readFixedString(8);
        $reader->readFixedString(1); // filler
        $capabilities = $reader->readFixedInteger(2);

        $authPlugin = 'mysql_native_password'; // Default for old protocols

        if ($capabilities & CapabilityFlags::CLIENT_PROTOCOL_41) {
            $frameBuilder = $frameBuilder->withCharset($reader->readFixedInteger(1))
                ->withStatus($reader->readFixedInteger(2));

            $capabilities |= $reader->readFixedInteger(2) << 16;

            $authDataLen = 0;
            if ($capabilities & CapabilityFlags::CLIENT_PLUGIN_AUTH) {
                $authDataLen = $reader->readFixedInteger(1);
            } else {
                $reader->readFixedString(1); // Skip 0x00 byte
            }

            $reader->readFixedString(10); // reserved

            if ($capabilities & CapabilityFlags::CLIENT_SECURE_CONNECTION) {
                $authDataPart2Len = max(13, $authDataLen - 8);
                if ($authDataPart2Len > 0) {
                    $authData .= $reader->readFixedString($authDataPart2Len);
                }
            }

            if ($capabilities & CapabilityFlags::CLIENT_PLUGIN_AUTH) {
                $authPlugin = $reader->readNullTerminatedString();
            }
        } else {
            // Legacy handshake protocol support
            $authData .= $reader->readRestOfPacketString();
            // Trim trailing NUL byte if present
            if (substr($authData, -1) === "\x00") {
                $authData = substr($authData, 0, -1);
            }
        }

        $frameBuilder = $frameBuilder
            ->withCapabilities($capabilities)
            ->withAuthData($authData)
            ->withAuthPlugin($authPlugin);

        ($this->frameReceiver)($frameBuilder->build());
    }
}