<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cook\StoreQuarterRequest;
use App\Http\Requests\Cook\UpdateQuarterRequest;
use App\Services\DeliveryAreaService;
use Illuminate\Http\Request;

class QuarterController extends Controller
{
    /**
     * Store a new quarter in a cook's delivery area (town).
     *
     * F-086: Add Quarter
     * BR-232: Quarter name required in both EN and FR
     * BR-233: Quarter name must be unique within its parent town
     * BR-234: Delivery fee is required and must be >= 0 XAF
     * BR-235: Delivery fee is stored as an integer in XAF
     * BR-236: A fee of 0 means free delivery to that quarter
     * BR-237: Quarter can optionally be assigned to a quarter group
     * BR-238: When assigned to a group, the group's delivery fee overrides the individual fee
     * BR-239: Quarter is scoped to its parent town (town_id foreign key)
     * BR-240: Save via Gale; quarter appears in list without page reload
     * BR-241: Only users with location management permission can add quarters
     */
    public function store(Request $request, int $deliveryArea, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-241: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern
        if ($request->isGale()) {
            $validated = $request->validateState([
                'quarter_name_en' => ['required', 'string', 'max:255'],
                'quarter_name_fr' => ['required', 'string', 'max:255'],
                'quarter_delivery_fee' => ['required', 'integer', 'min:0'],
            ], [
                'quarter_name_en.required' => __('Quarter name is required in English.'),
                'quarter_name_en.max' => __('Quarter name must not exceed 255 characters.'),
                'quarter_name_fr.required' => __('Quarter name is required in French.'),
                'quarter_name_fr.max' => __('Quarter name must not exceed 255 characters.'),
                'quarter_delivery_fee.required' => __('Delivery fee is required.'),
                'quarter_delivery_fee.integer' => __('Delivery fee must be a whole number.'),
                'quarter_delivery_fee.min' => __('Delivery fee must be 0 or greater.'),
            ]);
        } else {
            $formRequest = app(StoreQuarterRequest::class);
            $validated = $formRequest->validated();
            // Map standard keys for HTTP path
            $validated['quarter_name_en'] = $validated['name_en'];
            $validated['quarter_name_fr'] = $validated['name_fr'];
            $validated['quarter_delivery_fee'] = $validated['delivery_fee'];
        }

        // BR-232: Trim whitespace before storage and uniqueness check
        $nameEn = trim($validated['quarter_name_en']);
        $nameFr = trim($validated['quarter_name_fr']);
        $fee = (int) $validated['quarter_delivery_fee'];

        // Use DeliveryAreaService for business logic
        $result = $deliveryAreaService->addQuarter($tenant, $deliveryArea, $nameEn, $nameFr, $fee);

        if (! $result['success']) {
            // BR-233: Uniqueness violation
            if ($request->isGale()) {
                return gale()->messages([
                    'quarter_name_en' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['name_en' => $result['error']])->withInput();
        }

        // Activity logging
        activity('delivery_areas')
            ->causedBy($user)
            ->withProperties([
                'action' => 'quarter_added',
                'quarter_name_en' => $nameEn,
                'quarter_name_fr' => $nameFr,
                'delivery_fee' => $fee,
                'delivery_area_id' => $deliveryArea,
                'tenant_id' => $tenant->id,
            ])
            ->log('Quarter added to delivery area');

        // Build success message
        $successMessage = __('Quarter added successfully.');

        // Include high fee warning if applicable
        if (! empty($result['warning'])) {
            $successMessage .= ' '.$result['warning'];
        }

        // BR-240: Gale redirect with toast (quarter appears in list without page reload)
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/locations'))
                ->with('success', $successMessage);
        }

        return redirect()->route('cook.locations.index')
            ->with('success', $successMessage);
    }

    /**
     * Update an existing quarter's name and delivery fee.
     *
     * F-088: Edit Quarter
     * BR-250: Quarter name required in both EN and FR
     * BR-251: Quarter name must remain unique within parent town (excluding current quarter)
     * BR-252: Delivery fee required; must be >= 0 XAF
     * BR-255: Fee changes apply to new orders only
     * BR-256: Save via Gale; list updates without page reload
     * BR-257: Edit action requires location management permission
     */
    public function update(Request $request, int $deliveryAreaQuarter, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-257: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        // Dual Gale/HTTP validation pattern (F-084 edit pattern with quarter_ prefix)
        if ($request->isGale()) {
            $validated = $request->validateState([
                'edit_quarter_name_en' => ['required', 'string', 'max:255'],
                'edit_quarter_name_fr' => ['required', 'string', 'max:255'],
                'edit_quarter_delivery_fee' => ['required', 'integer', 'min:0'],
            ], [
                'edit_quarter_name_en.required' => __('Quarter name is required in English.'),
                'edit_quarter_name_en.max' => __('Quarter name must not exceed 255 characters.'),
                'edit_quarter_name_fr.required' => __('Quarter name is required in French.'),
                'edit_quarter_name_fr.max' => __('Quarter name must not exceed 255 characters.'),
                'edit_quarter_delivery_fee.required' => __('Delivery fee is required.'),
                'edit_quarter_delivery_fee.integer' => __('Delivery fee must be a whole number.'),
                'edit_quarter_delivery_fee.min' => __('Delivery fee must be 0 or greater.'),
            ]);
        } else {
            $formRequest = app(UpdateQuarterRequest::class);
            $validated = $formRequest->validated();
            // Map standard keys for HTTP path
            $validated['edit_quarter_name_en'] = $validated['name_en'];
            $validated['edit_quarter_name_fr'] = $validated['name_fr'];
            $validated['edit_quarter_delivery_fee'] = $validated['delivery_fee'];
        }

        // BR-250: Trim whitespace before storage and uniqueness check
        $nameEn = trim($validated['edit_quarter_name_en']);
        $nameFr = trim($validated['edit_quarter_name_fr']);
        $fee = (int) $validated['edit_quarter_delivery_fee'];

        // Use DeliveryAreaService for business logic
        $result = $deliveryAreaService->updateQuarter($tenant, $deliveryAreaQuarter, $nameEn, $nameFr, $fee);

        if (! $result['success']) {
            // BR-251: Uniqueness violation
            if ($request->isGale()) {
                return gale()->messages([
                    'edit_quarter_name_en' => $result['error'],
                ]);
            }

            return redirect()->back()->withErrors(['name_en' => $result['error']])->withInput();
        }

        // Activity logging
        activity('delivery_areas')
            ->causedBy($user)
            ->withProperties([
                'action' => 'quarter_updated',
                'quarter_name_en' => $nameEn,
                'quarter_name_fr' => $nameFr,
                'delivery_fee' => $fee,
                'delivery_area_quarter_id' => $deliveryAreaQuarter,
                'tenant_id' => $tenant->id,
            ])
            ->log('Quarter updated in delivery area');

        // Build success message
        $successMessage = __('Quarter updated successfully.');

        // Include high fee warning if applicable
        if (! empty($result['warning'])) {
            $successMessage .= ' '.$result['warning'];
        }

        // BR-256: Gale redirect with toast (list updates without page reload)
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/locations'))
                ->with('success', $successMessage);
        }

        return redirect()->route('cook.locations.index')
            ->with('success', $successMessage);
    }

