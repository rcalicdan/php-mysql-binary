<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Handshake;

use Rcalicdan\MySQLBinaryProtocol\Constants\CapabilityFlags;
use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * Parser for MySQL handshake version 10 frames.
 */
final class HandshakeParser implements FrameParser
{
    public function parse(PayloadReader $reader, int $length, int $sequenceNumber): Frame
    {
        $reader->readFixedInteger(1);

        $serverVersion = $reader->readNullTerminatedString();
        $connectionId = (int) $reader->readFixedInteger(4);

        $authData = $reader->readFixedString(8);

        $reader->readFixedString(1);

        $capabilities = (int) $reader->readFixedInteger(2);

        $authPlugin = 'mysql_native_password';
        $charset = 0;
        $status = 0;

        if ($capabilities & CapabilityFlags::CLIENT_PROTOCOL_41) {
            [$capabilities, $charset, $status, $authData, $authPlugin] = $this->parseProtocol41(
                $reader,
                $capabilities,
                $authData
            );
        } else {
            $authData = $this->parseLegacyAuthData($reader, $authData);
        }

        return new HandshakeV10(
            $serverVersion,
            $connectionId,
            $authData,
            $capabilities,
            $charset,
            $status,
            $authPlugin,
            $sequenceNumber
        );
    }

    /**
     * Parses protocol 4.1 specific handshake fields.
     *
     * @return array{int, int, int, string, string} [capabilities, charset, status, authData, authPlugin]
     */
    private function parseProtocol41(PayloadReader $reader, int $capabilities, string $authData): array
    {
        $charset = (int) $reader->readFixedInteger(1);
        $status = (int) $reader->readFixedInteger(2);

        $capabilities |= (int) $reader->readFixedInteger(2) << 16;

        $authDataLen = $this->readAuthDataLength($reader, $capabilities);

        $reader->readFixedString(10);

        $authData = $this->parseSecureAuthData($reader, $capabilities, $authData, $authDataLen);

        $authPlugin = $this->parseAuthPlugin($reader, $capabilities);

        return [$capabilities, $charset, $status, $authData, $authPlugin];
    }

    /**
     * Reads the auth data length if CLIENT_PLUGIN_AUTH is supported.
     */
    private function readAuthDataLength(PayloadReader $reader, int $capabilities): int
    {
        if ($capabilities & CapabilityFlags::CLIENT_PLUGIN_AUTH) {
            return (int) $reader->readFixedInteger(1);
        }

        $reader->readFixedString(1);

        return 0;
    }

    /**
     * Parses the second part of auth data if CLIENT_SECURE_CONNECTION is enabled.
     */
    private function parseSecureAuthData(
        PayloadReader $reader,
        int $capabilities,
        string $authData,
        int $authDataLen
    ): string {
        if ($capabilities & CapabilityFlags::CLIENT_SECURE_CONNECTION) {
            if ($authDataLen > 0) {
                $authDataPart2Len = max(13, $authDataLen - 8);
            } else {
                $authDataPart2 = $reader->readNullTerminatedString();

                return $authData . $authDataPart2;
            }

            if ($authDataPart2Len > 0) {
                $authData .= $reader->readFixedString($authDataPart2Len);
            }
        }

        return $authData;
    }

    /**
     * Parses the authentication plugin name if CLIENT_PLUGIN_AUTH is enabled.
     */
    private function parseAuthPlugin(PayloadReader $reader, int $capabilities): string
    {
        if ($capabilities & CapabilityFlags::CLIENT_PLUGIN_AUTH) {
            return $reader->readNullTerminatedString();
        }

        return 'mysql_native_password';
    }

    /**
     * Parses legacy (pre-4.1) authentication data.
     */
    private function parseLegacyAuthData(PayloadReader $reader, string $authData): string
    {
        $authData .= $reader->readRestOfPacketString();

        if (substr($authData, -1) === "\x00") {
            $authData = substr($authData, 0, -1);
        }

        return $authData;
    }
}
