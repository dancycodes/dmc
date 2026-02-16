<?php

use App\Mail\BaseMailableNotification;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

/**
 * Concrete test mailable extending BaseMailableNotification.
 * Used to verify the base class behavior in feature tests.
 */
class TestOrderConfirmationEmail extends BaseMailableNotification
{
    public function __construct(
        public string $orderNumber,
        public int $amount,
    ) {}

    protected function getSubjectLine(): string
    {
        return $this->trans('Order Confirmation - :orderNumber', [
            'orderNumber' => $this->orderNumber,
        ]);
    }

    protected function getEmailView(): string
    {
        return 'emails.test-order-confirmation';
    }

    protected function getEmailData(): array
    {
        return [
            'orderNumber' => $this->orderNumber,
            'amount' => $this->amount,
        ];
    }

    protected function getEmailType(): string
    {
        return 'order_notification';
    }
}

/**
 * Test critical mailable (password reset) for high-priority queue routing.
 */
class TestCriticalEmail extends BaseMailableNotification
{
    protected function getSubjectLine(): string
    {
        return $this->trans('Reset Your Password');
    }

    protected function getEmailView(): string
    {
        return 'emails.test-order-confirmation';
    }

    protected function getEmailData(): array
    {
        return [];
    }

    protected function getEmailType(): string
    {
        return 'password_reset';
    }
}

describe('BaseMailableNotification', function () {
    beforeEach(function () {
        // Create a minimal test email view for rendering
        View::addNamespace('emails', resource_path('views/emails'));
    });

    it('implements ShouldQueue for async delivery (BR-114)', function () {
        $mailable = new TestOrderConfirmationEmail('ORD-001', 5000);

        expect($mailable)->toBeInstanceOf(ShouldQueue::class);
    });

    it('has 3 retry attempts configured (BR-120)', function () {
        $mailable = new TestOrderConfirmationEmail('ORD-001', 5000);

        expect($mailable->tries)->toBe(3);
    });

    it('has exponential backoff delays (BR-120)', function () {
        $mailable = new TestOrderConfirmationEmail('ORD-001', 5000);

        expect($mailable->backoff)->toBe([10, 30, 90]);
    });

    it('dispatches after database commit when built', function () {
        $mailable = new TestOrderConfirmationEmail('ORD-001', 5000);
        $mailable->build();

        expect($mailable->afterCommit)->toBeTrue();
    });

    it('returns correct envelope with subject', function () {
        $mailable = new TestOrderConfirmationEmail('ORD-001', 5000);
        $envelope = $mailable->envelope();

        expect($envelope)->toBeInstanceOf(Envelope::class);
        expect($envelope->subject)->toContain('ORD-001');
    });

    it('returns content with correct view', function () {
        $mailable = new TestOrderConfirmationEmail('ORD-001', 5000);
        $content = $mailable->content();

        expect($content)->toBeInstanceOf(Content::class);
        expect($content->view)->toBe('emails.test-order-confirmation');
    });

    it('includes common template data in content', function () {
        $mailable = new TestOrderConfirmationEmail('ORD-001', 5000);
        $content = $mailable->content();

        expect($content->with)
            ->toHaveKeys(['appName', 'appUrl', 'supportEmail', 'tenantBranding', 'locale', 'currentYear'])
            ->toHaveKey('orderNumber', 'ORD-001')
            ->toHaveKey('amount', 5000);
    });

    it('sets recipient locale from user preference (BR-118)', function () {
        $user = User::factory()->withLanguage('fr')->make();
        $mailable = (new TestOrderConfirmationEmail('ORD-001', 5000))
            ->forRecipient($user);

        $content = $mailable->content();

        expect($content->with['emailLocale'])->toBe('fr');
        expect($content->with['locale'])->toBe('fr');
    });

    it('defaults to english locale when no recipient set', function () {
        $mailable = new TestOrderConfirmationEmail('ORD-001', 5000);
        $content = $mailable->content();

        expect($content->with['emailLocale'])->toBe('en');
    });

    it('includes tenant branding when tenant is set (BR-116)', function () {
        $tenant = Tenant::factory()->make([
            'name_en' => 'Latifa Kitchen',
            'name_fr' => 'Cuisine de Latifa',
            'slug' => 'latifa',
        ]);

        $mailable = (new TestOrderConfirmationEmail('ORD-001', 5000))
            ->forTenant($tenant);

        $content = $mailable->content();

        expect($content->with['tenantBranding'])->toBe([
            'name' => 'Latifa Kitchen',
            'slug' => 'latifa',
        ]);
    });

    it('has null tenant branding without tenant context', function () {
        $mailable = new TestOrderConfirmationEmail('ORD-001', 5000);
        $content = $mailable->content();

        expect($content->with['tenantBranding'])->toBeNull();
    });

    it('routes general emails to default queue', function () {
        $mailable = new TestOrderConfirmationEmail('ORD-001', 5000);

        expect($mailable->determineQueueName())->toBe(EmailNotificationService::QUEUE_DEFAULT);
    });

    it('routes critical emails to high-priority queue (BR-121)', function () {
        $mailable = new TestCriticalEmail;

        expect($mailable->determineQueueName())->toBe(EmailNotificationService::QUEUE_HIGH);
    });

    it('has no attachments by default', function () {
        $mailable = new TestOrderConfirmationEmail('ORD-001', 5000);

        expect($mailable->attachments())->toBeEmpty();
    });

    it('supports chaining recipient and tenant methods', function () {
        $user = User::factory()->withLanguage('fr')->make();
        $tenant = Tenant::factory()->make([
            'name_en' => 'Powel Dishes',
            'name_fr' => 'Plats de Powel',
            'slug' => 'powel',
        ]);

        $mailable = (new TestOrderConfirmationEmail('ORD-001', 5000))
            ->forRecipient($user)
            ->forTenant($tenant);

        $content = $mailable->content();

        expect($content->with['emailLocale'])->toBe('fr');
        expect($content->with['tenantBranding']['name'])->toBe('Powel Dishes');
    });
});

