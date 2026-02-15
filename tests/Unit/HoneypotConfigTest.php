<?php

declare(strict_types=1);

/**
 * Unit tests for F-018: Honeypot Protection Setup
 *
 * Tests configuration values, custom responder structure, and
 * published view template correctness without requiring database access.
 */
$configPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR;
$viewPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'honeypot'.DIRECTORY_SEPARATOR;

describe('Honeypot Configuration', function () use ($configPath) {
    it('has honeypot config file published', function () use ($configPath) {
        expect(file_exists($configPath.'honeypot.php'))->toBeTrue();
    });

    it('is enabled by default', function () use ($configPath) {
        $config = include $configPath.'honeypot.php';

        expect($config['enabled'])->toBeTrue();
    });

    it('uses a non-obvious default field name (BR-150)', function () use ($configPath) {
        $config = include $configPath.'honeypot.php';

        expect($config['name_field_name'])->toBe('my_name');
    });

    it('randomizes field names for additional protection (BR-150)', function () use ($configPath) {
        $config = include $configPath.'honeypot.php';

        expect($config['randomize_name_field_name'])->toBeTrue();
    });

    it('enforces timestamp validation (BR-146)', function () use ($configPath) {
        $config = include $configPath.'honeypot.php';

        expect($config['valid_from_timestamp'])->toBeTrue();
    });

    it('has 2-second minimum submission threshold (BR-146/BR-147)', function () use ($configPath) {
        $config = include $configPath.'honeypot.php';

        expect($config['amount_of_seconds'])->toBe(2);
    });

    it('uses SilentSpamResponder for silent rejection (BR-145/BR-148)', function () use ($configPath) {
        $config = include $configPath.'honeypot.php';

        expect($config['respond_to_spam_with'])->toBe(\App\Http\Responses\SilentSpamResponder::class);
    });

    it('does not require honeypot fields for all forms', function () use ($configPath) {
        $config = include $configPath.'honeypot.php';

        expect($config['honeypot_fields_required_for_all_forms'])->toBeFalse();
    });

    it('allows all settings to be configured via env variables', function () use ($configPath) {
        $configContent = file_get_contents($configPath.'honeypot.php');

        expect($configContent)->toContain('HONEYPOT_ENABLED');
        expect($configContent)->toContain('HONEYPOT_NAME');
        expect($configContent)->toContain('HONEYPOT_RANDOMIZE');
        expect($configContent)->toContain('HONEYPOT_VALID_FROM_TIMESTAMP');
        expect($configContent)->toContain('HONEYPOT_VALID_FROM');
        expect($configContent)->toContain('HONEYPOT_SECONDS');
    });
});

describe('SilentSpamResponder Structure', function () {
    it('class exists and is loadable', function () {
        expect(class_exists(\App\Http\Responses\SilentSpamResponder::class))->toBeTrue();
    });

    it('implements SpamResponder interface', function () {
        $interfaces = class_implements(\App\Http\Responses\SilentSpamResponder::class);

        expect($interfaces)->toContain(\Spatie\Honeypot\SpamResponder\SpamResponder::class);
    });

    it('has respond method', function () {
        expect(method_exists(\App\Http\Responses\SilentSpamResponder::class, 'respond'))->toBeTrue();
    });
});

describe('Published Honeypot View Template', function () use ($viewPath) {
    it('has published honeypotFormFields blade template', function () use ($viewPath) {
        expect(file_exists($viewPath.'honeypotFormFields.blade.php'))->toBeTrue();
    });

    it('uses CSS positioning instead of display:none (BR-144)', function () use ($viewPath) {
        $template = file_get_contents($viewPath.'honeypotFormFields.blade.php');

        // Must NOT use display: none (bots detect it)
        expect($template)->not->toContain('display: none');
        expect($template)->not->toContain('display:none');

        // Must use positioning-based hiding
        expect($template)->toContain('position: absolute');
        expect($template)->toContain('left: -9999px');
    });

    it('has aria-hidden attribute for screen reader accessibility', function () use ($viewPath) {
        $template = file_get_contents($viewPath.'honeypotFormFields.blade.php');

        expect($template)->toContain('aria-hidden="true"');
    });

    it('has tabindex=-1 to prevent keyboard navigation', function () use ($viewPath) {
        $template = file_get_contents($viewPath.'honeypotFormFields.blade.php');

        expect($template)->toContain('tabindex="-1"');
    });

    it('includes both name and valid_from input fields', function () use ($viewPath) {
        $template = file_get_contents($viewPath.'honeypotFormFields.blade.php');

        expect($template)->toContain('$nameFieldName');
        expect($template)->toContain('$validFromFieldName');
        expect($template)->toContain('$encryptedValidFrom');
    });

    it('uses autocomplete="nope" to prevent browser autofill on honeypot field', function () use ($viewPath) {
        $template = file_get_contents($viewPath.'honeypotFormFields.blade.php');

        expect($template)->toContain('autocomplete="nope"');
    });
});

describe('Auth Form Templates Include Honeypot', function () {
    $authViewPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR;

    it('registration form includes honeypot component (BR-143)', function () use ($authViewPath) {
        $template = file_get_contents($authViewPath.'register.blade.php');

        expect($template)->toContain('<x-honeypot');
    });

    it('login form includes honeypot component (BR-143)', function () use ($authViewPath) {
        $template = file_get_contents($authViewPath.'login.blade.php');

        expect($template)->toContain('<x-honeypot');
    });

    it('password reset form does not include honeypot component (BR-069)', function () use ($authViewPath) {
        $template = file_get_contents($authViewPath.'passwords'.DIRECTORY_SEPARATOR.'email.blade.php');

        // BR-069: Honeypot protection is not required on the password reset form
        expect($template)->not->toContain('<x-honeypot');
    });
});

describe('Middleware Registration', function () {
    it('honeypot middleware alias is registered in bootstrap/app.php', function () {
        $bootstrapContent = file_get_contents(
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php'
        );

        expect($bootstrapContent)->toContain("'honeypot'");
        expect($bootstrapContent)->toContain('ProtectAgainstSpam');
    });
});
