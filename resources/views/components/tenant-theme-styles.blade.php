{{-- Tenant Theme Customization CSS Injection --}}
{{-- This component injects tenant-specific CSS custom property overrides --}}
{{-- into the <head> section. Must be placed AFTER the FOIT prevention script --}}
{{-- but BEFORE @vite to ensure overrides take priority. --}}

@if(!empty($tenantFontLink ?? ''))
    {!! $tenantFontLink !!}
@endif

@if(!empty($tenantThemeCss ?? ''))
    <style id="tenant-theme">{!! $tenantThemeCss !!}</style>
@endif