describe('Mail configuration', function () {
    it('has support email configured in mail config', function () {
        expect(config('mail.support_email'))->not->toBeNull();
    });

    it('has smtp mailer configured', function () {
        $smtp = config('mail.mailers.smtp');

        expect($smtp['transport'])->toBe('smtp');
        expect($smtp)->toHaveKeys(['host', 'port', 'username', 'password']);
    });

    it('has failover mailer configured with smtp and log', function () {
        $failover = config('mail.mailers.failover');

        expect($failover['transport'])->toBe('failover');
        expect($failover['mailers'])->toContain('smtp', 'log');
    });

    it('has from address and name configured', function () {
        expect(config('mail.from'))->toHaveKeys(['address', 'name']);
    });
});

describe('Queue configuration for emails', function () {
    it('has database queue connection configured', function () {
        $database = config('queue.connections.database');

        expect($database['driver'])->toBe('database');
        expect($database['table'])->toBe('jobs');
    });

    it('has pgsql for batching database', function () {
        expect(config('queue.batching.database'))->toBe('pgsql');
    });

    it('has pgsql for failed jobs database', function () {
        expect(config('queue.failed.database'))->toBe('pgsql');
    });
});

describe('Email template views', function () {
    it('base email layout view exists', function () {
        expect(View::exists('emails.layouts.base'))->toBeTrue();
    });

    it('email header partial view exists', function () {
        expect(View::exists('emails.layouts.partials.header'))->toBeTrue();
    });

    it('email footer partial view exists', function () {
        expect(View::exists('emails.layouts.partials.footer'))->toBeTrue();
    });
});

