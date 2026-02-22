<?php

namespace App\Services;

use App\Models\User;

class PushNotificationService
{
    /**
     * The localStorage key for tracking when the push prompt was last dismissed.
     */
    public const DISMISS_STORAGE_KEY = 'dmc-push-dismissed-at';

    /**
     * The localStorage key for tracking whether push permission was granted.
     */
    public const GRANTED_STORAGE_KEY = 'dmc-push-granted';

    /**
     * Delay in milliseconds before showing the push permission prompt
     * after a meaningful interaction.
     */
    public const PROMPT_DELAY_MS = 2000;

    /**
     * Number of days before re-prompting a user who dismissed the prompt.
     */
    public const RE_PROMPT_DAYS = 7;

    /**
     * Get the VAPID public key for frontend subscription.
     */
    public function getVapidPublicKey(): ?string
    {
        return config('webpush.vapid.public_key');
    }

    /**
     * Check if VAPID keys are configured.
     */
    public function isConfigured(): bool
    {
        $publicKey = config('webpush.vapid.public_key');
        $privateKey = config('webpush.vapid.private_key');

        return ! empty($publicKey) && ! empty($privateKey);
    }

    /**
     * Store or update a push subscription for the given user.
     *
     * @param  array{endpoint: string, keys: array{p256dh: string, auth: string}, contentEncoding?: string}  $subscriptionData
     */
    public function storeSubscription(User $user, array $subscriptionData): void
    {
        $user->updatePushSubscription(
            $subscriptionData['endpoint'],
            $subscriptionData['keys']['p256dh'] ?? null,
            $subscriptionData['keys']['auth'] ?? null,
            $subscriptionData['contentEncoding'] ?? 'aesgcm'
        );
    }

    /**
     * Remove a push subscription for the given user.
     */
    public function deleteSubscription(User $user, string $endpoint): void
    {
        $user->deletePushSubscription($endpoint);
    }

    /**
     * Check if a user has any active push subscriptions.
     */
    public function hasSubscriptions(User $user): bool
    {
        return $user->pushSubscriptions()->exists();
    }

    /**
     * Get the count of active push subscriptions for a user.
     */
    public function subscriptionCount(User $user): int
    {
        return $user->pushSubscriptions()->count();
    }

    /**
     * Get the Alpine.js data object for the push notification prompt component.
     * Handles permission checking, subscription creation, prompt dismissal,
     * and re-prompt timing.
     */
    public function getPromptAlpineData(): string
    {
        $dismissKey = self::DISMISS_STORAGE_KEY;
        $grantedKey = self::GRANTED_STORAGE_KEY;
        $delayMs = self::PROMPT_DELAY_MS;
        $rePromptDays = self::RE_PROMPT_DAYS;
        $vapidPublicKey = $this->getVapidPublicKey();

        return <<<JS
{
    showPrompt: false,
    permissionGranted: false,
    permissionDenied: false,
    subscribing: false,
    supported: false,

    init() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            return;
        }
        this.supported = true;

        var currentPermission = Notification.permission;
        if (currentPermission === 'granted') {
            this.permissionGranted = true;
            try { localStorage.setItem('{$grantedKey}', 'true'); } catch(e) {}
            this.ensureSubscription();
            return;
        }
        if (currentPermission === 'denied') {
            this.permissionDenied = true;
            return;
        }

        try {
            if (localStorage.getItem('{$grantedKey}') === 'true') {
                return;
            }
            var dismissedAt = localStorage.getItem('{$dismissKey}');
            if (dismissedAt) {
                var dismissDate = new Date(parseInt(dismissedAt, 10));
                var daysSince = (Date.now() - dismissDate.getTime()) / (1000 * 60 * 60 * 24);
                if (daysSince < {$rePromptDays}) {
                    return;
                }
            }
        } catch(e) {}

        setTimeout(() => {
            if (Notification.permission === 'default') {
                this.showPrompt = true;
            }
        }, {$delayMs});
    },

    async requestPermission() {
        this.subscribing = true;
        try {
            var permission = await Notification.requestPermission();
            if (permission === 'granted') {
                this.permissionGranted = true;
                try { localStorage.setItem('{$grantedKey}', 'true'); } catch(e) {}
                await this.subscribe();
            } else if (permission === 'denied') {
                this.permissionDenied = true;
            }
        } catch(e) {
            console.warn('Push permission request failed:', e);
        }
        this.subscribing = false;
        this.showPrompt = false;
    },

    async subscribe() {
        try {
            var registration = await navigator.serviceWorker.ready;
            var existing = await registration.pushManager.getSubscription();
            if (existing) {
                await this.sendSubscriptionToServer(existing);
                return;
            }
            var subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array('{$vapidPublicKey}')
            });
            await this.sendSubscriptionToServer(subscription);
        } catch(e) {
            console.warn('Push subscription failed:', e);
        }
    },

    async ensureSubscription() {
        try {
            var registration = await navigator.serviceWorker.ready;
            var existing = await registration.pushManager.getSubscription();
            if (existing) {
                await this.sendSubscriptionToServer(existing);
            } else {
                await this.subscribe();
            }
        } catch(e) {
            console.warn('Ensure subscription failed:', e);
        }
    },

    async sendSubscriptionToServer(subscription) {
        try {
            var key = subscription.getKey('p256dh');
            var auth = subscription.getKey('auth');
            var response = await fetch('/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('[name=csrf-token]')?.content || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                    keys: {
                        p256dh: key ? btoa(String.fromCharCode.apply(null, new Uint8Array(key))) : null,
                        auth: auth ? btoa(String.fromCharCode.apply(null, new Uint8Array(auth))) : null
                    },
                    contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0]
                })
            });
            if (!response.ok) {
                console.warn('Failed to store push subscription:', response.status);
            }
        } catch(e) {
            console.warn('Failed to send subscription to server:', e);
        }
    },

    dismissPrompt() {
        this.showPrompt = false;
        try { localStorage.setItem('{$dismissKey}', Date.now().toString()); } catch(e) {}
    },

    urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/\\-/g, '+').replace(/_/g, '/');
        var rawData = atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
}
JS;
    }
}
