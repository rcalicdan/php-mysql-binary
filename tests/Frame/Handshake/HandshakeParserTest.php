<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Constants\CapabilityFlags;
use Rcalicdan\MySQLBinaryProtocol\Constants\CharsetIdentifiers;
use Rcalicdan\MySQLBinaryProtocol\Constants\StatusFlags;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\HandshakeParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\HandshakeV10;

test('parses MySQL 5.7 handshake with mysql_native_password', function () {
    $capabilities = CapabilityFlags::CLIENT_PROTOCOL_41
        | CapabilityFlags::CLIENT_SECURE_CONNECTION
        | CapabilityFlags::CLIENT_PLUGIN_AUTH
        | CapabilityFlags::CLIENT_LONG_PASSWORD
        | CapabilityFlags::CLIENT_TRANSACTIONS;

    $payloadData = "\x0a";

    $payloadData .= "5.7.44\x00";

    $payloadData .= pack('V', 42);

    $payloadData .= 'abcdefgh';

    $payloadData .= "\x00";

    $payloadData .= pack('v', $capabilities & 0xFFFF);

    $payloadData .= pack('C', CharsetIdentifiers::UTF8);

    $payloadData .= pack('v', StatusFlags::SERVER_STATUS_AUTOCOMMIT);

    $payloadData .= pack('v', ($capabilities >> 16) & 0xFFFF);

    $payloadData .= "\x15";

    $payloadData .= str_repeat("\x00", 10);

    $payloadData .= 'ijklmnopqrstu';

    $payloadData .= "mysql_native_password\x00";

    $reader = createReader($payloadData);

    $parser = new HandshakeParser();
    /** @var HandshakeV10 $handshake */
    $handshake = $parser->parse($reader, strlen($payloadData), 0);

    expect($handshake)->toBeInstanceOf(HandshakeV10::class)
        ->and($handshake->serverVersion)->toBe('5.7.44')
        ->and($handshake->connectionId)->toBe(42)
        ->and($handshake->authData)->toBe('abcdefghijklmnopqrstu')
        ->and($handshake->charset)->toBe(CharsetIdentifiers::UTF8)
        ->and($handshake->status)->toBe(StatusFlags::SERVER_STATUS_AUTOCOMMIT)
        ->and($handshake->authPlugin)->toBe('mysql_native_password')
        ->and($handshake->sequenceNumber)->toBe(0)
    ;
});

test('parses MySQL 8.0 handshake with caching_sha2_password', function () {
    $capabilities = CapabilityFlags::CLIENT_PROTOCOL_41
        | CapabilityFlags::CLIENT_SECURE_CONNECTION
        | CapabilityFlags::CLIENT_PLUGIN_AUTH
        | CapabilityFlags::CLIENT_DEPRECATE_EOF;

    $payloadData = "\x0a";
    $payloadData .= "8.0.35\x00";
    $payloadData .= pack('V', 100);
    $payloadData .= '12345678';
    $payloadData .= "\x00";
    $payloadData .= pack('v', $capabilities & 0xFFFF);
    $payloadData .= pack('C', CharsetIdentifiers::UTF8MB4);
    $payloadData .= pack('v', StatusFlags::SERVER_STATUS_AUTOCOMMIT);
    $payloadData .= pack('v', ($capabilities >> 16) & 0xFFFF);
    $payloadData .= "\x15";
    $payloadData .= str_repeat("\x00", 10);
    $payloadData .= '90abcdefghijk';
    $payloadData .= "caching_sha2_password\x00";

    $reader = createReader($payloadData);

    /** @var HandshakeV10 $handshake */
    $handshake = (new HandshakeParser())->parse($reader, strlen($payloadData), 0);

    expect($handshake->serverVersion)->toBe('8.0.35')
        ->and($handshake->connectionId)->toBe(100)
        ->and($handshake->authData)->toBe('1234567890abcdefghijk')
        ->and($handshake->charset)->toBe(CharsetIdentifiers::UTF8MB4)
        ->and($handshake->authPlugin)->toBe('caching_sha2_password')
    ;
});

test('parses legacy handshake without CLIENT_PLUGIN_AUTH', function () {
    $capabilities = CapabilityFlags::CLIENT_PROTOCOL_41
        | CapabilityFlags::CLIENT_SECURE_CONNECTION
        | CapabilityFlags::CLIENT_LONG_PASSWORD;

    $payloadData = "\x0a";
    $payloadData .= "5.6.51\x00";
    $payloadData .= pack('V', 10);
    $payloadData .= 'testauth';
    $payloadData .= "\x00";
    $payloadData .= pack('v', $capabilities & 0xFFFF);
    $payloadData .= pack('C', CharsetIdentifiers::UTF8);
    $payloadData .= pack('v', StatusFlags::SERVER_STATUS_AUTOCOMMIT);
    $payloadData .= pack('v', ($capabilities >> 16) & 0xFFFF);
    $payloadData .= "\x00";
    $payloadData .= str_repeat("\x00", 10);
    $payloadData .= "moreauthdata\x00";

    $reader = createReader($payloadData);

    /** @var HandshakeV10 $handshake */
    $handshake = (new HandshakeParser())->parse($reader, strlen($payloadData), 0);

    expect($handshake->serverVersion)->toBe('5.6.51')
        ->and($handshake->connectionId)->toBe(10)
        ->and($handshake->authData)->toBe('testauthmoreauthdata')
        ->and($handshake->authPlugin)->toBe('mysql_native_password') // default
    ;
});

test('parses pre-4.1 legacy handshake', function () {
    $capabilities = CapabilityFlags::CLIENT_LONG_PASSWORD;

    $payloadData = "\x0a";
    $payloadData .= "3.23.58\x00";
    $payloadData .= pack('V', 5);
    $payloadData .= 'scramble';
    $payloadData .= "\x00";
    $payloadData .= pack('v', $capabilities & 0xFFFF);
    $payloadData .= "extradata\x00";

    $reader = createReader($payloadData);

    /** @var HandshakeV10 $handshake */
    $handshake = (new HandshakeParser())->parse($reader, strlen($payloadData), 0);

    expect($handshake->serverVersion)->toBe('3.23.58')
        ->and($handshake->connectionId)->toBe(5)
        ->and($handshake->authData)->toBe('scrambleextradata') // null terminator stripped
        ->and($handshake->charset)->toBe(0)
        ->and($handshake->status)->toBe(0)
        ->and($handshake->authPlugin)->toBe('mysql_native_password')
    ;
});
