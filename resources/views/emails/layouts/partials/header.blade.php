{{-- Email Header: DancyMeals branding with optional tenant name (BR-115, BR-116) --}}
<tr>
    <td style="padding: 32px 24px 24px; text-align: center; background-color: #0D9488;">
        {{-- DancyMeals Logo/Name --}}
        <h1 style="margin: 0; font-family: 'Playfair Display', Georgia, serif; font-size: 28px; font-weight: 700; color: #FFFFFF; letter-spacing: 0.5px;">
            {{ $appName ?? 'DancyMeals' }}
        </h1>

        @if(isset($tenantBranding) && $tenantBranding)
            {{-- Tenant brand name displayed below platform name --}}
            <p style="margin: 8px 0 0; font-family: 'Inter', -apple-system, sans-serif; font-size: 14px; color: rgba(255, 255, 255, 0.85);">
                {{ $tenantBranding['name'] }}
            </p>
        @endif
    </td>
</tr>
