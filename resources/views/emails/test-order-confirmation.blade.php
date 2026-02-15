@extends('emails.layouts.base')

@section('preheader')
{{ __('Order Confirmation', [], $emailLocale ?? 'en') }} - {{ $orderNumber ?? '' }}
@endsection

@section('content')
    <h2 style="margin: 0 0 16px; font-family: 'Inter', -apple-system, sans-serif; font-size: 20px; font-weight: 600; color: #18181B;">
        {{ __('Order Confirmation', [], $emailLocale ?? 'en') }}
    </h2>

    <p style="margin: 0 0 16px; font-family: 'Inter', -apple-system, sans-serif; font-size: 15px; color: #3F3F46; line-height: 1.6;">
        {{ __('Your order has been confirmed.', [], $emailLocale ?? 'en') }}
    </p>

    @if(isset($orderNumber))
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 16px 0; background-color: #F4F4F5; border-radius: 8px;">
            <tr>
                <td style="padding: 16px;">
                    <p style="margin: 0 0 4px; font-family: 'Inter', -apple-system, sans-serif; font-size: 13px; color: #71717A;">
                        {{ __('Order Number', [], $emailLocale ?? 'en') }}
                    </p>
                    <p style="margin: 0; font-family: 'JetBrains Mono', monospace; font-size: 16px; font-weight: 600; color: #18181B;">
                        {{ $orderNumber }}
                    </p>
                </td>
                @if(isset($amount))
                    <td style="padding: 16px; text-align: right;">
                        <p style="margin: 0 0 4px; font-family: 'Inter', -apple-system, sans-serif; font-size: 13px; color: #71717A;">
                            {{ __('Amount', [], $emailLocale ?? 'en') }}
                        </p>
                        <p style="margin: 0; font-family: 'JetBrains Mono', monospace; font-size: 16px; font-weight: 600; color: #0D9488;">
                            {{ number_format($amount) }} XAF
                        </p>
                    </td>
                @endif
            </tr>
        </table>
    @endif
@endsection
