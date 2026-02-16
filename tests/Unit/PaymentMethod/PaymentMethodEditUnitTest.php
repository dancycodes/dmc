<?php

use App\Http\Requests\PaymentMethod\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/*
|--------------------------------------------------------------------------
| F-039: Edit Payment Method — Unit Tests
|--------------------------------------------------------------------------
|
| Unit tests for edit payment method validation, model behavior,
| and business rules.
|
| BR-163: Only label and phone number are editable. Provider is read-only.
| BR-165: Phone validation must match existing provider.
| BR-166: Label uniqueness excludes current method.
| BR-167: Phone shown unmasked for editing.
| BR-168: Users can only edit their own payment methods.
|
*/

$projectRoot = dirname(__DIR__, 3);

/*
|--------------------------------------------------------------------------
| UpdatePaymentMethodRequest Tests
|--------------------------------------------------------------------------
*/

it('UpdatePaymentMethodRequest authorizes authenticated users', function () {
    $request = new UpdatePaymentMethodRequest;
    Auth::shouldReceive('check')->once()->andReturn(true);

    expect($request->authorize())->toBeTrue();
});

it('UpdatePaymentMethodRequest rejects unauthenticated users', function () {
    $request = new UpdatePaymentMethodRequest;
    Auth::shouldReceive('check')->once()->andReturn(false);

    expect($request->authorize())->toBeFalse();
});

it('UpdatePaymentMethodRequest requires label', function () {
    Auth::shouldReceive('id')->andReturn(1);

    $request = new UpdatePaymentMethodRequest;
    $request->setRouteResolver(fn () => new class
    {
        public function parameter($key)
        {
            return null;
        }
    });

    $rules = $request->rules();

    expect($rules['label'])->toContain('required');
    expect($rules['label'])->toContain('string');
    expect($rules['label'])->toContain('max:50');
});

it('UpdatePaymentMethodRequest requires phone', function () {
    Auth::shouldReceive('id')->andReturn(1);

    $request = new UpdatePaymentMethodRequest;
    $request->setRouteResolver(fn () => new class
    {
        public function parameter($key)
        {
            return null;
        }
    });

    $rules = $request->rules();

    expect($rules['phone'])->toContain('required');
    expect($rules['phone'])->toContain('string');
});

it('UpdatePaymentMethodRequest does NOT require provider field', function () {
    Auth::shouldReceive('id')->andReturn(1);

    $request = new UpdatePaymentMethodRequest;
    $request->setRouteResolver(fn () => new class
    {
        public function parameter($key)
        {
            return null;
        }
    });

    $rules = $request->rules();

    expect($rules)->not->toHaveKey('provider');
});

it('UpdatePaymentMethodRequest has label unique rule with user scope', function () {
    Auth::shouldReceive('id')->andReturn(42);

    $request = new UpdatePaymentMethodRequest;
    $request->setRouteResolver(fn () => new class
    {
        public function parameter($key)
        {
            return null;
        }
    });

    $rules = $request->rules();

    // The label rules should contain a unique rule
    $hasUniqueRule = false;
    foreach ($rules['label'] as $rule) {
        if ($rule instanceof \Illuminate\Validation\Rules\Unique) {
            $hasUniqueRule = true;
        }
    }

    expect($hasUniqueRule)->toBeTrue();
});

it('UpdatePaymentMethodRequest has messages method', function () {
    expect(method_exists(UpdatePaymentMethodRequest::class, 'messages'))->toBeTrue();

    $reflection = new ReflectionMethod(UpdatePaymentMethodRequest::class, 'messages');
    expect($reflection->getReturnType()?->getName())->toBe('array');
});

/*
|--------------------------------------------------------------------------
| Model Phone Utility Tests
|--------------------------------------------------------------------------
*/

it('normalizes phone with spaces for update context', function () {
    $normalized = PaymentMethod::normalizePhone('6 80 11 22 33');

    expect($normalized)->toBe('+237680112233');
});

