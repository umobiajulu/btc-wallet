# Local BTC Wallet (Laravel)

Single-address (BIP84 native SegWit, bc1q... / tb1q...) wallet. Seed phrase can be any text.
The seed phrase is used once at creation. The DB stores the derived seed encrypted with your wallet password (Argon2id + secretbox). Log in and sign with the password.
Phrase -> Argon2id (256 MB, 3 passes, salted with wallet name) -> BIP32 -> m/84'/coin'/0'/0/0.

## Install
    cd btc-wallet
    composer install
    copy .env.example to .env
    composer require bitwasp/bitcoin          # needs php-gmp and php-sodium
    # in .env set:
    #   APP_DEBUG=false          (debug pages can leak form data)
    #   SESSION_ENCRYPT=true
    #   BTC_NETWORK=mainnet      
    #   BTC_API_MAINNET=https://blockstream.info/api
    php artisan migrate && php artisan key:gen        # (use migrate:fresh if you ran the earlier version)
    php artisan serve --host=127.0.0.1       # keep it local-only

    # the frond end version in in btc-wallet/frontend/btc-wallet
        cd btc-wallet/frontend/btc-wallet
        npm install
        npm run dev

### Alternatively
    open https://wallet.predict.ng and use it online for free

## Recovery
Wallet name + exact phrase (case, spacing) + this derivation scheme. Back up the name and keep a copy of this code.

    #   php rebuild-wallet.php --selftest     # proves the maths works on this machine
    #   php rebuild-wallet.php                # prompts for network, username, phrase; prints your address
    #   php rebuild-wallet.php --wif          # also lets you reveal the private key
