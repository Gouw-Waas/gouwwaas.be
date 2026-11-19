{{-- Side menu behind the burger: the tabs and (with $account) the login state. Opened by public/js/nav.js. --}}
<dialog class="sidenav" id="sidenav" aria-labelledby="sidenav-title" data-sidenav>
    <div class="sidenav-panel">
        <div class="sidenav-header">
            <h2 class="sidenav-title" id="sidenav-title">Menu</h2>
            <button type="button" class="sidenav-close" aria-label="Menu sluiten" data-sidenav-close>
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <nav aria-labelledby="sidenav-title">
            <ul class="sidenav-links">
                @foreach (\App\Support\MainMenu::items() as $item)
                    <li>
                        <a href="{{ $item['url'] }}" @class(['sidenav-link', 'active' => $item['active']]) @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @if ($account ?? false)
            <div class="sidenav-account">
                @auth
                    <ul class="sidenav-links">
                        <li>
                            <a href="{{ route('profiel') }}" @class(['sidenav-link', 'sidenav-profile', 'active' => request()->routeIs('profiel')])>
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                                <span><span class="visually-hidden">Profiel van </span>{{ auth()->user()->name }}</span>
                            </a>
                        </li>
                        @can('admin')
                            <li><a href="{{ route('gebruikers.index') }}" @class(['sidenav-link', 'active' => request()->routeIs('gebruikers.*')])>Gebruikers</a></li>
                        @endcan
                    </ul>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="button button-ghost button-small">Uitloggen</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="button button-small">Inloggen</a>
                @endauth
            </div>
        @endif
    </div>
</dialog>
