{{--
    F-167: Refund Credited Email Template (N-008, BR-295)

    Email sent to the client when a refund is credited to their wallet.
    Extends the base DancyMeals email layout for consistent branding.

    Dark mode: Email clients do not support Tailwind dark: classes.
    Inline CSS uses fixed colors. The base layout handles dark: mode via
    @media (prefers-color-scheme: dark) CSS media query in the parent template.

    Variables:
    - $order: Order model instance
    - $refundAmount: Formatted refund amount (e.g., "5,000 XAF")
    - $cookName: Cook/tenant name
    - $walletUrl: URL to the client's wallet dashboard
    - $emailLocale: Resolved locale (en/fr)
--}}
@extends('emails.layouts.base')

@section('preheader')
{{ __('Refund of :amount credited to your wallet for order :number', ['amount' => $refundAmount, 'number' => $order->order_number], $emailLocale ?? 'en') }}
@endsection

@section('content')
    {{-- Refund icon --}}
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="display: inline-block; width: 64px; height: 64px; border-radius: 50%; background-color: #0D9488; text-align: center; line-height: 64px;">
            <span style="color: #FFFFFF; font-size: 32px; font-weight: bold;">&#x21BA;</span>
        </div>
    </div>

    {{-- Heading --}}
    <h1 class="email-content-heading" style="margin: 0 0 8px 0; font-size: 22px; font-weight: 700; color: #18181B; line-height: 1.3; text-align: center;">
        {{ __('Refund Credited!', [], $emailLocale ?? 'en') }}
    </h1>

    <p class="email-content-text" style="margin: 0 0 24px 0; font-size: 15px; color: #3F3F46; line-height: 1.6; text-align: center;">
        {{ __('A refund has been credited to your DancyMeals wallet.', [], $emailLocale ?? 'en') }}
    </p>

    {{-- Refund details card --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #F4F4F5; border-radius: 8px; margin-bottom: 24px;">
        <tr>
            <td style="padding: 20px;">
                {{-- Refund amount --}}
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 12px;">
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding-bottom: 4px;">{{ __('Refund Amount', [], $emailLocale ?? 'en') }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 24px; font-weight: 700; color: #0D9488;">{{ $refundAmount }}</td>
                    </tr>
                </table>

                {{-- Divider --}}
                <hr style="border: none; border-top: 1px solid #E4E4E7; margin: 12px 0;">

                {{-- Order details --}}
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Order Number', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0; font-family: monospace;">{{ $order->order_number }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Cook', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0;">{{ $cookName }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Credited To', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #0D9488; text-align: right; padding: 4px 0; font-weight: 600;">{{ __('Wallet Balance', [], $emailLocale ?? 'en') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Info note --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ECFDF5; border-radius: 8px; margin-bottom: 24px;">
        <tr>
            <td style="padding: 16px;">
                <p style="margin: 0; font-size: 14px; color: #065F46; line-height: 1.5;">
                    {{ __('Your refund has been added to your DancyMeals wallet. You can use this balance to pay for future orders.', [], $emailLocale ?? 'en') }}
                </p>
            </td>
        </tr>
    </table>

    {{-- CTA Button --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto 24px auto;">
        <tr>
            <td style="border-radius: 8px; background-color: #0D9488;">
                <a href="{{ $walletUrl }}" class="btn-primary" target="_blank" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #FFFFFF; text-decoration: none; border-radius: 8px; background-color: #0D9488;">
                    {{ __('View Wallet', [], $emailLocale ?? 'en') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Thank you note --}}
    <p class="email-content-text" style="margin: 0; font-size: 14px; color: #71717A; line-height: 1.5; text-align: center;">
        {{ __('Thank you for using DancyMeals!', [], $emailLocale ?? 'en') }}
    </p>
@endsection
