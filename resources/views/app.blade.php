<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="alternate icon" href="/favicon.ico" sizes="any">

        {{-- Apply the saved (or system) theme before first paint so the page never flashes the wrong colours. --}}
        <script>
            (function () {
                let savedTheme = null;

                try {
                    savedTheme = localStorage.getItem('theme');
                } catch (error) {}

                const isDark = savedTheme ? savedTheme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;

                document.documentElement.classList.toggle('dark', isDark);
            })();
        </script>

        @fonts

        @vite('resources/js/app.js')
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
