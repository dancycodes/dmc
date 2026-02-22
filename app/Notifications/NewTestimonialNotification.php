<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\Testimonial;

/**
 * F-180: New Testimonial Push Notification (N-018)
 *
 * Sent to the cook when a client submits a testimonial on their landing page.
 * BR-434: Cook receives push + DB notification.
 */
class NewTestimonialNotification extends BasePushNotification
{
    public function __construct(
        private Testimonial $testimonial,
        private Tenant $tenant,
    ) {}

    /**
     * Get the push notification title.
     */
    public function getTitle(object $notifiable): string
    {
        return __('New Testimonial Received!');
    }

    /**
     * Get the push notification body text.
     */
    public function getBody(object $notifiable): string
    {
        $excerpt = mb_strimwidth($this->testimonial->text, 0, 80, '...');

        return __('A client shared: ":excerpt"', ['excerpt' => $excerpt]);
    }

    /**
     * Get the URL the notification links to.
     */
    public function getActionUrl(object $notifiable): string
    {
        return $this->tenant->getUrl().'/dashboard/testimonials';
    }

    /**
     * Get additional data payload for the notification.
     *
     * @return array<string, mixed>
     */
    public function getData(object $notifiable): array
    {
        return [
            'testimonial_id' => $this->testimonial->id,
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'type' => 'new_testimonial',
        ];
    }

    /**
     * Get the notification tag for grouping.
     */
    public function getTag(object $notifiable): ?string
    {
        return 'testimonial-'.$this->tenant->id;
    }
}
