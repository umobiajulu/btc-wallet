<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Services\ChainService;
use App\Services\WalletService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private WalletService $wallets, private ChainService $chain) {}

    private function current(Request $r): Wallet
    {
        return Wallet::findOrFail($r->session()->get('wallet_id'));
    }

    /** Decrypt the stored seed with the password. Returns [privateKey, address] or null if wrong password. */
    private function unlock(Wallet $w, string $password): ?array
    {
        $seed = $this->wallets->decryptSeed($w->encrypted_seed, $w->kdf_salt, $password);
        if ($seed === null) {
            return null;
        }
        $keys = $this->wallets->keysFromSeed($seed, $w->network);

        return hash_equals($w->address, $keys[1]->getAddress()) ? $keys : null;
    }

    // ---------- create / login / logout ----------

    public function showCreate()
    {
        return view('create', ['network' => config('wallet.network')]);
    }

    public function create(Request $r)
    {
        $d = $r->validate([
            'name'     => 'required|string|max:50|unique:wallets,name',
            'phrase'   => 'required|string|min:25|confirmed',
            'password' => 'required|string|min:10|confirmed',
        ]);

        $network = config('wallet.network');
        $seed = $this->wallets->seedFromPhrase($d['name'], $d['phrase'], $network);
        [$encrypted, $salt] = $this->wallets->encryptSeed($seed, $d['password']);
        [, $addr] = $this->wallets->keysFromSeed($seed, $network);

        Wallet::create([
            'name' => $d['name'], 'network' => $network, 'address' => $addr->getAddress(),
            'encrypted_seed' => $encrypted, 'kdf_salt' => $salt,
        ]);

        return redirect()->route('login')->with('status', 'Wallet created. Address: ' . $addr->getAddress() . ' — now log in with your password.');
    }

    public function showLogin()
    {
        return view('login');
    }

    public function login(Request $r)
    {
        $d = $r->validate(['name' => 'required|string', 'password' => 'required|string']);
        $w = Wallet::where('name', $d['name'])->first();

        if (!$w || $this->unlock($w, $d['password']) === null) {
            return back()->withErrors(['password' => 'Wrong username or password.'])->withInput($r->only('name'));
        }

        $r->session()->regenerate();
        $r->session()->put('wallet_id', $w->id);
        return redirect()->route('dashboard');
    }

    public function logout(Request $r)
    {
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect()->route('login');
    }

    // ---------- dashboard ----------

    public function dashboard(Request $r)
    {
        $w = $this->current($r);
        $chain = $this->chain->forNetwork($w->network);
        $data = ['wallet' => $w, 'error' => null, 'balance' => null, 'history' => [], 'fees' => ['fast' => 5, 'medium' => 3, 'slow' => 1]];

        try {
            $data['balance'] = $chain->balance($w->address);
            $data['history'] = $chain->history($w->address);
            $data['fees'] = $chain->feeRates();
        } catch (\Throwable $e) {
            $data['error'] = 'Could not reach the blockchain API: ' . $e->getMessage();
        }

        return view('dashboard', $data);
    }

    // ---------- send (preview -> confirm) ----------

    public function preview(Request $r)
    {
        $d = $r->validate([
            'to'       => 'required|string|max:100',
            'amount'   => ['required', 'regex:/^\d{1,8}(\.\d{1,8})?$/'],
            'fee_rate' => 'required|numeric|min:1|max:1000',
            'password' => 'required|string',
        ]);

        $w = $this->current($r);

        try {
            $keys = $this->unlock($w, $d['password']);
            if ($keys === null) {
                return back()->withErrors(['password' => 'Wrong password.'])->withInput($r->except('password'));
            }
            [$priv, $addr] = $keys;

            $utxos = $this->chain->forNetwork($w->network)->utxos($w->address);
            $amount = $this->wallets->toSats($d['amount']);
            $built = $this->wallets->buildSigned($priv, $addr, $utxos, $d['to'], $amount, (float) $d['fee_rate']);
        } catch (\Throwable $e) {
            return back()->withErrors(['send' => $e->getMessage()])->withInput($r->except('password'));
        }

        $r->session()->put('pending', ['wallet' => $w->id, 'hex' => $built['hex'], 'to' => $d['to'], 'amount' => $amount, 'fee' => $built['fee'], 'change' => $built['change']]);

        return view('preview', ['wallet' => $w, 'p' => $r->session()->get('pending')]);
    }

    public function confirm(Request $r)
    {
        $w = $this->current($r);
        $p = $r->session()->pull('pending');

        if (!$p || $p['wallet'] !== $w->id) {
            return redirect()->route('dashboard')->withErrors(['send' => 'Nothing to confirm.']);
        }

        try {
            $txid = $this->chain->forNetwork($w->network)->broadcast($p['hex']);
        } catch (RequestException $e) {
            return redirect()->route('dashboard')->withErrors(['send' => 'Broadcast failed: ' . $e->response->body()]);
        }

        return redirect()->route('dashboard')->with('status', "Sent! Transaction ID: $txid");
    }
}
