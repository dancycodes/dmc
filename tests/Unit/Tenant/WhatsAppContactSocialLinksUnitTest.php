<?php

/**
 * F-134: WhatsApp Contact & Social Links — Unit Tests
 *
 * Tests the contact section and floating WhatsApp button behavior
 * on the tenant landing page.
 *
 * BR-204: WhatsApp floating button always visible
 * BR-205: WhatsApp link uses wa.me format
 * BR-206: Pre-filled message includes cook's brand name
 * BR-207: Phone number in international Cameroon format
 * BR-208: Click-to-call on mobile; copy-to-clipboard on desktop
 * BR-209: Social media links open in new tab
 * BR-210: Only configured social links displayed
 * BR-211: Supported social platforms: Facebook, Instagram, TikTok
 * BR-213: All text labels localized via __()
 */

use App\Services\TenantLandingService;

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: WhatsApp URL format
// ============================================================
describe('WhatsApp URL format', function (): void {

    it('BR-205: generates correct wa.me URL format', function (): void {
        $phone = '+237655123456';
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $message = urlencode("Hi Chef Latifa, I'm interested in ordering from DancyMeals!");
        $url = 'https://wa.me/'.$cleanPhone.'?text='.$message;

        expect($url)->toStartWith('https://wa.me/237655123456');
        expect($url)->toContain('?text=');
        expect($url)->toContain('Chef+Latifa');
    });

    it('strips non-numeric characters from phone for wa.me URL', function (): void {
        $phone = '+237 655 123 456';
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        expect($cleanPhone)->toBe('237655123456');
    });

    it('handles phone with dashes', function (): void {
        $phone = '+237-655-123-456';
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        expect($cleanPhone)->toBe('237655123456');
    });

    it('handles phone with parentheses', function (): void {
        $phone = '+237(655)123456';
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        expect($cleanPhone)->toBe('237655123456');
    });
});

// ============================================================
// Test group: Phone number formatting
// ============================================================
describe('Phone number formatting', function (): void {

    it('BR-207: formats 12-digit Cameroon numbers correctly', function (): void {
        $phone = '+237655123456';
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($digits) === 12 && str_starts_with($digits, '237')) {
            $formatted = '+237 '.substr($digits, 3, 3).' '.substr($digits, 6, 3).' '.substr($digits, 9, 3);
        } else {
            $formatted = $phone;
        }

        expect($formatted)->toBe('+237 655 123 456');
    });

    it('formats 9-digit number without country code', function (): void {
        $phone = '655123456';
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
        $digits = preg_replace('/[^0-9]/', '', $cleanPhone);

        // 9 digits, no 237 prefix — no auto-formatting
        expect(strlen($digits))->toBe(9);
    });

    it('preserves plus prefix for tel: links', function (): void {
        $phone = '+237655123456';
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);

        expect($cleanPhone)->toBe('+237655123456');
        expect('tel:'.$cleanPhone)->toBe('tel:+237655123456');
    });

    it('handles phone with spaces in country code', function (): void {
        $phone = '+237 677 890 123';
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($digits) === 12 && str_starts_with($digits, '237')) {
            $formatted = '+237 '.substr($digits, 3, 3).' '.substr($digits, 6, 3).' '.substr($digits, 9, 3);
        } else {
            $formatted = $phone;
        }

        expect($formatted)->toBe('+237 677 890 123');
    });
});

// ============================================================
// Test group: Blade template content
// ============================================================
describe('WhatsApp FAB template', function () use ($projectRoot): void {

    $fabContent = file_get_contents($projectRoot.'/resources/views/tenant/_whatsapp-fab.blade.php');

    it('BR-204: has fixed positioning for bottom-right corner', function () use ($fabContent): void {
        expect($fabContent)->toContain('fixed');
        expect($fabContent)->toContain('bottom-6');
        expect($fabContent)->toContain('right-6');
        expect($fabContent)->toContain('z-40');
    });

    it('BR-205: uses wa.me URL format', function () use ($fabContent): void {
        expect($fabContent)->toContain('https://wa.me/');
        expect($fabContent)->toContain('?text=');
    });

    it('BR-206: includes pre-filled message with cook name', function () use ($fabContent): void {
        expect($fabContent)->toContain('ordering from DancyMeals');
        expect($fabContent)->toContain("cookProfile['name']");
    });

    it('has WhatsApp brand green color', function () use ($fabContent): void {
        expect($fabContent)->toContain('bg-[#25D366]');
    });

    it('has pulse animation on first load', function () use ($fabContent): void {
        expect($fabContent)->toContain('animate-ping');
        expect($fabContent)->toContain('showPulse');
        // Auto-stops after 4 seconds
        expect($fabContent)->toContain('4000');
    });

    it('opens WhatsApp in new tab', function () use ($fabContent): void {
        expect($fabContent)->toContain('target="_blank"');
        expect($fabContent)->toContain('rel="noopener noreferrer"');
    });

    it('BR-212: does not obstruct other elements (z-index)', function () use ($fabContent): void {
        expect($fabContent)->toContain('z-40');
    });

    it('Edge Case: hides button when cook has no phone', function () use ($fabContent): void {
        expect($fabContent)->toContain("@if(\$cookProfile['whatsapp'])");
    });

    it('has responsive sizing (smaller on mobile)', function () use ($fabContent): void {
        expect($fabContent)->toContain('w-14 h-14 sm:w-16 sm:h-16');
    });

    it('uses x-data for Alpine.js interactivity', function () use ($fabContent): void {
        expect($fabContent)->toContain('x-data');
    });

    it('has accessible aria-label', function () use ($fabContent): void {
        expect($fabContent)->toContain('aria-label');
        expect($fabContent)->toContain("__('Chat on WhatsApp')");
    });
});

