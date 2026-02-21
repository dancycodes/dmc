{{--
    F-193: Complaint Resolved Email Template (N-012, BR-292, BR-294)

    Email sent to the client when their complaint is resolved by admin.
    Extends the base DancyMeals email layout for consistent branding.

    Dark mode: Email clients do not support Tailwind dark: classes.
    Inline CSS uses fixed colors. The base layout handles dark mode via
    @media (prefers-color-scheme: dark) CSS media query in the parent template.

    Variables:
    - $complaint: Complaint model instance
    - $order: Order model instance (nullable)
    - $orderNumber: Formatted order number string
    - $category: Raw complaint category key
    - $categoryLabel: Human-readable category label
    - $resolutionType: Resolution type key (dismiss, partial_refund, full_refund, warning, suspend)
    - $resolutionTypeLabel: Human-readable resolution type label
    - $resolutionNotes: Admin resolution notes
    - $refundAmount: Formatted refund amount string (e.g. "5,000 XAF") or null
    - $viewComplaintUrl: URL to the complaint tracking page
    - $emailLocale: Resolved locale (en/fr)
--}}
@extends('emails.layouts.base')

@section('preheader')
{{ __('Your complaint on order :order has been resolved', ['order' => $orderNumber], $emailLocale ?? 'en') }}
@endsection

@section('content')
    {{-- Resolution icon --}}
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="display: inline-block; width: 64px; height: 64px; border-radius: 50%; background-color: #0D9488; text-align: center; line-height: 64px;">
            {{-- Checkmark icon --}}
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-top: 16px;">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
    </div>

    {{-- Heading --}}
    <h1 class="email-content-heading" style="margin: 0 0 8px 0; font-size: 22px; font-weight: 700; color: #18181B; line-height: 1.3; text-align: center;">
        {{ __('Complaint Resolved', [], $emailLocale ?? 'en') }}
    </h1>

    <p class="email-content-text" style="margin: 0 0 24px 0; font-size: 15px; color: #3F3F46; line-height: 1.6; text-align: center;">
        {{ __('Our support team has reviewed and resolved your complaint.', [], $emailLocale ?? 'en') }}
    </p>

    {{-- Resolution summary card --}}
    @php
        $cardBg = match($resolutionType) {
            'dismiss' => '#F4F4F5',
            'partial_refund' => '#EFF6FF',
            'full_refund' => '#FFF7ED',
            default => '#F4F4F5',
        };
        $cardBorder = match($resolutionType) {
            'dismiss' => '#D4D4D8',
            'partial_refund' => '#BFDBFE',
            'full_refund' => '#FED7AA',
            default => '#D4D4D8',
        };
        $labelColor = match($resolutionType) {
            'dismiss' => '#18181B',
            'partial_refund' => '#1D4ED8',
            'full_refund' => '#C2410C',
            default => '#18181B',
        };
    @endphp

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
        style="background-color: {{ $cardBg }}; border-radius: 8px; border: 1px solid {{ $cardBorder }}; margin-bottom: 24px;">
        <tr>
            <td style="padding: 20px;">
                {{-- Resolution type badge --}}
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 16px;">
                    <tr>
                        <td style="font-size: 12px; color: #71717A; text-transform: uppercase; letter-spacing: 0.05em; padding-bottom: 4px;">
                            {{ __('Resolution', [], $emailLocale ?? 'en') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 18px; font-weight: 700; color: {{ $labelColor }};">
                            {{ $resolutionTypeLabel }}
                        </td>
                    </tr>
                </table>

                {{-- Divider --}}
                <hr style="border: none; border-top: 1px solid {{ $cardBorder }}; margin: 0 0 16px 0;">

                {{-- Complaint details --}}
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">
                            {{ __('Order', [], $emailLocale ?? 'en') }}
                        </td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0; font-family: monospace;">
                            {{ $orderNumber }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #71717A; padding: 4px 0;">
                            {{ __('Category', [], $emailLocale ?? 'en') }}
                        </td>
                        <td style="font-size: 13px; color: #3F3F46; text-align: right; padding: 4px 0;">
                            {{ $categoryLabel }}
                        </td>
                    </tr>
                    @if($refundAmount)
                        <tr>
                            <td style="font-size: 13px; color: #71717A; padding: 4px 0;">
                                {{ __('Refund Amount', [], $emailLocale ?? 'en') }}
                            </td>
                            <td style="font-size: 13px; font-weight: 700; color: #0D9488; text-align: right; padding: 4px 0;">
                                {{ $refundAmount }}
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- Admin notes section --}}
    @if($resolutionNotes)
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
            style="background-color: #F9FAFB; border-radius: 8px; border-left: 4px solid #0D9488; margin-bottom: 24px;">
            <tr>
                <td style="padding: 16px;">
                    <p style="margin: 0 0 6px 0; font-size: 12px; color: #71717A; text-transform: uppercase; letter-spacing: 0.05em;">
                        {{ __('Admin Notes', [], $emailLocale ?? 'en') }}
                    </p>
                    <p style="margin: 0; font-size: 14px; color: #3F3F46; line-height: 1.6;">
                        {{ $resolutionNotes }}
                    </p>
                </td>
            </tr>
        </table>
    @endif

    {{-- Refund info note for refund resolutions --}}
    @if($refundAmount)
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
            style="background-color: #ECFDF5; border-radius: 8px; margin-bottom: 24px;">
            <tr>
                <td style="padding: 16px;">
                    <p style="margin: 0; font-size: 14px; color: #065F46; line-height: 1.5;">
                        {{ __('Your refund of :amount has been credited to your DancyMeals wallet. You can use it to pay for future orders.', ['amount' => $refundAmount], $emailLocale ?? 'en') }}
                    </p>
                </td>
            </tr>
        </table>
    @elseif($resolutionType === 'dismiss')
        {{-- Dismiss notice --}}
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
            style="background-color: #F4F4F5; border-radius: 8px; margin-bottom: 24px;">
            <tr>
                <td style="padding: 16px;">
                    <p style="margin: 0; font-size: 14px; color: #52525B; line-height: 1.5;">
                        {{ __('Complaint reviewed — no action required at this time. If you have further concerns, please contact our support team.', [], $emailLocale ?? 'en') }}
                    </p>
                </td>
            </tr>
        </table>
    @else
        {{-- Warning/suspend — action taken --}}
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
            style="background-color: #ECFDF5; border-radius: 8px; margin-bottom: 24px;">
            <tr>
                <td style="padding: 16px;">
                    <p style="margin: 0; font-size: 14px; color: #065F46; line-height: 1.5;">
                        {{ __('Resolved — action taken. Our team has addressed your complaint and taken appropriate action.', [], $emailLocale ?? 'en') }}
                    </p>
                </td>
            </tr>
        </table>
    @endif

    {{-- CTA Button --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto 24px auto;">
        <tr>
            <td style="border-radius: 8px; background-color: #0D9488;">
                <a href="{{ $viewComplaintUrl }}" class="btn-primary" target="_blank"
                    style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #FFFFFF; text-decoration: none; border-radius: 8px; background-color: #0D9488;">
                    {{ __('View Complaint', [], $emailLocale ?? 'en') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Thank you note --}}
    <p class="email-content-text" style="margin: 0; font-size: 14px; color: #71717A; line-height: 1.5; text-align: center;">
        {{ __('Thank you for using DancyMeals. We take all complaints seriously and are committed to improving your experience.', [], $emailLocale ?? 'en') }}
    </p>
@endsection
