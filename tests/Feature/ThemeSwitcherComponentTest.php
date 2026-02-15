<?php

use App\Models\User;

test('theme switcher component renders on login page', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);

    // The component renders three theme buttons with icons
    $content = $response->getContent();
    expect($content)
        ->toContain('role="radiogroup"')
        ->toContain("switchTheme('light')")
        ->toContain("switchTheme('dark')")
        ->toContain("switchTheme('system')");
});

test('theme switcher component renders on register page', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);

    $content = $response->getContent();
    expect($content)->toContain('role="radiogroup"');
});

test('theme switcher component renders alongside language switcher', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);

    $content = $response->getContent();
    // Both switchers should be present
    expect($content)
        ->toContain('role="radiogroup"')  // theme switcher
        ->toContain('aria-haspopup="listbox"');  // language switcher
});

test('theme switcher shows as not authenticated for guests', function () {
    $response = $this->get('/login');

    $content = $response->getContent();
    // Guest users should have isAuthenticated: false
    expect($content)->toContain('isAuthenticated: false');
});

test('theme switcher shows as authenticated for logged in users', function () {
    $user = User::factory()->create();

    // Render the component directly in the application context as an authenticated user
    $this->actingAs($user);

    $rendered = $this->blade('<x-theme-switcher />');
    $rendered->assertSee('isAuthenticated: true', false);
});

test('theme switcher includes sun icon for light mode', function () {
    $response = $this->get('/login');

    $content = $response->getContent();
    // Sun icon: circle element
    expect($content)->toContain('<circle cx="12" cy="12" r="4">');
});

test('theme switcher includes moon icon for dark mode', function () {
    $response = $this->get('/login');

    $content = $response->getContent();
    // Moon icon path
    expect($content)->toContain('M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z');
});

test('theme switcher includes monitor icon for system mode', function () {
    $response = $this->get('/login');

    $content = $response->getContent();
    // Monitor icon rect
    expect($content)->toContain('width="20" height="14"');
});

test('theme switcher has proper aria labels', function () {
    $response = $this->get('/login');

    $content = $response->getContent();
    expect($content)
        ->toContain('aria-label="Light mode"')
        ->toContain('aria-label="Dark mode"')
        ->toContain('aria-label="System default"');
});

test('theme switcher has title attributes for tooltips', function () {
    $response = $this->get('/login');

    $content = $response->getContent();
    expect($content)
        ->toContain('title="Light mode"')
        ->toContain('title="Dark mode"')
        ->toContain('title="System default"');
});

test('theme switcher includes noscript fallback for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $rendered = $this->blade('<x-theme-switcher />');
    $rendered->assertSee('<noscript>', false);
});

test('authenticated user can persist theme via form action', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/theme/update', ['theme' => 'dark']);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'theme_preference' => 'dark',
    ]);
});

test('guest cannot persist theme to database', function () {
    $response = $this->post('/theme/update', ['theme' => 'dark']);

    $response->assertRedirect('/login');
});

test('theme switcher references correct route url', function () {
    $response = $this->get('/login');

    $content = $response->getContent();
    expect($content)->toContain('/theme/update');
});

test('auth layout includes both theme and language switcher in wrapper div', function () {
    $response = $this->get('/login');

    $content = $response->getContent();
    // The wrapper div should contain both switchers with flex layout
    expect($content)->toContain('flex items-center gap-2');
});
