{{--
    F-173: Withdrawal Processed Email Template (N-013, BR-358)

    Email sent to the cook when a withdrawal is successfully processed.
    Extends the base DancyMeals email layout for consistent branding.

    Dark mode: Email clients do not support Tailwind dark: classes.
    Inline CSS uses fixed colors. The base layout handles dark: mode via
    @media (prefers-color-scheme: dark) CSS media query in the parent template.

    Variables:
    - $withdrawal: WithdrawalRequest model instance
    - $formattedAmount: Formatted amount (e.g., "20,000 XAF")
    - $providerLabel: Provider label (e.g., "MTN MoMo")
    - $success: Boolean indicating success/failure
    - $walletUrl: URL to the cook's wallet dashboard
    - $emailLocale: Resolved locale (en/fr)
--}}
@extends('emails.layouts.base')

@section('preheader')
@if($success)
{{ __('Withdrawal of :amount sent to your :provider account', ['amount' => $formattedAmount, 'provider' => $providerLabel], $emailLocale ?? 'en') }}
@else
{{ __('Withdrawal of :amount failed - amount returned to wallet', ['amount' => $formattedAmount], $emailLocale ?? 'en') }}
@endif
@endsection

@section('content')
    {{-- Status icon --}}
    <div style="text-align: center; margin-bottom: 24px;">
        @if($success)
        <div style="display: inline-block; width: 64px; height: 64px; border-radius: 50%; background-color: #0D9488; text-align: center; line-height: 64px;">
            <span style="color: #FFFFFF; font-size: 32px; font-weight: bold;">&#x2713;</span>
        </div>
        @else
        <div style="display: inline-block; width: 64px; height: 64px; border-radius: 50%; background-color: #EF4444; text-align: center; line-height: 64px;">
            <span style="color: #FFFFFF; font-size: 32px; font-weight: bold;">&#x2717;</span>
        </div>
        @endif
    </div>

    {{-- Heading --}}
    <h1 class="email-content-heading" style="margin: 0 0 8px 0; font-size: 22px; font-weight: 700; color: #18181B; line-height: 1.3; text-align: center;">
        @if($success)
            {{ __('Withdrawal Sent!', [], $emailLocale ?? 'en') }}
        @else
            {{ __('Withdrawal Failed', [], $emailLocale ?? 'en') }}
        @endif
    </h1>

    <p class="email-content-text" style="margin: 0 0 24px 0; font-size: 15px; color: #3F3F46; line-height: 1.6; text-align: center;">
        @if($success)
            {{ __('Your withdrawal has been processed and sent to your mobile money account.', [], $emailLocale ?? 'en') }}
        @else
            {{ __('Your withdrawal could not be processed. The amount has been returned to your wallet.', [], $emailLocale ?? 'en') }}
        @endif
    </p>

    {{-- Withdrawal details card --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #F4F4F5; border-radius: 8px; margin-bottom: 24px;">
        <tr>
            <td style="padding: 20px;">
                {{-- Amount --}}
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 12px;">
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding-bottom: 4px;">{{ __('Amount', [], $emailLocale ?? 'en') }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 24px; font-weight: 700; color: {{ $success ? '#0D9488' : '#EF4444' }};">{{ $formattedAmount }}</td>
                    </tr>
                </table>

                {{-- Divider --}}
                <hr style="border: none; border-top: 1px solid #E4E4E7; margin: 12px 0;">

                {{-- Transfer details --}}
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Provider', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0;">{{ $providerLabel }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Phone Number', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0; font-family: monospace;">+237 {{ $withdrawal->mobile_money_number }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Status', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; text-align: right; padding: 4px 0; font-weight: 600; color: {{ $success ? '#0D9488' : '#EF4444' }};">
                            {{ $success ? __('Completed', [], $emailLocale ?? 'en') : __('Failed', [], $emailLocale ?? 'en') }}
                        </td>
                    </tr>
                    @if($withdrawal->flutterwave_reference)
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">{{ __('Reference', [], $emailLocale ?? 'en') }}</td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0; font-family: monospace;">{{ $withdrawal->flutterwave_reference }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if(!$success)
    {{-- Failure info note --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #FEF2F2; border-radius: 8px; margin-bottom: 24px;">
        <tr>
            <td style="padding: 16px;">
                <p style="margin: 0; font-size: 14px; color: #991B1B; line-height: 1.5;">
                    {{ __('The withdrawal amount has been returned to your wallet balance. You can try again or contact support if the issue persists.', [], $emailLocale ?? 'en') }}
                </p>
            </td>
        </tr>
    </table>
    @else
    {{-- Success info note --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ECFDF5; border-radius: 8px; margin-bottom: 24px;">
        <tr>
            <td style="padding: 16px;">
                <p style="margin: 0; font-size: 14px; color: #065F46; line-height: 1.5;">
                    {{ __('Your funds have been sent to your mobile money account. You should receive them shortly.', [], $emailLocale ?? 'en') }}
                </p>
            </td>
        </tr>
    </table>
    @endif

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
