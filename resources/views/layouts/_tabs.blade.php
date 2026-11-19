{{-- The main menu as tabs in the header. public/js/nav.js swaps them for the burger when they don't fit. --}}
<nav class="tabs" aria-label="Hoofdmenu">
    @foreach (\App\Support\MainMenu::items() as $item)
        <a href="{{ $item['url'] }}" @class(['tab', 'active' => $item['active']]) @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
    @endforeach
</nav>

<button type="button" class="burger" aria-controls="sidenav" aria-expanded="false" aria-haspopup="dialog" data-sidenav-open>
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    <span>Menu</span>
</button>
