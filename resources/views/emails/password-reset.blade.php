{{--
    Password Reset Email Template (N-022, BR-067)

    Extends the base DancyMeals email layout for consistent branding.
    Contains greeting, reset button, expiration notice, and security note.

    Variables:
    - $userName: The recipient's display name
    - $resetUrl: Signed password reset URL
    - $expirationMinutes: Link validity in minutes
    - $emailLocale: Resolved locale (en/fr)
--}}
@extends('emails.layouts.base')

@section('preheader')
{{ __('Reset your DancyMeals password. This link expires in :minutes minutes.', ['minutes' => $expirationMinutes ?? 60], $emailLocale ?? 'en') }}
@endsection

@section('content')
    {{-- Greeting --}}
    <h1 class="email-content-heading" style="margin: 0 0 16px 0; font-size: 22px; font-weight: 700; color: #18181B; line-height: 1.3;">
        {{ __('Hello :name,', ['name' => $userName ?? ''], $emailLocale ?? 'en') }}
    </h1>

    <p class="email-content-text" style="margin: 0 0 24px 0; font-size: 15px; color: #3F3F46; line-height: 1.6;">
        {{ __('We received a request to reset your password for your DancyMeals account. Click the button below to set a new password.', [], $emailLocale ?? 'en') }}
    </p>

    {{-- CTA Button --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto 24px auto;">
        <tr>
            <td style="border-radius: 8px; background-color: #0D9488;">
                <a href="{{ $resetUrl }}" class="btn-primary" target="_blank" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #FFFFFF; text-decoration: none; border-radius: 8px; background-color: #0D9488;">
                    {{ __('Reset Password', [], $emailLocale ?? 'en') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Expiration notice --}}
    <p class="email-content-text" style="margin: 0 0 16px 0; font-size: 14px; color: #71717A; line-height: 1.5;">
        {{ __('This password reset link will expire in :minutes minutes.', ['minutes' => $expirationMinutes ?? 60], $emailLocale ?? 'en') }}
    </p>

    {{-- Security note --}}
    <p class="email-content-text" style="margin: 0 0 16px 0; font-size: 14px; color: #71717A; line-height: 1.5;">
        {{ __('If you did not request a password reset, no action is required. Your password will remain unchanged.', [], $emailLocale ?? 'en') }}
    </p>

    {{-- Divider --}}
    <hr class="email-divider" style="border: none; border-top: 1px solid #E4E4E7; margin: 24px 0;">

    {{-- Fallback URL --}}
    <p class="email-content-text" style="margin: 0 0 8px 0; font-size: 13px; color: #A1A1AA; line-height: 1.5;">
        {{ __('If the button above does not work, copy and paste this URL into your browser:', [], $emailLocale ?? 'en') }}
    </p>
    <p style="margin: 0; font-size: 12px; color: #0D9488; word-break: break-all; line-height: 1.5;">
        <a href="{{ $resetUrl }}" style="color: #0D9488; text-decoration: underline;">{{ $resetUrl }}</a>
    </p>
@endsection
