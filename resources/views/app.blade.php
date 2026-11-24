<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';
                const root = document.documentElement;
                let dark = appearance === 'dark';

                if (appearance === 'system') {
                    dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                }

                root.classList.toggle('dark', dark);
                root.style.colorScheme = dark ? 'dark' : 'light';
            })();
        </script>

        {{-- FOUC: match app.css tokens before Vite CSS loads --}}
        <style>
            html {
                background-color: oklch(0.97 0.006 240);
            }

            html.dark {
                background-color: oklch(0.14 0.012 240);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
