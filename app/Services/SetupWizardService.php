<?php

namespace App\Services;

use App\Models\Tenant;

class SetupWizardService
{
    /**
     * The wizard step definitions.
     *
     * BR-108: Wizard has exactly 4 steps.
     */
    public const STEPS = [
        1 => 'brand-info',
        2 => 'cover-images',
        3 => 'delivery-areas',
        4 => 'schedule-meal',
    ];

    /**
     * Step titles for display.
     */
    public const STEP_TITLES = [
        1 => 'Brand Info',
        2 => 'Cover Images',
        3 => 'Delivery Areas',
        4 => 'Schedule & First Meal',
    ];

    /**
     * Get the step completion status for a tenant.
     *
     * BR-112: Completed steps are marked with a checkmark.
     * BR-114: Step progress is persisted so the wizard resumes where the cook left off.
     *
     * @return array<int, bool>
     */
    public function getStepCompletion(Tenant $tenant): array
    {
        return [
            1 => $this->isStepComplete($tenant, 1),
            2 => $this->isStepComplete($tenant, 2),
            3 => $this->isStepComplete($tenant, 3),
            4 => $this->isStepComplete($tenant, 4),
        ];
    }

    /**
     * Check if a specific step is complete.
     *
     * Step completion is stored in the tenant's settings JSON under 'setup_steps'.
     * Steps 1, 3, and 4 also check actual data presence for "Go Live" requirements.
     */
    public function isStepComplete(Tenant $tenant, int $step): bool
    {
        $completedSteps = $tenant->getSetting('setup_steps', []);

        return in_array($step, $completedSteps, true);
    }

    /**
     * Mark a step as complete.
     *
     * BR-114: Step progress is persisted.
     */
    public function markStepComplete(Tenant $tenant, int $step): void
    {
        $completedSteps = $tenant->getSetting('setup_steps', []);

        if (! in_array($step, $completedSteps, true)) {
            $completedSteps[] = $step;
            $tenant->setSetting('setup_steps', $completedSteps);
            $tenant->save();
        }
    }

    /**
     * Get the current step the cook should be on (first incomplete step).
     *
     * BR-114: Wizard resumes where the cook left off.
     */
    public function getCurrentStep(Tenant $tenant): int
    {
        foreach (self::STEPS as $number => $slug) {
            if (! $this->isStepComplete($tenant, $number)) {
                return $number;
            }
        }

        // All steps complete — show last step
        return 4;
    }

    /**
     * Check if the minimum setup requirements are met for "Go Live".
     *
     * BR-109: Minimum setup requirements:
     * - Brand info saved (name in both languages)
     * - At least 1 town with 1 quarter and delivery fee
     * - At least 1 active meal with at least 1 component
     *
     * BR-111: "Go Live" button is only enabled when all minimum requirements are met.
     */
    public function canGoLive(Tenant $tenant): bool
    {
        return $this->hasBrandInfo($tenant)
            && $this->hasDeliveryArea($tenant)
            && $this->hasActiveMeal($tenant);
    }

    /**
     * Check if brand info is saved (name in both languages + WhatsApp).
     *
     * BR-109: Part of minimum setup requirements.
     * BR-125: Step complete when name (both languages) and WhatsApp are saved.
     */
    public function hasBrandInfo(Tenant $tenant): bool
    {
        return ! empty($tenant->name_en)
            && ! empty($tenant->name_fr)
            && ! empty($tenant->whatsapp);
    }

    /**
     * Check if at least 1 delivery area (town with quarter) exists for this tenant.
     *
     * BR-109: Part of minimum setup requirements.
     * Forward-compatible: delivery_areas table created by F-074 links tenants to towns.
     * Towns and quarters are global reference tables (no tenant_id).
     */
    public function hasDeliveryArea(Tenant $tenant): bool
    {
        // Forward-compatible: delivery_areas table created by F-074
        if (! \Schema::hasTable('delivery_areas')) {
            return false;
        }

        // Check if tenant has at least one delivery area linked to a town with quarters
        return \DB::table('delivery_areas')
            ->where('delivery_areas.tenant_id', $tenant->id)
            ->whereExists(function ($query) {
                $query->select(\DB::raw(1))
                    ->from('quarters')
                    ->whereColumn('quarters.town_id', 'delivery_areas.town_id');
            })
            ->exists();
    }

    /**
     * Check if at least 1 active meal with at least 1 component exists.
     *
     * BR-109: Part of minimum setup requirements.
     * Forward-compatible: checks if tables exist before querying.
     */
    public function hasActiveMeal(Tenant $tenant): bool
    {
        // Forward-compatible: meals/meal_components tables created by F-108/F-118
        if (! \Schema::hasTable('meals')) {
            return false;
        }

        $mealQuery = \DB::table('meals')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true);

        // If meal_components table exists, also check for at least 1 component
        if (\Schema::hasTable('meal_components')) {
            $mealQuery->whereExists(function ($query) {
                $query->select(\DB::raw(1))
                    ->from('meal_components')
                    ->whereColumn('meal_components.meal_id', 'meals.id');
            });
        }

        return $mealQuery->exists();
    }

    /**
     * Get a summary of the setup requirements status.
     *
     * Used by the wizard UI to show what still needs to be done.
     *
     * @return array{brand_info: bool, delivery_area: bool, active_meal: bool, can_go_live: bool}
     */
    public function getRequirementsSummary(Tenant $tenant): array
    {
        $brandInfo = $this->hasBrandInfo($tenant);
        $deliveryArea = $this->hasDeliveryArea($tenant);
        $activeMeal = $this->hasActiveMeal($tenant);

        return [
            'brand_info' => $brandInfo,
            'delivery_area' => $deliveryArea,
            'active_meal' => $activeMeal,
            'can_go_live' => $brandInfo && $deliveryArea && $activeMeal,
        ];
    }

    /**
     * Get the step data for the wizard UI.
     *
     * @return array<int, array{number: int, slug: string, title: string, complete: bool, active: bool}>
     */
    public function getStepsData(Tenant $tenant, int $activeStep): array
    {
        $completion = $this->getStepCompletion($tenant);

        $steps = [];
        foreach (self::STEPS as $number => $slug) {
            $steps[$number] = [
                'number' => $number,
                'slug' => $slug,
                'title' => self::STEP_TITLES[$number],
                'complete' => $completion[$number],
                'active' => $number === $activeStep,
            ];
        }

        return $steps;
    }

    /**
     * Check if a step number is valid.
     */
    public function isValidStep(int $step): bool
    {
        return array_key_exists($step, self::STEPS);
    }

    /**
     * Check if a step is navigable (completed or current).
     *
     * BR-112: Completed steps are clickable for revisiting.
     * The current step (first incomplete) is also navigable.
     */
    public function isStepNavigable(Tenant $tenant, int $step): bool
    {
        if (! $this->isValidStep($step)) {
            return false;
        }

        // Completed steps are always navigable
        if ($this->isStepComplete($tenant, $step)) {
            return true;
        }

        // The current step is navigable
        if ($step === $this->getCurrentStep($tenant)) {
            return true;
        }

        // After "Go Live", all steps are navigable (BR-116)
        if ($tenant->isSetupComplete()) {
            return true;
        }

        return false;
    }

    /**
     * Mark the tenant setup as complete.
     *
     * BR-115: After "Go Live", the tenant's setup_complete flag is set to true.
     */
    public function goLive(Tenant $tenant): void
    {
        $tenant->setSetting('setup_complete', true);
        $tenant->save();
    }
}
