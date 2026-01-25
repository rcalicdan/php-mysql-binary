<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Auth;

class AuthScrambler
{
    /**
     * Scrambles password using MySQL Native Password (SHA1 based).
     * Used for MySQL 5.7 and legacy connections.
     *
     * Algorithm: SHA1(password) XOR SHA1(nonce + SHA1(SHA1(password)))
     */
    public static function scrambleNativePassword(string $password, string $nonce): string
    {
        if ($password === '') {
            return '';
        }

        $stage1 = sha1($password, true);
        $stage2 = sha1($stage1, true);
        $stage3 = sha1($nonce . $stage2, true);

        return $stage1 ^ $stage3;
    }

    /**
     * Scrambles password using Caching SHA2 Password (SHA256 based).
     * Used for MySQL 8.0+.
     *
     * Algorithm: XOR(SHA256(password), SHA256(SHA256(SHA256(password)) + nonce))
     */
    public static function scrambleCachingSha2Password(string $password, string $nonce): string
    {
        if ($password === '') {
            return '';
        }

        $hash1 = hash('sha256', $password, true);
        $hash2 = hash('sha256', $hash1, true);
        $hash3 = hash('sha256', $hash2 . $nonce, true);

        $scrambled = '';
        $length = \strlen($hash1);
        for ($i = 0; $i < $length; $i++) {
            $scrambled .= \chr(\ord($hash1[$i]) ^ \ord($hash3[$i]));
        }

        return $scrambled;
    }

    /**
     * Scrambles password using RSA encryption for Caching SHA2 Password method.
     */
    public static function scrambleSha256Rsa(string $password, string $scramble, string $publicKeyPem): string
    {
        if (!extension_loaded('openssl')) {
            throw new \RuntimeException('The openssl extension is required for caching_sha2_password.');
        }

        $message = ($password . "\0") ^ str_pad($scramble, \strlen($password) + 1, $scramble);

        if (!openssl_public_encrypt($message, $encrypted, $publicKeyPem, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new \RuntimeException('Failed to encrypt password with public key.');
        }

        return $encrypted;
    }
}