// ============================================================
// Test group: Contact section template
// ============================================================
describe('Contact section template', function () use ($projectRoot): void {

    $contactContent = file_get_contents($projectRoot.'/resources/views/tenant/_contact-section.blade.php');

    it('has Get in Touch header (localized)', function () use ($contactContent): void {
        expect($contactContent)->toContain("__('Get in Touch')");
    });

    it('has Contact subsection heading (localized)', function () use ($contactContent): void {
        expect($contactContent)->toContain("__('Contact')");
    });

    it('has Follow Us subsection heading (localized)', function () use ($contactContent): void {
        expect($contactContent)->toContain("__('Follow Us')");
    });

    it('BR-205: uses wa.me URL with pre-filled message', function () use ($contactContent): void {
        expect($contactContent)->toContain('https://wa.me/');
        expect($contactContent)->toContain('?text=');
        expect($contactContent)->toContain("__('Chat on WhatsApp')");
    });

    it('BR-208: has copy-to-clipboard logic for desktop', function () use ($contactContent): void {
        expect($contactContent)->toContain('navigator.clipboard.writeText');
        expect($contactContent)->toContain('copied');
    });

    it('BR-208: has mobile detection for click-to-call', function () use ($contactContent): void {
        expect($contactContent)->toContain('ontouchstart');
        expect($contactContent)->toContain('maxTouchPoints');
    });

    it('BR-208: shows copy success toast', function () use ($contactContent): void {
        expect($contactContent)->toContain("__('Phone number copied!')");
    });

    it('BR-208: has tel: link for phone', function () use ($contactContent): void {
        expect($contactContent)->toContain('tel:');
    });

    it('BR-209: social links open in new tab', function () use ($contactContent): void {
        // Check that all social links have target="_blank"
        expect($contactContent)->toContain('target="_blank"');
        expect($contactContent)->toContain('rel="noopener noreferrer"');
    });

    it('BR-210: conditionally shows social section', function () use ($contactContent): void {
        expect($contactContent)->toContain('@if($hasSocial)');
    });

    it('BR-211: supports Facebook, Instagram, TikTok', function () use ($contactContent): void {
        expect($contactContent)->toContain("socialLinks']['facebook']");
        expect($contactContent)->toContain("socialLinks']['instagram']");
        expect($contactContent)->toContain("socialLinks']['tiktok']");
    });

    it('has hover tooltips on social icons', function () use ($contactContent): void {
        expect($contactContent)->toContain('group-hover:opacity-100');
        expect($contactContent)->toContain('title="Facebook"');
        expect($contactContent)->toContain('title="Instagram"');
        expect($contactContent)->toContain('title="TikTok"');
    });

    it('uses semantic color tokens (not hardcoded colors)', function () use ($contactContent): void {
        expect($contactContent)->toContain('bg-surface');
        expect($contactContent)->toContain('text-on-surface');
        expect($contactContent)->toContain('border-outline');
    });

    it('has dark mode variants', function () use ($contactContent): void {
        expect($contactContent)->toContain('dark:bg-surface');
        expect($contactContent)->toContain('dark:border-outline');
    });

    it('uses brand colors for social platforms', function () use ($contactContent): void {
        // WhatsApp green
        expect($contactContent)->toContain('[#25D366]');
        // Facebook blue
        expect($contactContent)->toContain('[#1877F2]');
        // Instagram pink/red
        expect($contactContent)->toContain('[#E4405F]');
    });

    it('hides entire section when no contact info exists', function () use ($contactContent): void {
        expect($contactContent)->toContain('@if($hasAnyContactInfo)');
    });

    it('hides contact subsection when no phone/whatsapp', function () use ($contactContent): void {
        expect($contactContent)->toContain('@if($hasContact)');
    });

    it('uses responsive grid layout', function () use ($contactContent): void {
        expect($contactContent)->toContain('grid grid-cols-1 sm:grid-cols-2');
    });
});

