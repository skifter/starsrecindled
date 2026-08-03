<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final readonly class AccountCredentialVault
{
    public function __construct(private ParameterBagInterface $parameters)
    {
    }

    public function encrypt(string $plainText): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = sodium_crypto_secretbox($plainText, $nonce, $this->key());

        return base64_encode($nonce.$cipherText);
    }

    public function decrypt(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);
        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Stored player credential is malformed.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plainText = sodium_crypto_secretbox_open($cipherText, $nonce, $this->key());
        if ($plainText === false) {
            throw new \RuntimeException('Stored player credential could not be decrypted.');
        }

        return $plainText;
    }

    private function key(): string
    {
        $secret = (string) $this->parameters->get('kernel.secret');
        if ($secret === '') {
            throw new \RuntimeException('kernel.secret must be configured.');
        }

        // Same derivation as the first account-login implementation, so existing links remain readable.
        return hash('sha256', 'stars-account-token:'.$secret, true);
    }
}
