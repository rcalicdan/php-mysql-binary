<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Handshake;

/**
 * Builder pattern implementation for creating HandshakeV10 frames.
 * 
 * This builder provides a fluent interface for constructing handshake frames
 * with various optional parameters while maintaining immutability.
 */
class HandshakeV10Builder
{
    private string $serverVersion;
    private int $clientId;
    private string $authData;
    private int $capabilities = 0;
    private int $charset = 0;
    private int $status = 0;
    private string $authPlugin = '';

    /**
     * Sets the server version and client connection ID.
     *
     * @param string $serverVersion The MySQL server version
     * @param int $clientId The client connection identifier
     * @return self A new builder instance with the specified values
     */
    public function withServerInfo(string $serverVersion, int $clientId): self
    {
        $builder = clone $this;
        $builder->serverVersion = $serverVersion;
        $builder->clientId = $clientId;
        return $builder;
    }

    /**
     * Sets the authentication data.
     *
     * @param string $authData The authentication challenge data
     * @return self A new builder instance with the specified value
     */
    public function withAuthData(string $authData): self
    {
        $builder = clone $this;
        $builder->authData = $authData;
        return $builder;
    }

    /**
     * Sets the server capability flags.
     *
     * @param int $flags The capability flags bitmask
     * @return self A new builder instance with the specified value
     */
    public function withCapabilities(int $flags): self
    {
        $builder = clone $this;
        $builder->capabilities = $flags;
        return $builder;
    }

    /**
     * Sets the character set identifier.
     *
     * @param int $charsetId The character set identifier
     * @return self A new builder instance with the specified value
     */
    public function withCharset(int $charsetId): self
    {
        $builder = clone $this;
        $builder->charset = $charsetId;
        return $builder;
    }

    /**
     * Sets the server status flags.
     *
     * @param int $status The status flags bitmask
     * @return self A new builder instance with the specified value
     */
    public function withStatus(int $status): self
    {
        $builder = clone $this;
        $builder->status = $status;
        return $builder;
    }

    /**
     * Sets the authentication plugin name.
     *
     * @param string $authPlugin The authentication plugin name
     * @return self A new builder instance with the specified value
     */
    public function withAuthPlugin(string $authPlugin): self
    {
        $builder = clone $this;
        $builder->authPlugin = $authPlugin;
        return $builder;
    }

    /**
     * Builds and returns the configured HandshakeV10 frame.
     *
     * @return HandshakeV10 The constructed handshake frame
     */
    public function build(): HandshakeV10
    {
        return new HandshakeV10(
            $this->serverVersion,
            $this->clientId,
            $this->authData,
            $this->capabilities,
            $this->charset,
            $this->status,
            $this->authPlugin
        );
    }
}