<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Auth\AuthScrambler;

test('scrambles using mysql_native_password (SHA1)', function () {
    $password = 'password';
    $nonce = '12345678901234567890';

    $scramble = AuthScrambler::scrambleNativePassword($password, $nonce);

    expect(strlen($scramble))->toBe(20);

    $scramble2 = AuthScrambler::scrambleNativePassword($password, $nonce);

    expect($scramble)->toBe($scramble2);
});

test('returns empty string for empty password in native password', function () {
    expect(AuthScrambler::scrambleNativePassword('', 'nonce'))->toBe('');
});

test('scrambles using caching_sha2_password', function () {
    $password = 'password';
    $nonce = str_repeat('a', 20);

    $scramble = AuthScrambler::scrambleCachingSha2Password($password, $nonce);

    expect(strlen($scramble))->toBe(32);

    $scramble2 = AuthScrambler::scrambleCachingSha2Password($password, $nonce);

    expect($scramble)->toBe($scramble2);
});

test('returns empty string for empty password in sha2', function () {
    expect(AuthScrambler::scrambleCachingSha2Password('', 'nonce'))->toBe('');
});
