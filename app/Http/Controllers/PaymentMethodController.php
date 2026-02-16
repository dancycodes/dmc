<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentMethod\StorePaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
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
}
