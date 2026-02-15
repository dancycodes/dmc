<?php

use App\Models\User;
use App\Notifications\BasePushNotification;
use App\Services\PushNotificationService;
use NotificationChannels\WebPush\WebPushChannel;

describe('Push Subscription Routes', function () {
    describe('POST /push/subscribe', function () {
        it('requires authentication', function () {
            $response = $this->postJson('/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
                'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
            ]);

            $response->assertStatus(401);
        });

        it('stores a push subscription for authenticated user', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
                'keys' => [
                    'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8p8REfWRk',
                    'auth' => 'tBHItJI5svbpC7eTnZ9TWg',
                ],
                'contentEncoding' => 'aesgcm',
            ]);

            $response->assertOk()
                ->assertJson(['message' => 'Push subscription stored successfully.']);

            expect($user->pushSubscriptions()->count())->toBe(1);
        });

        it('validates endpoint is required', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/push/subscribe', [
                'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['endpoint']);
        });

        it('validates endpoint is a valid URL', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/push/subscribe', [
                'endpoint' => 'not-a-url',
                'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['endpoint']);
        });

        it('validates keys are required', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['keys']);
        });

        it('validates p256dh key is required', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
                'keys' => ['auth' => 'auth'],
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['keys.p256dh']);
        });

        it('validates auth key is required', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
                'keys' => ['p256dh' => 'key'],
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['keys.auth']);
        });

        it('validates contentEncoding accepts valid values', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
                'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
                'contentEncoding' => 'invalid-encoding',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['contentEncoding']);
        });

        it('updates existing subscription for same endpoint', function () {
            $user = User::factory()->create();
            $endpoint = 'https://fcm.googleapis.com/fcm/send/same-endpoint';

            $this->actingAs($user)->postJson('/push/subscribe', [
                'endpoint' => $endpoint,
                'keys' => ['p256dh' => 'old-key', 'auth' => 'old-auth'],
            ]);

            $this->actingAs($user)->postJson('/push/subscribe', [
                'endpoint' => $endpoint,
                'keys' => ['p256dh' => 'new-key', 'auth' => 'new-auth'],
            ]);

            expect($user->pushSubscriptions()->count())->toBe(1);
            expect($user->pushSubscriptions()->first()->public_key)->toBe('new-key');
        });

        it('supports multiple subscriptions per user (BR-108)', function () {
            $user = User::factory()->create();

            $this->actingAs($user)->postJson('/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/device-1',
                'keys' => ['p256dh' => 'key1', 'auth' => 'auth1'],
            ]);

            $this->actingAs($user)->postJson('/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/device-2',
                'keys' => ['p256dh' => 'key2', 'auth' => 'auth2'],
            ]);

            expect($user->pushSubscriptions()->count())->toBe(2);
        });
    });

    describe('POST /push/unsubscribe', function () {
        it('requires authentication', function () {
            $response = $this->postJson('/push/unsubscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
            ]);

            $response->assertStatus(401);
        });

        it('removes a push subscription', function () {
            $user = User::factory()->create();
            $endpoint = 'https://fcm.googleapis.com/fcm/send/to-remove';

            $user->updatePushSubscription($endpoint, 'key', 'auth');

            $response = $this->actingAs($user)->postJson('/push/unsubscribe', [
                'endpoint' => $endpoint,
            ]);

            $response->assertOk()
                ->assertJson(['message' => 'Push subscription removed successfully.']);

            expect($user->pushSubscriptions()->count())->toBe(0);
        });

        it('returns 422 when endpoint is missing', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson('/push/unsubscribe', []);

            $response->assertStatus(422);
        });
    });
});

describe('User Model Push Subscriptions', function () {
    it('has push subscriptions relationship via HasPushSubscriptions trait', function () {
        $user = User::factory()->create();

        expect($user->pushSubscriptions())->toBeInstanceOf(
            \Illuminate\Database\Eloquent\Relations\MorphMany::class
        );
    });

    it('can route notifications for WebPush channel', function () {
        $user = User::factory()->create();

        expect($user->routeNotificationForWebPush())->toBeInstanceOf(
            \Illuminate\Database\Eloquent\Collection::class
        );
    });
});

describe('PushNotificationService', function () {
    it('returns true when VAPID keys are configured', function () {
        $service = app(PushNotificationService::class);

        expect($service->isConfigured())->toBeTrue();
    });

    it('returns the VAPID public key from config', function () {
        $service = app(PushNotificationService::class);

        expect($service->getVapidPublicKey())->not->toBeEmpty();
    });

    it('generates Alpine data with required properties', function () {
        $service = app(PushNotificationService::class);
        $alpineData = $service->getPromptAlpineData();

        expect($alpineData)->toBeString()
            ->toContain('showPrompt')
            ->toContain('requestPermission')
            ->toContain('dismissPrompt')
            ->toContain('subscribe')
            ->toContain('/push/subscribe');
    });

    it('stores and deletes subscriptions', function () {
        $user = User::factory()->create();
        $service = app(PushNotificationService::class);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/service-test';

        $service->storeSubscription($user, [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
        ]);

        expect($service->hasSubscriptions($user))->toBeTrue();
        expect($service->subscriptionCount($user))->toBe(1);

        $service->deleteSubscription($user, $endpoint);

        expect($service->hasSubscriptions($user))->toBeFalse();
    });
});

