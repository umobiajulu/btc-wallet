# Local BTC Wallet (Vite + Vue 3 + Tailwind v4)
Client-only. Wallets live in localStorage (key encrypted with your password).
Derivation matches rebuild-wallet.php (Argon2id 3 ops / 256 MiB → BIP32 m/84'/coin'/0'/0/0 → bech32).

    npm install
    npm run dev          # local
    npm run build        # -> dist/
    npx wrangler login
    npm run deploy       # builds + deploys to Cloudflare Pages
