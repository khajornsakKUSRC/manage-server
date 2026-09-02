<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light only">
<meta name="supported-color-schemes" content="light">
<title>{{ $headerText }}</title>
</head>
{{--
    The whole shell (logo, colours, layout) is driven by Settings → IT Repair
    Notification Email — see App\Mail\ItRepairNotificationMail. Colours are
    validated as #rrggbb hex on save, and every dynamic value is escaped by
    Blade, so nothing here can break out of a style attribute.

    layout = 'full'      → edge-to-edge, like a normal email in the reading pane
    layout = 'centered'  → a fixed-width column ($contentWidth px) on the background
--}}
<body style="margin:0; padding:0; width:100%; background:{{ $backgroundColor }}; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; min-width:100%; border-collapse:collapse; background:{{ $backgroundColor }};">
        <tr>
            <td align="{{ $layout === 'centered' ? 'center' : 'left' }}" style="padding:32px 28px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="{{ $layout === 'centered' ? 'width:100%; max-width:'.$contentWidth.'px; margin:0 auto;' : 'width:100%;' }} border-collapse:collapse;">
                    <tr>
                        <td align="left" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; color:{{ $textColor }}; font-size:14px; line-height:1.6;">
                            @if ($showLogo)
                                <img src="{{ $logoUrl }}" width="{{ $logoWidth }}" alt="Kasetsart University" style="display:block; width:{{ $logoWidth }}px; height:auto; border:0; outline:none; text-decoration:none; margin:0 0 24px 0;">
                            @endif
                            <h1 style="margin:0 0 16px 0; font-size:18px; line-height:1.4; font-weight:700; color:{{ $headingColor }};">{{ $headerText }}</h1>
                            <div style="white-space:pre-wrap; font-size:14px; line-height:1.6; color:{{ $textColor }};">{{ $bodyText }}</div>
                            @if (trim($footerText) !== '')
                                <div style="margin-top:28px; padding-top:16px; border-top:1px solid #e4e4e7; font-size:12px; line-height:1.5; color:#71717a; white-space:pre-wrap;">{{ $footerText }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
