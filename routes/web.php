<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\LegajoController;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Middleware\ValidateOidcToken;


Route::middleware(['auth', ValidateOidcToken::class])->group(function () {
    Route::get('/', fn() => view('reporte'));
    Route::get('/reporte', [ReporteController::class, 'index'])->name('reporte.index');
    Route::get('/reporte/filters', [ReporteController::class, 'filters'])->name('reporte.filters');
    Route::get('/reporte/data', [ReporteController::class, 'data'])->name('reporte.data');

    Route::get('/legajo', [LegajoController::class, 'index'])->name('legajo.index');
    Route::get('/legajo/proveedores', [LegajoController::class, 'proveedores'])->name('legajo.proveedores');
    Route::get('/legajo/generar-qr', [LegajoController::class, 'generarQr'])->name('legajo.generar-qr');
});

// Login OIDC (Nextcloud)
Route::get('/auth/redirect', function () {
    $allowed  = ['/reporte', '/legajo'];
    $intended = request()->input('intended', '/reporte');
    if (! in_array($intended, $allowed)) {
        $intended = '/reporte';
    }
    // Codificamos el destino en el state (el OIDC spec lo devuelve intacto)
    return Socialite::driver('nextcloud')
        ->stateless()
        ->with(['state' => base64_encode($intended)])
        ->redirect();
})->name('login');
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
    if (!$user->wasRecentlyCreated) {
        $user->name = $oidcUser->getName() ?? $oidcUser->getNickname() ?? $user->name;
        $user->save();
    }

    Auth::login($user, false);
    session([
        'oidc_token'            => $oidcUser->token,
        'oidc_token_expires_at' => now()->timestamp + ($oidcUser->expiresIn ?? 3600),
    ]);

    // Recuperar destino del state (whitelist para evitar open-redirect)
    $allowed  = ['/reporte', '/legajo'];
    $decoded  = base64_decode((string) request()->input('state', ''), strict: true);
    $intended = ($decoded !== false && in_array($decoded, $allowed)) ? $decoded : '/reporte';
    return redirect($intended);
});

// Logout local (opcional)
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout');

