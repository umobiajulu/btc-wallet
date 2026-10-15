<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Only the seed encrypted with your password (Argon2id + XSalsa20-Poly1305) is stored.
        Schema::create('wallets', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('network');
            $t->string('address');
            $t->text('encrypted_seed');
            $t->string('kdf_salt');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
