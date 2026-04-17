<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Constants\CapabilityFlags;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\SslRequest;

test('builds SSL request packet with correct structure', function () {
    $caps = CapabilityFlags::CLIENT_PROTOCOL_41;
    $charset = 33;

    $packet = (new SslRequest())->build($caps, $charset);

    $builtCaps = unpack('V', substr($packet, 0, 4))[1];
    expect($builtCaps & CapabilityFlags::CLIENT_SSL)->not->toBe(0);

    expect(unpack('V', substr($packet, 4, 4))[1])->toBe(0x01000000);

    expect(ord($packet[8]))->toBe($charset);

    expect(substr($packet, 9, 23))->toBe(str_repeat("\x00", 23));
    expect(strlen($packet))->toBe(32);
});

test('always sets CLIENT_SSL flag regardless of input capabilities', function () {
    $caps = 0;

    $packet = (new SslRequest())->build($caps, 33);

    $builtCaps = unpack('V', substr($packet, 0, 4))[1];
    expect($builtCaps & CapabilityFlags::CLIENT_SSL)->not->toBe(0);
});

test('preserves other capability flags alongside CLIENT_SSL', function () {
    $caps = CapabilityFlags::CLIENT_PROTOCOL_41 | CapabilityFlags::CLIENT_SECURE_CONNECTION;

    $packet = (new SslRequest())->build($caps, 33);

    $builtCaps = unpack('V', substr($packet, 0, 4))[1];
    expect($builtCaps & CapabilityFlags::CLIENT_PROTOCOL_41)->not->toBe(0)
        ->and($builtCaps & CapabilityFlags::CLIENT_SECURE_CONNECTION)->not->toBe(0)
        ->and($builtCaps & CapabilityFlags::CLIENT_SSL)->not->toBe(0)
    ;
});
