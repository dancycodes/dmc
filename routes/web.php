<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\CookAssignmentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\CrossDomainAuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LanguagePreferenceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PaymentMethodController;
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
    Route::get('/profile/addresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::get('/profile/addresses/create', [AddressController::class, 'create'])->name('addresses.create');
    Route::post('/profile/addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::post('/profile/addresses/quarters', [AddressController::class, 'quarters'])->name('addresses.quarters');
    Route::post('/profile/addresses/{address}/set-default', [AddressController::class, 'setDefault'])->name('addresses.set-default');
    Route::get('/profile/addresses/{address}/edit', [AddressController::class, 'edit'])->name('addresses.edit');
    Route::post('/profile/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/profile/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');

    // Payment Methods (F-037, F-038, F-039, F-040)
    Route::get('/profile/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
    Route::get('/profile/payment-methods/create', [PaymentMethodController::class, 'create'])->name('payment-methods.create');
    Route::post('/profile/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
    Route::post('/profile/payment-methods/{paymentMethod}/set-default', [PaymentMethodController::class, 'setDefault'])->name('payment-methods.set-default');
    Route::get('/profile/payment-methods/{paymentMethod}/edit', [PaymentMethodController::class, 'edit'])->name('payment-methods.edit');
    Route::post('/profile/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
    Route::delete('/profile/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');

    // Language Preference (F-042)
    Route::get('/profile/language', [LanguagePreferenceController::class, 'show'])->name('language.show');
    Route::post('/profile/language', [LanguagePreferenceController::class, 'update'])->name('language.update');
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
    // Admin panel routes (F-043: only on main domain at /vault-entry)
    // BR-043: Admin panel routes are ONLY accessible on the main domain
    // BR-044: Requests to /vault-entry/* on tenant domains return 404 (via main.domain middleware)
    // BR-045: Only users with can-access-admin-panel permission may access
    Route::prefix('vault-entry')->middleware(['auth', 'admin.access', 'throttle:moderate'])->group(function () {
        Route::get('/', [DashboardController::class, 'adminDashboard'])->name('admin.dashboard');

        // Tenant management (F-045, F-046, F-047, F-048)
        Route::get('/tenants', [TenantController::class, 'index'])->name('admin.tenants.index');
        Route::get('/tenants/create', [TenantController::class, 'create'])->name('admin.tenants.create');
        Route::post('/tenants', [TenantController::class, 'store'])->name('admin.tenants.store');
        Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('admin.tenants.show');
        Route::get('/tenants/{tenant}/edit', [TenantController::class, 'edit'])->name('admin.tenants.edit');
        Route::post('/tenants/{tenant}', [TenantController::class, 'update'])->name('admin.tenants.update');
        Route::post('/tenants/{tenant}/toggle-status', [TenantController::class, 'toggleStatus'])->name('admin.tenants.toggle-status');

        // Cook assignment (F-049)
        Route::get('/tenants/{tenant}/assign-cook', [CookAssignmentController::class, 'show'])->name('admin.tenants.assign-cook');
        Route::post('/tenants/{tenant}/assign-cook/search', [CookAssignmentController::class, 'search'])->name('admin.tenants.assign-cook.search');
        Route::post('/tenants/{tenant}/assign-cook', [CookAssignmentController::class, 'assign'])->name('admin.tenants.assign-cook.store');

        // User management (F-050, F-051)
        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('admin.users.show');
        Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('admin.users.toggle-status');

        // Role management (F-052, F-053, F-054, F-055, F-056)
        Route::get('/roles', [RoleController::class, 'index'])->name('admin.roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('admin.roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('admin.roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('admin.roles.edit');
        Route::post('/roles/{role}', [RoleController::class, 'update'])->name('admin.roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('admin.roles.destroy');
        Route::get('/roles/{role}/permissions', [RoleController::class, 'permissions'])->name('admin.roles.permissions');
        Route::post('/roles/{role}/permissions/toggle', [RoleController::class, 'togglePermission'])->name('admin.roles.permissions.toggle');
        Route::post('/roles/{role}/permissions/toggle-module', [RoleController::class, 'toggleModule'])->name('admin.roles.permissions.toggle-module');
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
