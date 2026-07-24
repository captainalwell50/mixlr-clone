@php
    /** @var string $store play_store|app_store|mac_app_store|microsoft_store|android_apk */
    $store = $store ?? 'play_store';
@endphp
@if ($store === 'android_apk')
    <svg class="store-badge-svg" viewBox="0 0 180 54" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Download Android APK">
        <rect width="180" height="54" rx="8" fill="#000"/>
        <rect x="0.75" y="0.75" width="178.5" height="52.5" rx="7.25" fill="none" stroke="#A6A6A6" stroke-width="1.5"/>
        <g transform="translate(16 12)" fill="#3DDC84">
            <path d="M4.2 8.2c0-.7.6-1.3 1.3-1.3h12.2c.7 0 1.3.6 1.3 1.3v16.4c0 .7-.6 1.3-1.3 1.3H5.5c-.7 0-1.3-.6-1.3-1.3V8.2z"/>
            <path d="M8.2 4.2l-1.6-2.4M15 4.2l1.6-2.4" stroke="#3DDC84" stroke-width="1.4" stroke-linecap="round" fill="none"/>
            <circle cx="9.2" cy="11.2" r="1.1" fill="#000"/>
            <circle cx="14" cy="11.2" r="1.1" fill="#000"/>
            <rect x="1.2" y="12" width="2.2" height="6.5" rx="1.1"/>
            <rect x="19.8" y="12" width="2.2" height="6.5" rx="1.1"/>
            <rect x="8.2" y="27.2" width="2.2" height="3.4" rx="1.1"/>
            <rect x="12.8" y="27.2" width="2.2" height="3.4" rx="1.1"/>
        </g>
        <text x="52" y="20" fill="#fff" font-family="Arial, Helvetica, sans-serif" font-size="9" letter-spacing="0.4">Download</text>
        <text x="52" y="38" fill="#fff" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="600">Android APK</text>
    </svg>
@elseif ($store === 'play_store')
    <svg class="store-badge-svg" viewBox="0 0 180 54" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Get it on Google Play">
        <rect width="180" height="54" rx="8" fill="#000"/>
        <rect x="0.75" y="0.75" width="178.5" height="52.5" rx="7.25" fill="none" stroke="#A6A6A6" stroke-width="1.5"/>
        <g transform="translate(14 11)">
            <path d="M.9 1.1c-.4.4-.7 1.1-.7 2v25.8c0 .9.3 1.6.7 2l.1.1 14.5-14.5v-.1L.9 1.1z" fill="#00D7FF"/>
            <path d="M20.7 21.8l-4.9-4.9v-.1l4.9-4.9.1.1 5.8 3.3c1.7 1 1.7 2.5 0 3.4l-5.9 3.1z" fill="#FFD400"/>
            <path d="M20.8 21.7L15.8 16.8.9 31.7c.5.5 1.2.5 2.1 0l17.8-10z" fill="#F83F37"/>
            <path d="M20.8 10.2L2.9.2C2.1-.3 1.4-.2.9.2l14.9 14.9 5-4.9z" fill="#00F076"/>
        </g>
        <text x="52" y="20" fill="#fff" font-family="Arial, Helvetica, sans-serif" font-size="9" letter-spacing="0.4">GET IT ON</text>
        <text x="52" y="38" fill="#fff" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="600">Google Play</text>
    </svg>
@elseif ($store === 'app_store')
    <svg class="store-badge-svg" viewBox="0 0 180 54" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Download on the App Store">
        <rect width="180" height="54" rx="8" fill="#000"/>
        <rect x="0.75" y="0.75" width="178.5" height="52.5" rx="7.25" fill="none" stroke="#A6A6A6" stroke-width="1.5"/>
        <g transform="translate(16 10)" fill="#fff">
            <path d="M19.2 12.7c0-2.7 2.2-4 2.3-4.1-1.3-1.8-3.2-2.1-3.9-2.1-1.7-.2-3.2 1-4.1 1s-2.1-1-3.5-.9c-1.8.0-3.5 1.1-4.4 2.7-1.9 3.3-.5 8.1 1.3 10.8.9 1.3 1.9 2.7 3.3 2.7 1.3 0 1.8-.8 3.4-.8s2 .8 3.4.8c1.4 0 2.3-1.3 3.2-2.6.9-1.4 1.3-2.8 1.3-2.9-.1 0-2.7-1-2.3-4.6zm-2.2-6.5c.7-.9 1.2-2.1 1.1-3.3-1 .1-2.3.7-3 1.6-.7.8-1.2 2-1.1 3.1 1.2.1 2.3-.5 3-1.4z"/>
        </g>
        <text x="52" y="20" fill="#fff" font-family="Arial, Helvetica, sans-serif" font-size="9" letter-spacing="0.4">Download on the</text>
        <text x="52" y="38" fill="#fff" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="600">App Store</text>
    </svg>
@elseif ($store === 'mac_app_store')
    <svg class="store-badge-svg" viewBox="0 0 180 54" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Download for Mac on the Mac App Store">
        <rect width="180" height="54" rx="8" fill="#000"/>
        <rect x="0.75" y="0.75" width="178.5" height="52.5" rx="7.25" fill="none" stroke="#A6A6A6" stroke-width="1.5"/>
        <g transform="translate(16 10)" fill="#fff">
            <path d="M19.2 12.7c0-2.7 2.2-4 2.3-4.1-1.3-1.8-3.2-2.1-3.9-2.1-1.7-.2-3.2 1-4.1 1s-2.1-1-3.5-.9c-1.8.0-3.5 1.1-4.4 2.7-1.9 3.3-.5 8.1 1.3 10.8.9 1.3 1.9 2.7 3.3 2.7 1.3 0 1.8-.8 3.4-.8s2 .8 3.4.8c1.4 0 2.3-1.3 3.2-2.6.9-1.4 1.3-2.8 1.3-2.9-.1 0-2.7-1-2.3-4.6zm-2.2-6.5c.7-.9 1.2-2.1 1.1-3.3-1 .1-2.3.7-3 1.6-.7.8-1.2 2-1.1 3.1 1.2.1 2.3-.5 3-1.4z"/>
        </g>
        <text x="52" y="20" fill="#fff" font-family="Arial, Helvetica, sans-serif" font-size="9" letter-spacing="0.4">Download for</text>
        <text x="52" y="38" fill="#fff" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="600">Mac</text>
    </svg>
@else
    <svg class="store-badge-svg" viewBox="0 0 180 54" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Get it from Microsoft">
        <rect width="180" height="54" rx="8" fill="#000"/>
        <rect x="0.75" y="0.75" width="178.5" height="52.5" rx="7.25" fill="none" stroke="#A6A6A6" stroke-width="1.5"/>
        <g transform="translate(14 13)">
            <rect width="12" height="12" fill="#F25022"/>
            <rect x="14" width="12" height="12" fill="#7FBA00"/>
            <rect y="14" width="12" height="12" fill="#00A4EF"/>
            <rect x="14" y="14" width="12" height="12" fill="#FFB900"/>
        </g>
        <text x="52" y="20" fill="#fff" font-family="Arial, Helvetica, sans-serif" font-size="9" letter-spacing="0.4">Get it from</text>
        <text x="52" y="38" fill="#fff" font-family="Arial, Helvetica, sans-serif" font-size="17" font-weight="600">Microsoft</text>
    </svg>
@endif