describe('BasePushNotification', function () {
    it('uses WebPush and database channels', function () {
        $notification = new class extends BasePushNotification
        {
            public function getTitle(object $notifiable): string
            {
                return 'Test Title';
            }

            public function getBody(object $notifiable): string
            {
                return 'Test Body';
            }

            public function getActionUrl(object $notifiable): string
            {
                return '/test-url';
            }
        };

        $user = User::factory()->create();
        $channels = $notification->via($user);

        expect($channels)->toContain(WebPushChannel::class);
        expect($channels)->toContain('database');
    });

    it('generates correct toArray for database storage', function () {
        $notification = new class extends BasePushNotification
        {
            public function getTitle(object $notifiable): string
            {
                return 'Order Confirmed';
            }

            public function getBody(object $notifiable): string
            {
                return 'Your order #123 has been confirmed.';
            }

            public function getActionUrl(object $notifiable): string
            {
                return '/orders/123';
            }

            /** @return array<string, mixed> */
            public function getData(object $notifiable): array
            {
                return ['order_id' => 123];
            }
        };

        $user = User::factory()->create();
        $array = $notification->toArray($user);

        expect($array)->toHaveKeys(['title', 'body', 'icon', 'action_url', 'data']);
        expect($array['title'])->toBe('Order Confirmed');
        expect($array['body'])->toBe('Your order #123 has been confirmed.');
        expect($array['action_url'])->toBe('/orders/123');
        expect($array['icon'])->toBe('/icons/icon-192x192.png');
        expect($array['data'])->toHaveKey('order_id', 123);
    });

    it('generates WebPush message with correct structure', function () {
        $notification = new class extends BasePushNotification
        {
            public function getTitle(object $notifiable): string
            {
                return 'Payment Received';
            }

            public function getBody(object $notifiable): string
            {
                return 'Payment of 5000 XAF received.';
            }

            public function getActionUrl(object $notifiable): string
            {
                return '/payments/456';
            }

            public function getTag(object $notifiable): ?string
            {
                return 'payment-456';
            }
        };

        $user = User::factory()->create();
        $webPushMessage = $notification->toWebPush($user, $notification);

        $messageArray = $webPushMessage->toArray();

        expect($messageArray['title'])->toBe('Payment Received');
        expect($messageArray['body'])->toBe('Payment of 5000 XAF received.');
        expect($messageArray['data']['url'])->toBe('/payments/456');
        expect($messageArray['tag'])->toBe('payment-456');
    });
});

describe('VAPID Configuration', function () {
    it('has VAPID keys configured in webpush config', function () {
        expect(config('webpush.vapid.public_key'))->not->toBeEmpty();
        expect(config('webpush.vapid.private_key'))->not->toBeEmpty();
        expect(config('webpush.vapid.subject'))->not->toBeEmpty();
    });

    it('has push_subscriptions table in database', function () {
        expect(\Illuminate\Support\Facades\Schema::hasTable('push_subscriptions'))->toBeTrue();
    });
});

describe('Push Notification Prompt Component', function () {
    it('renders the prompt for authenticated users on the login page view', function () {
        $user = User::factory()->create();

        $view = $this->actingAs($user)->view('components.push-notification-prompt');

        $view->assertSee('push-notification-prompt', false);
        $view->assertSee('Stay updated on your orders', false);
    });

    it('does not render the prompt for guests', function () {
        $view = $this->view('components.push-notification-prompt');

        // The component uses @auth, so guests see nothing
        $view->assertDontSee('push-notification-prompt', false);
    });

    it('includes Allow and Maybe Later buttons', function () {
        $user = User::factory()->create();

        $view = $this->actingAs($user)->view('components.push-notification-prompt');

        $view->assertSee('push-allow-btn', false);
        $view->assertSee('push-later-btn', false);
    });
});

describe('Service Worker', function () {
    it('contains push event handler', function () {
        $swContent = file_get_contents(public_path('service-worker.js'));

        expect($swContent)->toContain("self.addEventListener('push'");
        expect($swContent)->toContain('showNotification');
    });

    it('contains notificationclick event handler', function () {
        $swContent = file_get_contents(public_path('service-worker.js'));

        expect($swContent)->toContain("self.addEventListener('notificationclick'");
        expect($swContent)->toContain('clients.openWindow');
    });

    it('parses JSON push data', function () {
        $swContent = file_get_contents(public_path('service-worker.js'));

        expect($swContent)->toContain('event.data.json()');
    });

    it('handles notification click by opening the action URL', function () {
        $swContent = file_get_contents(public_path('service-worker.js'));

        expect($swContent)->toContain('event.notification.data');
        expect($swContent)->toContain('actionUrl');
    });
});
