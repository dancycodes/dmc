<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\UpdateDeliveryFeeRequest;
use App\Http\Requests\Cook\UpdateGroupFeeRequest;
use App\Services\DeliveryAreaService;
use Illuminate\Http\Request;

class DeliveryFeeController extends Controller
{
    /**
     * Display the delivery fee configuration view.
     *
     * F-091: Delivery Fee Configuration
     * BR-278: Fee configuration is accessible from the Locations section of the dashboard
     * BR-280: Only users with location management permission can modify fees
     */
    public function index(Request $request, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-280: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        $feeSummary = $deliveryAreaService->getDeliveryFeeSummary($tenant);

        return gale()->view('cook.locations.delivery-fees', [
            'areas' => $feeSummary['areas'],
            'groups' => $feeSummary['groups'],
            'summary' => $feeSummary['summary'],
        ], web: true);
    }

    /**
     * Update an individual quarter's delivery fee.
     *
     * F-091: Delivery Fee Configuration
     * BR-273: Delivery fee must be >= 0 XAF
     * BR-274: Fee of 0 XAF means free delivery
     * BR-276: Fee changes apply to new orders only
     * BR-277: Fees are stored as integers in XAF
     * BR-279: Changes saved via Gale without page reload
     * BR-280: Only users with location management permission can modify fees
     */
    public function updateQuarterFee(Request $request, int $deliveryAreaQuarter, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-280: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'fee_value' => ['required', 'integer', 'min:0'],
            ], [
                'fee_value.required' => __('Delivery fee is required.'),
                'fee_value.integer' => __('Delivery fee must be a whole number.'),
                'fee_value.min' => __('Delivery fee must be 0 or greater.'),
            ]);
        } else {
            $formRequest = app(UpdateDeliveryFeeRequest::class);
            $validated = $formRequest->validated();
            $validated['fee_value'] = $validated['delivery_fee'];
        }

        $fee = (int) $validated['fee_value'];

        $result = $deliveryAreaService->updateQuarterFee($tenant, $deliveryAreaQuarter, $fee);

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'fee_value' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['delivery_fee' => $result['error']])->withInput();
        }

        // Activity logging
        activity('delivery_areas')
            ->causedBy($user)
            ->withProperties([
                'action' => 'quarter_fee_updated',
                'delivery_area_quarter_id' => $deliveryAreaQuarter,
                'new_fee' => $fee,
                'tenant_id' => $tenant->id,
            ])
            ->log('Quarter delivery fee updated');

        $successMessage = __('Delivery fee updated successfully.');

        if (! empty($result['warning'])) {
            $successMessage .= ' '.$result['warning'];
        }

        // BR-279: Gale redirect with toast
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/locations/delivery-fees'))
                ->with('success', $successMessage);
        }

        return redirect()->route('cook.locations.delivery-fees')
            ->with('success', $successMessage);
    }

    /**
     * Update a quarter group's delivery fee.
     *
     * F-091: Delivery Fee Configuration
     * BR-273: Delivery fee must be >= 0 XAF
     * BR-275: Group fee overrides individual quarter fees for all quarters in the group
     * BR-276: Fee changes apply to new orders only
     * BR-277: Fees are stored as integers in XAF
     * BR-279: Changes saved via Gale without page reload
     * BR-280: Only users with location management permission can modify fees
     */
    public function updateGroupFee(Request $request, int $group, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-280: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'group_fee_value' => ['required', 'integer', 'min:0'],
            ], [
                'group_fee_value.required' => __('Delivery fee is required.'),
                'group_fee_value.integer' => __('Delivery fee must be a whole number.'),
                'group_fee_value.min' => __('Delivery fee must be 0 or greater.'),
            ]);
        } else {
            $formRequest = app(UpdateGroupFeeRequest::class);
            $validated = $formRequest->validated();
            $validated['group_fee_value'] = $validated['delivery_fee'];
        }

        $fee = (int) $validated['group_fee_value'];

        $result = $deliveryAreaService->updateGroupFee($tenant, $group, $fee);

        if (! $result['success']) {
            if ($request->isGale()) {
                return gale()->messages([
                    'group_fee_value' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['delivery_fee' => $result['error']])->withInput();
        }

        // Activity logging
        activity('delivery_areas')
            ->causedBy($user)
            ->withProperties([
                'action' => 'group_fee_updated',
                'group_id' => $group,
                'new_fee' => $fee,
                'tenant_id' => $tenant->id,
            ])
            ->log('Quarter group delivery fee updated');

        $successMessage = __('Group fee updated successfully. All member quarters now use this fee.');

        if (! empty($result['warning'])) {
            $successMessage .= ' '.$result['warning'];
        }

        // BR-279: Gale redirect with toast
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/locations/delivery-fees'))
                ->with('success', $successMessage);
        }

        return redirect()->route('cook.locations.delivery-fees')
            ->with('success', $successMessage);
    }
}
