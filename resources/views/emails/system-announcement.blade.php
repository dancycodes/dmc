{{--
    F-195: System Announcement Email Template (N-020, BR-319)

    Email sent to targeted users for a system announcement from the admin.
    Extends the base DancyMeals email layout for consistent branding.

    Variables:
    - $announcement: Announcement model instance
    - $content: full announcement content
    - $contentPreview: truncated preview for preheader
    - $sentAt: formatted sent timestamp
    - $emailLocale: resolved locale (en/fr)
--}}
@extends('emails.layouts.base')

@section('preheader')
{{ $contentPreview }}
@endsection

@section('content')
    {{-- Announcement icon --}}
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="display: inline-block; width: 64px; height: 64px; border-radius: 50%; background-color: #0D9488; text-align: center; line-height: 64px;">
            <span style="color: #FFFFFF; font-size: 28px;">&#x1F4E2;</span>
        </div>
    </div>

    {{-- Heading --}}
    <h1 class="email-content-heading" style="margin: 0 0 8px 0; font-size: 22px; font-weight: 700; color: #18181B; line-height: 1.3; text-align: center;">
        {{ __('DancyMeals Announcement', [], $emailLocale ?? 'en') }}
    </h1>

    {{-- Date --}}
    <p style="text-align: center; color: #71717A; font-size: 14px; margin: 0 0 24px 0;">
        {{ $sentAt }}
    </p>

    {{-- Divider --}}
    <hr style="border: none; border-top: 1px solid #E4E4E7; margin: 0 0 24px 0;" />

    {{-- Announcement content --}}
    <div style="background-color: #F4F4F5; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
        <p style="margin: 0; color: #18181B; font-size: 15px; line-height: 1.7; white-space: pre-wrap;">{{ $content }}</p>
    </div>

    {{-- CTA --}}
    <div style="text-align: center; margin-bottom: 24px;">
        <a href="{{ config('app.url') }}"
           style="display: inline-block; padding: 12px 28px; background-color: #0D9488; color: #FFFFFF; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px;">
            {{ __('Visit DancyMeals', [], $emailLocale ?? 'en') }}
        </a>
    </div>

    {{-- Footer note --}}
    <p style="text-align: center; color: #A1A1AA; font-size: 12px; margin: 0;">
        {{ __('You received this message because you have an account on DancyMeals.', [], $emailLocale ?? 'en') }}
    </p>
@endsection