    /**
     * Delete a quarter from the cook's delivery areas.
     *
     * F-089: Delete Quarter
     * BR-258: Cannot delete a quarter with active (non-completed, non-cancelled) orders
     * BR-259: Deleting a quarter removes it from any quarter group it belongs to
     * BR-260: Confirmation dialog must show the quarter name (handled in blade)
     * BR-261: On success, toast notification: "Quarter deleted successfully"
     * BR-262: Delete action requires location management permission
     * BR-263: Quarter list updates via Gale without page reload
     */
    public function destroy(Request $request, int $deliveryAreaQuarter, DeliveryAreaService $deliveryAreaService): mixed
    {
        $user = $request->user();
        $tenant = tenant();

        // BR-262: Permission check
        if (! $user->can('can-manage-locations')) {
            abort(403);
        }

        $result = $deliveryAreaService->removeQuarter($tenant, $deliveryAreaQuarter);

        if (! $result['success']) {
            // BR-258: Active orders block deletion
            if ($request->isGale()) {
                return gale()
                    ->redirect(url('/dashboard/locations'))
                    ->with('error', $result['error']);
            }

            return redirect()->route('cook.locations.index')
                ->with('error', $result['error']);
        }

        // Activity logging
        activity('delivery_areas')
            ->causedBy($user)
            ->withProperties([
                'action' => 'quarter_deleted',
                'quarter_name' => $result['quarter_name'],
                'tenant_id' => $tenant->id,
                'delivery_area_quarter_id' => $deliveryAreaQuarter,
            ])
            ->log('Quarter deleted from delivery area');

        // BR-261 + BR-263: Toast notification and list update via Gale
        if ($request->isGale()) {
            return gale()
                ->redirect(url('/dashboard/locations'))
                ->with('success', __('Quarter deleted successfully.'));
        }

        return redirect()->route('cook.locations.index')
            ->with('success', __('Quarter deleted successfully.'));
    }
}
