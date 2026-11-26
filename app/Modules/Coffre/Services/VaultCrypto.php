<?php

namespace App\Modules\Coffre\Services;

use RuntimeException;

/**
 * Envelope encryption: each entry has its own random DEK (XChaCha20-Poly1305) wrapped by the
 * master key (VAULT_MASTER_KEY, never stored in the database).
 */
class VaultCrypto
{
    private const AAD = 'nocline-vault-v1';

    public function isConfigured(): bool
    {
        try {
            $this->masterKey();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function newDek(): string
    {
        return random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
    }

    public function wrapDek(string $dek): string
    {
        return $this->encrypt($dek, $this->masterKey());
    }

    public function unwrapDek(string $wrapped): string
    {
        return $this->decrypt($wrapped, $this->masterKey());
    }

    public function encrypt(string $plaintext, string $key): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, self::AAD, $nonce, $key);

        return base64_encode($nonce.$cipher);
    }

    public function decrypt(string $payload, string $key): string
    {
        $raw = base64_decode($payload, true);
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

        if ($raw === false || strlen($raw) <= $nonceLength) {
            throw new RuntimeException('Invalid vault payload.');
        }

        $cipher = substr($raw, $nonceLength);
        $nonce = substr($raw, 0, $nonceLength);

        foreach (self::decryptAads() as $aad) {
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, $aad, $nonce, $key);

            if ($plain !== false) {
                return $plain;
            }
        }

        throw new RuntimeException('Vault decryption failed (wrong key or tampered data).');
    }

    /**
     * @return list<non-empty-string>
     */
    private static function decryptAads(): array
    {
        /** @var non-empty-string $legacy */
        $legacy = pack('H*', '70686172652d7661756c742d7631');

        return [self::AAD, $legacy];
    }

    private function masterKey(): string
    {
        $key = base64_decode((string) config('vault.master_key'), true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new RuntimeException('VAULT_MASTER_KEY is missing or invalid (expected 32 random bytes, base64).');
        }

        if (hash_equals($key, (string) base64_decode(substr((string) config('app.key'), 7), true))) {
            throw new RuntimeException('VAULT_MASTER_KEY must differ from APP_KEY.');
        }

        return $key;
    }
}