describe('Email sending via Mail facade', function () {
    it('can queue a mailable notification', function () {
        Mail::fake();

        $user = User::factory()->make();
        $mailable = new TestOrderConfirmationEmail('ORD-100', 7500);

        Mail::to($user)->queue($mailable);

        Mail::assertQueued(TestOrderConfirmationEmail::class);
    });

    it('queues mailable with correct recipient', function () {
        Mail::fake();

        $user = User::factory()->make(['email' => 'amina@example.com']);
        $mailable = new TestOrderConfirmationEmail('ORD-200', 3000);

        Mail::to($user)->queue($mailable);

        Mail::assertQueued(TestOrderConfirmationEmail::class, function ($mail) {
            return $mail->hasTo('amina@example.com');
        });
    });

    it('sends mailable with tenant branding data', function () {
        Mail::fake();

        $user = User::factory()->make();
        $tenant = Tenant::factory()->make(['name_en' => 'Grace Cuisine', 'name_fr' => 'Cuisine de Grace', 'slug' => 'grace']);

        $mailable = (new TestOrderConfirmationEmail('ORD-300', 10000))
            ->forTenant($tenant);

        Mail::to($user)->queue($mailable);

        Mail::assertQueued(TestOrderConfirmationEmail::class, function ($mail) {
            $content = $mail->content();

            return $content->with['tenantBranding']['name'] === 'Grace Cuisine';
        });
    });

    it('sends mailable with French locale for French-speaking recipient', function () {
        Mail::fake();

        $user = User::factory()->withLanguage('fr')->make();

        $mailable = (new TestOrderConfirmationEmail('ORD-400', 2000))
            ->forRecipient($user);

        Mail::to($user)->queue($mailable);

        Mail::assertQueued(TestOrderConfirmationEmail::class, function ($mail) {
            $content = $mail->content();

            return $content->with['emailLocale'] === 'fr';
        });
    });
});

describe('EmailNotificationService integration', function () {
    it('is resolvable from the service container', function () {
        $service = app(EmailNotificationService::class);

        expect($service)->toBeInstanceOf(EmailNotificationService::class);
    });

    it('returns platform URL from config', function () {
        $service = app(EmailNotificationService::class);
        $url = $service->getPlatformUrl();

        expect($url)->toBe(config('app.url'));
    });

    it('resolves locale from user preferred language', function () {
        $service = new EmailNotificationService;
        $user = User::factory()->withLanguage('fr')->make();

        expect($service->resolveLocale($user))->toBe('fr');
    });

    it('resolves locale to english for unsupported language', function () {
        $service = new EmailNotificationService;
        $user = User::factory()->make(['preferred_language' => 'de']);

        expect($service->resolveLocale($user))->toBe('en');
    });

    it('resolves locale to default when user is null', function () {
        $service = new EmailNotificationService;

        expect($service->resolveLocale(null))->toBe('en');
    });

    it('returns tenant branding for a valid tenant', function () {
        $service = new EmailNotificationService;
        $tenant = Tenant::factory()->make([
            'name_en' => 'Latifa Kitchen',
            'name_fr' => 'Cuisine de Latifa',
            'slug' => 'latifa',
        ]);

        $branding = $service->getTenantBranding($tenant);

        expect($branding)->toBe([
            'name' => 'Latifa Kitchen',
            'slug' => 'latifa',
        ]);
    });

    it('returns configured from address', function () {
        $service = new EmailNotificationService;
        config(['mail.from.address' => 'test@dancymeals.com']);

        expect($service->getFromAddress())->toBe('test@dancymeals.com');
    });

    it('returns common template data with all keys', function () {
        $service = new EmailNotificationService;
        $data = $service->getCommonTemplateData();

        expect($data)
            ->toHaveKeys(['appName', 'appUrl', 'supportEmail', 'tenantBranding', 'locale', 'currentYear']);
        expect($data['currentYear'])->toBe(now()->year);
        expect($data['tenantBranding'])->toBeNull();
    });

    it('includes tenant branding in common template data', function () {
        $service = new EmailNotificationService;
        $tenant = Tenant::factory()->make([
            'name_en' => 'Powel Dishes',
            'name_fr' => 'Plats de Powel',
            'slug' => 'powel',
        ]);

        $data = $service->getCommonTemplateData($tenant);

        expect($data['tenantBranding']['name'])->toBe('Powel Dishes');
    });

    it('resolves locale from recipient in common template data', function () {
        $service = new EmailNotificationService;
        $user = User::factory()->withLanguage('fr')->make();

        $data = $service->getCommonTemplateData(null, $user);

        expect($data['locale'])->toBe('fr');
    });
});
