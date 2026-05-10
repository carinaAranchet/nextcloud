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
            $allowed  = ['reporte', 'legajo'];
            $path     = '/' . $request->path();
            $intended = in_array($request->path(), $allowed) ? $path : '/reporte';
            return redirect()->route('login', ['intended' => $intended]);
        }

        return $next($request);
    }
}
