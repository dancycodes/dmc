<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCommissionRequest;
use App\Models\CommissionChange;
use App\Models\Tenant;
use App\Services\CommissionService;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function __construct(private CommissionService $commissionService) {}

    /**
     * Show the commission configuration page for a tenant.
     *
     * F-062: Commission Configuration per Cook
     * Scenario 1: Viewing current commission rate
     * Scenario 3: Viewing commission history
     */
    public function show(Request $request, Tenant $tenant): mixed
    {
        if (! $request->user()?->can('can-manage-commission')) {
            abort(403);
        }

        $currentRate = $tenant->getCommissionRate();
        $isDefault = ! $tenant->hasCustomCommissionRate();
        $history = $this->commissionService->getHistory($tenant);

        $data = [
            'tenant' => $tenant,
            'currentRate' => $currentRate,
            'isDefault' => $isDefault,
            'defaultRate' => CommissionChange::DEFAULT_RATE,
            'minRate' => CommissionChange::MIN_RATE,
            'maxRate' => CommissionChange::MAX_RATE,
            'rateStep' => CommissionChange::RATE_STEP,
            'history' => $history,
        ];

        // Handle Gale navigate for history pagination
        if ($request->isGaleNavigate('commission-history')) {
            return gale()->fragment('admin.tenants.commission', 'commission-history-content', $data);
        }

        return gale()->view('admin.tenants.commission', $data, web: true);
    }

    /**
     * Update the commission rate for a tenant.
     *
     * F-062: Commission Configuration per Cook
     * Scenario 2: Setting a custom commission rate
     * BR-176: Commission range 0%-50% in 0.5% increments
     * BR-179: Changes recorded with rate, admin, timestamp, reason
     */
    public function update(Request $request, Tenant $tenant): mixed
    {
        if (! $request->user()?->can('can-manage-commission')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState($this->validationRules());
        } else {
            $formRequest = app(UpdateCommissionRequest::class);
            $validated = $formRequest->validated();
        }

        $newRate = (float) $validated['commission_rate'];
        $reason = $validated['reason'] ?? null;
        $currentRate = $tenant->getCommissionRate();

        // Skip if rate hasn't changed
        if ($newRate === $currentRate) {
            session()->flash('toast', [
                'type' => 'info',
                'message' => __('Commission rate is already set to :rate%.', ['rate' => $newRate]),
            ]);

            if ($request->isGale()) {
                return gale()->redirect(url('/vault-entry/tenants/'.$tenant->slug.'/commission'));
            }

            return redirect('/vault-entry/tenants/'.$tenant->slug.'/commission');
        }

        $result = $this->commissionService->updateRate($tenant, $newRate, $request->user(), $reason);

        $toastMessage = __('Commission rate updated from :old% to :new%.', [
            'old' => $currentRate,
            'new' => $newRate,
        ]);

        // BR-182: Warn about Flutterwave subaccount update
        if ($result['flutterwave_warning']) {
            $toastMessage .= ' '.__('Please verify the Flutterwave subaccount split percentage.');
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => $toastMessage,
        ]);

        if ($request->isGale()) {
            return gale()->redirect(url('/vault-entry/tenants/'.$tenant->slug.'/commission'));
        }

        return redirect('/vault-entry/tenants/'.$tenant->slug.'/commission');
    }

    /**
     * Reset the commission rate to the platform default.
     *
     * F-062: Commission Configuration per Cook
     * Scenario 4: Resetting to default
     * BR-180: Reset to default sets rate back to platform default (10%)
     */
    public function resetToDefault(Request $request, Tenant $tenant): mixed
    {
        if (! $request->user()?->can('can-manage-commission')) {
            abort(403);
        }

        $currentRate = $tenant->getCommissionRate();

        // Skip if already at default
        if (! $tenant->hasCustomCommissionRate()) {
            session()->flash('toast', [
                'type' => 'info',
                'message' => __('Commission rate is already at the default (:rate%).', ['rate' => CommissionChange::DEFAULT_RATE]),
            ]);

            if ($request->isGale()) {
                return gale()->redirect(url('/vault-entry/tenants/'.$tenant->slug.'/commission'));
            }

            return redirect('/vault-entry/tenants/'.$tenant->slug.'/commission');
        }

        $result = $this->commissionService->resetToDefault($tenant, $request->user());

        $toastMessage = __('Commission rate reset to default (:rate%).', ['rate' => CommissionChange::DEFAULT_RATE]);

        if ($result['flutterwave_warning']) {
            $toastMessage .= ' '.__('Please verify the Flutterwave subaccount split percentage.');
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => $toastMessage,
        ]);

        if ($request->isGale()) {
            return gale()->redirect(url('/vault-entry/tenants/'.$tenant->slug.'/commission'));
        }

        return redirect('/vault-entry/tenants/'.$tenant->slug.'/commission');
    }

    /**
     * Get the validation rules for commission rate update.
     *
     * @return array<string, array<int, mixed>>
     */
    private function validationRules(): array
    {
        return [
            'commission_rate' => [
                'required',
                'numeric',
                'min:'.CommissionChange::MIN_RATE,
                'max:'.CommissionChange::MAX_RATE,
                function (string $attribute, mixed $value, \Closure $fail) {
                    $remainder = fmod((float) $value * 2, 1.0);
                    if (abs($remainder) > 0.001) {
                        $fail(__('Commission rate must be in 0.5% increments.'));
                    }
                },
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
