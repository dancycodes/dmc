<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\CookAssignmentController;
use App\Http\Controllers\Admin\PaymentTransactionController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\PlatformSettingController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\CrossDomainAuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Cook\BrandProfileController;
use App\Http\Controllers\Cook\CookScheduleController;
use App\Http\Controllers\Cook\CoverImageController;
use App\Http\Controllers\Cook\DeliveryFeeController;
use App\Http\Controllers\Cook\PickupLocationController;
use App\Http\Controllers\Cook\QuarterController;
use App\Http\Controllers\Cook\QuarterGroupController;
use App\Http\Controllers\Cook\SetupWizardController;
use App\Http\Controllers\Cook\TownController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\LanguagePreferenceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LocationSearchController;
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
// BR-066: Discovery page on main domain, tenant landing on tenant domains
Route::get('/', function () {
    if (app(TenantService::class)->isTenantDomain()) {
        return app(DashboardController::class)->tenantHome(request());
    }

    return app(DiscoveryController::class)->index(
        app(App\Http\Requests\DiscoveryRequest::class),
        app(App\Services\DiscoveryService::class),
    );
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
| Location Search Routes (accessible on ALL domains)
|--------------------------------------------------------------------------
|
| OpenStreetMap Nominatim API proxy for neighbourhood autocomplete (F-097).
| BR-322: Server-side proxy to avoid CORS and control rate limiting.
| Available to all users (guests may use it on tenant domains).
|
*/
Route::get('/location-search', [LocationSearchController::class, 'search'])
    ->middleware('throttle:moderate')
    ->name('location-search');

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

        // Commission configuration (F-062)
        Route::get('/tenants/{tenant}/commission', [CommissionController::class, 'show'])->name('admin.tenants.commission');
        Route::post('/tenants/{tenant}/commission', [CommissionController::class, 'update'])->name('admin.tenants.commission.update');
        Route::post('/tenants/{tenant}/commission/reset', [CommissionController::class, 'resetToDefault'])->name('admin.tenants.commission.reset');

        // Payment monitoring (F-059)
        Route::get('/payments', [PaymentTransactionController::class, 'index'])->name('admin.payments.index');
        Route::get('/payments/{transaction}', [PaymentTransactionController::class, 'show'])->name('admin.payments.show');

        // Complaint escalation queue (F-060) & resolution (F-061)
        Route::get('/complaints', [ComplaintController::class, 'index'])->name('admin.complaints.index');
        Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('admin.complaints.show');
        Route::post('/complaints/{complaint}/resolve', [ComplaintController::class, 'resolve'])->name('admin.complaints.resolve');

        // Manual payout task queue (F-065)
        Route::get('/payouts', [PayoutController::class, 'index'])->name('admin.payouts.index');
        Route::get('/payouts/{task}', [PayoutController::class, 'show'])->name('admin.payouts.show');
        Route::post('/payouts/{task}/retry', [PayoutController::class, 'retry'])->name('admin.payouts.retry');
        Route::post('/payouts/{task}/mark-complete', [PayoutController::class, 'markComplete'])->name('admin.payouts.mark-complete');

        // Platform settings (F-063)
        Route::get('/settings', [PlatformSettingController::class, 'index'])->name('admin.settings.index');
        Route::post('/settings', [PlatformSettingController::class, 'update'])->name('admin.settings.update');
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
    // Cook/Manager dashboard (F-076: Cook Dashboard Layout & Navigation)
    // BR-156: Dashboard routes are only accessible on tenant domains
    // BR-157: Only users with cook or manager role for the current tenant
    Route::prefix('dashboard')->middleware(['auth', 'cook.access', 'throttle:moderate'])->group(function () {
        Route::get('/', [DashboardController::class, 'cookDashboard'])->name('cook.dashboard');

        // Dashboard stats refresh (F-077: Cook Dashboard Home)
        // BR-170: Real-time updates via Gale SSE polling
        Route::post('/stats/refresh', [DashboardController::class, 'refreshDashboardStats'])->name('cook.dashboard.refresh');

        // Setup wizard (F-071: Cook Setup Wizard Shell)
        // BR-113: Only accessible to cook/manager role (enforced by cook.access middleware)
        // BR-116: Accessible both before and after "Go Live"
        Route::get('/setup', [SetupWizardController::class, 'show'])->name('cook.setup');
        Route::post('/setup/brand-info', [SetupWizardController::class, 'saveBrandInfo'])->name('cook.setup.brand-info');
        Route::post('/setup/cover-images/upload', [SetupWizardController::class, 'uploadCoverImages'])->name('cook.setup.cover-images.upload');
        Route::post('/setup/cover-images/reorder', [SetupWizardController::class, 'reorderCoverImages'])->name('cook.setup.cover-images.reorder');
        Route::delete('/setup/cover-images/{mediaId}', [SetupWizardController::class, 'deleteCoverImage'])->name('cook.setup.cover-images.delete');

        // Delivery areas (F-074: Delivery Areas Step)
        Route::post('/setup/delivery-areas/add-town', [SetupWizardController::class, 'addTown'])->name('cook.setup.delivery.add-town');
        Route::delete('/setup/delivery-areas/remove-town/{deliveryAreaId}', [SetupWizardController::class, 'removeTown'])->name('cook.setup.delivery.remove-town');
        Route::post('/setup/delivery-areas/{deliveryAreaId}/add-quarter', [SetupWizardController::class, 'addQuarter'])->name('cook.setup.delivery.add-quarter');
        Route::delete('/setup/delivery-areas/remove-quarter/{deliveryAreaQuarterId}', [SetupWizardController::class, 'removeQuarter'])->name('cook.setup.delivery.remove-quarter');
        Route::post('/setup/delivery-areas/add-pickup', [SetupWizardController::class, 'addPickupLocation'])->name('cook.setup.delivery.add-pickup');
        Route::delete('/setup/delivery-areas/remove-pickup/{pickupLocationId}', [SetupWizardController::class, 'removePickupLocation'])->name('cook.setup.delivery.remove-pickup');
        Route::post('/setup/delivery-areas/save', [SetupWizardController::class, 'saveDeliveryAreas'])->name('cook.setup.delivery.save');

        // Schedule & First Meal (F-075: Schedule & First Meal Step)
        Route::post('/setup/schedule/save', [SetupWizardController::class, 'saveSchedule'])->name('cook.setup.schedule.save');
        Route::post('/setup/meal/save', [SetupWizardController::class, 'saveMeal'])->name('cook.setup.meal.save');

        Route::post('/setup/go-live', [SetupWizardController::class, 'goLive'])->name('cook.setup.go-live');

        // Brand profile (F-079: Cook Brand Profile View, F-080: Cook Brand Profile Edit)
        // BR-181: All profile sections in a single view
        Route::get('/profile', [BrandProfileController::class, 'show'])->name('cook.profile.show');
        // F-080: Cook Brand Profile Edit
        // BR-195: Only users with profile edit permission can access
        Route::get('/profile/edit', [BrandProfileController::class, 'edit'])->name('cook.profile.edit');
        Route::post('/profile/update', [BrandProfileController::class, 'update'])->name('cook.profile.update');

        // Cover images management (F-081: Cook Cover Images Management)
        // BR-197 to BR-206: Full cover images management page
        Route::get('/profile/cover-images', [CoverImageController::class, 'index'])->name('cook.cover-images.index');
        Route::post('/profile/cover-images/upload', [CoverImageController::class, 'upload'])->name('cook.cover-images.upload');
        Route::post('/profile/cover-images/reorder', [CoverImageController::class, 'reorder'])->name('cook.cover-images.reorder');
        Route::delete('/profile/cover-images/{mediaId}', [CoverImageController::class, 'destroy'])->name('cook.cover-images.destroy');

        // Location management (F-082: Add Town, F-083: Town List View, F-084: Edit Town, F-085: Delete Town)
        // BR-212: Only users with can-manage-locations permission
        Route::get('/locations', [TownController::class, 'index'])->name('cook.locations.index');
        Route::post('/locations/towns', [TownController::class, 'store'])->name('cook.locations.towns.store');
        Route::put('/locations/towns/{deliveryArea}', [TownController::class, 'update'])->name('cook.locations.towns.update');
        Route::delete('/locations/towns/{deliveryArea}', [TownController::class, 'destroy'])->name('cook.locations.towns.destroy');

        // Quarter management (F-086: Add Quarter, F-088: Edit Quarter, F-089: Delete Quarter)
        // BR-241/BR-257/BR-262: Only users with can-manage-locations permission
        Route::post('/locations/quarters/{deliveryArea}', [QuarterController::class, 'store'])->name('cook.locations.quarters.store');
        Route::put('/locations/quarters/{deliveryAreaQuarter}', [QuarterController::class, 'update'])->name('cook.locations.quarters.update');
        Route::delete('/locations/quarters/{deliveryAreaQuarter}', [QuarterController::class, 'destroy'])->name('cook.locations.quarters.destroy');

        // Quarter group management (F-090: Quarter Group Creation)
        Route::post('/locations/groups', [QuarterGroupController::class, 'store'])->name('cook.locations.groups.store');

        // Delivery fee configuration (F-091: Delivery Fee Configuration)
        // BR-278: Accessible from the Locations section of the dashboard
        // BR-280: Only users with can-manage-locations permission
        Route::get('/locations/delivery-fees', [DeliveryFeeController::class, 'index'])->name('cook.locations.delivery-fees');
        Route::put('/locations/delivery-fees/quarter/{deliveryAreaQuarter}', [DeliveryFeeController::class, 'updateQuarterFee'])->name('cook.locations.delivery-fees.quarter.update');
        Route::put('/locations/delivery-fees/group/{group}', [DeliveryFeeController::class, 'updateGroupFee'])->name('cook.locations.delivery-fees.group.update');

        // Pickup location management (F-092: Add Pickup Location, F-094: Edit, F-095: Delete)
        // BR-288/BR-304: Only users with can-manage-locations permission
        Route::get('/locations/pickup', [PickupLocationController::class, 'index'])->name('cook.locations.pickup.index');
        Route::post('/locations/pickup', [PickupLocationController::class, 'store'])->name('cook.locations.pickup.store');
        Route::get('/locations/pickup/{pickupLocation}/edit', [PickupLocationController::class, 'edit'])->name('cook.locations.pickup.edit');
        Route::put('/locations/pickup/{pickupLocation}', [PickupLocationController::class, 'update'])->name('cook.locations.pickup.update');
        Route::delete('/locations/pickup/{pickupLocation}', [PickupLocationController::class, 'destroy'])->name('cook.locations.pickup.destroy');

        // Schedule management (F-098: Cook Day Schedule Creation)
        // BR-103: Only users with can-manage-schedules permission
        Route::get('/schedule', [CookScheduleController::class, 'index'])->name('cook.schedule.index');
        Route::post('/schedule', [CookScheduleController::class, 'store'])->name('cook.schedule.store');
        // F-099: Order Time Interval Configuration
        Route::put('/schedule/{cookSchedule}/order-interval', [CookScheduleController::class, 'updateOrderInterval'])->name('cook.schedule.update-order-interval');
    });

    // Tenant-specific routes will be added by later features (F-126, etc.)
});
