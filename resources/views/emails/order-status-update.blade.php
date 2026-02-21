{{--
    F-192: Order Status Update Email Template (N-002 through N-005, BR-279)

    Email sent to the client for key order status transitions.
    Extends the base DancyMeals email layout for consistent branding.

    Variables:
    - $order: Order model instance
    - $newStatus: the new order status string constant
    - $statusLabel: human-readable status label
    - $clientName: client's display name
    - $cookName: cook/tenant name
    - $viewOrderUrl: URL to client's order detail page
    - $isRateable: bool — whether to show Rate Your Order CTA (Delivered/Picked Up/Completed)
    - $rateOrderUrl: URL to the order detail page for rating (null if not rateable)
    - $pickupDetails: pickup location name+address string (only for pickup orders)
    - $emailLocale: resolved locale (en/fr)
--}}
@extends('emails.layouts.base')

@section('preheader')
{{ __('Your order :number has been updated to: :status', ['number' => $order->order_number, 'status' => $statusLabel], $emailLocale ?? 'en') }}
@endsection

@section('content')
    {{-- Status icon with color based on status --}}
    @php
        $iconColor = match ($newStatus) {
            \App\Models\Order::STATUS_CONFIRMED        => '#0D9488',
            \App\Models\Order::STATUS_READY_FOR_PICKUP => '#2563EB',
            \App\Models\Order::STATUS_OUT_FOR_DELIVERY => '#7C3AED',
            \App\Models\Order::STATUS_DELIVERED        => '#059669',
            \App\Models\Order::STATUS_PICKED_UP        => '#059669',
            \App\Models\Order::STATUS_COMPLETED        => '#047857',
            default                                    => '#6B7280',
        };
        $statusIcon = match ($newStatus) {
            \App\Models\Order::STATUS_CONFIRMED        => '&#x2705;',
            \App\Models\Order::STATUS_READY_FOR_PICKUP => '&#x1F4CD;',
            \App\Models\Order::STATUS_OUT_FOR_DELIVERY => '&#x1F6D5;',
            \App\Models\Order::STATUS_DELIVERED        => '&#x1F384;',
            \App\Models\Order::STATUS_PICKED_UP        => '&#x1F384;',
            \App\Models\Order::STATUS_COMPLETED        => '&#x1F389;',
            default                                    => '&#x1F4E6;',
        };
    @endphp

    {{-- Icon --}}
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="display: inline-block; width: 64px; height: 64px; border-radius: 50%; background-color: {{ $iconColor }}; text-align: center; line-height: 64px;">
            <span style="color: #FFFFFF; font-size: 28px;">{!! $statusIcon !!}</span>
        </div>
    </div>

    {{-- Heading --}}
    <h1 class="email-content-heading" style="margin: 0 0 8px 0; font-size: 22px; font-weight: 700; color: #18181B; line-height: 1.3; text-align: center;">
        {{ __($statusLabel, [], $emailLocale ?? 'en') }}
    </h1>

    <p class="email-content-text" style="margin: 0 0 24px 0; font-size: 15px; color: #3F3F46; line-height: 1.6; text-align: center;">
        @if($newStatus === \App\Models\Order::STATUS_CONFIRMED)
            {{ __('Hi :name, your order has been confirmed and is being prepared.', ['name' => $clientName], $emailLocale ?? 'en') }}
        @elseif($newStatus === \App\Models\Order::STATUS_READY_FOR_PICKUP)
            {{ __('Hi :name, your order is ready for pickup!', ['name' => $clientName], $emailLocale ?? 'en') }}
        @elseif($newStatus === \App\Models\Order::STATUS_OUT_FOR_DELIVERY)
            {{ __('Hi :name, your order is on its way!', ['name' => $clientName], $emailLocale ?? 'en') }}
        @elseif(in_array($newStatus, [\App\Models\Order::STATUS_DELIVERED, \App\Models\Order::STATUS_PICKED_UP]))
            {{ __('Hi :name, your order has arrived. Enjoy your meal!', ['name' => $clientName], $emailLocale ?? 'en') }}
        @elseif($newStatus === \App\Models\Order::STATUS_COMPLETED)
            {{ __('Hi :name, your order is now complete. Thank you for ordering!', ['name' => $clientName], $emailLocale ?? 'en') }}
        @else
            {{ __('Hi :name, your order status has been updated.', ['name' => $clientName], $emailLocale ?? 'en') }}
        @endif
    </p>

    {{-- Order info card --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #F4F4F5; border-radius: 8px; margin-bottom: 24px;">
        <tr>
            <td style="padding: 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Order Number', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0; font-family: monospace; font-weight: 600;">{{ $order->order_number }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Status', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; font-weight: 700; color: {{ $iconColor }}; text-align: right; padding: 4px 0;">{{ __($statusLabel, [], $emailLocale ?? 'en') }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Cook', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0;">{{ $cookName }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Total', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; font-weight: 600; color: #18181B; text-align: right; padding: 4px 0;">{{ $order->formattedGrandTotal() }}</td>
                    </tr>
                    @if($pickupDetails)
                        <tr>
                            <td colspan="2" style="padding-top: 12px;">
                                <hr style="border: none; border-top: 1px solid #E4E4E7; margin: 0 0 12px 0;">
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 13px; color: #71717A; padding: 4px 0; vertical-align: top;">{{ __('Pickup Location', [], $emailLocale ?? 'en') }}</td>
                            <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0;">{{ $pickupDetails }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- View Order CTA --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto 24px auto;">
        <tr>
            <td style="border-radius: 8px; background-color: #0D9488;">
                <a href="{{ $viewOrderUrl }}" class="btn-primary" target="_blank" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #FFFFFF; text-decoration: none; border-radius: 8px; background-color: #0D9488;">
                    {{ __('View Order', [], $emailLocale ?? 'en') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- BR-283: Rate Your Order CTA for Delivered/Picked Up/Completed --}}
    @if($isRateable && $rateOrderUrl)
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #FFFBEB; border-radius: 8px; margin-bottom: 24px;">
            <tr>
                <td style="padding: 20px; text-align: center;">
                    <p style="margin: 0 0 12px 0; font-size: 14px; color: #92400E; line-height: 1.5;">
                        {{ __('We hope you enjoyed your meal! Share your experience by leaving a rating.', [], $emailLocale ?? 'en') }}
                    </p>
                    <a href="{{ $rateOrderUrl }}" target="_blank" style="display: inline-block; padding: 10px 24px; font-size: 14px; font-weight: 600; color: #FFFFFF; text-decoration: none; border-radius: 6px; background-color: #D97706;">
                        {{ __('Rate Your Order', [], $emailLocale ?? 'en') }}
                    </a>
                </td>
            </tr>
        </table>
    @endif

    {{-- Thank you note --}}
    <p class="email-content-text" style="margin: 0; font-size: 14px; color: #71717A; line-height: 1.5; text-align: center;">
        {{ __('Thank you for using DancyMeals!', [], $emailLocale ?? 'en') }}
    </p>
@endsection
