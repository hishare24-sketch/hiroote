<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'Hiroote AI') }}</title>

    {{-- Applied before first paint so a dark-mode reload never flashes light. --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('hiroote.theme');
                var dark = stored === 'dark' || (stored === null &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) { /* storage unavailable — fall back to light */ }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="h-full bg-surface-base font-sans text-fg-default antialiased">
    @inertia
</body>
</html>
