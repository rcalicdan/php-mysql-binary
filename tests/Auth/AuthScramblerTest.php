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

test('scrambles using RSA encryption for caching_sha2_password', function () {
    $keyPair = generateTestRsaKeyPair();

    $password = 'test_password';
    $scramble = str_repeat('a', 20);

    $encrypted = AuthScrambler::scrambleSha256Rsa($password, $scramble, $keyPair['public_key_pem']);

    expect(strlen($encrypted))->toBe(256);

    openssl_private_decrypt($encrypted, $decrypted, $keyPair['resource'], OPENSSL_PKCS1_OAEP_PADDING);

    $expectedMessage = ($password . "\0") ^ str_pad($scramble, strlen($password) + 1, $scramble);

    expect($decrypted)->toBe($expectedMessage);
});

test('throws exception when openssl extension is not loaded', function () {
    if (! extension_loaded('openssl')) {
        expect(fn () => AuthScrambler::scrambleSha256Rsa('password', 'nonce', 'fake_key'))
            ->toThrow(RuntimeException::class, 'The openssl extension is required')
        ;
    }
})->skip(extension_loaded('openssl'), 'OpenSSL extension is loaded, cannot test missing extension');

test('throws exception on invalid public key', function () {
    $password = 'test_password';
    $scramble = str_repeat('a', 20);
    $invalidKey = 'not a valid public key';

    $suppressWarnings = function (callable $callback) {
        set_error_handler(fn () => true);

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    };

    $suppressWarnings(function () use ($password, $scramble, $invalidKey) {
        expect(fn () => AuthScrambler::scrambleSha256Rsa($password, $scramble, $invalidKey))
            ->toThrow(RuntimeException::class, 'Failed to encrypt password with public key')
        ;
    });
});

test('handles empty password in RSA scrambling', function () {
    $keyPair = generateTestRsaKeyPair();

    $password = '';
    $scramble = str_repeat('a', 20);

    $encrypted = AuthScrambler::scrambleSha256Rsa($password, $scramble, $keyPair['public_key_pem']);

    expect(strlen($encrypted))->toBe(256);

    openssl_private_decrypt($encrypted, $decrypted, $keyPair['resource'], OPENSSL_PKCS1_OAEP_PADDING);

    $expectedMessage = "\0" ^ substr($scramble, 0, 1);
    expect($decrypted)->toBe($expectedMessage);
});

test('different passwords produce different encrypted results', function () {
    $keyPair = generateTestRsaKeyPair();

    $scramble = str_repeat('a', 20);

    $encrypted1 = AuthScrambler::scrambleSha256Rsa('password1', $scramble, $keyPair['public_key_pem']);
    $encrypted2 = AuthScrambler::scrambleSha256Rsa('password2', $scramble, $keyPair['public_key_pem']);

    expect($encrypted1)->not->toBe($encrypted2);
});

test('XOR padding works correctly for different password lengths', function () {
    $keyPair = generateTestRsaKeyPair();

    $shortPassword = 'abc';
    $longPassword = 'this_is_a_much_longer_password_string';
    $scramble = str_repeat('x', 20);

    $encrypted1 = AuthScrambler::scrambleSha256Rsa($shortPassword, $scramble, $keyPair['public_key_pem']);
    openssl_private_decrypt($encrypted1, $decrypted1, $keyPair['resource'], OPENSSL_PKCS1_OAEP_PADDING);
    $expected1 = ($shortPassword . "\0") ^ str_pad($scramble, strlen($shortPassword) + 1, $scramble);
    expect($decrypted1)->toBe($expected1);
    $encrypted2 = AuthScrambler::scrambleSha256Rsa($longPassword, $scramble, $keyPair['public_key_pem']);
    openssl_private_decrypt($encrypted2, $decrypted2, $keyPair['resource'], OPENSSL_PKCS1_OAEP_PADDING);
    $expected2 = ($longPassword . "\0") ^ str_pad($scramble, strlen($longPassword) + 1, $scramble);
    expect($decrypted2)->toBe($expected2);
});
