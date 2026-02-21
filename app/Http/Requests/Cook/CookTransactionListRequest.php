<?php

namespace App\Http\Requests\Cook;

use App\Models\WalletTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * F-170: Cook Wallet Transaction History
 *
 * Validates query parameters for the cook wallet transaction list page.
 * BR-327: Filter by type allows: All, Order Payments, Commissions, Withdrawals, Auto-Deductions, Clearances.
 * BR-324: Default sort by date descending.
 * BR-325: Paginated with 20 per page.
 */
class CookTransactionListRequest extends FormRequest
{
    /**
     * BR-331: Only users with manage-finances permission can access.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('can-manage-cook-wallet') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', Rule::in(WalletTransaction::TYPES)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
