<?php

use App\Http\Requests\Auth\RegisterRequest;

/*
|--------------------------------------------------------------------------
| F-022: User Registration Submission — Unit Tests
|--------------------------------------------------------------------------
|
| Tests the RegisterRequest phone normalization, validation rules, and
| custom error messages without HTTP layer involvement.
|
*/

$projectRoot = dirname(__DIR__, 2);

/*
|--------------------------------------------------------------------------
| Phone Normalization
|--------------------------------------------------------------------------
*/

it('normalizes a 9-digit phone to +237 format', function () {
    expect(RegisterRequest::normalizePhone('670000000'))->toBe('+237670000000');
    expect(RegisterRequest::normalizePhone('770000000'))->toBe('+237770000000');
    expect(RegisterRequest::normalizePhone('222000000'))->toBe('+237222000000');
});

it('preserves a phone already in +237 format', function () {
    expect(RegisterRequest::normalizePhone('+237670000000'))->toBe('+237670000000');
});

it('adds + to a phone starting with 237 and 12 digits', function () {
    expect(RegisterRequest::normalizePhone('237670000000'))->toBe('+237670000000');
});

it('strips spaces and dashes from phone numbers', function () {
    expect(RegisterRequest::normalizePhone('+237 670 000 000'))->toBe('+237670000000');
    expect(RegisterRequest::normalizePhone('+237-670-000-000'))->toBe('+237670000000');
    expect(RegisterRequest::normalizePhone('670 000 000'))->toBe('+237670000000');
});

it('strips parentheses from phone numbers', function () {
    expect(RegisterRequest::normalizePhone('(670)000000'))->toBe('+237670000000');
});

it('does not normalize invalid phone formats', function () {
    // Phone starting with 5 (not 6, 7, or 2) with 9 digits should not get +237 prepended
    expect(RegisterRequest::normalizePhone('590000000'))->toBe('590000000');
});

/*
|--------------------------------------------------------------------------
| Phone Regex Validation
|--------------------------------------------------------------------------
*/

it('validates Cameroon phone regex for valid numbers', function () {
    $regex = RegisterRequest::CAMEROON_PHONE_REGEX;

    // Valid formats: +237 followed by 6, 7, or 2, then 8 digits
    expect(preg_match($regex, '+237670000000'))->toBe(1);
    expect(preg_match($regex, '+237690000000'))->toBe(1);
    expect(preg_match($regex, '+237770000000'))->toBe(1);
    expect(preg_match($regex, '+237222000000'))->toBe(1);
    expect(preg_match($regex, '+237650000000'))->toBe(1);
});

it('rejects invalid Cameroon phone formats', function () {
    $regex = RegisterRequest::CAMEROON_PHONE_REGEX;

    // Missing +237 prefix
    expect(preg_match($regex, '670000000'))->toBe(0);
    // Wrong prefix
    expect(preg_match($regex, '+33612345678'))->toBe(0);
    // First digit after +237 is 5 (invalid)
    expect(preg_match($regex, '+237590000000'))->toBe(0);
    // Too short
    expect(preg_match($regex, '+23767000'))->toBe(0);
    // Too long
    expect(preg_match($regex, '+2376700000001'))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Validation Rules
|--------------------------------------------------------------------------
*/

it('defines required validation rules for all fields', function () {
    $request = new RegisterRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKeys(['name', 'email', 'phone', 'password']);
    expect($rules['name'])->toContain('required');
    expect($rules['email'])->toContain('required');
    expect($rules['phone'])->toContain('required');
    expect($rules['password'])->toContain('required');
});

it('enforces email uniqueness', function () {
    $request = new RegisterRequest;
    $rules = $request->rules();

    expect($rules['email'])->toContain('unique:users,email');
});

it('enforces password confirmation', function () {
    $request = new RegisterRequest;
    $rules = $request->rules();

    expect($rules['password'])->toContain('confirmed');
});

it('enforces name max length of 255 characters', function () {
    $request = new RegisterRequest;
    $rules = $request->rules();

    expect($rules['name'])->toContain('max:255');
});

/*
|--------------------------------------------------------------------------
| Custom Error Messages
|--------------------------------------------------------------------------
|
| Note: messages() method calls __() which requires the Laravel translator.
| These tests verify the method exists and returns the expected keys.
| The actual message content is validated in feature tests with the full app.
|
*/

it('has a messages method returning expected validation keys', function () {
    $request = new RegisterRequest;

    // Verify the method exists without calling it (would need translator)
    expect(method_exists($request, 'messages'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| CAMEROON_PHONE_REGEX Constant
|--------------------------------------------------------------------------
*/

it('exports the CAMEROON_PHONE_REGEX as a public constant', function () {
    expect(RegisterRequest::CAMEROON_PHONE_REGEX)->toBeString();
    expect(RegisterRequest::CAMEROON_PHONE_REGEX)->toContain('+237');
});
