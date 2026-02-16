<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Auth\CrossDomainAuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
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
    Route::post('/login', [LoginController::class, 'login'])->middleware(['honeypot', 'throttle:login']);

    Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email')->middleware(['throttle:password-reset']);

    // Password reset execution (F-027)
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update')->middleware(['throttle:strict']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Account Deactivation Page (F-029)
|--------------------------------------------------------------------------
|
| BR-096: Accessible without authentication (user is logged out by middleware).
| Shows a branded message informing the user their account has been deactivated.
|
*/
Route::get('/account-deactivated', function () {
    return gale()->view('auth.account-deactivated', web: true);
})->name('account.deactivated');

/*
|--------------------------------------------------------------------------
| Cross-Domain Session Sharing Routes (F-028)
|--------------------------------------------------------------------------
|
| Handles session sharing between subdomains (via shared cookie domain)
| and custom domains (via one-time token exchange). BR-081 through BR-088.
|
| - generate-token: Authenticated user gets a one-time token to carry
|   their session to a custom domain (BR-083).
| - cross-domain-auth: Custom domain consumes the token and establishes
|   a session for the user (BR-083, BR-087).
|
*/
Route::middleware('auth')->group(function () {
    Route::get('/cross-domain/generate-token', [CrossDomainAuthController::class, 'generateToken'])
        ->name('cross-domain.generate-token');
});

Route::get('/cross-domain-auth', [CrossDomainAuthController::class, 'consumeToken'])
    ->name('cross-domain.consume-token');

/*
|--------------------------------------------------------------------------
| Email Verification Routes (accessible on ALL domains)
|--------------------------------------------------------------------------
|
| Email verification flow (F-023): notice page, verification link handler,
| and resend endpoint. Uses Laravel's signed URLs for secure verification.
| Resend is rate-limited to 5/hour per user (BR-042).
|
*/
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:verification-resend')
        ->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Profile Routes (accessible on ALL domains)
|--------------------------------------------------------------------------
|
| User profile page (F-030). Accessible to authenticated users on any
| domain (main or tenant). BR-097: requires authentication.
| BR-100: works across all domains.
|
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/edit', [ProfileController::class, 'update'])->name('profile.update');

    // Delivery Addresses (F-033, F-034, F-035, F-036)
    Route::get('/profile/addresses/create', [AddressController::class, 'create'])->name('addresses.create');
    Route::post('/profile/addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::post('/profile/addresses/quarters', [AddressController::class, 'quarters'])->name('addresses.quarters');
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
