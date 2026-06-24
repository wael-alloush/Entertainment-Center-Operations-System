<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        @php
            $faviconIco = public_path('favicon.ico');
            $faviconPng = public_path('images/logo-icon.png');
            $faviconIcoVersion = file_exists($faviconIco) ? filemtime($faviconIco) : null;
            $faviconPngVersion = file_exists($faviconPng) ? filemtime($faviconPng) : null;
        @endphp
        @if ($faviconIcoVersion)
            <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ $faviconIcoVersion }}" sizes="32x32" type="image/x-icon">
            <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v={{ $faviconIcoVersion }}" type="image/x-icon">
        @endif
        @if ($faviconPngVersion)
            <link rel="icon" href="{{ asset('images/logo-icon.png') }}?v={{ $faviconPngVersion }}" type="image/png" sizes="32x32">
            <link rel="apple-touch-icon" href="{{ asset('images/logo-icon.png') }}?v={{ $faviconPngVersion }}">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=noto-sans-arabic:400,500,600,700" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
