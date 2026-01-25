<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Constants\CapabilityFlags;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\HandshakeResponse41;

beforeEach(function () {
    $this->builder = new HandshakeResponse41();
});

test('builds minimal handshake response', function () {
    $caps = CapabilityFlags::CLIENT_PROTOCOL_41 | CapabilityFlags::CLIENT_SECURE_CONNECTION;
    $charset = 33;
    $user = 'root';
    $auth = 'scramble';

    $packet = $this->builder->build($caps, $charset, $user, $auth);

    expect(unpack('V', substr($packet, 0, 4))[1])->toBe($caps);

    expect(unpack('V', substr($packet, 4, 4))[1])->toBe(0x01000000);

    expect(ord($packet[8]))->toBe(33);

    expect(substr($packet, 9, 23))->toBe(str_repeat("\x00", 23));

    expect(substr($packet, 32, 5))->toBe("root\x00");

    expect(ord($packet[37]))->toBe(8)
        ->and(substr($packet, 38, 8))->toBe('scramble')
    ;
});

test('builds handshake response with database', function () {
    $caps = CapabilityFlags::CLIENT_PROTOCOL_41
          | CapabilityFlags::CLIENT_SECURE_CONNECTION
          | CapabilityFlags::CLIENT_CONNECT_WITH_DB;

    $user = 'root';
    $auth = 'scramble';
    $db = 'my_db';

    $packet = $this->builder->build($caps, 33, $user, $auth, $db);

    expect(substr($packet, 46))->toBe("my_db\x00");
});

test('builds handshake response with auth plugin', function () {
    $caps = CapabilityFlags::CLIENT_PROTOCOL_41
          | CapabilityFlags::CLIENT_SECURE_CONNECTION
          | CapabilityFlags::CLIENT_PLUGIN_AUTH;

    $user = 'root';
    $auth = 'scramble';
    $plugin = 'mysql_native_password';

    $packet = $this->builder->build($caps, 33, $user, $auth, '', $plugin);

    expect(substr($packet, 46))->toBe("mysql_native_password\x00");
});

test('uses length encoded integer for auth when flag is set', function () {
    $caps = CapabilityFlags::CLIENT_PROTOCOL_41
          | CapabilityFlags::CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA;

    $auth = str_repeat('a', 300);

    $packet = $this->builder->build($caps, 33, 'user', $auth);

    expect(substr($packet, 37, 1))->toBe("\xFC")
        ->and(substr($packet, 38, 2))->toBe("\x2C\x01")
    ;
});
