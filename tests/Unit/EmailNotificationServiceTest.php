<?php

use App\Services\EmailNotificationService;

/*
|--------------------------------------------------------------------------
| Unit tests for EmailNotificationService
|--------------------------------------------------------------------------
|
| Pure logic tests that do not require the Laravel application container.
| Tests that need config(), now(), or factories are in the Feature test.
|
*/

describe('getQueueName', function () {
    it('returns high priority queue for password reset', function () {
        $service = new EmailNotificationService;

        expect($service->getQueueName('password_reset'))
            ->toBe(EmailNotificationService::QUEUE_HIGH);
    });

    it('returns high priority queue for email verification', function () {
        $service = new EmailNotificationService;

        expect($service->getQueueName('email_verification'))
            ->toBe(EmailNotificationService::QUEUE_HIGH);
    });

    it('returns default queue for general emails', function () {
        $service = new EmailNotificationService;

        expect($service->getQueueName('general'))
            ->toBe(EmailNotificationService::QUEUE_DEFAULT);
    });

    it('returns default queue for order notification emails', function () {
        $service = new EmailNotificationService;

        expect($service->getQueueName('order_notification'))
            ->toBe(EmailNotificationService::QUEUE_DEFAULT);
    });
});

describe('isCriticalEmail', function () {
    it('identifies password reset as critical', function () {
        $service = new EmailNotificationService;

        expect($service->isCriticalEmail('password_reset'))->toBeTrue();
    });

    it('identifies email verification as critical', function () {
        $service = new EmailNotificationService;

        expect($service->isCriticalEmail('email_verification'))->toBeTrue();
    });

    it('does not identify general emails as critical', function () {
        $service = new EmailNotificationService;

        expect($service->isCriticalEmail('general'))->toBeFalse();
    });
});

describe('getRetryDelays', function () {
    it('returns exponential backoff delays', function () {
        $service = new EmailNotificationService;

        expect($service->getRetryDelays())->toBe([10, 30, 90]);
    });

    it('returns exactly 3 delay values matching MAX_RETRIES', function () {
        $service = new EmailNotificationService;

        expect($service->getRetryDelays())
            ->toHaveCount(EmailNotificationService::MAX_RETRIES);
    });
});

describe('getTenantBranding', function () {
    it('returns null when no tenant is provided', function () {
        $service = new EmailNotificationService;

        expect($service->getTenantBranding(null))->toBeNull();
    });
});

describe('constants', function () {
    it('has correct default from address', function () {
        expect(EmailNotificationService::DEFAULT_FROM_ADDRESS)
            ->toBe('noreply@dancymeals.com');
    });

    it('has correct default from name', function () {
        expect(EmailNotificationService::DEFAULT_FROM_NAME)
            ->toBe('DancyMeals');
    });

    it('has max retries of 3', function () {
        expect(EmailNotificationService::MAX_RETRIES)->toBe(3);
    });

    it('has distinct queue names for default and high priority', function () {
        expect(EmailNotificationService::QUEUE_DEFAULT)
            ->not->toBe(EmailNotificationService::QUEUE_HIGH);
    });

    it('has emails queue name', function () {
        expect(EmailNotificationService::QUEUE_DEFAULT)->toBe('emails');
    });

    it('has emails-high queue name', function () {
        expect(EmailNotificationService::QUEUE_HIGH)->toBe('emails-high');
    });
});
