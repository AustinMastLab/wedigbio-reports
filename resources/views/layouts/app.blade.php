<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (() => {
            const defaultMode = getComputedStyle(document.documentElement)
                .getPropertyValue('--default-theme-mode')
                .trim() || 'system';
            const selected = localStorage.getItem('theme') ?? defaultMode;
            const isDark = selected === 'dark' || (selected === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
        })();
    </script>
    <title>@yield('title', config('app.name'))</title>
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @stack('head')
</head>
<body class="antialiased bg-slate-50 text-slate-900 dark:bg-[#090909] dark:text-gray-100">

    <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between dark:bg-[#18181b] dark:border-white/10">
        <a href="{{ route('home') }}" class="text-xl font-semibold text-slate-900 hover:text-amber-600 dark:text-white dark:hover:text-amber-300">
            WeDigBio Reports
        </a>
        <a href="/admin" class="btn-primary">Admin Login</a>
    </header>

    <main class="max-w-6xl mx-auto px-6 py-10">
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 mt-10 dark:border-white/10">
        <p class="max-w-6xl mx-auto px-6 py-6 text-center text-xs text-slate-600 dark:text-gray-400">
            WeDigBio is funded, in part, by grants from the National Science Foundation [DBI-1115210 (2011-2018), DBI-1547229 (2016-2022), &amp; DBI-2027654 (2021-2026)]. Any opinions, findings, and conclusions or recommendations expressed in this material are those of the author(s) and do not necessarily reflect the views of the National Science Foundation.
        </p>
    </footer>

    @stack('scripts')
</body>
</html>
