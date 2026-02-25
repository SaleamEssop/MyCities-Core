<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'MyCities-Core') }}</title>
    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#009BA4">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MyCities">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <!-- SB Admin 2 (Bootstrap 4) + FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="/css/main.css" rel="stylesheet">
    <link href="/css/custom-admin.css" rel="stylesheet">
    <!-- User App design tokens -->
    <link href="/css/user-app.css" rel="stylesheet">
    @routes
    {!! vite(['resources/js/inertia-app.js']) !!}
    @inertiaHead
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js');
            });
        }
    </script>
</head>
<body>
    @inertia
</body>
</html>
