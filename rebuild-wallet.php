<?php
/**
 * rebuild-wallet.php  —  standalone recovery for the Local BTC Wallet
 *
 * Rebuilds your Bitcoin address (and, if you ask, your private key) from
 *   username + seed phrase + network
 * with NO Composer packages, NO Laravel, NO Bitcoin library. Just this one file.
 *
 * Needs only PHP extensions that ship with PHP itself:
 *   - sodium            (Argon2id)           PHP 7.3+  — required
 *   - gmp OR bcmath     (big-number maths)   at least one — required
 *   - hash              (SHA/HMAC/RIPEMD)    always present
 * Optional: mbstring + intl (only matter if your username/phrase has non-English letters)
 *
 * Usage:
 *   php rebuild-wallet.php              # prints your address, compare it with your wallet
 *   php rebuild-wallet.php --wif        # also lets you reveal the private key (WIF) to import elsewhere
 *   php rebuild-wallet.php --selftest   # proves the maths works on this machine using public test vectors
 *   add --no-gmp to force the BCMath maths (slower, for testing/compatibility)
 *
 * Derivation (identical to the app):
 *   seed  = Argon2id(phrase, salt, ops=3, mem=256MiB, 64 bytes)
 *           phrase normalised: trim, collapse whitespace, Unicode NFKD (if intl present)
 *           salt = first 16 bytes of SHA256("btc-local-wallet-v1|" + network + "|" + lowercase(trim(username)))
 *   key   = BIP32 master from seed, path m/84'/coin'/0'/0/0   (coin 0 = mainnet, 1 = testnet)
 *   addr  = native SegWit (bech32) P2WPKH of the compressed public key
 */

error_reporting(E_ALL);
const ARGON_OPS = 3;
const ARGON_MEM = 268435456; // 256 MiB  (== SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE)
const ARGON_ALG = 2;         // == SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13

/* ------------------------------------------------------------------ *
 *  Big-number backend: GMP if available, otherwise BCMath
 * ------------------------------------------------------------------ */
final class N
{
    public static bool $gmp = false;

    public static function init(bool $allowGmp): void
    {
        self::$gmp = $allowGmp && extension_loaded('gmp');
        if (!self::$gmp && !extension_loaded('bcmath')) {
            fail("Need the 'gmp' or 'bcmath' PHP extension. Install one (Ubuntu: sudo apt install php-gmp).");
        }
    }
    public static function hex(string $h) // hex string -> number
    {
        if (self::$gmp) return gmp_init($h === '' ? '0' : $h, 16);
        $r = '0';
        foreach (str_split(strtolower($h)) as $c) $r = bcadd(bcmul($r, '16', 0), (string) hexdec($c), 0);
        return $r;
    }
    public static function toHex($n, int $pad = 64): string
    {
        if (self::$gmp) { $s = gmp_strval($n, 16); }
        else {
            $s = ''; $d = '0123456789abcdef';
            if (bccomp($n, '0') === 0) $s = '0';
            while (bccomp($n, '0') > 0) { $s = $d[(int) bcmod($n, '16')] . $s; $n = bcdiv($n, '16', 0); }
        }
        return str_pad($s, $pad, '0', STR_PAD_LEFT);
    }
    public static function add($a, $b)  { return self::$gmp ? $a + $b : bcadd($a, $b, 0); }
    public static function mul($a, $b)  { return self::$gmp ? $a * $b : bcmul($a, $b, 0); }
    public static function mod($a, $m)  { return self::$gmp ? gmp_mod($a, $m) : bcmod($a, $m); }
    public static function cmp($a, $b): int { return self::$gmp ? gmp_cmp($a, $b) : bccomp($a, $b, 0); }
    public static function isZero($a): bool { return self::cmp($a, self::hex('0')) === 0; }
    public static function bit($n, int $i): int // bit i of n (0 = least significant)
    {
        if (self::$gmp) return gmp_testbit($n, $i) ? 1 : 0;
        return ((int) bcmod(bcdiv($n, bcpow('2', (string) $i, 0), 0), '2'));
    }
    public static function bits($n): int
    {
        return self::$gmp ? strlen(gmp_strval($n, 2)) : strlen(ltrim(self::toHex($n, 64), '0')) * 4;
    }
    public static function inv($a, $p)
    {
        if (self::$gmp) return gmp_invert($a, $p);
        return bcpowmod($a, bcsub($p, '2'), $p); // Fermat (p is prime)
    }
}

/* ------------------------------------------------------------------ *
 *  secp256k1 (Jacobian coordinates; infinity = null)
 * ------------------------------------------------------------------ */
