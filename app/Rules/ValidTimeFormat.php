<?php

namespace App\Rules;

use App\Services\ScheduleValidationService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * F-107: Schedule Validation Rules
 *
 * BR-172: Time format must be 24-hour (HH:MM), values from 00:00 to 23:59.
 *
 * Custom validation rule that ensures time strings follow the 24-hour format
 * and contain valid hour (0-23) and minute (0-59) values. Rejects values like
 * "25:00", "12:60", or malformed strings.
 */
class ValidTimeFormat implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('Invalid time format. Use 24-hour format (00:00 - 23:59).'));

            return;
        }

        $validationService = app(ScheduleValidationService::class);

        if (! $validationService->isValidTimeFormat($value)) {
            $fail(__('Invalid time format. Use 24-hour format (00:00 - 23:59).'));
        }
    }
}
