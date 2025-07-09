<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Handshake;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;

/**
 * Represents a MySQL handshake version 10 frame.
 * 
 * This frame contains the initial handshake information sent by the MySQL server
 * to establish connection capabilities and authentication parameters.
 */
class HandshakeV10 implements Frame
{
    public string $serverVersion;
    public int $connectionId;
    public string $authData;
    public int $capabilities;
    public int $charset;
    public int $status;
    public string $authPlugin;

    /**
     * Creates a new handshake frame with the specified parameters.
     *
     * @param string $serverVersion The MySQL server version string
     * @param int $connectionId The connection identifier
     * @param string $authData The authentication challenge data
     * @param int $capabilities The server capability flags
     * @param int $charset The default character set identifier
     * @param int $status The server status flags
     * @param string $authPlugin The authentication plugin name
     */
    public function __construct(
        string $serverVersion,
        int $connectionId,
        string $authData,
        int $capabilities,
        int $charset = 0,
        int $status = 0,
        string $authPlugin = ''
    ) {
        $this->serverVersion = $serverVersion;
        $this->connectionId = $connectionId;
        $this->authData = $authData;
        $this->capabilities = $capabilities;
        $this->charset = $charset;
        $this->status = $status;
        $this->authPlugin = $authPlugin;
    }
}