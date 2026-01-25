<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Constants\CapabilityFlags;
use Rcalicdan\MySQLBinaryProtocol\Constants\CharsetIdentifiers;
use Rcalicdan\MySQLBinaryProtocol\Constants\StatusFlags;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\HandshakeV10;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\HandshakeV10Builder;

beforeEach(function () {
    $this->builder = new HandshakeV10Builder();
});

test('creates handshake with minimal data', function () {
    $expected = new HandshakeV10(
        '5.5.1',
        10,
        "\x01\x02\x03\x04\x05\x06\x07\x08",
        CapabilityFlags::CLIENT_PROTOCOL_41
    );

    $result = $this->builder->withServerInfo('5.5.1', 10)
        ->withAuthData("\x01\x02\x03\x04\x05\x06\x07\x08")
        ->withCapabilities(CapabilityFlags::CLIENT_PROTOCOL_41)
        ->build()
    ;

    expect($result)->toEqual($expected);
});

test('creates handshake with server status and charset', function () {
    $expected = new HandshakeV10(
        '1.0.0',
        1,
        'thisisstringdata',
        0,
        CharsetIdentifiers::UTF8,
        StatusFlags::SERVER_STATUS_AUTOCOMMIT
    );

    $result = $this->builder->withServerInfo('1.0.0', 1)
        ->withAuthData('thisisstringdata')
        ->withCharset(CharsetIdentifiers::UTF8)
        ->withStatus(StatusFlags::SERVER_STATUS_AUTOCOMMIT)
        ->build()
    ;

    expect($result)->toEqual($expected);
});

test('creates handshake with auth plugin specified', function () {
    $expected = new HandshakeV10(
        '1.0.0',
        1,
        'thisisstringdata',
        0,
        0,
        0,
        'mysql_native_password'
    );

    $result = $this->builder->withServerInfo('1.0.0', 1)
        ->withAuthData('thisisstringdata')
        ->withAuthPlugin('mysql_native_password')
        ->build()
    ;

    expect($result)->toEqual($expected);
});
