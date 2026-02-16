<?php

use App\Http\Controllers\PaymentMethodController;
use App\Models\PaymentMethod;

/*
|--------------------------------------------------------------------------
| F-040: Delete Payment Method — Unit Tests
|--------------------------------------------------------------------------
|
| Unit tests for the payment method deletion logic: controller structure,
| model behavior, view content, route definitions, and translation keys.
|
| BR-170: Confirmation dialog before deletion.
| BR-171: Payment methods can always be deleted (no order dependency).
| BR-172: If deleted method was default, first remaining becomes default.
| BR-173: Users can only delete their own payment methods.
| BR-174: Hard delete (permanent).
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| Controller Method Tests
|--------------------------------------------------------------------------
*/

it('PaymentMethodController has a destroy method', function () {
    $controller = new PaymentMethodController;
    expect(method_exists($controller, 'destroy'))->toBeTrue();
});

it('destroy method accepts Request and PaymentMethod parameters', function () {
    $reflection = new ReflectionMethod(PaymentMethodController::class, 'destroy');
    $params = $reflection->getParameters();

    expect($params)->toHaveCount(2);
    expect($params[0]->getName())->toBe('request');
    expect($params[1]->getName())->toBe('paymentMethod');
});

it('destroy method has mixed return type', function () {
    $reflection = new ReflectionMethod(PaymentMethodController::class, 'destroy');
    $returnType = $reflection->getReturnType();

    expect($returnType)->not->toBeNull();
    expect($returnType->getName())->toBe('mixed');
});

/*
|--------------------------------------------------------------------------
| Model Tests
|--------------------------------------------------------------------------
*/

it('PaymentMethod model does not use SoftDeletes (BR-174)', function () {
    $method = new PaymentMethod;
    $traits = class_uses_recursive($method);

    expect($traits)->not->toContain(\Illuminate\Database\Eloquent\SoftDeletes::class);
});

it('PaymentMethod model has MAX_PAYMENT_METHODS_PER_USER constant set to 3', function () {
    expect(PaymentMethod::MAX_PAYMENT_METHODS_PER_USER)->toBe(3);
});

it('PaymentMethod model has is_default in fillable array', function () {
    $model = new PaymentMethod;
    expect($model->getFillable())->toContain('is_default');
});

it('PaymentMethod model casts is_default to boolean', function () {
    $model = new PaymentMethod;
    $casts = $model->getCasts();
    expect($casts)->toHaveKey('is_default');
    expect($casts['is_default'])->toBe('boolean');
});

/*
|--------------------------------------------------------------------------
| View File Tests — Confirmation Modal
|--------------------------------------------------------------------------
*/

it('index view contains delete confirmation modal title', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain("__('Delete this payment method?')");
});

it('index view contains confirmation warning text', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain("__('This cannot be undone.')");
});

it('index view contains delete and cancel buttons in modal', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain("__('Delete')");
    expect($viewContent)->toContain("__('Cancel')");
});

it('index view contains deleting loading state', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain("__('Deleting...')");
});

it('index view uses Alpine.js confirmDelete function', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain('confirmDelete(');
});

it('index view uses Alpine.js cancelDelete function', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain('cancelDelete()');
});

it('index view uses Alpine.js executeDelete function', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain('executeDelete()');
});

it('index view has delete modal with Alpine x-show', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain('x-show="deleteModal"');
});

it('index view uses $action for delete request', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain("method: 'DELETE'");
});

it('index view uses danger color tokens for delete modal', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)
        ->toContain('bg-danger')
        ->toContain('text-danger')
        ->toContain('bg-danger-subtle');
});

it('index view has dark mode support for delete modal backdrop', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain('dark:bg-black/70');
});

it('index view uses x-teleport for modal', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain('x-teleport="body"');
});

it('index view has escape key handler for modal', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain('@keydown.escape');
});

it('index view has backdrop click to close modal', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain('@click="cancelDelete()"');
});

it('index view displays method label in modal', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain('x-text="deleteMethodLabel"');
});

it('index view displays method provider in modal', function () use ($projectRoot) {
    $viewContent = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/index.blade.php');

    expect($viewContent)->toContain('x-text="deleteMethodProvider"');
});

/*
|--------------------------------------------------------------------------
| Route Tests
|--------------------------------------------------------------------------
*/

it('delete payment method route is defined', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    expect($routeContent)->toContain("Route::delete('/profile/payment-methods/{paymentMethod}'");
    expect($routeContent)->toContain("->name('payment-methods.destroy')");
});

it('delete route uses PaymentMethodController destroy method', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    expect($routeContent)->toContain("[PaymentMethodController::class, 'destroy']");
});

it('delete route is inside auth middleware group', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    expect($routeContent)->toContain("Route::middleware('auth')->group(function ()");
    expect($routeContent)->toContain("Route::delete('/profile/payment-methods/{paymentMethod}'");
});

/*
|--------------------------------------------------------------------------
| Translation Key Tests
|--------------------------------------------------------------------------
*/

it('has required English translation keys for payment method delete', function () use ($projectRoot) {
    $enJson = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

    expect($enJson)
        ->toHaveKey('Delete this payment method?')
        ->toHaveKey('Payment method deleted.')
        ->toHaveKey('Payment method deleted');
});

it('has required French translation keys for payment method delete', function () use ($projectRoot) {
    $frJson = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($frJson)
        ->toHaveKey('Delete this payment method?')
        ->toHaveKey('Payment method deleted.')
        ->toHaveKey('Payment method deleted');
});

it('French translations are not same as English for delete feature', function () use ($projectRoot) {
    $enJson = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
    $frJson = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($frJson['Delete this payment method?'])->not->toBe($enJson['Delete this payment method?']);
    expect($frJson['Payment method deleted.'])->not->toBe($enJson['Payment method deleted.']);
});