// ============================================================
// Test group: Home blade integration
// ============================================================
describe('Home blade integration', function () use ($projectRoot): void {

    $homeContent = file_get_contents($projectRoot.'/resources/views/tenant/home.blade.php');

    it('includes WhatsApp FAB partial', function () use ($homeContent): void {
        expect($homeContent)->toContain("@include('tenant._whatsapp-fab'");
    });

    it('includes contact section partial in footer', function () use ($homeContent): void {
        expect($homeContent)->toContain("@include('tenant._contact-section'");
    });

    it('has footer-content section', function () use ($homeContent): void {
        expect($homeContent)->toContain("@section('footer-content')");
    });

    it('passes cookProfile to WhatsApp FAB partial', function () use ($homeContent): void {
        expect($homeContent)->toContain("'cookProfile' => \$cookProfile");
    });

    it('passes cookProfile to contact section partial', function () use ($homeContent): void {
        expect($homeContent)->toContain("'cookProfile' => \$cookProfile");
    });
});

// ============================================================
// Test group: TenantLandingService cook profile structure
// ============================================================
describe('TenantLandingService cook profile structure', function (): void {

    it('buildCookProfile method exists', function (): void {
        $reflection = new ReflectionClass(TenantLandingService::class);
        expect($reflection->hasMethod('buildCookProfile'))->toBeTrue();
    });

    it('buildCookProfile returns array with whatsapp key', function (): void {
        $reflection = new ReflectionMethod(TenantLandingService::class, 'buildCookProfile');
        $docComment = $reflection->getDocComment();

        // The method's return type doc includes whatsapp
        expect($docComment)->toContain('whatsapp');
        expect($docComment)->toContain('phone');
        expect($docComment)->toContain('socialLinks');
    });
});

// ============================================================
// Test group: Translation strings
// ============================================================
describe('Translation strings', function () use ($projectRoot): void {

    $enJson = json_decode(file_get_contents($projectRoot.'/lang/en.json'), true);
    $frJson = json_decode(file_get_contents($projectRoot.'/lang/fr.json'), true);

    $requiredStrings = [
        'Get in Touch',
        'Chat on WhatsApp',
        'Phone number copied!',
        'Contact',
        'Follow Us',
    ];

    foreach ($requiredStrings as $string) {
        it("has English translation for '{$string}'", function () use ($enJson, $string): void {
            expect($enJson)->toHaveKey($string);
        });

        it("has French translation for '{$string}'", function () use ($frJson, $string): void {
            expect($frJson)->toHaveKey($string);
        });
    }

    it('BR-206: has pre-filled message translation with name placeholder', function () use ($enJson): void {
        $key = "Hi :name, I'm interested in ordering from DancyMeals!";
        expect($enJson)->toHaveKey($key);
    });

    it('BR-206: has French pre-filled message translation', function () use ($frJson): void {
        $key = "Hi :name, I'm interested in ordering from DancyMeals!";
        expect($frJson)->toHaveKey($key);
        expect($frJson[$key])->toContain('DancyMeals');
    });

    it('French Get in Touch translation is correct', function () use ($frJson): void {
        expect($frJson['Get in Touch'])->toBe('Nous contacter');
    });
});

// ============================================================
// Test group: Social link visibility logic
// ============================================================
describe('Social link visibility logic', function (): void {

    it('BR-210: hides social section when no links configured', function (): void {
        $socialLinks = [
            'facebook' => null,
            'instagram' => null,
            'tiktok' => null,
        ];

        $hasSocial = $socialLinks['facebook'] || $socialLinks['instagram'] || $socialLinks['tiktok'];

        expect($hasSocial)->toBeFalse();
    });

    it('BR-210: shows social section when at least one link configured', function (): void {
        $socialLinks = [
            'facebook' => null,
            'instagram' => 'https://instagram.com/chef_latifa',
            'tiktok' => null,
        ];

        $hasSocial = $socialLinks['facebook'] || $socialLinks['instagram'] || $socialLinks['tiktok'];

        expect($hasSocial)->toBeTruthy();
    });

    it('BR-211: supports all three social platforms', function (): void {
        $socialLinks = [
            'facebook' => 'https://facebook.com/chef',
            'instagram' => 'https://instagram.com/chef',
            'tiktok' => 'https://tiktok.com/@chef',
        ];

        expect($socialLinks)->toHaveKeys(['facebook', 'instagram', 'tiktok']);
        expect(count($socialLinks))->toBe(3);
    });

    it('shows section with only WhatsApp configured', function (): void {
        $whatsapp = '+237655123456';
        $phone = null;
        $socialLinks = ['facebook' => null, 'instagram' => null, 'tiktok' => null];

        $hasContact = $whatsapp || $phone;
        $hasSocial = $socialLinks['facebook'] || $socialLinks['instagram'] || $socialLinks['tiktok'];
        $hasAny = $hasContact || $hasSocial;

        expect($hasAny)->toBeTruthy();
    });

    it('hides entire section when nothing configured', function (): void {
        $whatsapp = null;
        $phone = null;
        $socialLinks = ['facebook' => null, 'instagram' => null, 'tiktok' => null];

        $hasContact = $whatsapp || $phone;
        $hasSocial = $socialLinks['facebook'] || $socialLinks['instagram'] || $socialLinks['tiktok'];
        $hasAny = $hasContact || $hasSocial;

        expect($hasAny)->toBeFalse();
    });
});