it('normalizes phone with +237 prefix for update context', function () {
    $normalized = PaymentMethod::normalizePhone('+237671234567');

    expect($normalized)->toBe('+237671234567');
});

it('normalizes phone with dashes for update context', function () {
    $normalized = PaymentMethod::normalizePhone('671-234-567');

    expect($normalized)->toBe('+237671234567');
});

it('validates MTN phone matches MTN provider', function () {
    expect(PaymentMethod::phoneMatchesProvider('+237671234567', PaymentMethod::PROVIDER_MTN_MOMO))->toBeTrue();
    expect(PaymentMethod::phoneMatchesProvider('+237680111222', PaymentMethod::PROVIDER_MTN_MOMO))->toBeTrue();
    expect(PaymentMethod::phoneMatchesProvider('+237650111222', PaymentMethod::PROVIDER_MTN_MOMO))->toBeTrue();
});

it('validates Orange phone matches Orange provider', function () {
    expect(PaymentMethod::phoneMatchesProvider('+237691234567', PaymentMethod::PROVIDER_ORANGE_MONEY))->toBeTrue();
    expect(PaymentMethod::phoneMatchesProvider('+237655111222', PaymentMethod::PROVIDER_ORANGE_MONEY))->toBeTrue();
    expect(PaymentMethod::phoneMatchesProvider('+237659111222', PaymentMethod::PROVIDER_ORANGE_MONEY))->toBeTrue();
});

it('rejects MTN phone for Orange provider', function () {
    expect(PaymentMethod::phoneMatchesProvider('+237671234567', PaymentMethod::PROVIDER_ORANGE_MONEY))->toBeFalse();
});

it('rejects Orange phone for MTN provider', function () {
    expect(PaymentMethod::phoneMatchesProvider('+237691234567', PaymentMethod::PROVIDER_MTN_MOMO))->toBeFalse();
});

it('validates Cameroon phone format for valid numbers', function () {
    expect(PaymentMethod::isValidCameroonPhone('+237671234567'))->toBeTrue();
    expect(PaymentMethod::isValidCameroonPhone('+237691234567'))->toBeTrue();
});

