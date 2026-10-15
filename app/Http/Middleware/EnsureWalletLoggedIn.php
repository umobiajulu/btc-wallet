<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureWalletLoggedIn
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->has('wallet_id')) {
            return redirect()->route('login');
        }
        return $next($request);
    }
}
