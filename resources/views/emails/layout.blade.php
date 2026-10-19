<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? config('app.name', 'SoundMix Live') }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #0f172a;
            color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        table {
            border-collapse: collapse;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #1e293b;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }
        .header {
            padding: 28px 32px;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            text-align: left;
        }
        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
            text-decoration: none;
            letter-spacing: -0.5px;
        }
        .content {
            padding: 32px;
            font-size: 16px;
            line-height: 1.6;
            color: #e2e8f0;
        }
        .content h1 {
            font-size: 22px;
            font-weight: 600;
            color: #ffffff;
            margin-top: 0;
            margin-bottom: 16px;
        }
        .content p {
            margin-top: 0;
            margin-bottom: 16px;
        }
        .btn-wrapper {
            margin: 28px 0;
            text-align: left;
        }
        .btn {
            display: inline-block;
            background-color: #38bdf8;
            color: #0f172a !important;
            font-weight: 600;
            font-size: 15px;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
        }
        .box {
            background-color: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 16px 20px;
            margin: 20px 0;
        }
        .footer {
            padding: 24px 32px;
            background-color: #0f172a;
            font-size: 13px;
            line-height: 1.5;
            color: #94a3b8;
            border-top: 1px solid #334155;
        }
        .footer a {
            color: #38bdf8;
            text-decoration: none;
        }
        .footer-links {
            margin-top: 12px;
        }
        .footer-links span {
            color: #475569;
            margin: 0 8px;
        }
        @media only screen and (max-width: 600px) {
            .container {
                width: 100% !important;
                border-radius: 0 !important;
            }
            .content {
                padding: 24px 20px !important;
            }
            .header {
                padding: 20px !important;
            }
            .footer {
                padding: 20px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 24px 0; background-color: #0f172a;">
    <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center" style="padding: 0 16px;">
                <table class="container" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                    <!-- Brand Header -->
                    <tr>
                        <td class="header">
                            <a href="{{ config('app.url') }}" class="logo-text">SoundMix Live</a>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td class="content">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            <div>
                                @if(!empty($isAffiliate))
                                    Sent by <strong>SoundMix Live Affiliate Programme</strong> &bull; Reliable live audio broadcasting.
                                @else
                                    Sent by <strong>SoundMix Live</strong> &bull; Reliable live audio broadcasting.
                                @endif
                            </div>
                            <div class="footer-links">
                                @if(!empty($isAffiliate))
                                    <a href="mailto:partners@soundmix.live">Partner Support</a>
                                @else
                                    <a href="mailto:{{ $replyTo ?? 'support@soundmix.live' }}">Contact Support</a>
                                @endif
                                <span>&bull;</span>
                                <a href="{{ config('app.url') }}/terms">Terms</a>
                                <span>&bull;</span>
                                <a href="{{ config('app.url') }}/privacy">Privacy</a>
                                @if(!empty($unsubscribeUrl) && ($category ?? '') === 'marketing')
                                    <span>&bull;</span>
                                    <a href="{{ $unsubscribeUrl }}">Unsubscribe</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
