<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ChainService
{
    private string $base = '';

    public function forNetwork(string $network): static
    {
        $c = clone $this;
        $c->base = rtrim(config("wallet.apis.$network"), '/');
        return $c;
    }

    private function http()
    {
        // Force IPv4 (broken IPv6 routes are a common cause of connect timeouts) and allow slow links.
        return Http::connectTimeout(30)->timeout(45)->withOptions(['force_ip_resolve' => 'v4']);
    }

    private function get(string $path): array
    {
        return $this->http()->acceptJson()->get($this->base . $path)->throw()->json();
    }

    public function balance(string $addr): array
    {
        $d = $this->get("/address/$addr");
        $c = $d['chain_stats'];
        $m = $d['mempool_stats'];
        return [
            'confirmed'   => $c['funded_txo_sum'] - $c['spent_txo_sum'],
            'unconfirmed' => $m['funded_txo_sum'] - $m['spent_txo_sum'],
        ];
    }

    public function utxos(string $addr): array
    {
        return collect($this->get("/address/$addr/utxo"))
            ->filter(fn ($u) => $u['status']['confirmed'] ?? false)
            ->map(fn ($u) => ['txid' => $u['txid'], 'vout' => $u['vout'], 'value' => $u['value']])
            ->values()->all();
    }

    public function history(string $addr): array
    {
        return collect($this->get("/address/$addr/txs"))->take(15)->map(function ($tx) use ($addr) {
            $in  = collect($tx['vin'])->filter(fn ($i) => ($i['prevout']['scriptpubkey_address'] ?? null) === $addr)->sum(fn ($i) => $i['prevout']['value']);
            $out = collect($tx['vout'])->filter(fn ($o) => ($o['scriptpubkey_address'] ?? null) === $addr)->sum('value');
            return [
                'txid' => $tx['txid'],
                'net' => $out - $in,
                'confirmed' => $tx['status']['confirmed'] ?? false,
                'time' => $tx['status']['block_time'] ?? null,
            ];
        })->all();
    }

    public function feeRates(): array
    {
        try { // mempool.space
            $r = $this->get('/v1/fees/recommended');
            return ['fast' => $r['fastestFee'], 'medium' => $r['halfHourFee'], 'slow' => $r['hourFee']];
        } catch (\Throwable $e) {
        }

        try { // plain Esplora (blockstream.info): {"1": rate, "3": rate, "6": rate, ...}
            $r = $this->get('/fee-estimates');
            return [
                'fast'   => max(1, (int) ceil($r['1'] ?? 5)),
                'medium' => max(1, (int) ceil($r['3'] ?? 3)),
                'slow'   => max(1, (int) ceil($r['6'] ?? 1)),
            ];
        } catch (\Throwable $e) {
            return ['fast' => 5, 'medium' => 3, 'slow' => 1];
        }
    }

    public function broadcast(string $hex): string
    {
        return trim($this->http()->withBody($hex, 'text/plain')->post($this->base . '/tx')->throw()->body());
    }
}