final class Secp
{
    public static $p, $n, $gx, $gy;
    public static function init(): void
    {
        self::$p  = N::hex('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F');
        self::$n  = N::hex('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141');
        self::$gx = N::hex('79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798');
        self::$gy = N::hex('483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8');
    }
    private static function m($a, $b) { return N::mod(N::mul($a, $b), self::$p); }
    private static function sub($a, $b) { return N::mod(N::add($a, N::add(self::$p, self::neg($b))), self::$p); }
    private static function neg($b) { return N::$gmp ? -$b : bcmul($b, '-1', 0); }
    private static function ad($a, $b) { return N::mod(N::add($a, $b), self::$p); }
    private static function k($i) { return N::hex(dechex($i)); }

    private static function dbl(?array $P): ?array
    {
        if ($P === null || N::isZero($P[1])) return null;
        [$X, $Y, $Z] = $P;
        $YY = self::m($Y, $Y);
        $S  = self::m(self::k(4), self::m($X, $YY));
        $M  = self::m(self::k(3), self::m($X, $X));
        $X3 = self::sub(self::m($M, $M), self::m(self::k(2), $S));
        $Y3 = self::sub(self::m($M, self::sub($S, $X3)), self::m(self::k(8), self::m($YY, $YY)));
        $Z3 = self::m(self::k(2), self::m($Y, $Z));
        return [$X3, $Y3, $Z3];
    }
    private static function add(?array $P, ?array $Q): ?array
    {
        if ($P === null) return $Q;
        if ($Q === null) return $P;
        [$X1, $Y1, $Z1] = $P; [$X2, $Y2, $Z2] = $Q;
        $Z1Z1 = self::m($Z1, $Z1); $Z2Z2 = self::m($Z2, $Z2);
        $U1 = self::m($X1, $Z2Z2); $U2 = self::m($X2, $Z1Z1);
        $S1 = self::m($Y1, self::m($Z2, $Z2Z2)); $S2 = self::m($Y2, self::m($Z1, $Z1Z1));
        if (N::cmp($U1, $U2) === 0) return (N::cmp($S1, $S2) === 0) ? self::dbl($P) : null;
        $H = self::sub($U2, $U1); $R = self::sub($S2, $S1);
        $HH = self::m($H, $H); $HHH = self::m($H, $HH); $V = self::m($U1, $HH);
        $X3 = self::sub(self::sub(self::m($R, $R), $HHH), self::m(self::k(2), $V));
        $Y3 = self::sub(self::m($R, self::sub($V, $X3)), self::m($S1, $HHH));
        $Z3 = self::m($H, self::m($Z1, $Z2));
        return [$X3, $Y3, $Z3];
    }
    /** k*G -> [x, y] affine (numbers) */
    public static function mulG($k): array
    {
        $G = [self::$gx, self::$gy, N::hex('1')];
        $R = null;
        for ($i = N::bits($k) - 1; $i >= 0; $i--) {
            $R = self::dbl($R);
            if (N::bit($k, $i)) $R = self::add($R, $G);
        }
        if ($R === null) fail('Invalid key (point at infinity).');
        $zi = N::inv($R[2], self::$p); $zi2 = self::m($zi, $zi);
        return [self::m($R[0], $zi2), self::m($R[1], self::m($zi2, $zi))];
    }
    public static function compressed($k): string // 33-byte binary
    {
        [$x, $y] = self::mulG($k);
        $odd = N::bit($y, 0);
        return hex2bin(($odd ? '03' : '02') . N::toHex($x, 64));
    }
}

/* ------------------------------------------------------------------ *
 *  BIP32 + addresses
 * ------------------------------------------------------------------ */
function bip32_master(string $seed): array
{
    $I = hash_hmac('sha512', $seed, 'Bitcoin seed', true);
    return [substr($I, 0, 32), substr($I, 32)]; // [key(32 bytes), chaincode]
}
function bip32_child(array $parent, int $index): array
{
    [$k, $c] = $parent;
    $hardened = $index >= 0x80000000;
    $data = $hardened ? "\x00" . $k : Secp::compressed(N::hex(bin2hex($k)));
    $data .= pack('N', $index);
    $I  = hash_hmac('sha512', $data, $c, true);
    $il = N::hex(bin2hex(substr($I, 0, 32)));
    if (N::cmp($il, Secp::$n) >= 0) fail('Invalid derivation (IL >= n). Extremely rare — try a different username.');
    $kc = N::mod(N::add($il, N::hex(bin2hex($k))), Secp::$n);
    if (N::isZero($kc)) fail('Invalid derivation (zero key).');
    return [hex2bin(N::toHex($kc, 64)), substr($I, 32)];
}
function derive_path(string $seed, int $coin): array
{
    $H = 0x80000000;
    $node = bip32_master($seed);
    foreach ([84 + $H, $coin + $H, 0 + $H, 0, 0] as $i) $node = bip32_child($node, $i);
    return $node;
}
function hash160(string $b): string { return hash('ripemd160', hash('sha256', $b, true), true); }

