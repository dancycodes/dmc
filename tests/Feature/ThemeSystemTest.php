<?php

use App\Models\User;

test('theme update route requires authentication', function () {
    $response = $this->post('/theme/update', ['theme' => 'dark']);

    $response->assertRedirect('/login');
});

test('theme preference route requires authentication', function () {
    $response = $this->get('/theme/preference');

    $response->assertRedirect('/login');
});

test('authenticated user can update theme to dark', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/theme/update', ['theme' => 'dark']);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'theme_preference' => 'dark',
    ]);
});

test('authenticated user can update theme to light', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/theme/update', ['theme' => 'light']);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'theme_preference' => 'light',
    ]);
});

test('authenticated user can update theme to system', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/theme/update', ['theme' => 'system']);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'theme_preference' => null,
    ]);
});

test('theme update rejects invalid theme value', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/theme/update', ['theme' => 'purple']);

    $response->assertSessionHasErrors('theme');
});

test('theme update requires theme parameter', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/theme/update', []);

    $response->assertSessionHasErrors('theme');
});

test('authenticated user can view theme preference', function () {
    $user = User::factory()->withTheme('dark')->create();

    $response = $this->actingAs($user)
        ->get('/theme/preference');

    $response->assertStatus(200)
        ->assertJson(['preference' => 'dark']);
});

test('theme preference defaults to system when not set', function () {
    $user = User::factory()->create(['theme_preference' => null]);

    $response = $this->actingAs($user)
        ->get('/theme/preference');

    $response->assertStatus(200)
        ->assertJson(['preference' => 'system']);
});

test('user model stores theme preference correctly', function () {
    $user = User::factory()->withTheme('dark')->create();

    expect($user->theme_preference)->toBe('dark');
});

test('user model allows null theme preference for system default', function () {
    $user = User::factory()->create(['theme_preference' => null]);

    expect($user->theme_preference)->toBeNull();
});

test('user factory withTheme state works for all valid values', function (?string $theme) {
    $user = User::factory()->withTheme($theme)->create();

    expect($user->theme_preference)->toBe($theme);
})->with([null, 'light', 'dark']);

test('users table has theme_preference column', function () {
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('users');

    expect($columns)->toContain('theme_preference');
});

test('theme preference column is nullable', function () {
    $user = User::factory()->create(['theme_preference' => null]);

    $user->refresh();

    expect($user->theme_preference)->toBeNull();
});

test('auth layout includes FOIT prevention script', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertSee('dmc-theme', false)
        ->assertSee('data-theme', false);
});

test('auth layout includes theme Alpine init script', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertSee('applyTheme()', false);
});

test('css file contains dark mode override rules', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('[data-theme="dark"]')
        ->toContain('--color-surface:')
        ->toContain('--color-on-surface:');
});

test('css file contains custom dark variant configuration', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('@custom-variant dark');
});

test('css file contains semantic color tokens', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('--color-surface:')
        ->toContain('--color-surface-alt:')
        ->toContain('--color-on-surface:')
        ->toContain('--color-on-surface-strong:')
        ->toContain('--color-primary:')
        ->toContain('--color-primary-hover:')
        ->toContain('--color-primary-subtle:')
        ->toContain('--color-on-primary:')
        ->toContain('--color-secondary:')
        ->toContain('--color-secondary-hover:')
        ->toContain('--color-outline:')
        ->toContain('--color-danger:')
        ->toContain('--color-success:')
        ->toContain('--color-warning:')
        ->toContain('--color-info:');
});

test('css file contains smooth theme transition styles', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('theme-transition');
});

test('css file contains print media styles for light mode fallback', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('@media print');
});

test('theme routes are named correctly', function () {
    expect(route('theme.update'))->toContain('/theme/update');
    expect(route('theme.show'))->toContain('/theme/preference');
});
