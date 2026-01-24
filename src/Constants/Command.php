<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Constants;

/**
 * MySQL Protocol Command Bytes.
 * 
 * Used in the header of Command Packets sent by the client to the server.
 */
final class Command
{
    public const int SLEEP = 0x00;
    public const int QUIT = 0x01;
    public const int INIT_DB = 0x02;
    public const int QUERY = 0x03;
    public const int FIELD_LIST = 0x04;
    public const int CREATE_DB = 0x05;
    public const int DROP_DB = 0x06;
    public const int REFRESH = 0x07;
    public const int SHUTDOWN = 0x08;
    public const int STATISTICS = 0x09;
    public const int PROCESS_INFO = 0x0A;
    public const int CONNECT = 0x0B;
    public const int PROCESS_KILL = 0x0C;
    public const int DEBUG = 0x0D;
    public const int PING = 0x0E;
    public const int TIME = 0x0F;
    public const int DELAYED_INSERT = 0x10;
    public const int CHANGE_USER = 0x11;
    public const int STMT_PREPARE = 0x16;
    public const int STMT_EXECUTE = 0x17;
    public const int STMT_SEND_LONG_DATA = 0x18;
    public const int STMT_CLOSE = 0x19;
    public const int STMT_RESET = 0x1A;
    public const int SET_OPTION = 0x1B;
    public const int STMT_FETCH = 0x1C;
}