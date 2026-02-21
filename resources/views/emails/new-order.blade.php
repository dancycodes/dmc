{{--
    F-191: New Order Email Template (N-001, BR-273)

    Email sent to the cook and managers when a new paid order arrives.
    Extends the base DancyMeals email layout for consistent branding.

    Dark mode: Email clients do not support Tailwind dark: classes.
    Inline CSS uses fixed colors. The base layout handles dark mode via
    @media (prefers-color-scheme: dark) CSS media query in the parent template.

    Variables:
    - $order: Order model instance
    - $items: array of parsed order items
    - $itemCount: total item count
    - $clientName: client's display name
    - $cookName: cook/tenant name
    - $deliveryLabel: "Delivery" or "Pickup"
    - $deliveryAddress: formatted delivery/pickup address
    - $viewOrderUrl: URL to cook dashboard order detail
    - $orderDate: formatted order date
    - $emailLocale: resolved locale (en/fr)
--}}
@extends('emails.layouts.base')

@section('preheader')
{{ __('New order :number from :client - :amount', ['number' => $order->order_number, 'client' => $clientName, 'amount' => $order->formattedGrandTotal()], $emailLocale ?? 'en') }}
@endsection

@section('content')
    {{-- Order icon --}}
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="display: inline-block; width: 64px; height: 64px; border-radius: 50%; background-color: #0D9488; text-align: center; line-height: 64px;">
            <span style="color: #FFFFFF; font-size: 28px;">&#x1F4E6;</span>
        </div>
    </div>

    {{-- Heading --}}
    <h1 class="email-content-heading" style="margin: 0 0 8px 0; font-size: 22px; font-weight: 700; color: #18181B; line-height: 1.3; text-align: center;">
        {{ __('New Order Received!', [], $emailLocale ?? 'en') }}
    </h1>

    <p class="email-content-text" style="margin: 0 0 24px 0; font-size: 15px; color: #3F3F46; line-height: 1.6; text-align: center;">
        {{ __('A new order has been placed by :client.', ['client' => $clientName], $emailLocale ?? 'en') }}
    </p>

    {{-- Order summary card --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #F4F4F5; border-radius: 8px; margin-bottom: 24px;">
        <tr>
            <td style="padding: 20px;">
                {{-- Order number and total --}}
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 12px;">
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding-bottom: 4px;">{{ __('Order Number', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding-bottom: 4px; font-family: monospace; font-weight: 600;">{{ $order->order_number }}</td>
                    </tr>
                </table>

                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 12px;">
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding-bottom: 4px;">{{ __('Total Amount', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 24px; font-weight: 700; color: #0D9488; text-align: right;">{{ $order->formattedGrandTotal() }}</td>
                    </tr>
                </table>

                {{-- Divider --}}
                <hr style="border: none; border-top: 1px solid #E4E4E7; margin: 12px 0;">

                {{-- Order details --}}
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Customer', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0;">{{ $clientName }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Items', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0;">{{ $itemCount }} {{ trans_choice('item|items', $itemCount, [], $emailLocale ?? 'en') }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Method', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0;">{{ $deliveryLabel }}</td>
                    </tr>
                    @if($deliveryAddress)
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ $order->delivery_method === 'delivery' ? __('Delivery Address', [], $emailLocale ?? 'en') : __('Pickup Location', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0;">{{ $deliveryAddress }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Order Date', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0;">{{ $orderDate }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Items breakdown --}}
    @if(count($items) > 0)
    <h2 class="email-content-heading" style="margin: 0 0 12px 0; font-size: 16px; font-weight: 600; color: #18181B;">
        {{ __('Order Items', [], $emailLocale ?? 'en') }}
    </h2>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 24px;">
        {{-- Table header --}}
        <tr style="background-color: #F4F4F5;">
            <td style="font-size: 12px; color: #71717A; padding: 8px 12px; font-weight: 600; text-transform: uppercase;">{{ __('Item', [], $emailLocale ?? 'en') }}</td>
            <td style="font-size: 12px; color: #71717A; padding: 8px 12px; font-weight: 600; text-transform: uppercase; text-align: center;">{{ __('Qty', [], $emailLocale ?? 'en') }}</td>
            <td style="font-size: 12px; color: #71717A; padding: 8px 12px; font-weight: 600; text-transform: uppercase; text-align: right;">{{ __('Price', [], $emailLocale ?? 'en') }}</td>
            <td style="font-size: 12px; color: #71717A; padding: 8px 12px; font-weight: 600; text-transform: uppercase; text-align: right;">{{ __('Subtotal', [], $emailLocale ?? 'en') }}</td>
        </tr>

        {{-- Item rows --}}
        @foreach($items as $item)
        <tr style="border-bottom: 1px solid #E4E4E7;">
            <td style="font-size: 13px; color: #3F3F46; padding: 10px 12px;">
                {{ $item['meal_name'] }}
                @if(!empty($item['component_name']))
                <br><span style="font-size: 12px; color: #71717A;">{{ $item['component_name'] }}</span>
                @endif
            </td>
            <td style="font-size: 13px; color: #3F3F46; padding: 10px 12px; text-align: center;">{{ $item['quantity'] }}</td>
            <td style="font-size: 13px; color: #3F3F46; padding: 10px 12px; text-align: right;">{{ number_format($item['unit_price'], 0, '.', ',') }} XAF</td>
            <td style="font-size: 13px; color: #3F3F46; padding: 10px 12px; text-align: right; font-weight: 600;">{{ number_format($item['subtotal'], 0, '.', ',') }} XAF</td>
        </tr>
        @endforeach

        {{-- Totals --}}
        <tr>
            <td colspan="3" style="font-size: 13px; color: #71717A; padding: 8px 12px; text-align: right;">{{ __('Subtotal', [], $emailLocale ?? 'en') }}</td>
            <td style="font-size: 13px; color: #3F3F46; padding: 8px 12px; text-align: right;">{{ number_format($order->subtotal, 0, '.', ',') }} XAF</td>
        </tr>
        @if((float) $order->delivery_fee > 0)
        <tr>
            <td colspan="3" style="font-size: 13px; color: #71717A; padding: 4px 12px; text-align: right;">{{ __('Delivery Fee', [], $emailLocale ?? 'en') }}</td>
            <td style="font-size: 13px; color: #3F3F46; padding: 4px 12px; text-align: right;">{{ number_format($order->delivery_fee, 0, '.', ',') }} XAF</td>
        </tr>
        @endif
        @if((float) $order->promo_discount > 0)
        <tr>
            <td colspan="3" style="font-size: 13px; color: #0D9488; padding: 4px 12px; text-align: right;">{{ __('Discount', [], $emailLocale ?? 'en') }}</td>
            <td style="font-size: 13px; color: #0D9488; padding: 4px 12px; text-align: right;">-{{ number_format($order->promo_discount, 0, '.', ',') }} XAF</td>
        </tr>
        @endif
        <tr style="background-color: #F4F4F5;">
            <td colspan="3" style="font-size: 15px; font-weight: 700; color: #18181B; padding: 12px; text-align: right;">{{ __('Grand Total', [], $emailLocale ?? 'en') }}</td>
            <td style="font-size: 15px; font-weight: 700; color: #0D9488; padding: 12px; text-align: right;">{{ $order->formattedGrandTotal() }}</td>
        </tr>
    </table>
    @endif

    {{-- CTA Button --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto 24px auto;">
        <tr>
            <td style="border-radius: 8px; background-color: #0D9488;">
                <a href="{{ $viewOrderUrl }}" class="btn-primary" target="_blank" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #FFFFFF; text-decoration: none; border-radius: 8px; background-color: #0D9488;">
                    {{ __('View Order', [], $emailLocale ?? 'en') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Info note --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ECFDF5; border-radius: 8px; margin-bottom: 24px;">
        <tr>
            <td style="padding: 16px;">
                <p style="margin: 0; font-size: 14px; color: #065F46; line-height: 1.5;">
                    {{ __('Please review this order and confirm it as soon as possible. The customer is waiting for your confirmation.', [], $emailLocale ?? 'en') }}
                </p>
            </td>
        </tr>
    </table>

    {{-- Thank you note --}}
    <p class="email-content-text" style="margin: 0; font-size: 14px; color: #71717A; line-height: 1.5; text-align: center;">
        {{ __('Thank you for using DancyMeals!', [], $emailLocale ?? 'en') }}
    </p>
@endsection
