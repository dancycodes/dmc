<?php

namespace App\Http\Controllers;

use App\Http\Requests\Address\StoreAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Models\Address;
use App\Models\Quarter;
use App\Models\Town;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
    /**
     * Display the list of user's saved delivery addresses (F-034).
     *
     * BR-128: All addresses displayed, default first.
     * BR-129: Default address visually distinguished.
     * BR-132: "Add Address" button only if < 5 addresses.
     * BR-134: Localized town/quarter names.
     */
    public function index(Request $request): mixed
    {
        $user = Auth::user();
        $addresses = $user->addresses()
            ->with(['town', 'quarter'])
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();

        $canAddMore = $addresses->count() < Address::MAX_ADDRESSES_PER_USER;

        return gale()->view('profile.addresses.index', [
            'user' => $user,
            'tenant' => tenant(),
            'addresses' => $addresses,
            'canAddMore' => $canAddMore,
            'maxAddresses' => Address::MAX_ADDRESSES_PER_USER,
        ], web: true);
    }

    /**
     * Set an address as the user's default delivery address (F-034).
     *
     * BR-130: Only one address can be default at a time.
     * BR-131: Setting a new default removes previous default.
     */
    public function setDefault(Request $request, Address $address): mixed
    {
        $user = Auth::user();

        // Ensure address belongs to authenticated user
        if ($address->user_id !== $user->id) {
            abort(403);
        }

        // Already default — no change needed
        if ($address->is_default) {
            return gale()->redirect('/profile/addresses')
                ->with('toast', [
                    'type' => 'info',
                    'message' => __('This address is already your default.'),
                ]);
        }

        // BR-131: Remove default from all user addresses
        $user->addresses()->where('is_default', true)->update(['is_default' => false]);

        // Set the new default
        $address->update(['is_default' => true]);

        // Log the change
        activity('addresses')
            ->performedOn($address)
            ->causedBy($user)
            ->event('updated')
            ->withProperties([
                'label' => $address->label,
                'action' => 'set_as_default',
                'ip' => $request->ip(),
            ])
            ->log(__('Default address updated'));

        return gale()->redirect('/profile/addresses')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Default address updated.'),
            ]);
    }

    /**
     * Display the add address form.
     *
     * If the user already has the maximum number of addresses (BR-119),
     * the view shows a limit message instead of the form.
     */
    public function create(Request $request): mixed
    {
        $user = Auth::user();
        $addressCount = $user->addresses()->count();
        $canAddMore = $addressCount < Address::MAX_ADDRESSES_PER_USER;
        $towns = Town::query()->active()->orderBy(localized('name'))->get();

        return gale()->view('profile.addresses.create', [
            'user' => $user,
            'tenant' => tenant(),
            'canAddMore' => $canAddMore,
            'addressCount' => $addressCount,
            'maxAddresses' => Address::MAX_ADDRESSES_PER_USER,
            'towns' => $towns,
        ], web: true);
    }

    /**
     * Store a new delivery address.
     *
     * Handles both Gale SSE requests (from Alpine $action) and traditional
     * HTTP form submissions. Validates required fields, enforces the 5-address
     * limit (BR-119), and auto-sets the first address as default (BR-125).
     *
     * BR-119: Maximum 5 saved addresses per user.
     * BR-120: Label required, unique per user, max 50 chars.
     * BR-121: Town required, must exist in system.
     * BR-122: Quarter required, must belong to selected town.
     * BR-123: Neighbourhood optional, with OpenStreetMap autocomplete.
     * BR-124: Additional directions optional, max 500 chars.
     * BR-125: First address auto-set as default.
     * BR-126: Address is user-scoped, not tenant-scoped.
     * BR-127: All text localized via __().
     */
    public function store(Request $request): mixed
    {
        $user = Auth::user();

        // BR-119: Check maximum address limit before processing
        $addressCount = $user->addresses()->count();
        if ($addressCount >= Address::MAX_ADDRESSES_PER_USER) {
            if ($request->isGale()) {
                return gale()->messages([
                    '_error' => __('You can save up to :max addresses. Please remove one to add a new one.', [
                        'max' => Address::MAX_ADDRESSES_PER_USER,
                    ]),
                ]);
            }

            return redirect()->back()->withErrors([
                'limit' => __('You can save up to :max addresses. Please remove one to add a new one.', [
                    'max' => Address::MAX_ADDRESSES_PER_USER,
                ]),
            ]);
        }

        if ($request->isGale()) {
            $validated = $this->validateGaleState($request);
        } else {
            $formRequest = StoreAddressRequest::createFrom($request);
            $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();
            $validated = $formRequest->validated();
        }

        // BR-125: First address is automatically set as default
        $isDefault = $addressCount === 0;

        $address = $user->addresses()->create([
            'label' => trim($validated['label']),
            'town_id' => $validated['town_id'],
            'quarter_id' => $validated['quarter_id'],
            'neighbourhood' => $validated['neighbourhood'] ?? null,
            'additional_directions' => $validated['additional_directions'] ?? null,
            'is_default' => $isDefault,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]);

        // Log the address creation
        activity('addresses')
            ->performedOn($address)
            ->causedBy($user)
            ->event('created')
            ->withProperties([
                'label' => $address->label,
                'is_default' => $isDefault,
                'ip' => $request->ip(),
            ])
            ->log(__('Delivery address added'));

        return gale()->redirect('/profile/addresses')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Address saved successfully.'),
            ]);
    }

    /**
     * Display the edit form for an existing delivery address (F-035).
     *
     * BR-136: Form must be pre-populated with current address values.
     * BR-138: Quarter dropdown populated for the address's current town.
     * BR-139: Users can only edit their own addresses.
     */
    public function edit(Request $request, Address $address): mixed
    {
        $user = Auth::user();

        // BR-139: Ensure address belongs to authenticated user
        if ($address->user_id !== $user->id) {
            abort(403);
        }

        $towns = Town::query()->active()->orderBy(localized('name'))->get();

        // BR-138: Pre-load quarters for the address's current town
        $quarters = Quarter::query()
            ->active()
            ->forTown($address->town_id)
            ->orderBy(localized('name'))
            ->get()
            ->map(fn (Quarter $quarter) => [
                'id' => $quarter->id,
                'name' => $quarter->{localized('name')},
            ])
            ->values()
            ->toArray();

        return gale()->view('profile.addresses.edit', [
            'user' => $user,
            'tenant' => tenant(),
            'address' => $address,
            'towns' => $towns,
            'quarters' => $quarters,
        ], web: true);
    }

    /**
     * Update an existing delivery address (F-035).
     *
     * Handles both Gale SSE requests and traditional HTTP form submissions.
     * BR-135: Same validation as add (F-033).
     * BR-137: Label uniqueness excludes the current address.
     * BR-139: Users can only edit their own addresses.
     * BR-140: Save via Gale without page reload.
     */
    public function update(Request $request, Address $address): mixed
    {
        $user = Auth::user();

        // BR-139: Ensure address belongs to authenticated user
        if ($address->user_id !== $user->id) {
            abort(403);
        }

        if ($request->isGale()) {
            $validated = $this->validateGaleUpdateState($request, $address);
        } else {
            $formRequest = UpdateAddressRequest::createFrom($request);
            $formRequest->setRouteResolver(fn () => $request->route());
            $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();
            $validated = $formRequest->validated();
        }

        // Track old values for activity logging
        $oldValues = [
            'label' => $address->label,
            'town_id' => $address->town_id,
            'quarter_id' => $address->quarter_id,
            'neighbourhood' => $address->neighbourhood,
            'additional_directions' => $address->additional_directions,
        ];

        $address->update([
            'label' => trim($validated['label']),
            'town_id' => $validated['town_id'],
            'quarter_id' => $validated['quarter_id'],
            'neighbourhood' => $validated['neighbourhood'] ?? null,
            'additional_directions' => $validated['additional_directions'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]);

        // Build changed fields for activity log
        $newValues = [
            'label' => $address->label,
            'town_id' => $address->town_id,
            'quarter_id' => $address->quarter_id,
            'neighbourhood' => $address->neighbourhood,
            'additional_directions' => $address->additional_directions,
        ];

        $changes = array_filter(
            $newValues,
            fn ($value, $key) => $value !== ($oldValues[$key] ?? null),
            ARRAY_FILTER_USE_BOTH
        );

        activity('addresses')
            ->performedOn($address)
            ->causedBy($user)
            ->event('updated')
            ->withProperties([
                'label' => $address->label,
                'changes' => $changes,
                'ip' => $request->ip(),
            ])
            ->log(__('Delivery address updated'));

        return gale()->redirect('/profile/addresses')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Address updated successfully.'),
            ]);
    }

    /**
     * Delete a delivery address (F-036).
     *
     * BR-141: Confirmation handled client-side via Alpine modal.
     * BR-142: Cannot delete if only address AND pending orders reference it.
     * BR-143: If deleted address was default, first remaining becomes default.
     * BR-144: Users can only delete their own addresses.
     * BR-145: Hard delete (permanent).
     * BR-146: Multiple addresses — any can be deleted regardless of pending orders.
     */
    public function destroy(Request $request, Address $address): mixed
    {
        $user = Auth::user();

        // BR-144: Ensure address belongs to authenticated user
        if ($address->user_id !== $user->id) {
            abort(403);
        }

        $userAddressCount = $user->addresses()->count();

        // BR-142: Block deletion if only address and pending orders reference it
        if ($userAddressCount === 1 && $address->hasPendingOrders()) {
            if ($request->isGale()) {
                return gale()->state('deleteError', __('This address is used by pending orders and cannot be deleted. You can edit it instead.'));
            }

            return gale()->redirect('/profile/addresses')
                ->with('toast', [
                    'type' => 'error',
                    'message' => __('This address is used by pending orders and cannot be deleted. You can edit it instead.'),
                ]);
        }

        $wasDefault = $address->is_default;
        $addressLabel = $address->label;

        // BR-145: Hard delete
        $address->delete();

        // BR-143: Reassign default if needed
        if ($wasDefault) {
            $newDefault = $user->addresses()
                ->orderBy('label')
                ->first();

            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
        }

        // Log the deletion
        activity('addresses')
            ->causedBy($user)
            ->event('deleted')
            ->withProperties([
                'label' => $addressLabel,
                'was_default' => $wasDefault,
                'ip' => $request->ip(),
            ])
            ->log(__('Delivery address deleted'));

        return gale()->redirect('/profile/addresses')
            ->with('toast', [
                'type' => 'success',
                'message' => __('Address deleted.'),
            ]);
    }

    /**
     * Get quarters for a given town (AJAX endpoint for dynamic dropdown).
     *
     * Returns quarters filtered by town_id for the quarter dropdown.
     * This endpoint is called via Gale $action when the user selects a town.
     */
    public function quarters(Request $request): mixed
    {
        $townId = $request->isGale()
            ? $request->state('town_id')
            : $request->input('town_id');

        if (! $townId) {
            return gale()->state('quarters', []);
        }

        $quarters = Quarter::query()
            ->active()
            ->forTown((int) $townId)
            ->orderBy(localized('name'))
            ->get()
            ->map(fn (Quarter $quarter) => [
                'id' => $quarter->id,
                'name' => $quarter->{localized('name')},
            ])
            ->values()
            ->toArray();

        return gale()->state('quarters', $quarters);
    }

    /**
     * Validate Gale state for address creation.
     *
     * Applies the same rules as StoreAddressRequest but for Gale SSE requests.
     *
     * @return array<string, mixed>
     */
    private function validateGaleState(Request $request): array
    {
        $userId = Auth::id();

        return $request->validateState([
            'label' => [
                'required',
                'string',
                'max:50',
                Rule::unique('addresses', 'label')->where('user_id', $userId),
            ],
            'town_id' => [
                'required',
                'integer',
                'exists:towns,id',
            ],
            'quarter_id' => [
                'required',
                'integer',
                Rule::exists('quarters', 'id')->where('town_id', $request->state('town_id')),
            ],
            'neighbourhood' => [
                'nullable',
                'string',
                'max:255',
            ],
            'additional_directions' => [
                'nullable',
                'string',
                'max:500',
            ],
            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
        ], [
            'label.required' => __('Address label is required.'),
            'label.max' => __('Address label must not exceed 50 characters.'),
            'label.unique' => __('You already have an address with this label.'),
            'town_id.required' => __('Town is required.'),
            'town_id.exists' => __('The selected town is not available.'),
            'quarter_id.required' => __('Quarter is required.'),
            'quarter_id.exists' => __('The selected quarter does not belong to the chosen town.'),
            'neighbourhood.max' => __('Neighbourhood must not exceed 255 characters.'),
            'additional_directions.max' => __('Directions must not exceed 500 characters.'),
        ]);
    }

    /**
     * Validate Gale state for address update (F-035).
     *
     * Same rules as validateGaleState but with label uniqueness excluding the current address.
     * BR-137: Label uniqueness is validated among the user's other addresses.
     *
     * @return array<string, mixed>
     */
    private function validateGaleUpdateState(Request $request, Address $address): array
    {
        $userId = Auth::id();

        return $request->validateState([
            'label' => [
                'required',
                'string',
                'max:50',
                Rule::unique('addresses', 'label')
                    ->where('user_id', $userId)
                    ->ignore($address->id),
            ],
            'town_id' => [
                'required',
                'integer',
                'exists:towns,id',
            ],
            'quarter_id' => [
                'required',
                'integer',
                Rule::exists('quarters', 'id')->where('town_id', $request->state('town_id')),
            ],
            'neighbourhood' => [
                'nullable',
                'string',
                'max:255',
            ],
            'additional_directions' => [
                'nullable',
                'string',
                'max:500',
            ],
            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
        ], [
            'label.required' => __('Address label is required.'),
            'label.max' => __('Address label must not exceed 50 characters.'),
            'label.unique' => __('You already have an address with this label.'),
            'town_id.required' => __('Town is required.'),
            'town_id.exists' => __('The selected town is not available.'),
            'quarter_id.required' => __('Quarter is required.'),
            'quarter_id.exists' => __('The selected quarter does not belong to the chosen town.'),
            'neighbourhood.max' => __('Neighbourhood must not exceed 255 characters.'),
            'additional_directions.max' => __('Directions must not exceed 500 characters.'),
        ]);
    }
}
