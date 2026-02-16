<?php

namespace App\Http\Controllers;

use App\Http\Requests\Address\StoreAddressRequest;
use App\Models\Address;
use App\Models\Quarter;
use App\Models\Town;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
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

        return gale()->redirect('/profile/addresses/create')->back()
            ->with('toast', [
                'type' => 'success',
                'message' => __('Address saved successfully.'),
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
}
