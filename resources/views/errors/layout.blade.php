{{-- Layout for error pages. Deliberately without login state, session or database: a 500 or 503
     often happens exactly when those don't work, and the error page must still render. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#23290a">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/icon-192.png') }}" type="image/png">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
    <header class="site-header" data-site-header>
        <div class="container header-inner">
            <a href="{{ url('/') }}" class="brand">
                <img src="{{ asset('images/logo-200.webp') }}" alt="Gouw Waas – Scouts en Gidsen Vlaanderen" class="brand-logo" width="200" height="144">
            </a>
            @include('layouts._tabs')
        </div>
    </header>
    {{-- No login state here (see above), so the side menu only has the tabs. --}}
    @include('layouts._sidenav')
    <script src="{{ asset('js/nav.js') }}?v={{ filemtime(public_path('js/nav.js')) }}"></script>

    <main id="content" class="error-page">
        <div class="container">
            <div class="error-card">
                <img src="{{ asset('images/gezocht.png') }}" alt="" class="error-mascot" width="160" height="160">
                <div>
                    <p class="error-code">@yield('code')</p>
                    <h1>@yield('title')</h1>
                    <div class="error-message">@yield('message')</div>
                    <div class="error-actions">
                        @section('actions')
                            <a href="{{ url('/') }}" class="button">Naar de homepage</a>
                            <a href="{{ url('/activiteiten') }}" class="button button-ghost">Bekijk de activiteiten</a>
                        @show
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <div class="footer-bottom">
            <div class="container footer-bottom-inner">
                <span>© {{ date('Y') }} Gouw Waas · <a href="mailto:gouwbureau@gouwwaas.be">gouwbureau@gouwwaas.be</a></span>
                <a href="{{ url('/privacy') }}">Privacyverklaring</a>
            </div>
        </div>
    </footer>
</body>
</html>
