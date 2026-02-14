<?php

use App\Models\User;

test('registration page is accessible', function () {
    $response = $this->get('/register');

    $response->assertStatus(200)
        ->assertSee(__('Create your account'));
});

test('login page is accessible', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertSee(__('Sign in to your account'));
});

test('password reset request page is accessible', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200)
        ->assertSee(__('Reset your password'));
});

test('user can register with valid data', function () {
    $response = $this->post('/register', [
        'name' => 'Latifa Kamga',
        'email' => 'latifa@example.com',
        'phone' => '690123456',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'latifa@example.com',
        'phone' => '690123456',
        'is_active' => true,
        'preferred_language' => 'en',
    ]);
});

test('registration normalizes email to lowercase', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'TEST@EXAMPLE.COM',
        'phone' => '690000001',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
    ]);
});

test('registration trims whitespace from name', function () {
    $this->post('/register', [
        'name' => '  Latifa Kamga  ',
        'email' => 'latifa2@example.com',
        'phone' => '690000002',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $this->assertDatabaseHas('users', [
        'name' => 'Latifa Kamga',
    ]);
});

test('registration fails with duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->post('/register', [
        'name' => 'Another User',
        'email' => 'taken@example.com',
        'phone' => '690000003',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('registration fails with invalid cameroonian phone', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'phone-test@example.com',
        'phone' => '123456789',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('registration accepts phone with +237 prefix', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'prefix-test@example.com',
        'phone' => '+237690000004',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $this->assertAuthenticated();
});

test('registration fails with short password', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'short-pass@example.com',
        'phone' => '690000005',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

test('registration fails with mismatched password confirmation', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'mismatch@example.com',
        'phone' => '690000006',
        'password' => 'password123',
        'password_confirmation' => 'differentpass',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'login@example.com',
        'password' => 'password123',
    ]);

    $response = $this->post('/login', [
        'email' => 'login@example.com',
        'password' => 'password123',
    ]);

    $this->assertAuthenticated();
});

test('login fails with wrong password', function () {
    User::factory()->create([
        'email' => 'wrong@example.com',
        'password' => 'correctpassword',
    ]);

    $response = $this->post('/login', [
        'email' => 'wrong@example.com',
        'password' => 'wrongpassword',
    ]);

    $this->assertGuest();
});

test('login fails with non-existent email', function () {
    $response = $this->post('/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
    ]);

    $this->assertGuest();
});

test('authenticated user can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout');

    $this->assertGuest();
});

test('guest cannot access logout', function () {
    $response = $this->post('/logout');

    $response->assertRedirect('/login');
});

test('authenticated user is redirected from login page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/login');

    $response->assertRedirect('/');
});

test('authenticated user is redirected from register page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/register');

    $response->assertRedirect('/');
});

test('login page is accessible on tenant domain', function () {
    $tenant = \App\Models\Tenant::factory()->withSlug('auth-test', 'Auth Test Cook')->create();

    $response = $this->get('http://auth-test.dm.test/login');

    $response->assertStatus(200)
        ->assertSee(__('Sign in to your account'))
        ->assertSee('Auth Test Cook');
});

test('register page is accessible on tenant domain', function () {
    $tenant = \App\Models\Tenant::factory()->withSlug('auth-reg', 'Auth Reg Cook')->create();

    $response = $this->get('http://auth-reg.dm.test/register');

    $response->assertStatus(200)
        ->assertSee(__('Create your account'))
        ->assertSee('Auth Reg Cook');
});

test('registration on tenant domain creates user with same account system', function () {
    $tenant = \App\Models\Tenant::factory()->withSlug('tenant-reg', 'Tenant Reg Cook')->create();

    $response = $this->post('http://tenant-reg.dm.test/register', [
        'name' => 'Tenant User',
        'email' => 'tenant-user@example.com',
        'phone' => '690000007',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'tenant-user@example.com',
    ]);
});

test('users table has all required custom columns', function () {
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('users');

    expect($columns)
        ->toContain('phone')
        ->toContain('is_active')
        ->toContain('profile_photo_path')
        ->toContain('preferred_language');
});

test('user factory generates valid cameroonian phone numbers', function () {
    $user = User::factory()->create();

    expect($user->phone)
        ->toMatch('/^6[0-9]{8}$/')
        ->toHaveLength(9);
});

test('user factory creates user with all required fields', function () {
    $user = User::factory()->create();

    expect($user->name)->not->toBeEmpty()
        ->and($user->email)->not->toBeEmpty()
        ->and($user->phone)->not->toBeEmpty()
        ->and($user->password)->not->toBeEmpty()
        ->and($user->is_active)->toBeTrue()
        ->and($user->preferred_language)->toBe('en');
});

test('user model casts password as hashed', function () {
    $user = User::factory()->create(['password' => 'plaintext']);

    expect($user->password)->not->toBe('plaintext')
        ->and(password_verify('plaintext', $user->password))->toBeTrue();
});

test('user model defaults is_active to true', function () {
    $user = User::factory()->create();

    expect($user->is_active)->toBeTrue();
});

test('user model defaults preferred_language to en', function () {
    $user = User::factory()->create();

    expect($user->preferred_language)->toBe('en');
});

test('user factory inactive state works correctly', function () {
    $user = User::factory()->inactive()->create();

    expect($user->is_active)->toBeFalse();
});
