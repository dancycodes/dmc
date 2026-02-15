<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ThemeController;
use App\Services\TenantService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Routes are organized by domain context. The ResolveTenant middleware
| (registered globally) resolves the tenant before routes are handled.
| The EnsureMainDomain and EnsureTenantDomain middleware enforce context.
|
*/

// Root route dispatches based on domain context
Route::get('/', function () {
    if (app(TenantService::class)->isTenantDomain()) {
        return app(DashboardController::class)->tenantHome(request());
    }

    return app(DashboardController::class)->home(request());
})->name('home');

/*
|--------------------------------------------------------------------------
| Authentication Routes (accessible on ALL domains)
|--------------------------------------------------------------------------
|
| Auth is a single system across all domains. Users authenticate with
| their DancyMeals account regardless of whether they are on the main
| domain or a tenant domain. Tenant branding is shown on tenant domains.
|
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware(['honeypot', 'throttle:strict']);

    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware(['honeypot', 'throttle:strict']);

    Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email')->middleware(['honeypot', 'throttle:strict']);
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Email Verification Routes (accessible on ALL domains)
|--------------------------------------------------------------------------
|
| Placeholder routes for email verification. F-023 will implement the
| full verification flow. These routes are needed because the Registered
| event dispatches a verification email that generates a signed URL.
|
*/
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', function () {
        return gale()->view('auth.verify-email', [], web: true);
    })->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function () {
        // F-023 will implement the full verification handler
        return gale()->redirect('/')->route('home');
    })->middleware('signed')->name('verification.verify');
});

/*
|--------------------------------------------------------------------------
| Locale Routes (accessible on ALL domains)
|--------------------------------------------------------------------------
|
| Language switching is available to all users (guests and authenticated)
| on every domain. Persists choice in session and user preference.
|
*/
Route::post('/locale/switch', [LocaleController::class, 'switch'])->name('locale.switch');

/*
|--------------------------------------------------------------------------
| Theme Routes (accessible on ALL domains)
|--------------------------------------------------------------------------
|
| Theme preference persistence for authenticated users.
| The frontend handles instant theme application via localStorage.
| This route persists the choice to the database for cross-device sync.
|
*/
Route::middleware(['auth', 'throttle:moderate'])->group(function () {
    Route::post('/theme/update', [ThemeController::class, 'update'])->name('theme.update');
    Route::get('/theme/preference', [ThemeController::class, 'show'])->name('theme.show');
});

/*
|--------------------------------------------------------------------------
| Push Notification Routes (accessible on ALL domains)
|--------------------------------------------------------------------------
|
| Push subscription management for authenticated users.
| Works on both main domain and tenant domains (BR-112).
|
*/
Route::middleware(['auth', 'throttle:moderate'])->group(function () {
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
});

/*
|--------------------------------------------------------------------------
| Main Domain Routes
|--------------------------------------------------------------------------
|
| Routes accessible only on the main domain (dmc.test / dancymeals.com).
| Admin panel and other main-domain-only features live here.
|
*/
Route::middleware('main.domain')->group(function () {
    // Admin panel routes (BR-129: only on main domain at /vault-entry)
    Route::prefix('vault-entry')->middleware(['auth', 'throttle:moderate'])->group(function () {
        Route::get('/', [DashboardController::class, 'adminDashboard'])->name('admin.dashboard');
    });
});

/*
|--------------------------------------------------------------------------
| Tenant Domain Routes
|--------------------------------------------------------------------------
|
| Routes accessible only on tenant domains (cook.dmc.test / cook.cm).
| Cook landing pages, ordering, and tenant-specific features live here.
|
*/
Route::middleware('tenant.domain')->group(function () {
    // Cook/Manager dashboard (BR-130: authenticated cooks/managers on tenant domains)
    Route::prefix('dashboard')->middleware(['auth', 'throttle:moderate'])->group(function () {
        Route::get('/', [DashboardController::class, 'cookDashboard'])->name('cook.dashboard');
    });

    // Tenant-specific routes will be added by later features (F-126, etc.)
});
