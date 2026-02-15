{{--
    DancyMeals Base Email Layout (BR-115, BR-119)

    All platform emails extend this layout for consistent branding.
    Uses inline CSS for maximum email client compatibility.
    Single-column responsive design for mobile readability.

    Dark mode: Email clients do not support Tailwind dark: classes.
    Instead, we use @media (prefers-color-scheme: dark) CSS blocks
    with dark: variant equivalent colors for email client compatibility:
    - bg-surface / dark:bg-surface => #FFFFFF / #18181B
    - text-on-surface / dark:text-on-surface => #3F3F46 / #D4D4D8
    - text-on-surface-strong / dark:text-on-surface-strong => #18181B / #F4F4F5
    - bg-surface-alt / dark:bg-surface-alt => #F4F4F5 / #27272A
    - border-outline / dark:border-outline => #E4E4E7 / #3F3F46

    Variables available:
    - $appName: Platform name (default: DancyMeals)
    - $appUrl: Platform URL
    - $supportEmail: Support contact email
    - $tenantBranding: array{name, slug}|null for tenant-context emails
    - $emailLocale: Resolved locale (en/fr) for the recipient
    - $currentYear: Current year for copyright
--}}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ $emailLocale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $appName ?? 'DancyMeals' }}</title>

    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->

    <style>
        /* Reset */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
            }
            .email-content {
                padding: 20px 16px !important;
            }
            .email-header {
                padding: 24px 16px 20px !important;
            }
            .email-footer {
                padding: 20px 16px !important;
            }
            .btn-primary {
                display: block !important;
                width: 100% !important;
                text-align: center !important;
            }
        }

        /* Dark mode support for email clients that support it */
        @media (prefers-color-scheme: dark) {
            .email-body {
                background-color: #18181B !important;
            }
            .email-container-bg {
                background-color: #27272A !important;
            }
            .email-header-bg {
                background-color: #0F766E !important;
            }
            .email-content-text {
                color: #D4D4D8 !important;
            }
            .email-content-heading {
                color: #F4F4F5 !important;
            }
            .email-footer-bg {
                background-color: #18181B !important;
                border-color: #3F3F46 !important;
            }
            .email-footer-text {
                color: #A1A1AA !important;
            }
            .email-divider {
                border-color: #3F3F46 !important;
            }
        }

        :root {
            color-scheme: light dark;
            supported-color-schemes: light dark;
        }
    </style>
</head>
<body class="email-body" style="margin: 0; padding: 0; width: 100%; background-color: #F4F4F5; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    {{-- Preheader text (hidden, shown in email client preview) --}}
    @hasSection('preheader')
        <div style="display: none; font-size: 1px; line-height: 1px; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; mso-hide: all;">
            @yield('preheader')
        </div>
    @endif

    {{-- Main container table --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #F4F4F5;">
        <tr>
            <td style="padding: 24px 12px;" align="center">
                {{-- Email card container --}}
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" class="email-container" style="width: 100%; max-width: 580px; background-color: #FFFFFF; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">

                    {{-- Header --}}
                    @include('emails.layouts.partials.header')

                    {{-- Main content --}}
                    <tr>
                        <td class="email-content" style="padding: 32px 24px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    @include('emails.layouts.partials.footer')

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
