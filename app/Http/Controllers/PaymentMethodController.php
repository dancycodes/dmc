<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentMethod\StorePaymentMethodRequest;
use App\Http\Requests\PaymentMethod\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    /**
     * Display the list of user's saved payment methods (F-038).
     *
     * BR-156: All methods displayed, default first.
     * BR-157: Phone numbers masked.
     * BR-158: Provider icons/logos.
     * BR-161: "Add" button only if < 3 methods.
     * BR-162: Each method has edit/delete links.
     */
    public function index(Request $request): mixed
    {
        $user = Auth::user();
        $paymentMethods = $user->paymentMethods()
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();

        $canAddMore = $paymentMethods->count() < PaymentMethod::MAX_PAYMENT_METHODS_PER_USER;

        return gale()->view('profile.payment-methods.index', [
            'user' => $user,
            'tenant' => tenant(),
            'paymentMethods' => $paymentMethods,
            'canAddMore' => $canAddMore,
            'maxMethods' => PaymentMethod::MAX_PAYMENT_METHODS_PER_USER,
        ], web: true);
    }

    /**
     * Set a payment method as the user's default (F-038).
     *
     * BR-159: Only one payment method can be default at a time.
     * BR-160: Setting a new default removes previous default.
     */
    public function setDefault(Request $request, PaymentMethod $paymentMethod): mixed
    {
        $user = Auth::user();

        // Ensure payment method belongs to authenticated user
        if ($paymentMethod->user_id !== $user->id) {
            abort(403);
        }

        // Already default — no change needed
        if ($paymentMethod->is_default) {
            return gale()->redirect('/profile/payment-methods')->back()
                ->with('toast', [
                    'type' => 'info',
                    'message' => __('This payment method is already your default.'),
                ]);
        }

        // BR-160: Remove default from all user payment methods
        $user->paymentMethods()->where('is_default', true)->update(['is_default' => false]);

        // Set the new default
        $paymentMethod->update(['is_default' => true]);

        // Log the change
        activity('payment_methods')
            ->performedOn($paymentMethod)
            ->causedBy($user)
            ->event('updated')
            ->withProperties([
                'label' => $paymentMethod->label,
                'action' => 'set_as_default',
                'ip' => $request->ip(),
            ])
            ->log(__('Default payment method updated'));

        return gale()->redirect('/profile/payment-methods')->back()
            ->with('toast', [
                'type' => 'success',
                'message' => __('Default payment method updated.'),
            ]);
    }

    /**
     * Display the add payment method form (F-037).
     *
     * BR-147: Maximum 3 saved payment methods per user.
     * BR-153: Payment methods are user-scoped, not tenant-scoped.
     */
    public function create(Request $request): mixed
    {
        $user = Auth::user();
        $methodCount = $user->paymentMethods()->count();
        $canAddMore = $methodCount < PaymentMethod::MAX_PAYMENT_METHODS_PER_USER;

        return gale()->view('profile.payment-methods.create', [
            'user' => $user,
            'tenant' => tenant(),
            'canAddMore' => $canAddMore,
            'methodCount' => $methodCount,
            'maxMethods' => PaymentMethod::MAX_PAYMENT_METHODS_PER_USER,
            'providers' => PaymentMethod::PROVIDERS,
            'providerLabels' => PaymentMethod::PROVIDER_LABELS,
        ], web: true);
    }

    /**
     * Store a new payment method (F-037).
     *
     * Handles both Gale SSE requests (from Alpine $action) and traditional
     * HTTP form submissions. Validates phone format and provider match.
     *
     * BR-147: Maximum 3 saved payment methods per user.
     * BR-148: Label required, unique per user, max 50 characters.
     * BR-149: Provider required, mtn_momo or orange_money.
     * BR-150: Phone must be valid Cameroon mobile number.
     * BR-151: Phone prefix must match provider.
     * BR-152: First payment method auto-set as default.
     * BR-153: User-scoped, not tenant-scoped.
     * BR-154: Phone stored in normalized +237XXXXXXXXX format.
     * BR-155: All text localized via __().
     */
    public function store(Request $request): mixed
    {
        $user = Auth::user();

        // BR-147: Check maximum payment method limit before processing
        $methodCount = $user->paymentMethods()->count();
        if ($methodCount >= PaymentMethod::MAX_PAYMENT_METHODS_PER_USER) {
            if ($request->isGale()) {
                return gale()->messages([
                    '_error' => __('You can save up to :max payment methods. Please remove one to add a new one.', [
                        'max' => PaymentMethod::MAX_PAYMENT_METHODS_PER_USER,
                    ]),
                ]);
            }

            return redirect()->back()->withErrors([
                'limit' => __('You can save up to :max payment methods. Please remove one to add a new one.', [
                    'max' => PaymentMethod::MAX_PAYMENT_METHODS_PER_USER,
                ]),
            ]);
        }

        if ($request->isGale()) {
            $validated = $this->validateGaleState($request);
        } else {
            $formRequest = StorePaymentMethodRequest::createFrom($request);
            $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();
            $validated = $formRequest->validated();
        }

        // BR-154: Normalize phone number
        $normalizedPhone = PaymentMethod::normalizePhone($validated['phone']);

        // BR-150: Validate Cameroon phone format
        if (! PaymentMethod::isValidCameroonPhone($normalizedPhone)) {
            if ($request->isGale()) {
                return gale()->messages([
                    'phone' => __('Please enter a valid Cameroon phone number.'),
                ]);
            }

            return redirect()->back()->withErrors([
                'phone' => __('Please enter a valid Cameroon phone number.'),
            ])->withInput();
        }

        // BR-151: Validate phone prefix matches provider
        if (! PaymentMethod::phoneMatchesProvider($normalizedPhone, $validated['provider'])) {
            if ($request->isGale()) {
                return gale()->messages([
                    'phone' => __('This phone number does not match the selected provider.'),
                ]);
            }

            return redirect()->back()->withErrors([
                'phone' => __('This phone number does not match the selected provider.'),
            ])->withInput();
        }

        // Edge case: Reject same phone under different provider
        $existingWithPhone = $user->paymentMethods()
            ->where('phone', $normalizedPhone)
            ->where('provider', '!=', $validated['provider'])
            ->exists();

        if ($existingWithPhone) {
            if ($request->isGale()) {
                return gale()->messages([
                    'phone' => __('This phone number is already saved under a different provider.'),
                ]);
            }

            return redirect()->back()->withErrors([
                'phone' => __('This phone number is already saved under a different provider.'),
            ])->withInput();
        }

        // BR-152: First payment method is automatically set as default
        $isDefault = $methodCount === 0;

        $paymentMethod = $user->paymentMethods()->create([
            'label' => trim($validated['label']),
            'provider' => $validated['provider'],
            'phone' => $normalizedPhone,
            'is_default' => $isDefault,
        ]);

        // Log the creation
        activity('payment_methods')
            ->performedOn($paymentMethod)
            ->causedBy($user)
            ->event('created')
            ->withProperties([
                'label' => $paymentMethod->label,
                'provider' => $paymentMethod->provider,
                'is_default' => $isDefault,
                'ip' => $request->ip(),
            ])
            ->log(__('Payment method added'));

        return gale()->redirect('/profile/payment-methods')->back()
            ->with('toast', [
                'type' => 'success',
                'message' => __('Payment method saved.'),
            ]);
    }

    /**
     * Display the edit payment method form (F-039).
     *
     * BR-163: Only label and phone are editable. Provider is read-only.
     * BR-167: Phone number shown unmasked for editing.
     * BR-168: Users can only edit their own payment methods.
     */
    public function edit(Request $request, PaymentMethod $paymentMethod): mixed
    {
        $user = Auth::user();

        // BR-168: Ensure payment method belongs to authenticated user
        if ($paymentMethod->user_id !== $user->id) {
            abort(403);
        }

        return gale()->view('profile.payment-methods.edit', [
            'user' => $user,
            'tenant' => tenant(),
            'paymentMethod' => $paymentMethod,
            'providerLabels' => PaymentMethod::PROVIDER_LABELS,
        ], web: true);
    }

    /**
     * Update a payment method (F-039).
     *
     * Handles both Gale SSE requests (from Alpine $action) and traditional
     * HTTP form submissions. Validates phone format against existing provider.
     *
     * BR-163: Only label and phone are editable.
     * BR-165: Phone validation must match existing provider.
     * BR-166: Label uniqueness excludes current method.
     * BR-168: Users can only edit their own payment methods.
     * BR-169: Save via Gale without page reload.
     */
    public function update(Request $request, PaymentMethod $paymentMethod): mixed
    {
        $user = Auth::user();

        // BR-168: Ensure payment method belongs to authenticated user
        if ($paymentMethod->user_id !== $user->id) {
            abort(403);
        }

        if ($request->isGale()) {
            $validated = $this->validateGaleUpdateState($request, $paymentMethod);
        } else {
            $formRequest = UpdatePaymentMethodRequest::createFrom($request);
            $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();
            $validated = $formRequest->validated();
        }

        // BR-154: Normalize phone number
        $normalizedPhone = PaymentMethod::normalizePhone($validated['phone']);

        // BR-150: Validate Cameroon phone format
        if (! PaymentMethod::isValidCameroonPhone($normalizedPhone)) {
            if ($request->isGale()) {
                return gale()->messages([
                    'phone' => __('Please enter a valid Cameroon phone number.'),
                ]);
            }

            return redirect()->back()->withErrors([
                'phone' => __('Please enter a valid Cameroon phone number.'),
            ])->withInput();
        }

        // BR-165: Validate phone prefix matches existing provider
        if (! PaymentMethod::phoneMatchesProvider($normalizedPhone, $paymentMethod->provider)) {
            $providerLabel = PaymentMethod::PROVIDER_LABELS[$paymentMethod->provider] ?? $paymentMethod->provider;

            if ($request->isGale()) {
                return gale()->messages([
                    'phone' => __('This phone number does not match :provider.', ['provider' => $providerLabel]),
                ]);
            }

            return redirect()->back()->withErrors([
                'phone' => __('This phone number does not match :provider.', ['provider' => $providerLabel]),
            ])->withInput();
        }

        // Edge case: Reject same phone under different provider
        $existingWithPhone = $user->paymentMethods()
            ->where('phone', $normalizedPhone)
            ->where('id', '!=', $paymentMethod->id)
            ->where('provider', '!=', $paymentMethod->provider)
            ->exists();

        if ($existingWithPhone) {
            if ($request->isGale()) {
                return gale()->messages([
                    'phone' => __('This phone number is already saved under a different provider.'),
                ]);
            }

            return redirect()->back()->withErrors([
                'phone' => __('This phone number is already saved under a different provider.'),
            ])->withInput();
        }

        // Track old values for activity logging
        $oldLabel = $paymentMethod->label;
        $oldPhone = $paymentMethod->phone;

        // Update the payment method
        $paymentMethod->update([
            'label' => trim($validated['label']),
            'phone' => $normalizedPhone,
        ]);

        // Log the update with old/new values
        activity('payment_methods')
            ->performedOn($paymentMethod)
            ->causedBy($user)
            ->event('updated')
            ->withProperties([
                'old' => [
                    'label' => $oldLabel,
                    'phone' => $oldPhone,
                ],
                'new' => [
                    'label' => $paymentMethod->label,
                    'phone' => $paymentMethod->phone,
                ],
                'ip' => $request->ip(),
            ])
            ->log(__('Payment method updated'));

        return gale()->redirect('/profile/payment-methods')->back()
            ->with('toast', [
                'type' => 'success',
                'message' => __('Payment method updated.'),
            ]);
    }

    /**
     * Validate Gale state for payment method creation.
     *
     * Applies the same rules as StorePaymentMethodRequest but for Gale SSE requests.
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
                Rule::unique('payment_methods', 'label')->where('user_id', $userId),
            ],
            'provider' => [
                'required',
                'string',
                Rule::in(PaymentMethod::PROVIDERS),
            ],
            'phone' => [
                'required',
                'string',
            ],
        ], [
            'label.required' => __('Payment method label is required.'),
            'label.max' => __('Label must not exceed 50 characters.'),
            'label.unique' => __('You already have a payment method with this label.'),
            'provider.required' => __('Please select a payment provider.'),
            'provider.in' => __('Please select a valid payment provider.'),
            'phone.required' => __('Phone number is required.'),
        ]);
    }

    /**
     * Validate Gale state for payment method update (F-039).
     *
     * Applies the same rules as UpdatePaymentMethodRequest but for Gale SSE requests.
     * BR-166: Label uniqueness excludes the current payment method.
     *
     * @return array<string, mixed>
     */
    private function validateGaleUpdateState(Request $request, PaymentMethod $paymentMethod): array
    {
        $userId = Auth::id();

        return $request->validateState([
            'label' => [
                'required',
                'string',
                'max:50',
                Rule::unique('payment_methods', 'label')
                    ->where('user_id', $userId)
                    ->ignore($paymentMethod->id),
            ],
            'phone' => [
                'required',
                'string',
            ],
        ], [
            'label.required' => __('Payment method label is required.'),
            'label.max' => __('Label must not exceed 50 characters.'),
            'label.unique' => __('You already have a payment method with this label.'),
            'phone.required' => __('Phone number is required.'),
        ]);
    }
}
