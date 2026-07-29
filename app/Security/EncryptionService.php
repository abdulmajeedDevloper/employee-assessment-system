<?php

namespace App\Security;

use RuntimeException;
use InvalidArgumentException;

class EncryptionService
{
    private string $key;
    private int $keyVersion;

    public function __construct(?string $hexKey = null, int $keyVersion = 1)
    {
        $rawKey = $hexKey ?? ($_ENV['SECURITY_AES_KEY'] ?? '');
        if (empty($rawKey)) {
            throw new InvalidArgumentException("Encryption key cannot be empty.");
        }

        // If key is hex-encoded 64 characters, convert to 32 bytes binary
        if (ctype_xdigit($rawKey) && strlen($rawKey) === 64) {
            $this->key = hex2bin($rawKey);
        } else {
            $this->key = hash('sha256', $rawKey, true);
        }

        $this->keyVersion = $keyVersion;
    }

    /**
     * Encrypt plaintext string using AES-256-GCM.
     *
     * @param string $plaintext
     * @return array{ciphertext: string, iv: string, tag: string, key_version: int}
     */
    public function encrypt(string $plaintext): array
    {
        $iv = openssl_random_pseudo_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException("Encryption failed.");
        }

        return [
            'ciphertext' => base64_encode($ciphertext),
            'iv' => $iv, // Binary 12 bytes
            'tag' => $tag, // Binary 16 bytes
            'key_version' => $this->keyVersion,
        ];
    }

    /**
     * Decrypt AES-256-GCM ciphertext array.
     *
     * @param string $ciphertextBase64
     * @param string $ivBinary
     * @param string $tagBinary
     * @return string
     */
    public function decrypt(string $ciphertextBase64, string $ivBinary, string $tagBinary): string
    {
        $ciphertext = base64_decode($ciphertextBase64, true);
        if ($ciphertext === false) {
            throw new InvalidArgumentException("Invalid base64 ciphertext.");
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $ivBinary,
            $tagBinary
        );

        if ($plaintext === false) {
            throw new RuntimeException("Decryption failed. Authentication tag or key mismatch.");
        }

        return $plaintext;
    }
}
