<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ValidateOidcToken
{
    public function handle(Request $request, Closure $next)
    {
        $expiresAt = session('oidc_token_expires_at');

        if (!$expiresAt || now()->timestamp >= $expiresAt) {
            Auth::logout();
            session()->forget(['oidc_token', 'oidc_token_expires_at']);
            return redirect()->route('login');
        }

        return $next($request);
    }
}
