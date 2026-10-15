<?php

namespace App\Services;

use BitWasp\Bitcoin\Address\AddressCreator;
use BitWasp\Bitcoin\Address\SegwitAddress;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Key\Factory\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Bitcoin\Script\WitnessProgram;
use BitWasp\Bitcoin\Transaction\Factory\Signer;
use BitWasp\Bitcoin\Transaction\TransactionFactory;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Buffertools\Buffer;
use RuntimeException;

/**
 * Seed phrase (any text) --Argon2id--> 64-byte seed --BIP32/BIP84--> native segwit key.
 * Keys exist only in memory for the duration of a request.
 */
class WalletService
{
    public const DUST = 294;

    public function normalize(string $phrase): string
    {
        $phrase = preg_replace('/\s+/u', ' ', trim($phrase));
        if (class_exists(\Normalizer::class)) {
            $phrase = \Normalizer::normalize($phrase, \Normalizer::FORM_KD) ?: $phrase;
        }
        return $phrase;
    }

    /** Seed phrase (any text) -> 64-byte seed. Used once, at wallet creation. */
    public function seedFromPhrase(string $walletName, string $phrase, string $network): string
    {
        // Deterministic salt from wallet name => wallet is recoverable from (name + phrase) alone.
        $salt = substr(hash('sha256', 'btc-local-wallet-v1|' . $network . '|' . mb_strtolower(trim($walletName)), true), 0, SODIUM_CRYPTO_PWHASH_SALTBYTES);

        return sodium_crypto_pwhash(
            64,
            $this->normalize($phrase),
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
    }

    /** @return array{0: \BitWasp\Bitcoin\Key\PrivateKeyInterface, 1: SegwitAddress} */
    public function keysFromSeed(string $seed, string $network): array
    {
        Bitcoin::setNetwork($network === 'mainnet' ? NetworkFactory::bitcoin() : NetworkFactory::bitcoinTestnet());

        $master = (new HierarchicalKeyFactory())->fromEntropy(new Buffer($seed));
        sodium_memzero($seed);

        $coin = $network === 'mainnet' ? 0 : 1;
        $child = $master->derivePath("84'/{$coin}'/0'/0/0");
        $address = new SegwitAddress(WitnessProgram::v0($child->getPublicKey()->getPubKeyHash()));

        return [$child->getPrivateKey(), $address];
    }

    private function passwordKey(string $password, string $salt): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $password,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
    }

    /** @return array{0:string,1:string} [encrypted_seed (base64), kdf_salt (base64)] */
    public function encryptSeed(string $seed, string $password): array
    {
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key = $this->passwordKey($password, $salt);
        $blob = $nonce . sodium_crypto_secretbox($seed, $nonce, $key);
        sodium_memzero($key);

        return [base64_encode($blob), base64_encode($salt)];
    }

    /** Returns the seed, or null if the password is wrong. */
    public function decryptSeed(string $encrypted, string $kdfSalt, string $password): ?string
    {
        $blob = base64_decode($encrypted, true);
        $salt = base64_decode($kdfSalt, true);
        if ($blob === false || $salt === false) {
            return null;
        }

        $key = $this->passwordKey($password, $salt);
        $nonce = substr($blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $seed = sodium_crypto_secretbox_open(substr($blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
        sodium_memzero($key);

        return $seed === false ? null : $seed;
    }

    public function vbytes(int $in, int $out): int
    {
        return 11 + 68 * $in + 31 * $out; // P2WPKH estimate
    }

    public function toSats(string $btc): int
    {
        [$i, $f] = array_pad(explode('.', $btc), 2, '');
        return ((int) $i) * 100_000_000 + (int) str_pad($f, 8, '0');
    }

    /** @return array{hex:string, fee:int, change:int} */
    public function buildSigned($privateKey, SegwitAddress $mine, array $utxos, string $to, int $amount, float $feeRate): array
    {
        if ($amount < self::DUST) {
            throw new RuntimeException('Amount is below the dust limit (' . self::DUST . ' sats).');
        }

        try {
            $dest = (new AddressCreator())->fromString($to);
        } catch (\Throwable $e) {
            throw new RuntimeException('Invalid recipient address for this network.');
        }

        usort($utxos, fn ($a, $b) => $b['value'] <=> $a['value']);

        $selected = [];
        $total = 0;
        $fee = 0;
        foreach ($utxos as $u) {
            $selected[] = $u;
            $total += $u['value'];
            $fee = (int) ceil($this->vbytes(count($selected), 2) * $feeRate);
            if ($total >= $amount + $fee) {
                break;
            }
        }
        if ($total < $amount + $fee) {
            throw new RuntimeException('Insufficient confirmed funds (need amount + fee).');
        }

        $change = $total - $amount - $fee;
        if ($change < self::DUST) { // too small: give it to miners instead of making dust
            $fee = $total - $amount;
            $change = 0;
        }

        $myScript = $mine->getScriptPubKey();
        $b = TransactionFactory::build();
        foreach ($selected as $u) {
            $b->input($u['txid'], $u['vout']);
        }
        $b->output($amount, $dest->getScriptPubKey());
        if ($change > 0) {
            $b->output($change, $myScript);
        }
        $tx = $b->get();

        $signer = new Signer($tx, Bitcoin::getEcAdapter());
        foreach ($selected as $i => $u) {
            $signer->sign($i, $privateKey, new TransactionOutput($u['value'], $myScript));
        }

        return ['hex' => $signer->get()->getHex(), 'fee' => $fee, 'change' => $change];
    }
}
