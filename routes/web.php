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
use App\Http\Controllers\Cook\ComponentRequirementRuleController;
use App\Http\Controllers\Cook\CookScheduleController;
use App\Http\Controllers\Cook\CoverImageController;
use App\Http\Controllers\Cook\DeliveryFeeController;
use App\Http\Controllers\Cook\MealComponentController;
use App\Http\Controllers\Cook\MealController;
use App\Http\Controllers\Cook\MealImageController;
use App\Http\Controllers\Cook\MealLocationOverrideController;
use App\Http\Controllers\Cook\MealScheduleController;
use App\Http\Controllers\Cook\MealTagController;
use App\Http\Controllers\Cook\OrderController;
use App\Http\Controllers\Cook\PickupLocationController;
use App\Http\Controllers\Cook\QuarterController;
use App\Http\Controllers\Cook\QuarterGroupController;
use App\Http\Controllers\Cook\ScheduleTemplateController;
use App\Http\Controllers\Cook\SellingUnitController;
use App\Http\Controllers\Cook\SetupWizardController;
use App\Http\Controllers\Cook\TagController;
use App\Http\Controllers\Cook\TownController;
use App\Http\Controllers\Cook\WalletController as CookWalletController;
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
// BR-126: Tenant landing page renders ONLY on tenant domains
Route::get('/', function () {
    if (app(TenantService::class)->isTenantDomain()) {
        return app(DashboardController::class)->tenantHome(
            request(),
            app(App\Services\TenantLandingService::class),
        );
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

    // Client Order List (F-160)
    // BR-219: Accessible from any domain (main or tenant)
    // BR-220: Authentication required
    Route::get('/my-orders', [\App\Http\Controllers\Client\OrderController::class, 'index'])->name('client.orders.index');

    // Client Order Detail & Status Tracking (F-161)
    // BR-222: Client can only view their own orders
    // BR-223: Real-time status tracking via SSE polling
    Route::get('/my-orders/{order}', [\App\Http\Controllers\Client\OrderController::class, 'show'])->name('client.orders.show');
    Route::post('/my-orders/{order}/refresh-status', [\App\Http\Controllers\Client\OrderController::class, 'refreshStatus'])->name('client.orders.refresh-status');

    // Order Rating (F-176)
    // BR-388: Only Completed orders can be rated
    // BR-390: Each order can be rated exactly once
    Route::post('/my-orders/{order}/rate', [\App\Http\Controllers\Client\RatingController::class, 'store'])->name('client.orders.rate');

    // Client Complaint Submission (F-183)
    // BR-183: Only delivered/completed orders can receive a complaint
    // BR-184: One complaint per order
    Route::get('/my-orders/{order}/complaint', [\App\Http\Controllers\Client\ComplaintController::class, 'create'])->name('client.complaints.create');
    Route::post('/my-orders/{order}/complaint', [\App\Http\Controllers\Client\ComplaintController::class, 'store'])->name('client.complaints.store');
    Route::get('/my-orders/{order}/complaint/{complaint}', [\App\Http\Controllers\Client\ComplaintController::class, 'show'])->name('client.complaints.show');

    // Client Transaction History (F-164)
    // BR-260: All transactions across all tenants
    // BR-269: Authentication required
    Route::get('/my-transactions', [\App\Http\Controllers\Client\TransactionController::class, 'index'])->name('client.transactions.index');

    // Transaction Detail View (F-165)
    // BR-271: Client can only view their own transaction details
    Route::get('/my-transactions/{sourceType}/{sourceId}', [\App\Http\Controllers\Client\TransactionController::class, 'show'])
        ->where('sourceType', 'payment_transaction|wallet_transaction')
        ->where('sourceId', '[0-9]+')
        ->name('client.transactions.show');

    // Client Wallet Dashboard (F-166)
    // BR-280: Each client has one wallet with a single balance
    // BR-288: Authentication required
    Route::get('/my-wallet', [\App\Http\Controllers\Client\WalletController::class, 'index'])->name('client.wallet.index');
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
        // F-100: Delivery/Pickup Time Interval Configuration
        Route::put('/schedule/{cookSchedule}/delivery-pickup-interval', [CookScheduleController::class, 'updateDeliveryPickupInterval'])->name('cook.schedule.update-delivery-pickup-interval');

        // Schedule templates (F-101, F-102, F-103, F-104, F-105)
        // BR-133/BR-138: Only users with can-manage-schedules permission (enforced in controller)
        Route::get('/schedule/templates', [ScheduleTemplateController::class, 'index'])->name('cook.schedule-templates.index');
        Route::get('/schedule/templates/create', [ScheduleTemplateController::class, 'create'])->name('cook.schedule-templates.create');
        Route::post('/schedule/templates', [ScheduleTemplateController::class, 'store'])->name('cook.schedule-templates.store');
        // F-103: Edit Schedule Template
        Route::get('/schedule/templates/{scheduleTemplate}/edit', [ScheduleTemplateController::class, 'edit'])->name('cook.schedule-templates.edit');
        Route::put('/schedule/templates/{scheduleTemplate}', [ScheduleTemplateController::class, 'update'])->name('cook.schedule-templates.update');
        // F-104: Delete Schedule Template
        Route::delete('/schedule/templates/{scheduleTemplate}', [ScheduleTemplateController::class, 'destroy'])->name('cook.schedule-templates.destroy');
        // F-105: Schedule Template Application to Days
        Route::get('/schedule/templates/{scheduleTemplate}/apply', [ScheduleTemplateController::class, 'showApply'])->name('cook.schedule-templates.show-apply');
        Route::post('/schedule/templates/{scheduleTemplate}/apply', [ScheduleTemplateController::class, 'apply'])->name('cook.schedule-templates.apply');

        // Meal management (F-108: Meal Creation Form, F-110: Meal Edit, F-116: Meal List)
        // BR-194: Only users with can-manage-meals permission (enforced in controller)
        Route::get('/meals', [MealController::class, 'index'])->name('cook.meals.index');
        Route::get('/meals/create', [MealController::class, 'create'])->name('cook.meals.create');
        Route::post('/meals', [MealController::class, 'store'])->name('cook.meals.store');
        Route::get('/meals/{meal}/edit', [MealController::class, 'edit'])->name('cook.meals.edit');
        Route::put('/meals/{meal}', [MealController::class, 'update'])->name('cook.meals.update');
        // F-111: Meal Delete
        Route::delete('/meals/{meal}', [MealController::class, 'destroy'])->name('cook.meals.destroy');
        // F-112: Meal Status Toggle (Draft/Live)
        Route::patch('/meals/{meal}/toggle-status', [MealController::class, 'toggleStatus'])->name('cook.meals.toggle-status');
        // F-113: Meal Availability Toggle
        Route::patch('/meals/{meal}/toggle-availability', [MealController::class, 'toggleAvailability'])->name('cook.meals.toggle-availability');
        // F-109: Meal Image Upload & Carousel
        Route::post('/meals/{meal}/images/upload', [MealImageController::class, 'upload'])->name('cook.meals.images.upload');
        Route::post('/meals/{meal}/images/reorder', [MealImageController::class, 'reorder'])->name('cook.meals.images.reorder');
        Route::delete('/meals/{meal}/images/{image}', [MealImageController::class, 'destroy'])->name('cook.meals.images.destroy');
        // F-096: Meal-Specific Location Override
        Route::get('/meals/{meal}/locations', [MealLocationOverrideController::class, 'getData'])->name('cook.meals.locations.data');
        Route::post('/meals/{meal}/locations', [MealLocationOverrideController::class, 'update'])->name('cook.meals.locations.update');
        // F-106: Meal Schedule Override
        Route::get('/meals/{meal}/schedule', [MealScheduleController::class, 'getData'])->name('cook.meals.schedule.data');
        Route::post('/meals/{meal}/schedule', [MealScheduleController::class, 'store'])->name('cook.meals.schedule.store');
        Route::put('/meals/{meal}/schedule/{mealSchedule}/order-interval', [MealScheduleController::class, 'updateOrderInterval'])->name('cook.meals.schedule.update-order-interval');
        Route::put('/meals/{meal}/schedule/{mealSchedule}/delivery-pickup-interval', [MealScheduleController::class, 'updateDeliveryPickupInterval'])->name('cook.meals.schedule.update-delivery-pickup-interval');
        Route::delete('/meals/{meal}/schedule/revert', [MealScheduleController::class, 'revert'])->name('cook.meals.schedule.revert');
        // F-114: Meal Tag Assignment
        Route::post('/meals/{meal}/tags', [MealTagController::class, 'sync'])->name('cook.meals.tags.sync');
        // F-118: Meal Component Creation
        Route::post('/meals/{meal}/components', [MealComponentController::class, 'store'])->name('cook.meals.components.store');
        // F-125: Meal Component List View — Reorder
        Route::post('/meals/{meal}/components/reorder', [MealComponentController::class, 'reorder'])->name('cook.meals.components.reorder');
        // F-119: Meal Component Edit
        Route::put('/meals/{meal}/components/{component}', [MealComponentController::class, 'update'])->name('cook.meals.components.update');
        // F-120: Meal Component Delete
        Route::delete('/meals/{meal}/components/{component}', [MealComponentController::class, 'destroy'])->name('cook.meals.components.destroy');
        // F-124: Meal Component Quantity Settings
        Route::patch('/meals/{meal}/components/{component}/quantity', [MealComponentController::class, 'updateQuantity'])->name('cook.meals.components.update-quantity');
        // F-123: Meal Component Availability Toggle
        Route::patch('/meals/{meal}/components/{component}/toggle-availability', [MealComponentController::class, 'toggleAvailability'])->name('cook.meals.components.toggle-availability');
        // F-122: Meal Component Requirement Rules
        Route::post('/meals/{meal}/components/{component}/rules', [ComponentRequirementRuleController::class, 'store'])->name('cook.meals.components.rules.store');
        Route::delete('/meals/{meal}/components/{component}/rules/{rule}', [ComponentRequirementRuleController::class, 'destroy'])->name('cook.meals.components.rules.destroy');

        // Tag management (F-115: Cook Tag Management)
        // BR-257: Only users with can-manage-meals permission (enforced in controller)
        Route::get('/tags', [TagController::class, 'index'])->name('cook.tags.index');
        Route::post('/tags', [TagController::class, 'store'])->name('cook.tags.store');
        Route::put('/tags/{tag}', [TagController::class, 'update'])->name('cook.tags.update');
        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('cook.tags.destroy');

        // Selling unit management (F-121: Custom Selling Unit Definition)
        // BR-312: Only users with can-manage-meals permission (enforced in controller)
        Route::get('/selling-units', [SellingUnitController::class, 'index'])->name('cook.selling-units.index');
        Route::post('/selling-units', [SellingUnitController::class, 'store'])->name('cook.selling-units.store');
        Route::put('/selling-units/{unit}', [SellingUnitController::class, 'update'])->name('cook.selling-units.update');
        Route::delete('/selling-units/{unit}', [SellingUnitController::class, 'destroy'])->name('cook.selling-units.destroy');

        // Order management (F-155: Cook Order List View, F-157: Status Update)
        // BR-162/BR-187: Only users with can-manage-orders permission (enforced in controller)
        Route::get('/orders', [OrderController::class, 'index'])->name('cook.orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('cook.orders.show');
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('cook.orders.update-status');

        // F-158: Mass Order Status Update
        // BR-197: Only users with can-manage-orders permission (enforced in controller)
        Route::post('/orders/mass-update-status', [OrderController::class, 'massUpdateStatus'])->name('cook.orders.mass-update-status');

        // F-169: Cook Wallet Dashboard
        // BR-319: Only cook or users with can-manage-cook-wallet permission (enforced in controller)
        // BR-320: Managers can view but not withdraw
        Route::get('/wallet', [CookWalletController::class, 'index'])->name('cook.wallet.index');

        // F-170: Cook Wallet Transaction History
        // BR-331: Only users with manage-finances permission (enforced via CookTransactionListRequest)
        // BR-324: Default sort by date descending
        // BR-325: Paginated with 20 per page
        Route::get('/wallet/transactions', [CookWalletController::class, 'transactions'])->name('cook.wallet.transactions');

        // F-172: Cook Withdrawal Request
        // BR-353: Only the cook can initiate withdrawals (not managers) -- enforced in controller
        // BR-350: Confirmation dialog shows amount and destination before submission
        Route::get('/wallet/withdraw', [CookWalletController::class, 'showWithdraw'])->name('cook.wallet.withdraw');
        Route::post('/wallet/withdraw', [CookWalletController::class, 'submitWithdraw'])->name('cook.wallet.submit-withdraw');
    });

    // F-135: Meal Search Bar (public, on tenant domain)
    // BR-214: Search across meal names, descriptions, component names, tag names
    // BR-217: Returns Gale fragment for real-time grid updates
    Route::get('/meals/search', [\App\Http\Controllers\Tenant\MealSearchController::class, 'search'])
        ->name('tenant.meal.search');

    // F-129: Meal Detail View (public, on tenant domain)
    // BR-153: Clicking a meal card navigates to this route via Gale
    // BR-156: Displays name, description, images, components, schedule, locations
    Route::get('/meals/{meal}', [\App\Http\Controllers\Tenant\MealDetailController::class, 'show'])
        ->name('tenant.meal.show');

    // F-178: Load more reviews for a meal (public, on tenant domain)
    // BR-413: Paginated with 10 per page via Gale fragment
    Route::get('/meals/{meal}/reviews', [\App\Http\Controllers\Tenant\MealDetailController::class, 'loadMoreReviews'])
        ->name('tenant.meal.reviews');

    // F-138: Meal Component Selection & Cart Add (public, on tenant domain)
    // BR-246: Cart state in session, accessible across tenant site
    // BR-247: Guest carts work via session without authentication
    // BR-251: Cart updates use Gale (no page reload)
    Route::post('/cart/add', [\App\Http\Controllers\Tenant\CartController::class, 'addToCart'])
        ->name('tenant.cart.add');
    Route::post('/cart/remove', [\App\Http\Controllers\Tenant\CartController::class, 'removeFromCart'])
        ->name('tenant.cart.remove');
    Route::post('/cart/get', [\App\Http\Controllers\Tenant\CartController::class, 'getCart'])
        ->name('tenant.cart.get');

    // F-139: Order Cart Management (public, on tenant domain)
    // BR-253: Cart items displayed grouped by meal
    // BR-259: Cart persists in server-side session
    // BR-262: All cart interactions use Gale
    Route::get('/cart', [\App\Http\Controllers\Tenant\CartController::class, 'index'])
        ->name('tenant.cart.index');
    Route::post('/cart/update-quantity', [\App\Http\Controllers\Tenant\CartController::class, 'updateQuantity'])
        ->name('tenant.cart.update-quantity');
    Route::post('/cart/clear', [\App\Http\Controllers\Tenant\CartController::class, 'clearCart'])
        ->name('tenant.cart.clear');
    Route::post('/cart/checkout', [\App\Http\Controllers\Tenant\CartController::class, 'checkout'])
        ->name('tenant.cart.checkout');

    // F-140: Delivery/Pickup Choice Selection
    // BR-264: Client must choose delivery or pickup
    // BR-272: Requires authentication
    Route::get('/checkout/delivery-method', [\App\Http\Controllers\Tenant\CheckoutController::class, 'deliveryMethod'])
        ->name('tenant.checkout.delivery-method');
    Route::post('/checkout/delivery-method', [\App\Http\Controllers\Tenant\CheckoutController::class, 'saveDeliveryMethod'])
        ->name('tenant.checkout.save-delivery-method');

    // F-141: Delivery Location Selection
    // BR-274: Town dropdown from cook's delivery areas
    // BR-275: Quarter dropdown filtered by selected town
    // BR-276: Neighbourhood with OpenStreetMap autocomplete
    // BR-278: Saved addresses as quick-select options
    // BR-281: All fields required for delivery orders
    Route::get('/checkout/delivery-location', [\App\Http\Controllers\Tenant\CheckoutController::class, 'deliveryLocation'])
        ->name('tenant.checkout.delivery-location');
    Route::post('/checkout/delivery-location', [\App\Http\Controllers\Tenant\CheckoutController::class, 'saveDeliveryLocation'])
        ->name('tenant.checkout.save-delivery-location');
    Route::post('/checkout/load-quarters', [\App\Http\Controllers\Tenant\CheckoutController::class, 'loadQuarters'])
        ->name('tenant.checkout.load-quarters');

    // F-147: Location Not Available Flow — Switch to Pickup
    // BR-331: Switch to pickup when delivery to selected quarter is not available
    Route::post('/checkout/switch-to-pickup', [\App\Http\Controllers\Tenant\CheckoutController::class, 'switchToPickup'])
        ->name('tenant.checkout.switch-to-pickup');

    // F-142: Pickup Location Selection
    // BR-284: All pickup locations displayed
    // BR-286: Client must select exactly one
    // BR-287: Auto-select if only one location
    // BR-288: Pickup is always free
    Route::get('/checkout/pickup-location', [\App\Http\Controllers\Tenant\CheckoutController::class, 'pickupLocation'])
        ->name('tenant.checkout.pickup-location');
    Route::post('/checkout/pickup-location', [\App\Http\Controllers\Tenant\CheckoutController::class, 'savePickupLocation'])
        ->name('tenant.checkout.save-pickup-location');

    // F-143: Order Phone Number
    // BR-292: Pre-filled from user's profile phone
    // BR-293: Client can override per order
    // BR-295: Cameroon phone format validation
    // BR-296: Required field
    Route::get('/checkout/phone', [\App\Http\Controllers\Tenant\CheckoutController::class, 'phoneNumber'])
        ->name('tenant.checkout.phone');
    Route::post('/checkout/phone', [\App\Http\Controllers\Tenant\CheckoutController::class, 'savePhoneNumber'])
        ->name('tenant.checkout.save-phone');

    // F-146: Order Total Calculation & Summary
    // BR-316: Itemized list with meal grouping
    // BR-321: Grand total = subtotal + delivery fee - promo discount
    // BR-324: Edit Cart link
    // BR-325: Proceed to Payment leads to F-149
    Route::get('/checkout/summary', [\App\Http\Controllers\Tenant\CheckoutController::class, 'summary'])
        ->name('tenant.checkout.summary');

    // F-149: Payment Method Selection
    // BR-345: MTN MoMo, Orange Money, Wallet Balance
    // BR-350: Total displayed prominently
    // BR-352: Pay Now triggers F-150 (Flutterwave) or F-153 (wallet)
    Route::get('/checkout/payment', [\App\Http\Controllers\Tenant\CheckoutController::class, 'paymentMethod'])
        ->name('tenant.checkout.payment');
    Route::post('/checkout/payment', [\App\Http\Controllers\Tenant\CheckoutController::class, 'savePaymentMethod'])
        ->name('tenant.checkout.save-payment');

    // F-153: Wallet Balance Payment
    // BR-387: Only when admin enabled wallet payments globally
    // BR-390: Instant deduction; no external payment gateway
    // BR-391: Order status immediately changes to "Paid"
    Route::get('/checkout/payment/wallet', [\App\Http\Controllers\Tenant\CheckoutController::class, 'processWalletPayment'])
        ->name('tenant.checkout.wallet-payment');

    // F-150: Flutterwave Payment Initiation
    // BR-354: Payment initiated via Flutterwave v3 mobile money charge API
    // BR-358: Order created with "Pending Payment" status
    // BR-360: Waiting UI with countdown timer
    Route::get('/checkout/payment/initiate', [\App\Http\Controllers\Tenant\CheckoutController::class, 'initiatePayment'])
        ->name('tenant.checkout.initiate-payment');
    Route::get('/checkout/payment/waiting/{orderId}', [\App\Http\Controllers\Tenant\CheckoutController::class, 'paymentWaiting'])
        ->name('tenant.checkout.payment-waiting');
    Route::post('/checkout/payment/check-status/{orderId}', [\App\Http\Controllers\Tenant\CheckoutController::class, 'checkPaymentStatus'])
        ->name('tenant.checkout.check-payment-status');
    Route::post('/checkout/payment/cancel/{orderId}', [\App\Http\Controllers\Tenant\CheckoutController::class, 'cancelPayment'])
        ->name('tenant.checkout.cancel-payment');

    // F-152: Payment Retry with Timeout
    // BR-376: On payment failure, order remains for retry window
    // BR-379: Maximum 3 retry attempts allowed
    // BR-380: Each retry creates a new Flutterwave charge
    Route::get('/checkout/payment/retry/{orderId}', [\App\Http\Controllers\Tenant\CheckoutController::class, 'paymentRetry'])
        ->name('tenant.checkout.payment-retry');
    Route::post('/checkout/payment/retry/{orderId}', [\App\Http\Controllers\Tenant\CheckoutController::class, 'processRetryPayment'])
        ->name('tenant.checkout.process-retry');

    // F-154: Payment Receipt & Confirmation
    // BR-398: Displays order number, item summary, total, payment method, reference, status
    // BR-403: Receipt can be downloaded as PDF (via browser print)
    // BR-404: Track Order links to order tracking page
    // BR-406: Confirmation page accessible only to order's owner
    Route::get('/checkout/payment/receipt/{orderId}', [\App\Http\Controllers\Tenant\CheckoutController::class, 'paymentReceipt'])
        ->name('tenant.checkout.payment-receipt');
});

/*
|--------------------------------------------------------------------------
| Webhook Routes (accessible on ALL domains)
|--------------------------------------------------------------------------
|
| External webhook endpoints that receive callbacks from third-party
| services. These routes are excluded from CSRF verification since they
| receive requests from external servers (not browsers).
|
| F-151: Payment Webhook Handling
| BR-364: Verifies Flutterwave webhook signature for authenticity
| BR-375: Returns 200 OK promptly to prevent Flutterwave retries
|
*/
Route::post('/webhooks/flutterwave', [\App\Http\Controllers\Webhook\FlutterwaveWebhookController::class, 'handle'])
    ->name('webhooks.flutterwave');