it('rejects invalid Cameroon phone format', function () {
    expect(PaymentMethod::isValidCameroonPhone('+237512345678'))->toBeFalse();
    expect(PaymentMethod::isValidCameroonPhone('+23712345'))->toBeFalse();
    expect(PaymentMethod::isValidCameroonPhone('invalid'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| PaymentMethod Model Tests
|--------------------------------------------------------------------------
*/

it('has label and phone in fillable attributes', function () {
    $method = new PaymentMethod;

    expect($method->getFillable())->toContain('label');
    expect($method->getFillable())->toContain('phone');
    expect($method->getFillable())->toContain('provider');
});

it('provider label returns human-readable name', function () {
    $mtn = new PaymentMethod(['provider' => PaymentMethod::PROVIDER_MTN_MOMO]);
    $orange = new PaymentMethod(['provider' => PaymentMethod::PROVIDER_ORANGE_MONEY]);

    expect($mtn->providerLabel())->toBe('MTN MoMo');
    expect($orange->providerLabel())->toBe('Orange Money');
});

it('maskedPhone shows only last 2 digits', function () {
    $method = new PaymentMethod(['phone' => '+237671234567']);

    $masked = $method->maskedPhone();
    expect($masked)->toContain('67');
    expect($masked)->toContain('+237');
    expect($masked)->toContain('*');
});

/*
|--------------------------------------------------------------------------
| Provider Constants Tests
|--------------------------------------------------------------------------
*/

it('has exactly 2 providers', function () {
    expect(PaymentMethod::PROVIDERS)->toHaveCount(2);
    expect(PaymentMethod::PROVIDERS)->toContain(PaymentMethod::PROVIDER_MTN_MOMO);
    expect(PaymentMethod::PROVIDERS)->toContain(PaymentMethod::PROVIDER_ORANGE_MONEY);
});

it('has provider labels for all providers', function () {
    foreach (PaymentMethod::PROVIDERS as $provider) {
        expect(PaymentMethod::PROVIDER_LABELS)->toHaveKey($provider);
    }
});

it('has provider prefixes for all providers', function () {
    foreach (PaymentMethod::PROVIDERS as $provider) {
        expect(PaymentMethod::PROVIDER_PREFIXES)->toHaveKey($provider);
        expect(PaymentMethod::PROVIDER_PREFIXES[$provider])->not->toBeEmpty();
    }
});

/*
|--------------------------------------------------------------------------
| Translation Tests
|--------------------------------------------------------------------------
*/

it('has English translations for edit payment method strings', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

    expect($translations)->toHaveKey('Edit Payment Method');
    expect($translations)->toHaveKey('Back to Payment Methods');
    expect($translations)->toHaveKey('Update your payment method details.');
    expect($translations)->toHaveKey('To change provider, please delete this method and add a new one.');
    expect($translations)->toHaveKey('This phone number does not match :provider.');
    expect($translations)->toHaveKey('Payment method updated.');
});

it('has French translations for edit payment method strings', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($translations)->toHaveKey('Edit Payment Method');
    expect($translations)->toHaveKey('Back to Payment Methods');
    expect($translations)->toHaveKey('Update your payment method details.');
    expect($translations)->toHaveKey('To change provider, please delete this method and add a new one.');
    expect($translations)->toHaveKey('This phone number does not match :provider.');
    expect($translations)->toHaveKey('Payment method updated.');
});

/*
|--------------------------------------------------------------------------
| View File Tests
|--------------------------------------------------------------------------
*/

it('edit view file exists', function () use ($projectRoot) {
    expect(file_exists($projectRoot.'/resources/views/profile/payment-methods/edit.blade.php'))->toBeTrue();
});

it('edit view uses localized strings', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/edit.blade.php');

    expect(str_contains($content, "__('Edit Payment Method')"))->toBeTrue();
    expect(str_contains($content, "__('Back to Payment Methods')"))->toBeTrue();
    expect(str_contains($content, "__('Save Changes')"))->toBeTrue();
    expect(str_contains($content, "__('To change provider, please delete this method and add a new one.')"))->toBeTrue();
});

it('edit view uses semantic color tokens', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/edit.blade.php');

    expect(str_contains($content, 'bg-surface'))->toBeTrue();
    expect(str_contains($content, 'text-on-surface'))->toBeTrue();
    expect(str_contains($content, 'border-outline'))->toBeTrue();
    expect(str_contains($content, 'text-danger'))->toBeTrue();
});

it('edit view supports dark mode', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/edit.blade.php');

    expect(str_contains($content, 'dark:'))->toBeTrue();
});

it('edit view uses Gale patterns', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/edit.blade.php');

    expect(str_contains($content, 'x-data'))->toBeTrue();
    expect(str_contains($content, 'x-sync'))->toBeTrue();
    expect(str_contains($content, '$action'))->toBeTrue();
    expect(str_contains($content, 'x-name'))->toBeTrue();
    expect(str_contains($content, 'x-message'))->toBeTrue();
    expect(str_contains($content, '$fetching()'))->toBeTrue();
});

it('edit view shows provider as read-only with lock icon', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/edit.blade.php');

    // Lock icon should be present
    expect(str_contains($content, 'rect width="18" height="11"'))->toBeTrue();
    // No radio or select for provider
    expect(str_contains($content, 'x-model="provider"'))->toBeFalse();
});

it('edit view uses x-navigate for back link', function () use ($projectRoot) {
    $content = file_get_contents($projectRoot.'/resources/views/profile/payment-methods/edit.blade.php');

    expect(str_contains($content, 'x-navigate'))->toBeTrue();
    expect(str_contains($content, '/profile/payment-methods'))->toBeTrue();
});
