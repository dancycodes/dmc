<?php

namespace Database\Factories;

use App\Models\Complaint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Complaint>
 */
class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    /**
     * Realistic complaint descriptions for Cameroonian food context.
     */
    private const DESCRIPTIONS = [
        'food_quality' => [
            'The jollof rice was undercooked and tasteless.',
            'Ndole was too salty and the fish was not fresh.',
            'The plantains were burnt and the sauce was watery.',
            'Eru was not properly cooked, the vegetables were still raw.',
        ],
        'delivery_issue' => [
            'My order was supposed to arrive at 12:30 PM but came at 2:15 PM.',
            'Delivery was over 1 hour late with no communication.',
            'I waited 90 minutes past the estimated delivery time.',
            'The food was cold when it arrived due to late delivery.',
        ],
        'missing_item' => [
            'I ordered 3 portions but only received 2.',
            'The drink I ordered was not included in the delivery.',
            'Side dishes were missing from my order.',
        ],
        'late_delivery' => [
            'My order was supposed to arrive at 12:30 PM but came at 2:15 PM.',
            'Delivery was over 1 hour late with no communication.',
            'I waited 90 minutes past the estimated delivery time.',
        ],
        'missing_items' => [
            'I ordered 3 portions but only received 2.',
            'The drink I ordered was not included in the delivery.',
            'Side dishes were missing from my order.',
        ],
        'wrong_order' => [
            'I ordered poulet DG but received grilled fish instead.',
            'The wrong meal was delivered to my address.',
            'I received someone else\'s order entirely.',
        ],
        'rude_behavior' => [
            'The delivery person was very rude and aggressive.',
            'The cook was dismissive when I called about my order.',
            'I was treated disrespectfully when I asked about the delay.',
        ],
        'other' => [
            'The packaging was damaged and food spilled everywhere.',
            'I was charged twice for the same order.',
            'The portion size was significantly smaller than advertised.',
        ],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = $this->faker->randomElement(Complaint::CATEGORIES);
        $descriptions = self::DESCRIPTIONS[$category] ?? self::DESCRIPTIONS['other'];
        $submittedAt = $this->faker->dateTimeBetween('-30 days', '-1 day');

        return [
            'client_id' => User::factory(),
            'cook_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'order_id' => $this->faker->optional(0.8)->numberBetween(1000, 9999),
            'category' => $category,
            'description' => $this->faker->randomElement($descriptions),
            'status' => 'open',
            'is_escalated' => false,
            'escalation_reason' => null,
            'escalated_at' => null,
            'escalated_by' => null,
            'submitted_at' => $submittedAt,
        ];
    }

    /**
     * State: F-183 client-facing complaint with valid client categories.
     */
    public function clientSubmitted(): static
    {
        return $this->state(function (array $attributes) {
            $category = $this->faker->randomElement(Complaint::CLIENT_CATEGORIES);
            $descriptions = self::DESCRIPTIONS[$category] ?? self::DESCRIPTIONS['other'];

            return [
                'category' => $category,
                'description' => $this->faker->randomElement($descriptions),
                'status' => 'open',
                'is_escalated' => false,
                'submitted_at' => now(),
            ];
        });
    }

    /**
     * State: with photo path.
     */
    public function withPhoto(): static
    {
        return $this->state(fn () => [
            'photo_path' => 'complaints/tenant-1/complaint-'.$this->faker->uuid().'.jpg',
        ]);
    }

    /**
     * State: complaint has been escalated to admin queue.
     */
    public function escalated(): static
    {
        return $this->state(function (array $attributes) {
            $submittedAt = $attributes['submitted_at'] ?? now()->subDays(3);
            $escalatedAt = $this->faker->dateTimeBetween($submittedAt, 'now');

            return [
                'is_escalated' => true,
                'status' => 'pending_resolution',
                'escalation_reason' => $this->faker->randomElement([
                    Complaint::ESCALATION_AUTO_24H,
                    Complaint::ESCALATION_MANUAL_CLIENT,
                    Complaint::ESCALATION_MANUAL_COOK,
                ]),
                'escalated_at' => $escalatedAt,
            ];
        });
    }

    /**
     * State: auto-escalated after 24h no response.
     */
    public function autoEscalated(): static
    {
        return $this->state(function (array $attributes) {
            $submittedAt = $attributes['submitted_at'] ?? now()->subDays(3);

            return [
                'is_escalated' => true,
                'status' => 'pending_resolution',
                'escalation_reason' => Complaint::ESCALATION_AUTO_24H,
                'escalated_at' => now()->subHours($this->faker->numberBetween(1, 72)),
            ];
        });
    }

    /**
     * State: manually escalated by client.
     */
    public function manuallyEscalatedByClient(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_escalated' => true,
            'status' => 'pending_resolution',
            'escalation_reason' => Complaint::ESCALATION_MANUAL_CLIENT,
            'escalated_at' => now()->subHours($this->faker->numberBetween(1, 48)),
            'escalated_by' => $attributes['client_id'],
        ]);
    }

    /**
     * State: under review by admin.
     */
    public function underReview(): static
    {
        return $this->escalated()->state(fn () => [
            'status' => 'under_review',
        ]);
    }

    /**
     * State: resolved.
     */
    public function resolved(): static
    {
        return $this->escalated()->state(fn () => [
            'status' => 'resolved',
            'resolved_at' => now()->subHours($this->faker->numberBetween(1, 24)),
            'resolution_notes' => $this->faker->sentence(),
            'resolution_type' => $this->faker->randomElement(['partial_refund', 'full_refund', 'warning']),
        ]);
    }

    /**
     * State: dismissed.
     */
    public function dismissed(): static
    {
        return $this->escalated()->state(fn () => [
            'status' => 'dismissed',
            'resolved_at' => now()->subHours($this->faker->numberBetween(1, 24)),
            'resolution_notes' => $this->faker->sentence(),
            'resolution_type' => 'dismiss',
        ]);
    }

    /**
     * State: resolved with partial refund.
     */
    public function resolvedWithPartialRefund(float $amount = 3000): static
    {
        return $this->resolved()->state(fn () => [
            'resolution_type' => 'partial_refund',
            'refund_amount' => $amount,
        ]);
    }

    /**
     * State: resolved with warning.
     */
    public function resolvedWithWarning(): static
    {
        return $this->resolved()->state(fn () => [
            'resolution_type' => 'warning',
            'refund_amount' => null,
        ]);
    }

    /**
     * State: resolved with cook suspension.
     */
    public function resolvedWithSuspension(int $days = 7): static
    {
        return $this->resolved()->state(fn () => [
            'resolution_type' => 'suspend',
            'suspension_days' => $days,
            'suspension_ends_at' => now()->addDays($days),
        ]);
    }

    /**
     * State: overdue (escalated more than 48h ago, still unresolved).
     */
    public function overdue(): static
    {
        return $this->escalated()->state(fn () => [
            'escalated_at' => now()->subDays($this->faker->numberBetween(3, 7)),
        ]);
    }

    /**
     * State: with a specific category.
     */
    public function withCategory(string $category): static
    {
        $descriptions = self::DESCRIPTIONS[$category] ?? self::DESCRIPTIONS['other'];

        return $this->state(fn () => [
            'category' => $category,
            'description' => $this->faker->randomElement($descriptions),
        ]);
    }

    /**
     * State: cook has responded but complaint still escalated.
     */
    public function withCookResponse(): static
    {
        return $this->state(fn () => [
            'cook_response' => $this->faker->sentence(),
            'cook_responded_at' => now()->subHours($this->faker->numberBetween(1, 48)),
        ]);
    }
}
