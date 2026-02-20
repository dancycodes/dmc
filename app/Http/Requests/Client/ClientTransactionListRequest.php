<?php

namespace App\Http\Requests\Client;

use App\Services\ClientTransactionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * F-164: Client Transaction History
 *
 * Validates query parameters for the transaction list page.
 * BR-267: Filter by type (All, Payments, Refunds, Wallet Payments).
 * BR-262: Sort direction (asc/desc, default desc).
 */
class ClientTransactionListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * BR-269: Authentication required.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', Rule::in(ClientTransactionService::FILTER_TYPES)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
