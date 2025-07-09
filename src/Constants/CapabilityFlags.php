<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Constants;

/**
 * MySQL client and server capability flags.
 * 
 * These flags define the capabilities supported by the client and server
 * during the MySQL handshake process and determine available features.
 */
class CapabilityFlags
{
    public const CLIENT_LONG_PASSWORD = 0x01;
    public const CLIENT_FOUND_ROWS = 0x02;
    public const CLIENT_LONG_FLAG = 0x04;
    public const CLIENT_CONNECT_WITH_DB = 0x08;
    public const CLIENT_NO_SCHEMA = 0x10;
    public const CLIENT_COMPRESS = 0x20;
    public const CLIENT_IGNORE_SPACE = 0x0100;
    public const CLIENT_PROTOCOL_41 = 0x0200;
    public const CLIENT_INTERACTIVE = 0x0400;
    public const CLIENT_SSL = 0x0800;
    public const CLIENT_TRANSACTIONS = 0x2000;
    public const CLIENT_SECURE_CONNECTION = 0x8000;
    public const CLIENT_MULTI_STATEMENTS = 0x010000;
    public const CLIENT_MULTI_RESULTS = 0x020000;
    public const CLIENT_PS_MULTI_RESULTS = 0x040000;
    public const CLIENT_PLUGIN_AUTH = 0x080000;
    public const CLIENT_CONNECT_ATTRS = 0x100000;
    public const CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA = 0x200000;
    public const CLIENT_SESSION_TRACK = 0x800000;
    public const CLIENT_DEPRECATE_EOF = 0x01000000;
}