function bech32_polymod(array $v): int
{
    $G = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];
    $chk = 1;
    foreach ($v as $x) {
        $top = $chk >> 25;
        $chk = (($chk & 0x1ffffff) << 5) ^ $x;
        for ($i = 0; $i < 5; $i++) if (($top >> $i) & 1) $chk ^= $G[$i];
    }
    return $chk;
}
function bech32_p2wpkh(string $hrp, string $prog20): string
{
    $cs = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
    $bits = ''; foreach (str_split($prog20) as $ch) $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
    $data = [0];
    foreach (str_split(str_pad($bits, (int) (ceil(strlen($bits) / 5) * 5), '0'), 5) as $g) $data[] = bindec($g);
    $hrpExp = []; for ($i = 0; $i < strlen($hrp); $i++) $hrpExp[] = ord($hrp[$i]) >> 5;
    $hrpExp[] = 0; for ($i = 0; $i < strlen($hrp); $i++) $hrpExp[] = ord($hrp[$i]) & 31;
    $mod = bech32_polymod(array_merge($hrpExp, $data, [0, 0, 0, 0, 0, 0])) ^ 1;
    for ($i = 0; $i < 6; $i++) $data[] = ($mod >> (5 * (5 - $i))) & 31;
    $out = $hrp . '1'; foreach ($data as $d) $out .= $cs[$d];
    return $out;
}
function base58(string $bin): string
{
    $alpha = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $digits = array_values(unpack('C*', $bin)); $out = '';
    $zeros = 0; while ($zeros < count($digits) && $digits[$zeros] === 0) $zeros++;
    $start = $zeros;
    while ($start < count($digits)) {
        $rem = 0;
        for ($i = $start; $i < count($digits); $i++) {
            $cur = $rem * 256 + $digits[$i];
            $digits[$i] = intdiv($cur, 58); $rem = $cur % 58;
        }
        $out = $alpha[$rem] . $out;
        while ($start < count($digits) && $digits[$start] === 0) $start++;
    }
    return str_repeat('1', $zeros) . $out;
}
function wif(string $key32, bool $mainnet): string
{
    $p = chr($mainnet ? 0x80 : 0xEF) . $key32 . "\x01";
    return base58($p . substr(hash('sha256', hash('sha256', $p, true), true), 0, 4));
}
function address_for(string $key32, string $network): string
{
    $pub = Secp::compressed(N::hex(bin2hex($key32)));
    return bech32_p2wpkh($network === 'mainnet' ? 'bc' : 'tb', hash160($pub));
}

/* ------------------------------------------------------------------ *
 *  Seed from username + phrase (matches the app exactly)
 * ------------------------------------------------------------------ */
function normalize_phrase(string $p): string
{
    $p = preg_replace('/\s+/u', ' ', trim($p));
    if (class_exists('Normalizer')) $p = Normalizer::normalize($p, Normalizer::FORM_KD) ?: $p;
    return $p;
}

function canon(string $s): string
{
    if (class_exists('Normalizer')) $s = Normalizer::normalize($s, Normalizer::FORM_KD) ?: $s;
    $s = preg_replace('/[\x{2018}-\x{201B}]/u', "'", $s);          // curly single quotes
    $s = preg_replace('/[\x{201C}-\x{201E}]/u', '"', $s);          // curly double quotes
    $s = preg_replace('/[\x{2013}\x{2014}\x{2212}]/u', '-', $s);   // en/em dash, minus sign
    $s = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $s); // invisible characters
    return trim(preg_replace('/\s+/u', ' ', $s));
}

function seed_from_phrase(string $user, string $phrase, string $network): string
{
    if (!function_exists('sodium_crypto_pwhash')) fail("The 'sodium' PHP extension is required (built into PHP 7.3+).");
    $u     = canon($user);
    $lower = function_exists('mb_strtolower') ? mb_strtolower($u) : strtolower($u);
    $salt  = substr(hash('sha256', 'btc-local-wallet-v1|' . $network . '|' . $lower, true), 0, 16);
    return sodium_crypto_pwhash(64, canon($phrase), $salt, ARGON_OPS, ARGON_MEM, ARGON_ALG);
}

/* ------------------------------------------------------------------ *
 *  CLI helpers
 * ------------------------------------------------------------------ */
function fail(string $m): void { fwrite(STDERR, "\nERROR: $m\n"); exit(1); }
function ask(string $label, bool $hidden = false): string
{
    echo $label;
    $tty = $hidden && DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec')
        && function_exists('posix_isatty') && posix_isatty(STDIN);
    if ($tty) shell_exec('stty -echo');
    $line = fgets(STDIN);
    if ($tty) { shell_exec('stty echo'); echo "\n"; }
    return $line === false ? '' : rtrim($line, "\r\n");
}

