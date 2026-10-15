<?php

return [
    // 'testnet' (safe for practice) or 'mainnet' (real bitcoin)
    'network' => env('BTC_NETWORK', 'testnet'),

    // Esplora-compatible API used only to read balances/UTXOs and broadcast signed txs.
    // Your keys never leave this machine.
    'apis' => [
        'mainnet' => env('BTC_API_MAINNET', 'https://mempool.space/api'),
        'testnet' => env('BTC_API_TESTNET', 'https://mempool.space/testnet4/api'),
    ],
];
