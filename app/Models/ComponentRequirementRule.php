<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ComponentRequirementRule extends Model
{
    /** @use HasFactory<\Database\Factories\ComponentRequirementRuleFactory> */
    use HasFactory, LogsActivityTrait;

    /**
     * BR-316: Three rule types.
     */
    public const RULE_TYPE_REQUIRES_ANY_OF = 'requires_any_of';

    public const RULE_TYPE_REQUIRES_ALL_OF = 'requires_all_of';

    public const RULE_TYPE_INCOMPATIBLE_WITH = 'incompatible_with';

    /**
     * Valid rule types.
     *
     * @var array<string>
     */
    public const VALID_RULE_TYPES = [
        self::RULE_TYPE_REQUIRES_ANY_OF,
        self::RULE_TYPE_REQUIRES_ALL_OF,
        self::RULE_TYPE_INCOMPATIBLE_WITH,
    ];

    /**
     * Translatable rule type labels for display.
     *
     * @var array<string, array{en: string, fr: string}>
     */
    public const RULE_TYPE_LABELS = [
        self::RULE_TYPE_REQUIRES_ANY_OF => ['en' => 'Requires any of', 'fr' => 'Requiert au moins un de'],
        self::RULE_TYPE_REQUIRES_ALL_OF => ['en' => 'Requires all of', 'fr' => 'Requiert tous les'],
        self::RULE_TYPE_INCOMPATIBLE_WITH => ['en' => 'Incompatible with', 'fr' => 'Incompatible avec'],
    ];

    /**
     * The table associated with the model.
     */
    protected $table = 'component_requirement_rules';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'meal_component_id',
        'rule_type',
    ];

    /**
     * Get the component this rule belongs to.
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(MealComponent::class, 'meal_component_id');
    }

    /**
     * Get the target components for this rule.
     */
    public function targetComponents(): BelongsToMany
    {
        return $this->belongsToMany(
            MealComponent::class,
            'component_requirement_rule_targets',
            'rule_id',
            'target_component_id'
        );
    }

    /**
     * Get the localized rule type label for display.
     */
    public function getRuleTypeLabelAttribute(): string
    {
        $locale = app()->getLocale();
        $labels = self::RULE_TYPE_LABELS[$this->rule_type] ?? null;

        if ($labels) {
            return $labels[$locale] ?? $labels['en'];
        }

        return ucfirst(str_replace('_', ' ', $this->rule_type));
    }
}
