<?php

/*
|--------------------------------------------------------------------------
| F-025: User Logout — Unit Tests
|--------------------------------------------------------------------------
|
| Tests the logout-related business rules at the unit level:
| route configuration, controller method existence, and
| translation strings.
|
*/

$projectRoot = dirname(__DIR__, 3);

it('has the logout route defined as POST only', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    expect($routeContent)->toContain("Route::post('/logout'")
        ->and($routeContent)->toContain("->name('logout')");
});

it('does not have GET logout route defined', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    expect($routeContent)->not->toContain("Route::get('/logout'");
});

it('has the logout method on LoginController', function () use ($projectRoot) {
    $controllerContent = file_get_contents($projectRoot.'/app/Http/Controllers/Auth/LoginController.php');

    expect($controllerContent)->toContain('public function logout(Request $request): mixed');
});

it('logs activity with logged_out event in the logout method', function () use ($projectRoot) {
    $controllerContent = file_get_contents($projectRoot.'/app/Http/Controllers/Auth/LoginController.php');

    expect($controllerContent)->toContain("->event('logged_out')")
        ->and($controllerContent)->toContain("activity('users')");
});

it('captures user before Auth::logout call', function () use ($projectRoot) {
    $controllerContent = file_get_contents($projectRoot.'/app/Http/Controllers/Auth/LoginController.php');

    // The user must be captured BEFORE Auth::logout() is called
    $userPosition = strpos($controllerContent, '$user = Auth::user()');
    $logoutPosition = strpos($controllerContent, 'Auth::logout()');

    expect($userPosition)->not->toBeFalse()
        ->and($logoutPosition)->not->toBeFalse()
        ->and($userPosition)->toBeLessThan($logoutPosition);
});

it('invalidates session after logout', function () use ($projectRoot) {
    $controllerContent = file_get_contents($projectRoot.'/app/Http/Controllers/Auth/LoginController.php');

    expect($controllerContent)->toContain('$request->session()->invalidate()')
        ->and($controllerContent)->toContain('$request->session()->regenerateToken()');
});

it('has logout translation strings in English', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);

    expect($translations)->toHaveKey('Logout')
        ->and($translations)->toHaveKey('User logged out')
        ->and($translations)->toHaveKey('Log Out');
});

it('has logout translation strings in French', function () use ($projectRoot) {
    $translations = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    expect($translations)->toHaveKey('Logout')
        ->and($translations)->toHaveKey('User logged out')
        ->and($translations)->toHaveKey('Log Out');
});

it('does not use auth middleware on logout route', function () use ($projectRoot) {
    $routeContent = file_get_contents($projectRoot.'/routes/web.php');

    // The logout route should NOT have auth middleware so unauthenticated users get
    // redirected to home instead of login
    $logoutLine = '';
    foreach (explode("\n", $routeContent) as $line) {
        if (str_contains($line, "'/logout'")) {
            $logoutLine = $line;
            break;
        }
    }

    expect($logoutLine)->not->toContain("middleware('auth')");
});

it('uses standard redirect instead of gale redirect for logout', function () use ($projectRoot) {
    $controllerContent = file_get_contents($projectRoot.'/app/Http/Controllers/Auth/LoginController.php');

    // Find the logout method — extract from function signature to the closing brace
    $startPos = strpos($controllerContent, 'public function logout');
    $methodSection = substr($controllerContent, $startPos);
    // Find the first closing brace that ends the method body
    $braceCount = 0;
    $methodEnd = 0;
    for ($i = 0; $i < strlen($methodSection); $i++) {
        if ($methodSection[$i] === '{') {
            $braceCount++;
        } elseif ($methodSection[$i] === '}') {
            $braceCount--;
            if ($braceCount === 0) {
                $methodEnd = $i;
                break;
            }
        }
    }
    $logoutMethod = substr($methodSection, 0, $methodEnd + 1);

    expect($logoutMethod)->toContain("return redirect('/')")
        ->and($logoutMethod)->not->toContain('gale()->redirect');
});

it('has logout forms using x-navigate-skip in layout files', function () use ($projectRoot) {
    $mainPublic = file_get_contents($projectRoot.'/resources/views/layouts/main-public.blade.php');
    $tenantPublic = file_get_contents($projectRoot.'/resources/views/layouts/tenant-public.blade.php');
    $admin = file_get_contents($projectRoot.'/resources/views/layouts/admin.blade.php');
    $cookDashboard = file_get_contents($projectRoot.'/resources/views/layouts/cook-dashboard.blade.php');

    // All layouts must have logout forms with x-navigate-skip to trigger full page reload
    expect($mainPublic)->toContain('x-navigate-skip')
        ->and($tenantPublic)->toContain('x-navigate-skip')
        ->and($admin)->toContain('x-navigate-skip')
        ->and($cookDashboard)->toContain('x-navigate-skip');
});

it('has logout forms using POST method in all layouts', function () use ($projectRoot) {
    $mainPublic = file_get_contents($projectRoot.'/resources/views/layouts/main-public.blade.php');
    $tenantPublic = file_get_contents($projectRoot.'/resources/views/layouts/tenant-public.blade.php');
    $admin = file_get_contents($projectRoot.'/resources/views/layouts/admin.blade.php');
    $cookDashboard = file_get_contents($projectRoot.'/resources/views/layouts/cook-dashboard.blade.php');

    // All logout forms must use POST method
    expect($mainPublic)->toContain("method=\"POST\" action=\"{{ route('logout') }}\"")
        ->and($tenantPublic)->toContain("method=\"POST\" action=\"{{ route('logout') }}\"")
        ->and($admin)->toContain("method=\"POST\" action=\"{{ route('logout') }}\"")
        ->and($cookDashboard)->toContain("method=\"POST\" action=\"{{ route('logout') }}\"");
});
