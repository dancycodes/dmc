{{--
    F-154: Payment Receipt Email Template (N-006, BR-402)

    Email receipt sent to the client after successful payment.
    Extends the base DancyMeals email layout for consistent branding.

    Variables:
    - $order: Order model instance
    - $items: Parsed items grouped by meal
    - $paymentLabel: Human-readable payment method
    - $transactionReference: Flutterwave or internal reference
    - $cookName: Cook/tenant name
    - $trackingUrl: URL to track the order
    - $emailLocale: Resolved locale (en/fr)
--}}
@extends('emails.layouts.base')

@section('preheader')
{{ __('Payment confirmed for order :number - :amount', ['number' => $order->order_number, 'amount' => $order->formattedGrandTotal()], $emailLocale ?? 'en') }}
@endsection

@section('content')
    {{-- Success icon --}}
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="display: inline-block; width: 64px; height: 64px; border-radius: 50%; background-color: #10B981; text-align: center; line-height: 64px;">
            <span style="color: #FFFFFF; font-size: 32px; font-weight: bold;">&#10003;</span>
        </div>
    </div>

    {{-- Heading --}}
    <h1 class="email-content-heading" style="margin: 0 0 8px 0; font-size: 22px; font-weight: 700; color: #18181B; line-height: 1.3; text-align: center;">
        {{ __('Payment Successful!', [], $emailLocale ?? 'en') }}
    </h1>

    <p class="email-content-text" style="margin: 0 0 24px 0; font-size: 15px; color: #3F3F46; line-height: 1.6; text-align: center;">
        {{ __('Your payment for order :number from :cook has been confirmed.', ['number' => $order->order_number, 'cook' => $cookName], $emailLocale ?? 'en') }}
    </p>

    {{-- Order details card --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #F4F4F5; border-radius: 8px; margin-bottom: 24px;">
        <tr>
            <td style="padding: 20px;">
                {{-- Order number --}}
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 12px;">
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding-bottom: 4px;">{{ __('Order Number', [], $emailLocale ?? 'en') }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 16px; font-weight: 700; color: #18181B; font-family: monospace;">{{ $order->order_number }}</td>
                    </tr>
                </table>

                {{-- Divider --}}
                <hr style="border: none; border-top: 1px solid #E4E4E7; margin: 12px 0;">

                {{-- Items --}}
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 12px;">
                    <tr>
                        <td style="font-size: 13px; font-weight: 600; color: #18181B; padding-bottom: 8px;">{{ __('Items', [], $emailLocale ?? 'en') }}</td>
                    </tr>
                    @foreach($items as $meal)
                        <tr>
                            <td style="font-size: 14px; font-weight: 600; color: #3F3F46; padding: 4px 0 2px 0;">{{ $meal['meal_name'] }}</td>
                        </tr>
                        @foreach($meal['components'] as $component)
                            <tr>
                                <td style="padding: 2px 0 2px 12px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td style="font-size: 13px; color: #71717A;">{{ $component['quantity'] }}x {{ $component['name'] }}</td>
                                            <td style="font-size: 13px; color: #3F3F46; text-align: right; font-weight: 500;">{{ number_format($component['subtotal'], 0, '.', ',') }} XAF</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </table>

                {{-- Divider --}}
                <hr style="border: none; border-top: 1px solid #E4E4E7; margin: 12px 0;">

                {{-- Subtotal --}}
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="font-size: 14px; color: #71717A; padding: 4px 0;">{{ __('Subtotal', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 14px; color: #3F3F46; text-align: right; padding: 4px 0;">{{ number_format($order->subtotal, 0, '.', ',') }} XAF</td>
                    </tr>
                    @if($order->delivery_fee > 0)
                        <tr>
                            <td style="font-size: 14px; color: #71717A; padding: 4px 0;">{{ __('Delivery Fee', [], $emailLocale ?? 'en') }}</td>
                            <td style="font-size: 14px; color: #3F3F46; text-align: right; padding: 4px 0;">{{ number_format($order->delivery_fee, 0, '.', ',') }} XAF</td>
                        </tr>
                    @else
                        <tr>
                            <td style="font-size: 14px; color: #71717A; padding: 4px 0;">{{ __('Delivery Fee', [], $emailLocale ?? 'en') }}</td>
                            <td style="font-size: 14px; color: #10B981; text-align: right; padding: 4px 0;">{{ __('Free', [], $emailLocale ?? 'en') }}</td>
                        </tr>
                    @endif
                    @if($order->promo_discount > 0)
                        <tr>
                            <td style="font-size: 14px; color: #71717A; padding: 4px 0;">{{ __('Promo Discount', [], $emailLocale ?? 'en') }}</td>
                            <td style="font-size: 14px; color: #10B981; text-align: right; padding: 4px 0;">-{{ number_format($order->promo_discount, 0, '.', ',') }} XAF</td>
                        </tr>
                    @endif
                </table>

                {{-- Grand total --}}
                <hr style="border: none; border-top: 2px solid #E4E4E7; margin: 8px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="font-size: 16px; font-weight: 700; color: #18181B; padding: 4px 0;">{{ __('Total Paid', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 16px; font-weight: 700; color: #0D9488; text-align: right; padding: 4px 0;">{{ $order->formattedGrandTotal() }}</td>
                    </tr>
                </table>

                {{-- Divider --}}
                <hr style="border: none; border-top: 1px solid #E4E4E7; margin: 12px 0;">

                {{-- Payment details --}}
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Payment Method', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0;">{{ $paymentLabel }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Transaction Ref', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0; font-family: monospace;">{{ $transactionReference }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Status', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #10B981; text-align: right; padding: 4px 0; font-weight: 600;">{{ __('Paid', [], $emailLocale ?? 'en') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- CTA Button --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto 24px auto;">
        <tr>
            <td style="border-radius: 8px; background-color: #0D9488;">
                <a href="{{ $trackingUrl }}" class="btn-primary" target="_blank" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #FFFFFF; text-decoration: none; border-radius: 8px; background-color: #0D9488;">
                    {{ __('View Order', [], $emailLocale ?? 'en') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Thank you note --}}
    <p class="email-content-text" style="margin: 0; font-size: 14px; color: #71717A; line-height: 1.5; text-align: center;">
        {{ __('Thank you for ordering from :cook on DancyMeals!', ['cook' => $cookName], $emailLocale ?? 'en') }}
    </p>
@endsection
