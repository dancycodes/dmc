{{-- Email Footer: DancyMeals footer with unsubscribe link, support contact, social links (BR-115) --}}
<tr>
    <td style="padding: 24px; background-color: #F4F4F5; border-top: 1px solid #E4E4E7;">
        {{-- Support contact --}}
        <p style="margin: 0 0 16px; font-family: 'Inter', -apple-system, sans-serif; font-size: 13px; color: #71717A; text-align: center; line-height: 1.6;">
            @if(isset($emailLocale) && $emailLocale === 'fr')
                {{ __('Need help? Contact us at', [], 'fr') }}
            @else
                {{ __('Need help? Contact us at') }}
            @endif
            <a href="mailto:{{ $supportEmail ?? 'support@dancymeals.com' }}" style="color: #0D9488; text-decoration: underline;">
                {{ $supportEmail ?? 'support@dancymeals.com' }}
            </a>
        </p>

        {{-- Divider --}}
        <hr style="margin: 0 0 16px; border: none; border-top: 1px solid #E4E4E7;">

        {{-- Platform info --}}
        <p style="margin: 0 0 8px; font-family: 'Inter', -apple-system, sans-serif; font-size: 12px; color: #A1A1AA; text-align: center; line-height: 1.5;">
            &copy; {{ $currentYear ?? now()->year }} {{ $appName ?? 'DancyMeals' }}.
            @if(isset($emailLocale) && $emailLocale === 'fr')
                {{ __('All rights reserved.', [], 'fr') }}
            @else
                {{ __('All rights reserved.') }}
            @endif
        </p>

        {{-- Unsubscribe link --}}
        <p style="margin: 0; font-family: 'Inter', -apple-system, sans-serif; font-size: 12px; color: #A1A1AA; text-align: center; line-height: 1.5;">
            <a href="{{ ($appUrl ?? config('app.url', 'https://dancymeals.com')) . '/notification-preferences' }}" style="color: #A1A1AA; text-decoration: underline;">
                @if(isset($emailLocale) && $emailLocale === 'fr')
                    {{ __('Manage notification preferences', [], 'fr') }}
                @else
                    {{ __('Manage notification preferences') }}
                @endif
            </a>
        </p>
    </td>
</tr>
