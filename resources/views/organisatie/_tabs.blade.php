{{-- The tabs of Organisatie (admins): details, users and the participation certificate. --}}
<nav class="container subtabs" aria-label="Organisatie">
    @foreach ([
        ['Gegevens', route('organisatie.edit'), ['organisatie.*']],
        ['Gebruikers', route('gebruikers.index'), ['gebruikers.*']],
        ['Attest', route('attest.instellingen'), ['attest.instellingen*', 'attest.voorbeeld']],
        ['Extern attest', route('attest.extern'), ['attest.extern*']],
    ] as [$label, $url, $patterns])
        @php($active = request()->routeIs(...$patterns))
        <a href="{{ $url }}" @class(['tab', 'active' => $active]) @if ($active) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
