<?php

use App\Services\PushNotificationService;

describe('PushNotificationService', function () {
    describe('constants', function () {
        it('has sensible default values', function () {
            expect(PushNotificationService::DISMISS_STORAGE_KEY)->toBe('dmc-push-dismissed-at');
            expect(PushNotificationService::GRANTED_STORAGE_KEY)->toBe('dmc-push-granted');
            expect(PushNotificationService::PROMPT_DELAY_MS)->toBe(2000);
            expect(PushNotificationService::RE_PROMPT_DAYS)->toBe(7);
        });

        it('uses localStorage keys with dmc prefix', function () {
            expect(PushNotificationService::DISMISS_STORAGE_KEY)->toStartWith('dmc-');
            expect(PushNotificationService::GRANTED_STORAGE_KEY)->toStartWith('dmc-');
        });
    });

    describe('class structure', function () {
        it('can be instantiated', function () {
            $service = new PushNotificationService;

            expect($service)->toBeInstanceOf(PushNotificationService::class);
        });

        it('has public methods for subscription management', function () {
            $service = new PushNotificationService;

            expect(method_exists($service, 'storeSubscription'))->toBeTrue();
            expect(method_exists($service, 'deleteSubscription'))->toBeTrue();
            expect(method_exists($service, 'hasSubscriptions'))->toBeTrue();
            expect(method_exists($service, 'subscriptionCount'))->toBeTrue();
        });

        it('has public methods for configuration', function () {
            $service = new PushNotificationService;

            expect(method_exists($service, 'getVapidPublicKey'))->toBeTrue();
            expect(method_exists($service, 'isConfigured'))->toBeTrue();
            expect(method_exists($service, 'getPromptAlpineData'))->toBeTrue();
        });
    });
});

describe('BasePushNotification', function () {
    it('is an abstract class', function () {
        $reflection = new ReflectionClass(\App\Notifications\BasePushNotification::class);

        expect($reflection->isAbstract())->toBeTrue();
    });

    it('implements ShouldQueue interface', function () {
        $reflection = new ReflectionClass(\App\Notifications\BasePushNotification::class);

        expect($reflection->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class))->toBeTrue();
    });

    it('extends Laravel Notification class', function () {
        $reflection = new ReflectionClass(\App\Notifications\BasePushNotification::class);

        expect($reflection->isSubclassOf(\Illuminate\Notifications\Notification::class))->toBeTrue();
    });

    it('has required abstract methods', function () {
        $reflection = new ReflectionClass(\App\Notifications\BasePushNotification::class);
        $abstractMethods = array_filter(
            $reflection->getMethods(),
            fn ($m) => $m->isAbstract()
        );
        $abstractMethodNames = array_map(fn ($m) => $m->getName(), $abstractMethods);

        expect($abstractMethodNames)->toContain('getTitle');
        expect($abstractMethodNames)->toContain('getBody');
        expect($abstractMethodNames)->toContain('getActionUrl');
    });
});
