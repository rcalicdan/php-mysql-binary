<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Handshake;

use Rcalicdan\MySQLBinaryProtocol\Constants\CapabilityFlags;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * Parser for MySQL handshake version 10 frames.
 * 
 * This parser extracts handshake information from the binary protocol payload
 * and constructs a HandshakeV10 frame object using the builder pattern.
 */
final class HandshakeParser
{
    private HandshakeV10Builder $frameBuilder;
    private mixed $frameReceiver;

    /**
     * Creates a new handshake parser.
     *
     * @param HandshakeV10Builder $frameBuilder The builder for creating frames
     * @param callable $frameReceiver Callback to receive the parsed frame
     */
    public function __construct(HandshakeV10Builder $frameBuilder, callable $frameReceiver)
    {
        $this->frameBuilder = $frameBuilder;
        $this->frameReceiver = $frameReceiver;
    }

    /**
     * Parses a handshake frame from the payload reader.
     *
     * @param PayloadReader $reader The payload reader containing handshake data
     */
    public function __invoke(PayloadReader $reader): void
    {
        $reader->readFixedInteger(1);

        $frameBuilder = $this->frameBuilder->withServerInfo(
            $reader->readNullTerminatedString(),
            $reader->readFixedInteger(4)
        );

        $authData = $reader->readFixedString(8);
        $reader->readFixedString(1);
        $capabilities = $reader->readFixedInteger(2);
        $authPlugin = 'mysql_native_password';

        if ($capabilities & CapabilityFlags::CLIENT_PROTOCOL_41) {
            $frameBuilder = $frameBuilder->withCharset($reader->readFixedInteger(1))
                ->withStatus($reader->readFixedInteger(2));

            $capabilities |= $reader->readFixedInteger(2) << 16;

            $authDataLen = 0;
            if ($capabilities & CapabilityFlags::CLIENT_PLUGIN_AUTH) {
                $authDataLen = $reader->readFixedInteger(1);
            } else {
                $reader->readFixedString(1);
            }

            $reader->readFixedString(10);

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
            $authData .= $reader->readRestOfPacketString();
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