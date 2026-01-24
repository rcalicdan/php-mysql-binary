<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Constants;

/**
 * Defines the type identifiers for parameters in prepared statements.
 * These are sent as part of the COM_STMT_EXECUTE packet.
 */
final class MysqlType
{
    public const int DECIMAL = 0x00;
    public const int TINY = 0x01;
    public const int SHORT = 0x02;
    public const int LONG = 0x03;
    public const int FLOAT = 0x04;
    public const int DOUBLE = 0x05;
    public const int NULL = 0x06;
    public const int TIMESTAMP = 0x07;
    public const int LONGLONG = 0x08;
    public const int INT24 = 0x09;
    public const int DATE = 0x0a;
    public const int TIME = 0x0b;
    public const int DATETIME = 0x0c;
    public const int YEAR = 0x0d;
    public const int VARCHAR = 0x0f;
    public const int BIT = 0x10;
    public const int JSON = 0xf5;
    public const int NEWDECIMAL = 0xf6;
    public const int ENUM = 0xf7;
    public const int SET = 0xf8;
    public const int TINY_BLOB = 0xf9;
    public const int MEDIUM_BLOB = 0xfa;
    public const int LONG_BLOB = 0xfb;
    public const int BLOB = 0xfc;
    public const int VAR_STRING = 0xfd;
    public const int STRING = 0xfe;
    public const int GEOMETRY = 0xff;
}