function selftest(): void
{
    $ok = true;
    $chk = function (string $name, $got, $want) use (&$ok) {
        $pass = ($got === $want); $ok = $ok && $pass;
        echo ($pass ? '  PASS  ' : '  FAIL  ') . $name . ($pass ? '' : "\n         got:  $got\n         want: $want") . "\n";
    };
    echo "Self-test (" . (N::$gmp ? 'GMP' : 'BCMath') . " maths, PHP " . PHP_VERSION . ")\n";

    // Public BIP84 test vector: mnemonic "abandon x11 about", empty passphrase, m/84'/0'/0'/0/0
    $mn   = 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about';
    $seed = hex2bin(hash_pbkdf2('sha512', $mn, 'mnemonic', 2048, 128, false));
    $node = derive_path($seed, 0);
    $chk('BIP84 mainnet pubkey', bin2hex(Secp::compressed(N::hex(bin2hex($node[0])))), '0330d54fd0dd420a6e5f8d3624f5f3482cae350f79d5f0753bf5beef9c2d91af3c');
    $chk('BIP84 mainnet address', address_for($node[0], 'mainnet'), 'bc1qcr8te4kr609gcawutmrza0j4xv80jy8z306fyu');

    // Private key = 1 (well-known WIF values)
    $one = str_repeat("\x00", 31) . "\x01";
    $chk('WIF mainnet (key=1)', wif($one, true), 'KwDiBf89QgGbjEhKnhXJuH7LrciVrZi3qYjgd9M7rFU73sVHnoWn');
    $chk('WIF testnet (key=1)', wif($one, false), 'cMahea7zqjxrtgAbB7LSGbcQUr1uX1ojuat9jZodMN87JcbXMTcA');
    // Key = 1 -> public key is the generator point G
    $chk('Generator point', bin2hex(Secp::compressed(N::hex('1'))), '0279be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798');

    echo $ok ? "\nALL TESTS PASSED — the maths works on this machine.\n" : "\nSOME TESTS FAILED — do not trust this machine/PHP build for recovery.\n";
    exit($ok ? 0 : 1);
}

/* ------------------------------------------------------------------ *
 *  Main
 * ------------------------------------------------------------------ */
if (PHP_SAPI !== 'cli') { exit("Run this from a terminal: php rebuild-wallet.php\n"); }
$args = $argv ?? [];
N::init(!in_array('--no-gmp', $args, true));
Secp::init();
if (in_array('--selftest', $args, true)) selftest();

echo "Local BTC Wallet — standalone recovery  (maths: " . (N::$gmp ? 'GMP' : 'BCMath') . ")\n\n";
$net = strtolower(trim(ask('Network [mainnet/testnet] (default mainnet): ')));
$net = ($net === '' || $net === 'mainnet' || $net === 'm') ? 'mainnet' : (($net === 'testnet' || $net === 't') ? 'testnet' : fail('Network must be mainnet or testnet.'));
$user = ask('Username (wallet name): ');
$phrase = ask('Seed phrase (hidden while typing): ', true);
if ($user === '' || $phrase === '') fail('Username and phrase are required.');
$expected = trim(ask('Expected address (optional, press Enter to skip): '));

if ((preg_match('/[^\x20-\x7e]/', $user . $phrase)) && (!class_exists('Normalizer') || !function_exists('mb_strtolower'))) {
    echo "\nWARNING: your text has non-English characters but this PHP lacks the intl/mbstring extension.\n"
       . "         If the address does not match your wallet, run this on a PHP that has both.\n";
}

echo "\nDeriving key (Argon2id, 256 MiB — takes a few seconds)...\n";
$seed = seed_from_phrase($user, $phrase, $net);
$node = derive_path($seed, $net === 'mainnet' ? 0 : 1);
$addr = address_for($node[0], $net);

echo "\nAddress ($net): $addr\n";
if ($expected !== '') echo (strcasecmp($expected, $addr) === 0) ? "MATCH — this is your wallet's address.\n" : "MISMATCH — check username, phrase (exact characters), and network.\n";

if (in_array('--wif', $args, true)) {
    echo "\nThe private key controls your coins. Anyone who sees it can take them.\nMake sure nobody is watching and no screen recording is running.\n";
    if (strtolower(trim(ask("Type 'show key' to reveal it: "))) === 'show key') {
        echo "\nPrivate key (WIF, compressed): " . wif($node[0], $net === 'mainnet') . "\n";
        echo "Raw key (hex):                 " . bin2hex($node[0]) . "\n";
        echo "\nImport into Electrum as:  p2wpkh:<WIF>     (Sparrow: Import → Private key, script type Native SegWit)\n";
        echo "Clear your terminal afterwards (Ctrl+L, and clear scrollback).\n";
    }
}