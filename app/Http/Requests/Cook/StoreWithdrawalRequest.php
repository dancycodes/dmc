<?php

namespace App\Http\Requests\Cook;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F-172: Cook Withdrawal Request
 *
 * Validates withdrawal form data for traditional HTTP requests.
 * BR-344: Amount > 0 and <= withdrawable balance (server validates in service).
 * BR-345: Minimum amount from platform settings (server validates in service).
 * BR-349: Cameroon mobile money number format.
 */
class StoreWithdrawalRequest extends FormRequest
{
    /**
     * Cameroon phone regex: 9 digits starting with 6.
     */
    public const CAMEROON_PHONE_REGEX = '/^6\d{8}$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'mobile_money_number' => ['required', 'string', 'regex:'.self::CAMEROON_PHONE_REGEX],
            'mobile_money_provider' => ['required', 'string', 'in:mtn_momo,orange_money'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => __('Please enter an amount to withdraw.'),
            'amount.integer' => __('Withdrawal amount must be a whole number (no decimals).'),
            'amount.min' => __('Amount must be greater than zero.'),
            'mobile_money_number.required' => __('Please enter your mobile money number.'),
            'mobile_money_number.regex' => __('Invalid mobile money number format. Must be 9 digits starting with 6.'),
            'mobile_money_provider.required' => __('Please select a mobile money provider.'),
            'mobile_money_provider.in' => __('Invalid mobile money provider.'),
        ];
    }
}
