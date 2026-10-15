<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $hidden = ['encrypted_seed', 'kdf_salt'];

    protected $fillable = ['name', 'network', 'address', 'encrypted_seed', 'kdf_salt'];
}
