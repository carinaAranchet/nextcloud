<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReporteController;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Middleware\ValidateOidcToken;


Route::middleware(['auth', ValidateOidcToken::class])->group(function () {
    Route::get('/', fn() => view('reporte'));
    Route::get('/reporte', [ReporteController::class, 'index'])->name('reporte.index');
    Route::get('/reporte/filters', [ReporteController::class, 'filters'])->name('reporte.filters');
    Route::get('/reporte/data', [ReporteController::class, 'data'])->name('reporte.data');
});

// Login OIDC (Nextcloud)
Route::get('/auth/redirect', fn() => Socialite::driver('nextcloud')->stateless()->redirect())->name('login');
Route::get('/auth/callback', function () {
    $oidcUser = Socialite::driver('nextcloud')->stateless()->user();

    $email = $oidcUser->getEmail() ?: ($oidcUser->getId().'@oidc.local');
    $user = User::firstOrCreate(
        ['email' => $email],
        [
            'name'     => $oidcUser->getName() ?? $oidcUser->getNickname() ?? 'Usuario OIDC',
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
        ]
    );
    // Si el usuario ya existía, actualizamos solo el nombre
    if (!$user->wasRecentlyCreated) {
        $user->name = $oidcUser->getName() ?? $oidcUser->getNickname() ?? $user->name;
        $user->save();
    }

    Auth::login($user, false);
    session([
        'oidc_token'            => $oidcUser->token,
        'oidc_token_expires_at' => now()->timestamp + ($oidcUser->expiresIn ?? 3600),
    ]);
    return redirect()->intended('/reporte');
});

// Logout local (opcional)
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout');

