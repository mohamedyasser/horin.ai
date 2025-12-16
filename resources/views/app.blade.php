<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" @class(['dark' => ($appearance ?? 'light') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "light" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }

                // Sync localStorage with server-set locale (from URL)
                const serverLocale = document.documentElement.lang;
                if (serverLocale === 'ar' || serverLocale === 'en') {
                    localStorage.setItem('locale', serverLocale);
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Canonical URL --}}
        <link rel="canonical" href="{{ $page['props']['seo']['canonical'] ?? url()->current() }}">

        {{-- Hreflang tags for language targeting --}}
        @if(isset($page['props']['seo']['alternates']))
            @foreach($page['props']['seo']['alternates'] as $hreflang => $url)
                <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $url }}">
            @endforeach
        @endif

        {{-- Open Graph tags --}}
        @if(isset($page['props']['seo']['og']))
            @foreach($page['props']['seo']['og'] as $property => $content)
                <meta property="{{ $property }}" content="{{ $content }}">
            @endforeach
        @endif

        {{-- Twitter Card tags --}}
        @if(isset($page['props']['seo']['twitter']))
            @foreach($page['props']['seo']['twitter'] as $name => $content)
                <meta name="{{ $name }}" content="{{ $content }}">
            @endforeach
        @endif

        {{-- JSON-LD Structured Data --}}
        @if(isset($page['props']['seo']['schemas']))
            @foreach($page['props']['seo']['schemas'] as $schema)
                <script type="application/ld+json">
                    {!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
                </script>
            @endforeach
        @endif

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Inter font --}}
        <link rel="preconnect" href="https://rsms.me/">
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        {{-- Cairo font for Arabic RTL --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200..1000&display=swap" rel="stylesheet" />

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
