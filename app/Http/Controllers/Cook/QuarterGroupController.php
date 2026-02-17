<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreQuarterGroupRequest;
use App\Services\DeliveryAreaService;
use Illuminate\Http\Request;

class QuarterGroupController extends Controller
{
    /**
     * Store a new quarter group.
     *
     * F-090: Quarter Group Creation
     * BR-264: Group name is required (plain text, not translatable)
     * BR-265: Group name must be unique within this tenant
     * BR-266: Delivery fee is required and must be >= 0 XAF
     * BR-267: Group fee overrides individual quarter fees
     * BR-268: A quarter can belong to at most one group
     * BR-269: Quarters from any town under this tenant can be assigned
     * BR-270: Groups are tenant-scoped
     * BR-271: Creating a group does not require assigning quarters
     * BR-272: Fee changes apply to all member quarters for new orders
     */
    public function store(Request $request, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'group_name' => ['required', 'string', 'max:100'],
                'group_delivery_fee' => ['required', 'integer', 'min:0'],
                'group_quarter_ids' => ['nullable', 'array'],
                'group_quarter_ids.*' => ['integer'],
            ], [
                'group_name.required' => __('Group name is required.'),
                'group_name.max' => __('Group name must not exceed 100 characters.'),
                'group_delivery_fee.required' => __('Delivery fee is required.'),
                'group_delivery_fee.integer' => __('Delivery fee must be a whole number.'),
                'group_delivery_fee.min' => __('Delivery fee must be 0 or greater.'),
            ]);
        } else {
            $formRequest = app(StoreQuarterGroupRequest::class);
            $validated = $formRequest->validated();
            // Map standard keys for HTTP path
            $validated['group_name'] = $validated['name'];
            $validated['group_delivery_fee'] = $validated['delivery_fee'];
            $validated['group_quarter_ids'] = $validated['quarter_ids'] ?? [];
        }

        $name = trim($validated['group_name']);
        $fee = (int) $validated['group_delivery_fee'];
        $quarterIds = array_map('intval', $validated['group_quarter_ids'] ?? []);

        // Use DeliveryAreaService for business logic
        $result = $deliveryAreaService->createQuarterGroup($tenant, $name, $fee, $quarterIds);

        if (! $result['success']) {
            // BR-265: Uniqueness violation
            if ($request->isGale()) {
                return gale()->messages([
                    'group_name' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['name' => $result['error']])->withInput();
        }

        // Activity logging
        activity('delivery_areas')
            ->causedBy($user)
            ->withProperties([
                'action' => 'quarter_group_created',
                'group_name' => $name,
                'delivery_fee' => $fee,
                'quarter_count' => count($quarterIds),
                'tenant_id' => $tenant->id,
            ])
            ->log('Quarter group created');

        // Build success message
        $successMessage = __('Quarter group created successfully.');

        // Gale redirect with toast
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/locations'))
                ->with('success', $successMessage);
        }

        return redirect()->route('cook.locations.index')
            ->with('success', $successMessage);
    }
}
